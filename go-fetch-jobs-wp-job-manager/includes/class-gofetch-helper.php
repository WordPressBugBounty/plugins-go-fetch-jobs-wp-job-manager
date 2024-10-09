<?php
/**
 * Provides public helper methods.
 *
 * @package GoFetch/Helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class.
 */
class GoFetch_Helper {

	/**
	 * Check if current job was imported.
	 */
	public static function is_goft_job( $post_id = 0 ) {
		global $post;

		$post_id = $post_id ? $post_id : $post->ID;

		return (bool) get_post_meta( $post_id, '_goft_wpjm_is_external', true );
	}

	/**
	 * Retrieves a given field content type if recognized. Defaults to 'text' if unknown.
	 */
	public static function get_field_type( $field, $content_type = 'post' ) {

		$type = 'text';

		$fields = self::get_known_field_types();

		$fields = array_merge( $fields, self::get_field_types( $content_type ) );

		if ( $field && isset( $fields[ $field ] ) ) {
			$type = $field;
		}
		return $type;
	}

	/**
	 * Retrieve known field types for a know list of core fields.
	 *
	 * @uses apply_filters() Calls 'goft_wpjm_known_field_types'
	 */
	public static function get_known_field_types() {

		$fields = array(
			'post_author' => array(
				'user' => __( 'User', 'gofetch-wpjm' ),
				'text' => __( 'Text', 'gofetch-wpjm' ),
			),
			'post_status' => array(
				'post_status' => __( 'Post Status', 'gofetch-wpjm' ),
				'text'        => __( 'Text', 'gofetch-wpjm' )
			),
		);
		return apply_filters( 'goft_wpjm_known_field_types', $fields );
	}

	/**
	 * Retrieve all possible field types.
	 *
	 * @uses apply_filters() Calls 'goft_wpjm_field_types'
	 *
	 */
	public static function get_field_types( $content_type = 'post' ) {

		$types = array(
			'text' => __( 'Text', 'gofetch-wpjm' ),
			'date' => __( 'Date', 'gofetch-wpjm' ),
			'user' => __( 'User', 'gofetch-wpjm' ),
		);

		if ( 'user' != $content_type ) {
			$types['post_status'] = __( 'Post Status', 'gofetch-wpjm' );
		}

		// Get existing taxonomies.
		$taxonomies = get_object_taxonomies( $content_type, 'objects' );

		// Unset the 'post_status' taxonomy since it's empty.
		$taxonomies['post_status'] = null; unset( $taxonomies['post_status'] );

		foreach ( $taxonomies as $tax ) {
			$types[ $tax->name ] = sprintf( __( "Taxonomy :: %s", 'gofetch-wpjm' ), $tax->label );
		}

		return apply_filters( 'goft_wpjm_field_types', $types, $content_type );
	}

	/**
	 * Alternative HTTP Get method to fetch remote feeds.
	 */
	public static function gofj_alt_remote_get( $url ) {
		return false;
	}

	/**
	 * Matches a list of keywords against a string.
	 */
	public static function match_keywords( $text, $keywords, $comparison = 'OR' ) {

		$keywords = array_map( 'trim', $keywords );

		$match = false;

		$match_count = 0;

		$keywords_count = count( $keywords );

		$text = html_entity_decode( $text );

		// Check if the text contains the positive keywords.
		foreach ( (array) $keywords as $keyword ) {
			if ( false !== stripos( $text, trim( $keyword ) ) ) {
				if ( 'AND' === $comparison ) {
					if ( ++$match_count && $match_count === $keywords_count ) {
						$match = true;
						break;
					}
				} else {
					$match = true;
					break;
				}
			}
		}
		return $match;
	}

	/**
	 * Removes extra slashes from a string.
	 */
	public static function remove_slashes( $string ) {
		$string = implode( '', explode( '\\', $string ) );
		return stripslashes( trim( $string ) );
	}


	/**
	 * Retrieves the sanitized list of saved templates.
	 */
	public static function get_sanitized_templates() {
		global $goft_wpjm_options;

		$templates = array();

		foreach ( $goft_wpjm_options->templates as $template => $data ) {
			$template = GoFetch_Helper::remove_slashes( $template );
			$templates[ $template ] = $data;
		}
		return $templates;
	}

