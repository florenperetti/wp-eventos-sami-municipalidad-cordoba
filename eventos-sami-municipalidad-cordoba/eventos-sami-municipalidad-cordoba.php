<?php
/*
Plugin Name: Buscador de actividad p&uacute;blica con filtros de la Municipalidad de C&oacute;rdoba
Plugin URI: https://github.com/florenperetti/wp-eventos-sami-municipalidad-cordoba
Description: Este plugin genera un shortcode para incluir en una p&aacute;gina un buscador de actividades p&uacute;blicas de la Municipalidad de C&oacute;rdoba.
Version: 1.3.1
Author: Municipalidad de C&oacute;
*/

setlocale(LC_ALL,"es_ES");
date_default_timezone_set('America/Argentina/Cordoba');

add_action('plugins_loaded', array('EventosSAMIMuniCordoba', 'get_instancia'));

class EventosSAMIMuniCordoba
{
	public static $instancia = null;

	private static $MESES = array("Ene", "Abr", "Ago", "Dic");
	private static $MONTHS = array("Jan", "Apr", "Aug", "Dec");

	private static $URL_API_GOB_AB = 'https://gobiernoabierto.cordoba.gob.ar/api';

	private static $IMAGEN_PREDETERMINADA_BUSCADOR = '/images/evento-predeterminado.png';
	private static $IMAGEN_PREDETERMINADA_LISTADO = '/images/listado-default.png';

	public $nonce_busquedas = '';

	public static function get_instancia() {
		if (null == self::$instancia) {
			self::$instancia = new EventosSAMIMuniCordoba();
		} 
		return self::$instancia;
	}

	private function __construct()
	{
		add_action('wp_ajax_buscar_actividad', array($this, 'buscar_actividad')); 
		add_action('wp_ajax_nopriv_buscar_actividad', array($this, 'buscar_actividad'));
		add_action('wp_enqueue_scripts', array($this, 'cargar_assets'));

		add_shortcode('lista_eventos_cba', array($this, 'render_shortcode_lista_evento_sami'));
		add_action('init', array($this, 'boton_shortcode_lista_eventos_sami'));
	}

