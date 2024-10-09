<?php
/**
 * Provides basic admin functionality.
 *
 * @package GoFetchJobs/Admin
 */

use Stripe\Source;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Base admin class.
 */
class GoFetch_Admin {

	/**
	 * List of pages considered valid for this class.
	 *
	 * @var string
	 */
	protected $valid_admin_pages_prefix = 'go-fetch-jobs-';

	/**
	 * The guided tutorial instance
	 */
	protected $guided_tutorial = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $menu;
		if ( get_option( 'goft-wpjm-error' ) ) {
			add_action( 'admin_notices', array( $this, 'warnings' ) );
		}

		$this->hooks();
		$this->includes();

		if ( class_exists( 'GoFetch_Guided_Tutorial' ) ) {
			$this->guided_tutorial = new GoFetch_Guided_Tutorial();
		}

		add_action( 'admin_init', array( $this, 'maybe_init_guided_tutorial' ), 25 );
		add_action( 'restrict_manage_posts', array( $this, 'jobs_filter_restrict_manage_posts' ) );
		add_action( 'restrict_manage_posts', array( $this, 'jobs_filter_restrict_providers' ) );
		add_filter( 'manage_' . GoFetch_Jobs()->parent_post_type . '_posts_custom_column', array( $this, 'custom_columns' ), 2 );
		add_filter( 'manage_edit-' . GoFetch_Jobs()->parent_post_type . '_columns', array( $this, 'columns' ) );
		add_filter( 'parse_query', array( $this, 'jobs_filter' ) );
		add_filter( 'parse_query', array( $this, 'providers_filter' ) );
		add_filter( 'wp_kses_allowed_html', array( $this, 'allowed_html' ), 50, 2 );
		add_filter( 'safe_style_css', array( $this, 'safe_style_css' ), 50, 2 );
	}

	/**
	 * Include any classes we need within admin.
	 */
	public function includes() {
		require_once 'class-gofetch-admin-builder.php';
		require_once 'class-gofetch-admin-sample-table.php';
		require_once 'class-gofetch-admin-settings.php';
		require_once 'class-gofetch-admin-help.php';
		require_once 'class-gofetch-admin-ajax.php';
		require_once 'class-gofetch-guided-tutorial.php';

		require_once 'intro/class-gofetch-admin-api-providers.php';
		require_once 'intro/class-gofetch-admin-ats-providers.php';
		require_once 'intro/class-gofetch-api-providers-intro.php';
		require_once 'intro/class-gofetch-ats-providers-intro.php';
	}

	public function hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_scripts' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 21 );
		add_action( 'admin_head', array( $this, 'admin_icon' ) );
	}

	public function maybe_init_guided_tutorial() {
		global $submenu;

		// Don't init the tutorial if the plugin items are not initialized yet (i.e: avoids tutorial showing on Freemius opt-in init page).
		if ( empty( $submenu['go-fetch-jobs-wp-job-manager'] ) ) {
			remove_action( 'admin_enqueue_scripts', array( $this->guided_tutorial, 'start_tour' ), 25 );
		}

	}

	/**
	 * Check if the current page is a valid GOFJ page.
	 */
	protected function is_valid_admin_page() {
		if ( empty( $_GET['page'] ) && empty( $_GET['post'] ) ) {
			return false;
		}

		if ( ! empty( $_GET['post'] ) ) {
			$post_id = sanitize_text_field( intval( $_GET['post'] ) );
			$post_type = get_post_type( $post_id );
			return GoFetch_Jobs()->post_type === $post_type;
		}

		$page = sanitize_text_field( $_GET['page'] );
		return strpos( $this->valid_admin_pages_prefix, $page ) >= 0;
	}

	/**
	 * Register admin JS scripts and CSS styles.
	 */
	public function register_admin_scripts( $hook ) {
		global $goft_wpjm_options;

		$ext = ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ? '.min' : '' ) . '.js';

		wp_register_style(
			'goft-fontello',
			GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/font-icons/css/goft-fontello.css'
		);

		if ( $this->is_valid_admin_page() || $this->load_scripts( $hook ) ) {

			// Dummy script for appending inline JS.
			wp_register_script(
				'goft_wpjm',
				GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/js/goft.min.js',
				array(),
				GoFetch_Jobs()->version,
				true
			);

			wp_register_script(
				'select2-goft',
				GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/select2/4.0.3/js/select2.min.js',
				array( 'jquery' ),
				GoFetch_Jobs()->version,
				true
			);

			wp_register_script(
				'select2-resize',
				GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/select2/maximize-select2-height.min.js',
				array( 'select2-goft' ),
				GoFetch_Jobs()->version,
				true
			);

			wp_register_style(
				'select2-goft',
				GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/select2/4.0.3/css/select2.min.css'
			);

		}

		// Selective load.
		if ( ! $this->load_scripts( $hook ) ) {
			return;
		}

		wp_register_script(
			'goft_wpjm-settings',
			GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/js/scripts' . $ext,
			array( 'jquery' ),
			GoFetch_Jobs()->version,
			true
		);

		wp_register_script(
			'validate',
			GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/js/jquery.validate.min.js',
			array( 'jquery' ),
			GoFetch_Jobs()->version
		);

		if ( ! empty( $goft_wpjm_options->geocode_api_key ) ) {

			$params = array(
				'sensor'    => false,
				'libraries' => 'places',
				'key'       => $goft_wpjm_options->geocode_api_key,
				'v'         => 3,
				'language'  => get_bloginfo( 'language' ),
			);
			$google_api = add_query_arg( apply_filters( 'goft_wpjm_gmaps_params', $params ), 'https://maps.googleapis.com/maps/api/js' );

			wp_register_script(
				'gmaps',
				$google_api,
				array( 'jquery' ),
				GoFetch_Jobs()->version
			);

			wp_register_script(
				'geocomplete',
				GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/js/jquery.geocomplete.min.js',
				array( 'jquery', 'gmaps' ),
				GoFetch_Jobs()->version
			);

		}

		wp_register_style(
			'goft_wpjm',
			GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/css/styles.css',
			array(),
			GoFetch_Jobs()->version
		);

	}

	/**
	 * Enqueue registered admin JS scripts and CSS styles.
	 */
	public function enqueue_admin_scripts( $hook ) {
		global $goft_wpjm_options;

		wp_enqueue_style( 'goft-fontello' );

		// Always enqueue 'select2' on the settings page.
		if ( $this->is_valid_admin_page() || $this->load_scripts( $hook ) ) {
			wp_enqueue_script( 'goft_wpjm' );
			wp_enqueue_script( 'select2-goft' );
			wp_enqueue_script( 'select2-resize' );
			wp_enqueue_style( 'select2-goft' );
		}

		wp_add_inline_script( 'select2-goft', "jQuery('.select2-gofj-multiple').select2({
			placeholder: 'Choose ...',
		});" );

		// Selective load.
		if ( ! $this->load_scripts( $hook ) ) {
			return;
		}

		wp_enqueue_script( 'goft_wpjm-settings' );
		wp_enqueue_script( 'validate' );

		wp_enqueue_script( 'gmaps' );
		wp_enqueue_script( 'geocomplete' );

		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_style( 'goft_wpjm' );

		$params = array(
			'ajaxurl'               => admin_url( 'admin-ajax.php' ),
			'ajax_nonce'            => wp_create_nonce( 'goft_wpjm_nonce' ),
			'ajax_save_nonce'       => wp_create_nonce( GoFetch_Jobs()->slug ),
			'date_format'           => get_option( 'date_format' ),
			'geocode_api_key'       => (bool) $goft_wpjm_options->geocode_api_key,

			// Messages.
			'msg_jobs_found'        => __( 'Job(s) Available', 'gofetch-wpjm' ),
			'msg_jobs_limit_warn'   => __( 'You are choosing to import a very high number of jobs, which can be very resource intensive. This it not recommended, specially if used with multiple schedules.<br/>If you get memory related issues, please try reducing this number using the limit filter, below.', 'gofetch-wpjm' ),
			'msg_try_scrape'        => __( 'If the feed is returning incomplete content, try the option above. It can help retrieve additional information directly from the provider site.<br/>', 'gofetch-wpjm' ),
			'msg_not_rss_feed'      => __( '<div><span class="dashicons-before dashicons-info"></span>Since you\'ve loaded <strong>[content_type]</strong> content (not a standard RSS feed), please make sure that <strong>JOB TITLE</strong>, <strong>JOB DESCRIPTION</strong> and <strong>APPLICATION URL</strong>, or <strong>APPLICATION EMAIL (if applicable)</strong>, are mapped, or visitors won\'t be able to apply.</div>', 'gofetch-wpjm' ),
			'msg_file_loaded'       => __( '<div><span class="dashicons-before dashicons-info"></span>Since you\'ve loaded a local <strong>[content_type]</strong> file, please make sure that <strong>JOB TITLE</strong>, <strong>JOB DESCRIPTION</strong> and <strong>APPLICATION URL</strong>, or <strong>APPLICATION EMAIL (if applicable)</strong>, are mapped, or visitors won\'t be able to apply.</div>', 'gofetch-wpjm' ),
			'msg_simple'            => __( 'Simple...', 'gofetch-wpjm' ),
			'msg_advanced'          => __( 'Edit...', 'gofetch-wpjm' ),
			'msg_specify_valid_url' => __( 'Please specify a valid URL/File.', 'gofetch-wpjm' ),
			'msg_invalid_feed'      => __( 'Could not load feed.', 'gofetch-wpjm' ),
			'msg_no_jobs_found'     => __( 'No jobs found in feed.', 'gofetch-wpjm' ),
			'msg_required_params'   => __( 'Please fill in ALL the required highlighted parameters.', 'gofetch-wpjm' ),
			'msg_required_optional_params'  => __( 'Please fill AT LEAST ONE of the required highlighted parameters.', 'gofetch-wpjm' ),
			'msg_template_missing'  => __( 'Please specify a template name.', 'gofetch-wpjm' ),
			'msg_template_saved'    => __( 'Template Settings Saved.', 'gofetch-wpjm' ),
			'msg_save_error'        => __( 'Save failed. Please try again later.', 'gofetch-wpjm' ),
			'msg_rss_copied'        => __( 'Feed URL copied', 'gofetch-wpjm' ),
			'msg_import_jobs'       => __( 'Jobs are being fetched and imported, please do not move away from this page until import finishes. This can take some time depending on your options and number of jobs being imported...' , 'goftech-wpjm' ),

			'title_close'           => esc_attr( __( 'Close/Hide', 'gofetch-wpjm' ) ),

			'label_yes'             => __( 'Yes', 'gofetch-wpjm' ),
			'label_no'              => __( 'No', 'gofetch-wpjm' ),
			'label_providers'       => __( 'Choose a Job Provider . . .', 'gofetch-wpjm' ),
			'label_templates'       => __( 'Choose a Template . . .', 'gofetch-wpjm' ),
			'label_scrape_fields'   => __( 'Choose fields to scrape . . .', 'gofetch-wpjm' ),

			'cancel_feed_load'      => __( 'Cancel', 'gofetch-wpjm' ),

			'default_query_args'    => GoFetch_RSS_Providers::valid_item_tags(),

			'jobs_limit_warn'       => apply_filters( 'goft_wpjm_jobs_limit_warn', 99 ),

			'can_use_premium' => intval( gfjwjm_fs()->can_use_premium_code() ) === 0 ? 'no' : 'yes',

			'scrape_fields' => $goft_wpjm_options->scrape_fields,

			'core_fields' => array(
				'Application URL' => $goft_wpjm_options->setup_field_application,
				'Job Title'               => 'post_title',
				'Job Description'         => 'post_content',
			)
		);

		if ( gfjwjm_fs()->can_use_premium_code() && class_exists( 'GoFetch_Scheduler' ) ) {
			$params['used_templates'] = GoFetch_Scheduler::get_used_templates();
		}

		wp_localize_script( 'goft_wpjm-settings', 'goft_wpjm_admin_l18n', $params );
	}

	/**
	 * Criteria used for the selective load of scripts/styles.
	 */
	private function load_scripts( $hook = '' ) {
		global $plugin_page;

		if ( empty( $_GET['post_type'] ) && empty( $_GET['post'] ) && 'toplevel_page_' . GoFetch_Jobs()->slug !== $hook ) {
			return false;
		}

		$post_type = '';

		if ( ! empty( $_GET['post'] ) ) {
			$post = get_post( (int) sanitize_text_field( $_GET['post'] ) );
			if ( $post ) {
				$post_type = $post->post_type;
			}
		} elseif ( isset( $_GET['post_type'] ) ) {
			$post_type = sanitize_text_field( $_GET['post_type'] );
		}

		if ( GoFetch_Jobs()->post_type !== $post_type && 'toplevel_page_' . GoFetch_Jobs()->slug !== $hook ) {
			return false;
		}
		return true;
	}

	/**
	 * Checks if the user is on a plugin related page.
	 */
	protected function is_plugin_page() {
		$is_doing_gofj_ajax = is_admin() && defined( 'DOING_AJAX' ) && DOING_AJAX && ! empty( $_REQUEST['action'] ) && strpos( sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ), 'goft_' ) >= 0;
		return $this->is_valid_admin_page() || $is_doing_gofj_ajax;
	}

	/**
	 * Use external font icons in dashboard.
	 */
	public function admin_icon() {
		echo "<style type='text/css' media='screen'>
	   		#toplevel_page_" . GoFetch_Jobs()->slug . " div.wp-menu-image:before {
	   		font-family: goft-fontello !important;
	   		content: '\\e802';
		 	}
		 	</style>";
	}

	/**
	 * Display a custom filter dropdown to filter imported/user submitted jobs.
	 */
	public function jobs_filter_restrict_manage_posts( $type ) {
		global $goft_wpjm_options;

		if ( ! $goft_wpjm_options->admin_jobs_filter ) {
			return;
		}

		$post_type = GoFetch_Jobs()->parent_post_type;

		if ( $post_type !== $type ) {
			return;
		}

		$values = array(
			__( 'Imported', 'gofetch-wpjm' )       => 1,
			__( 'User Submitted', 'gofetch-wpjm' ) => 2,
		);
?>
		<select name="goft_imported_jobs">
			<option value=""><?php _e( 'All Jobs', 'gofetch-wpjm' ); ?></option>
			<?php
				$current_v = isset( $_GET['goft_imported_jobs'] ) ? (int) sanitize_text_field( $_GET['goft_imported_jobs'] ) : '';
				foreach ( $values as $label => $value ) {
					printf( '<option value="%s"%s>%s</option>', $value, selected( $value, $current_v ), $label );
				}
			?>
		</select>
<?php
	}

	/**
	 * Display a custom filter dropdown to filter providers.
	 */
	public function jobs_filter_restrict_providers( $type ) {
		global $goft_wpjm_options, $wpdb;

		if ( ! $goft_wpjm_options->admin_provider_filter ) {
			return;
		}

		$post_type = GoFetch_Jobs()->parent_post_type;

		if ( $post_type !== $type ) {
			return;
		}

		$values = array();

		$providers = $this->get_current_providers();

		if ( empty( $providers ) ) return;

		$values = array_combine( array_keys( $providers ), range( 1, count( $providers ) ) );

		$labels = array();

		foreach ( $values as $label => $value ) {
			$url_parts = parse_url( $label );
			if ( ! empty( $url_parts['host'] ) ) {
				$label = $url_parts['host'];
			}
			$label = str_replace( array( 'www.', 'www2.' ), '', $label );
			$labels[ $value ] = $label;
		}
		asort( $labels );
?>
		<select name="goft_provider">
			<option value=""><?php _e( 'All Providers', 'gofetch-wpjm' ); ?></option>
			<?php
				$current_v = isset( $_GET['goft_provider'] ) && ( empty( $_GET['goft_imported_jobs'] ) || 2 !== (int) $_GET['goft_imported_jobs'] ) ? (int) $_GET['goft_provider'] : '';
				foreach ($labels as $value => $label ) {
					printf( '<option value="%s"%s>%s</option>', $value, selected( $value, $current_v, false ), $label );
				}
			?>
		</select>
		<input type="hidden" name="providers_list" value="<?php echo esc_attr( implode( ',', $providers ) ); ?>">
	<?php
	}

	/**
	 * Display additional columns on job listings.
	 */
	public function columns( $columns ) {
		global $goft_wpjm_options;

		if ( ! $goft_wpjm_options->admin_jobs_provider_col ) {
			return $columns;
		}

		if ( ! is_array( $columns ) ) {
			$columns = array();
		}

		$total_cols = count( $columns );

		$new_columns = array();

		$cols = 0;

		foreach ( $columns as $key => $label ) {
			// Display custom columns before the last column.
			if ( $total_cols - $cols === 1 ) {
				$new_columns['job_provider'] = __( 'Provider', 'gofetch-wpjm' );
			}
			$new_columns[ $key ] = $label;

			$cols++;
		}
		return $new_columns;
	}

	/**
	 * Display custom columns on job listings.
	 */
	public function custom_columns( $column ) {
		global $post, $goft_wpjm_options;

		if ( ! $goft_wpjm_options->admin_jobs_provider_col ) {
			return;
		}

		$source = get_post_meta( $post->ID, '_goft_source_data', true );

		switch ( $column ) {

			case 'job_provider':
				if ( ! empty( $source['website'] ) ) {
					if ( strtolower( $source['website'] ) !== 'unknown' ) {
						$value = GoFetch_RSS_Providers::simple_url( $source['website'] );
					} else {
						$import_params = get_post_meta( $post->ID, '_goft_wpjm_import_params', true );
						$value = sprintf( '%s / %s', $source['website'], $import_params['content_type'] );
					}
					echo sprintf( '<span class="goft-job-provider">%s</span>', esc_attr( $value ) );
				} else {
					echo '-';
				}
				break;

		}

	}

	/**
	 * Apply the custom filter.
	 */
	public function jobs_filter( $query ) {
		global $pagenow;

		$post_type = GoFetch_Jobs()->parent_post_type;

		if ( ! isset( $_GET['post_type'] ) || $post_type !== $_GET['post_type'] ) {
			return;
		}

		if ( is_admin() && $pagenow === 'edit.php' && isset( $_GET['goft_imported_jobs'] ) && $_GET['goft_imported_jobs'] != '' ) {

			$compare = '=';

			if ( 2 == $_GET['goft_imported_jobs'] ) {
				$compare = 'NOT EXISTS';
			}

			if ( empty( $query->query_vars['meta_query'] ) && empty( $query->query_vars['meta_key'] ) ) {

				$query->query_vars['meta_key']   = '_goft_wpjm_is_external';
				$query->query_vars['meta_value'] = (int) sanitize_text_field( $_GET['goft_imported_jobs'] );
				$query->query_vars['meta_compare'] = $compare;

			} else {

				$query->query_vars = wp_parse_args( $query->query_vars, array(
					'meta_query' => array(),
				) );

				$meta_query = array_merge( $query->query_vars['meta_query'], array(
					array(
						'key'     => '_goft_wpjm_is_external',
						'value'   => (int) sanitize_text_field( $_GET['goft_imported_jobs'] ),
						'compare' => $compare,
					),
				));
				$query->query_vars['meta_query'] = $meta_query;

			}

		}

	}

	/**
	 * Apply the custom filter.
	 */
	public function providers_filter( $query ) {
		global $pagenow;

		$post_type = GoFetch_Jobs()->parent_post_type;

		if ( ! isset( $_GET['post_type'] ) || $post_type !== $_GET['post_type'] ) {
			return;
		}

		if ( is_admin() && 'edit.php' === $pagenow && ! empty( $_GET['goft_provider'] ) && ( empty( $_GET['goft_imported_jobs'] ) || 2 !== (int) $_GET['goft_imported_jobs'] ) ) {

			$providers = explode( ',', stripslashes( sanitize_text_field( $_GET['providers_list'] ) ) );
			$values    = array_combine( range( 1, count( $providers ) ), $providers );

			if ( empty( $_GET['goft_provider'] ) ) {
				return;
			}

			if ( empty( $query->query_vars['meta_query'] ) && empty( $query->query_vars['meta_key'] ) ) {

				$query->query_vars['meta_compare'] = 'LIKE';
				$query->query_vars['meta_key']     = '_goft_source_data';
				$query->query_vars['meta_value']   =  $values[ (int) sanitize_text_field( $_GET['goft_provider'] ) ];

			} else {

				$query->query_vars = wp_parse_args( $query->query_vars, array(
					'meta_query' => array(),
				) );

				$meta_query = array_merge( $query->query_vars['meta_query'], array(
					array(
						'key'     => '_goft_source_data',
						'value'   => $values[ (int) sanitize_text_field( $_GET['goft_provider'] ) ],
						'compare' => 'LIKE',
					),
				));
				$query->query_vars['meta_query'] = $meta_query;

			}
		}

	}

	/**
	 * Add support fo additional HTML tags and attributes.
	 */
	public function allowed_html( $allowed_tags, $context ) {

		if ( $context !== 'post' && ! $this->is_plugin_page() ) {
			return $allowed_tags;
		}

		$allowed_tags['a']['expand']   = true;
		$allowed_tags['a']['style']    = true;
		$allowed_tags['p']['style']    = true;
		$allowed_tags['h2']['section'] = true;
		$allowed_tags['td']['section'] = true;
		$allowed_tags['tr']['section'] = true;

		$span = array();

		if ( ! empty( $allowed_tags['span'] ) ) {
			$span = $allowed_tags['span'];
		}

		// Span.
		$allowed_tags['span'] = array_merge( $span, array(
			'class'  => true,
			'style'  => true,
			'data-*' => true,
			'title'  => true,
		) );

		$form = array();

		if ( ! empty( $allowed_tags['form'] ) ) {
			$form = $allowed_tags['form'];
		}

		// Form fields - input.
		$allowed_tags['form'] = array_merge( $form, array(
			'id'      => true,
			'enctype' => true,
			'method'  => true,
			'action'  => true,
		) );

		$input = array();

		if ( ! empty( $allowed_tags['input'] ) ) {
			$input = $allowed_tags['input'];
		}

		// Form fields - input.
		$allowed_tags['input'] = array_merge( $input, array(
			'class'       => true,
			'id'          => true,
			'name'        => true,
			'value'       => true,
			'type'        => true,
			'placeholder' => true,
			'data-*'      => true,
			'section'     => true,
			'checked'     => true,
			'style'       => true,
		) );

		$select = array();

		if ( ! empty( $allowed_tags['select'] ) ) {
			$select = $allowed_tags['select'];
		}

		// Select.
		$allowed_tags['select'] = array_merge( $select, array(
			'class'       => true,
			'id'          => true,
			'name'        => true,
			'value'       => true,
			'type'        => true,
			'data-*'      => true,
			'section'     => true,
			'style'       => true,
			'multiple'    => true,
			'placeholder' => true,
		) );

		$option = array();

		if ( ! empty( $allowed_tags['option'] ) ) {
			$option = $allowed_tags['option'];
		}

		// Select options.
		$allowed_tags['option'] = array_merge( $option, array(
			'selected' => true,
			'value'    => true,
		) );

		$allowed_tags['optgroup'] = array(
			'label' => true,
		);

		// Style.
		$allowed_tags['style'] = array(
			'types' => true,
		);

		return $allowed_tags;
	}

	/**
	 * Allow additional styles to safe styles list.
	 */
	public function safe_style_css( $styles ) {
		if ( ! $this->is_plugin_page() ) {
			return $styles;
		}

		$styles[] = 'display';

		return $styles;
	}

	/**
	 * Admin notices.
	 */
	public function warnings() {
		echo scb_admin_notice( sprintf( __( '<strong>%1$s</strong> was not found. Please install it first to be able to use <strong>%2$s</strong>.', 'gofetch-wpjm' ),  GoFetch_Jobs()->parent_plugin, 'Go Fetch Jobs' ), 'error' );
	}

	public static function limited_plan_warn() {

		$text = '';

		if ( ! gfjwjm_fs()->can_use_premium_code() ) {
			$tooltip = __( 'Not available on the Free plan.', 'gofetch-wpjm' );
			$text = html( 'span class="dashicons dashicons-warning tip-icon bc-tip limitation" data-tooltip="' . $tooltip . '"', '&nbsp;' );
		}
		return $text;
	}

	/**
	 * Retrieve providers for the jobs being listed.
	 */
	protected function get_current_providers() {
		global $wpdb;

		$providers = array();

		$screen = get_current_screen();
		$option = $screen->get_option( 'per_page', 'option' );

		$post_status = ! empty( $_GET['post_status'] ) && 'all' !== $_GET['post_status'] ? sanitize_text_field( $_GET['post_status'] ): '';

		if ( $post_status ) {
			$sql = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta a, $wpdb->posts b WHERE a.post_id = b.ID AND post_type = '%s' AND meta_key = '_goft_source_data' AND post_status = %s", GoFetch_Jobs()->parent_post_type, $post_status );
		} else {
			$sql = $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta a, $wpdb->posts b WHERE a.post_id = b.ID AND post_type = '%s' AND meta_key = '_goft_source_data' AND post_status <> 'trash' ", GoFetch_Jobs()->parent_post_type );
		}

		$results = $wpdb->get_results( $sql );

		foreach ( $results as $result ) {
			$meta = get_post_meta( (int) $result->post_id, '_goft_source_data', true );
			if ( ! empty( $meta['website'] ) ) {
				$providers[ $meta['website'] ] = GoFetch_RSS_Providers::simple_url( $meta['website'] );
			}
		}
		return $providers;
	}

}

$GLOBALS['goft_wpjm']['admin'] = new GoFetch_Admin();