	/**
	 * Upload an image given an external URL.
	 */
	public static function upload_attach_with_external_url( $image_url, $post_ID = 0 ) {
		$upload_dir = wp_upload_dir();

		if ( ! $image_url ) {
			return false;
		}

		$opts = array(
			'ssl' => array(
				'verify_peer'      => false,
				'verify_peer_name' => false,
			),
		);
		$context = stream_context_create( $opts );

		$image_data = @file_get_contents( $image_url, null, $context );

		if ( $image_data ) {
			$filename = basename( $image_url );

			$parts = parse_url( $filename );
			if ( ! empty( $parts['path'] ) ) {
				$filename = $parts['path'];
			}

			$wp_filetype = wp_check_filetype( $filename, null );
			if ( empty( $wp_filetype['ext'] ) || empty( $wp_filetype['type'] ) ) {
				$filename = sprintf( '%s.png', $filename );
			}

			$filename = wp_unique_filename( $upload_dir['path'], $filename );

			if ( wp_mkdir_p( $upload_dir['path'] ) ) {
				$file = $upload_dir['path'] . '/' . $filename;
			} else {
				$file = $upload_dir['basedir'] . '/' . $filename;
			}

			@file_put_contents( $file, $image_data );

			$wp_filetype = wp_check_filetype( $filename, null );

			if ( empty( $wp_filetype['ext'] ) || empty( $wp_filetype['type'] ) ) {
				unlink( $file );
				return false;
			}

			$attachment = array(
				'post_mime_type' => $wp_filetype['type'],
				'post_title'     => sanitize_file_name( $filename ),
				'post_content'   => '',
				'post_status'    => 'inherit',
			);

			$attach_id = wp_insert_attachment( $attachment, $file, $post_ID );

			require_once( ABSPATH . 'wp-admin/includes/image.php' );

			$attach_data = wp_generate_attachment_metadata( $attach_id, $file );

			wp_update_attachment_metadata( $attach_id, $attach_data );

			set_post_thumbnail( $post_ID, $attach_id );

			return $attach_id;
		}
		return false;
	}

	/**
	 * Check if string is in JSON format.
	 */
	public static function is_json( $string ) {
		self::json_decode( $string );
		return ( json_last_error() === JSON_ERROR_NONE );
	}

	/**
	 * Parse XML into array.
	 */
	public static function xml_to_array_parse( $xml ) {

		$return = array();

		if ( ! $xml ) return $return;

		try {

			$rss = $xml->children();

			// Look for a regular XMLS RSS structure.
			if ( ! empty( $rss->channel ) ) {
				$children = $rss->channel->item;
			} else {
				$children = $xml->children();
			}

			foreach ( $children as $parent => $child ) {
				$value = self::xml_to_array_parse( $child ) ? self::xml_to_array_parse( $child ) : $child;

				if ( is_array( $value ) ) {
					$return[] = $value;
				} else {
					$return[ "$parent" ] = $child->__toString();
				}
			}

		} catch (\Throwable $th) {
			//
		}
		return $return;
	}

	/**
	 * Iterate through the list of item to find the main nodes.
	 */
	public static function get_xml_main_items( $list ) {
		$default_valid_parents = array( 'jobs', 'items', 'channel' );

		$keys = array_keys( $list );

		$possible_parents = array();
		// Iterate through the base keys and get all that contain child nodes.
		foreach ( $keys as $key ) {
			if ( is_array( $list[ $key ] ) ) {
				$possible_parents[] = $key;
			}
		}

		if ( $parent = array_intersect( $possible_parents, $default_valid_parents ) ) {
			$parent = reset( $parent );

			$list = $list[ $parent ];

		} else {

			// Iterate through all parents and bail as soon as there's a list of nodes.
			foreach ( $possible_parents as $parent ) {
				if ( ! empty( $list[ $parent ][0] ) && 1 === count( array_keys( $list[ $parent ][0] ) ) ) {
					return self::get_xml_main_items( $list[ $parent ] );
				} else {
					$list = $list[ $parent ];
					break;
				}
			}

		}

		// No more parent nodes, so, iterate through the keys and break on the first numeric array.
		$updated_list = $list;

		if ( ! isset( $updated_list[0] ) ) {

			foreach ( $updated_list as $key => $item ) {
				if ( is_array( $item ) && isset( $item[0] ) ) {
					break;
				}
			}

		} else {
			$item = $updated_list;
		}
		return $item;
	}

	/**
	 * Extract the relevant value from a list of attributes.
	 */
	protected static function extract_attribute_value( $attributes, $element, $single = false ) {
		$attributes = (array) $attributes;

		if ( empty( $attributes ) ) {
			return '';
		}

		// Get the attributes that are most likely to contain the value.
		$priority_atts = apply_filters( 'goft_wpjm_xml_priority_props', array( 'value', 'label', 'name', 'url' ), $element, $attributes );

		$atts = array_intersect_key( $attributes, array_flip( $priority_atts ) );

		// If there are known attributes, use them.
		if ( ! empty( $atts ) ) {

			if ( $single || count( $atts ) === 1 ) {
				$value = reset( $atts );
			} else {
				$value = $atts;
			}

		} else {
			// Otherwise, use the first attribute value
			$value = reset( $attributes );
		}
		return $value;
	}

