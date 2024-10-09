<?php
/**
 * Specific frontend code for WP Job Manager.
 *
 * @package GoFetch/WPJM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $gofetch_wpjm_frontend;

require_once 'class-gofetch-wpjm-shortcode.php';

class GoFetch_WPJM_Frontend {

	public function __construct() {


		add_filter( 'the_job_location', array( $this, 'maybe_override_location' ), 20, 2 );

		if ( is_admin() ) {
			return;
		}

		add_action( 'goft_wpjm_single_goft_job', array( $this, 'single_job_page_hooks' ) );
		add_filter( 'goft_wpjm_single_goft_job', array( $this, 'maybe_override_wpjm_applications' ), 10 );
		add_action( 'goft_wpjm_single_goft_job', array( $this, 'maybe_override_application_details_url' ), 11 );
		add_filter( 'goft_wpjm_override_application_details', array( $this, 'override_application_details_url' ) );
		add_filter( 'jobify_company_logo_url', array( $this, 'get_logo_from_meta_for_jobify' ) );
	}

	/**
	 * Actions that should run on the single job page.
	 */
	public function single_job_page_hooks( $post ) {
		add_action( 'wp', array( $this, 'override_application_details_url' ), 25 );

		remove_filter( 'the_content', array( 'GoFetch_Frontend', 'goft_the_job_description' ), 50 );
		add_filter( 'the_job_description', array( 'GoFetch_Frontend', 'goft_the_job_description' ), 50 );
		add_filter( 'the_job_application_method', array( $this, 'maybe_forbid_apply' ), 10, 2 );

		add_filter( 'job_manager_locate_template', array( $this, 'force_hide_apply_with_resume' ), 10, 3 );

		add_filter( 'the_job_salary_message', array( $this, 'maybe_disable_salary_currency_unit' ), 10, 2 );
	}

	/**
	 * Make sure GOFJ location is given priority when location related plugins are active.
	 */
	public function maybe_override_location( $location, $post ) {
		global $goft_wpjm_options;

		if ( ! class_exists( 'Astoundify_Job_Manager_Regions' ) ) {
			return $location;
		}

		if ( ! ( get_post_meta( $post->ID, '_goft_wpjm_is_external', true ) ) ) {
			return $location;
		}

		$location = get_post_meta( $post->ID, $goft_wpjm_options->setup_field_location, true );

		return $location;
	}

	/**
	 * Hide the apply button if the user disables it.
	 */
	public function maybe_forbid_apply( $method, $post ) {
		global $goft_wpjm_options;

		if ( ! $goft_wpjm_options->allow_visitors_apply && ! is_user_logged_in() ) {
			return false;
		}

		return $method;
	}

	/**
	 * Force hide the apply with resume template on imported jobs for any add-ons.
	 */
	public function force_hide_apply_with_resume( $template, $template_name, $template_path ) {
		if ( 'apply-with-resume.php' === $template_name ) {
			return false;
		}
		return $template;
	}

	/**
	 * Disable WPJM currency/unit.
	 */
	public function maybe_disable_salary_currency_unit( $job_salary, $post ) {
		if ( ! function_exists('get_the_job_salary') ) {
			return $job_salary;
		}
		$post = get_post( $post );
		$job_salary = get_the_job_salary( $post );
		return $job_salary;
	}



	/**
	 * Overrides the 'WPJM Applications' add-on for imported jobs.
	 */
	public function maybe_override_wpjm_applications() {
		global $job_manager, $wp_filter, $post;

		if ( ! class_exists( 'WP_Job_Manager_Applications_Apply' ) && ! class_exists( 'WP_Resume_Manager_Apply' ) ) {
			return;
		}

		$application = get_the_job_application_method( $post );

		// Don't override if the application is done through email.
		if ( ! empty( $application->type ) && 'email' === $application->type ) {
			return;
		}

		// Get the instance for the current application.
		if ( ! empty( $wp_filter['job_manager_application_details_url']->callbacks ) ) {
			$this->remove_applications_action( 'WP_Job_Manager_Applications_Apply', 'application_form', 20 );
		}
		add_action( 'job_manager_application_details_url', array( $job_manager->post_types, 'application_details_url' ) );
	}

	/**
	 * Override the default applications URL markup with a custom one, if requested.
	 */
	public function maybe_override_application_details_url() {
		global $job_manager, $wp_filter, $post;

		$application = get_the_job_application_method( $post );

		// Don't override if the application is done through email.
		if ( ! empty( $application->type ) && 'email' === $application->type ) {
			return;
		}

		if ( ! apply_filters( 'goft_wpjm_override_application_details', false ) ) {
			return;
		}

		if ( ! empty( $wp_filter['job_manager_application_details_url']->callbacks ) ) {
			$this->remove_applications_action( 'WP_Job_Manager_Post_Types' );
		}

		return true;
	}

	/**
	 * Helper function to remove job applications action hooks.
	 */
	protected function remove_applications_action( $class_name, $action = 'application_details_url', $priority = 10 ) {
		global $wp_filter;

		$callbacks = $wp_filter['job_manager_application_details_url']->callbacks;

		foreach ( $callbacks as $callback ) {
			$object   = wp_list_pluck( array_values( $callback ), 'function' );
			$instance = array_pop( $object );
			if ( is_a( $instance[0], $class_name ) ) {
				remove_action( 'job_manager_application_details_url', array( $instance[0], $action ), $priority );
			}
		}

	}

	/**
	 * Overrides the default WPJM template to be able to add the extra link attributes.
	 */
	public function override_application_details_url( $override ) {
		// Removes any actions added by other plugins that do not apply to imported jobs.
		remove_all_actions( 'job_manager_application_details_url' );

		add_action( 'job_manager_application_details_url', array( 'GoFetch_Frontend', 'application_details_url' ), 15 );

		return true;
	}

	/**
	 * If using Jobify, get the logo from the post meta, if available.
	 */
	public function get_logo_from_meta_for_jobify( $logo ) {
		global $post;
		if ( ! $post || ! get_post_meta( $post->ID, '_goft_wpjm_is_external', true ) ) {
			return $logo;
		}
		if ( $_logo = get_the_company_logo( $post, 'thumbnail' ) ) {
			$logo = $_logo;
		}
		return $logo;
	}
}

$gofetch_wpjm_frontend = new GoFetch_WPJM_Frontend();
