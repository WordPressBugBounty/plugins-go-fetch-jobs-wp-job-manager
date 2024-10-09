<?php
/**
 * Module for guided tour using WP Pointers.
 *
 * Based on the work by: Giuseppe Mazzapica (https://gm.zoomlab.it).
 *
 * @package Framework\Pointers-Tour
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Base class for pointer guided tours.
 *
 * Optionally provides screen options to enable/disable tour.
 * Optionally provides help screen.
 */
abstract class BC_Framework_Pointers_Tour {

	/**
	 * The tour version.
	 *
	 * @var string
	 */
	private $version = '1.1';

	/**
	 * The screen ID where the guided tour is displayed.
	 *
	 * @var string
	 */
	protected $screen_id;

	/**
	 * The language text domain.
	 *
	 * @var string
	 */
	protected $text_domain;

	/**
	 * Instance of the guide tour manager.
	 *
	 * @var object
	 */
	private $manager;

	/**
	 * The screen settings option name.
	 *
	 * @var string
	 */
	private $option_key;

	/**
	 * The prefix to use for the 'wp_pointers'.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Initialized the guided tour.
	 */
	public function __construct( $screen_id, $params = array() ) {

		if ( ! $screen_id ) {
			trigger_error( 'Screen ID cannot be empty!' );
			return;
		}

		$this->load_files();
		$this->enqueue_scripts();

		$defaults = array(
			'version'        => '1.0',
			'prefix'         => 'bc_guided_tour',
			'text_domain'    => 'bc-framework',
			'screen_options' => array(
			  'show'       => true,
			  'option_key' => 'guided_tour',
			),
			'help' => false,
		);
		$params = wp_parse_args( $params, $defaults );

		extract( $params );

		$this->version     = $version;
		$this->prefix      = $prefix;
		$this->screen_id   = $screen_id;
		$this->text_domain = $text_domain;

		// Check if the screen options should be displayed.
		if ( ! empty( $screen_options['show'] ) && $screen_options['show'] ) {
			$this->option_key = $screen_id . '_' . $screen_options['option_key'];

			add_filter( 'set_screen_option_bc_screen_options', array( $this, 'set_screen_settings' ), 10, 3 );
			add_filter( 'screen_settings', array( $this, 'screen_settings' ), 10, 3 );
			add_filter( 'screen_settings', array( $this, 'screen_settings_apply' ), 20, 3 );
		}

		// Check if the help screen should be displayed.
		if ( $help ) {
			add_action( 'admin_menu', array( $this, 'init_help_page' ) );
		}

		add_action( 'admin_head', array( $this, 'css_styles' ) );

		$this->manager = BC_Framework_Pointers_Manager::instance( $this->screen_id, $this->pointers(), $this->version, $this->prefix );
	}

	/**
	 * Adds the help page.
	 */
	public function init_help_page() {
		add_action( "load-$this->screen_id", array( $this, 'help_page' ) );
	}

	/**
	 * Setup the help page.
	 */
	public function help_page() {

		$screen = get_current_screen();

		if ( ! is_object( $screen ) || $screen->id !== $this->screen_id ) {
			return;
		}

		$help = $this->help();

		if ( ! empty( $help ) ) {

			foreach ( $help as $tab ) {
				$screen->add_help_tab( $tab );
			}
		}

	}

	/**
	 * Retrieves the custom settings HTML.
	 *
	 * @todo: create abstract class for screen settings.
	 */
	public function screen_settings( $settings, $args ) {
		$return = $settings;

		$screen = get_current_screen();

		if ( $screen->id === $this->screen_id ) {

			$options = get_user_meta( get_current_user_id(), 'bc_screen_options', true );

			$checked = checked( $this->show_guided_tour(), true, false );

			$return .= "
				<input type='hidden' name='wp_screen_options[option]' value='bc_screen_options' />
				<input type='hidden' name='wp_screen_options[value]' value='yes' />
				<input type='hidden' name='bc_screen_options_screen_id' value='" . esc_attr( $this->screen_id ) . "' />

				<fieldset class='screen-options'>
					<legend><span class='dashicons dashicons-sos'></span>" . __( 'Guided Tutorial', $this->text_domain ) . "</legend>
					<div class='metabox-prefs'>
						<div class='bc_guided_tour_custom_fields'>
							<label for='bc_screen_options[" . $this->option_key . "]'>
								<input type='checkbox' " . selected( $checked ) . " value='on' $checked name='bc_screen_options[" . esc_attr( $this->option_key ) . "]' id='" . esc_attr( $this->option_key ) . "' />" . __( 'Enable', $this->text_domain ) . '
								<small>(' . __( 'display the guided tutorial', $this->text_domain ) . ')</small>
							</label>
						</div>
					</div>
				</fieldset>';
		}
		return $return;
	}

