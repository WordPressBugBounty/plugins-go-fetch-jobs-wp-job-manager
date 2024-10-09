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
class GoFetch_WPJM_Admin_Settings extends GoFetch_Importer {

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
		add_filter( 'goft_wpjm_geocomplete_hidden_fields', array( $this, 'geocomplete_hidden_fields' ) );
		add_action( 'tabs_go-fetch-jobs_page_go-fetch-jobs-wpjm-settings', array( $this, 'settings' ), 99 );
		add_action( 'init', array( $this, 'maybe_delete_expired_jobs' ), 99 );
	}

	/**
	 * Geolocation meta fields.
	 */
	public function geocomplete_hidden_fields( $fields = array() ) {
		return array_merge( $fields, array(
			'geolocation_formatted_address' => 'formatted_address',
			'geolocation_city'              => 'city',
			'geolocation_lat'               => 'lat',
			'geolocation_long'              => 'lng',
			'geolocation_country_long'      => 'country',
			'geolocation_country_short'     => 'country_short',
			'geolocation_state_short'       => 'administrative_area_level_1_short',
			'geolocation_state_long'        => 'administrative_area_level_1',
		) );
	}

	/**
	 * Display exclusive WPJM settings.
	 */
	public function settings( $tab ) {

		$tab->tab_sections['importer']['importer']['fields'][] = array(
			'title'   => __( 'Upload Company Logos', 'gofetch-wpjm' ),
			'name'    => 'image_uploads',
			'type'    => 'checkbox',
			'tip' => __( 'Allow company logos to be uploaded to your media library?', 'gofetch-wpjm' ) .
					'<br/><br/>' . __( 'Check this option to allow the importer to upload company logos to the media library, and set them as featured job images.', 'gofetch-wpjm' ) .
					'<br/><br/>' . __( 'Leave this option unchecked, to keep the company logos as metadata (default).', 'gofetch-wpjm' )
		);

		$tab->tab_sections['jobs']['jobs']['fields'][] =
			array(
				'title' => __( 'Disable Salary Currency/Unit', 'gofetch-wpjm' ),
				'name'  => 'disable_salary_currency',
				'type'  => 'checkbox',
				'desc' => __( 'Yes', 'gofetch-wpjm' ),
				'tip' => __( 'Imported jobs salary format will vary between providers.<br/><br/>Check this option to disable WPJM default Currency and/or Unit (for imported jobs only), and use whatever format is returned by the provider.', 'gofetch-wpjm' ),
			);

		$tab->tab_sections['jobs']['jobs']['fields'][] =
			array(
				'title' => __( 'Delete expired jobs', 'gofetch-wpjm' ),
				'name'  => 'delete_exired_jobs',
				'type'  => 'checkbox',
				'desc' => __( 'Yes', 'gofetch-wpjm' ),
				'tip' => __( 'Check this option to auto-delete expired jobs.<br/><br/>Jobs will be deleted shortly after expiring (not immediatelly).', 'gofetch-wpjm' ),
			);
	}

	/**
	 * Delete any expired jobs, if applicable.
	 */
	public function maybe_delete_expired_jobs() {
		global $goft_wpjm_options;

		if ( ! $goft_wpjm_options->delete_exired_jobs ) {
			return;
		}

		add_filter( 'job_manager_delete_expired_jobs', '__return_true' );
		add_filter( 'job_manager_delete_expired_jobs_days', function() {
			return apply_filters( 'goft_wpjm_expire_delete_delay_days', 0 );
		} );

	}

}

GoFetch_WPJM_Admin_Settings::instance();
