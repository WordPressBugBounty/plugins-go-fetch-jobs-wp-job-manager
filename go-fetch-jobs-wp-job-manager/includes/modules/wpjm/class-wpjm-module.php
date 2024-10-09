<?php
/**
 * Active module configuration.
 *
 * @package GoFetch/Module/WPJM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $gofj_module_settings;

$settings = array(

	/**
	 * @var Specific options for the plugin/theme.
	 */
	'options' => array(

		'jobs_duration' => get_option( 'job_manager_submission_duration' ),

		'setup_post_type'               => 'job_listing',
		'setup_job_category'            => 'job_listing_category',
		'setup_job_type'                => 'job_listing_type',
		'setup_expired_status'          => 'expired',
		'setup_field_company_name'      => '_company_name',
		'setup_field_company_logo'      => '_company_logo',
		'setup_field_company_url'       => '_company_website',
		'setup_field_location'          => '_job_location',
		'setup_field_formatted_address' => 'geolocation_formatted_address',
		'setup_field_country'           => 'geolocation_country_short',
		'setup_field_city'              => 'geolocation_city',
		'setup_field_state'             => 'geolocation_state_short',
		'setup_field_latitude'          => 'geolocation_lat',
		'setup_field_longitude'         => 'geolocation_long',
		'setup_field_application'       => '_application',
		'setup_field_expiration'        => '_job_expires',
		'setup_field_salary'            => '_job_salary',
		'setup_field_featured'          => '_featured',
		'setup_field_remote'          => '_remote_position',
	),

);

$gofj_module_settings = $settings;
