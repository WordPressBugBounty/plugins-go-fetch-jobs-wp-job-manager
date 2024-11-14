jQuery(document).ready(function($) {
	'use strict';

	var g_provider = {};

	var g_feed_base_url = '';
	var params          = [];
	var xhr             = '';
	var feed_loaded     = false;
	var items           = 0;
	var g_template_data = '';
	var selRegionDomain = '';
	var currProviderId  = '';
	var isFileUpload    = false;

	var g_missingRequiredParams = false;

	var template_actions = $('#templates_list + .description');

	var resetLoadType = function() {
		$('.tr-rss-toggle').hide();
		$('.tr-rss-url').show();
	}

	$('.gofj-select2').select2();

	$('#templates_list').select2({
		placeholder: goft_wpjm_admin_l18n.label_templates,
	});

	// Select2
	$('#providers_list').select2({
		width: '70%',
		minimumResultsForSearch : 20, // at least 20 results must be displayed
		templateResult          : single_provider_item,
		placeholder             : goft_wpjm_admin_l18n.label_providers,
	}).maximizeSelect2Height();

	$('#providers_list').on('select2:open', function(e) {
		$('input.select2-search__field').prop('placeholder', 'type to search provider (e.g: Indeed, Marketing, Multi-Region, etc)' )

		// Expand all groups with matches on search
		$(document).on( 'keyup', '.select2-search__field', function() {
			if ( ! $(this).val() ) {
				$('.select2-results__option:not(:first) li').hide();
			} else {
				$('.select2-results__option:not(:visible)').show();
			}
		})

		resetLoadType();
	})

	$(this).on('keyup', '.required-param', function() {
		$(this).removeClass('required');
		if ( ! $(this).val() ) {

			if ( $(this).hasClass('required-or') ) {
				var filledOptionalFields = $('.required-param.required-or').filter(function() { return $(this).val() != ""; });

				if ( ! filledOptionalFields.length ) {
					$('.required-param').addClass('required');
				}
			} else {
				$(this).addClass('required');
			}

		} else {
			// If its a filled optional required field, clear all the other fields
			if ( $(this).hasClass('required-or') ) {
				$('.required-param.required').removeClass('required');
			}
		}
		$('.no-jobs-found.required-parameters').remove();
	});

	// Scraper

	function scrapeToggleSetState( state ) {
		var $toggleEl = $('.scrape-select-all');
		$toggleEl.data( 'active', state ? 'selected' : '' );

		var label = 'selected';

		if ( ! state ) {
			label = 'none';
		}
		$toggleEl.text( $toggleEl.data( label ) );
	}

	function initScrapeToggle() {
		var $scrapeEl = $('select[name="special[scrape][]"]');
		var $scrapeElContainer = $scrapeEl.next('.select2-container');

		$('.bulk-scrape-select, .scrape-info').remove();

		if ( $scrapeEl.length ) {
			var totalItems = $scrapeEl.find('option').length;
			var selectedItems = $scrapeEl.select2('data').length;

			$scrapeElContainer.after('<div class="bulk-scrape-select"><a class="scrape-select-all" data-none="Select All" data-selected="Clear All" data-active="">Select All</a></div>');

			$('.bulk-scrape-select').after( '<div class="scrape-info secondary-container"><span class="dashicons-before dashicons-warning"></span><div> ' + goft_wpjm_admin_l18n.msg_try_scrape + ' </div>' );

			scrapeToggleSetState( totalItems === selectedItems );

			var $toggleEl = $('.scrape-select-all');

			$toggleEl.on( 'click', function(e) {
				var selected = $toggleEl.data('active') !== ''

				if ( ! selected ) {
					var items = []

					$scrapeEl.find('option').each(function(){
						items.push( $(this).val() );
					});

					$scrapeEl.val( items ).trigger('change');

					scrapeToggleSetState( true )
				} else {
					$scrapeEl.val('').trigger('change');

					scrapeToggleSetState( false );
				}
				e.preventDefault();
				return false;
			})

		}
	}

	function fillInternalCountryFields() {

		// Fill the in the reserved hidden fields.
		var regionDomainsVal = $('select[name=feed-region_domains]').val(),
			localeCodeVal    = $('select[name=feed-locale_code]').val(),
			countryVal       = $('select[name=feed-country]').val();

		$('input[name=feed-params-gofj-country-locale]').val('');

		if ( countryVal ) {
			$('input[name=feed-params-gofj-country-code]').val(countryVal);
			$('input[name=feed-params-gofj-country-name]').val($('select[name=feed-country] option:selected').text());
		} else if ( regionDomainsVal ) {
			$('input[name=feed-params-gofj-country-code]').val(regionDomainsVal);
			$('input[name=feed-params-gofj-country-name]').val($('select[name=feed-region_domains] option:selected').text());
		} else if ( localeCodeVal ) {
			$('input[name=feed-params-gofj-country-code]').val('');
			$('input[name=feed-params-gofj-country-locale]').val(localeCodeVal);
			$('input[name=feed-params-gofj-country-name]').val($('select[name=feed-locale_code] option:selected').text());
		}

	}

	function maybeLimitSchedules() {
		var valid_schedules = g_provider['invalid_schedules'] || false;
		if ( valid_schedules ) {
			jQuery('.schedule-recurrence option').each( function() {
				if ( valid_schedules.indexOf( jQuery(this).val() ) >= 0 ) {
					jQuery(this).prop('disabled', true);
				}
			})
		}
	}

	function missingCoreFields() {
		var missing = [];

		var coreFields = goft_wpjm_admin_l18n.core_fields || [];

		Object.keys(coreFields).forEach(function(key) {
			var field = coreFields[ key ];
			if ( $('[name*=field_mapping]').filter(function(){return this.value==field}).length == 0 ) {
				if ( $('.mappings-core-field').filter(function(){return this.innerHTML==field}).length == 0 ) {
					missing.push( key );
				}
			}
		});
		return missing;
	}

	// Geocomplete.
	if ( goft_wpjm_admin_l18n.geocode_api_key ) {

		var geo_options = {
			details: "form",
			detailsAttribute: "data-geo",
			country: [],
			type: ['(regions)']
		};

		if ( $('.geocomplete').length ) {

			try {
				$('.geocomplete').geocomplete( geo_options );
			}
			catch(err) {
				console.error( err.message );
				console.error( "Geolocation is not working. Please check that the google maps 'Places' library is being correctly loaded or not overriden by another plugin." );
			}

		}

	}

	// Date picker.
	$('#from_date').datepicker({
		dateFormat: 'yy-mm-dd',
		changeMonth: true,
		onClose: function( selectedDate ) {
			$( "#to_date" ).datepicker( "option", "minDate", selectedDate );
			if ( ! $( "#to_date" ).val() ) {
				$( "#to_date" ).val( $( "#from_date" ).val() );
			}
		}
	});

	$('#to_date').datepicker({
		dateFormat: 'yy-mm-dd',
		changeMonth: true,
		onClose: function( selectedDate ) {
			$( "#from_date" ).datepicker( "option", "maxDate", selectedDate );
		}
	});


	// __Init.

	init();

	function init() {
		update_hidden_sections( '[section=taxonomies], [section=meta], [section=source]' );

		// Hide rows with hidden inputs.
		$('input.hidden').closest('tr').hide();

		$('#templates_list').val('');
		$('.rss-load-images').prop( 'checked', false );
	}


	// __Select2

	// Expand optgroup's
	$(document).on( 'click', '.select2-results__options li', function() {
	    $(this).find('li').toggle();
	});

	$(document).on( 'click', '.select2-results__option', function() {

    	if ( $( '.expand-minimize', this ).hasClass('dashicons-minus') ) {
	    	$( '.expand-minimize', this ).removeClass('dashicons-minus').addClass('dashicons-plus');
	    } else {
	    	$( '.expand-minimize', this ).removeClass('dashicons-plus').addClass('dashicons-minus');
	    }

	});


	// __Tutorial / Toggle Settings

	var guidedTourContentLoaded = false;

	// Init tutorial related.
	$(document).on( 'guided_tour.init', function( e, data ) {

		if ( guidedTourContentLoaded ) return;

		// Make sure the advanced options are visible during the tour.
		toggle_settings('advanced');

		// Resume Tutorial if still active.
		$(document).on( 'goftj_rss_content_loaded', function( e, data ) {
			if ( goft_wpjm_admin_l18n.can_use_premium === 'yes' ) {
				$('.tr-auto-schedule').show();
			}
			if ( ! guidedTourContentLoaded ) {
				$(document).guided_tour();
			}
			guidedTourContentLoaded = true;
		});

		$(document).on( 'click', '.button.gofetch-wpjm-tour1_0_step4_2', function() {
			$('#rss_feed_import').val( $('#rss_feed_import').data('example') );
			$('.import-feed').click();
		})

		$(document).on( 'click', '.button.gofetch-wpjm-tour1_0_step13', function() {
			$('.tr-auto-schedule').show();
		})

	})

	// On toggle basic/advanced settings.
	$(document).on( 'change', '#goft-settings-type', function(e) {

		var toggle = $( '#goft-settings-type:checked' ).val();

		var data = {
			action      : 'goft_wpjm_toggle_settings',
			toggle      : toggle,
			_ajax_nonce : goft_wpjm_admin_l18n.ajax_nonce,
		};

		$.post( goft_wpjm_admin_l18n.ajaxurl, data );

		toggle_settings( toggle );

		toggle_settings_apply();
	});

	// Load the saved mappings after the feed is loaded
	$(document).on( 'goftj_rss_template_loaded', function( e, template, data ) {

		if ( data.provider_id ) {
			var urlParts = data.rss_feed_import.split('?');
			if ( urlParts.length ) {
				var searchParams = new URLSearchParams( urlParts[1] );
				var skipResetImport = true;
				$('#providers_list').val( data.provider_id ).trigger( 'change', [ searchParams, data, skipResetImport ] );
			}
		}

		templateHasSchedule( template );

		// Load the feed from the template.
		$('.import-feed:visible').click();
	})

	$(document).on( 'goftj_rss_content_loaded', function( e, provider ) {
		feed_loaded = true;
		toggle_settings_apply();

		$('.helper-fields').append( '<input type="hidden" name="provider_id" value="' + provider.id + '"></input>' );

		$('.tr-special-scrape-options').removeClass('temp-tr-hide tr-advanced tr-advanced-show').hide();

		templateHasScheduleToggle( true );

		// Dynamic special options.
		if ( typeof provider.special !== 'undefined' ) {

			$.each( provider.special, function( option, obj ) {

				$('[data-special=' + option + ']').find('option').remove();

				Object.keys( obj ).forEach( function (key) {

					var selected = false;

					if ( 'undefined' !== typeof g_template_data['special'] && g_template_data['special'] && 'undefined' !== typeof g_template_data['special'][ option ] ) {
						selected = g_template_data['special'][ option ].indexOf( key ) >= 0 ? 'selected' : false;
					} else {

						if ( 'undefined' !== typeof goft_wpjm_admin_l18n.scrape_fields && goft_wpjm_admin_l18n.scrape_fields && Object.keys( goft_wpjm_admin_l18n.scrape_fields ).length ) {
							selected = Object.keys( goft_wpjm_admin_l18n.scrape_fields).find(index => goft_wpjm_admin_l18n.scrape_fields[index] === key ) >= 0 ? 'selected' : false;
						}

					}

					$('[data-special=' + option + ']')
						.find('option')
						.end()
						.append( $('<option></option>')
						.val( key )
						.text( obj[key].nicename )
						.prop( 'selected', selected )
                    );

				});

				$('.tr-special-' + option + '-options').hide();

				$('[data-special=' + option + ']').select2({
					placeholder : goft_wpjm_admin_l18n.label_scrape_fields,
				});

				$('.tr-special-' + option + '-options').removeClass('tr-hide').addClass('tr-advanced tr-advanced-show').show();
			});

			$('.tr-special-scrape-options').addClass('temp-tr-hide')

			initScrapeToggle();
		} else {
			$('.tr-special-scrape-options').hide();
		}

		var remoteOnlyJobs = provider.remote_only || false;
		update_section_meta( 'data-remote=1', remoteOnlyJobs );

		if ( $('.tr-rss-file').is(':visible') ) {
			$('.tr-save').hide();
			$('.tr-template-name').hide();
			var skipResetImport = true;
			$('#providers_list').val('').trigger( 'change', [ '', '', skipResetImport ] );
		} else {
			$('.tr-save').show();
			$('.tr-template-name').show();
		}

		fillInternalCountryFields();
	})

	$('#goft-settings-type').trigger('change');


	// __Core Events.

	$(document).on( 'click', '.feed-type-toggle', function(e) {
		$('.tr-rss-toggle').toggle();

		var isFileUpload = $('.tr-rss-file').is(':visible');

		$('.tr-save, .tr-template-name, .save-warning').toggle( ! isFileUpload );
		$('.tr-replace-jobs').toggle( ! isFileUpload );

		return false;
	})

	// On refresh templates.
	$(document).on( 'click', '.refresh-templates', function(e) {

		$('#templates_list option').filter( function() {
			return this.value;
		}).remove();

		if ( ! $('.goft_wpjm.processing.templates').length ) {

			template_actions.hide().after('<div class="goft_wpjm processing templates">&nbsp;</div>');

			$('.import-feed').prop( 'disabled', true );
		}

		var data = {
			action      : 'goft_wpjm_update_templates_list',
			_ajax_nonce : goft_wpjm_admin_l18n.ajax_nonce,
		};

		$.post( goft_wpjm_admin_l18n.ajaxurl, data, function( response ) {

			if ( typeof response !== 'undefined' && typeof response.templates !== 'undefined' ) {

				$.each( response.templates, function( key, value ) {

					var template_exists = false;

					$("#templates_list option").filter( function(i){
						if ( $(this).attr("value").indexOf( value ) !== -1 ) {
							template_exists = true;
							return;
						}
					});

					if ( template_exists ) {
						return;
					}

					$('#templates_list').append( $( '<option>', { value : value } ).text( value ) );
				});

				$('#templates_list').trigger('change');
			}

			$('.goft_wpjm.processing.templates').remove();
			$('.import-feed').prop( 'disabled', false );

			template_actions.show();

		}, 'json' );


		e.preventDefault();
		return false;
	});

	// On load feed.
	$(document).on( 'click', '.import-feed', function(e) {
		$('.feed-type-toggle').hide();

		isFileUpload = $('.tr-rss-file').is(':visible');

		var url = '';

		var form     = $('#gofj_import')[0],
		    formData = new FormData( form );

		var fileInput = document.getElementById('import_local_file');
		var file = fileInput.files[0] || false;

		if ( isFileUpload && file ) {
			isFileUpload = true;
			url = file.name;

			formData.append( 'file_upload', 1 );
		} else {
			url = $('#rss_feed_import').val();

			formData.append( 'file_upload', 0 );
		}

		$('.limit-warn').remove();

		if ( ! url ) {
			alert( goft_wpjm_admin_l18n.msg_specify_valid_url );
			e.preventDefault();
			return;
		}

		enable_import( false );

		$('.goft_wpjm.no-jobs-found').remove();

		var canLoadFeed = ! $('.feed-builder').find('input.required').length;

		if ( ! canLoadFeed ) {
			var $importSelector = $('#rss_feed_import')
			//alert( goft_wpjm_admin_l18n.msg_specify_valid_url );
			$('.goft_wpjm.no-jobs-found').remove();

			var message = goft_wpjm_admin_l18n.msg_required_params;
			if ( $('.feed-builder').find('input.required-param.required-or').length ) {
				message = goft_wpjm_admin_l18n.msg_required_optional_params;
			}
			$importSelector.closest('label').after('<p class="goft_wpjm no-jobs-found required-parameters feed"> <span class="dashicons-before dashicons-warning"></span> ' + message + '</p>');
			e.preventDefault();
			return;
		}

		if ( ! $('.goft_wpjm.processing-dog.feed').length ) {
			$('input[name=import_feed]').hide();
			$('input[name=import_feed]').after('<span class="goft_wpjm processing-dog feed">&nbsp;</span>').after('<a class="button-secondary cancel-feed-load">' + goft_wpjm_admin_l18n.cancel_feed_load + '</a>');
			$('input', template_actions).prop( 'disabled', true );
		}

		formData.append( 'url', encodeURI( url ) );
		formData.append( 'action', 'goft_wpjm_import_feed' );
		formData.append( 'load_images', $('input[name=load_images]').is(':checked') );
		formData.append( '_ajax_nonce', goft_wpjm_admin_l18n.ajax_nonce );

		xhr = $.ajax({
			url        : goft_wpjm_admin_l18n.ajaxurl,
			contentType: false,
			processData: false,
			dataType   : 'json',
			data       : formData,
			type       : 'POST',
			success    : function(response) {

			//xhr = $.post( goft_wpjm_admin_l18n.ajaxurl, data, function( response ) {

				if ( typeof response !== 'undefined' ) {

					var $importSelector = $('#rss_feed_import')

					if ( isFileUpload ) {
						$importSelector = $('#import_local_file');
					}

					if ( typeof response.success !== 'undefined' && ! response.success ) {
						response.error = response.data;
					}

					if ( typeof response.error !== 'undefined'  ) {

						$importSelector.closest('label').after('<p class="goft_wpjm no-jobs-found feed"> <span class="dashicons-before dashicons-warning"></span> ' + goft_wpjm_admin_l18n.msg_invalid_feed + ': ' + response.error + '</p>' );

					} else if ( response.total_items > 0 ) {

						$('.goft_wpjm_jobs_found').remove();

						$('.goft_wpjm_table').html( response.sample_item );

						$('.goft_wpjm_jobs_found').remove();
						$('.content-type-warning').remove();

						$('.goft_wpjm_table').after( '<h4 class="goft_wpjm_jobs_found"><div class="available-jobs">' + response.total_items + '</div><div class="available-jobs-desc">' + goft_wpjm_admin_l18n.msg_jobs_found + '</div></h4>');

						if ( response.total_items > goft_wpjm_admin_l18n.jobs_limit_warn && goft_wpjm_admin_l18n.msg_jobs_limit_warn ) {
							$('.limit-warn').remove();
							$('.goft_wpjm_jobs_found').after( '<div class="limit-warn secondary-container"><span class="dashicons-before dashicons-warning"></span><div> ' + goft_wpjm_admin_l18n.msg_jobs_limit_warn + ' </div></p>' );
						}

						$('input[name=content_type]').val( 'RSS' );

						if ( isFileUpload ) {
							$('input[name=content_type]').val( response.content_type );
							$('.goft_wpjm_jobs_found').after( '<div class="provider-data content-type-warning secondary-container sample-table-notes">' + goft_wpjm_admin_l18n.msg_file_loaded.replace( '[content_type]', response.content_type.toUpperCase() ) + '</div>' );
						} else if ( response.content_type && response.content_type.toUpperCase() !== 'RSS' ) {
							$('input[name=content_type]').val( response.content_type );
							$('.goft_wpjm_jobs_found').after( '<div class="provider-data content-type-warning secondary-container sample-table-notes">' + goft_wpjm_admin_l18n.msg_not_rss_feed.replace( '[content_type]', response.content_type.toUpperCase() ) + '</div>' );
						}

						$('.limit-jobs-found').remove();
						$('#limit').after( '<small class="limit-jobs-found">' + goft_wpjm_admin_l18n.msg_jobs_found + ': <strong>' + response.total_items + '</strong></small>' );

						// Default the limit to the max number of items.
						if ( $('#limit').val() > response.total_items ) {
							$('#limit').val( response.total_items );
						}

						var load_only_defaults = $('#templates_list').val() && ! is_template_new_provider();

						if ( response.provider ) {
							auto_fill_provider_details( response.provider, load_only_defaults );
							auto_fill_custom_fields_defaults( response.provider );
						}

						if ( ! $('#templates_list').val() ) {
							$('#template_name').val( generate_default_template_name( url ) );
						}

						enable_import( true );

						currProviderId = '';

						if ( typeof response.provider !== 'undefined' && response.provider ) {
							currProviderId = response.provider.id;
						}

						$.event.trigger({
							type: "goftj_rss_content_loaded",
						}, [ response.provider ] );

					} else {

						$importSelector.closest('label').after('<p class="goft_wpjm no-jobs-found feed"> <span class="dashicons-before dashicons-warning"></span> ' + goft_wpjm_admin_l18n.msg_no_jobs_found + '</p>');
					}

				}

				$('.goft_wpjm.processing-dog').remove();
				$('.cancel-feed-load').remove();

				$('input[name=import_feed]').show();
				$('input', template_actions).prop( 'disabled', false );

				update_hidden_sections( '[section=taxonomies], [section=meta], [section=source]' );

				$('.feed-type-toggle').show();
			},
			fail: function( error ) {
				$('.feed-type-toggle').show();
				console.error(error);
			},
		});

		e.preventDefault();
		return false;
	})

	// On cancel load feed.
	$(document).on( 'click', '.cancel-feed-load', function(e) {

		xhr.abort();

		$(this).remove();

		$('.goft_wpjm.processing-dog').remove();
		$('input[name=import_feed]').show();
		$('input', template_actions).prop( 'disabled', false );

		e.preventDefault();
		return false;

	});

	// On save template.
	$(document).on( 'click', '.save-template', function(e) {
		var template_name = $('#template_name').val();

		if ( ! template_name ) {
			alert( goft_wpjm_admin_l18n.msg_template_missing );
			return;
		}

		if ( ! $('.goft_wpjm.processing.save').length ) {
			$('.save-template').hide();
			$('.save-template').after('<span class="goft_wpjm processing save">&nbsp;</span>');
		}

		var special_options = {};

		// @todo: iterate trough the special input to get all key/values and store them in 'special_options'
		$('[name^="special"]').each( function( i, obj ) {
			special_options[ $(obj).data('special') ] = $( obj ).val();
		});

		function process_mappings( serializedMappings ) {

			var processedMappings = {};

			var regex = /field_mappings\[(.*?)\]/;
			var subst = '$1';

			$.each( serializedMappings, function( index, mapping ) {
				var key = mapping['name'].replace( regex, subst );
				processedMappings[ key ] = mapping['value'];
			});
			return processedMappings;
		}

		var serializedMappings = $('[name*=field_mappings]').serializeArray();

		var regionDomainsVal = $('select[name=feed-region_domains]').val(),
		    localeCodeVal    = $('select[name=feed-locale_code]').val(),
		    coVal            = $('select[name=feed-co]').val(),
		    countryVal       = $('select[name=feed-country]').val();

		var data = {
			action         : 'goft_wpjm_save_template',
			template       : template_name,
			provider_id    : currProviderId,
			logos          : Number( $('input[name=logos]').is(':checked') ),
			smart_tax_input: $('select[name=smart_tax_input]').val(),
			special        : special_options,
			rss_feed_import: $('#rss_feed_import').val(),
			replace_jobs   : $('input[name=replace_jobs]').is(':checked') ? 'yes': '',
			mappings       : serializedMappings,
			_wpnonce       : goft_wpjm_admin_l18n.ajax_save_nonce,
			field_mappings : process_mappings( serializedMappings ),
			region_domains: regionDomainsVal,
			locale_code   : localeCodeVal,
			co            : coVal,
			country       : countryVal,
		};

		// Dynamically get all meta and taxonomy input data.
		$.each( [ 'tax_input', 'meta', 'source' ], function( idx, element ) {

			$('[name*="' + element + '"]').each( function( i, el ) {

				if ( $(el).is(':checkbox') ) {
					data[ $(this).prop('name') ] = $(el).is(':checked') ? $(this).data('default') : '';
				} else {
					data[ $(this).prop('name') ] = $(this).val();
				}
			});

		});

		$.post( goft_wpjm_admin_l18n.ajaxurl, data, function( response ) {

			$('.save-template').show();

			if ( typeof response !== 'undefined' && '1' === response) {

				$('.save-template').after('<span class="template-saved-msg"> ' + goft_wpjm_admin_l18n.msg_template_saved + '</span>');

				var template_exists = false;

 				$("#templates_list option").filter( function(i){
       				if ( $(this).attr("value").indexOf( template_name ) !== -1 ) {
       					template_exists = true;
       					return;
       				}
				});

				if ( ! template_exists ) {
					$('#templates_list').append( $( '<option>', { value : template_name } ).text( template_name ) );
				}

				$('#templates_list').val( template_name );

				// Display auto scheduling options if there's no schedule using the saved template.
				if ( ! templateHasSchedule( template_name ) ) {
					$('.tr-auto-schedule').show();
				} else {
					$('.tr-auto-schedule').hide();
				}

			} else {

				$('.save-template').after('<span class="template-saved-msg"> ' + goft_wpjm_admin_l18n.msg_save_error + '</span>');

			}

			$('.template-saved-msg').delay(2000).fadeOut();

			$('.goft_wpjm.processing.save').remove();

		})

		e.preventDefault();
		return false;
	});

	// On template change.
	$(document).on( 'change', '#templates_list', function() {
		var template_obj = $(this);
		var template     = template_obj.val()

		// Hide any visible sections.
		toggle_settings_groups( false );

		enable_import( false );

		if ( ! $('.goft_wpjm.processing.templates').length ) {
			template_actions.hide().after('<div class="goft_wpjm processing templates">&nbsp;</div>');
		}

		var data = {
			action      : 'goft_wpjm_load_template_content',
			template    : template,
			_ajax_nonce : goft_wpjm_admin_l18n.ajax_nonce,
		};

		resetLoadType();

		$.post( goft_wpjm_admin_l18n.ajaxurl, data, function( response ) {

			if ( ! response ) {

				$('#rss_feed_import').val('');

			} else {

				var feed_url = response['rss_feed_import'];

				// Load settings from the template.

				$('#rss_feed_import').val( decodeURI( feed_url ) );
				$('#template_name').val( template_obj.val() );

				$('input[name=logos]').prop( 'checked', Boolean( response['logos'] ) );
				$('select[name=smart_tax_input]').val( response['smart_tax_input'] );
				$('#rss_feed_import').attr( 'data-saved-url', feed_url );
				$('input[name=replace_jobs]').prop( 'checked', response['replace_jobs'] === 'yes' );

				$.each( [ 'tax_input', 'meta', 'source' ], function( idx, element ) {

					$.each( response[ element ], function( index, value ) {

						var object = '[name="' + element + '[' + index + ']"]';

						if ( $( object ).is(':checkbox') ) {

							if ( ! value ) {
								$( object ).val( $( object ).data('default') );
								$( object ).prop( 'checked', false );
							} else {
								$( object ).prop( 'checked', true );
							}
							return;
						}

						$( object ).val( value ).prop( 'selected', true ).change();

						if ( $( object ).hasClass('goft-image') ) {

							if ( value ) {
								var td = $( object ).closest('td');
								$( 'img', td ).attr( 'src', value );
							}

						}

					})

				})

				// Load the saved mappings after the feed is loaded
				$(document).on( 'goftj_rss_content_loaded', function( e, template, data ) {

					$.each( response['mappings'], function( idx, el ) {
						$("[name='" + el.name + "']").val( el.value ).prop( 'selected', true ).change();
					});

				})

				$.event.trigger({
					type: "goftj_rss_template_loaded",
				}, [ template, response ] );

				g_template_data = response;
			}

			$('.goft_wpjm.processing.templates').remove();

			template_actions.show();

		}, 'json' );

		// Clear the providers selection.
		$('#providers_list').val('').change();
	});

	// On load feed.
	$(document).on( 'click', '.import-posts', function(e) {
		var missingFields = missingCoreFields();

		if ( missingFields.length || $('.import-posts').hasClass('goft-disabled') ) {

			if ( missingFields.length ) {
				alert( 'There are core unmapped fields:\n - ' + missingFields.join('\n - ' )  + '\n\nPlease map them, and try again.');
			}
			e.preventDefault();
			return false;
		}


		$('.import-posts').after('<div class="goft_wpjm processing-dog">&nbsp;</div>');
		$('.import-posts + .goft_wpjm.processing-dog').after( '<p class="import-wait-message">' + goft_wpjm_admin_l18n.msg_import_jobs + '</p>' );
		$('.import-posts').addClass('goft-disabled');
	});

	// On sections values change.
	$('[section=taxonomies],[section=meta], [section=source]').on( 'change', function() {
		update_hidden_sections( '[section=' + $(this).attr('section') + ']' );
	});


	// __Providers Events.

	// On provider change.
	$(document).on( 'change', '#providers_list', function( e, searchParams, savedData, skipResetImport ) {
		var $providers_list = $(this);

		skipResetImport = skipResetImport | false;

		if ( ! skipResetImport ) {
			enable_import( false );
		}

		$('.goft_wpjm.processing.providers').remove();

		if ( ! $providers_list.val() ) {
			$('.providers-placeholder').closest('tr').hide();
			return;
		}

		if ( ! $('.goft_wpjm.processing.templates').length ) {
			$('#providers_list').next('.select2').after('<div class="goft_wpjm processing providers">&nbsp;</div>');
		}

		var data = {
			action      : 'goft_wpjm_load_provider_info',
			provider    : $providers_list.val(),
			_ajax_nonce : goft_wpjm_admin_l18n.ajax_nonce,
		};

		$.post( goft_wpjm_admin_l18n.ajaxurl, data, function( response ) {

			if ( response ) {
				$('.providers-placeholder-content').html( response['setup'] ).closest('tr').fadeIn();
				$('.providers-placeholder-content').append( '<a class="dashicons-before dashicons-dismiss close-provider" title="' + goft_wpjm_admin_l18n.title_close + '" style="cursor: pointer;"s> hide</a>' );

				$('.close-provider').click( function() {
					$providers_list.val('').change();
				});

				$('.providers-placeholder').slideDown();

				pre_fill_provider_rss_builder( response['provider'], searchParams );
			}

			$('.goft_wpjm.processing.providers').remove();

			$.event.trigger({
				type: "goftj_rss_provider_loaded",
			}, [ response.provider, response.required_query_params, savedData ] );

		}, 'json' );

		g_feed_base_url = '';
		params          = [];
	});

	// On provider click.
	$(document).on( 'click', 'a.provider-rss, a.provider-rss-custom', function(e) {

		$('.rss-copied-msg').remove();

		$('input[name=rss_feed_import]').val( $(this).attr('href') ).addClass('input-feed-pasted');

		$(this).after('<span class="rss-copied-msg" style="display: inline-block"> ' + goft_wpjm_admin_l18n.msg_rss_copied + '</span>').fadeIn();
		$('.rss-copied-msg').delay(1000).fadeOut( function(){
			$('input[name=rss_feed_import]').removeClass('input-feed-pasted');
		})

		resetLoadType();

		e.preventDefault();
		return false;
	});

	// On provider section expand.
	$(document).on( 'click', '[class*=provider-expand]', function(e) {

		var oclick = $(this);
		var ochild = $( '.' + oclick.attr('data-child') );

		ochild.slideToggle( update_provider_help_links( oclick, ! ochild.is(':visible') ) );

		e.preventDefault();
		return false;
	});

	// __Provider RSS builder events.

	// On any RSS builder field keyup.
	$(document).on( 'change', '.feed-builder [name*=feed]', function() {
		$(this).keyup();
	});

	$(document).on( 'keyup', '.feed-builder [name*=feed]:not(input[name="feed-url"])', function() {
		var query_arg       = $(this).attr('data-qarg');
		var param           = $('[name=' + query_arg + ']').val();
		var value           = $(this).val();
		var feed_params_sep = $('.feed-builder input[name=feed-params-sep]').val();
		var is_param_prefix = $('[name=' + query_arg + ']').attr('data-prefix');

		params[ query_arg ] = [];

		if ( ! value ) {
			value = '';
		}

		value = encodeURIComponent( value );

		if ( '&' === feed_params_sep ) {
			param += '=' + value;
		} else {
			param += ( ! is_param_prefix ? feed_params_sep : '' ) + value;
		}

		params[ query_arg ] = param;
		//console.log("query_arg = " + query_arg + "; param="+param);

		build_provider_rss_feed( params );
	});

	// On RSS builder feed URL field keyup.
	$(document).on( 'keyup', '.feed-builder input[name=feed-url]', function( e, params) {
		params = params || false;

		var m = false;

		g_feed_base_url = get_provider_feed_base_url();

		var is_domain_location = 'domain_l' === $('.feed-builder input[name=feed-param-location]').val();
		var is_domain_keyword = 'domain_k' === $('.feed-builder input[name=feed-param-keyword]').val();

		// If the feed change was manually triggered avoid bubbling on the param that triggered this change
		if ( params && typeof params.key !== 'undefined' ) {

			$('.feed-builder .params [name*=feed-]:not([name=feed-' + params.key + ']):visible').keyup();

		} else {

			// Check for the special 'domain_l' parameter name for locations in the feed URL.
			if ( is_domain_location ) {
				var regex = new RegExp( $('.feed-builder input[name=feed-param-location]').attr('data-regex') );

				if ( ( m = regex.exec( g_feed_base_url ) ) !== null ) {
					$('.feed-builder input[name=feed-location]').val( m[1] );
				}
			}

			// Check for the special 'domain_l' parameter name for locations in the feed URL.
			if ( is_domain_keyword ) {
				var regex = new RegExp( $('.feed-builder input[name=feed-param-keyword]').attr('data-regex') );

				if ( ( m = regex.exec( g_feed_base_url ) ) !== null ) {
					$('.feed-builder input[name=feed-keyword]').val( m[1] );
				}
			}

			$('.feed-builder .params [name*=feed-]:visible').not('.feed-builder input[name=feed-url]').keyup();
		}
	})

	// Update the feed URL based on the selected region domain
	$(document).on( 'change', 'select[name="feed-region_domains"]', function() {
		var currURL = $('input[name="feed-url"]').val();
		var newSelRegionDomain = $(this).val();
		var newURL = currURL.replace( selRegionDomain, newSelRegionDomain )
		$('input[name="feed-url"]').val( newURL ).keyup();
		selRegionDomain = newSelRegionDomain;
	});

	// Set default values for the provider
	$(document).on( 'goftj_rss_provider_loaded', function( e, params, required_query_params, savedData ) {
		var currFeedURL = $('.feed-builder input[name=feed-url]').val();

		g_provider = params;

		selRegionDomain = $('[name=feed-region_domains]').val();

		var is_domain_keyword = 'domain_k' === $('.feed-builder input[name=feed-param-keyword]').val();
		var is_domain_location = 'domain_l' === $('.feed-builder input[name=feed-param-location]').val();

		// Set the default values if there's no template data saved for this provider, yet
		if ( params['region_domains'] ) {
			var paramDomainField = params['region_param_domain'] || false;
			if ( ! paramDomainField ) {
				paramDomainField = 'region_domains'
			}
			var regionDomain = savedData && typeof savedData[ paramDomainField ] !== 'undefined' && savedData[ paramDomainField ] ? savedData[ paramDomainField ] : params['region_default'];

			if ( regionDomain ) {
				// If the saved value is not valid go back to the default
				var isValidValue = $('select[name=feed-' + paramDomainField + '] option[value="' + regionDomain + '"]').length
				if ( ! isValidValue ) {
					regionDomain = params['region_default'];
				}
			}
			$('select[name=feed-' + paramDomainField + ']').val( regionDomain ).change();
		}
		///

		if ( required_query_params ) {
			g_missingRequiredParams = required_query_params;

			$('.feed-builder input[name*=feed-]').removeClass( 'required-param required-or' );

			var classes = 'required-param';

			Object.keys( g_missingRequiredParams ).forEach( function( key ) {
				let $el = $('.feed-builder input[name=feed-' + key + ']');

				if ( key === 'relation' ) {
					if ( g_missingRequiredParams[ key ] === 'relation_or' ) {
						classes += ' required-or';
					}
				} else {
					if ( ! $el.val() ) {
						$('.feed-builder input[name=feed-' + key + ']').addClass( classes );
					}
				}

				$('.required-param').keyup();
			});

		}

		if ( ! is_domain_keyword && ! is_domain_location ) return;

		var currKeyword, currLocation;

		$('.gofj-multiselect').select2({
			templateResult: single_provider_item,
		});

		$('.gofj-multiselect').on('select2:open', function(e) {
			$('input.select2-search__field').prop('placeholder', 'type to search ... ' )
		});

		// On RSS builder feed location field keyup.
		$(document).on( 'keyup', '.feed-builder input[name=feed-location]', function() {
			if ( ! is_domain_location ) return;

			var regex = new RegExp( $('.feed-builder input[name=feed-param-location]').attr('data-regex') );
			var regexMatches = regex.exec( g_feed_base_url );

			if ( ! regexMatches.length ) return;

			currLocation = regexMatches[1] || false;

			// Check for the special 'domain_l' parameter name for locations in the feed URL.
			var newLocation = $(this).val();

			if ( currLocation && newLocation && newLocation !== currLocation ) {
				$('.feed-builder input[name=feed-url]').val( currFeedURL.replace( currLocation, newLocation ) );
				$('.feed-builder input[name=feed-url]').trigger( 'keyup', { key: 'location' } );
				currFeedURL = $('.feed-builder input[name=feed-url]').val();
				currLocation = newLocation;
			}

		})

		// On RSS builder feed keyword field keyup.
		$(document).on( 'keyup', '.feed-builder input[name=feed-keyword]', function() {
			if ( ! is_domain_keyword ) return;

			var regex = new RegExp( $('.feed-builder input[name=feed-param-keyword]').attr('data-regex') );
			var regexMatches = regex.exec( g_feed_base_url );

			if ( ! regexMatches.length ) return;

			currKeyword = regexMatches[1] || false;

			// Check for the special 'domain_l' or 'domain_k' parameter names for locations and keywords in the feed URL.
			var newKeyword = $(this).val();

			if ( currKeyword && newKeyword && newKeyword !== currKeyword ) {
				$('.feed-builder input[name=feed-url]').val( currFeedURL.replace( currKeyword, newKeyword ) );
				$('.feed-builder input[name=feed-url]').trigger( 'keyup', { key: 'keyword' } );
				currFeedURL = $('.feed-builder input[name=feed-url]').val();
				currKeyword = newKeyword;
			}

		})

	})

	// On reset feed URL.
	$(document).on( 'click', '.reset-feed-url', function(e) {
		$('.feed-builder input[name=feed-url]').val( $('.feed-builder input[name=feed-url]').data('default') ).keyup();
		e.preventDefault();
		return false;
	});

	// On keywords filtering click.
	$(document).on( 'click', 'input[name=keywords_filtering]', function(e) {
		if ( $(this).is(':checked') ) {
			$('.tr-keywords.tr-toggle-hide').show();
		} else {
			$('.tr-keywords.tr-toggle-hide').hide();
		}

	});


	// __Date events.


	// On clear date.
	$(document).on( 'click', '.clear_span_dates', function() {
		$( '.' + $(this).attr('data-goft_parent') ).val('');
	});


	// __Other events.


	// On reset values.
	$(document).on( 'click', '.reset-val', function(e) {

		var parent = $( 'input[name="' + $(this).attr('data-parent') + '"' );

		$( parent ).val( $(parent).attr('data-original') ).change();
		e.preventDefault();
		return false;
	});

	// On 'Advanced' fields toggle.
	$('.section-expand').on( 'click', function(e) {

		var section = $(this).attr('expand');
		var context = '[section=' + section + ']';
		var section_a = this;

		$( '.section-' + section + '-values' ).toggle(0).addClass('temp-tr-hide');

		$( context ).closest('tr').toggle( 0, function() {

			$(this).addClass('temp-tr-hide');

			if ( $(this).is(':visible') ) {
				$( section_a ).text( goft_wpjm_admin_l18n.simple );
			} else {
				$( section_a ).text( goft_wpjm_admin_l18n.advanced );
			}

		});

		update_hidden_sections( '[section=taxonomies], [section=meta], [section=source]' );

		e.preventDefault;
		return false;
	});


	// On reset values.
	$(document).on( 'change', '.auto-schedule', function(e) {
		if ( $(this).val() === 'yes' ) {
			var defaultScheduleName = '(Auto Schedule) ' + $('input[name=template_name]').val();
			$('.schedule-name').val( defaultScheduleName );
			$('.auto-schedule-toggle').show();

			maybeLimitSchedules();
		} else {
			$('.auto-schedule-toggle').hide();
		}
	})


	// __Providers callbacks.

	/**
	 * Update the manual/builder link names considering their child visibility.
	 */
	function update_provider_help_links( oclick, visible ) {

		var oclick = oclick ? oclick : $('[class*=provider-expand]');

		oclick.each( function() {

			var ochild = $( '.' + $(this).data('child') );
			var text = $(this).data('default');
			var is_visible = typeof( visible ) === 'undefined' ? ochild.is(':visible') : visible;

			text = ( is_visible ?  '- ' : '+ ' ) + text;

			$(this).text( text );
		});

	}

	/**
	 * Pre fill the provider RSS builder fields.
	 */
	function pre_fill_provider_rss_builder( provider, savedValues ) {
		savedValues = savedValues || false;

		var savedValues = append_source_stack_params( savedValues );

		if ( $('.provider-expand-feed-builder').length ) {
			$('.provider-expand-feed-builder').click();
		} else {
			$('.provider-expand-feed-manual-setup').click();
		}

		update_provider_help_links();

		if ( 'undefined' !== typeof provider.feed.query_args_sep ) {
			$('.feed-builder input[name=feed-params-sep]').val( provider.feed.query_args_sep );
			$('.feed-builder input[name=feed-params-sep-pos]').val( provider.feed.query_args_sep_pos );
		}

		var feedURL = provider.feed.base_url;

		if ( 'undefined' !== typeof provider.feed.query_args ) {

			$('[class*=opt-param]:not(.domain-param)').hide();

			$.each( provider.feed.query_args, function( key, value ) {

				if ( savedValues ) {
					$.each( Object.keys( value ), function( idx, oKey ) {
						if ( savedValues.get( oKey ) ) {
							value[ oKey ] = savedValues.get( oKey );
							return;
						}
					})
				}

				$('.feed-builder .opt-param-' + key).last().show();

				var param           = Object.keys(value)[0];
				var def_val         = 'undefined' !== typeof value[ param ] ? ( 'undefined' !== typeof value[ param ].default_value ? value[ param ].default_value : ( value[ param ] ? value[ param ] : value[ key ] ) ) : '';
				var placeholder     = 'undefined' !== typeof value[ param ].placeholder ? value[ param ].placeholder : '';
				var required        = 'undefined' !== typeof value[ param ].required ? value[ param ].required :'';
				var is_param_prefix = 'undefined' !== typeof value[ param ].is_prefix ? value[ param ].is_prefix : '';

				var $field       = $('.feed-builder [name=feed-' + key + ']');
				var $hiddenField = $('.feed-builder [name=feed-param-' + key + ']');

				// Check for the special 'domain_l' and ' 'domain_k'  parameter name for locations, keywords in the feed URL.
				if ( param === 'domain_l' || param === 'domain_k' ) {
					$('.feed-builder input[name=feed-param-' + key + ']').attr( 'data-regex', value[ param ].regex );
				}

				if ( required ) {
					$('.feed-builder .opt-param-' + key + ' label').append( ' ' + required );
				}

				$hiddenField.val( param );
				$hiddenField.data( 'default', def_val );

				if ( placeholder ) {
					$('.feed-builder [name=feed-' + key + ']').attr( 'placeholder', placeholder );
				}

				if ( def_val ) {
					if ( $field.attr('multiple') === 'multiple' ) {
						var defValArr = def_val.split(',');
						$field.val( defValArr ).trigger('change');
					} else {
						$('.feed-builder [name=feed-' + key + ']').val( def_val );
					}
				}

				if ( is_param_prefix ) {
					$hiddenField.attr( 'data-prefix', is_param_prefix );
				}
			});

			$('select[name*=feed-][multiple=multiple]').select2({
				placeholder: 'Click to choose ...',
			});
		}

		$('.feed-builder input[name=feed-url]').val( feedURL ).keyup();
		$('.feed-builder input[name=feed-url]').data( 'default', feedURL );
	}

	/**
	 * Get the base feed URL.
	 */
	function get_provider_feed_base_url() {
		var feed_url        = $('input[name=feed-url]').val();
		var feed_params_sep = $('.feed-builder input[name=feed-params-sep]').val();

		if ( 'undefined' === typeof( feed_url ) ) {
			return feed_url;
		}

		if ( '&' === feed_params_sep ) {

			if ( feed_url.indexOf('?') < 0 ) {
				feed_url += '?';
			} else {
				feed_url += '&';
			}

		} else {

			if ( feed_url.indexOf( feed_params_sep ) < 0 ) {
				feed_url += feed_params_sep;
			}

		}
		return feed_url;
	}

	/**
	 * RSS feed builder.
	 */
	function build_provider_rss_feed( params ) {
		var feed_url                 = g_feed_base_url;
		var feed_params_sep          = $('.feed-builder input[name=feed-params-sep]').val();
		var feed_params_sep_pos      = $('.feed-builder input[name=feed-params-sep-pos]').val();
		var feed_params_split_multi  = !!parseInt( $('.feed-builder input[name=feed-param-split-multi]').val() );
		var sorted_params            = [];

		// Make sure all the available parameters are present, sorted correctly and in some cases assigned a default value.
		$('.feed-builder .params [name*=feed-]').each( function() {

			var qarg        = $(this).attr('data-qarg') || false,
			    def_value   = $('[name=' + qarg + ']').data('default'),
			    input_value = $(this).val(),
			    value       = '';

			if ( ! qarg ) {
				return true;
			}

			def_value = 'undefined' !== typeof def_value ? def_value : '';
			value     = 'undefined' !== typeof params[ qarg ] ? params[ qarg ] : '';
			//console.log('param='+qarg+'; value='+value+'; def value='+def_value);

			if ( ( Array.isArray(input_value) && input_value.length ) || ( ! Array.isArray( input_value ) && input_value ) ) {
				let inputValue = value ? value : def_value;
				sorted_params[ qarg ] = inputValue;
			}

		});

		params = sorted_params;

		for( var param in params ) {
			if ( ! params.hasOwnProperty( param ) || typeof params[ param ] === 'undefined' ) {
				continue;
			}

			if ( typeof params[ param ] !== 'undefined' ) {

				if ( ( '&' === feed_params_sep && '?' !== feed_url.slice(-1) ) || '&' !== feed_params_sep ) {

					if ( feed_params_sep !== feed_url.slice(-1) && feed_params_sep_pos !== 'before' ) {
						feed_url += feed_params_sep;
					}

				}

				if ( feed_params_sep_pos === 'before' ) {
					feed_url = feed_url.replace( '?',  params[param] + feed_params_sep + '?' );
				} else {

					if ( feed_params_split_multi ) {
						var values = decodeURIComponent( params[param] );

						var urlParam = values.split('=')

						if ( urlParam.length && urlParam[1] ) {
							// Split by strings wrapped in double quotes
							//var urlParamValues = urlParam[1].split(/(\w+|"[^"]+"|'[^"]+')/gm)
							var urlParamValues = urlParam[1].split(/(".*?"|[^",]+)(?=\s*,|\s*$)/g);

							var singularParams = '';

							for ( var valKey in urlParamValues ) {
								var val = urlParamValues[ valKey ].trim();
								if ( ! val || val === ',' ) continue;
								// Remove quotes and leading spaces
								val = val.replaceAll('"', '');
								val = val.replaceAll('"', '');
								val = val.replaceAll("'", '')
								val = capitalizeWords( val );
								if ( singularParams ) singularParams += '&';
								singularParams += ( urlParam[0] + '=' + encodeURIComponent( val ) );
							}
							feed_url += singularParams;
						}

					} else {
						feed_url += params[param];
					}
				}

			}

		}

		feed_url = apply_filters_to_url( feed_url );

		feed_url = decodeURIComponent( feed_url);

		$('.provider-rss-custom').prop( 'href', feed_url );
		$('.provider-rss-custom').text( feed_url );

	}

	// Decode and append SourceStack params, if applicable
	function append_source_stack_params( params ) {

		if ( !params || (params && ! params.has('filters') ) ) {
			return params;
		}

		var providerId = $('#providers_list').val();
		if ( providerId !== 'api.sourcestack.com' ) {
			return params;
		}

		var filters = JSON.parse( params.get('filters') );

		filters.forEach( function( filter, index ) {
			params.append( filter.field, filter.value );
		})

		return params;
	}


	function apply_filters_to_url( url ) {

		if ( ! isValidUrl( url ) ) {
			return url;
		}

		var provider = $('#providers_list').val();

		switch ( provider ) {

			case 'api.sourcestack.com':

				var ssTypes = {
					'remote'     : 'EQUALS',
					'postal_code': 'EQUALS',
					'city'       : 'EQUALS',
					'region'     : 'EQUALS',
					'city'       : 'EQUALS',
					'seniority'  : 'IS_IN',
					'hours'      : 'IS_IN',
					'education'  : 'IS_IN',
					'logo_url'   : 'NOT_EQUALS'
				}

				var ssDefaultFields = [
					'job_name',
					'post_html',
					'hours',
					'education',
					'department',
					'seniority',
					'remote',
					'company_name',
					'company_url',
					'post_url',
					'job_location',
					'city',
					'region',
					'postal_code',
					'country',
					'last_indexed',
					'logo_url'
				]

				var ssRegularPerams = [
					'limit',
				]

				var ssUrl = new URL(url);
				var ssUrlParams = new URLSearchParams(ssUrl.search);

				var job_published_at_operator = 'GREATER_THAN';

				var ssFilters = [],
					ssParams = [];

				for (var [key, value] of ssUrlParams) {

					if ( ssRegularPerams.indexOf( key ) > -1 ) {
						ssParams.push( key + '=' + value );
					} else {

						var field = {
							'field': key,
							'value': value,
							'operator': ssTypes[ key ] || 'CONTAINS_ANY',
						}

						if ( key === 'job_published_at_operator' ) {
							job_published_at_operator = value;
						} else if ( key === 'job_published_at' ) {
							field['operator'] = job_published_at_operator;
						} else if ( key === 'logo_url' && ! value ) {
							return true;
						}

						ssFilters.push( field );
					}
				}

				var ssFiltersIndexesCheck = [];

				ssFilters.find( function( element, index ) {
					if ( element['field'] === 'job_published_at' ) {
						element['operator'] = job_published_at_operator;
						ssFiltersIndexesCheck.push( element['field'] );
						return true;
					}
				});

				// If there's no 'job_published_at', remove the 'job_published_at_operator' field
				ssFilters = ssFilters.filter( function( element, index ) {
					if ( element['field'] === 'job_published_at_operator' ) {
						return false;
					}
					return true;
				});

				var newUrlParams = new URLSearchParams( ssParams.join('&') );
				newUrlParams.append( 'filters', JSON.stringify( ssFilters ) );
				newUrlParams.append( 'fields', ssDefaultFields.join(',') )

				if ( ssFilters.length ) {
					var ssUrl = new URL(url);
					url = ssUrl.origin + ssUrl.pathname + '?' + newUrlParams.toString()
				}
		}
		return url;
	}

	function isValidUrl(urlString) {
		try {
			return Boolean(new URL(urlString));
		}
		catch(e){
			return false;
		}
	}

	function capitalizeWords( string ) {
		var arr = string.split(' ');
		var capitalized = ''
		arr.map( function( element ) {
			if (capitalized ) capitalized += ' ';
			if ( element.toLowerCase() === 'and' || element.toLowerCase() === 'or' ) {
				return capitalized += element;
			}
			capitalized += element.charAt(0).toUpperCase() + element.substring(1);
		});
		return capitalized;
	  }

	/**
	 * Check if user is changing this saved feed.
	 */
	function is_template_new_provider( $url ) {

		var new_feed   = $('#rss_feed_import').val().split('?')[0];
		if ( $('#rss_feed_import').attr('data-saved-url') ) {
			var saved_feed = $('#rss_feed_import').attr('data-saved-url').split('?')[0];
		}

		return new_feed !== saved_feed;
	}

	/**
	 * Auto fill the provider details given the provider data.
	 */
	function auto_fill_provider_details( provider, only_defaults ) {

		$.each( provider, function( key, value ) {
			if ( ! only_defaults ) {
				$('input[name="source[' + key + ']"]').val( value ).change();
			}
			$('input[name="source[' + key + ']"]').attr( 'data-original', value ).change();
		})

		update_hidden_sections( '[section=source]' );
	}


	// __Custom fields callbacks.

	/**
	 * Auto fill custom fields default values based on the feed query string.
	 */
	function auto_fill_custom_fields_defaults( provider ) {

		var all_query_args = [];

		if ( typeof goft_wpjm_admin_l18n.default_query_args !== 'undefined' ) {
			all_query_args.push( goft_wpjm_admin_l18n.default_query_args );
		}

		if ( typeof provider.feed !== 'undefined' ) {
			all_query_args.push( provider.feed.query_args );
		}

		// Iterate through the default query args and specific provider query args, if any.
		$.each( all_query_args, function( k, query_args ) {

			if ( typeof query_args === 'undefined' ) {
				return;
			}

			$.each( query_args, function( key, value ) {

				var param = '',
					param_value = '';

				// Defaults don't use key/value pairs, hack the key/value.
				if ( '' !== Object.keys(value)[0] && 0 === Object.keys(value)[0] ) {
					param = value;
					key = param;
				} else {
					param = Object.keys(value)[0];
				}

				param_value = get_parameter_by_name( $('#rss_feed_import').val(), param );
				if ( typeof param_value !== 'undefined' ) {
					$('input[data-core-name=' + key + ']').val( param_value ).change();
				}

			});

		});

	}

	// __Core callbacks.

	function toggle_settings( toggle ) {
		$('.tr-advanced, .tr-advanced + .section-advanced').addClass( 'tr-advanced-' + ( 'advanced' === toggle ? 'show' : 'hide' ) );
		$('.tr-advanced, .tr-advanced + .section-advanced').removeClass( 'tr-advanced-' + ( 'advanced' === toggle ? 'hide' : 'show' ) );
	}

	/**
	 * Toggle basic/advanced settings.
	 */
	function toggle_settings_apply() {

		if ( feed_loaded ) {
			$('.tr-advanced').promise().done( function() {
			 	$('.tr-advanced-show:not(.tr-toggle-hide)').show();
 				$('.tr-advanced-hide:not(.tr-toggle-hide)').hide();
			});
		}

	}

	/**
	 * Toggle sections and import related elements.
	 */
	function enable_import( visible ) {

		if ( visible ) {
			$('.import-notes').closest('tr').hide();
			$('.import-posts').fadeIn();
		} else {
			$('.import-notes').closest('tr').fadeIn();
			$('.import-posts').hide();
		}

		toggle_settings_groups( visible );
	}

	/**
	 * Updates the value for a specific section meta field.
	 */
	function update_section_meta( match, value ) {

		$('[section=meta]').each( function() {

			if ( $(this).filter('[' + match + ']' ) ) {
				var $field = $(this).filter('[' + match + ']' );

				if ( $field.is(':checkbox') ) {
					$field.val( value ? 1 : 0 );
					$field.prop( 'checked', value );
				} else {
					$field.val( value );
				}
			}

		})
	}

	 /**
	  * Dynamically update user changes when options are minimized.
	  */
	function update_hidden_sections( context ) {
		var curr_section = '';

		$( context ).each( function() {

			var section      = $(this).attr('section');
			var sectionClass = 'section-' + section;
			var selector     = 'section-' + section + '-values';

	 		var tr = $(this).closest('tr');
			var label = $( 'th', tr ).text();

			var default_value = '-';

			var value = '',
				classes = '';

			$( 'select, input[type!="button"], textarea', tr ).each( function(i,el) {

				value = classes = '';

				if ( $(this).is('select') ) {
					value = $(this).find(':selected').text();
				} else if ( $(this).is(':checkbox') ) {
					value = $(this).is(':checked') ? goft_wpjm_admin_l18n.label_yes : goft_wpjm_admin_l18n.label_no;
				} else if ( $(this).val() ) {
					value = $(this).val();

					classes = $(this).attr('class');

					// For URL values, output the last part or an image if that's the case.
					if ( is_url( value ) ) {

						var parser = document.createElement('a');
						parser.href = value;

						if ( parser.pathname.match(/\.(jpeg|jpg|gif|png|ico|svg)$/) !== null ) {
							value = '<img src="' + value + '" class = "goft-image-thumb">';
						} else {
							value = '<a href="' + value + '" class = "goft-link">' + value + '</a>';
						}

					}
				} else {

					if ( '' !== $(this).data('default') ) {
						value = $(this).data('default');
					}

				}

			});

			if ( section !== curr_section ) {

				if ( ! $( '.' + selector ).length ) {

					var otherClasses = $('.' + sectionClass).closest('tr').hasClass('tr-always-hide') ? 'tr-always-hide' : '' ;

					var sel_values_placeholder =
						'<tr class="section-advanced temp-tr-hide ' + otherClasses + '">' +
							'<td><a href="#" class="section-expand" expand="' + section + '">' + goft_wpjm_admin_l18n.msg_advanced + '</a></td>' +
							'<td class="tip">&nbsp;</td>' +
							'<td class="section-values ' + selector + '" colspan=5></td>' +
						'</tr>';

					$( '.section-' + section ).parents('tr').after( sel_values_placeholder );
				} else {
					$( '.' + selector ).html('');
				}

			}

			if ( ! value ) {
				value = default_value;
			}

			var sel_values = '<p class="goft-basic" data-classes="' + classes + '"><strong>' + label  + ': </strong><span>' + value + '</span></p>' ;
			$( '.' + selector ).append( sel_values );

			curr_section = section;
		});

	}

	/**
	 * Show/hide the hidden settings groups.
	 */
	function toggle_settings_groups( show ) {

		var group      = $('.temp-tr-hide:not(.tr-toggle-hide)');
		var fixedgroup = $('.temp-tr-hide:not(.tr-toggle-hide).tr-hide');

		if ( show ) {
			group.fadeIn();
 		} else {
			group.fadeOut();
			fixedgroup.removeClass('temp-tr-hide');
 		}

		 $('.tr-auto-schedule').hide();
	}


	// __Helpers.

	/**
	 * Toggles template schedule related markup on/off.
	 */
	function templateHasScheduleToggle( state ) {
		if ( state ) {
			$('.save-warning-schedule').hide();
			$('.save-warning-regular').show();
		} else {
			$('.save-warning-schedule').show();
			$('.save-warning-regular').hide();
		}
	}

	/**
	 * Checks if a given template is assigned to a schedule.
	 */
	function templateHasSchedule( template ) {
		var hasSchedule = true;
		if ( goft_wpjm_admin_l18n.can_use_premium === 'yes' ) {
			hasSchedule = goft_wpjm_admin_l18n.used_templates && goft_wpjm_admin_l18n.used_templates.indexOf( template ) >= 0;
		}
		templateHasScheduleToggle( hasSchedule );
		return hasSchedule;
	}

	/**
	 * Custom HTML for the provider li item.
	 */
	function single_provider_item ( state ) {

		if ( ! state.id ) {

			var icon = 'dashicons-' + ( items === 1 ? 'minus' : 'plus' );

			items++;

			if ( state.children ) {
				var $state = $('<span class="goft-wpjm-group">' + state.text + '<span class="dashicons-before ' + icon + ' expand-minimize"></span></span>');
				return $state;
			}

			return state.text;
		}

		if ( $(state.element).data('desc') ) {

			var $state = $(
				'<span>' + $(state.element).val() + '<span class="provider-desc">' + $(state.element).data('desc') + '</span></span>'
			);

		} else {

			var $state = $(
				'<span>' + state.text + '</span>'
			);

		}
		return $state;
	}

	/**
	 * Generates a template name given the feed url.
	 */
	function generate_default_template_name( feed_url ) {
		//var re = /\/\/www\.(.*?)\.|\/\/(.*?[\.][^.][a-zA-z]*)[\/|?]/;
		var re = /\/\/www\.(.*?)[\/\?]$/;
		var m;

		if ( ( m = re.exec( feed_url ) ) !== null ) {
		    for (var i = m.length - 1; i >= 0; i--) {
		    	if ( typeof m[ i ] !== 'undefined' && '' !== m[ i ] ) {
			 		return 'rss-' + m[ i ];
		    	}
		    }

		}
		return 'my-rss-feed';
	}

	/**
	 * Checks if string is an URL.
	 */
	function is_url( str ) {
		var urlRegEx = /((([A-Za-z]{3,9}:(?:\/\/)?)(?:[\-;:&=\+\$,\w]+@)?[A-Za-z0-9\.\-]+|(?:www\.|[\-;:&=\+\$,\w]+@)[A-Za-z0-9\.\-]+)((?:\/[\+~%\/\.\w\-]*)?\??(?:[\-\+=&;%@\.\w]*)#?(?:[\.\!\/\\\w]*))?)/g;
	  	var pattern = new RegExp( urlRegEx );
	  	return pattern.test(str);
	}

	/**
	 * Retrieves the value from a given query string parameter.
	 */
	function get_parameter_by_name( url, name ) {

		name = name.replace( new RegExp( "]", 'gm' ), /[\[]/, '\\[').replace( new RegExp( "]" , 'gm'),  /[\]]/, '\\]' );

	    var regex = new RegExp("[\\?&\/]" + name + "=([^&#\/]*)");
	    var results = regex.exec( url );
    	return results === null ? "" : decodeURIComponent( results[1].replace(new RegExp( ']', 'gm' ),  /\+/g, " ") );
	}

})
