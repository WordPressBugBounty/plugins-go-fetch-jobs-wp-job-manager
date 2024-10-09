<?php
/**
 * Importer classes for providers that use an API to provide jobs.
 *
 * @package GoFetch/Admin/ATS Providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The class for the greenhouse Feed API.
 */
class GoFetch_ATS_Feed_Provider_Intro {

	/**
	 * @var The single instance of the class.
	 */
	protected static $_instance = null;

	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Setup the base data for the provider.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	public function init_hooks() {

		if ( gfjwjm_fs()->is_plan('business') ) {
			return;
		}

		add_action( 'tabs_go-fetch-jobs_page_go-fetch-jobs-wpjm-ats', array( $this, 'tabs' ), 15 );
	}

	/**
	 * Init the greenhouse tabs.
	 */
	public function tabs( $all_tabs ) {
		$this->all_tabs = $all_tabs;
		$this->all_tabs->tabs->add( 'providers', __( 'Info', 'gofetch-wpjm' ) );
		$this->tab_content();
	}

	/**
	 * Greenhouse settings tab.
	 */
	protected function tab_content() {

		$this->all_tabs->tab_sections['providers']['settings'] = array(
			'title' => __( 'Upgrade Required', 'gofetch-wpjm' ),
			'fields' => array(
				array(
					'title'  => '',
					'name'   => '_blank',
					'type'   => 'custom',
					'render' => array( $this, 'output' ),
				),
			),
		);
	}

	/**
	 * The output markup.
	 */
	public function output() {

		$providers = array(
			'Greenhouse' => 'https://www.greenhouse.io/',
			'JazzHR'     => 'https://info.jazzhr.com/submit-demo.html?utm_source=partner-WPUno',
			'Recruitee'  => 'https://recruitee.com/',
		);

		$providers_html = '';

		foreach ( $providers as $name => $link ) {
			$providers_html .= html( 'li', html( 'a href="' . esc_url( $link ) . '" rel="noreferrer noopener" target="_blank"', $name ) );
		}

		$output = html( 'p', sprintf( __( 'Upgrade to a <a href="%1$s" rel="noreferrer noopener">Business</a> plan and get support for ATS providers:', 'gofetch-wpjm' ), gfjwjm_fs()->get_upgrade_url() )  );

		$output .= html( 'ul', $providers_html );

		$output .= html( 'p', __( 'More soon.', 'gofetch-wpjm' ) );

		$output .= '
		<style>
		.go-fetch-jobs_page_go-fetch-jobs-wpjm-ats ul { list-style: inside; }
		.go-fetch-jobs_page_go-fetch-jobs-wpjm-ats .button-primary { display: none }
		</style>';

		echo wp_kses_post( $output );
	}

}
new GoFetch_ATS_Feed_Provider_Intro();
