<?php

/**
 * Plugin Name: WP Job Manager - Go Fetch Jobs
 * Version:     1.8.4.8.1
 * Description: Instantly populate your WP Job Manager database using RSS job feeds from the most popular job sites.
 * Author:      Bruno Carreço
 * Plugin URI:  https://gofetchjobs.com
 * Other:       Icons by freepik 'http://www.freepik.com'
 *
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly.
}
$GLOBALS['gofj_module_settings'] = array();
if ( function_exists( 'gfjwjm_fs' ) ) {
    gfjwjm_fs()->set_basename( false, __FILE__ );
} else {
    // Begin Freemius.
    if ( !function_exists( 'gfjwjm_fs' ) ) {
        /**
         * Create a helper function for easy SDK access.
         */
        function gfjwjm_fs() {
            global $gfjwjm_fs;
            if ( !isset( $gfjwjm_fs ) ) {
                // Activate multisite network integration.
                if ( !defined( 'WP_FS__PRODUCT_192_MULTISITE' ) ) {
                    define( 'WP_FS__PRODUCT_192_MULTISITE', true );
                }
                // Include Freemius SDK.
                require_once dirname( __FILE__ ) . '/includes/freemius/start.php';
                $gfjwjm_fs = fs_dynamic_init( array(
                    'id'         => '192',
                    'slug'       => 'gofetch-wpjm',
                    'public_key' => 'pk_d8c021486da49f69324049b5736a3',
                    'menu'       => array(
                        'slug'    => 'go-fetch-jobs-wp-job-manager',
                        'support' => false,
                    ),
                    'is_premium' => false,
                    'trial'      => array(
                        'days'               => 7,
                        'is_require_payment' => true,
                    ),
                    'is_live'    => true,
                ) );
            }
            return $gfjwjm_fs;
        }

        gfjwjm_fs();
        gfjwjm_fs()->add_action( 'after_uninstall', array('GoFetch_Jobs', 'uninstall') );
        gfjwjm_fs()->add_filter( 'handle_gdpr_admin_notice', '__return_true' );
        // End Freemius.
        register_activation_hook( __FILE__, array('GoFetch_Jobs', 'activate') );
        // Initialize the plugin after all plugins are loaded.
        add_action( 'plugins_loaded', 'GoFetch_Jobs', 998 );
        if ( !class_exists( 'GoFetch_Jobs' ) ) {
            /**
             * Core class.
             */
            final class GoFetch_Jobs {
                /**
                 * The dynamic module for the plugin.
                 */
                protected static $module = 'wpjm';

                /**
                 * @var The plugin version.
                 */
                public $version = '1.8.4.8.1';

                /**
                 * @var The expected parent plugin/theme name.
                 */
                public $parent_plugin = 'WP Job Manager';

                /**
                 * @var The expected parent plugin/theme ID.
                 */
                public $parent_plugin_id = 'JOB_MANAGER_VERSION';

                /**
                 * @var The expected theme post type.
                 */
                public $parent_post_type = 'job_listing';

                /**
                 * @var The plugin slug.
                 */
                public $slug = 'go-fetch-jobs-wp-job-manager';

                /**
                 * @var The plugin post type.
                 */
                public $post_type = 'goft_wpjm_schedule';

                /**
                 * @var The log messages limit.
                 */
                public $log_limit = 10;

                /**
                 * @var The single instance of the class.
                 */
                protected static $_instance = null;

                /**
                 * Main 'Go Fetch Jobs' Instance.
                 *
                 * Ensures only one instance is loaded or can be loaded.
                 *
                 * @see GoFetch_Jobs()
                 *
                 * @return GoFetch_Jobs - Main instance
                 */
                public static function instance() {
                    if ( is_null( self::$_instance ) ) {
                        self::$_instance = new self();
                    }
                    return self::$_instance;
                }

                /**
                 * The Constructor.
                 */
                public function __construct() {
                    $this->define_constants();
                    $this->includes();
                    $this->include_modules();
                    $this->init_hooks();
                    do_action( 'gofetch_wpjm_loaded' );
                }

                /**
                 * Include required core files used in admin and on the frontend.
                 */
                public function includes() {
                    $active_module = self::$module;
                    // Framework Core.
                    require_once 'includes/framework/load.php';
                    // Parent plugin module.
                    require_once "includes/modules/{$active_module}/class-{$active_module}-module.php";
                    // Common dependencies.
                    require_once 'includes/settings.php';
                    require_once 'includes/dynamic/admin/class-gofetch-dynamic-importer.php';
                    // Admin Setup.
                    require_once 'includes/admin/class-gofetch-admin-setup.php';
                    require_once 'includes/class-gofetch-html-table.php';
                    require_once 'includes/class-gofetch-helper.php';
                    require_once 'includes/class-gofetch-importer.php';
                    // Other.
                    if ( defined( 'CARIERA_VERSION' ) ) {
                        require_once "includes/modules/{$active_module}/themes/cariera/class-gofetch-wpjm-cariera-importer.php";
                    }
                    require_once "includes/modules/{$active_module}/plugins/mas-wp-job-manager-company-integration.php";
                    // RSS providers.
                    require_once 'includes/class-gofetch-rss-providers.php';
                    require_once 'includes/dynamic/admin/class-gofetch-dynamic-importer.php';
                    require_once 'includes/admin/class-gofetch-admin.php';
                    if ( class_exists( 'GoFetch_Admin_Settings' ) ) {
                        require_once 'includes/dynamic/admin/class-gofetch-dynamic-settings.php';
                    }
                    if ( $this->is_request( 'frontend' ) ) {
                        require_once 'includes/class-gofetch-frontend.php';
                    }
                }

                /**
                 * Include amy external modules
                 */
                public function include_modules() {
                    $module = self::$module;
                    if ( $this->is_request( 'backend' ) ) {
                        require_once "includes/modules/{$module}/admin/class-gofetch-{$module}-settings.php";
                        require_once "includes/modules/{$module}/admin/class-gofetch-{$module}-importer.php";
                    }
                    require_once "includes/modules/{$module}/class-gofetch-{$module}.php";
                }

                /**
                 * Define Constants.
                 */
                private function define_constants() {
                    $this->define( 'GOFT_WPJM_PLUGIN_FILE', __FILE__ );
                }

                /**
                 * Hook into actions and filters.
                 */
                private function init_hooks() {
                    register_deactivation_hook( __FILE__, array($this, 'deactivate') );
                    add_action( 'init', array($this, 'init'), 0 );
                    add_action( 'init', array($this, 'maybe_get_list_of_providers') );
                }

                /**
                 * Init plugin when WordPress Initializes.
                 */
                public function init() {
                    add_action( 'init', array($this, 'maybe_install') );
                    add_action( 'init', array($this, 'maybe_install_sample_schedule') );
                    // Before init action.
                    do_action( 'before_gofetch_wpjm_init' );
                    // Set up localization.
                    $this->load_plugin_textdomain();
                    // Init action.
                    do_action( 'gofetch_wpjm_init' );
                }

                /**
                 * Load Localization files.
                 *
                 * Note: the first-loaded translation file overrides any following ones if the same translation is present.
                 *
                 * Locales are found in:
                 * - WP_LANG_DIR/plugins/gofetch-wpjm-LOCALE.mo
                 */
                public function load_plugin_textdomain() {
                    $locale = apply_filters( 'plugin_locale', get_locale(), 'gofetch-wpjm' );
                    load_textdomain( 'gofetch-wpjm', WP_LANG_DIR . '/plugins/gofetch-wpjm-' . $locale . '.mo' );
                    load_plugin_textdomain( 'gofetch-wpjm', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
                }

                /**
                 * Define constant if not already set.
                 *
                 * @param  string      $name
                 * @param  string|bool $value
                 */
                private function define( $name, $value ) {
                    if ( !defined( $name ) ) {
                        define( $name, $value );
                    }
                }

                /**
                 * What type of request is this?
                 *
                 * string $type ajax, frontend or admin
                 *
                 * @return bool
                 */
                private function is_request( $type ) {
                    switch ( $type ) {
                        case 'admin':
                            return is_admin();
                        case 'ajax':
                            return defined( 'DOING_AJAX' );
                        case 'cron':
                            return defined( 'DOING_CRON' );
                        case 'frontend':
                            return (!is_admin() || defined( 'DOING_AJAX' )) && !defined( 'DOING_CRON' );
                        case 'frontend_no_cron_ajax':
                            return !is_admin() && !defined( 'DOING_AJAX' ) && !defined( 'DOING_CRON' );
                        default:
                            // backend
                            return is_admin() || defined( 'DOING_AJAX' ) || defined( 'DOING_CRON' );
                    }
                }

                /*
                 * On deactivation, remove plugin related stuff.
                 */
                public function deactivate() {
                    wp_clear_scheduled_hook( 'gofetch_wpjm_importer' );
                }

                /*
                 * On activate, set default stuff.
                 */
                public static function activate() {
                    add_option( 'goft-wpjm-activated', 1 );
                }

                /*
                 * On uninstall, do stuff.
                 */
                public static function uninstall() {
                }

                /**
                 * Install any sample options.
                 */
                public function maybe_install() {
                    global $goft_wpjm_options;
                    if ( !defined( $this->parent_plugin_id ) ) {
                        add_option( 'goft-wpjm-error', 1 );
                        return;
                    } else {
                        delete_option( 'goft-wpjm-error' );
                    }
                    if ( !is_admin() || get_option( 'goft-wpjm-samples-installed' ) || !get_option( 'goft-wpjm-activated' ) ) {
                        return;
                    }
                    add_option( 'goft-wpjm-samples-installed', 1 );
                    delete_option( 'goft-wpjm-activated' );
                    // Skip sample install if there are already templates set.
                    if ( !empty( $goft_wpjm_options->templates ) ) {
                        return;
                    }
                    // Get the default values for the 'options' fields and save them on the DB.
                    //$options_defaults = $goft_wpjm_options->get_defaults( 'options' );
                    //$goft_wpjm_options->set( 'options', $options_defaults );
                    // Pre configure a default template.
                    // Provider.
                    $feed_url = 'https://jobs.theguardian.com/jobsrss/?keywords=wordpress%20full-time&radialtown=&';
                    // Taxonomy.
                    $job_type = apply_filters(
                        'goft_wpjm_default_value_for_taxonomy',
                        '',
                        'job_listing_type',
                        'full-time'
                    );
                    // Meta.
                    $expires = GoFetch_Dynamic_Import::get_expire_date();
                    $templates = $goft_wpjm_options->templates;
                    $example = 'rss-example-theguardian-wordpress-fulltime-jobs';
                    // @todo: adapt to the current module
                    $templates[$example] = array(
                        'rss_feed_import' => $feed_url,
                        'tax_input'       => array(
                            'job_listing_type' => $job_type,
                        ),
                        'meta'            => array(
                            '_job_expires' => $expires,
                        ),
                        'source'          => array(
                            'name'    => 'Guardian Jobs',
                            'website' => 'jobs.theguardian.com',
                            'logo'    => GoFetch_Jobs()->plugin_url() . '/includes/images/logos/logo-theguardian.png',
                        ),
                    );
                    //
                    $goft_wpjm_options->templates = $templates;
                }

                /**
                 * Install a sample schedule.
                 */
                public function maybe_install_sample_schedule() {
                    $module = self::$module;
                    if ( !is_admin() || !post_type_exists( $this->post_type ) ) {
                        return;
                    }
                    if ( !class_exists( 'GoFetch_Scheduler' ) || !get_option( "goft-{$module}-samples-installed" ) || get_option( "goft-{$module}-samples-schedule-installed" ) || !get_option( "goft-{$module}-activated" ) ) {
                        return;
                    }
                    $example = 'rss-example-theguardian-wordpress-fulltime-jobs';
                    GoFetch_Scheduler::create_schedule( 'Daily Import Example [WordPress Full-Time Jobs] (theguardian.com)', $example );
                }

                /**
                 * Get the plugin URL.
                 */
                public function plugin_url() {
                    return untrailingslashit( plugins_url( '/', __FILE__ ) );
                }

                /**
                 * Conditional check for a 'GOFJ' job page.
                 */
                public function is_single_goft_job_page() {
                    global $post;
                    if ( !is_singular( GoFetch_Jobs()->parent_post_type ) ) {
                        return false;
                    }
                    if ( !get_post_meta( $post->ID, '_goft_wpjm_is_external', true ) ) {
                        return false;
                    }
                    return true;
                }

                /**
                 * Fod debug purposes only.
                 */
                public function maybe_get_list_of_providers() {
                    if ( !empty( $_GET['GOFJ_PROVIDERS_LIST'] ) ) {
                        var_dump( GoFetch_RSS_Providers::get_providers_list() );
                        exit;
                    }
                }

            }

        }
        /**
         * Returns the main instance of 'Go Fetch Jobs'.
         *
         * @return GoFetch_Jobs instance.
         */
        function GoFetch_Jobs() {
            return GoFetch_Jobs::instance();
        }

    }
}