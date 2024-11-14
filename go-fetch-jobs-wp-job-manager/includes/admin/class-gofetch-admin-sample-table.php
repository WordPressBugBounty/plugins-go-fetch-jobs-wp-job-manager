<?php
/**
 * Extends the HTML table class to output an HTML table with sample content.
 *
 * @package GoFetchJobs/Admin/Sample Table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Sample HTML table class.
 */
class GoFetch_Sample_Table extends GoFetch_HTML_Table {

	/**
	 * Constructor.
	 */
	public function __construct( $fields, $content_type, $url, $default_mappings = array() ) {
		$this->url          = $url;
		$this->fields       = $this->make_fields_struct( $fields, $default_mappings );
		$this->content_type = $content_type;
	}

	/**
	 * Prepares and displays the final table.
	 */
	public static function display( $query_args = array(), $fields = array(), $url = '' ) {

		$defaults = array(
			'content_type'     => GoFetch_Jobs()->parent_post_type,
			'default_mappings' => array(),
		);
		$query_args = wp_parse_args( $query_args, $defaults );

		// Output the table.
		$table = new GoFetch_Sample_Table( $fields, $query_args['content_type'], $url, $query_args['default_mappings'] );

		ob_start();

		$table->show();

		return ob_get_clean();
	}

	/**
	 * Outputs the final table.
	 */
	public function show() {
		echo wp_kses_post( $this->table( $this->fields, array( 'class' => 'goft_wpjm_table widefat fields' ) ) );
	}

	/**
	 * Retrieves the table header.
	 */
	public function header( $data ) {

		$cols = 3;

		for ( $i = 0; $i <= $cols; $i++ ) {
			$atts[ $i ] = array(
				'class' => 'index',
				'width' => '50%',
			);

			if ( $i === 0 ) {
				$atts[ $i ]['width'] = '30%';
			}
		}

		$cells = $this->cells( array(
			__( 'Tags/Keys', 'gofetch-wpjm' ),
			__( 'DB Field Mapping', 'gofetch-wpjm' ),
			__( 'Sample Content', 'gofetch-wpjm' ),
		), $atts, 'td' );

		return html( 'tr', array(), $cells );
	}

	/**
	 * Retrieves the table footer.
	 */
	public function footer( $data ) {
		return $this->header( $data );
	}

	/**
	 * Retrieves the table row.
	 */
	protected function row( $item = array(), $atts = array() ) {

		if ( ! isset( $item['data'] ) ) {
			$item['data'] = '-';
		} elseif ( is_serialized( $item['data'] ) ) {
			$item['data'] = __( '[serialized data]', 'gofetch-wpjm' );
		}

		$field_atts = array(
			'type'    => 'checkbox',
			'name'    => 'field[]',
			'class'   => 'field',
			'value'   => $item['name'],
			'checked' => $item['checked']
		);

		if ( 'user_pass' == $item['name'] ) {
			$field_atts['disabled'] = 'disabled';
		}

		$element = '';

		if ( ! empty( $item['core_field'] ) ) {
			$element = html( 'span', array( 'class' => 'mappings-core-field', 'style' => 'display: none;' ), $item['core_field'] );
		}

		$cells = $this->cells( array(
			html( 'span', array( 'class' => 'field' ), '&lt;' . $item['name'] . '/&gt;' ) .
			html( 'input', array( 'type' => 'hidden', 'name' => 'type[' . $item['name'] . ']', 'value' => $item['type'] ) ),
			html( 'span', array( 'class' => 'mappings' ), $item['mapping'] ) . $element,
			html( 'span', array( 'class' => 'sample_content' ), $item['data'] ),
		) );

		if ( '_' == $item['name'][0] ) {
			$atts['class'] .= ' hidden_field';
		}
		$atts['class'] .= ' ' . $item['type'];

		return html( 'tr', $atts, $cells );
	}