	public function render_shortcode_lista_evento_sami($atributos = [], $content = null, $tag = '')
	{
	    $atributos = array_change_key_case((array)$atributos, CASE_LOWER);
	    $atr = shortcode_atts([
            'disciplina' => 0,
            'tipo' => 0,
            'lugar' => 0,
			'participante' => 0,
            'cant' => 0,
            'evento' => 0,
            'agrupador' => 0,
            'titulo' => '',
			'desde' => '',
			'hasta' => '',
			'query' => '',
			'fecha' => '',
			'hoy' => '',
			'orden' => '',
			'audiencia' => '',
			'excluir_disciplina' => '',
			'menu' => '',
			'modo' => ''
        ], $atributos, $tag);

	    $filtro_disciplina = $atr['disciplina'] == 0 ? '' : '&disciplina_id='.$atr['disciplina'];
	    $filtro_tipo = $atr['tipo'] == 0 ? '' : '&tipo_id='.$atr['tipo'];
		$filtro_lugar = $atr['lugar'] == 0 ? '' : '&lugar_id='.$atr['lugar'];
		$filtro_agrupador = $atr['agrupador'] == 0 ? '' : '&agrupador_id='.$atr['agrupador'];
		$filtro_evento = $atr['evento'] == 0 ? '' : '&evento_id='.$atr['evento'];
		$filtro_participante = $atr['participante'] == 0 ? '' : '&participante_id='.$atr['participante'];
	    $filtro_cantidad = $atr['cant'] == 0 ? '' : '&page_size='.$atr['cant'];
		$limitado = $atr['cant'] != 0;
		$filtro_query = trim($atr['query']) == '' ? '' : '&q='.trim($atr['query']);
		$filtro_ordenamiento = trim($atr['orden']) != 'titulo' ? '' : trim('&ordering=titulo');
		$filtro_audiencia = trim($atr['audiencia']) <= 0 ? '' : '&audiencia_id='.trim($atr['audiencia']);
		$modo = trim($atr['modo']) ? $atr['modo'] : false;
		
		$filtro_fecha = '';
		if (trim($atr['fecha'])) {
			$fecha_inicia = str_replace('/','-',$atr['fecha']).'-23-59-59';
			$fecha_termina = str_replace('/','-',$atr['fecha']).'-23-59-59';
			$filtro_fecha = '&inicia_LTE='.$fecha_inicia.'&termina_GTE='.$fecha_termina;
		} elseif (trim($atr['desde']) || trim($atr['hasta'])) {
			$fecha_desde = trim($atr['desde']) ? '&inicia_GTE='.str_replace('/','-',$atr['desde']).'-00-00-00' : '';
			$fecha_hasta = trim($atr['hasta']) ? '&inicia_LTE='.str_replace('/','-',$atr['hasta']).'-23-59-59' : '';
			$filtro_fecha = $fecha_desde.$fecha_hasta;
		} elseif (trim($atr['hoy'])) {
			$hoy = explode('.',date('Y.m.d'));
			$hoy = $hoy[2].'-'.$hoy[1].'-'.$hoy[0];
			$filtro_fecha = '&inicia_LTE='.$hoy.'-23-59-59&termina_GTE='.$hoy.'-00-00-00';
		}

	    $url = self::$URL_API_GOB_AB.'/actividad-publica/?a=0'.$filtro_audiencia.$filtro_agrupador.$filtro_evento.$filtro_tipo.$filtro_disciplina.$filtro_lugar.$filtro_participante.$filtro_cantidad.$filtro_query.$filtro_fecha.$filtro_ordenamiento;

	    $datos = $this->obtener_datos_para_buscador($url);

	    $url_plugin = plugins_url("eventos-sami-municipalidad-cordoba");
	    $url_loading_gif = $url_plugin.'/images/loading.gif';
	    $idRandom = "bmc-" . rand(0,10000);

		$html = '<div id="'.$idRandom.'" class="bmc c-buscador eventos-sami">';

		if ($modo) {
			$html .= $this->renderFiltroModo($modo);
		}

		$html .= '<div class="c-buscador__cuerpo">
				    <div class="c-buscador__contenido">
				      <ul class="c-actividades">';

		if (count($datos['actividades']) > 0) {
			foreach ($datos['actividades']['results'] as $key => $a) {
				$dejar = true;
				if (trim($atr['excluir_disciplina'])) {
					foreach ($a['disciplinas'] as $k => $d) {
						if ($d['id'] == trim($atr['excluir_disciplina'])) {
							$dejar = false;
						}
					}
				}
				$lugar = isset($a['lugar']['nombre']) ? $a['lugar']['nombre'] : ''; 
				if ($dejar) {
					$html .= '<li data-id="'. $a['id'] .'" class="o-actividad" data-agrupador="'. $a['agrupador']['id'] .'" data-lugar="'. $a['lugar']['id'] .'" data-fecha="'. $a['rango_fecha'] .'" data-disciplina="'. $a['disciplinas_ids'] .'" data-audiencia="'. $a['audiencias_ids'] .'" data-tipo="'. $a['tipos_ids'] .'" >
				      <div class="o-actividad__informacion">
				        <div class="o-actividad__contenedor-link">
				          <div class="o-actividad__contenedor-imagen"><img class="o-actividad__imagen'.($a['es_flyer'] ? ' o-actividad__imagen--flyer' : '' ).'" data-src="'. $a['imagen_final'] .'" /></div>
				          <div class="o-actividad__contenedor-datos">
				            <div class="o-actividad__contenedor-fecha">';

					$fecha_separada = explode(' / ',$a['fecha_actividad']);
					$calendario1 = '';
					$calendario2 = '';
					if (count($fecha_separada) == 2) {
						$fechas1 = explode(" ",$fecha_separada[0]);
						$fechas2 = explode(" ",$fecha_separada[1]);
						$calendario1 = '<div class="e-fecha"><div class="mes">' . $fechas1[0] . '</div><div class="dia">' . $fechas1[1] . '</div></div>';
						$calendario2 = '<div class="e-fecha"><div class="mes">' . $fechas2[0] . '</div><div class="dia">' . $fechas2[1] . '</div></div>';
					} else {
						$fechas1 = explode(" ",$fecha_separada[0]);
						$calendario1 = '<div class="e-fecha"><div class="mes">' . $fechas1[0] . '</div><div class="dia">' . $fechas1[1] . '</div></div>';
					}

				    $html .= '<span class="o-actividad__fecha-actividad">'.$calendario1.$calendario2.'</span>
				        </div>
				        <div class="o-actividad__contenedor-descripcion">
				          <h3 title="'.$a['titulo'].'" class="o-actividad__titulo">'.$a['nombre_corto'].'</h3>
				          <p class="o-actividad__lugar">'.$lugar.'</p>
				        </div>
				      </div>
				    </div>
				    <div class="o-actividad__contenedor-botones">';

					$inicia = substr($a['inicia'], 0, -6); $termina = $a['termina'] ? substr($a['termina'], 0, -6) : $inicia;
					$html .= '<a href="http://www.google.com/calendar/event?action=TEMPLATE&text='.str_replace('"','&quot;',$a['titulo']).'&dates='.str_replace([':','-'], "",$inicia).'/'.str_replace([':','-'], "",$termina).'&details='. strip_tags($a['descripcion']) .'&location='. $lugar .'&trp=false&sprop=&sprop=name:" target="_blank" rel="nofollow" class="o-actividad__boton-calendario"><span class="icono icono-calendario"></span> <span>Agendar</span></a>
				    </div>
				  </div>
				</li>';
				}
			}
			$html .= '</ul></div>
			<img class="c-loading" src="'.$url_loading_gif.'" alt="Cargando..." />
			<div class="c-actividades--particular">
			  <div data-id="" class="o-actividad o-actividad--particular">
			    <div class="o-actividad--particular__contenedor-imagen"><img class="o-actividad--particular__imagen" alt="Evento" src="'.$url_plugin."/images/evento-predeterminado.png".'"></div>
			    <div class="o-actividad--particular__informacion">
					<h3 title="" class="o-actividad__titulo"></h3>
					<a class="c-atras" href="#">Atr&aacute;s</a>
					<p class="o-actividad__evento"></p>
					<p class="c-tipos"></p>
					<div class="o-actividad__contenedor">
						<div class="o-actividad__icono"><span class="icono icono-pin"></span></div>
						<div>
							<p class="o-actividad__lugar"></p>
						</div>
					</div>
					<div class="o-actividad__contenedor">
						<div class="o-actividad__icono"><span class="icono icono-reloj"></span></div>
						<div>
							<p class="o-actividad__fecha-inicia"></p>
							<p class="o-actividad__fecha-termina"></p>
						</div>
					</div>
					<div class="o-actividad__contenedor --precios" style="display:none">
						<div class="o-actividad__icono"><span class="icono icono-precio"></span></div>
						<div class="o-actividad--particular__precios">
						</div>
					</div>
					<div class="c-social">
						<a href="#" target="_blank" rel="nofollow" class="o-actividad__boton-calendario"><span class="icono icono-calendario"></span> <span>Agendar</span></a>
						<button class="c-social__boton c-social__boton--link"></button>
						<button class="c-social__boton c-social__boton--twitter">Twitter</button>
						<button class="c-social__boton c-social__boton--facebook">Facebook</button>
						<input class="c-social__link" type="text">
					</div>
					<p class="o-actividad__descripcion"></p>
			    </div>
			  </div>
			  </div>
				<div class="c-mensaje"><p></p><a class="c-atras" href="#">Atr&aacute;s</a></div>
			</div>
			';
			if (trim($atr['menu'])) {
				$api_response = wp_remote_get(self::$URL_API_GOB_AB.'/tipo-actividad?a=0'.$filtro_evento.$filtro_audiencia);
				$tipos_actividades = ($this->chequear_respuesta($api_response, 'los tipos de actividades'))['results'];
			  	$html .= '<div class="l-contenedor-sidebar">
			  	<!-- Barra lateral -->
			  		<aside class="c-sidebar" role="navigation">
			  		<div class="c-sidebar__header">
			  			<button class="c-sidebar__toggle">
			  				<span class="c-cruz c-cruz--fino"></span>
			  			</button>
			  			<!--<img class="c-sidebar__imagen" src="">-->
			  		</div>
			  		<div class="c-sidebar__nav">
			  			<button class="c-boton_fecha c-boton_fecha--semana" data-filtro="fecha" data-id="semana">Semana</button>
			  			<button class="c-boton_fecha c-boton_fecha--mes" data-filtro="fecha" data-id="mes">Mes</button>
			  			<button class="c-boton_fecha c-boton_fecha--ninguno">Todo</button>
			  		</div>
			  		<ul class="c-sidebar__nav">
			  			<li class="c-dropdown">
			  				<a href="#" class="c-dropdown__link" data-toggle="dropdown">
			  					Tipo de Actividad
			  					<b class="c-dropdown__caret"></b>
			  				</a>
			  				<ul class="c-dropdown__menu">';
			  				foreach($tipos_actividades as $key => $ta) {
			  					$html .= '<li class="c-dropdown__item" data-filtro="tipo" data-id="'. $ta['id'] .'">
			  						<a class="c-dropdown__link" href="#" tabindex="-1">'.
			  							$ta['nombre'] .'
			  						</a>
			  					</li>';
			  					}
			  				$html .= '</ul>
			  			</li>';
			  			/*<li class="c-dropdown">
			  				<a href="#" class="c-dropdown__link" data-toggle="dropdown">
			  					Lugares
			  					<b class="c-dropdown__caret"></b>
			  				</a>
			  				<ul class="c-dropdown__menu">';
			  				/*
		  					foreach($lugares as $key => $l) {
			  					$html .= '<li class="c-dropdown__item" data-filtro="lugar" data-id="'. $l['id'] .'">
			  						<a class="c-dropdown__link" href="#" tabindex="-1">
			  							'. $l['nombre'] .'
			  						</a>
			  					</li>';
			  					}
			  				$html .= '</ul>
			  			</li>*/
			  		$html .= '</aside>
			  		<span style="margin: 9px;font-size: 10px;">MENU</span>
			  		<button class="c-hamburger c-hamburger--3dx" tabindex="0" type="button">
			  			<span class="c-hamburger__contenido">
			  				<span class="c-hamburger__interno"></span>
			  			</span>
			  		</button>
			  	</div>';
			}
			$html .= '</div></div>
			<script src="https://cdnjs.cloudflare.com/ajax/libs/vanilla-lazyload/10.0.1/lazyload.min.js"></script>
			<style>
			.o-actividad img.loading {
				background-image: url(\''.plugins_url("actividad-publica-municipalidad-cordoba").'/images/loading.gif'.'\');
				background-size: auto !important;
				background-position: center;
				background-repeat: no-repeat;
			}
			</style>';
		}
		$html .= '</div></div>';
		return $html;
	}

	public function boton_shortcode_lista_eventos_sami() {
		if (!current_user_can('edit_posts') && !current_user_can('edit_pages') && get_user_option('rich_editing') == 'true')
			return;

		add_filter("mce_external_plugins", array($this, "registrar_tinymce_plugin")); 
		add_filter('mce_buttons', array($this, 'agregar_boton_tinymce_shortcode_lista_eventos_sami'));
	}

	public function registrar_tinymce_plugin($plugin_array) {
		$plugin_array['levcba_button'] = $this->cargar_url_asset('/js/shortcode.js');
	    return $plugin_array;
	}

	public function agregar_boton_tinymce_shortcode_lista_eventos_sami($buttons) {
	    $buttons[] = "levcba_button";
	    return $buttons;
	}

	public function cargar_assets()
	{
		$urlCSSBuscador = $this->cargar_url_asset('/css/buscadorEventos.css');
		$urlJSBuscador = $this->cargar_url_asset('/js/buscadorEventos.js');

		wp_register_style('buscador_eventos.css', $urlCSSBuscador);
		wp_register_script('buscador_eventos.js', $urlJSBuscador);

		global $post;
	    if( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'lista_eventos_cba') ) {
	        wp_enqueue_style('buscador_eventos.css', $urlCSSBuscador);

	        wp_enqueue_script(
	        	'buscar_actividades_ajax', 
	        	$urlJSBuscador, 
	        	array('jquery'), 
	        	'1.0.0',
	        	TRUE 
	        );

	        $nonce_busquedas = wp_create_nonce("buscar_actividad_nonce");

	        wp_localize_script(
	        	'buscar_actividades_ajax', 
	        	'buscarActividad', 
	        	array(
	        		'url'   => admin_url('admin-ajax.php'),
	        		'nonce' => $nonce_busquedas,
	        		'imagen' => plugins_url(self::$IMAGEN_PREDETERMINADA_BUSCADOR, __FILE__)
	        	)
	        );
	    }
	}