	/**
	 * Removes non array items from an item list (originated from a XML file).
	 */
	public static function traverse_and_build_list( $item_list ) {

		$new_item_list = array();

		foreach ( $item_list as $item ) {

			$new_item = array();

			// Iterate through all the item keys.
			foreach ( (array) $item as $item_el => $item_el_atts ) {

				if ( '@attributes' === $item_el ) {
					continue;
				}

				// Complex XML file.
				if ( is_array( $item_el_atts ) ) {

					// Iterate through all the item element keys.
					foreach ( (array) $item_el_atts as $item_el_atts_key => $atts ) {

						$attributes = $atts;

						if ( '@attributes' !== $item_el_atts_key ) {

							// If its a single item list, extract the attributes.
							if ( isset( $item_el_atts[ $item_el_atts_key ]['@attributes'] ) ) {
								$attributes = $item_el_atts[ $item_el_atts_key ]['@attributes'];
							} else {

								$taxonomy = array();

								foreach ( (array) $item_el_atts as $item_el_att_key => $item_el_att ) {

									// Default to a regular value.
									$el_atts = $item_el_att;

									if ( isset( $item_el_att['@attributes'] ) ) {
										$el_atts = $item_el_att['@attributes'];
									}

									if ( is_numeric( $item_el_att_key ) ) {

										// Taxonomy.
										$value = self::extract_attribute_value( $el_atts, $item_el_att_key, $_single = true );

										$taxonomy[] = $value;

									} else {

										$value = self::extract_attribute_value( $el_atts, $item_el_att_key, $_single = true );

										// Extract attributes
										$new_item[ $item_el_att_key ] = $value;
									}
								}

								if ( ! empty( $taxonomy ) ) {
									$new_item[ $item_el ] = implode( '|', $taxonomy );
								}
								continue;
							}

						}

						$value = self::extract_attribute_value( $attributes, $item_el );

						// Convert valid attributes to their own tag.
						if ( is_array( $value ) ) {
							foreach ( $value as $att_name => $att_value) {
								$new_item[ sprintf( '%s_%s', $item_el, $att_name ) ] = $att_value;
							}
						} else {
							$new_item[ $item_el ] = $value;
						}

						// Allow overriding the XML items.
						$new_item = apply_filters( 'goft_wpjm_xml_item', $new_item, $item_el, $attributes, $value );
					}

				} else {
					// Simple XML file.
					$new_item[ $item_el ] = $item_el_atts;
				}
			}

			$new_item_list[] = $new_item;
		}
		return $new_item_list;
	}

	public static function is_assoc( $arr ) {
		if ( array() === $arr || ! is_array( $arr ) ) return false;
		return array_keys( $arr ) !== range( 0, count( $arr  ) - 1 );
	}

	// Check for associative array.
	public static function is_assoc_array( array $arr ) {
		return count( array_filter( array_keys( $arr ), 'is_string' ) ) > 0;
	}

	public static function maybe_use_xml_prop( $value, $props ) {
		$priority_props = array( 'value' => '', 'label' => '' );

		if ( ! $value ) {
			$prop = array_intersect_key( $props, $priority_props );
			if ( ! empty( $prop ) ) {
				$value = reset( $prop );
			}
		}
		return $value;
	}

	public static function array_flatten( $array, $parent_key = '' ) {
		$return = array();

		$priority_props = array( 'value' => '', 'label' => '', 'url' => '' );

		foreach ( $array as $values ) {

			foreach ( $values as $key => $value) {

				if ( '@attributes' === $key ) {
					$prop = array_intersect_key( $value, $priority_props );
					if ( $parent_key && ! empty( $prop ) ) {
						$prop_value = reset( $prop );

						if ( ! isset( $return[ $parent_key ] ) ) {
							$return[ $parent_key ] = array();
						}
						$return[ $parent_key ][] = $prop_value;
					} else {
						unset( $return[ $key ] );
					}
				} elseif ( is_array( $value ) ) {
					$return = array_merge($return, self::array_flatten( $value, $parent_key ) );
				} else {
					$return[$key] = $value;
				}
			}
		}
		return $return;
	}

