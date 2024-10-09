<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin browser core class.
 */
class BC_Framework_Plugin_Browser_Core extends BC_Framework_Plugin_Browser_Menu_Page {

	/**
	 * The page slug for the current plugin browser.
	 *
	 * @var string
	 */
	protected $page_slug;

	/**
	 * The plugin browser main args.
	 *
	 * @var array
	 */
	protected $browser_args;

	/**
	 * The list of transient used.
	 *
	 * @var array
	 */
	protected $transients;

	/**
	 * Constructor.
	 *
	 * Setup the plugin browser page.
	 *
	 * @param string          $page_slug The admin page slug.
	 * @param array           $args      Args to customize the plugin browser (see 'BC_Framework_Plugin_Browser' for more info).
	 * @param boolean|string  $file      Optional file name to pass to the parent class, if any.
	 */
	function __construct( $page_slug, $args = array(), $file = false ) {

		$this->page_slug = $page_slug;

		// Set the menu args (for WP menu page).
		$this->args = $args['menu'];

		unset( $args['menu'] );

		// Set the plugin browser args.
		$this->browser_args = $args;

		// Prepare the plugin browser.
		$this->prepare_browser();

		// Setup the plugin browser menu.
		parent::__construct( $file );

		do_action( "wp_self_hosted_plugin_page_{$this->pagehook}" );
	}

	/**
	 * Setup all the necessary stuff for the browser.
	 */
	public function prepare_browser() {
		$this->setup_hooks();
		$this->setup_browser();
		$this->setup_tabs();
		$this->setup_transients();
	}

	/**
	 * Setup all the necessary stuff for the browser.
	 */
	public function setup() {
		$this->setup_menu();
		$this->enqueue_scripts();
	}

	/**
	 * Condition check for displaying the plugin browser page.
	 *
	 * @return boolean True if the plugin browser page should be displayed, False otherwise.
	 */
	function condition() {
		return ! empty( $_GET['page'] ) && $this->page_slug === $_GET['page']; // Input var okay.
	}