	/**
	 * Given a list of fields retrieves them as a normalized associative array.
	 */
	private function make_fields_struct( $fields, $default_mappings = array(), $type = 'custom', $content_type = 'post' ) {
		global $goft_wpjm_options;

		$internal_default_mappings = GoFetch_Dynamic_Import::default_mappings();

		$default_mappings = wp_parse_args( $default_mappings, $internal_default_mappings );

		$fields_struct = array();

		$reserved_fields = apply_filters( 'goft_wpjm_field_struct', array(
			'title'       => __( 'JOB TITLE', 'gofetch-wpjm' ),
			'description' => __( 'JOB DESCRIPTION', 'gofetch-wpjm' ),
			'date'        => __( 'JOB DATE', 'gofetch-wpjm' ),
			'link'        => __( 'APPLICATION URL', 'gofetch-wpjm' ),
		));

		$custom_fields = GOFT_Setup_Helper::get_custom_fields();

		$core_custom_fields = self::group_core_fields( $custom_fields );

		$flipped_core_custom_fields = array_flip( $core_custom_fields );
		$flipped_core_custom_fields['JOB TITLE'] = 'post_title';
		$flipped_core_custom_fields['JOB DESCRIPTION'] = 'post_content';

		// Remove core custom fields from the list of custom fields.
		$custom_fields = array_diff_key( $custom_fields, $core_custom_fields );

		if ( ! empty( $custom_fields ) ) {

			$custom_fields = array_merge(
				array( '_reserved_custom_fields_'  => __( 'OTHER CUSTOM FIELDS', 'gofetch-wpjm' ) ),
				$custom_fields
			);

		}

		$user_custom_fields = apply_filters( 'goft_wpjm_user_custom_fields', array() );

		if ( ! empty( $user_custom_fields ) ) {

			$custom_fields = array_merge(
				array( '_reserved_user_custom_fields_'  => __( 'USER CUSTOM FIELDS', 'gofetch-wpjm' ) ),
				$user_custom_fields
			);

		}

		$taxonomies = array_merge(
			array( '_reserved_tax_'  => __( 'TAXONOMIES', 'gofetch-wpjm' ) ),
			GOFT_Setup_Helper::get_taxonomies( 'singular_name' )
		);

		$post_fields = apply_filters( 'goft_wpjm_post_fields', array(
			'_reserved_post_'                           => __( 'JOB', 'gofetch-wpjm' ),
			'post_content'                              => __( 'JOB DESCRIPTION', 'gofetch-wpjm' ),
			'post_title'                                => __( 'JOB TITLE', 'gofetch-wpjm' ),
			'date'                                      => __( 'JOB DATE', 'gofetch-wpjm' ),
			$goft_wpjm_options->setup_field_application => __( 'APPLICATION URL', 'gofetch-wpjm' ),
		) );

		$all_fields = array_merge( $post_fields, $taxonomies, $core_custom_fields, $custom_fields );

		$mapped = array();

		foreach ( $fields as $field => $data ) {

			$use_default_mapping = false;

			if ( ! is_array( $data ) && isset( $data ) ) {
				$data = array( 'data' => $data );
			}

			$default = apply_filters( 'goft_wpjm_field_mapping_default', GOFT_Setup_Helper::get_best_match( $field ), $field, $this->url );

			// If the feed has a default mapping, prioritize it.
			if ( isset( $default_mappings[ $field ] ) ) {
				$default = $default_mappings[ $field ];
				$use_default_mapping = true;
			}

			// Make sure we map each field only once.
			if ( $default ) {
				if ( ! isset( $mapped[ $default ] ) ) {
					$mapped[ $default ] = $field;
				} else {
					// If the field was already mapped, unset the previous mapping, and prioritize the default mapping.
					if ( $use_default_mapping ) {
						$previously_mapped_field = $mapped[ $default ];
						// Allow changing the original mapping.
						$fields_struct[ $previously_mapped_field ]['mapping'] = self::core_fields_grouped_dropdown( $all_fields, $field, '' );
					} else {
						$default = null;
					}
				}
			}

			// Return mapping as 'auto' if mapping uses internal GOFJ field (in sample mode only).
			if ( ! empty( $default ) && false !== strpos( $default, 'gofj_' ) ) {
				$reserved_fields[ $field ] = __( 'AUTO ASSIGNED', 'gofetch-wpjm' );
				if ( empty( $reserved_fields_internal ) ) {
					$reserved_fields_internal = array();
				}
				$reserved_fields_internal[ $field ] = $default;
			}

			if ( ! in_array( $field, array_keys( $reserved_fields ) ) ) {
				$data['mapping'] = self::core_fields_grouped_dropdown( $all_fields, $field, $default );
			}

			$core_field = '';

			if ( ! empty( $reserved_fields[ $field ] ) && isset( $flipped_core_custom_fields[ $reserved_fields[ $field ] ] ) ) {
				$core_field = $flipped_core_custom_fields[ $reserved_fields[ $field ] ];
			} elseif( ! empty( $reserved_fields_internal[ $field ] ) && !isset( $flipped_core_custom_fields[ $reserved_fields_internal[ $field ] ] ) ) {
				$core_field = $reserved_fields_internal[ $field ];
			}

			$default = array(
				'name'              => $field,
				'type'              => $type,
				'content_data_type' => GoFetch_Helper::get_field_type( $field, $content_type ),
				'data'              => '-',
				'label'             => '',
				'checked'           => false,
				'core_field'        => $core_field,
				'mapping'           => ! empty( $reserved_fields[ $field ] ) ? $reserved_fields[ $field ] : '-' ,
			);
			$fields_struct[ $field ] = wp_parse_args( $data, $default );
		}
		return $fields_struct;
	}

