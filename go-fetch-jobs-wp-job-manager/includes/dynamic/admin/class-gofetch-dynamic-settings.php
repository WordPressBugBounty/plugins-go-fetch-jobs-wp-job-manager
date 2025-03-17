<?php
/**
 * Handles admin settings that depend on the current job theme/plugin setup
 *
 * @package GoFetch/Dynamic/Admin/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class GoFetch_Dynamic_Settings extends GoFetch_Admin_Builder {

	public function __construct() {
		add_filter( 'goft_wpjm_meta_fields', array( $this, 'meta_fields' ) );
		add_filter( 'goft_wpjm_form_extra_content', array( $this, 'other_hidden_fields' ) );
		add_filter( 'goft_wpjm_default_value_for_field', array( $this, 'default_value_for_field' ), 10, 2 );
		add_filter( 'goft_wpjm_default_value_for_taxonomy', array( $this, 'default_value_for_tax' ), 10, 3 );
		add_filter( 'goft_wpjm_template_setup', array( $this, 'template_setup' ), 10, 3 );
	}

	/**
	 * WP Job Manager Meta Fields.
	 */
	public function meta_fields( $fields ) {
		global $goft_wpjm_options;

		$fields = array(
			array(
				'title' => __( 'Remote', 'gofetch-wpjm' ),
				'type'  => 'checkbox',
				'name'  => 'meta[' . $goft_wpjm_options->setup_field_remote . ']',
				'tip'   => __( 'Check this option to flag all imported jobs as Remote jobs.', 'gofetch-wpjm' ),
				'extra' => array(
					'section'      => 'meta',
					'data-default' => '0',
					'data-remote'  => '1'
				),
				'value' => '0',
			),
			array(
				'title'  => __( 'Expiry Date', 'gofetch-wpjm' ),
				'name'   => '_blank',
				'type'   => 'custom',
				'tip'    => __( 'Choose the expiry date for the jobs being imported.', 'gofetch-wpjm' ),
				'render' => array( $this, 'output_expiry_field' )
			),
			array(
				'title' => __( 'Location', 'gofetch-wpjm' ),
				'name'  => 'meta[' . $goft_wpjm_options->setup_field_location . ']',
				'extra' => array(
					'class'          => 'geocomplete regular-text',
					'placeholder'    => __( 'e.g: Lisbon', 'gofetch-wpjm' ),
					'data-default'   => __( 'Anywhere', 'gofetch-wpjm' ),
					'data-core-name' => 'location',
				),
				'value' => $this->get_default_value_for_meta( $goft_wpjm_options->setup_field_location ),
				'tip'   => __( 'The location for the jobs being imported.', 'gofetch-wpjm' ),
				'desc'  => $goft_wpjm_options->geocode_api_key ? '<br/><img class="goft-powered-by-google" src="' . esc_url( GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/images/powered_by_google_on_white_hdpi.png' ) . '">' : '',
			),
			array(
				'title' => __( 'Company', 'gofetch-wpjm' ),
				'name'  => 'meta[' . $goft_wpjm_options->setup_field_company_name . ']',
				'value' => $this->get_default_value_for_meta( $goft_wpjm_options->setup_field_company_name ),
				'extra' => array(
					'placeholder'    => __( 'e.g: Google', 'gofetch-wpjm' ),
					'data-core-name' => 'company',
				),
				'tip' => __( 'Company name for the jobs being imported.', 'gofetch-wpjm' ),
			),
			array(
				'title'  => __( 'Logo', 'gofetch-wpjm' ),
				'name'  => 'meta[' . $goft_wpjm_options->setup_field_company_logo . ']',
				'type'   => 'custom',
				'value'  => $this->get_default_value_for_meta( $goft_wpjm_options->setup_field_company_logo ),
				'extra' => array(
					'data-core-name' => 'logo',
				),
				'render' => array( $this, 'logo_uploader' ),
				'tip' => __( 'Company logo for the jobs being imported.', 'gofetch-wpjm' ),
			),
			array(
				'title' => __( 'Website', 'gofetch-wpjm' ),
				'name'  => 'meta[' . $goft_wpjm_options->setup_field_company_url . ']',
				'value' => $this->get_default_value_for_meta( $goft_wpjm_options->setup_field_company_url ),
				'extra' => array(
					'placeholder' => __( 'e.g: www.google.com', 'gofetch-wpjm' ),
					'data-core-name' => 'website',
				),
				'tip' => __( 'Company Website for the jobs being imported.', 'gofetch-wpjm' ),
			),
		);
		return apply_filters( 'goft_jobs_meta_fields', $fields );
	}

	/**
	 * Outputs a meta field.
	 */
	public function output_featured_meta_field() {
		return apply_filters( 'goft_wpjm_setting_meta_featured', false );
	}

	/**
	 * Output additional form hidden fields.
	 */
	public function other_hidden_fields( $content ) {

		$fields = array();

		foreach ( $fields as $field => $atts ) {
			$content .= $this->input( $atts );
		}

		return $content;
	}

	/**
	 * Renders the provider logo uploader field.
	 */
	public function logo_uploader() {
		global $goft_wpjm_options;

		$meta_field = 'meta[' . $goft_wpjm_options->setup_field_company_logo . ']';

		$logo = ! empty( $_POST[ $meta_field ] ) ? sanitize_url( $_POST[ $meta_field ] ) : '';

		if ( ! $logo && $goft_wpjm_options->company_logo_default ) {
			$logo = wp_get_attachment_image_url( $goft_wpjm_options->company_logo_default );
		}

		$field = array(
			'name'  => $meta_field,
			'type'  => 'text',
			'extra' => array(
				'class'       => 'goft-company-logo goft-image regular-text',
				'placeholder' => 'e.g: google.png',
				'section'     => 'meta',
			),
			'tip'   => __( 'Default company logo or placeholder, to apply to jobs without a logo.', 'gofetch-wpjm' ),
			'value' => $logo,
			'desc'  => html( 'input', array( 'type' => 'button', 'name' => 'upload_company_logo', 'class' => 'goft-company-logo goft-upload button-secondary', 'value' => __( 'Browse...', 'gofetch-wpjm' ) ) ),
		);
		return $this->image_uploader( $field, 'goft-company-logo' );
	}

	/**
	 * The default value to use on a given meta field.
	 */
	public function default_value_for_field( $value, $field ) {
		global $goft_wpjm_options;

		switch ( $field ) {

			case $goft_wpjm_options->setup_field_expiration:
				$value = $this->get_expire_date();
				break;

			case $goft_wpjm_options->setup_field_application:
				$value = __( 'Apply to this job by clicking this <a href="%external_apply_to_url%">link</a>', 'gofetch-wpjm' );
				break;

		}
		return $value;
	}

	/**
	 * Override template setup values if necessary.
	 */
	public function template_setup( $settings ) {
		global $goft_wpjm_options;

		$expire_field = $goft_wpjm_options->setup_field_expiration;

		// Decode any JSON encoded URL's
		if ( ! empty( $settings['rss_feed_import'] ) ) {
			$settings['rss_feed_import'] = stripslashes( $settings['rss_feed_import'] );
		}

		// Always calculate a new expiry date.
		if ( ! empty( $settings['meta'][ $expire_field ] ) ) {
			$settings['meta'][ $expire_field ] = GoFetch_Dynamic_Import::get_expire_date();
		}
		return $settings;
	}

	/**
	 * Default to use on a given taxonomy.
	 */
	public function default_value_for_tax( $value, $tax, $slug = '' ) {

		$args = array(
			'number'     => 1,
			'fields'     => 'id=>slug',
			'hide_empty' => false,
		);

		if ( $slug ) {
			$args['slug'] = $slug;
		}

		$terms = get_terms( $tax, $args );

		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			$value = reset( $terms );
		}
		return $value;
	}

	/**
	 * Outputs the date interval settings.
	 */
	public function output_expiry_field() {
		global $goft_wpjm_options;

		$value      = $this->get_default_value_for_meta( $goft_wpjm_options->setup_field_expiration );
		$meta_field = 'meta[' . $goft_wpjm_options->setup_field_expiration . ']';

		$atts = array(
			'type'  => 'text',
			'name'  => $meta_field,
			'extra' => array(
				'section'      => 'meta',
				'class'        => 'span_date meta-job-expires',
				'style'        => 'width: 120px;',
				'placeholder'  => __( 'click to choose...', 'gofetch-wpjm' ),
				'readonly'     => true,
				'data-default' => $value,
				),
			'desc'  => html( 'a', array( 'class' => 'button clear_span_dates', 'data-goft_parent' => 'meta-job-expires' ), __( 'Clear', 'gofetch-wpjm' ) ),
			'value' => $value,
		);
?>
		<script>
			jQuery(document).ready(function($) {

				// Date picker.
				$('.meta-job-expires').datepicker({
					dateFormat: 'yy-mm-dd',
					changeMonth: true,
				});

				$(document).on( 'goftj_rss_content_loaded', function( e, data ) {

					$('.meta-job-expires').bind( 'change', function() {
						var date = new Date( $(this).val() );

						if ( date.getTime() > $.now() ) {
							$(this).removeClass('value-warning');
						} else {
							$(this).addClass('value-warning')
						}

					});
					$('.meta-job-expires').change();
				});

			});
		</script>
<?php
		return $this->input( $atts );
	}

	/**
	 * Calculates the jobs expire date considering the job duration option.
	 * Leave empty if not set
	*/
	public function get_expire_date( $date = '' ) {
		global $goft_wpjm_options;

		$duration = $goft_wpjm_options->jobs_duration ? $goft_wpjm_options->jobs_duration : 30;

		if ( $duration ) {
			$date = $date ? $date: current_time( 'mysql' );
			return date( 'Y-m-d', strtotime( $date . ' +' . absint( $duration ) . ' days' ) );
		}
	}

}

$GLOBALS['goft_wpjm']['specific_settings'] = new GoFetch_Dynamic_Settings();
