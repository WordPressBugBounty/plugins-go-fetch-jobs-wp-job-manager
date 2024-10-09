<?php
/**
 * Specific import code for WP Job Manager.
 *
 * @package GoFetch/WPJM/Admin/Import
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once dirname( GOFT_WPJM_PLUGIN_FILE ) . '/includes/class-gofetch-importer.php';

/**
 * WPJM specific import functionality.
 */
class GoFetch_WPJM_Import extends GoFetch_Importer {

	/**
	 * @var The single instance of the class.
	 */
	protected static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	public function __construct() {
		add_filter( 'goft_wpjm_update_meta', array( $this, 'maybe_skip_geo_field' ), 10, 4 );
		add_filter( 'goft_wpjm_item_meta_value', array( $this, 'maybe_upload_company_logo' ), 10, 5 );
		add_filter( 'wpjm_schema_ping_search_engines', array( $this, 'maybe_disable_wpjm_schema' ) );
	}

	/**
	 * Skip adding any geolocation fields if WPJM already geolocated the job.
	 */
	public function maybe_skip_geo_field( $update, $meta_key, $meta_value, $post_id ) {

		if ( ! apply_filters( 'job_manager_geolocation_enabled', true ) ) {
			return $update;
		}

		$geo_fields = GoFetch_Admin_Builder::get_geocomplete_hidden_fields();

		return isset( $geo_fields[ $meta_key ] ) && class_exists( 'WP_Job_Manager_Geocode' ) && WP_Job_Manager_Geocode::has_location_data( $post_id );
	}


	/**
	 * Update logo and set related metadata.
	 */
	public function maybe_upload_company_logo( $meta_value, $meta_key, $item, $post_id, $params ) {
		global $goft_wpjm_options;

		if ( $goft_wpjm_options->setup_field_company_logo === $meta_key && $goft_wpjm_options->image_uploads ) {

			$skip_upload = false;

			// Do not upload the default logo, if set
			if ( $goft_wpjm_options->company_logo_default ) {
				$image_src_parts = wp_get_attachment_image_src( $goft_wpjm_options->company_logo_default );
				if ( $image_src_parts[0] === $meta_value ) {
					set_post_thumbnail( $post_id, $goft_wpjm_options->company_logo_default );
					$skip_upload = true;
				}
			}

			if ( ! $skip_upload && ! get_post_thumbnail_id( $post_id ) ) {
				GoFetch_Helper::upload_attach_with_external_url( $meta_value, $post_id );
			}
		}

		if ( $goft_wpjm_options->setup_field_application === $meta_key ) {
			update_post_meta( $post_id, '_job_apply_type', 'external' );
		}

		// Fill in the location metadata for Workup theme.
		if ( $goft_wpjm_options->setup_field_location === $meta_key ) {
			update_post_meta( $post_id, $goft_wpjm_options->setup_field_location_workup, $meta_value );
		}
		return $meta_value;
	}

	/**
	 * Disable WPJM schema.
	 */
	public function maybe_disable_wpjm_schema( $disable ) {

		if ( defined( 'GOFJ_IMPORTING' ) && GOFJ_IMPORTING ) {
			$disable = apply_filters( 'goft_wpjm_disable_wpjm_schema', GOFJ_IMPORTING );
		}
		return $disable;
	}

}

GoFetch_WPJM_Import::instance();
