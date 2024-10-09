<?php
/**
 * Admin options for the 'Setup' page.
 *
 * @package GoFetchJobs/Admin/Setup
 */


// __Classes.

class GoFetch_Admin_Setup {

	protected $all_tabs;

	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! empty( $_GET['gofj_setup'] ) ) {
			add_action( 'tabs_go-fetch-jobs_page_go-fetch-jobs-wpjm-settings', array( $this, 'tabs' ), 99 );
		}
	}

	/**
	 * Init the custom tabs.
	 */
	public function tabs( $all_tabs ) {
		$this->all_tabs = $all_tabs;

		$this->all_tabs->tabs->add( 'setup', __( 'Setup', 'gofetch-wpjm' ) );

		$this->tab_setup();
	}

	/**
	 * General settings tab.
	 */
	protected function tab_setup() {
		global $goft_wpjm_options;

		$this->all_tabs->tab_sections['setup']['post_type'] = array(
			'title' => __( 'Admin', 'gofetch-wpjm' ),
			'fields' => array(
				array(
					'title' => __( 'Post Type', 'gofetch-wpjm' ),
					'name'  => 'setup_post_type',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_post_types(),
				),
				array(
					'title' => __( 'Job Categories', 'gofetch-wpjm' ),
					'name'  => 'setup_job_category',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_taxonomies(),
				),
				array(
					'title' => __( 'Job Types', 'gofetch-wpjm' ),
					'name'  => 'setup_job_type',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_taxonomies(),
				),
				array(
					'title' => __( 'Expired Status', 'gofetch-wpjm' ),
					'name'  => 'setup_expired_status',
					'type'    => 'text',
				),
			),
		);

		$this->all_tabs->tab_sections['setup']['custom_fields'] = array(
			'title' => __( 'Custom Fields', 'gofetch-wpjm' ),
			'fields' => array(
				array(
					'title' => __( 'Company Name', 'gofetch-wpjm' ),
					'name'  => 'setup_field_company_name',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_custom_fields(),
					'selected' => GOFT_Setup_Helper::get_best_match( 'company_name' ),
				),
				array(
					'title' => __( 'Company Logo', 'gofetch-wpjm' ),
					'name'  => 'setup_field_company_logo',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_custom_fields(),
					'default' => GOFT_Setup_Helper::get_best_match( 'company_logo' ),
				),
				array(
					'title' => __( 'Company URL', 'gofetch-wpjm' ),
					'name'  => 'setup_field_company_url',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_custom_fields(),
					'default' => GOFT_Setup_Helper::get_best_match( array( 'company_url', 'company_website' ) ),
				),
				array(
					'title' => __( 'Location / City', 'gofetch-wpjm' ),
					'name'  => 'setup_field_location',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_custom_fields(),
					'default' => GOFT_Setup_Helper::get_best_match( array( 'location', 'city' ) ),
				),
				array(
					'title' => __( 'Latitude', 'gofetch-wpjm' ),
					'name'  => 'setup_field_latitude',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_custom_fields(),
					'default' => GOFT_Setup_Helper::get_best_match( 'latitude' ),
				),
				array(
					'title' => __( 'Longitude', 'gofetch-wpjm' ),
					'name'  => 'setup_field_longitude',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_custom_fields(),
					'default' => GOFT_Setup_Helper::get_best_match( 'longitude' ),
				),
				array(
					'title' => __( 'Application', 'gofetch-wpjm' ),
					'name'  => 'setup_field_application',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_custom_fields(),
					'default' => GOFT_Setup_Helper::get_best_match( 'application' ),
				),
				array(
					'title' => __( 'Expiration', 'gofetch-wpjm' ),
					'name'  => 'setup_field_expiration',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_custom_fields(),
					'default' => GOFT_Setup_Helper::get_best_match( array( 'expire', 'expiration' ) ),
				),
				array(
					'title' => __( 'Featured?', 'gofetch-wpjm' ),
					'name'  => 'setup_field_featured',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_custom_fields(),
					'default' => GOFT_Setup_Helper::get_best_match( array( 'featured' ) ),
				),
				array(
					'title' => __( 'Remote?', 'gofetch-wpjm' ),
					'name'  => 'setup_field_remote',
					'type'    => 'select',
					'choices' => GOFT_Setup_Helper::get_custom_fields(),
					'default' => GOFT_Setup_Helper::get_best_match( array( 'remote' ) ),
				),
			),
		);

	}
}


/**
 * The plugin admin setup helper class.
 */
class GOFT_Setup_Helper {

