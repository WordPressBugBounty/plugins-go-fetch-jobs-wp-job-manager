<?php
/**
 * Contains all the options used by the plugin.
 */

$options = array(

	// Templates.
	'templates'               => array(),

	// General.
	'admin_jobs_filter'       => false,
	'admin_provider_filter'   => false,
	'admin_jobs_provider_col' => false,
	'admin_jobs_roles'        => array( 'administrator', 'editor', 'contributor', 'author', 'job_lister', 'jobsearch_employer', 'jobsearch_empmnger' ),

	// Screen Options.
	'so_import_type'           => 'advanced',
	'so_hide_provider_details' => true,
	'so_hide_affiliate_params' => true,
	'so_updated'               => false,

	// Importing.
	'keyword_matching'     => 'all',
	'job_date'             => 'feed',
	'company_logo_default' => '',
	'scrape_fields'        => array(),
	'use_cors_proxy'       => false,

	// Smart Assign.
	'smart_assign_create_terms' => false,

	// Custom.
	'image_uploads' => false,

	// Geocode.
	'geocode_api_key'         => '',
	'geocode_rate_limit'      => 50,

	// Jobs.
	'allow_visitors_apply'       => false,
	'read_more_text'             => '[...]',
	'apply_to_job_text'          => __( 'To apply, please visit the following URL:', 'gofetch-wpjm' ),
	'apply_on_click'             => '',
	'apply_to_job_hide_link'     => false,
	'block_search_indexing'      => false,
	'post_status'                => 'publish',
	'jobs_duration'              => 5,
	'independent_listings'       => false,
	'filter_imported_jobs'       => false,
	'filter_imported_jobs_label' => 'External Jobs',
	'filter_site_jobs_label'     => 'Our Jobs',
	'disable_salary_currency'    => false,
	'source_output'              => 'logo',

	// Debugging.
	'debug_log'                => false,

	// Scheduler.
	'scheduler_start_time'     => '09:00',
	'scheduler_interval_sleep' => '5',

	'delete_exired_jobs'       => false,

	// Indeed
	'indeed_publisher_id'          => '',
	'indeed_feature_sponsored'     => '',
	'indeed_feed_default_radius'   => 25,
	'indeed_feed_default_latlong'  => true,
	'indeed_feed_default_co'       => 'us',
	'indeed_feed_default_fromage'  => '',
	'indeed_feed_default_st'       => 'jobsite',
	'indeed_feed_default_chnl'     => '',
	'indeed_feed_default_sort'     => 'relevance',
	'indeed_feed_default_limit'    => 25,
	'indeed_feed_default_jt'       => '',
	'indeed_block_search_indexing' => true,

	// Appcast
	'appcast_api_key'                    => '',
	'appcast_feed_default_sort_by'       => '_score',
	'appcast_feed_default_limit'         => 50,
	'appcase_block_search_indexing'      => true,
	'appcast_feed_default_discipline_id' => '',


	// Carerjet
	'careerjet_publisher_id'                => '',
	'careerjet_feed_default_locale_code'    => 'en_GB',
	'careerjet_feed_default_contracttype'   => '',
	'careerjet_feed_default_contractperiod' => '',
	'careerjet_feed_default_sort'           => 'relevance',
	'careerjet_feed_default_pagesize'       => 50,
	'careerjet_block_search_indexing'       => false,

	// The Muse
	'themuse_api_key'               => '',
	'themuse_feed_default_limit'    => 50,
	'themuse_block_search_indexing' => true,
	'themuse_feed_default_industry' => '',

	// Neuvoo
	'neuvoo_publisher_id'              => '',
	'neuvoo_feature_sponsored'         => '',
	'neuvoo_feed_default_radius'       => 25,
	'neuvoo_feed_default_limit'        => 15,
	'neuvoo_feed_default_cc'           => 'us',
	'neuvoo_feed_default_lang'         => 'en',
	'neuvoo_feed_default_jobdesc'      => true,
	'neuvoo_feed_default_st'           => 'all',
	'neuvoo_feed_default_ct'           => 'all',
	'neuvoo_feed_searchon'             => 'title',
	'neuvoo_feed_default_min_cpcfloor' => '1',
	'neuvoo_feed_default_chnl'         => '',
	'neuvoo_feed_default_chn2'         => '',
	'neuvoo_feed_default_chn3'         => '',
	'neuvoo_feed_default_subid'        => '',
	'neuvoo_feed_default_rdr'          => '',
	'neuvoo_block_search_indexing'     => false,

	// ZipRecruiter
	'ziprecruiter_api_key'                       => '',
	'ziprecruiter_feed_default_jobs_per_page'    => 50,
	'ziprecruiter_feed_default_radius'           => 25,
	'ziprecruiter_feed_default_days_ago'         => '',
	'ziprecruiter_feed_default_refine_by_salary' => '',
	'ziprecruiter_block_search_indexing'         => false,

	// AdView
	'adview_publisher_id'                       => '',
	'adview_feed_default_snippet'               => 'full',
	'adview_feed_default_radius'                => 25,
	'adview_feed_default_salary_from'           => '',
	'adview_feed_default_salary_to'             => '',
	'adview_feed_default_channel'               => '',
	'adview_feed_default_sort'                  => 'relevance',
	'adview_feed_default_limit'                 => 50,
	'adview_feed_default_job_type'              => '',
	'adview_feed_default_mode'                  => 'advanced',
	'adview_feed_default_track_update_interval' => 6,
	'adview_expire_jobs'                        => false,
	'adview_block_search_indexing'              => false,

	// Adzuna
	'adzuna_feed_app_id'                         => '',
	'adzuna_feed_app_key'                        => '',
	'adzuna_feed_default_country'                => 'gb',
	'adzuna_feed_default_page'                   => 1,
	'adzuna_feed_default_results_per_page'       => 20,
	'adzuna_feed_default_where'                  => '',
	'adzuna_feed_default_location0'              => 'UK',
	'adzuna_feed_default_location1'              => '',
	'adzuna_feed_default_location2'              => '',
	'adzuna_feed_default_distance'               => 5,
	'adzuna_feed_default_max_days_old'           => 5,
	'adzuna_feed_default_category'               => '',
	'adzuna_feed_default_sort_by'                => 'relevance',
	'adzuna_feed_default_salary_min'             => '',
	'adzuna_feed_default_salary_max'             => '',
	'adzuna_feed_default_salary_include_unknown' => 1,
	'adzuna_feed_default_company'                => '',
	'adzuna_block_search_indexing'               => false,

	// SourceStack
	'sourcestack_api_key'                    => '',
	'sourcestack_feed_default_limit'         => 1,
	'sourcestack_feed_default_country'       => 'United States',
	'sourcestack_feed_default_language'      => 'en',
	'sourcestack_feed_default_city'          => '',
	'sourcestack_feed_default_region'        => '',
	'sourcestack_feed_default_job_category'  => array(),
	'sourcestack_feed_default_job_type'      => array(),
	'sourcestack_feed_default_job_education' => array(),
	'sourcestack_feed_default_remote'        => '',
	'sourcestack_feed_default_company'       => '',
	'sourcestack_feed_default_limit'         => 50,


	// Jobtome
	'jobtome_feed_pid'                      => '',
	'jobtome_feed_default_country'          => 'us',
	'jobtome_feed_default_channel'          => '',
	'jobtome_feed_default_location'         => 'new york',
	'jobtome_feed_default_results_per_page' => '50',
	'jobtome_block_search_indexing'         => false,

	// Greenhouse
	'greenhouse_board_token'  => '',
	'greenhouse_company_name' => '',
	'greenhouse_company_logo' => '',

	// Recruitee
	'recruitee_api_token'    => '',
	'recruitee_subdomain'     => '',
	'recruitee_company_name' => '',
	'recruitee_company_logo' => '',
	'recruitee_job_statuses' => 'publish',

	// JazzHR
	'jazzhr_api_key'         => '',
	'jazzhr_board_subdomain' => '',
	'jazzhr_board_code'      => '',
	'jazzhr_company_name'    => '',
	'jazzhr_company_logo'    => '',


	// CV-Library
	// see https://www.cv-library.co.uk/developers/job-search-api#api
	'cvlibrary_api_key'                    => '',
	'cvlibrary_affid'                      => '',
	'cvlibrary_agencyref'                  => '',
	'cvlibrary_feed_default_distance'      => 50,
	'cvlibrary_feed_default_salary_type'   => 'annum',
	'cvlibrary_feed_default_salary_min'    => '',
	'cvlibrary_feed_default_salary_max'    => '',
	'cvlibrary_feed_default_jobtype'       => '',
	'cvlibrary_feed_default_jobs_per_page' => 50,
	'cvlibrary_feed_default_sort'          => 'sm',
	'cvlibrary_feed_default_days_posted'   => 3,
	'cvlibrary_feed_default_apply_url'     => 0,
	'cvlibrary_feed_default_industry'      => array( 13 ),
	'cvlibrary_block_search_indexing'      => false,

	// Jobs2Careers
	'jobs2careers_publisher_id'                => '',
	'jobs2careers_publisher_pass'              => '',
	'jobs2careers_feed_default_distance'       => 40,
	'jobs2careers_feed_default_jobtype'        => array( 1, 2, 4 ),
	'jobs2careers_feed_default_full_job_desc'  => 1,
	'jobs2careers_feed_default_mobile_only'    => '',
	'jobs2careers_feed_default_jobs_per_page'  => 50,
	'jobs2careers_feed_default_sort'           => 'r',
	'jobs2careers_feed_default_link'           => 1,
	'jobs2careers_feed_default_industry'       => array( 30000 ),
	'jobs2careers_feed_default_minor_industry' => array( 30010 ),
	'jobs2careers_block_search_indexing'       => false,

	// Jooble
	'jooble_api_key'                  => '',
	'jooble_feed_default_domain'      => 'https://jooble.org',
	'jooble_feed_default_radius'      => 25,
	'jooble_feed_default_limit'       => 50,
	'jooble_feed_default_salary_from' => '',
	'jooble_feed_default_salary_to'   => '',
	'jooble_feed_default_search_mode' => '1',
	'jooble_block_search_indexing'    => false,
	// Jooble affiliate vars
	'jooble_aff_api_key'               => '',
	'jooble_feed_default_job_type'     => array(),
	'jooble_feed_default_max_days_old' => '',
	'jooble_feed_default_remote_only'  => '',
	'jooble_feed_default_salary_rate'  => '3',
	'jooble_feed_default_category'     => array(),

	// JuJu
	'juju_publisher_id'          => '',
	'juju_feed_default_radius'   => 20,
	'juju_feed_default_fromage'  => 90,
	'juju_feed_default_chnl'     => '',
	'juju_feed_default_sort'     => 'relevance',
	'juju_feed_default_limit'    => 20,
	'juju_feed_default_industry' => array( 'accounting' ),
	'juju_block_search_indexing' => false,

	// WhatJobs
	'whatjobs_publisher_id'             => '',
	'whatjobs_feed_default_locale_code' => '100',
	'whatjobs_block_search_indexing'    => false,
	'whatjobs_feed_default_limit'       => 50,

	// Text formatting
	'enable_wpautop'  => true,
	'auto_format_descriptions'            => false,
	'add_empty_line_breaks'               => false,
	'format_descriptions_paragraph_check' => 2,
	'format_descriptions_stops_split'     => 2,

);

// Merge options with the active module.
$options = wp_parse_args( $gofj_module_settings['options'], $options );

$GLOBALS['goft_wpjm_options'] = new scbBcOptions( 'goft_wpjm_options', __FILE__, $options );