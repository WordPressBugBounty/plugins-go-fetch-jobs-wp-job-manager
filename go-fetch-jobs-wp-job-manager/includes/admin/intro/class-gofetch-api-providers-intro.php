<?php
/**
 * Importer classes for providers that use an API to provide jobs.
 *
 * @package GoFetch/Admin/API Providers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * The class for the greenhouse Feed API.
 */
class GoFetch_API_Feed_Provider_Intro {

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

		if ( gfjwjm_fs()->is_plan('professional') ) {
			return;
		}

		add_action( 'tabs_go-fetch-jobs_page_go-fetch-jobs-wpjm-providers', array( $this, 'tabs' ), 15 );
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
			'Adzuna'      => 'https://www.adzuna.co.uk',
			'Sourcestack' => 'https://sourcestack.co/',
			'Appcast'     => 'http://www.appcast.io',
			'Careerjet'   => 'http://www.careerjet.com',
			'CV-Library'  => 'https://www.cv-library.co.uk',
			'Talroo'      => 'https://www.talroo.com',
			'Jooble'      => 'https://jooble.org',
			'Jobtome'     => 'https://ads.jobtome.com',
			'Juju'        => 'https://www.juju.com',
			'TheMuse'     => 'https://www.themuse.com',
			'Talent'      => 'https://talent.com',
			'WhatJobs'    => 'https://publisher.whatjobs.com',
		);

		$providers_html = '';

		foreach ( $providers as $name => $link ) {
			$providers_html .= html( 'li', html( 'a href="' . esc_url( $link ) . '" rel="noreferrer noopener" target="_blank"', $name ) );
		}

		$output = html( 'p', sprintf( __( 'Upgrade to a <a href="%1$s" rel="noreferrer noopener">Professional</a> or <a href="%1$s" rel="noreferrer noopener">Business</a> plan and get support for API providers:', 'gofetch-wpjm' ), gfjwjm_fs()->get_upgrade_url() ) ) ;

		$output .= html( 'ul', $providers_html );

		$output .= html( 'p', __( 'More soon.', 'gofetch-wpjm' ) );

		$output .= html( 'p', __( '<strong>Note:</strong> You need to apply as a publisher on the respective provider site, to be able to use their API\'s.', 'gofetch-wpjm' ) );

		$output .= '
		<style>
		.go-fetch-jobs_page_go-fetch-jobs-wpjm-providers ul { list-style: inside; }
		.go-fetch-jobs_page_go-fetch-jobs-wpjm-providers .button-primary { display: none }
		</style>';

		echo wp_kses_post( $output );
	}

}
new GoFetch_API_Feed_Provider_Intro();