	/**
	 * Get a list of registered post types.
	 */
	public static function get_post_types() {

		$default_keyword_match = 'job';

		$args = array(
			'public'   => true,
			'_builtin' => false,
		);
		$post_types_objects = get_post_types( $args, 'objects' );

		$post_types = array();

		foreach ( $post_types_objects as $post_type ) {
			if ( strpos( $post_type->name, $default_keyword_match ) !== false ) {
				$suggested_post_type[ $post_type->name ] = $post_type->label;
			} else {
				$post_types[ $post_type->name ] = $post_type->label;
			}
		}

		if ( ! empty( $suggested_post_type ) ) {
			$post_types = array_merge( $suggested_post_type, $post_types );
		}
		return $post_types;
	}

	/**
	 * Get a list of taxonomies for the registered post type.
	 */
	public static function get_taxonomies( $field = 'name' ) {
		global $goft_wpjm_options;
		$taxonomies = get_object_taxonomies( $goft_wpjm_options->setup_post_type, 'objects' );

		$tax_labels = array();

		foreach ( $taxonomies as $tax ) {
			$labels = get_taxonomy_labels( $tax );
			$tax_labels[ $tax->name ] = strtoupper( $labels->$field );
		}
		return apply_filters( 'goft_wpjm_taxonomies', $tax_labels );
	}

	/**
	 * Get a list of all the cutom fields for a post type.
	 */
	public static function get_custom_fields() {
		global $wpdb, $goft_wpjm_options;
		static $_goft_setup_custom_fields;

		if ( $_goft_setup_custom_fields ) {
			return $_goft_setup_custom_fields;
		}

		$post_type = ! empty( $goft_wpjm_options->setup_post_type ) ? $goft_wpjm_options->setup_post_type : 'job_listing';

		$sql = "SELECT post_id, max( custom_keys ) AS max_keys FROM (
					SELECT post_id, count( meta_key ) AS custom_keys FROM $wpdb->posts a, $wpdb->postmeta b
					WHERE a.id = b.post_id
					AND post_type = %s
					AND post_status IN ( 'publish', 'pending', 'draft' )
					GROUP BY 1
				) AS posts";

		$post_id = $wpdb->get_var( $wpdb->prepare( $sql, $post_type ) );

		$custom_fields_temp = (array) get_post_custom_keys( $post_id );

		// Filter out reserved fields from the list of fields.
		$filter_reserved_fields = function( $el ) {

			$reserved_fields = apply_filters( 'goft_wpjm_mappings_reserved_fields', array(
				'_goft_',
				'_gofj_',
				'_wp_',
			) );

			// Find matches for each custom field.
			$filter = function( $part ) use ( $el ) {
				return stripos( $el, $part ) !== false;
			};
			$matches = array_filter( $reserved_fields, $filter );

			return empty( $matches );
		};
		$custom_fields = array_filter( $custom_fields_temp, $filter_reserved_fields );

		asort( $custom_fields );

		$_goft_setup_custom_fields = apply_filters( 'goft_wpjm_custom_fields', array_combine( $custom_fields, $custom_fields ) );
		//$_goft_setup_custom_fields = array_merge( array( '' => 'Map to ...' ), $_goft_setup_custom_fields );

		return $_goft_setup_custom_fields;
	}

	/**
	 * Finds the closest string match from a list of strings.
	 */
	public static function get_best_match( $labels, $min_percent = 70 ) {
		$fields = self::get_custom_fields();

		$fields = array_diff_key( $fields, GoFetch_Dynamic_Import::auto_mapping_reserved_fields() );

		add_filter( 'goft_wpjm_mapping_match_weight', array( __CLASS__, 'mapping_weights' ), 10, 3 );

		$result = array();

		unset( $fields[''] );

		foreach ( $fields as $key => $value ) {
			foreach ( (array) $labels as $label ) {
				similar_text( $label, $value, $percent );
				$result[ $value ] = apply_filters( 'goft_wpjm_mapping_match_weight', (float) $percent, $label, $value );
				if ( $result[ $value ] >= $min_percent ) {
					continue;
				}
			}
		}

		arsort( $result );
		$closest = key( $result );

		if ( ! empty( $result ) && $result[ $closest ] >= $min_percent ) {
			return $closest;
		}
	}

	/**
	 * Override some field best match weigths to give them more or lesse priority.
	 */
	public static function mapping_weights( $percent, $label, $value ) {

		// Give 'logo' fields less weight.
		if ( strpos( $value, 'logo') !== false ) {
			$percent--;
		}
		return $percent;
	}

}

new GoFetch_Admin_Setup();
