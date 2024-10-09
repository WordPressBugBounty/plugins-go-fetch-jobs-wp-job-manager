<?php
/**
 * Self Hosted Plugin Browser Module.
 *
 * @package Framework\Plugin-Browser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_init', array( 'BC_Framework_Plugin_Browser', 'init' ) );

/**
 * Self Hosted Plugin Browser.
 *
 * Extends the WordPress plugins browser to display self-hosted plugins with additional options.
 */
final class BC_Framework_Plugin_Browser {

	/**
	 * The core plugin browser object.
	 *
	 * @var object 'BC_Framework_Plugin_Browser_Core'
	 */
	public $core;

	/**
	 * @var The single instance of the class.
	 */
	protected static $_instance = array();

	public static function register( $page_slug = 'wp-shp-browser', $args = array(), $file = '' ) {
		if ( empty( self::$_instance[ $page_slug ] ) ) {
			self::$_instance[ $page_slug ] = new self( $page_slug, $args, $file );
		}
		return self::$_instance[ $page_slug ];
	}

	public static function get_instance( $page_slug ) {
		if ( ! empty( self::$_instance[ $page_slug ] ) ) {
			return self::$_instance[ $page_slug ];
		}
		return false;
	}

	/**
	 * Constructor.
	 *
	 * @param string $page_slug       The page slug.
	 * @param array  $args            The arguments to build/customize the plugin browser (read more below).
	 * @param string $file (optional) The plugin file name.
	 */
	public function __construct( $page_slug = 'wp-shp-browser', $args = array(), $file = '' ) {

		if ( empty( $args['tabs'] ) || ! is_array( $args['tabs'] ) ) {
			trigger_error('No valid tabs were found for the plugin browser.');
			return;
		}

		self::init();

		$this->core = new BC_Framework_Plugin_Browser_Core( $page_slug, $args, $file );

		do_action( 'wp_self_hosted_plugin_init_' . $page_slug );
	}

	/**
	 * Initialize.
	 */
	public static function init() {
		self::load_files();
		self::load_hooks();
	}

	/**
	 * Include dependencies.
	 */
	private static function load_files() {
		require_once dirname( __FILE__ ) . '/class-wp-plugin-browser-notices.php';
		require_once dirname( __FILE__ ) . '/class-wp-plugin-browser-menu.php';
		require_once dirname( __FILE__ ) . '/class-wp-plugin-browser.php';
		require_once dirname( __FILE__ ) . '/class-wp-plugin-browser-list.php';
	}

	/**
	 * Load hooks.
	 */
	private static function load_hooks() {
		add_action( 'wp_ajax_wp_sh_plugin_browser_dismiss_notice', array( 'BC_Framework_Plugin_Browser_Core', 'dismiss_notice' ) );
	}

}


// __Main Helper.

