<?php
/**
 * Specific Pro+ features for WP Job Manager.
 *
 * @package GoFetch/Premium/Pro+/WPJM
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class GoFetch_WPJM_Shortcode {

	protected static $company;

	protected static $country;

	protected static $template;

	/**
	 * Constructor
	 */
	public function __construct() {

		if ( ! class_exists( 'WP_Job_Manager_Shortcodes' ) ) {
			return;
		}

		add_shortcode( 'goft_jobs', array( $this, 'output_jobs' ) );

		add_filter( 'job_manager_output_jobs_defaults', array( $this, 'jobs_defaults' ), 99 );
		add_filter( 'job_manager_output_jobs_args', array( $this, 'jobs_args' ), 99 );
		add_filter( 'job_manager_output_jobs_args', array( $this, 'imported_jobs_args' ) );
		add_filter( 'job_manager_get_listings_args', array( $this, 'jobs_args' ), 99 );
		add_filter( 'job_manager_get_listings_args', array( $this, 'imported_jobs_args' ) );
		add_filter( 'get_job_listings_query_args', array( $this, 'company_support' ), 10, 2 );
		add_filter( 'get_job_listings_query_args', array( $this, 'country_support' ), 10, 2 );
		add_filter( 'get_job_listings_query_args', array( $this, 'template_support' ), 10, 2 );
		add_filter( 'get_job_listings_query_args', array( $this, 'filter_country_support' ), 10, 2 );
		add_filter( 'get_job_listings_query_args', array( $this, 'maybe_skip_imported_jobs_in_listings' ), 11, 2 );
		add_action( 'job_manager_job_filters_end', array( $this, 'job_filters' ), 21 );
	}

	/**
	 * Add support for 'company' default parameter on shortcode.
	 */
	public function jobs_defaults( $defaults ) {
		$defaults['company'] = self::$company;
		$defaults['country'] = self::$country;
		$defaults['template'] = self::$template;
		return $defaults;
	}

	/**
	 * Add support for 'company' arg on shortcode.
	 */
	public function jobs_args( $atts ) {

		$company = self::$company;
		$country = self::$country;
		$template = self::$template;

		if ( defined( 'DOING_AJAX' ) && isset( $_REQUEST['form_data'] ) ) {
			parse_str( sanitize_text_field( $_REQUEST['form_data'] ), $output );
			$output = array_map( 'sanitize_text_field', $output );

			if ( ! empty( $output['search_company'] ) ) {
				$company = $output['search_company'];
			}
			if ( ! empty( $output['search_country'] ) ) {
				$country = $output['search_country'];
			}
			if ( ! empty( $output['search_template'] ) ) {
				$template = $output['search_template'];
			}
		}

		$atts['search_company'] = $company;
		$atts['search_country'] = $country;
		$atts['search_template'] = $template;

		return $atts;
	}

	/**
	 * Wrapper for outputting WPJM's job listings with special parameters that allows filtering the listings output.
	 */
	public function output_jobs( $atts, $content = '' ) {
		add_action( 'job_manager_job_filters_end', function() use ( $atts ) {
			?>
			<?php if ( ! empty( $atts['company'] ) ) : ?>
				<input type="hidden" name="search_company" id="search_company" value="<?php echo esc_attr( $atts['company'] ); ?>" />
			<?php endif; ?>
			<?php if ( ! empty( $atts['country'] ) ) : ?>
				<input type="hidden" name="search_country" id="search_country" value="<?php echo esc_attr( $atts['country'] ); ?>" />
			<?php endif; ?>
			<?php if ( ! empty( $atts['template'] ) ) : ?>
				<input type="hidden" name="search_template" id="search_template" value="<?php echo esc_attr( $atts['template'] ); ?>" />
			<?php endif; ?>
			<?php
		} );

		$wpjm_sc = WP_Job_Manager_Shortcodes::instance();

		$orderby = 'featured';

		if ( ! empty( $atts['orderby'] ) ) {
			$orderby = $atts['orderby'];
		}

		if ( empty( $atts ) ) {
			$atts = array( 'orderby' => '' );
		}

		$atts['orderby'] = sprintf( '%s;goft_jobs', $orderby );

		if ( ! empty( $atts['company'] ) ) {
			self::$company = $atts['company'];
		}

		if ( ! empty( $atts['country'] ) ) {
			self::$country = $atts['country'];
		}

		if ( ! empty( $atts['template'] ) ) {
			self::$template = $atts['template'];
		}

		return $wpjm_sc->output_jobs( $atts );
	}

	/**
	 * Hack the WPJM 'orderby' parameter to be able to identify custom shortcode params.
	 *
	 * Usage: [jobs orderby="goft_jobs"] or [jobs orderby="featured; goft_jobs"]
	 * Uses the semi-colon (;) as delimiter
	 *
	 * @todo: maybe change when WPJM provides the required filters.
	 */
	public function imported_jobs_args( $args ) {
		if ( $sc_args = $this->is_goft_shortcode( $args ) ) {
			$args = $sc_args;
		}
		return $args;
	}

	/**
	 * Checks for a GOFT shortcode.
	 */
	protected function is_goft_shortcode( $args ) {
		if ( ! empty( $args['orderby'] ) ) {
			if ( false !== stripos( $args['orderby'], 'goft_jobs' ) ) {
				$args['goft_jobs_sc'] = 1;
				$args['orderby'] = str_ireplace( 'goft_jobs', '', $args['orderby'] );
				$args['orderby'] = str_replace( ';', '', $args['orderby'] );
				if ( empty( $args['orderby'] ) ) {
					$args['orderby'] = 'featured';
				}
				return $args;
			}
		}
		return false;
	}

	/**
	 * Add support for filtering jobs by company name.
	 */
	public function company_support( $query_args, $args ) {

		if ( empty( $args['search_company'] ) ) {
			return $query_args;
		}

		$company_meta_key = '_company_name';
		$company_search    = [ 'relation' => 'AND' ];
		$company_search[] = [
			'key'     => $company_meta_key,
			'value'   => $args['search_company'],
			'compare' => 'like',
		];
		$query_args['meta_query'][] = $company_search;

		return $query_args;
	}

	/**
	 * Add support for filtering jobs by country.
	 */
	public function country_support( $query_args, $args ) {

		if ( empty( $args['search_country'] ) ) {
			return $query_args;
		}

		$country_meta_key = '_gofj_country_name';
		$country_search   = [ 'relation' => 'AND' ];
		$country_search[] = [
			'key'     => $country_meta_key,
			'value'   => $args['search_country'],
			'compare' => 'like',
		];
		$query_args['meta_query'][] = $country_search;

		return $query_args;
	}

	/**
	 * Add support for filtering jobs by template.
	 */
	public function template_support( $query_args, $args ) {

		if ( empty( $args['search_template'] ) ) {
			return $query_args;
		}

		$template_meta_key = '_goft_template_name';
		$template_search   = [ 'relation' => 'AND' ];
		$template_search[] = [
			'key'     => $template_meta_key,
			'value'   => $args['search_template'],
			'compare' => '=',
		];
		$query_args['meta_query'][] = $template_search;

		return $query_args;
	}


	/**
	 * Include the country when searching.
	 */
	public function filter_country_support( $query_args, $args ) {

		if ( empty( $args['search_location'] ) || ! apply_filters( 'goft_wpjm_filter_include_country', false ) ) {
			return $query_args;
		}

		$query_args['meta_query'][0][] = [
			'key'     => '_gofj_country_name',
			'value'   => $args['search_location'],
			'compare' => 'like',
		];

		return $query_args;
	}

	/**
	 * Maybe skip imported jobs if set by the user.
	 */
	public function maybe_skip_imported_jobs_in_listings( $query_args, $args ) {
		global $goft_wpjm_options;

		$params = array();

		if ( isset( $_REQUEST['form_data'] ) ) {
			parse_str( sanitize_text_field( stripslashes( $_REQUEST['form_data'] ) ), $params );
		}

		if ( ! $goft_wpjm_options->independent_listings && ! $goft_wpjm_options->filter_imported_jobs && empty( $args['goft_jobs_sc'] ) ) {
			return $query_args;
		}

		if ( ( empty( $params['goft_jobs'] ) || 'all' === $params['goft_jobs'] ) && empty( $args['goft_jobs_sc'] ) && ! $goft_wpjm_options->independent_listings ) {
			return $query_args;
		}

		if ( ( ! empty( $params['goft_jobs'] ) && 'external' === $params['goft_jobs'] ) || ! empty( $args['goft_jobs_sc'] ) ) {

			$meta_query = array(
				'relation' => 'AND',
				array(
					'key'     => '_goft_wpjm_is_external',
					'compare' => '=',
					'value'   => 1,
				),
			);

		} else {

			$meta_query = array(
				'relation' => 'AND',
				array(
					'key'     => '_goft_wpjm_is_external',
					'compare' => 'NOT EXISTS',
				),
			);

		}

		if ( ! empty( $query_args['meta_query'] ) ) {
			$query_args['meta_query'] = array_merge( $query_args['meta_query'], $meta_query );
		} else {
			$query_args['meta_query'] = $meta_query;
		}
		return $query_args;
	}

	/**
	 * Allow filtering mixed listings by imported jobs.
	 */
	public function job_filters( $atts ) {
		global $goft_wpjm_options;

		// Always skip on GOFT shortcode.
		if ( $this->is_goft_shortcode( $atts ) ) {
			return;
		}

		// Don't show filter on independent listings.
		if ( $goft_wpjm_options->independent_listings || ! $goft_wpjm_options->filter_imported_jobs ) {
			return;
		}
?>
		<div class="goft_filter" class="goft-jobs-filter-container">
			<select id="goft_jobs" name="goft_jobs" class="goft-jobs-filter" style="margin: 5px;">
				<option value="all"><?php echo __( 'All Jobs', 'goft-wpjm' ); ?></option>
				<option value="external"><?php echo wp_kses_post( $goft_wpjm_options->filter_imported_jobs_label ); ?></option>
				<option value="site"><?php echo wp_kses_post( $goft_wpjm_options->filter_site_jobs_label ); ?></option>
			</select>
			<input type="hidden" id="active_filter_goft_jobs" name="active_filter_goft_jobs" value="1" />
		</div>
		<script>
			jQuery(document).ready(function($) {
				$( '.goft_filter' ).change( function() {
					var target = $( this ).closest( 'div.job_listings' );
					target.triggerHandler( 'update_results', [ 1, false ] );
					job_manager_store_state( target, 1 );
				} )

				var $supports_html5_history = false;
				if ( window.history && window.history.pushState ) {
					$supports_html5_history = true;
				}
				function job_manager_store_state( target, page ) {
					if ( $supports_html5_history ) {
						var form  = target.find( '.job_filters' );
						var data  = $( form ).serialize();
						var index = $( 'div.job_listings' ).index( target );
						window.history.replaceState( { id: 'job_manager_state', page: page, data: data, index: index }, '', location + '#s=1' );
					}
				}
			});
		</script>
<?php
	}

}
$GLOBALS['gofetch_wpjm_shortcode'] = new GoFetch_WPJM_Shortcode();