	public function obtener_datos_para_buscador($url)
	{
		$datos = [];

		$api_response = wp_remote_get($url);
		$datos['actividades'] = $this->chequear_respuesta($api_response, 'las actividades');

		$primerDiaSemana = date("Y-m-d", strtotime('monday this week'));
		$ultimoDiaSemana = date("Y-m-d", strtotime('sunday this week'));
		$primerDiaMes = date("Y-m-d", strtotime('first day of this month'));
		$ultimoDiaMes = date("Y-m-d", strtotime('last day of this month'));
		
		foreach($datos['actividades']['results'] as $key => $ac) {

			$inicia = date("Y-m-d", strtotime($ac['inicia']));
			$termina = date("Y-m-d", strtotime($ac['termina']));
			
			if ((($primerDiaSemana >= $inicia) && ($primerDiaSemana <= $termina)) || (($ultimoDiaSemana >= $inicia) && ($ultimoDiaSemana <= $termina))) {
				// Semana en rango
				$datos['actividades']['results'][$key]['rango_fecha'] = 'semana|mes|';
			} else if ((($primerDiaMes <= $inicia) && ($primerDiaMes >= $termina)) || (($ultimoDiaMes <= $inicia) && ($ultimoDiaMes >= $termina))) {
				$datos['actividades']['results'][$key]['rango_fecha'] = 'mes|';
			} else {
				$datos['actividades']['results'][$key]['rango_fecha'] = 'ninguno|';
			}
			
			$nombre = $ac['titulo'];
			if (strlen($nombre)>25) {
				$nombre = $this->quitar_palabras($ac['titulo'],5);
			} else {
				$nombre = $this->quitar_palabras($ac['titulo'],8);
			}
			$datos['actividades']['results'][$key]['nombre_corto'] = $nombre;
			
			$img = $ac['imagen'] ? $ac['imagen']['original'] : false;

			$imagen_final = $img;
			
			$datos['actividades']['results'][$key]['es_flyer'] = false;

			if (!$img && $ac['flyer']) {
				$imagen_final = $ac['flyer']['thumbnail_400x400'];
				$datos['actividades']['results'][$key]['es_flyer'] = true;
			} elseif (!$img) {
				$imagen_final = plugins_url(self::$IMAGEN_PREDETERMINADA_BUSCADOR, __FILE__);
			}
			
			$datos['actividades']['results'][$key]['imagen_final'] = $imagen_final;
			
			$datos['actividades']['results'][$key]['descripcion'] = $this->quitar_palabras($ac['descripcion'], 20);
			
			if ($ac['inicia']) {
				$iniciaFormat = $this->formatear_fecha_tres_caracteres($ac['inicia']);
				$terminaFormat = $ac['termina'] ? $this->formatear_fecha_tres_caracteres($ac['termina']) : $iniciaFormat;

				$datos['actividades']['results'][$key]['fecha_actividad'] = $iniciaFormat == $terminaFormat ? $iniciaFormat : $iniciaFormat . ' / ' . $terminaFormat;
			}
			// Cadena con los ids de los tipos de la actividad.
			$ids = "";
			foreach ($ac['tipos'] as $keyTipo => $tipo) {
				$ids .= $tipo["id"]."|";
			}
			$datos['actividades']['results'][$key]['tipos_ids'] = $ids;
			
			// Cadena con los ids de las audiencias.
			$ids = "";
			foreach ($ac['audiencias'] as $keyDisc => $disc) {
				$ids .= $disc["id"]."|";
			}
			$datos['actividades']['results'][$key]['audiencias_ids'] = $ids;

			// Cadena con los ids de los tipos de la actividad.
			$ids = "";
			foreach ($ac['disciplinas'] as $keyDisc => $disc) {
				$ids .= $disc["id"]."|";
			}
			$datos['actividades']['results'][$key]['disciplinas_ids'] = $ids;
		}
		
		return $datos;
	}

