<?php
/**
 * Integrations with:
 *
 * @package GoFetch/WPJM/Plugins/MAS Companies
 *
 * MAS Companies For WP Job Manager
 * https://wordpress.org/plugins/mas-wp-job-manager-company/
 *
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'MAS_WP_Job_Manager_Company' ) ) {
	return;
}

/**
 * WPJM specific import functionality.
 */
class GoFetch_MAS_Companies extends GoFetch_Importer {

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
		add_action('goft_wpjm_after_insert_jobs_commit', array( $this, 'update_mas_company' ) );
	}

	/**
	 * Update the company name on the Job Company dropdown.
	 */
	public function update_mas_company( $posts ) {

		$args = array(
			'paged' => false,
		);

		$companies = mas_wpjmc_get_companies( $args )->posts;

		foreach ( $posts as $post_id ) {

			$company_name = get_post_meta( $post_id, '_company_name', true );

			// Look for the company ID from the MAS plugin Cpmpanies list.
			$matches = wp_list_filter( $companies, array( 'post_title' => $company_name ) );

			if ( $matches ) {
				$matches = reset( $matches );
				$company_id = $matches->ID;
				update_post_meta( $post_id, '_company_id', $company_id );
			}

		}

	}

}

GoFetch_MAS_Companies::instance();
