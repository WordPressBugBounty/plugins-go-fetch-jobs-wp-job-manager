<?php
/**
 * Provides and outputs the template builder.
 *
 * @package GoFetch/Admin/Settings
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $goft_wpjm_options;

/**
 * Screen options class.
 */
class GoFetch_Help_Admin_Screen_Options {

	/**
	 * @var The current screen ID.
	 */
	var $screen_id;

	public function __construct() {
		$this->screen_id = sprintf( 'toplevel_page_%s', GoFetch_Jobs()->slug );
		add_filter( 'screen_settings', array( $this, 'screen_settings' ), 12, 3 );
	}

	/**
	 * Retrieves the custom settings HTML.
	 */
	public function screen_settings( $settings, $args ) {
		global $goft_wpjm_options;

		if ( $args->base === $this->screen_id ) {

			$options = get_user_meta( get_current_user_id(), 'bc_screen_options', true );

			$input = 'goft-settings-type';
			$input_2 = 'goft-provider-source-settings';
			$input_3 = 'goft-affiliate-param-settings';

			$value = 'advanced';

			if ( ! empty( $options[ $input ] ) ) {
				$value = $options[ $input ];
			}

			$value_2 = ! empty( $options[ $input_2 ] );
			$value_3 = ! empty( $options[ $input_3 ] );

			$checked_2 = checked( $value_2, true, false );
			$checked_3 = checked( $value_3, true, false );

			$settings .= "
				<fieldset class='gofetch-screen-options'>
					<legend>" . __( 'Toggle Basic/Advanced Settings <small>(changes are applied instantly)</small>', 'gofetch-wpjm' ) . "</legend>
					<div class='metabox-prefs'>
						<label for='bc_screen_options[" . esc_attr( $input ) . "]'>
							<input type='radio' value='basic' " . checked( 'basic' === $value, true, false ) . " name='bc_screen_options[" . esc_attr( $input ) . "]' id='" . esc_attr( $input ) . "' />" . __( 'Basic', 'gofetch-wpjm' ) . "
							<input type='radio' value='advanced' " . checked( 'advanced' === $value, true, false ) . " name='bc_screen_options[" . esc_attr( $input ) . "]' id='" . esc_attr( $input ) . "' />" . __( 'Advanced', 'gofetch-wpjm' ) . "
						</label>
					</div>
					<div class='metabox-prefs'>
						<label for='bc_screen_options[" . esc_attr( $input_2 ) . "]'>
							<input type='checkbox' " . selected( $checked_2 ) . " value='on' $checked_2 name='bc_screen_options[" . esc_attr( $input_2 ) . "]' id='" . esc_attr( $input_2 ) . "' />" . __( 'Display provider details group <small>(allows tweaking the provider details: name, website and logo)</small>', 'gofetch-wpjm' ) . "
						</label><br/>" .
						( gfjwjm_fs()->can_use_premium_code() ? "
						<label for='bc_screen_options[" . esc_attr( $input_3 ) . "]'>
							<input type='checkbox' " . selected( $checked_3 ) . " value='on' $checked_3 name='bc_screen_options[" . esc_attr( $input_3 ) . "]' id='" . esc_attr( $input_3 ) . "' />" . __( 'Display affiliate parameters group <small>(allows providing additional parameters on the feed URL)</small>', 'gofetch-wpjm' ) . "
						</label>"
						 : "" ) . "
					</div>
				</fieldset>";
		}
		return $settings;
	}

}

/**
 * Settings Admin class.
 */
class GoFetch_Admin_Builder extends BC_Framework_Admin_page {

	private static $taxonomy_compat;

	private static $taxonomy_compat_index = 0;

	private static $imported = 0;

	/**
	 * Setup the Import page.
	 */
	public function setup() {

		$this->args = array(
			'page_title'    => __( 'Import Jobs', 'gofetch-wpjm' ),
			'page_slug'     => GoFetch_Jobs()->slug,
			'parent'        => 'options.php',
			'menu_title'    => __( 'Go Fetch Jobs', 'gofetch-wpjm' ),
			'submenu_title' => __( 'Import Jobs', 'gofetch-wpjm' ),
			'toplevel'      => 'menu',
		);

		add_action( 'wp_ajax_goft_wpjm_save_template', array( $this, 'ajax_save_template' ) );
		add_action( 'admin_init', array( $this, 'init_tooltips' ), 9999 );
	}

	/**
	 * Load tooltips for the current screen.
	 * Avoids loading multiple tooltip instances on metaboxes.
	 */
	public function init_tooltips() {
		new BC_Framework_ToolTips( array( 'toplevel_page_gofetch' ) );
	}

