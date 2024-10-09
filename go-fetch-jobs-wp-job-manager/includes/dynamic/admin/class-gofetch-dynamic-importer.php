<?php
/**
 * Import functionality related with dynamic imports.
 *
 * @package GoFetch/Dynamic/Admin/Dynamic Importer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

require_once dirname( GOFT_WPJM_PLUGIN_FILE ) . '/includes/class-gofetch-importer.php';

/**
 * WPJM specific import functionality.
 */
class GoFetch_Dynamic_Import extends GoFetch_Importer {

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
		add_filter( 'goft_wpjm_item_meta_value', array( $this, 'replace_item_meta_placeholders' ), 10, 5 );
		add_filter( 'goft_wpjm_import_item_params', array( $this, 'default_item_meta' ), 10, 2 );
		add_action( 'goft_wpjm_after_insert_job', array( $this, 'maybe_remove_expiry_date' ) );
		add_filter( 'goft_wpjm_field_mapping_default', array( __CLASS__, 'default_field_mapping' ), 10, 3 );
		add_filter( 'goft_wpjm_feed_results', array( __CLASS__, 'hidden_fields' ) );
		add_filter( 'goft_wpjm_import_mappings_check', array( $this, 'validate_mappings' ), 10, 4 );
	}

	/**
	 * Setup default values for meta fields.
	 */
	public function default_item_meta( $params, $items ) {
		global $goft_wpjm_options;

		$defaults = array(
			$goft_wpjm_options->setup_field_application => '',
		);
		$params['meta'] = wp_parse_args( $params['meta'], $defaults );

		return $params;
	}

	/**
	 * Replaces string placeholders with valid data on a given meta key.
	 */
	public function replace_item_meta_placeholders( $meta_value, $meta_key, $item, $post_id, $params ) {
		global $goft_wpjm_options;

		switch ( $meta_key ) {

			case $goft_wpjm_options->setup_field_application:

				// Use the external job URL for application only if the application field is empty.
				if ( ! $meta_value || 'none' === $meta_value && ! empty( $item['link'] ) ) {
					$meta_value = self::add_query_args( $params, $item['link'] );
				}
				break;

			// Fix any coordinates using comma as decimal point.
			case $goft_wpjm_options->setup_field_latitude:
			case $goft_wpjm_options->setup_field_longitude:
				$meta_value = str_replace( ',', '.', $meta_value );
				break;

			case $goft_wpjm_options->setup_field_expiration:

				// Get the value provided by the user (if greater then current date) or default to settings duration.
				if ( $meta_value && strtotime( $meta_value ) > current_time( 'timestamp' ) ) {

					// Make sure the custom date is properly formatted.
					$meta_value = date( 'Y-m-d', strtotime( $meta_value ) );

					return $meta_value;
				}
				$meta_value = self::get_expire_date();
				break;

			case $goft_wpjm_options->setup_field_company_logo:

				// Cleanup the logo URL.
				if ( $meta_value ) {
					$meta_value = trim( $meta_value );
				}
				break;

		}
		return $meta_value;
	}

	/**
	 * Remove the expiry date meta on non-published jobs and let WPJM calculate it when the user publishes the job.
	 */
	public function maybe_remove_expiry_date( $post_id ) {
		global $goft_wpjm_options;

		if ( 'publish' !== get_post_status( $post_id ) ) {
			delete_post_meta( $post_id, $goft_wpjm_options->setup_field_expiration );
		}
	}

	/**
	 * Calculates the jobs expire date considering the WPJM job duration option.
	 * Leave empty if not set
	 */
	public static function get_expire_date( $date = '' ) {
		global $goft_wpjm_options;

		$duration = $goft_wpjm_options->jobs_duration ? $goft_wpjm_options->jobs_duration : 30;

		if ( $duration ) {
			$date = $date ? $date: current_time( 'mysql' );
			return date( 'Y-m-d', strtotime( $date . ' +' . absint( $duration ) . ' days' ) );
		}
	}

	/**
	 * Rerieve a list of default field mappings.
	 */
	public static function scrape_fields() {
		return apply_filters( 'goft_wpjm_scrape_fields', array(
			'logo',
			'logo_html',
			'description',
			'company',
			'location',
		) );
	}

	/**
	 * Rerieve a list of default field mappings.
	 */
	public static function default_mappings() {
		$fields = apply_filters( 'goft_wpjm_feed_fields', array(
			'description',
			'author',
			'creator',
			'city',
			'location',
			'country',
			'state',
			'latitude',
			'lat',
			'longitude',
			'long',
			'lng',
			'company',
			'company_name',
			'website',
			'site',
			'company_url',
			'company_site',
			'job_type',
			'type',
			'job_category',
			'category',
			'salary',
			'encoded'
		) );
		foreach ( $fields as $field ) {
			$mappings[ $field ] = self::default_field_mapping( $field, $field );
		}
		return $mappings;
	}

	/**
	 * the default field mappings for this provider.
	 */
	public static function default_field_mapping( $mapping, $field ) {
		global $goft_wpjm_options;

		$field = strtolower( $field );

		switch ( $field ) {
			case 'date':
				$mapping = 'date';
				break;

			case 'job_title':
			case 'title':
				$mapping = 'post_title';
				break;

			case 'job_description':
			case 'description':
			case 'jobdescription':
			case 'content':
			case 'encoded':
				$mapping = 'post_content';
				break;

			case 'link':
			case 'url':
				$mapping = $goft_wpjm_options->setup_field_application;
				break;

			case 'author':
			case 'creator':
				$mapping = 'post_author';
				break;

			case 'city':
				$mapping = $goft_wpjm_options->setup_field_city;
				break;

			case 'location':
				$mapping = $goft_wpjm_options->setup_field_location;
				break;

			case 'country':
				$mapping = $goft_wpjm_options->setup_field_country;
				break;

			case 'state':
				$mapping = $goft_wpjm_options->setup_field_state;
				break;

			case 'latitude':
			case 'lat':
				$mapping = $goft_wpjm_options->setup_field_latitude;
				break;

			case 'longitude':
			case 'long':
			case 'lng':
				$mapping = $goft_wpjm_options->setup_field_longitude;
				break;

			case 'company':
			case 'company_name':
				$mapping = $goft_wpjm_options->setup_field_company_name;
				break;

			case 'website':
			case 'company_url':
			case 'site':
				$mapping = $goft_wpjm_options->setup_field_company_url;
				break;

			case 'logo':
			case 'company_logo':
				$mapping = $goft_wpjm_options->setup_field_company_logo;
				break;

			case 'salary':
				if ( ! empty( $goft_wpjm_options->setup_field_salary ) ) {
					$mapping = $goft_wpjm_options->setup_field_salary;
				}
				break;

			case 'min_salary':
				if ( ! empty( $goft_wpjm_options->setup_field_salary_min ) ) {
					$mapping = $goft_wpjm_options->setup_field_salary_min;
				}
				break;

			case 'max_salary':
				if ( ! empty( $goft_wpjm_options->setup_field_salary_max ) ) {
					$mapping = $goft_wpjm_options->setup_field_salary_max;
				}
				break;

			// Taxonomies.

			case 'job_type':
			case 'type':
				$mapping = $goft_wpjm_options->setup_job_type;
				break;

			case 'job_category':
			case 'category':
				$mapping = $goft_wpjm_options->setup_job_category;
				break;

		}
		return $mapping;
	}

	/**
	 * Fields hidden on the sample table.
	 */
	public static function hidden_fields( $results ) {

		$fields = array_keys( $results['sample_item'] );

		$hidden_fields = array(
			'pubDate',
			'pubdate',
			'guid',
		);

		foreach ( array_intersect( $fields, $hidden_fields ) as $field ) {
			unset( $results['sample_item'][ $field ] );
		}
		return $results;
	}

	/**
	 * Fields that could causes issues if autaamtically mapped.
	 */
	public static function auto_mapping_reserved_fields() {
		global $goft_wpjm_options;

		$reserved_fields = array(
			$goft_wpjm_options->setup_field_application => '',
			$goft_wpjm_options->setup_field_expiration  => '',
		);
		return apply_filters( 'goft_wpjm_auto_mapping_reserved_fields', $reserved_fields );
	}

	/**
	 * Retrieve the nuclear fields alternative mappings.
	 */
	public static function nuclear_fields_alt_mappings() {
		global $goft_wpjm_options;

		$alt_mappings = array(
			'post_title'                                => 'title',
			'post_content'                              => 'description',
			$goft_wpjm_options->setup_field_application => 'link',
			$goft_wpjm_options->setup_field_location    => 'location',
		);

		return apply_filters( 'goft_wpjm_nuclear_fields_alt_mappings', $alt_mappings );
	}

	/**
	 * Retrieves the list of fields that always need to be mapped.
	 */
	protected static function nuclear_fields( $type = '' ) {

		// Only one of each group type must be mapped.
		$fields = array(
			'static' => array(
				'title' => array(
					'title',
					'post_title',
				),
				'description' => array(
					'description',
					'post_content',
				),
			),
			'dynamic' => array(
				'application' => array(
					'setup_field_application',
				),
			),
		);
		$fields = apply_filters( 'goft_wpjm_field_nuclear_fields', $fields );

		if ( $type ) {
			if ( empty( $fields[ $type ] ) ) {
				return false;
			}
			return $fields[ $type ];
		}
		return $fields;
	}

	/**
	 * Validate the mappings to make sure all the required fields are mapped.
	 *
	 * Uses a sampled item to get the raw fields and the mappings to get the dynamic fields.
	 *
	 * Succeeds if at least one field of each of the field group types is mapped.
	 */
	public static function validate_mappings( $valid, $mappings, $items, $content_type ) {
		global $goft_wpjm_options;

		// Merges the items fields with all the mappings fields.
		$sample_item_fields = array_keys( array_shift( $items ) );
		$mapped_fields = array_filter( array_values( $mappings ) );

		$mapped_fields = array_merge( $mapped_fields, $sample_item_fields );

		$nuclear_fields = self::nuclear_fields();

		// For regular RSS feeds, ignore the dynamic field mappings (e.g: 'link').
		if ( 'RSS' === $content_type || in_array( 'link', $mapped_fields ) ) {
			unset( $nuclear_fields['dynamic'] );
		}

		$matches = array();

		// Stores the number of field matches that makes the mappings valid.
		$must_match_count = 0;

		foreach ( $nuclear_fields as $group => $groups ) {

			foreach ( $groups as $type => $fields ) {

				$must_match_count++;
				$valid = false;

				foreach ( $fields as $field ) {
					$field_check = $field;

					if ( 'static' !== $group ) {
						$field_check = $goft_wpjm_options->$field;
					}

					if ( false !== in_array( $field_check, $mapped_fields ) ) {
						$matches[] = $field_check;
						$valid = true;
						break;
					}
				}

				if ( ! $valid ) {
					$failed[] = strtoupper( $type );
				}
			}
		}

		if ( count( $matches ) >= $must_match_count ) {
			return true;
		}

		$failed = implode( '</li><li>', $failed );

		$error  = sprintf( __( 'Import failed because the following fields were not mapped: <strong>%s</strong>', 'gofetch-wpjm' ), html( 'ul class="missing-mappings"', html( 'li', $failed ) ) );
		$error .= '</br>' . __( 'Please make sure you map the field(s) and try again.', 'gofetch-wpjm' );

		$error .= '<style>.missing-mappings{list-style:circle;margin-left:30px;}</style>';

		return new WP_Error( '-999', $error );
	}

}

GoFetch_Dynamic_Import::instance();
