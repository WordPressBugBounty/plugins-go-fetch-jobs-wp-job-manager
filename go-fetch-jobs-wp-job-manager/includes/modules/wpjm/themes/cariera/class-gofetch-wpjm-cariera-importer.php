<?php
/**
 * Specific import code for Cariera theme.
 *
 * @package GoFetch/WPJM/Cariera/Admin/Import
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WPJM specific import functionality.
 */
class GoFetch_Cariera_Import extends GoFetch_Importer {

	/**
	 * The theme Companies post type
	 */
	protected $company_post_type = 'company';

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
		add_filter( 'goft_wpjm_after_insert_job', array( $this, 'maybe_set_company' ), 10, 4 );
		add_filter( 'goft_wpjm_after_insert_job', array( $this, 'update_alt_application_field' ), 10, 4 );
	}

	/**
	 * Update logo and set related metadata.
	 */
	public function maybe_set_company( $post_id, $item, $params, $meta ) {
		global $goft_wpjm_options;

		$company_name_field = $goft_wpjm_options->setup_field_company_name;
		$company_logo_field = $goft_wpjm_options->setup_field_company_logo;

		if ( ! empty( $meta[ $company_name_field ] ) ) {

			$company_name = $meta[ $company_name_field ];

			$company_logo = '';

			if ( ! empty( $meta[ $company_logo_field ] ) ) {
				$company_logo = $meta[ $company_logo_field ];
			}

			$company = get_page_by_path( sanitize_title( $company_name ), OBJECT, $this->company_post_type );

			if ( empty( $company ) ) {
				$company_id = $this->save_company( $company_name, $company_logo );
			} else {
				$company_id = $company->ID;
			}

			if ( ! is_wp_error( $company_id ) && $company_id ) {
				update_post_meta( $post_id, '_company_manager_id', $company_id );
			}
		}

	}

	/**
	 * Creates/updates a Company and its respective logo, if available.
	 */
	protected function save_company( $name, $company_logo ) {

		$post_arr = array(
			'post_title'     => $name,
			'comment_status' => 'closed',
			'post_password'  => '',
			'post_type'      => $this->company_post_type,
			'post_status'    => 'publish',
		);

		$company_id = wp_insert_post( $post_arr );

		// Upload the company logo, if available.
		if ( $company_id ) {
			GoFetch_Helper::upload_attach_with_external_url( $company_logo, $company_id );
		}
		return $company_id;
	}


	/**
	 * Update Cariera's alternative application field.
	 */
	public function update_alt_application_field( $post_id, $item, $params, $meta ) {
		global $goft_wpjm_options;

		$alt_application_field = '_apply_link';

		$wpjm_apply_field = $goft_wpjm_options->setup_field_application;

		$value = $meta[ $wpjm_apply_field ];

		if ( is_array( $value ) ) {
			reset( $value );
		}

		if ( ! GoFetch_Helper::is_email_address( $value ) ) {
			update_post_meta( $post_id, $alt_application_field, $value );
		}
	}

}

GoFetch_Cariera_Import::instance();