	/**
	 * Output an option grouped dropdown.
	 */
	protected static function core_fields_grouped_dropdown( $fields, $name, $default ) {

		$group = $options = $optgroup = '';

		// Iterate through the categories.
		foreach ( $fields as $value => $field ) {

			// Group each time if finds a reserved field.
			if ( false !== strpos( $value, '_reserved_' ) ) {

				if ( $group && $field !== $group ) {
					$optgroup .= html( 'optgroup', array( 'label' => esc_attr( ucwords( $group ) ) ), $options );
					$options = '';
				}
				$group = $field;

			} else {
				$atts = array( 'value' => esc_attr( $value ) );
				if ( $value === $default ) {
					$atts['selected'] = 'selected';
				}
				$options .= html( 'option', $atts, $field );
			}
		}

		// Include the last group of fields.
		$optgroup .= html( 'optgroup', array( 'label' => esc_attr( ucwords( str_replace( '***', '', $group ) ) ) ), $options );
		//

		$optgroup = html( 'option', array( 'value' => '' ), __( 'Map To Field . . .', 'gofetch-wpjm' ) ) . $optgroup;

		$atts = array(
			'name' => sprintf( 'field_mappings[ %s ]', $name ),
		);

		return html( 'select', $atts, $optgroup );
	}

	/**
	 * Given a list of fields, group the main fields used on the current job board.
	 */
	public static function group_core_fields( $fields ) {
		global $goft_wpjm_options;

		$main_fields = apply_filters( 'goft_wpjm_mappings_main_fields', array(
			'setup_field_company_name' => 'COMPANY NAME',
			'setup_field_company_logo' => 'COMPANY LOGO',
			'setup_field_company_url'  => 'COMPANY URL',
			'setup_field_application'  => 'APPLICATION URL',
			'setup_field_location'     => 'LOCATION',
			'setup_field_country'      => 'COUNTRY',
			'setup_field_city'         => 'CITY',
			'setup_field_state'        => 'STATE',
			'setup_field_latitude'     => 'LATITUDE',
			'setup_field_longitude'    => 'LONGITUDE',
			'setup_field_salary_min'   => 'MIN SALARY',
			'setup_field_salary_max'   => 'MAX SALARY',
			'setup_field_salary'       => 'SALARY',
			'setup_field_remote'       => 'IS REMOTE',
			'setup_field_featured'     => 'IS FEATURED',
		) );

		$core_custom_fields = array();

		foreach ( $main_fields as $field => $name ) {

			if ( isset( $goft_wpjm_options->$field ) ) {
				$field_name = $goft_wpjm_options->$field;
				$core_custom_fields[ $field_name ] = $name;
			}
		}

		if ( ! empty( $core_custom_fields ) ) {

			$core_custom_fields = apply_filters( 'goft_wpjm_field_mappings_core_fields', $core_custom_fields );

			asort( $core_custom_fields );

			$core_custom_fields = array_merge(
				array( '_reserved_core_custom_fields_'  => __( 'CORE CUSTOM FIELDS', 'gofetch-wpjm' ) ),
				$core_custom_fields
			);

		}
		return apply_filters( 'goft_wpjm_field_mappings_core_fields_group', $core_custom_fields );
	}

}
