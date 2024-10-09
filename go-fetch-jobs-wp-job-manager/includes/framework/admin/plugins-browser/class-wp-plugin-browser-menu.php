<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for the menu page.
 *
 * Streamlined version of Scribu's scbFramework scbAdminPage class.
 */
abstract class BC_Framework_Plugin_Browser_Menu_Page {

	/** Page args
	 * $page_title string (mandatory)
	 * $parent (string)  (default: options-general.php)
	 * $capability (string)  (default: 'manage_options')
	 * $menu_title (string)  (default: $page_title)
	 * $submenu_title (string)  (default: $menu_title)
	 * $page_slug (string)  (default: sanitized $page_title)
	 * $toplevel (string)  If not empty, will create a new top level menu (for expected values see http://codex.wordpress.org/Administration_Menus#Using_add_submenu_page)
	 * - $icon_url (string)  URL to an icon for the top level menu
	 * - $position (int)  Position of the toplevel menu (caution!)
	 * $screen_icon (string)  The icon type to use in the screen header
	 * $nonce string  (default: $page_slug)
	 * $action_link (string|bool)  Text of the action link on the Plugins page (default: 'Settings')
	 * $admin_action_priority int  The priority that the admin_menu action should be executed at (default: 10)
	 */
	protected $args;

	// URL to the current plugin directory.
	// Useful for adding css and js files
	protected $plugin_url;

	protected $pagehook;

	/**
	 * Constructor.
	 *
	 * @param string|bool $file (optional)
	 *
	 * @return void
	 */
	public function __construct( $args, $file = false ) {

		$this->setup();
		$this->check_args();

		add_action( 'admin_menu', array( $this, 'page_init' ), $this->args['admin_action_priority'] );
		add_filter( 'current_screen', array( $this, '_contextual_help' ) );

		if ( $file ) {
			$this->plugin_url = plugin_dir_url( $file );

			if ( $this->args['action_link'] ) {
				add_filter( 'plugin_action_links_' . plugin_basename( $file ), array( $this, '_action_link' ) );
			}
		}
	}

	/**
	 * This is where all the page args can be set.
	 *
	 * @return void
	 */
	protected function setup() { }

	/**
	 * Called when the page is loaded, but before any rendering.
	 * Useful for calling $screen->add_help_tab() etc.
	 *
	 * @return void
	 */
	public function page_loaded() { }

	/**
	 * This is where the css and js go.
	 * Both wp_enqueue_*() and inline code can be added.
	 *
	 * @return void
	 */
	public function page_head() { }

	/**
	 * This is where the contextual help goes.
	 *
	 * @return string
	 */
	protected function page_help() { }

	/**
	 * Registers a page.
	 *
	 * @return void
	 */
	public function page_init() {

		if ( ! $this->args['toplevel'] ) {
			$this->pagehook = add_submenu_page(
				$this->args['parent'],
				$this->args['page_title'],
				$this->args['menu_title'],
				$this->args['capability'],
				$this->args['page_slug'],
				array( $this, '_page_content_hook' )
			);
		} else {
			$func = 'add_' . $this->args['toplevel'] . '_page';
			$this->pagehook = $func(
				$this->args['page_title'],
				$this->args['menu_title'],
				$this->args['capability'],
				$this->args['page_slug'],
				null,
				$this->args['icon_url'],
				$this->args['position']
			);

			add_submenu_page(
				$this->args['page_slug'],
				$this->args['page_title'],
				$this->args['submenu_title'],
				$this->args['capability'],
				$this->args['page_slug'],
				array( $this, '_page_content_hook' )
			);
		}

		if ( ! $this->pagehook ) {
			return;
		}

		add_action( 'load-' . $this->pagehook, array( $this, 'page_loaded' ) );

		add_action( 'admin_print_styles-' . $this->pagehook, array( $this, 'page_head' ) );
	}

	/**
	 * Displays page content.
	 *
	 * @return void
	 */
	public function _page_content_hook() {
		$this->page_header();
		$this->page_content();
		$this->page_footer();
	}

	/**
	 * A generic page header.
	 *
	 * @return void
	 */
	protected function page_header() {
		echo "<div class='wrap'>\n";
		echo html( 'h2', $this->args['page_title'] );
	}

	/**
	 * A generic page footer.
	 *
	 * @return void
	 */
	protected function page_footer() {
		echo "</div>\n";
	}

	/**
	 * Adds contextual help.
	 *
	 * @param string        $help
	 * @param string|object $screen
	 *
	 * @return string
	 */
	public function _contextual_help() {
		$screen = get_current_screen();
		if ( is_object( $screen ) ) {
			$screen_id = $screen->id;
		}

		$actual_help = $this->page_help();

		if ( $screen_id == $this->pagehook && $actual_help ) {
			$screen->add_help_tab( $actual_help );
		}
	}

	/**
	 * Checks page args.
	 *
	 * @return void
	 */
	private function check_args() {
		if ( empty( $this->args['page_title'] ) ) {
			trigger_error( 'Page title cannot be empty', E_USER_WARNING );
		}

		$this->args = wp_parse_args( $this->args, array(
			'toplevel'              => '',
			'position'              => null,
			'icon_url'              => '',
			'screen_icon'           => '',
			'parent'                => 'options-general.php',
			'capability'            => 'manage_options',
			'menu_title'            => $this->args['page_title'],
			'page_slug'             => '',
			'nonce'                 => '',
			'action_link'           => __( 'Settings', 'wp-shp-browser' ),
			'admin_action_priority' => 10,
		) );

		if ( empty( $this->args['submenu_title'] ) ) {
			$this->args['submenu_title'] = $this->args['menu_title'];
		}

		if ( empty( $this->args['page_slug'] ) ) {
			$this->args['page_slug'] = sanitize_title_with_dashes( $this->args['menu_title'] );
		}

	}

	/**
	 * Adds an action link.
	 *
	 * @param array $links
	 *
	 * @return array
	 */
	public function _action_link( $links ) {
		$url = add_query_arg( 'page', $this->args['page_slug'], admin_url( $this->args['parent'] ) );

		$links[] = html_link( $url, $this->args['action_link'] );
	}

}


// __Helpers.


if ( ! function_exists('html') ) {
/**
 * Generate an HTML tag. Attributes are escaped. Content is NOT escaped.
 *
 * @param string $tag
 *
 * @return string
 */
function html( $tag ) {
	static $SELF_CLOSING_TAGS = array( 'area', 'base', 'basefont', 'br', 'hr', 'input', 'img', 'link', 'meta' );

	$args = func_get_args();

	$tag = array_shift( $args );

	if ( is_array( $args[0] ) ) {
		$closing = $tag;
		$attributes = array_shift( $args );
		foreach ( $attributes as $key => $value ) {
			if ( false === $value ) {
				continue;
			}

			if ( true === $value ) {
				$value = $key;
			}

			$tag .= ' ' . $key . '="' . esc_attr( $value ) . '"';
		}
	} else {
		list( $closing ) = explode( ' ', $tag, 2 );
	}

	if ( in_array( $closing, $SELF_CLOSING_TAGS ) ) {
		return "<{$tag} />";
	}

	$content = implode( '', $args );

	return wp_kses_post( "<{$tag}>{$content}</{$closing}>" );
}
}