	/*
	* Mira si la respuesta es un error, si no lo es, cachea por una hora el resultado.
	*/
	private function chequear_respuesta($api_response, $tipoObjeto)
	{
		if (is_null($api_response)) {
			return [ 'results' => [] ];
		} else if (is_wp_error($api_response)) {
			$mensaje = $this->mostrar_error($api_response);
			return [ 'results' => [], 'error' => 'Ocurri&oacute; un error al cargar '.$tipoObjeto.'.'.$mensaje];
		} else {
			return json_decode(wp_remote_retrieve_body($api_response), true);
		}
	}

	private function renderFiltroModo($modo)
	{
		switch ($modo) {
			case 'parque_sur':
				return '<div class="c-buscador__modo"><select data-filtro="agrupador" class="c-modo__select">
							<option value="0">Todo</option>
							<option value="85">Culturales</option>
							<option value="62">Deportivas</option>
							<option value="86">Educativas</option>
							<option value="87">Eventos Especiales</option>
						</select></div>';
				break;
			case 'parque_noroeste':
				return '<div class="c-buscador__modo"><select data-filtro="agrupador" class="c-modo__select">
							<option value="0">Todo</option>
							<option value="89">Culturales</option>
							<option value="88">Deportivas</option>
							<option value="90">Educativas</option>
							<option value="91">Eventos Especiales</option>
						</select></div>';
				break;
			case 'turismo':
				$api_response = wp_remote_get(self::$URL_API_GOB_AB.'/tipo-actividad/?audiencia_id=2', ['timeout'=>10]);
				$tipos = json_decode(wp_remote_retrieve_body($api_response), true)['results'];
				$select = '<div class="c-buscador__modo"><select data-filtro="tipo" class="c-modo__select"><option value="0">Todo</option>';
				foreach ($tipos as $key => $tipo) {
					$select .= '<option value="'.$tipo['id'].'">'.$tipo['nombre'].'</option>';
				}
				$select .= '</select></div>';
				return $select;
				break;
			
			default:
				return '';
				break;
		}
	}