	/**
	 * The settings for the Import page.
	 */
	public function page_content() {

		if ( get_option( 'goft-wpjm-error' ) ) {
			return;
		}

		$file_upload_switch = $file_upload_switch_upsell = $feed_load_switch = '';

		$this->init_tooltips();

		$premium_custom_files_text = ', ';

		if ( gfjwjm_fs()->is_plan( 'professional' ) ) {
			$premium_custom_files_text = ' or custom XML/JSON files, ';
		}

		echo html( 'p', sprintf( __( 'From this page you can import jobs from any valid RSS job feed%s to your jobs database.', 'gofetch-wpjm' ), $premium_custom_files_text ) );
		echo html( 'div class="secondary-container provider-credits-info"', html( 'span class="dashicons-before dashicons-megaphone" style="padding-right: 8px;"', '' ) . html( 'div', __( 'Please note, all imported jobs belong to the respective providers. Always make sure they are properly credited.', 'gofetch-wpjm' ) ) );

		if ( ! gfjwjm_fs()->can_use_premium_code() ) {
			echo scb_bc_admin_notice( sprintf( html( 'span class="dashicons dashicons-warning" style=""', '&nbsp;' ) . ' ' . __( 'If you need further features like more job providers, a custom feed builder, featuring imported jobs, smart categories assign, schedule imports, and more, please upgrade to a <a href="%1$s">premium plan</a>.', 'gofetch-wpjm' ), esc_url( gfjwjm_fs()->get_upgrade_url() ) ) );
		}

		$templates = GoFetch_Helper::get_sanitized_templates();

		if ( gfjwjm_fs()->is_plan( 'professional' ) ) {
			$file_upload_switch = html( 'p class="feed-type-switch feed-type-file"', html( 'a class="feed-type-toggle"', __( 'Want to load a local file instead? Click here.' ) ) );

			$feed_load_switch = html( 'p class="feed-type-switch feed-type-url"', html( 'a class="feed-type-toggle"', __( 'Want to load an URL instead? Click here.' ) ) );

			// @todo: add link to upgrade page

			$feed_field_title = __( 'RSS/XML/JSON URL', 'gofetch-wpjm' );
			$feed_field_ph    = __( 'Type or paste your RSS feed or XML/JSON URL here (e.g: https://jobs.wordpress.net/feed/?s=developer)', 'gofetch-wpjm' );
			$feed_field_tip   = __( 'An RSS feed, XML or JSON URL, with job listings that you wish to import to your site. In case of an RSS feed, make sure you use a targeted feed instead of a generic one.', 'gofetch-wpjm' );
		} else {
			$feed_field_title = __( 'RSS Feed URL', 'gofetch-wpjm' );
			$feed_field_ph    = __( 'Type or paste your RSS feed here (e.g: https://jobs.wordpress.net/feed/?s=developer)', 'gofetch-wpjm' );
			$feed_field_tip   = __( 'An RSS feed, with job listings that you wish to import to your site. Make sure you use a targeted feed instead of a generic one.', 'gofetch-wpjm' );

			$file_upload_switch_upsell = html( 'div class="secondary-container custom-files-info"', '<span class="dashicons-before dashicons-megaphone"></span>' .
								   html( 'div', sprintf( 'Looking to load custom local/remote XML/JSON files? Upgrade to a <a href="%1$s">Professional</a> or <a href="%1$s">Business</a> plan.', gfjwjm_fs()->get_upgrade_url() ) ) );
		}

		$feed_setup = array(
			array(
				'title'         => '',
				'name'          => '_blank',
				'type'          => 'custom',
				'section_break' => true,
				'render'        => array( $this, 'section_break' ),
			),
			array(
				'title' => __( 'Saved Templates', 'gofetch-wpjm' ),
				'type'  => 'select',
				'name'  => 'templates_list',
				'extra' => array(
					'id' => 'templates_list',
				),
				'choices'  => array_keys( $templates ),
				'selected' => '',
				'desc'     => html( 'input', array( 'type' => 'submit', 'name' => 'refresh_templates', 'class' => 'refresh-templates button-secondary', 'value' => __( 'Refresh', 'gofetch-wpjm' ) ) ) . ' ' .
							  html( 'input', array( 'type' => 'submit', 'name' => 'delete_template', 'class' => 'button-secondary', 'disabled' => true, 'value' => __( 'Delete', 'gofetch-wpjm' ) ) ),
				'text'     => __( 'Choose a Template . . .', 'gofetch-wpjm' ),
				'tip'      => __( 'The list of all your saved import templates. Choosing an existing template, automatically loads all the related configuration.', 'gofetch-wpjm' ),
				'tr'       => 'tr-templates',
			),
			array(
				'title'  => __( 'Job Providers', 'gofetch-wpjm' ),
				'type'   => 'custom',
				'name'   => '_blank',
				'tip'    => __( 'A list with some of the most popular job sites that offer jobs via RSS feeds or through an API. Click a provider to view more details and instructions on how to use the RSS feed.', 'gofetch-wpjm' ) .
								( ! gfjwjm_fs()->can_use_premium_code() ? html( 'p', html( 'code', html( 'span class="dashicons dashicons-warning"', '&nbsp;' ) .  ' ' . __( 'Premium plans include a bigger list and an RSS feed builder for select providers.' ) ) ) :
 								'<br/><br/>' . sprintf( __( 'Missing any of you favorites RSS job feed providers? Submit your providers suggestions using the official site <a href="%s" target="_blank" rel="nofollow">contact form</a>. The most popular requests will be added on future releases.', 'gofetch-wpjm' ), esc_url( 'gofetchjobs.com/contact' ) )
							),
				'render' => array( $this, 'provider_helper_dropdown' ),
				'tr'     => 'tr-providers',
			),
			array(
				'title'  => '',
				'name'   => '_blank',
				'type'   => 'custom',
				'render' => array( $this, 'section_providers_placeholder' ),
				'tr'     => 'tr-hide',
			),
			array(
				'title' => $feed_field_title,
				'type'  => 'text',

				'name'  => 'rss_feed_import',
				'extra' => array(
					'class'        => 'regular-text2',
					'placeholder'  => $feed_field_ph,
					'data-example' => 'https://jobs.wordpress.net/feed/?s=developer',
				),
				'tip'   => $feed_field_tip .
							  __( '<br/><br/><code>GOOD - http://jobs.wordpress.net/feed/?s=developer (filtered results)</code> <br/><code>BAD - http://jobs.wordpress.net/feed (bulk results)</code>', 'gofetch-wpjm' ) .
							  __( '<br/><br/>If you prefer to use a generic feed, after loading it, you can specify some keywords to match against the jobs being imported.', 'gofetch-wpjm' ) .
							  ( ! gfjwjm_fs()->can_use_premium_code() ? html( 'p', html( 'code', html( 'span class="dashicons dashicons-warning"', '&nbsp;' ) .  ' ' . __( 'Keyword filtering is only available in Premium plans.' ) ) ) : '' ),
				'desc'  => html( 'input', array( 'type' => 'submit', 'name' => 'import_feed', 'class' => 'import-feed button-primary', 'value' => __( 'Load', 'gofetch-wpjm' ) ) ) .

							$file_upload_switch .

							'<br/>' . html( 'div class="secondary-container"', '<span class="dashicons-before dashicons-info"></span>' .
							html( 'div', sprintf( 'Looking for RSS feeds? Instantly generate RSS feeds from any URL at <a href="%1$s" rel="nofollow noopener noreferrer">%1$s</a>', 'https://rss.app/' ) .
							'<br/>' . sprintf( __( 'Having trouble with an RSS feed? Check if it is valid, using the <a href="%s" rel="nofollow noopener noreferrer">W3C online validator</a>.', 'gofetch-wpjm' ), 'https://validator.w3.org/feed/' ) ) ) .

							$file_upload_switch_upsell,

				'value' => ( ! empty( $_POST['rss_feed_import'] ) ? sanitize_text_field( $_POST['rss_feed_import'] ): '' ),
				'tr'    => 'tr-rss-url tr-rss-toggle',
			),
			array(
				'title' => __( 'RSS/XML/JSON FILE', 'gofetch-wpjm' ),
				'type'  => 'file',

				'name'  => 'import_local_feed',
				'extra' => array(
					'id'    => 'import_local_file',
					'class' => 'regular-text3',
				),
				'tip'   => __( 'An RSS feed, XML or JSON file, with job listings that you wish to import to your site.', 'gofetch-wpjm' ),
				'desc'  => html( 'input', array( 'type' => 'submit', 'name' => 'import_feed', 'class' => 'import-feed button-primary', 'value' => __( 'Load', 'gofetch-wpjm' ) ) ) .

							$feed_load_switch .

							'<br/>' . html( 'div class="secondary-container"', '<span class="dashicons-before dashicons-info"></span>' .
							html( 'div', __( 'Please note that files cannot be saved as templates.', 'gofetch-wpjm' ) ) ),

				'value' => ( ! empty( $_POST['import_local_feed'] ) ? sanitize_text_field( $_POST['import_local_feed'] ) : '' ),
				'tr'    => 'tr-rss-file tr-rss-toggle',
			),
			array(
				'title'  => '',
				'name'   => '_blank',
				'type'   => 'custom',
				'render' => array( $this, 'placeholder' ),
			),
			array(
				'title'  => __( 'Content Sample', 'gofetch-wpjm' ),
				'name'   => '_blank',
				'type'   => 'custom',
				'tip'    => __( 'Make sure you have at least one "regular" job submitted to have the custom fields automatically populated.', 'gofetch-wpjm' ),
				'render' => array( $this, 'table_fields_title' ),
				'tr'     => 'temp-tr-hide tr-sample',
			),
			array(
				'title'  => '',
				'name'   => '_blank',
				'type'   => 'custom',
				'render' => array( $this, 'output_sample_table' ),
				'tr'     => 'temp-tr-hide',
			)
		);
		$feed_setup = apply_filters( 'goft_wpjm_settings', $feed_setup, 'feed_setup' );

		$provider_details = array(
			array(
				'title'         => '',
				'name'          => '_blank',
				'type'          => 'custom',
				'section_break' => true,
				'render'        => array( $this, 'section_break' ),
				'tr'            => 'temp-tr-hide tr-provider-details',
			),
			array(
				'title' => __( 'Name', 'gofetch-wpjm' ),
				'type'  => 'text',
				'name'  => 'source[name]',
				'extra' => array(
					'class'       => 'regular-text',
					'placeholder' => 'e.g: Monster Jobs',
					'section'     => 'source',
				),
				'tip'   => __( 'The feed source name (e.g: Monster Jobs).', 'gofetch-wpjm' ),
				'value' => ( ! empty( $_POST['source[name]'] ) ? sanitize_text_field( $_POST['source[name]'] ) : '' ),
				'desc'  => html( 'span', array( 'class' => "wp-ui-text-highlight reset-val", 'data-parent' => 'source[name]', 'title' => esc_attr( __( 'Revert to default value.', 'gofetch-wpjm' ) ) ), html( 'span', array( 'class' => "dashicons dashicons-image-rotate" ) ) ),
				'tr'    => 'tr-hide',
			),
			array(
				'title' => __( 'Website', 'gofetch-wpjm' ),
				'type'  => 'text',
				'name'  => 'source[website]',
				'extra' => array(
					'class'       => 'regular-text',
					'placeholder' => 'e.g: www.monster.com',
					'section'     => 'source',
				),
				'tip'   => __( 'The feed source URL (e.g: www.monster.com).', 'gofetch-wpjm' ),
				'value' => ( ! empty( $_POST['source[url]'] ) ? sanitize_url( $_POST['source[url]'] ) : '' ),
				'desc'  => html( 'span', array( 'class' => "wp-ui-text-highlight reset-val", 'data-parent' => 'source[website]', 'title' => esc_attr( __( 'Revert to default value.', 'gofetch-wpjm' ) ) ), html( 'span', array( 'class' => "dashicons dashicons-image-rotate" ) ) ),
				'tr'    => 'tr-hide',
			),
			array(
				'title'  => __( 'Logo', 'gofetch-wpjm' ),
				'name'   => 'source[logo]',
				'type'   => 'custom',
				'tip'    => __( "Specify an image URL here to display the jobs source site logo instead of only the site name. It is recommend that you use a local image so you can resize it accordingly.", 'gofetch-wpjm' ),
				'render' => array( $this, 'provider_logo_uploader' ),
				'tr'     => 'tr-hide',
			),
		);
		$provider_details = apply_filters( 'goft_wpjm_settings', $provider_details, 'provider_details' );

		if ( gfjwjm_fs()->can_use_premium_code() ) {

			$monetize[] = array(
				'title'         => '',
				'name'          => '_blank',
				'type'          => 'custom',
				'section_break' => true,
				'render'        => array( $this, 'section_break' ),
				'tr'            => 'temp-tr-hide tr-advanced',
			);

			$monetize = apply_filters( 'goft_wpjm_settings', $monetize, 'monetize' );

		} else {

			$monetize = array();
		}

		$jobs_setup = array(
			array(
				'title'         => '',
				'name'          => '_blank',
				'type'          => 'custom',
				'section_break' => true,
				'render'        => array( $this, 'section_break' ),
				'tr'            => 'temp-tr-hide',
			),
			array(
				'title'         => '',
				'name'          => '_blank',
				'type'          => 'custom',
				'section_break' => true,
				'render'        => array( $this, 'section_break' ),
				'section'       => 'taxonomies',
				'tr'            => 'temp-tr-hide tr-taxonomies',
			),
			array(
				'title'         => '',
				'name'          => '_blank',
				'type'          => 'custom',
				'section_break' => true,
				'render'        => array( $this, 'section_break' ),
				'section'       => 'meta',
				'tr'            => 'temp-tr-hide tr-meta tr-advanced',
			),
			array(
				'title'         => '',
				'name'          => '_blank',
				'type'          => 'custom',
				'section_break' => true,
				'render'        => array( $this, 'section_break' ),
				'tr'            => 'temp-tr-hide',
			),
			array(
				'title'  => __( 'Name', 'gofetch-wpjm' ),
				'name'   => 'job_author',
				'type'   => 'custom',
				'render' => array( $this, 'output_job_listers' ),
				'tip'    => __( 'Choose the user to be assigned to the imported jobs. This user will only be assigned to the jobs your are currently importing. It is not saved in templates.', 'gofetch-wpjm' ),
				'tr'     => 'temp-tr-hide tr-job-lister',
			),
		);
		$jobs_setup = apply_filters( 'goft_wpjm_settings', $jobs_setup, 'jobs_setup' );

		$filter = array(
			array(
				'title'         => '',
				'name'          => '_blank',
				'type'          => 'custom',
				'section_break' => true,
				'render'        => array( $this, 'section_break' ),
				'tr'            => 'temp-tr-hide tr-filter tr-advanced',
			),
			array(
				'title' => __( 'Limit', 'gofetch-wpjm' ),
				'name'  => 'limit',
				'type'  => 'text',
				'extra' => array(
					'class'     => 'small-text',
					'maxlength' => 5,
				),
				'tip'     => __( 'Choose the number of jobs to import (leave empty to import all the jobs in the feed - not recommended on large RSS feeds).', 'gofetch-wpjm' ),
				'value'   => 	! empty( $_POST['limit'] ) ? sanitize_text_field( $_POST['limit'] ) : '',
				'tr'      => 'temp-tr-hide tr-limit tr-advanced',
				'default' => 50,
			),
		);
		$filter = apply_filters( 'goft_wpjm_settings', $filter, 'filter' );

		$save = array(
			array(
				'title'         => '',
				'name'          => '_blank',
				'type'          => 'custom',
				'section_break' => true,
				'render'        => array( $this, 'section_break' ),
				'tr'            => 'temp-tr-hide tr-save',
			),
			array(
				'title' => __( 'Replace Previous Jobs?', 'gofetch-wpjm' ),
				'type'  => 'checkbox',
				'name'  => 'replace_jobs',
				'desc'  => 'Replace all previously imported jobs from this feed?',
				'value'  => 'yes',
				'tr'    => 'temp-tr-hide tr-replace-jobs',
				'tip'   => __( 'Check this option to replace any previously imported jobs from this exact feed URL, with the new ones. Any previously imported jobs from this feed, will be deleted.', 'gofetch-wpjm' ) . '<br/><br/>' .
							__( 'Note: The import will be slower, since it will need to delete any previous jobs first.', 'gofetch-wpjm' ) . '<br/>'
			),
			array(
				'title' => __( 'Template', 'gofetch-wpjm' ),
				'type'  => 'text',
				'name'  => 'template_name',
				'extra' => array(
					'style' => 'width: 312px',
					'class' => 'field_dependent',
				),
				'desc' => html( 'a', array( 'class' => 'save-template button button-primary field_dependent' ), __( 'Save', 'gofetch-wpjm' ) ),
				'tip'  => __( 'Specify a template name and and save your import setup. Templates can be loaded later and can also be used on scheduled imports.', 'gofetch-wpjm' )
								. '<br/><br/>' . __( 'Some template name examples: <em>my fulltime jobs</em>, <em>my tech jobs</em>, <em>my big salary jobs</em>, etc.', 'gofetch-wpjm' ),
				'value'  => ! empty( $_POST['template_name'] ) ? sanitize_text_field( $_POST['template_name'] ) : 'my-rss-feed',
				'tr'     => 'temp-tr-hide tr-template-name',
			),
		);

		if ( gfjwjm_fs()->can_use_premium_code() ) {

			$field = array(
				'title' => '',
				'type'  => 'select',
				'name'  => 'auto_schedule_recurrence',
				'choices' => apply_filters( 'goft_wpjm_recurrence_options', array(
					'hourly'      => __( 'Hourly', 'gofetch-wpjm' ),
					'twice_daily' => __( 'Every 12 Hours', 'gofetch-wpjm' ),
					'daily'       => __( 'Daily', 'gofetch-wpjm' ),
					'weekly'      => __( 'Weekly', 'gofetch-wpjm' ),
					'monthly'     => __( 'Monthly', 'gofetch-wpjm' ),
				)),
				'extra' => array(
					'class' => 'schedule-options schedule-recurrence auto-schedule-toggle',
				),
				'tip' => __( 'How often should the schedule run?', 'gofetch-wpjm' ),
				'tr'     => 'temp-tr-hide tr-schedule-name',
				'default' => 'daily',
			);

			$additional_fields = scbBcForms::input( $field, array() );

			$additional_fields .= html( 'input', array(
				'type'        => 'text',
				'name'        => 'auto_schedule_name',
				'class'       => 'regular-text schedule-options schedule-name auto-schedule-toggle',
				'placeholder' => 'Schedule Name (e.g: Design Jobs in London)',
			));

			$additional_fields .= html( 'span class="auto-schedule-toggle"', '<br/><br/>' . sprintf( __( 'The schedule will be created with default settings. You can further tweak it, by editing the new schedule on the <a href="%s">Schedules</a> page.', 'gofetch-wpjm' ), admin_url( 'admin.php?post_type=' . GoFetch_Jobs()->post_type ) ) );

			$save[] = array(
				'title' => __( 'Create Schedule?', 'gofetch-wpjm' ),
				'type'  => 'select',
				'name'  => 'auto_schedule',
				'choices' => apply_filters( 'goft_wpjm_recurrence_options', array(
					'no'  => __( 'No', 'gofetch-wpjm' ),
					'yes' => __( 'Yes', 'gofetch-wpjm' ),
				)),
				'extra' => array(
					'class' => 'auto-schedule schedule-options',
				),
				'tip' => __( 'Automatically create a schedule for this template, during the import process.', 'gofetch-wpjm' ),
				'desc' => $additional_fields,
				'tr'     => 'temp-tr-hide tr-toggle-hide tr-auto-schedule',
			);

		}

		$save[] = array(
			'title'         => '',
			'name'          => '_blank',
			'type'          => 'custom',
			'section_break' => true,
			'render'        => array( $this, 'section_break' ),
			'tr'            => 'temp-tr-hide',
		);

		$save = apply_filters( 'goft_wpjm_settings', $save, 'save' );

		$fields = array_merge( $feed_setup, $provider_details, $monetize, $jobs_setup, $filter, $save );
		$fields = apply_filters( 'goft_wpjm_settings', $fields );

		$taxonomies = apply_filters( 'goft_wpjm_settings_taxonomies', $this->page_content_taxonomies() );
		$tax_pos    = BC_Framework_Utils::list_find_pos( $fields, array( 'section' => 'taxonomies' ) ) + 1;

		$fields = array_merge( array_slice( $fields, 0, $tax_pos, true ), $taxonomies, array_slice( $fields, $tax_pos, count( $fields ) - 1, true ) );

		$meta_fields = apply_filters( 'goft_wpjm_settings_meta_fields', $this->page_content_meta_fields() );

		$meta_fields_options = apply_filters( 'goft_wpjm_settings_meta_fields_options', $this->page_content_meta_fields_options() );
		$meta_fields         = array_merge( $meta_fields, (array) $meta_fields_options );

		$meta_pos = BC_Framework_Utils::list_find_pos( $fields, array( 'section' => 'meta' ) ) + 1;

		$fields = array_merge( array_slice( $fields, 0, $meta_pos, true ), $meta_fields, array_slice( $fields, $meta_pos, count( $fields ) - 1, true ) );

		echo wp_kses_post( $this->form_table( $fields ) );
	}