	/**
	 * Additional setup code for the add-ons page.
	 */
	function enqueue_scripts() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ), 21 );
		add_action( 'admin_init', array( $this, 'add_pagination' ) );
		add_action( "bc_plugin_browser_content_{$this->page_slug}", array( $this, 'display_plugins_browser' ), 10, 2 );
	}

	/**
	 * Enqueue registered admin JS scripts and CSS styles.
	 *
	 * @param string $hook The current hook name.
	 */
	public function enqueue_admin_scripts( $hook ) {

		if ( ! $this->condition() ) {
			return;
		}

		wp_enqueue_script(
			$this->args['page_slug'],
			plugins_url( '/', __FILE__ ) . '/js/scripts.js',
			array( 'jquery' ),
			'1.0'
		);

		wp_enqueue_style(
			$this->args['page_slug'],
			plugins_url( '/', __FILE__ ) . '/css/styles.css',
			array(),
			'1.0'
		);

		wp_localize_script( $this->args['page_slug'], 'wp_sh_plugin_browser_admin_l18n', array(
			'ajaxurl'    => admin_url('admin-ajax.php'),
			'ajax_nonce' => wp_create_nonce('wp_sh_plugin_browser_nonce'),
	    ) );
	}

	/**
	 * Setup hooks.
	 */
	protected function setup_hooks() {
		add_action( "bc_plugin_browser_list_before_filter_{$this->page_slug}", array( $this, 'display_banner' ) );
	}

	/**
	 * Setup the browser args.
	 */
	protected function setup_browser() {

		$defaults = array(
			'page_title'        => __( 'Browse Products', 'wp-shp-browser' ),
			'remote_info'       => '',
			'header_buttons'    => false,
			'show_popular'      => true,
			'popular_min_rates' => 3,
			'search'            => true,
			'vendor'            => array(),
			'tabs'              => array(),
			'default_tab'       => '',
			'filters'           => array(
				//'host'         => __( 'Hosted Anywhere', 'wp-shp-browser' ),
				'requirements' => __( 'Any Requirements', 'wp-shp-browser' ),
				'categories'   => __( 'All Categories', 'wp-shp-browser' ),
				//'authors'      => __( 'All Authors', 'wp-shp-browser' ),
			),
			'wp_hosted_args' => array(),
			'menu'           => array(),
		);
		$this->browser_args = wp_parse_args( $this->browser_args, $defaults );
	}

	/**
	 * Setup the menu args.
	 */
	protected function setup_menu() {
		$nag = '';

		if ( $this->has_notices() ) {
			$nag = " <span class='wp-shp-browser-info dashicons dashicons-info' style='line-height: 0.8em'></span>";
		}

		$defaults = array(
			'menu_title'            => __( 'Showcase', 'wp-shp-browser' ),
			'page_title'            => $this->get_page_title(),
			'page_slug'             => $this->page_slug,
			'action_link'           => false,
			'admin_action_priority' => 99,
		);
		$this->args = wp_parse_args( $this->args, $defaults );

		$this->args['menu_title'] .= $nag;
	}

	/**
	 * Setup the browser tabs.
	 */
	protected function setup_tabs() {

		// If there's a remote info file give it priority and override any existing parameters.
		if ( $url = $this->browser_args['remote_info'] ) {
			$info = $this->get_remote_info( $url );

			if ( ! empty( $info ) ) {
				$this->browser_args = wp_parse_args( $info, $this->browser_args );
			}

		}

		// Display the 'popular' tab if enabled.
		if ( 'true' == $this->browser_args['show_popular'] ) {

			$this->browser_args['tabs']['popular'] = array(
				'name' => __( 'Popular', 'wp-shp-browser' ),
				'url'  => ''
			);

		}

		// Set the default tab if not already set.
		if ( ! $this->browser_args['default_tab'] ) {
			$default_tab = array_keys( $this->browser_args['tabs'] );
			$default_tab = $default_tab[0];

			$this->browser_args['default_tab'] = $default_tab;
		}

	}

	/**
	 * Set all the transients names used.
	 */
	protected function setup_transients() {

		$this->transients = array(
			'notices' => array(
				'update' => '_wp_sh_plugin_browser_available_updates',
				'notice' => '_wp_sh_plugin_browser_notices',
			),
		);

	}


	// __Output.

	/**
	 * Outputs the content for the requested tab.
	 *
	 * @uses do_action() Calls 'wp_sh_plugin_browser_content_{$tab}'.
	 */
	public function page_content() {

		if ( ! $this->condition() ) {
			return;
		}

		$tab   = empty( $_REQUEST['tab'] ) ? $this->browser_args['default_tab'] : wp_unslash( sanitize_text_field( $_REQUEST['tab'] ) );
		$paged = ! empty( $_REQUEST['paged'] ) ? (int) sanitize_text_field( $_REQUEST['paged'] ) : 1;

		$args = array(
			'tab'  => esc_attr( $tab ),
			'page' => esc_attr( $paged ),
		);
		$args = array_merge( $args, $this->browser_args );

		$table = new BC_Framework_Plugin_Browser_List( $this->page_slug, $this->args['parent'], $args );

		$this->maybe_display_notices();

		// Outputs the tabs, filters and search bar.
		$table->views();

		// Hooked tab contents.
		do_action( "bc_plugin_browser_content_{$this->page_slug}", $table, $tab );

		do_action( "bc_plugin_browser_content_{$this->page_slug}_{$tab}", $table );
	}

	/**
	 *  Outputs the main page title.
	 */
	protected function output_page_title( $page_title = '' ) {
?>
		<h2>
			<?php echo esc_html( $this->browser_args['page_title'] ); ?>

			<?php if ( ! empty( $this->browser_args['header_buttons'] ) && is_array( $this->browser_args['header_buttons'] ) ) foreach( $this->browser_args['header_buttons'] as $tab ): ?>
				<a href="<?php echo esc_url( $tab['url'] ); ?>" class="add-new-h2" target="_blank" rel="nofollow"><?php echo esc_html( $tab['title'] ); ?></a>
			<?php endforeach; ?>
		</h2>
<?php
	}

	/**
	 * Outputs the vendor info.
	 */
	protected function output_vendor_info( $refresh = false ) {

		if ( ! empty( $this->browser_args['vendor'] ) ):

			$vendor = $this->browser_args['vendor'];

			$defaults = array(
				'name'   => '',
				'url'    => '',
				'logo'   => '',
				'avatar' => '',
				'social' => '',
			);
			$vendor = wp_parse_args( $vendor, $defaults );
?>
			<div class="wp-shp-browser-vendor">

				<script>
					jQuery(document).ready(function($) {
						$('.vendor-avatar, .bubble').on( 'hover', function(){
							$('.bubble').toggle();
						});
					});
				</script>

				<div class="bubble">
  					<?php echo __( "Enjoying my plugins? Cool! Here's my complete list. I'm constantly working on new stuff so, follow me on Twitter for news. Cheers!" ); ?>
				</div>

				<?php echo html( 'a', array( 'href' => esc_url( $vendor['url'] ), 'rel' => 'nofollow' ), ( $vendor['avatar'] ? html( 'span class="vendor-avatar"', get_avatar( $vendor['avatar'], 55 ) ) : ( $vendor['logo'] ? '<img class="vendor-logo" src="' . esc_attr( $vendor['logo'] ) . '">' : '' ) ) ); ?>

				<div class="wp-shp-browser-vendor-info">

					<?php if ( ! $vendor['logo'] ): ?>
						<div class="wp-shp-browser-vendor-name"><?php echo html( 'a', array( 'href' => esc_url( $vendor['url'] ), 'rel' => 'nofollow' ),$vendor['name'] ); ?></div>
					<?php endif; ?>

					<?php if ( $vendor['social'] ): ?>
						<div class="wp-shp-browser-social">
							<?php foreach( $vendor['social'] as $social_id => $url ): ?>

								<?php if ( ! $url ) continue; ?>

								<a href="<?php echo esc_url( $url ); ?>" title="<?php echo esc_attr( $this->get_social( $social_id, 'description' ) ); ?>"><div class="dashicons dashicons-<?php echo esc_attr( $social_id ); ?>"></div></a>

							<?php endforeach; ?>
						</div>
					<?php endif; ?>

				</div>
			</div>
<?php
		endif;
	}

	/**
	 * Displays a header banner if provided.
	 */
   public function display_banner() {

		if ( empty( $this->browser_args['banner'] ) ) {
			return;
		}

   		$banner = $this->browser_args['banner'];

   		$defaults = array(
   			'html'    => '',
			'src'     => '',
			'url'     => '',
			'title'   => '',
			'message' => '',
		);
		$banner = wp_parse_args( $banner, $defaults );

		$allowedtags = array(
			'a'    => array( 'href' => array(),'title' => array(), 'target' => array(), 'style' => array() ),
			'abbr' => array( 'title' => array() ),'acronym' => array( 'title' => array() ),
			'code' => array(), 'pre' => array(), 'em' => array(),'strong' => array(),
			'ul'   => array(), 'ol' => array(), 'li' => array(), 'p' => array( 'style' => array() ), 'br' => array(), 'div' => array( 'style' => array() ),
		);

   		if ( $banner['src'] && $banner['url'] ) {
	   		echo html( 'a', array( 'href' => esc_url( $banner['url'] ), 'rel' => 'nofollow' ), html( 'img', array( 'src' => esc_attr( $banner['src'] ), 'title' => esc_attr( $banner['title'] ) ), '&nbsp;' ) );
	   	} elseif( ! empty( $banner['html'] ) ) {
	   		echo wp_kses_post( $banner['html'], $allowedtags );
	   	}

   }

	/**
	 * Display any cached notices that were not already dismissed.
	 */
	protected function maybe_display_notices() {
		$this->display_update_notices();
		$this->display_other_notices();
   }

	/**
	 * Display notices for available plugin updates.
	 */
   	protected function display_update_notices() {
		$updates = BC_Framework_Plugin_Browser_Dismissible_Notice::get( '_wp_sh_plugin_browser_available_updates', '' );

		if ( ! empty( $updates['new'] ) ) {

			$updates['dismissed'] = ! empty( $updates['dismissed'] ) ? $updates['dismissed'] : array();

			$slugs = array_diff_key( $updates['new'], $updates['dismissed'] );

			if ( $slugs ) {
				$slugs = esc_attr( implode( ',', $slugs ) );

				$msg = sprintf( __( 'Found <strong>%d plugin(s)</strong> update(s). Please review your installed plugins below.', 'wp-shp-browser' ), count( $updates['new'] ) );
				self::output_admin_notice( $msg, $slugs );
			}

		}
   	}

	/**
	 * Display any other notices.
	 */
   	protected function display_other_notices() {

		if ( ! empty( $this->browser_args['notice'] ) ) {

			// Generate a unique slug for this notice.
			$notice = 'wp-shpb-notice-' . sanitize_title( $this->browser_args['notice'] );
			$slug   = substr( $notice, 0, 30 );

			wp_product_showcase_dismissible_notice( 'new', '_wp_sh_plugin_browser_notices', $slug, $this->browser_args['notice'] );
		}

		$notices = BC_Framework_Plugin_Browser_Dismissible_Notice::get( '_wp_sh_plugin_browser_notices' );

		if ( $notices ) {

			foreach( $notices as $slug => $notice ) {
				self::output_admin_notice( $notice, $slug );
			}

		}

   	}

	/**
	 * Outputs admin notices.
	 */
	public static function output_admin_notice( $msg, $slug = '', $type = 'update' ) {

		$allowedtags = array(
			'a'    => array( 'href' => array(),'title' => array(), 'target' => array() ),
			'abbr' => array( 'title' => array() ),'acronym' => array( 'title' => array() ),
			'code' => array(), 'pre' => array(), 'em' => array(),'strong' => array(),
			'ul'   => array(), 'ol' => array(), 'li' => array(), 'p' => array(), 'br' => array()
		);

   		$class = 'updated notice wp-shp-browser-notice ' . ( 'notice' !== $slug ? 'is-dismissible' : '' );

		// Sanitize output.
		$slug = esc_attr( $slug );
		$msg   = wp_kses( $msg, $allowedtags );

		if ( $msg && $msg !== $slug ) {
			echo html( "div class='$class fade' data-slugs='$slug' data-type='$type'", html( 'p', $msg ) );
		}
   }

	/**
	 * Check if there are new notices to be displayed.
	 */
	public function has_notices() {

		$notices = array();

   		foreach( $this->transients['notices'] as $transient ) {
	   		$notices = array_merge_recursive( $notices, BC_Framework_Plugin_Browser_Dismissible_Notice::get( $transient ) );
	   	}
   		return ! empty( $notices );
   }

	/**
	 * Convert SimpleXML objects into an array.
	 */
	public static function xml2array( $xmlObject ) {
		$json = json_encode( $xmlObject );
		return json_decode( $json, TRUE );
	}


	// __Getters.

	/**
	 * Retrieves the browser page title.
	 */
	public function get_page_title() {

		ob_start();

		$this->output_page_title();
		$this->output_vendor_info();

		return ob_get_clean();
	}

	/**
	 * Retrieves plugin browser parameters from a remote XML file.
	 */
	function get_remote_info( $url, $refresh = false ) {

		if ( empty( $_GET['page'] ) || $this->page_slug !== $_GET['page'] ) {
			return;
		}

		if ( $refresh || ( $info = get_transient( '_wp_sh_plugin_browser-list-info' ) ) === false ) {

			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) {
				return;
			}

			$xml = simplexml_load_string( $response['body'], 'SimpleXMLElement', LIBXML_NOCDATA );

			$info = $xml->info;

			if ( empty( $info ) ) {
				return;
			}

			$info = self::xml2array( $info );

			set_transient( '_wp_sh_plugin_browser-list-info', $info, WEEK_IN_SECONDS );
		}
		return $info;
	}

   /**
	* Retrieve meta for social networks.
	*/
   protected function get_social( $social_id = '', $part = '' ) {

	   $social = array(
		   'wordpress' => array(
				'name'        => __( 'WordPress', 'wp-shp-browser' ),
				'description' => __( 'WordPress Profile', 'wp-shp-browser' ),
		   ),
		   'twitter' => array(
				'name'        => __( 'Twitter', 'wp-shp-browser' ),
				'description' => __( 'Follow me on Twitter', 'wp-shp-browser' ),
		   ),
		   'email-alt' => array(
				'name'        => __( 'Email', 'wp-shp-browser' ),
				'description' => __( 'Contact Me', 'wp-shp-browser' ),
		   ),
	   );

	   if ( empty( $social_id ) || empty( $social[ $social_id ] ) ) {
		   return $social;
	   } elseif( ! empty( $social[ $social_id ][ $part ] ) ) {
		   return $social[ $social_id ][ $part ];
	   } else {
		   return $social[ $social_id ];
	   }

   }

	// __Hooks Callbacks.


	/**
	 * Dismisses a given notice through a ajax call.
	 */
	public static function dismiss_notice() {

		if ( ! wp_verify_nonce( $_POST['ajax_nonce'], 'wp_sh_plugin_browser_nonce' ) ) {
			die(0);
		}

		// User selected 'none' on the template list dropdown.
		if ( empty( $_POST['slugs'] ) || empty( $_POST['type'] ) ) {

			die(0);

		} else {

			$transient_types = array(
				'update' => '_wp_sh_plugin_browser_available_updates',
				'notice' => '_wp_sh_plugin_browser_notices',
			);

			if ( empty( $transient_types[ $_POST['type'] ] ) ) {
				die(0);
			}

			$slugs = sanitize_text_field( $_POST['slugs'] );

			foreach( $transient_types as $type => $transient ) {

				foreach( (array) $slugs as $slug ) {
					wp_product_showcase_dismissible_notice( 'dismiss', $transient, $slug );
				}

			}

		}
		die(1);
   }

	/**
	 * Outputs the plugin browser.
	 *
	 * @param object $table A 'WP_List_Table' object.
	 */
	public function display_plugins_browser( $table ) {

		if ( $table->screen->id !== $this->pagehook ) {
			return;
		}

		if ( ! $table->items ) {
			$table->no_items();
			return;
		}

?>
		<br class="clear" />
		<form id="plugin-filter" action="" method="post">
			<?php $table->display(); ?>
		</form>
<?php

		do_action( "bc_plugin_browser_list_after_table_{$this->pagehook}" );
	}

	/**
	 * Adds the 'paged' query arg to the URL if present on the '$_POST' object.
	 */
	public function add_pagination() {

		if ( ! $this->condition() ) {
			return;
		}

		if ( ! empty( $_REQUEST['_wp_http_referer'] ) ) { // Input var okay.

			if( ! empty( $_SERVER['REQUEST_URI'] ) ) {
				$location = remove_query_arg( '_wp_http_referer', sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );
			}

			if ( ! empty( $_REQUEST['paged'] ) ) {
				$location = add_query_arg( 'paged', (int) sanitize_text_field( wp_unslash( $_REQUEST['paged'] ) ), $location );
			}

			$location = esc_url_raw( $location );

			wp_redirect( $location );
			exit;
		}

	}

	/**
	 * Clears any previously cached content to refresh data.
	 */
	public function clear_cache() {
		global $wpdb;

		return $wpdb->get_results( $wpdb->prepare( "DELETE FROM $wpdb->options WHERE option_name LIKE  '%%%s%%' ", '_wp_sh_plugin_browser' ) );
	}

	/**
	 * Retrieves the plugin list without generating all the fancy visual stuff.
	 */
	public function get_plugins( $refresh = false ) {
		$args = array(
			'tab'  => esc_attr( $this->browser_args['default_tab'] ),
			'page' => 1,
		);
		$args = array_merge( $args, $this->browser_args );

		$list = new BC_Framework_Plugin_Browser_List( $this->page_slug, null, $args );

		return $list->items;
	}

}