	public function buscar_actividad()
	{
		$id = $_REQUEST['id'];
		
		check_ajax_referer('buscar_actividad_nonce', 'nonce');
		
		if(true && $id > 0) {
			$api_response = wp_remote_get(self::$URL_API_GOB_AB.'/actividad-publica/'.$id);
			$api_data = json_decode(wp_remote_retrieve_body($api_response), true);
			
			$api_data = $this->mejorar_contenido_actividad($api_data);
			
			wp_send_json_success($api_data);
		} else {
			wp_send_json_error(array('error' => $custom_error));
		}
		
		die();
	}

	private function mejorar_contenido_actividad($actividad)
	{
		if ($actividad['inicia']) {
			$iniciaFormat = $this->formatear_fecha_tres_caracteres($actividad['inicia']);
			$terminaFormat = $this->formatear_fecha_tres_caracteres($actividad['termina']);

			$actividad['fecha_actividad'] = $iniciaFormat == $terminaFormat ? $iniciaFormat : $iniciaFormat . ' / ' . $terminaFormat;
		}
		$actividad['fecha_inicia'] = $actividad['inicia'] ? $this->formatear_fecha_inicio_fin($actividad['inicia']) : '';
		$actividad['fecha_termina'] = $actividad['termina'] ? $this->formatear_fecha_inicio_fin($actividad['termina']) : '';

		return $actividad;
	}