	/**
	 * Retrieves the 'apply' button for the custom settings.
	 */
	public function screen_settings_apply( $settings, $args ) {

		$screen = get_current_screen();

		if ( $screen->id === $this->screen_id && $settings ) {
			$button = get_submit_button( __( 'Apply', $this->text_domain ), 'primary', 'screen-options-apply', false );
			$settings .= "<p class='submit'>{$button}</p>";
		}
		return $settings;
	}

	/**
	 * Updates the screens settings option(s).
	 */
	public function set_screen_settings( $status, $option, $value ) {
		$value = '';

		if ( ! empty( $_POST['bc_screen_options'] ) ) {
		   $value = stripslashes_deep( $_POST['bc_screen_options'] );
		}

		if ( ! empty( $value[ $this->option_key ] ) ) {
			$this->manager->restore_pointers();
		} else {
			$this->manager->dismiss_pointers();
		}
		return $value;
	}

	/**
	 * Enqueues the tour scripts.
	 */
	public function start_tour( $page ) {

		$screen = get_current_screen();

		if ( $screen->id !== $this->screen_id ) {
			return;
		}

		$pointers = $this->manager->filter( $page );

 		// Nothing to do if no pointers pass the filter.
		if ( empty( $pointers ) ) {
			return;
		}

		wp_enqueue_style( 'wp-pointer' );

		$ext = ( ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ? '.min' : '' ) . '.js';

		$js_url = plugins_url( 'pointers' . $ext, __FILE__ );

		wp_enqueue_script( 'custom_admin_pointers', $js_url, array( 'wp-pointer' ), $this->version, true );

		// Data to pass to javascript.
		$data = array(
			'next_label'  => __( 'Next', $this->text_domain ),
			'close_label' => __( 'Close', $this->text_domain ),
			'pointers'    => $pointers,
			'class'       => $this->prefix,
		);
		wp_localize_script( 'custom_admin_pointers', 'bc_framework_pointers_tour_l18n', $data );
	}

	/**
	 * Load dependencies.
	 */
	protected function load_files() {
		require_once plugin_dir_path( __FILE__ ) . 'class-pointers-manager-interface.php';
		require_once plugin_dir_path( __FILE__ ) . 'class-pointers-manager.php';
	}

	/**
	 * Enqueue scripts.
	 */
	protected function enqueue_scripts() {
		add_action( 'admin_enqueue_scripts', array( $this, 'start_tour' ), 25 );
	}

	/**
	 * Retrieves the guided tour visibility status.
	 */
	protected function show_guided_tour() {
		$user_id = get_current_user_id();

		$dismissed = explode( ',', (string) get_user_meta( $user_id, 'dismissed_wp_pointers', true ) );

		$pointers = array_keys( $this->manager->get_pointers() );

		$dismissed  = array_intersect( $pointers, $dismissed );
		$active_ids = array_diff( $pointers, $dismissed );

		// Display the guided tour if enable and user hasn't yet dismissed all pointers.
		return ! empty( $active_ids );
	}

	/**
	 * Abstract method for declaring the pointers used for the tour.
	 *
	 * Example:
	 *
	 *  $pointers['step1'] = array(
	 *        'title'     => sprintf( '<h3>%s</h3>', esc_html__( 'Step 1' ) ),
	 *        'content'   => sprintf( '<p>%s</p>', esc_html__( 'Lore ipsum....' ) ),
	 *        'anchor_id' => '#post-preview',
	 *        'edge'      => 'top',
	 *        'align'     => 'right',
	 *        'where'     => array( 'post-new.php', 'post.php' )
	 *   );
	 */
	protected function pointers() {
		return array();
	}

	/**
	 * Abstract method for declaring the info displayed on the help tabs pages.
	 *
	 * Defaults to the same information provided by pointers unless overridden.
	 *
	 * See WP 'add_help_tab()' for more info.
	 */
	protected function help() {
		$tabs = array();

		$pointers = $this->pointers();

		unset( $pointers['help'] );

		foreach ( $pointers as $id => $pointer ) {

			$tabs[] = array(
				'id'      => $id,
				'title'   => wp_strip_all_tags( $pointer['title'] ),
				'content' => $pointer['content'],
			);

		}
		return $tabs;
	}

	/**
	 * Custom CSS styles to be added on the page header.
	 */
	public function css_styles() {
		return;
	}
}
