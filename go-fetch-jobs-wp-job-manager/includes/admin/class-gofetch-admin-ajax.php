<?php
/**
 * Contains AJAX admin related callbacks.
 *
 * @package GoFetchJobs/Admin/Ajax
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Ajax class.
 */
class GoFetch_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_goft_wpjm_load_template_content', array( $this, 'load_template_content' ) );
		add_action( 'wp_ajax_goft_wpjm_update_templates_list', array( $this, 'update_templates_list' ) );
		add_action( 'wp_ajax_goft_wpjm_load_provider_info', array( $this, 'load_provider_info' ) );
		add_action( 'wp_ajax_goft_wpjm_import_feed', array( $this, 'import_feed' ) );
		add_action( 'wp_ajax_goft_wpjm_toggle_settings', array( $this, 'toggle_settings' ) );
	}

	/**
	 * Dynamically outputs the pre-saved fields settings for a given template.
	 */
	public function load_template_content() {

		if ( ! wp_verify_nonce( $_POST['_ajax_nonce'], 'goft_wpjm_nonce' ) ) {
			die(0);
		}

		// User selected 'none' on the template list dropdown.
		if ( empty( $_POST['template'] ) ) {

			die(0);

		} else {

			// User has selected a template.

			$template_name = sanitize_text_field( $_POST['template'] );
			$template_name = GoFetch_Helper::remove_slashes( $template_name );

			$templates = GoFetch_Helper::get_sanitized_templates();

			$template_settings = $templates[ $template_name ];

			$query_args = apply_filters( 'goft_wpjm_template_setup', $template_settings, $template_name );
		}

		echo wp_json_encode( $query_args );
		die( 1 );
	}

	/**
	 * Dynamically update the templates list.
	 */
	public function update_templates_list() {
		global $goft_wpjm_options;

		if (  ! wp_verify_nonce( $_POST['_ajax_nonce'], 'goft_wpjm_nonce' ) ) {
			die( 0 );
		}

		$templates = GoFetch_Helper::get_sanitized_templates();

		echo wp_json_encode( array(
			'templates' => array_keys( $templates ),
		) );

		die( 1 );
	}

	/**
	 * Load a given providers info.
	 */
	public function load_provider_info() {

		if ( ! wp_verify_nonce( $_POST['_ajax_nonce'], 'goft_wpjm_nonce' ) ) {
			die(0);
		}

		// User didn't select a provider.
		if ( empty( $_POST['provider'] ) ) {

			die( 0 );

		} else {

			// User has selected a provider.

			$provider = sanitize_text_field( $_POST['provider'] );

			$data = array(
				'provider'              => GoFetch_RSS_Providers::get_providers( $provider ),
				'setup'                 => GoFetch_RSS_Providers::setup_instructions_for( $provider ),
				'required_query_params' => GoFetch_RSS_Providers::required_query_params( $provider ),
			);

		}

		echo wp_json_encode( $data );
		die( 1 );
	}

	/**
	 * Dynamically import an RSS feed.
	 */
	public function import_feed() {

		if ( ! wp_verify_nonce( $_POST['_ajax_nonce'], 'goft_wpjm_nonce' ) ) {
			die( 0 );
		}

		$is_file_upload = (int) sanitize_text_field( $_POST['file_upload'] );

		if ( $is_file_upload ) {

			$url = sanitize_text_field( $_POST['url'] );

			$file = $_FILES['import_local_feed']['tmp_name'];

			if ( GoFetch_Helper::is_gziped( $url ) ) {
				if ( ! extension_loaded('zlib') ) {
					echo wp_json_encode( array(
						'error' => __( 'You must have the PHP\'s \'zlib\' extension installed, to load zipped files. ', 'gofetch-wpjm' ),
					) );
					die( 0 );
				}

				GoFetch_Helper::temp_remove_memory_limits();

				$contents = file_get_contents( "compress.zlib://{$file}" );
			} else {
				$contents = file_get_contents( $file );
			}

			$feed = GoFetch_Importer::import_custom_content_type( $contents, $is_file_upload );

			if ( ! is_wp_error( $feed ) ) {

				// Set provider data.
				$provider_data = array(
					'id'          => 'custom',
					'title'       => $url,
					'description' => $url,
				);
				$provider = GoFetch_RSS_Providers::get_base_provider( $provider_data );

				$result = GoFetch_Importer::fetch_feed_items( $feed, $_url = '', $provider, $_custom_content_type = true );

				if ( ! empty( $result['items'] ) ) {
					GoFetch_Importer::cache_feed_items( $result['items'] );
				}

			} else {
				$result = $feed;
			}
		} else {

			$url = sanitize_url( $_POST['url'] );
			$sanitized_post = array_map( 'sanitize_text_field', $_POST );

			$result = GoFetch_Importer::import_feed( $url, $sanitized_post, $cache = true );

		}

		if ( ! $result || is_wp_error( $result ) ) {

			echo wp_json_encode( array(
				'error' => is_wp_error( $result ) ? $result->get_error_message() : sprintf( __( 'This feed does not appear to be valid.<br/> %s', 'gofetch-wpjm' ), $result ),
			) );
			die( 0 );
		}

		if ( ! is_array( $result ) ) {
			echo wp_json_encode( array(
				'error' => __( 'Unknown error.', 'gofetch-wpjm' ),
			) );
			die( 0 );
		}

		$provider = $result['provider'];

		$sample_item = apply_filters( 'goft_wpjm_sample_item', $result['sample_item'], $provider );

		$args = array(
			'content_type' => GoFetch_Jobs()->parent_post_type,
		);

		if ( ! empty( $provider['feed']['default_mappings'] ) ) {
			$args['default_mappings'] = $provider['feed']['default_mappings'];
		}

		$total_items = count( $result['items'] );

		$content_type = ! empty( $result['type'] ) ? $result['type'] : 'RSS';

		// Clear memory.
		$result = null;

		if ( ! empty( $sample_item['logo_html'] ) ) {
			$sample_item['logo'] = $sample_item['logo_html'];
			// Clear memory.
			$sample_item['logo_html'] = null; unset( $sample_item['logo_html'] );
		}

		echo wp_json_encode( array(
			'provider'    => $provider,
			'sample_item' => $total_items ? GoFetch_Sample_Table::display( $args, $sample_item, $url ) : '',
			'total_items' => $total_items,
			'content_type' => $content_type,
		) );
		die( 1 );
	}

	/**
	 * Dynamically update toggle settings.
	 */
	public function toggle_settings() {

		if ( ! wp_verify_nonce( $_POST['_ajax_nonce'], 'goft_wpjm_nonce' ) ) {
			die( 0 );
		}

		$toggle = sanitize_text_field( $_POST['toggle'] );
		$user_id = get_current_user_id();

		$options = get_user_meta( $user_id, 'bc_screen_options', true );

		if ( ! $options || ! is_array( $options ) ) {
			$options = array(
				'goft-settings-type' => 'advanced',
			);
		}

		$options['goft-settings-type'] = $toggle;

		update_user_meta( $user_id, 'bc_screen_options', $options );

		die( 1 );
	}

}
new GoFetch_Ajax();