	/* Funciones de utilidad */

	private function mostrar_error($error)
	{
		return WP_DEBUG === true ? $error->get_error_message() : '';
	}

	private function quitar_palabras($texto, $palabras_devueltas)
	{
		$resultado = $texto;
		$texto = preg_replace('/(?<=\S,)(?=\S)/', ' ', $texto);
		$texto = str_replace("\n", " ", $texto);
		$arreglo = explode(" ", $texto);
		if (count($arreglo) <= $palabras_devueltas) {
			$resultado = $texto;
		} else {
			array_splice($arreglo, $palabras_devueltas);
			$resultado = implode(" ", $arreglo) . "...";
		}
		return $resultado;
	}

	private function formatear_fecha_tres_caracteres($timestamp)
	{
		$fecha = date_format(date_create($timestamp),"M j");
		$fecha = $this->traducir_meses($fecha); // Ene 1
		return $fecha;
	}

	private function formatear_fecha_inicio_fin($timestamp)
	{
		$fecha = strftime("%e %h, %H:%M hs.", strtotime($timestamp));
		$fecha = $this->traducir_meses($fecha);
		return $fecha;
	}

	private function formatear_fecha_listado($timestamp)
	{
		$fecha = strtotime($timestamp);
		return strftime("%e de %B, %Y", $fecha); // 7 de Abril, 2017
	}