	/**
	 * Remove CDATA strings from an array item.
	 */
	public static function remove_cdata( $item ) {

		if  ( !is_array( $item ) ) {
			return $item;
		}
		$keys = array_keys( $item );

		foreach ( $keys as $key ) {
			if ( ! empty( $item[ $key ] ) && is_array( $item[ $key ] ) ) {
				$item[ $key ] = array_shift( $item[ $key ] );
			}
		}
		return $item;
	}

	/**
	 * Check if an item exists by comparing the fields that make them unique, against the existing jobs.
	 */
	public static function find_duplicate( $new_item, $db_items ) {
		$is_new = true;

		$fields = apply_filters( 'goft_wpjm_duplicate_fields_check', array( 'title', 'description' ) );

		$optional_fields = apply_filters( 'goft_wpjm_duplicate_fields_optional_check', array() );

		$post_id = 0;

		$new_values = array();

		foreach ( $fields as $field ) {
			if ( isset( $new_item[ $field ] ) ) {
				// Remove all whitespace (including tabs and line ends).
				$new_values[ $field ] = GoFetch_Helper::clean_and_sanitize( $new_item[ $field ], $_alphanumeric_only = true );
			}
		}

		// Iterate through items on the database and bail immediateily
		// if there's a match on at least any of the core fields.
		foreach ( $db_items as $db_item ) {

			$matches = 0;

			foreach ( $fields as $field ) {

				$new_value = '';
				$db_value  = '';

				// no need to standardize value since it was alrady done.
				if ( isset( $db_item[ $field ] ) ) {
					$db_value = $db_item[ $field ];
				}

				if ( isset( $new_values[ $field ] ) ) {
					$new_value = $new_values[ $field ];
				}

				if ( $db_value === $new_value ) {
					++$matches;
				// Also consider duplicate if an optional field differs but at least one of them is empty.
				} else if ( false !== in_array( $field, $optional_fields ) && ( $db_value !== $new_value && ( $db_value || $new_value ) ) ) {
					++$matches;
				}
			}

			// If content from all main fields matches the new item, flag as duplicate and bail.
			if ( $matches === count( $fields ) ) {
				$is_new = false;
				$post_id = $db_item['post_id'];
				break;
			}
		}

		if ( ! $is_new ) {
			return $post_id;
		}
		return false;
	}

	/**
	 * Cleans a string by removing all spaces and tags.
	 */
	public static function clean_and_sanitize( $string, $alphanumeric = false ) {
		$string = wp_strip_all_tags( trim( preg_replace( '/\s+/', '', htmlspecialchars_decode( $string, ENT_QUOTES ) ) ) );
		if ( $alphanumeric ) {
			$string = preg_replace( "/[^A-Za-z0-9.!?]/", '', $string );
		}
		return $string;
	}

