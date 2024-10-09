jQuery( document ).ready( function( $ ) {
	'use strict';

	$('.plugins-browser-filter select').on( 'change', function() {
		$('#wp-shp-browser').submit();
	});

	$('#current-page-selector').keypress( function(e) {
        if (event.keyCode == 13) {
            $('#plugin-filter').submit();
        }
    });

	$( document ).on( 'click', '.wp-shp-browser-notice .notice-dismiss', function(e) {

		var data = {
			ajax_nonce: wp_sh_plugin_browser_admin_l18n.ajax_nonce,
        	type:       $(this).closest('.wp-shp-browser-notice').attr('data-type'),
        	slugs:      $(this).closest('.wp-shp-browser-notice').attr('data-slugs'),
            action:     'wp_sh_plugin_browser_dismiss_notice'
        }

		$.post( wp_sh_plugin_browser_admin_l18n.ajaxurl, data, function( response ) {
	        console.log("response=",response);

	    });

	});


});

