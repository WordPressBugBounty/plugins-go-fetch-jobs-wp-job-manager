<?php
/**
 * Provides and outputs the ATS settings page.
 *
 * @package GoFetch/Admin/ATS
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Settings Admin class.
 */
class GoFetch_ATS_Settings extends BC_Framework_Tabs_Page {

	/**
	 * Constructor.
	 */
	function __construct() {
		global $goft_wpjm_options;

		parent::__construct( $goft_wpjm_options, 'gofetch-wpjm' );

	}

	/**
	 * Setup the plugin sub-menu.
	 */
	public function setup() {
		$this->args = array(
			'page_title'            => __( 'ATS Providers', 'gofetch-wpjm' ),
			'menu_title'            => __( 'ATS Providers', 'gofetch-wpjm' ),
			'page_slug'             => 'go-fetch-jobs-wpjm-ats',
			'parent'                => GoFetch_Jobs()->slug,
			'admin_action_priority' => 10,
		);
	}


	// __Hook Callbacks.

	/**
	 * Initialize tabs.
	 */
	protected function init_tabs() {
		?>
		<style>
			.gofj-top-partner {
				color: #6c6a6a;
				font-style: italic;
			}
			.gofj-top-partner-tab .dashicons {
				height: 15px;
			}
			.gofj-top-partner-tab .dashicons::before {
				font-size: 15px;
			}
			sep {
				color: #ccc;
				padding: 0 5px;
				font-style: normal;
			}
			.nav-tab[href*=jazzhr] {
				margin-right: 20px;
			}
		</style>
<?php
	}
}

$GLOBALS['goft_wpjm']['api_ats'] = new GoFetch_ATS_Settings();