	protected function feed_specific_options() {
		return;
	}

	/**
	 * Retrieves the main taxonomies related with the the 'job_listing' post type.
	 */
	protected function page_content_taxonomies() {

		$taxonomies = get_object_taxonomies( GoFetch_Jobs()->parent_post_type, 'objects' );
		$taxonomies = apply_filters( 'goft_wpjm_taxonomies_objects', $taxonomies );

		$fields = array();

		// Provide compatibility for objects in anonymous functions (PHP < 5.3).
		$o_this = $this;

		foreach ( $taxonomies as $taxonomy ) {

			if ( version_compare( PHP_VERSION, '5.3.0' ) >= 0 ) {

				$render = function() use ( $o_this, $taxonomy ) {
					return $o_this->output_taxonomy( $taxonomy );
				};

			} else {

				// For older PHP versions (< 5.3.0) keep a list of the used taxonomies.
				self::$taxonomy_compat[] = $taxonomy;
				$render = array( $this, 'output_taxonomy' );

			}

			$fields[] = array(
				'title'  => $taxonomy->label,
				'name'   => '_blank',
				'type'   => 'custom',
				'render' => $render,
				'tip'    => sprintf( __( 'The %s for the jobs being imported.', 'gofetch-wpjm' ), $taxonomy->label ),
				'tr'     => 'tr-hide',
			);

		}
		return $fields;
	}

