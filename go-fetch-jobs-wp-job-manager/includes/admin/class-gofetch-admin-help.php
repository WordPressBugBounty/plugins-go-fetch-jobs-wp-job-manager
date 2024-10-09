<?php
/**
 * Provides and outputs the help page.
 *
 * @package GoFetch/Admin/Help
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings Admin class.
 */
class GoFetch_Admin_Help extends BC_Framework_Admin_page {

	/**
	 * Setup the Import page.
	 */
	public function setup() {

		$this->args = array(
			'page_title'    => __( 'Get Help', 'gofetch-wpjm' ),
			'page_slug'     => GoFetch_Jobs()->slug . '-help',
			'parent'        => GoFetch_Jobs()->slug,
			'menu_title'    => __( 'Help', 'gofetch-wpjm' ),
			'submenu_title' => __( 'Help', 'gofetch-wpjm' ),
			'action_link'   => __( 'Help', 'gofetch-wpjm' ),
			'admin_action_priority' => 11,
		);

	}

	/**
	 * The main page content.
	 */
	public function page_content() {

		echo html( 'p', sprintf( __( 'For detailed documentation please visit the official site <a href="%s" target="_blank">support page</a>.', 'gofetch-wpjm' ), 'https://gofetchjobs.com/support/' ) );

		echo html( 'h1', __( 'Guided Tutorial', 'gofetch-wpjm' ) );

		echo html( 'p', __( 'The first time you use <em>Go Fetch Jobs</em> to import jobs it is highly recommended to follow the guided tutorial. You can enable it on the jobs import page, under <em>Screen Options</em>.
					<br/>The tutorial will guide you through a test import and explain each of the available options.', 'gofetch-wpjm' ) );

		echo html( 'p', __( 'On the <em>Screen Options</em> you can also switch between Basic/Advanced modes:', 'gofetch-wpjm' ) );

		echo '<p><img style="width: 100%;" src="' . esc_url( GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/images/guided-tutorial.png' ) . '"></p>';

		echo html( 'h1', __( 'Options Help', 'gofetch-wpjm' ) );

		echo html( 'p', __( 'You can also get help on a specific feature by clicking the <em>Help</em> button, located on the top right of the import page.', 'gofetch-wpjm' ) );

		echo '<p><img style="width: 100px;" src="' . esc_url( GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/images/help-button.png' ) . '"></p>';

		echo html( 'h1', __( 'Tooltips', 'gofetch-wpjm' ) );

		echo html( 'p', __( 'Additional information for each of the import options is available by hovering the <span class="dashicons dashicons-editor-help" style="color: #afafaf;"></span> icon.', 'gofetch-wpjm' ) );

		echo '<img style="max-width: 500px;" src="' . esc_url( GoFetch_Jobs()->plugin_url() . '/includes/admin/assets/images/tooltips.png' ) . '">';
	}

	/**
	 * Outputs the <table> wrapper.
	 */
	public static function table_wrap( $content ) {
		return html( "table class='form-table goft'", $content );
	}

}

new GoFetch_Admin_Help( GOFT_WPJM_PLUGIN_FILE, $goft_wpjm_options );
