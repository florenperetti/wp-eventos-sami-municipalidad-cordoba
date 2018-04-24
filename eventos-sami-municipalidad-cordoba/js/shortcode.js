(function($) {
	tinymce.create('tinymce.plugins.levcba_plugin', {
        init: function(ed, url) {
            ed.addCommand('levcba_insertar_shortcode', function() {
                selected = tinyMCE.activeEditor.selection.getContent();
                var content = '';

                ed.windowManager.open({
					title: 'Listado de eventos',
					body: [{
						type: 'textbox',
						name: 'cant',
						label: 'Cantidad de Resultados'
					}],
					onsubmit: function(e) {
						ed.insertContent( '[lista_eventos_cba cant="' + e.data.cant + '"]' );
					}
				});
                tinymce.execCommand('mceInsertContent', false, content);
            });
            ed.addButton('levcba_button', {title : 'Insertar lista de eventos', cmd : 'levcba_insertar_shortcode', image: url.replace('/js', '') + '/images/logo-shortcode.png' });
        },   
    });
    tinymce.PluginManager.add('levcba_button', tinymce.plugins.levcba_plugin);
})(jQuery);