	/**
	 * Retrieves all the meta fields available for the 'job_listing' post type.
	 */
	protected function page_content_meta_fields() {

		$fields = apply_filters( 'goft_wpjm_meta_fields', array() );

		$defaults = array(
			'title' => '',
			'name'  => '',
			'type'  => 'text',
			'extra' => array(
				'section' => 'meta',
				'default' => '',
			),
			'tr' => 'tr-hide',
		);

		$final_fields = array();

		foreach ( $fields as $field ) {

			if ( ! empty( $field['type'] ) && 'checkbox' === $field['type'] ) {
				$defaults['extra']['class'] = '';
			} else {
				$defaults['extra']['class'] = 'regular-text';
			}

			$new_field = wp_parse_args( $field, $defaults );
			if ( ! empty( $field['extra'] ) ) {
				$new_field['extra'] = wp_parse_args( $field['extra'], $defaults['extra'] );
			}

			// Automatically set the default value.
			if ( empty( $new_field['extra']['default'] ) ) {
				preg_match_all( "/\[([^\]]*)\]/", $field['name'], $field_name );
				if ( ! empty( $field_name[1][0] ) ) {
					$new_field['extra']['default'] = $this->get_default_value_for_meta( $field_name[1][0] );
				}
			}

			$final_fields[] = $new_field;
		}

		return $final_fields;
	}

	/**
	 * Retrieves all the meta fields available for the 'job_listing' post type.
	 */
	protected function page_content_meta_fields_options() {
		return array();
	}

	/**
	 *
	 */
	public function provider_helper_dropdown() {

		$atts =	array(
			'name' => 'providers_list',
			'id'   => 'providers_list',
		);
		return GoFetch_RSS_Providers::output_providers_dropdown( $atts );
	}

	/**
	 * Renders the provider logo uploader field.
	 */
	public function provider_logo_uploader() {

		$img = '';

		if ( ! empty( $_POST['source[logo]'] ) ) {
			$img = sanitize_url( $_POST['source[logo]'] );
		}

		$field = array(
			'name'  => 'source[logo]',
			'type'  => 'text',
			'extra' => array(
				'class'              => 'goft-source-logo goft-image regular-text',
				'placeholder'        => 'e.g: www.my-jobs-site/uploads/monster-logo.png',
				'section'            => 'source',
				'data-image-id-name' => 'source[image_id]',
			),
			'tip'   => __( "Specify an image URL here to display the jobs source site logo instead of only the site name. It's recommend that you use a local image so you can resize it accordingly.", 'gofetch-wpjm' ),
			'value' => $img,
			'desc'  => html( 'span', array( 'class' => "wp-ui-text-highlight reset-val", 'data-parent' => 'source[logo]', 'title' => esc_attr( __( 'Revert to default value.', 'gofetch-wpjm' ) ) ), html( 'span', array( 'class' => "dashicons dashicons-image-rotate" ) ) ) .
					   html( 'a', array( 'class' => 'goft-source-logo goft-upload button-secondary' ), __( 'Browse...', 'gofetch-wpjm' ) ),
		);

		return $this->image_uploader( $field, 'goft-source-logo' );
	}