	private function traducir_meses($texto)
	{
		return str_ireplace(self::$MONTHS, self::$MESES, $texto);
	}
	
	
	private function es_pasado($timestamp)
	{
		$fecha = date('Y.m.d', strtotime($timestamp));
		$hoy = date('Y.m.d');
		return $fecha < $hoy;
	}

	private function que_dia_es($timestamp_inicio, $timestamp_fin)
	{
		$fecha_inicio = date('Y.m.d', strtotime($timestamp_inicio));
		$fecha_fin = date('Y.m.d', strtotime($timestamp_fin));
		$hoy = date('Y.m.d');
		$maniana = date('Y.m.d', strtotime('+1 day'));
		
		if ($fecha_inicio == $maniana) {
			return 'MA&Ntilde;ANA';
		} elseif ($fecha_inicio <= $hoy) {
			return 'HOY';
		} else {
			return false;
		}
	}

	private function cargar_url_asset($ruta_archivo)
	{
		return plugins_url($this->minified($ruta_archivo), __FILE__);
	}

	// Se usan archivos minificados en producción.
	private function minified($ruta_archivo)
	{
		if (WP_DEBUG === true) {
			return $ruta_archivo;
		} else {
			$extension = strrchr($ruta_archivo, '.');
			return substr_replace($ruta_archivo, '.min'.$extension, strrpos($ruta_archivo, $extension), strlen($extension));
		}
	}
	
	private function friendly_url($str)
	{	
		$str = iconv('UTF-8','ASCII//TRANSLIT',$str);
		return strtolower( preg_replace(
			array( '#[\\s-]+#', '#[^A-Za-z0-9\. -]+#' ),
			array( '-', '' ),
			urldecode($str)
		) );
	}
}