/**
 * Register a new plugin browser page.
 *
 * Accepted parameters for the browser page:
 *
 * @param array $args {
 *    Array of arguments for registering a plugin browser page.
 *
 *    @type string    $page_title        The page title (default 'Browse Plugins')
 *
 *    @type string    $remote_info       The path to a XML file containing any of the parameters accepted.
 *                                       If provided, it will override values passed to any of the parameter defined below.
 *                                       This allows changing the plugin browser without having to update the module. (default 'false')
 *
 *    @type array     $header_buttons    A list of header buttons to display right beside the page title. (default 'false')
 *                                       e.g: array( 'title' => 'Browse Shop', 'url' => 'http://my-plugin-shop.com' ).
 *
 *    @type boolean   $show_popular      If set to 'true', and the plugin contains ratings data, a dynamic tab of the most
 *                                       popular plugins will be automatically generated. (default 'true')
 *
 *    @type number    $popular_min_rates The min number of rates a plugin must have to be marked as popular (default '1').
 *
 *    @type boolean   $search            If set to 'true', the search bar will be displayed. (default 'true')
 *
 *    @type array     $vendor            Vendor information to display on the plugin browser header. (default '')
 *
 *                                       args:
 *
 *                                       'name'    The vendor name
 *                                       'url'     The vendor site URL
 *                                       'avatar'  The vendor avatar email
 *                                       'logo'    The vendor logo URL
 *                                       'social'  The social networks information
 *                                          array(
 *                                             'email-alt'  The vendor contact URL,
 *                                             'twitter'    Twitter ID,
 *                                             'wordpress'  WordPRess profile URL
 *                                          ),
 *
 *                                       e.g:
 *
 *                                       array(
 *                                           'name'    => 'John Doe Plugins',
 *                                           'url'     => 'http://jh-plugins.com',
 *                                           'avatar' => 'jh-plugins@email.com',
 *                                           'logo'   => 'http://jh-plugins.com/logo.png'
 *                                           'social' => array(
 *                                              'email-alt' => 'jh-plugins@email.com',
 *                                              'twitter'   => 'jh-pluginsm',
 *                                              'wordpress' => 'jh-plugins@email.com'
 *                                           ),
 *                                       );
 *
 *    @type array     $tabs             The tabs to display on the plugin browser.
 *          							At least one tab should have a URL pointing to a XML file containing the plugins data.  (default '').
 *
 *                                      args:
 *
 *                                       'slug'  The tab slug name
 *                                          array(
 *                                            'name'   The tab name/title
 *                                            'url'    The XML file URL with the plugins to be displayed in the tab.
 *                                                     The URL is only needed for the default tab. Tabs with empty URL's will
 *                                                     be populated if the tab name is found inside the <tabs> tag in the default tab XML file.
 *                                          ),
 *
 *                                       e.g:
 *
 *                                       array(
 *                                           'new' => array(
 *                                               'name' => 'New',
 *                                               'url'  => 'http://jh-plugins.com/my-plugins-tab-new.xml'
 *                                           ),
 *                                           'other' => array(
 *                                               'name' => 'Other',
 *                                               'url'  => '' // content will be added by looking for 'other' in the previous XML <tabs> tag
 *                                           ),
 *                                       );
 *
 *    @type string    $default_tab      The slug name for the tab to be used as default (default '').
 *
 *    @type array     $filters          A list of key/title pairs filters to be displayed. The filters key must match XML tag names. (default: host, requirements, categories, authors)
 *
 * 										Note: 'host', 'requirements' and 'authors' must have a <name> tag/key since they are also used and visible in the plugin card.
 * 										      Categories and other custom filters are not displayed and do not need the extra information.
 *
 *                                      e.g:
 *
 *                                       array(
 *                                           'requirements' => 'Any Requirements'
 *                                           'categories'   => 'All Categories',
 *                                       );
 *
 *										<categories>
 *											WooCommerce, Yoast, etc
 *										</categories>
 *
 *		        						<requirements>
 *			             					<requirement>
 *				                 				<name>WooCommerce</name>
 *			                      			</requirement>
  *			             					<requirement>
 *				                 				<name>Yoast</name>
 *			                      			</requirement>
 *		                          		</requirements>
 *
 *    @type array     $wp_hosted_args   A list of plugin API args to display specific WordPress hosted plugins. Look in 'plugins_api' function for more info. (default '')
 *
 *                                      e.g:
 *
 *                                       array(
 *                                           'author' => 'john-doe-plugins', // besides the self-hosted plugins additionally display any plugins by this author on the plugins browser.
 *                                       );
 *
 *    @type string    $menu             The menu args. See WordPress 'add_submenu_page' for info.
 *
 */
function wp_product_showcase_register( $page_slug = 'wp-shp-browser', $args = array(), $file = '' ) {

	if ( ! class_exists( 'WP_List_Table' ) ) {
		_doing_it_wrong( __FUNCTION__,  "This function must be hooked through the 'admin_menu' hook." , '1.0' );
		return;
	}

	return BC_Framework_Plugin_Browser::register( $page_slug, $args, $file );
}

/**
 * Retrieves the list of plugins for an existing plugin browser page slug.
 *
 * @param  string $page_slug The page slug for the plugin browser that will be queried.
 * @return array             The list of plugins for the specified plugin browser page slug.
 */
function wp_product_showcase_get_list( $page_slug = 'wp-shp-browser', $refresh = false ) {
	$wp_sh = BC_Framework_Plugin_Browser::get_instance( $page_slug );

	if ( ! is_a( $wp_sh, 'BC_Framework_Plugin_Browser' ) ) {
		_doing_it_wrong( __FUNCTION__,  "No plugin browser data found for the specified slug. This function must be hooked through the 'admin_menu' hook, after the plugin browser object is created." , '1.0' );
		return;
	}

	$list = $wp_sh->core->get_plugins( $refresh );

	return $list;
}