	/**
	 * Outputs an image uploader field;
	 */
	public function image_uploader( $field, $class ) {

		if ( function_exists( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		} else {
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_script( 'media-upload' );
			wp_enqueue_script( 'thickbox' );
		}

		ob_start();

		echo html( 'img', array( 'class' => esc_attr( $class ), 'src' => '' ) );

		echo wp_kses_post( $this->input( $field ) );

		$output = ob_get_clean();

		ob_start();
?>
		jQuery(document).ready(function($) {

			$( ".goft-upload.<?php echo esc_attr( $class ); ?>" ).on( 'click', function(e) {
				e.preventDefault();

				var custom_uploader = wp.media({
					title: "<?php echo __( 'Custom Image' , 'gofetch-wpjm' ); ?>",
					button: {
						text: "<?php echo __( 'Upload Image' , 'gofetch-wpjm' ); ?>"
					},
					multiple: false  // Set this to true to allow multiple files to be selected
				})
				.on('select', function() {
					var attachment = custom_uploader.state().get('selection').first().toJSON();
					$( "input[name='<?php echo esc_attr( $field['name'] ); ?>']" ).val( attachment.url ).trigger('change');
					<?php if ( ! empty( $field['extra']['data-image-id-name'] ) ): ?>
						$( "input[name='<?php echo esc_attr( $field['extra']['data-image-id-name'] ); ?>']" ).val( attachment.id );
					<?php endif; ?>
				})
				.open();
			});

			$( "input[name='<?php echo esc_attr( $field['name'] ); ?>']" ).on( 'change', function(e) {

					$( "img.<?php echo esc_attr( $class ); ?>" ).attr( 'src', $(this).val() );

					if ( $(this).val() ) {
						$( "img.<?php echo esc_attr( $class ); ?>" ).show();
					} else {
						$( "img.<?php echo esc_attr( $class ); ?>" ).hide();
					}

			});

			$( "input[name='<?php echo esc_attr( $field['name'] ); ?>']" ).change();
		});
<?php
		$inline_js = ob_get_clean();

		wp_add_inline_script( 'goft_wpjm', $inline_js );

		return $output;
	}

	/**
	 * Retrieves the key/value mapping for the geolocation meta keys and the geocomplete 'data-geo' helper fields.
	 */
	public static function get_geocomplete_hidden_fields() {
		return apply_filters( 'goft_wpjm_geocomplete_hidden_fields', array() );
	}

	/**
	 * Output hidden gecocomplete meta fields.
	 */
	public static function get_geocomplete_fields() {

		$hidden_fields = '';

		foreach ( self::get_geocomplete_hidden_fields() as $att => $location_att ) {
			$meta_key = "meta[{$att}]";
			$hidden_fields .= html( 'input', array( 'type' => 'hidden', 'name' => esc_attr( $meta_key ), 'data-geo' => esc_attr( $location_att ), 'value' => '' ) );
		}

		return html( 'div', array( 'class' => 'custom-location' ), $hidden_fields );
	}

	/**
	 * Undocumented function
	 *
	 * @return void
	 */
	public static function get_core_mapped_field( $core_field, $mappings ) {

		if ( empty( $mappings ) ) {
			return false;
		}

		$flip_fields = array_flip( $mappings );

		if ( isset( $flip_fields[ $core_field ] ) ) {
			return $flip_fields[ $core_field ];
		}
		return false;
	}

	/**
	 * Outputs the placeholder that the user sees before the RSS feed is loaded.
	 */
	public function placeholder() {
		return '';
	}

	/**
	 * Outputs the sample fields table title.
	 */
	public function table_fields_title() {
		return html( 'p', __( 'On the table below you can see a sample of the loaded file. You can map the tags found with all the available custom fields and taxonomies.', 'gofetch-wpjm' ) ) .
			   html( 'p', __( 'The plugin will try to set default mappings but you should always confirm each field mapping before import.', 'gofetch-wpjm' ) );
	}

	// _Sections.

	/**
	 * The sections titles.
	 */
	public function section_title( $section ) {

		$title_desc = array();

		$i = 1;

		$title_desc[ $i++ ] = array(
			'title' => '<span class="dashicons dashicons-rss title-icon"></span>' . __( 'Feed Setup', 'gofetch-wpjm' ),
			'desc'  => html( 'p', __( 'Select a feed provider from the <em>Providers</em> dropdown or just paste a known feed URL on the <em>Feed URL</em> field. It is recommended to import targeted feeds (e.g: for a specific job category, location, etc), or filtered by some criteria instead of a big generic feed, for better control of the jobs being imported.', 'gofetch-wpjm' ) ),
			'header' => 'h2',
		);

		$title_desc[ $i++ ] = array(
			'title'   => '<span class="dashicons dashicons-id-alt title-icon"></span>'. __( 'Provider Details', 'gofetch-wpjm' ),
			'desc'    => __( "Provide some information about the current jobs feed provider. This information will help identify each job source and will be displayed on each job page. Leave empty if you don't want to show the jobs source (not recommended!).", 'gofetch-wpjm' ),
			'tip'     => __( "It is highly recommended that you show the original job site attribution. Failing to do so might infringe Copywrite. The original jobs source should always be given the proper attribution.", 'gofetch-wpjm' ),			'header'  => 'h2',
			'section' => 'source',
		);

		if ( gfjwjm_fs()->can_use_premium_code() ) {

			$title_desc[ $i++ ] = array(
					'title'   => '<span class="title-icon icon dashicons dashicons-admin-links"></span>' . __( 'Affiliate Parameters', 'gofetch-wpjm' ),
					'desc'    => __( 'Affiliate parameters or any other parameters to use with the jobs provider external links. These parameters can be used with some providers to monetize on external job clicks or with 3rd party plugins that can monitor clicks.', 'gofetch-wpjm' ),
					'header'  => 'h2',
					'section' => 'monetize',
			);

		}

		$title_desc[ $i++ ] = array(
			'title'  => '<span class="icon icon-goft-briefcase title-icon"></span>' . __( 'Jobs Setup', 'gofetch-wpjm' ),
			'desc'   => __( 'Fill in additional details like job categories, job types, job duration, etc, for the jobs you are importing. These details will be added to each imported job.', 'gofetch-wpjm' ),
			'header' => 'h2',
		);

		$title_desc[ $i++ ] = array(
			'title'   => '<span class="dashicons dashicons-tag title-icon"></span>' . __( 'Terms', 'gofetch-wpjm' ),
			'desc'    => __( 'The terms that best fit the content you are importing or configuring. Click "Edit..." to specify terms yourself, otherwise jobs will use default terms.', 'gofetch-wpjm' ),
			'tip'     => __( "To make sure jobs are imported with relevant terms it's recommended that you import a relevant feed for each of your job types and job categories (e.g: import a feed containing 'Full-Time', 'Marketing' jobs). "
						 . 'Otherwise, any taxonomy terms you choose below will be blindly assigned to all imported jobs.', 'gofetch-wpjm' ) .
						'<br/><br/>' . __( "Alternatively, you can enable <em>Smart Assign</em> to let the import process scan and automatically assign terms using term marching on each job being imported. "
						 . 'If the automatic process fails it will assign the terms you choose below.', 'gofetch-wpjm' ) .
						( ! gfjwjm_fs()->can_use_premium_code() ? html( 'p', html( 'code', html( 'span class="dashicons dashicons-warning"', '&nbsp;' ) .  ' ' . __( '<em>Smart Assign</em> is only available in Premium plans.' ) ) ) : '' ),
			'section' => 'taxonomies',
		);

		$title_desc[ $i++ ] = array(
			'title'   => '<span class="dashicons dashicons-index-card title-icon"></span>' . __( 'Details', 'gofetch-wpjm' ),
			'desc'    => __( 'The default values for the custom fields (details) of each job being imported. Click "Edit..." to assign values to custom fields, otherwise they will be left as is.', 'gofetch-wpjm' ),
			'tip'     => __( 'The custom fields values will only be assigned if not already provided by the feed. They will not override the original values. As an example, if a job being imported already contains the location or the job company ***, that information will be used instead of any values you add here.', 'gofetch-wpjm' ) .
				   ' ' . __( 'The plugin will also try to assign default values based on the feed parameters (if any).', 'gofetch-wpjm' ) .
		  '<br/><br/>' . __( '*** Although the import process does it\'s best to find the custom fields values it is not guaranteed since each provider outputs that information in their feeds differently.', 'gofetch-wpjm' ) . ' ' .
						 __( 'Check the sample table to see all the valid fields in the RSS feed.', 'gofetch-wpjm' ) .
						( ! gfjwjm_fs()->is__premium_only() ? html( 'p', html( 'code', html( 'span class="dashicons dashicons-warning"', '&nbsp;' ) . ' ' . __( 'Premium plans include the \'Featured\' meta field to feature imported jobs.', 'gofetch-wpjm' ) ) ) : '' ),
			'section' => 'meta',
		);

		$title_desc[ $i++ ] = array(
			'title' => '<span class="dashicons dashicons-admin-users title-icon"></span>' .__( 'Posted by', 'gofetch-wpjm' ),
			'desc'  => __( 'Choose the user to be assigned to the jobs being imported. Note that this option is not saved on the template and applies only for this import.', 'gofetch-wpjm' ) .
						html( 'div class="save-warning secondary-container save-warning-regular"', '<span class="dashicons dashicons-warning"></span>' . html( 'div', __( "This setting is not saved. It is applied to the current import only.", 'gofetch-wpjm' ) ) ),
		);

		if ( gfjwjm_fs()->can_use_premium_code() ) {
			$title_desc[ $i - 1 ]['desc'] .= html( 'div class="save-warning secondary-container save-warning-schedule"', '<span class="dashicons dashicons-warning"></span>' . html( 'div', __( "If you save this template and opt to automatically create a schedule, this setting will be saved on the created schedule.", 'gofetch-wpjm' ) ) );
		}

		$title_desc[ $i++ ] = array(
			'title' => '<span class="dashicons dashicons-filter title-icon"></span>' . __( 'Filter', 'gofetch-wpjm' ),
			'desc'  => __( 'Use the filters below to limit the items that will be imported. Note that filter options are not saved on the template and apply only for this import.', 'gofetch-wpjm' ) .
						html( 'div class="save-warning secondary-container save-warning-regular"', '<span class="dashicons dashicons-warning"></span>' . html( 'div', __( "This setting is not saved. It is applied to the current import only.", 'gofetch-wpjm' ) ) ),
			'tip'    => __( 'Filters allow you to further refine the jobs that should be imported.', 'gofetch-wpjm' ) . ( ! gfjwjm_fs()->is__premium_only() ? html( 'p', html( 'code', html( 'span class="dashicons dashicons-warning"', '&nbsp;' ) .  ' ' . __( 'Premium plans provides an additional filter to filter jobs by their feed date.' ) ) ) : '' ),
			'header' => 'h2',
		);

		if ( gfjwjm_fs()->can_use_premium_code() ) {
			$title_desc[ $i - 1 ]['desc'] .= html( 'div class="save-warning secondary-container save-warning-schedule"', '<span class="dashicons dashicons-warning"></span>' . html( 'div', __( "If you save this template and opt to automatically create a schedule, this setting will be saved on the created schedule.", 'gofetch-wpjm' ) ) );
		}

		$title_desc[ $i++ ] = array(
			'title' => '<span class="icon icon-goft-floppy-1 title-icon"></span>' . __( 'Save', 'gofetch-wpjm' ),
			'desc'  => __( "Save your current settings as a template to simplify future imports or to use later in scheduled imports.", 'gofetch-wpjm' ),
			'header' => 'h2',
		);

		if ( empty( $title_desc[ $section ] ) ) {
			return array();
		}

		$defaults = array(
			'title'       => '',
			'description' => '',
			'header'      => 'h4',
			'section'     => '',
		);

		return wp_parse_args( $title_desc[ $section ], $defaults );
	}

	/**
	 * Outputs a placeholder for the providers RSS setup instructions.
	 */
	public function section_providers_placeholder() {

		$placeholder = html( 'div', array( 'class' => 'providers-placeholder' ), html( 'div', array( 'class' => 'providers-placeholder-content' ), '&nbsp;' ) );

		return $placeholder;
	}

	/**
	 * Outputs section breaks.
	 */
	public function section_break( $section ) {
		static $section;

		if ( ! $section ) {
			$section = 1;
		} else {
			$section++;
		}

		$title_desc_html = '';

		$title_desc = $this->section_title( $section );

		$section_slug = $section;

		if ( ! empty( $title_desc['section'] ) ) {
			$section_slug = $title_desc['section'];
		}

		if ( ! empty( $title_desc['title'] ) ) {
			$title_desc_html .= html( $title_desc['header'], array( 'class' => esc_attr( "section-{$section_slug}" ) ), $title_desc['title'] );
		}

		if ( ! empty( $title_desc['desc'] ) ) {

			if ( ! empty( $title_desc['tip'] ) ) {

				$tip  = html( 'span', array(
					'class'        => 'dashicons-before dashicons-editor-help tip-icon bc-tip',
					'title'        => __( 'Click to read additional info...', 'gofetch-wpjm' ),
					'data-tooltip' => BC_Framework_ToolTips::supports_wp_pointer() ? $title_desc['tip'] : __( 'Click for more info', 'gofetch-wpjm' ),
				) );

				if ( ! BC_Framework_ToolTips::supports_wp_pointer() ) {
					$tip .= html( "div class='tip-content'", $title_desc['tip'] );
				}

				$title_desc['desc'] = $tip . ' ' . $title_desc['desc'];
			}

			$title_desc_html .= html( 'p', array( 'class' => esc_attr( "section-{$section_slug}" ), 'style' => "font-weight: normal" ), $title_desc['desc'] );
		}

		return '<div class="goft-section">' . wp_kses_post( $title_desc_html ) . '</div>';
	}


	// _Form handling.

	/**
	 * Adds country data for multi-country feeds.
	 */
	protected function country_meta_data( $params ) {
		$country = array();

		if ( ! empty( $params['feed-params-gofj-country-locale'] ) ) {
			$country_parts = explode( '_', $params['feed-params-gofj-country-locale'] );
			$country = array(
				'name' => $params['feed-params-gofj-country-name'],
				'code' => ! empty( $country_parts[1] ) ? $country_parts[1] : $country_parts[0]
			);
		} else if ( ! empty( $params['feed-params-gofj-country-code'] ) ) {
			$country = array(
				'name' => $params['feed-params-gofj-country-name'],
				'code' => $params['feed-params-gofj-country-code']
			);
		}
		if ( $country ) {
			$country = array(
				'_gofj_country_code' => strtoupper( $country['code'] ),
				'_gofj_country_name' => ucwords( $country['name'] )
			);
		}
		return $country;
	}

	/**
	 * The main handler that starts the import process and/or saves the user settings and/or deletes user templates.
	 */
	public function form_handler() {
		global $_wp_using_ext_object_cache, $goft_wpjm_options;

		if ( empty( $_POST['submit'] ) && empty( $_POST['action'] ) && empty( $_POST['delete_template'] ) ) {
			return false;
		}

		if ( ! empty( $_POST['delete_template'] ) ) {

			if ( ! empty( $_POST['templates_list'] ) ) {
				$this->delete_template( sanitize_text_field( $_POST['templates_list'] ) );
			} else {
				echo scb_bc_admin_notice( __( 'Please select a template to delete.', 'gofetch-wpjm' ), 'error' );
			}
			return;

		}

		// Only skip nonce check when saving a template since it already checks the nonce when calling the parent form handler.
		if ( empty( $_POST['save_template'] ) ) {
			check_admin_referer( $this->nonce );
		} else  {

			if ( empty( $_POST['template_name'] ) ) {
				echo scb_bc_admin_notice( __( 'Please name your template.', 'gofetch-wpjm' ), 'error' );
				return;
			}

		}

		// Save the template and settings if requested.
		if ( ! empty( $_POST['save_template'] ) ) {
			$this->save_template( sanitize_text_field( $_POST['template_name'] ) );
		}

		// Skip earlier if the import was not requested.
		if ( empty( $_POST['submit'] ) ) {
			return;
		}

		$defaults = array(
			'post_author'                 => 1,
			'field_mappings'              => array(),
			'tax_input'                   => array(),
			'smart_tax_input'             => '',
			'meta'                        => array(),
			'source'                      => array(),
			'from_date'                   => '',
			'to_date'                     => '',
			'limit'                       => '',
			'keywords'                    => '',
			'keywords_filtering'          => '',
			'keywords_comparison'         => 'OR',
			'keywords_exclude'            => '',
			'keywords_exclude_comparison' => 'OR',
			'rss_feed_import'             => '',
			'replace_jobs'                => '',
			'logos'                       => '',
			'special'                     => '',
			'content_type'                => 'RSS',
			'template_name'               => '',
			'auto_schedule'               => 'no',
			'auto_schedule_recurrence'    => '',
			'auto_schedule_name'          => '',
		);
		$params = wp_parse_args( wp_array_slice_assoc( wp_unslash( $_POST ), array_keys( $defaults ) ), $defaults );

		$params['meta'] = array_merge( $params['meta'], $this->country_meta_data( wp_unslash( $_POST ) ) );

		if ( ! empty( $params['template_name'] ) ) {
			$params['meta']['_goft_template_name'] = $params['template_name'];
		}

		// Temporarily turn off the object cache while we deal with transients since
		// some caching plugins like W3 Total Cache conflicts with our work.
		$_wp_using_ext_object_cache_previous = $_wp_using_ext_object_cache;
		$_wp_using_ext_object_cache = false;

		// Retrieve the cached RSS items imported earlier.

		// Get the cached RSS items chunks.
		$chunks = get_transient( '_goft-rss-feed-chunks' );
		$chunks = (int) $chunks ? $chunks : 1;

		$items = array();

		$skip_chunks = false;

		if ( ! empty( $chunks ) ) {

			// Iterate trough each cached RSS items chunk.
			for ( $i = 0; $i < $chunks ; $i++ ) {
				$chunk = get_transient( "_goft-rss-feed-{$i}" );

				if ( ! $chunk ) {
					$skip_chunks = true;
					break;
				}
				$items = array_merge( $items, (array) $chunk );
			}

			// Restore the caching values.
			$_wp_using_ext_object_cache = $_wp_using_ext_object_cache_previous;

		}

		// If we can't get the items previously stored in chunks for faster import try to fetch the feed items directly again now.
		if ( $skip_chunks ) {

			// __LOG.
			$vars = array(
				'context' => 'GOFT :: WARNING: COULD NOT GET CHUNKS FOR IMPORT! FETCHING ITEMS FROM FEED DIRECTLY!',
				'url'     => ! empty( $_POST['rss_feed_import'] ) ? wp_strip_all_tags( $_POST['rss_feed_import'] ): 'URL is EMPTY!',
				'params'  => $params,
			);
			BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
			//

			if ( ! empty( $_POST['rss_feed_import'] ) ) {
				$url = sanitize_text_field( $_POST['rss_feed_import'] );

				$results = GoFetch_Importer::import_feed( $url, $_POST, $cache = false );

				$items = $results['items'];
			}
		}

		if ( empty( $items ) ) {
			echo scb_bc_admin_notice( __( "Sorry, couldn't find anything to import.", 'gofetch-wpjm' ), 'error' );
			return;
		}

		$mappings_check = apply_filters( 'goft_wpjm_import_mappings_check', true, $params['field_mappings'], $items, $params['content_type'] );

		if ( is_wp_error( $mappings_check ) ) {
			echo scb_bc_admin_notice( __( "<strong>IMPORT ERROR</strong>", 'gofetch-wpjm' ) . '<br/>' . $mappings_check->get_error_message(), 'error' );
			return;
		}

		if ( empty( $_POST['save_template'] ) ) {
			add_action( 'admin_notices', array( $this, 'admin_msg' ) );
			add_action( 'admin_notices', array( $this, 'admin_msg_auto_schedule' ) );
		}

		$results = GoFetch_Importer::import( $items, $params );

		self::$imported = $results;

		// Create a schedule if the respective option is enabled. Allow overriding state based on import results.
		if ( 'yes' === apply_filters( 'goft_wpjm_auto_schedule', $params['auto_schedule'], $results ) ) {
			$schedule_meta = array(
				'_goft_wpjm_cron'      => $params['auto_schedule_recurrence'],
				'post_author_override' => $params['post_author'],
			);

			if ( ! empty( $params['keywords'] ) ) {
				$schedule_meta['_goft_wpjm_keywords_comparison'] = $params['keywords_comparison'];
				$schedule_meta['_goft_wpjm_keywords'] = $params['keywords'];
			}

			if ( ! empty( $params['keywords_exclude'] ) ) {
				$schedule_meta['_goft_wpjm_keywords_exclude_comparison'] = $params['keywords_exclude_comparison'];
				$schedule_meta['_goft_wpjm_keywords_exclude'] = $params['keywords_exclude'];
			}

			$new_schedule = GoFetch_Scheduler::create_schedule( $params['auto_schedule_name'], $params['template_name'], 'publish', $schedule_meta );

			if ( $new_schedule ) {
				self::$imported['new_schedule'] = sprintf( __( 'A new schedule called %s, was automatically created.', 'gofetch-wpjm' ), html( 'strong', $params['template_name'] ) );
			}
		}

		// Clear memory.
		$results = null;
	}

	/**
	 * Retrieves all the form data that will be stored in the template.
	 */
	private function handle_template_settings() {

		$defaults = array(
			'provider_id'     => '',
			'rss_feed_import' => '',
			'replace_jobs'    => '',
			'logos'           => 0,
			'special'         => '',
			'tax_input'       => '',
			'smart_tax_input' => '',
			'meta'            => '',
			'source'          => '',
			'mappings'        => '',
			'field_mappings'  => '',
			'region_domains'  => '',
			'locale_code'     => '',
			'co'              => '',
			'country'         => '',
		);
		$params = wp_parse_args( $_POST, $defaults );

		$params = array(
			'provider_id'     => stripslashes_deep( $params['provider_id'] ),
			'rss_feed_import' => filter_var( strip_tags( $params['rss_feed_import'] ) ),
			'replace_jobs'    => stripslashes_deep( $params['replace_jobs'] ),
			'logos'           => (int) $params['logos'],
			'special'         => stripslashes_deep( $params['special'] ),
			'tax_input'       => stripslashes_deep( $params['tax_input'] ),
			'smart_tax_input' => stripslashes_deep( $params['smart_tax_input'] ),
			'meta'            => stripslashes_deep( $params['meta'] ),
			'source'          => stripslashes_deep( $params['source'] ),
			'mappings'        => stripslashes_deep( $params['mappings'] ),
			'field_mappings'  => stripslashes_deep( $params['field_mappings'] ),
			'region_domains'  => stripslashes_deep( $params['region_domains'] ),
			'locale_code'     => stripslashes_deep( $params['locale_code'] ),
			'co'              => stripslashes_deep( $params['co'] ),
			'country'         => stripslashes_deep( $params['country'] ),
		);
		return $params;
	}

	/**
	 * Save the user settings.
	 */
	private function save_template( $template_name ) {
		global $goft_wpjm_options;

		$result = parent::form_handler();

		if ( ! $result ) {
			die( 0 );
		}

		$templates = GoFetch_Helper::get_sanitized_templates();

		// Get the user params for the current template.
		$params = $this->handle_template_settings();

		$templates[ $template_name ] = $params;

		$goft_wpjm_options->templates = $templates;
	}

	/**
	 * Saves the user settings in a template using AJAX:
	 */
	public function ajax_save_template() {
		$this->save_template( sanitize_text_field( $_POST['template'] ) );
		echo 1;
		die( 1 );
	}

	/**
	 * Deletes an existing template.
	 */
	private function delete_template( $name ) {
		global $goft_wpjm_options;

		$name      = GoFetch_Helper::remove_slashes( $name );
		$templates = GoFetch_Helper::get_sanitized_templates();

		if ( empty( $templates[ $name ] ) ) {
			echo scb_bc_admin_notice( __( 'Could not delete template. Template name not found.', 'gofetch-wpjm' ) );
			return;
		}

		// Clear memory.
		$templates[ $name ] = null; unset( $templates[ $name ] );

		$goft_wpjm_options->templates = $templates;

		echo scb_bc_admin_notice( __( 'The template was deleted.', 'gofetch-wpjm' ) );
	}

	/**
	 * Retrieve terms key/value pairs given a taxonomy.
	 */
	private function _get_terms_key_value_pairs( $taxonomy ) {
		$terms = get_terms( $taxonomy, array( 'hide_empty' => false ) );

		$terms_kvp[''] = __( 'None', 'gofetch-wpjm' );

		if ( ! is_wp_error( $terms ) ) {
			foreach( $terms as $term ) {
				$terms_kvp[ $term->slug ] = $term->name;
			}
		}
		return apply_filters( 'goft_wpjm_get_terms_key_value_pairs', $terms_kvp, $taxonomy );
	}

	/**
	 * Retrieve default values for a given meta field.
	 */
	protected function get_default_value_for_meta( $field ) {

		if ( ! empty( $_POST['meta'][ $field ] ) ) {

			if ( ! empty( $field['type'] ) && 'textarea' !== $field['type'] ) {
				return wp_strip_all_tags( $_POST['meta'][ $field ] );
			}
			return stripslashes( sanitize_text_field( $_POST['meta'][ $field ] ) );
		}

		$value = '';

		return apply_filters( 'goft_wpjm_default_value_for_field', $value, $field );
	}

	/**
	 * Retrieve default values for a given taxonomy.
	 */
	protected function get_default_value_for_tax( $taxonomy ) {

		if ( ! empty( $_POST['tax_input'][ $taxonomy ] ) ) {
			return sanitize_text_field( $_POST['tax_input'][ $taxonomy ] );
		}

		$value = '';

		return apply_filters( 'goft_wpjm_default_value_for_taxonomy', $value, $taxonomy );
	}


	// Output callbacks for the 'render' property.

	/**
	 * Outputs the list of job listers.
	 */
	public function output_job_listers() {
		$job_listers_raw = apply_filters( 'goft_wpjm_job_listers', GoFetch_Admin_Settings::get_users() );

		$job_listers = array();

		foreach ( $job_listers_raw as $job_lister ) {
			$job_listers[ $job_lister->ID ] = $job_lister->display_name;
		}

		$type =  array(
			'title' => __( 'Job Lister', 'gofetch-wpjm' ),
			'type'  => 'select',
			'name'  => 'post_author',
			'extra' => array(
				'id' => 'job_lister',
				'class' => 'gofj-select2',
			),
			'choices'  => $job_listers,
			'selected' => get_current_user_id(),
		);

		if ( ! empty( $_POST['post_author'] ) ) {
			$type['selected'] = wp_strip_all_tags( $_POST['post_author'] );
		}

		$output = $this->input( $type );

		return $output;
	}

	/**
	 * Output the list of taxonomies.
	 */
	public function output_taxonomy( $taxonomy = '' ) {

		// For older PHP versions (< 5.3.0) output each taxonomy considering a previous stored list.
		if ( ! $taxonomy ) {
			$taxonomy = self::$taxonomy_compat[ self::$taxonomy_compat_index++ ];
		}

		$default_value = $this->get_default_value_for_tax( $taxonomy->name );

		$type = array(
			'title' => $taxonomy->label,
			'type'  => 'select',
			'name'  => 'tax_input[' . $taxonomy->name . ']',
			'extra' => array(
				'id'      => $taxonomy->name,
				'section' => 'taxonomies',
				'default' => $default_value,
			),
			'choices'  => $this->_get_terms_key_value_pairs( $taxonomy->name ),
			'selected' => $default_value,
		);

		if ( false !== strpos( $taxonomy->name, '_tag' ) ) {
			$type['type'] = 'text';
			$type['extra']['class'] = 'regular-text';
			$type['extra']['section'] = 'taxonomies';
			$type['value'] = $default_value;
		}

		return $this->input( $type );
	}

	/**
	 * Outputs the date interval settings.
	 */
	public function output_date_span() {
		return apply_filters( 'goft_wpjm_setting_date_span', false );
	}

	/**
	 * Outputs the main fields table.
	 */
	public function output_sample_table() {
		$output = GoFetch_Sample_Table::display();
		return $output;
	}

	/**
	 * Overrides the parent method to provide a different form button.
	 */
	public function form_table_wrap( $content ) {

		$args = array(
			'class' => 'button-primary import-posts',
			'value' => __( 'Go Fetch Jobs!', 'gofetch-wpjm' ),
		);

		$output = self::table_wrap( $content );
		$output = self::extra_content( $output );
		$output = $this->form_wrap_alt( $output, $args );

		return $output;
	}


	/**
	 * Wraps a content in a form.
	 *
	 * @param string $content
	 * @param string $nonce (optional)
	 *
	 * @return string
	 */
	public function form_wrap_alt( $content, $args ) {

		$form = $this->form_wrap( $content, $args );

		$form = html( 'div class="goft-table-wrapper"', $form );

		return str_replace( '<form', "<form id='gofj_import' enctype='multipart/form-data'", $form );
	}


	/**
	 * Outputs the <table> wrapper.
	 */
	public static function table_wrap( $content ) {
		return html( "table class='form-table goft'", $content );
	}

	/**
	 * Outputs additional content within the main form.
	 */
	public static function extra_content( $content ) {

		$content .= '<span class="helper-fields"></span>';

		$content = $content . self::get_geocomplete_fields();
		$content = $content . '<input name="content_type" type="hidden" value="RSS">';

		return apply_filters( 'goft_wpjm_form_extra_content', $content );
	}

	/**
	 * The admin messages to display after user actions.
	 */
	public function admin_msg( $msg = '', $class = 'updated' ) {

		$stats = self::$imported;

		if ( ! empty( $stats['imported'] ) || ! empty( $stats['updated'] ) || ! empty( $stats['excluded'] ) ) {

			if ( $stats['imported'] > 0 ) {
				$imported_jobs_msg = sprintf( __( 'Imported %d NEW %s!', 'gofetch-wpjm' ), $stats['imported'], _n( 'job' , 'jobs', $stats['imported'], 'gofetch-wpjm' ) );
			} else {
				$imported_jobs_msg = __( 'No new jobs found!', 'gofetch-wpjm' );
			}

			$msg = html( 'h1', html( 'em', html( 'strong', $imported_jobs_msg ) ) );

			$li  = html( 'li', html( 'em', sprintf( __( 'Skipped: <strong>%d %s</strong> <small>(discarded - applied import limit criteria)</small>', 'gofetch-wpjm' ), $stats['limit'], _n( 'job' , 'jobs', $stats['limit'], 'gofetch-wpjm' ) ) ) );
			$li .= html( 'li', html( 'em', sprintf( __( 'Duplicate: <strong>%d %s</strong> <small>(discarded - already exist in database)</small>', 'gofetch-wpjm' ), $stats['duplicates'], _n( 'job' , 'jobs', $stats['duplicates'], 'gofetch-wpjm' ) ) ) );
			$li .= html( 'li', html( 'em', sprintf( __( 'Excluded: <strong>%d %s</strong> <small>(discarded - after applying your filters)</small>', 'gofetch-wpjm' ), $stats['excluded'], _n( 'job' , 'jobs', $stats['excluded'], 'gofetch-wpjm' ) ) ) );

			$msg .= html( 'ul', $li );
			$msg .= html( 'p', html( 'small', sprintf( __( 'Feed contained: <strong>%d %s</strong>.', 'gofetch-wpjm' ), $stats['in_rss_feed'], _n( 'job' , 'jobs', $stats['in_rss_feed'], 'gofetch-wpjm' ) ) ) );

			$msg = html( 'div class="goft-stats"', $msg );

			$msg .= html( 'div class="goft-stats-image"', html( 'img', array( 'src' => GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/images/gofetch-bg.png' ) ) );

		} else {
			$msg .= __( 'No new jobs found.', 'gofetch-wpjm' );
		}

		if ( ! empty( $_POST['save_template'] ) ) {

			if ( $msg ) {
				$msg .= '<br/><br/>';
			}

			$msg .= __( 'Template <strong>saved</strong>.', 'gofetch-wpjm' );
		}

		$class .= ' goft-stats-container';

		echo scb_bc_admin_notice( $msg, $class );
	}

		/**
	 * The admin messages to display after user actions.
	 */
	public function admin_msg_auto_schedule( $msg = '', $class = 'updated' ) {

		$stats = self::$imported;

		if ( empty( $stats['new_schedule'] ) ) {
			return;
		}

		$msg = wp_kses_post( $stats['new_schedule'] );

		echo scb_bc_admin_notice( $msg, $class );
	}

}

new GoFetch_Admin_Builder( GOFT_WPJM_PLUGIN_FILE, $goft_wpjm_options );
new GoFetch_Help_Admin_Screen_Options();