	/**
	 * Checks if string is an email address.
	 */
	public static function is_email_address( $string ) {
		return filter_var( $string, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Checks if a given provider is an RSS, API or ATS.
	 */
	public static function is_api_provider( $provider_id, $provider = '' ) {

		$is_api_provider = false;

		if ( $provider_id ) {
			$is_api_provider = ( 0 === strpos( $provider_id, 'api.' ) || false !== strpos( $provider_id, '/api' ) );
		}

		if ( $provider && isset( $provider['category'] ) ) {
			$is_api_provider = count( array_intersect( (array) $provider['category'], array( 'API', 'ATS' ) ) );
		}

		return $is_api_provider;
	}

	/**
	 * Check if the provider data provides scraping.
	 */
	public static function supports_scraping( $data ) {
		return ! empty( $data['special']['scrape'] );
	}

	/**
	 * Checks if the provider supports full job descriptions and the user choses to scrape only that field.
	 */
	public static function do_scrape( $data, $provider ) {

		if ( ! self::supports_scraping( $provider ) ) {
			return false;
		}

		$is_single_description_scraping = ( 1 === count( $data['special']['scrape'] ) && false !== in_array( 'description', $data['special']['scrape'] ) );

		return ! $is_single_description_scraping || empty( $provider['feed']['full_description'] );
	}

	/**
	 * Look for empty mappings and replace it with a default value.
	 */
	public static function handle_empty_mappings( $array, $default = '', $find = '' ) {

		$array = array_map( function( $value ) use ( $default, $find ) {
			if ( ! $value || $find === $value ) $value = $default;
			return $value;
		}, (array) $array );

		return $array;
	}


	/**
	 * Enqueues the media uploader script and related custom inline JS:
	 */
	public static function image_uploader_js() {

		if ( ! did_action( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}

		ob_start();
?>
		jQuery(function($){

			// on upload button click
			$('body').on( 'click', '.gofj-upl', function(e){

				var $context = $(this).closest('.gofj-upl-container');
				var $remove = $context.find('.gofj-rmv');

				var $input = $context.find('input');

				e.preventDefault();

				var $button = $(this),
				custom_uploader = wp.media({
					title: 'Insert image',
					library : {
						// uploadedTo : wp.media.view.settings.post.id, // attach to the current post?
						type : 'image'
					},
					button: {
						text: 'Use this image' // button label text
					},
					multiple: false
				}).on('select', function() { // it also has "open" and "close" events
					var attachment = custom_uploader.state().get('selection').first().toJSON();
					$button.html('<img class="ats-company-logo" src="' + attachment.url + '">').show();
					console.log('<img class="ats-company-logo" src="' + attachment.url + '">');
					$remove.show();
					$input.val(attachment.id);
				}).open();

			});

			// on remove button click
			$('body').on('click', '.gofj-rmv', function(e){

				var $context = $(this).closest('.gofj-upl-container');
				var $remove = $context.find('.gofj-rmv');

				var $input = $context.find('input');

				e.preventDefault();

				var button = $(this), $image = $context.find('.gofj-upl');
				$input.val('');
				$image.html('Upload ...');
				button.hide();
			});

		});
<?php
		$inline_js = ob_get_clean();

		wp_add_inline_script( 'goft_wpjm', $inline_js );
?>		<style>.ats-company-logo{max-width: 150px}</style><?php
	}

	/**
	 * Wrapper for php's 'mb_convert_encoding()' that uses an alternative method, if extension is not installed.
	 *
	 * @param array|string $string
	 * @param string $to_enconding
	 * @param [type] $from_encoding
	 * @return array|string|false
	 */
	public static function mb_convert_encoding( $string, $to_enconding, $from_encoding = null ) {
		try {
			$output = mb_convert_encoding($string, $to_enconding, $from_encoding );
		} catch (\Throwable $th) {
			$output = htmlspecialchars_decode( utf8_decode( htmlentities( $string, ENT_COMPAT, $to_enconding, false ) ) );
		}
		return $output;
	}

	protected static function removeBOM($data) {
		if (0 === strpos(bin2hex($data), 'efbbbf')) {
			return substr($data, 3);
		}
		return $data;
	}

	/**
	 * Better JSON decode.
	 */
	public static function json_decode( $string, $associative = false ) {
		if ( ! is_string( $string ) ) {
			return $string;
		}
		$string = preg_replace( '/[\x00-\x1F\x80-\xFF]/', '', $string );
		$result = json_decode( $string, $associative );
		return $result;
	}

	/**
	 * Return random headers to use on HTTP requests.
	 */
	public static function random_headers() {

		$platforms       = array( 'Android', 'Chrome OS', 'Chromium OS', 'iOS', 'Linux', 'macOS', 'Windows', 'Unknown' );
		$platforms_index = rand( 0, count( $platforms ) - 1 );
		$platform        = $platforms[ $platforms_index ];

		$headers = array(
			'user-agent' => self::random_user_agent(),
			'upgrade-insecure-requests' => 1,
			'sec-fetch-dest'            => 'document',
			'sec-fetch-mode'            => 'navigate',
			'sec-fetch-site'            => 'cross-site',
			'sec-fetch-user'            => '?1',
			'sec-ch-ua-mobile'          => '?0',
			'sec-ch-ua-platform'        => $platform,
		);

		return $headers;
	}

	/**
	 * Return random user agents to use on HTTP requests.
	 */
	public static function random_user_agent() {
		$agents = array(
			'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.2 (KHTML, like Gecko) Chrome/22.0.1216.0 Safari/537.2',
			'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/57.0.2987.133 Safari/537.36',
			'Mozilla/1.22 (compatible; MSIE 10.0; Windows 3.1)',
			'Mozilla/5.0 (Windows NT 6.3; Trident/7.0; rv:11.0) like Gecko',
			'Opera/9.80 (Windows NT 6.0) Presto/2.12.388 Version/12.14',
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/7046A194A'
		);

		$chose = rand( 0, count( $agents ) - 1 );

		return $agents[ $chose ];
	}

	/**
	 * Checks if a path of filename has a 'gz' extension.
	 */
	public static function is_gziped( $url_filename ) {
		$path_parts = pathinfo( $url_filename );
		return isset( $path_parts['extension'] ) && 'gz' === $path_parts['extension'];
	}

	/**
	 * Temporarily remove memory limits.
	 */
	public static function temp_remove_memory_limits() {
		if ( ! apply_filters( 'goft_wpjm_import_force_no_limits', true ) ) {
			return;
		}
		ini_set( 'memory_limit', -1 );
		set_time_limit( 0 );
	}

}
