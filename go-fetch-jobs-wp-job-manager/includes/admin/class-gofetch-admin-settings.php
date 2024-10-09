<?php

/**
 * Admin options for the 'Settings' page.
 *
 * @package GoFetchJobs/Admin/Settings
 */
// __Classes.
/**
 * The plugin admin settings class.
 */
class GoFetch_Admin_Settings extends BC_Framework_Tabs_Page {
    /**
     * List of pages considered valid for this class.
     *
     * @var array
     */
    protected $valid_admin_pages = array('go-fetch-jobs-wpjm-settings', 'go-fetch-jobs-wpjm-providers', 'go-fetch-jobs-wpjm-ats');

    /**
     * List of multi-select options.
     */
    protected $multi_select_options = array(
        'admin_jobs_roles',
        'appcast_feed_default_discipline_id',
        'jobs2careers_feed_default_jobtype',
        'jobs2careers_feed_default_industry',
        'jobs2careers_feed_default_minor_industry',
        'cvlibrary_feed_default_industry',
        'juju_feed_default_industry',
        'themuse_feed_default_industry',
        'sourcestack_feed_default_language',
        'sourcestack_feed_default_job_category',
        'sourcestack_feed_default_job_education',
        'sourcestack_feed_default_job_type'
    );

    /**
     * The special CSS class for select2 options.
     */
    protected $multi_select_css_class = 'select2-gofj-multiple';

    /**
     * Constructor.
     */
    function __construct() {
        global $goft_wpjm_options;
        parent::__construct( $goft_wpjm_options, 'gofetch-wpjm' );
        add_action( 'admin_init', array($this, 'init_tooltips'), 9999 );
        add_action( 'trashed_post', array($this, 'clear_cache') );
        add_action( 'deleted_post', array($this, 'clear_cache') );
        add_action( 'admin_head', array($this, 'inline_js'), 20 );
        add_action( 'tabs_go-fetch-jobs_page_go-fetch-jobs-wpjm-settings_form_handler', array($this, 'maybe_clear_cache') );
        add_action( 'tabs_go-fetch-jobs_page_go-fetch-jobs-wpjm-settings', array($this, 'init_tab_advanced'), 199 );
        add_filter(
            'goft_wpjm_settings',
            array($this, 'hide_provider_source_settings'),
            10,
            2
        );
        add_filter(
            'goft_wpjm_settings',
            array($this, 'hide_affiliate_param_settings'),
            10,
            2
        );
        add_filter(
            'set-screen-option',
            array($this, 'set_screen_settings'),
            15,
            3
        );
        $this->multi_select_options = apply_filters( 'goft_multi_select_options', $this->multi_select_options );
    }

    /**
     * Load tooltips for the current screen.
     */
    public function init_tooltips() {
        new BC_Framework_ToolTips(array('toplevel_page_' . GoFetch_Jobs()->slug));
    }

    /**
     * Setup the plugin sub-menu.
     */
    public function setup() {
        $this->args = array(
            'page_title'            => __( 'Settings', 'gofetch-wpjm' ),
            'menu_title'            => __( 'Settings', 'gofetch-wpjm' ),
            'page_slug'             => 'go-fetch-jobs-wpjm-settings',
            'parent'                => GoFetch_Jobs()->slug,
            'admin_action_priority' => 10,
        );
    }

    // __Hook Callbacks.
    /**
     * Initialize tabs.
     */
    protected function init_tabs() {
        $_SERVER['REQUEST_URI'] = esc_url_raw( remove_query_arg( array('firstrun'), $_SERVER['REQUEST_URI'] ) );
        $this->tabs->add( 'importer', __( 'Importer', 'gofetch-wpjm' ) );
        $this->tabs->add( 'jobs', __( 'Jobs', 'gofetch-wpjm' ) );
        $this->tab_importer();
        $this->tab_jobs();
    }

    /**
     * Init the 'Advanced' as the last tab.
     */
    public function init_tab_advanced() {
        $this->tabs->add( 'advanced', __( 'Advanced', 'gofetch-wpjm' ) );
        $this->tab_advanced();
    }

    public function hide_provider_source_settings( $provider_details, $group = '' ) {
        global $goft_wpjm_options;
        if ( 'provider_details' !== $group ) {
            return $provider_details;
        }
        $options = get_user_meta( get_current_user_id(), 'bc_screen_options', true );
        if ( empty( $options['goft-provider-source-settings'] ) ) {
            foreach ( $provider_details as $key => $detail ) {
                $provider_details[$key]['tr'] .= ' tr-always-hide';
            }
            $goft_wpjm_options->so_provider_details = false;
        }
        return $provider_details;
    }

    public function hide_affiliate_param_settings( $affiliate_params, $group = '' ) {
        global $goft_wpjm_options;
        if ( 'monetize' !== $group ) {
            return $affiliate_params;
        }
        $options = get_user_meta( get_current_user_id(), 'bc_screen_options', true );
        if ( empty( $options['goft-affiliate-param-settings'] ) ) {
            foreach ( $affiliate_params as $key => $detail ) {
                $affiliate_params[$key]['tr'] .= ' tr-always-hide';
            }
            $goft_wpjm_options->so_affiliate_params = false;
        }
        return $affiliate_params;
    }

    /**
     * Updates the screens settings option(s).
     */
    public function set_screen_settings( $status, $option, $value ) {
        if ( 'bc_screen_options' !== $option || empty( $_POST['bc_screen_options_screen_id'] ) ) {
            return $value;
        }
        $value = '';
        if ( !empty( $_POST['bc_screen_options'] ) ) {
            $value = scb_recursive_sanitize_text_field( wp_unslash( $_POST['bc_screen_options'] ) );
        }
        return $value;
    }

    /**
     * General settings tab.
     */
    protected function tab_importer() {
        $roles = apply_filters( 'goft_default_roles', array_keys( get_editable_roles() ) );
        $sel_roles = array();
        foreach ( $roles as $role ) {
            $sel_roles[$role] = $role;
        }
        $this->tab_sections['importer']['admin'] = array(
            'title'  => __( 'Admin', 'gofetch-wpjm' ),
            'fields' => array(
                array(
                    'title' => __( 'Imported Jobs Filter', 'gofetch-wpjm' ),
                    'name'  => 'admin_jobs_filter',
                    'type'  => 'checkbox',
                    'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                    'tip'   => __( 'Enable this option to display an additional dropdown to filter jobs by user submitted/imported jobs, on job listings.', 'gofetch-wpjm' ),
                ),
                array(
                    'title' => __( 'Provider Filter', 'gofetch-wpjm' ),
                    'name'  => 'admin_provider_filter',
                    'type'  => 'checkbox',
                    'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                    'tip'   => __( 'Enable this option to display an additional dropdown to filter imported jobs by provider, on job listings.', 'gofetch-wpjm' ),
                ),
                array(
                    'title' => __( 'Provider Column', 'gofetch-wpjm' ),
                    'name'  => 'admin_jobs_provider_col',
                    'type'  => 'checkbox',
                    'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                    'tip'   => __( 'Enable this option to display an additional column with the job provider website, on job listings.', 'gofetch-wpjm' ),
                ),
                array(
                    'title'   => __( 'Author Roles', 'gofetch-wpjm' ),
                    'name'    => 'admin_jobs_roles',
                    'type'    => 'checkbox',
                    'extra'   => array(
                        'multiple' => 'multiple',
                        'class'    => $this->multi_select_css_class,
                    ),
                    'choices' => $sel_roles,
                    'tip'     => __( 'Choose the roles that users must have to be selectable as authors on imported jobs. Defaults to Administrators only, if none is selected.', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'If you have a long list of users on your site or are experiencing memory issues try limiting these roles to avoid including all users.', 'gofetch-wpjm' ),
                    'default' => array('administrator'),
                )
            ),
        );
        $this->tab_sections['importer']['importer'] = array(
            'title'  => __( 'Importer', 'gofetch-wpjm' ),
            'fields' => array(
                array(
                    'title'   => __( 'Keyword Matching', 'gofetch-wpjm' ),
                    'name'    => 'keyword_matching',
                    'type'    => 'select',
                    'choices' => array(
                        'all_fields' => __( 'All Fields', 'gofetch-wpjm' ),
                        'all'        => __( 'Content & Title', 'gofetch-wpjm' ),
                        'content'    => __( 'Content', 'gofetch-wpjm' ),
                        'title'      => __( 'Title', 'gofetch-wpjm' ),
                    ),
                    'tip'     => __( 'Choose whether positive/negative keywords matching should be done against each job content and/or title.', 'gofetch-wpjm' ),
                ),
                array(
                    'title'   => __( 'Job Date', 'gofetch-wpjm' ),
                    'name'    => 'job_date',
                    'type'    => 'select',
                    'choices' => array(
                        'feed'    => __( 'Feed Date', 'gofetch-wpjm' ),
                        'current' => __( 'Current Date', 'gofetch-wpjm' ),
                    ),
                    'tip'     => __( 'If the job date is not manually mapped, it can default to the date on the feed date tag, or the current date.', 'gofetch-wpjm' ),
                ),
                array(
                    'title'   => __( 'Scrape Job Description', 'gofetch-wpjm' ),
                    'name'    => 'scrape_fields',
                    'type'    => 'checkbox',
                    'extra'   => array(
                        'disabled' => 'disabled',
                    ),
                    'choices' => array(
                        'description' => __( 'Yes', 'gofetch-wpjm' ),
                    ),
                    'tip'     => __( 'On feeds, that only provide job excerpts (most of them), the importer can automatically crawl the provider job page, to try to get full job descriptions.', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'This option only works for pre-set providers that allow scraping, it does not work on custom/unknown feeds.', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'Please note that scraping is resource intensive. The import process will be slower, depending on the number of jobs, you import.', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'Leave this option unchecked, if you prefer to enable scraping on each import, individually, or if your imports become too slow.', 'gofetch-wpjm' ),
                ),
                array(
                    'title'  => __( 'Default Company Logo', 'gofetch-wpjm' ),
                    'name'   => 'company_logo_default',
                    'type'   => 'custom',
                    'tip'    => __( 'Default company logo or placeholder, to apply to all jobs without a logo.<br/>You can still choose unique default logos during each import, if you like.', 'gofetch-wpjm' ),
                    'render' => array($this, 'company_logo_uploader'),
                )
            ),
        );
        $this->tab_sections['importer']['smart_assign'] = array(
            'title'  => __( 'Taxonomies', 'gofetch-wpjm' ),
            'fields' => array(array(
                'title' => __( 'Create Terms', 'gofetch-wpjm' ),
                'name'  => 'smart_assign_create_terms',
                'type'  => 'checkbox',
                'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                'tip'   => __( 'Automatically create new terms on taxonomy field mappings.', 'gofetch-wpjm' ),
            )),
        );
        $tip_geocode = sprintf( __( '<a href="%s">Create/get your API key</a> and paste it here to if you want to geocode the <code>\'location\'</code> field, on the import jobs screen.', 'gofetch-wpjm' ), 'https://developers.google.com/maps/documentation/geocoding/start#get-a-key' ) . '<br/><br/>' . __( "Make sure you enable the <code>'Google Maps Javascript API'</code> and the <code>'Google Maps Geocoding API'</code> on your <em>Google Developers Console</em>. Otherwise you'll get javascript warnings and geocoding will fail.", 'gofetch-wpjm' );
        $geocoding_fields = array(array(
            'title' => __( 'Google API Key', 'gofetch-wpjm' ),
            'name'  => 'geocode_api_key',
            'type'  => 'text',
            'desc'  => sprintf( __( 'Read the plugin documentation on <a href="%s" target="_new" rel="nofollow">How to Generate a Google Maps API Key</a>.', 'gofetch-wpjm' ), 'https://gofetchjobs.com/documentation/generate-google-maps-api-key/' ),
            'tip'   => $tip_geocode,
        ));
        $this->tab_sections['importer']['geocode'] = array(
            'title'  => __( 'Geocoding', 'gofetch-wpjm' ),
            'fields' => $geocoding_fields,
        );
    }

    /**
     * Jobs settings tab.
     */
    protected function tab_jobs() {
        global $goft_wpjm_options;
        $this->tab_sections['jobs']['jobs'] = array(
            'title'  => __( 'Imported Jobs', 'gofetch-wpjm' ),
            'fields' => array(
                array(
                    'title'   => __( 'Status', 'gofetch-wpjm' ),
                    'name'    => 'post_status',
                    'type'    => 'select',
                    'choices' => array(
                        'publish' => __( 'Publish', 'gofetch-wpjm' ),
                        'pending' => __( 'Pending', 'gofetch-wpjm' ),
                        'draft'   => __( 'Draft', 'gofetch-wpjm' ),
                    ),
                    'tip'     => __( 'Choose the status to be assigned to each imported job.', 'gofetch-wpjm' ),
                ),
                array(
                    'title' => __( 'Duration', 'gofetch-wpjm' ),
                    'name'  => 'jobs_duration',
                    'type'  => 'text',
                    'extra' => array(
                        'class' => 'small-text',
                    ),
                    'tip'   => __( 'The default job duration for imported jobs.', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'Note that this duration will only be respected if jobs are imported as "Published".', 'gofetch-wpjm' ),
                ),
                array(
                    'title'   => __( 'Source Info', 'gofetch-wpjm' ),
                    'name'    => 'source_output',
                    'type'    => 'select',
                    'choices' => array(
                        'logo'  => __( 'Provider Logo', 'gofetch-wpjm' ),
                        'title' => __( 'Provider Name', 'gofetch-wpjm' ),
                        'none'  => __( 'None (not recommended)', 'gofetch-wpjm' ),
                    ),
                    'tip'     => __( 'Choose how the job provider source is displayed on the single job page (as set under the \'Provider Details\' group).', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'Choosing \'None\' is not recommended. Providers should always be credited.', 'gofetch-wpjm' ),
                ),
                array(
                    'title' => __( 'Allow Visitors to Apply', 'gofetch-wpjm' ),
                    'name'  => 'allow_visitors_apply',
                    'type'  => 'checkbox',
                    'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                    'tip'   => __( 'Check this option to make the import jobs external apply link visible to site visitors. By default, only registered users can see the application link.', 'gofetch-wpjm' ),
                )
            ),
        );
        $this->tab_sections['jobs']['applications'] = array(
            'title'  => __( 'Applications', 'gofetch-wpjm' ),
            'fields' => array(array(
                'title' => __( 'Replace Link with Text', 'gofetch-wpjm' ),
                'name'  => 'apply_to_job_hide_link',
                'type'  => 'checkbox',
                'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                'tip'   => __( 'Check this option to use the text in the previous field, when users click on the \'Apply for Job\' button, instead of the long inline external link <code>e.g: To apply, please click here</code>.', 'gofetch-wpjm' ) . '<br/><br/>' . __( '(default) If this option is unchecked, the text in the previous field will be prepended to the job external link when users click on the \'Apply for Job\' button <code>e.g: To apply, please visit the following URL: https://dummyjobsite.com/job1</code>.', 'gofetch-wpjm' ),
            ), array(
                'title' => __( 'Link Text', 'gofetch-wpjm' ),
                'name'  => 'apply_to_job_text',
                'type'  => 'text',
                'tip'   => __( 'The text displayed when users click on the \'Apply for Job\' button.', 'gofetch-wpjm' ),
            ), array(
                'title'   => __( 'Redirect on Apply Click', 'gofetch-wpjm' ),
                'name'    => 'apply_on_click',
                'type'    => 'select',
                'choices' => array(
                    ''       => __( 'No', 'gofetch-wpjm' ),
                    '_blank' => __( 'Yes. New Tab', 'gofetch-wpjm' ),
                    '_self'  => __( 'Yes. Same Tab', 'gofetch-wpjm' ),
                ),
                'tip'     => __( 'Imediatelly redirect user to the application URL on application clicks, instead of displaying the application URL.', 'gofetch-wpjm' ),
            )),
        );
        $this->tab_sections['jobs']['content'] = array(
            'title'  => __( 'Content', 'gofetch-wpjm' ),
            'fields' => array(
                array(
                    'title' => __( 'Read More Text', 'gofetch-wpjm' ),
                    'name'  => 'read_more_text',
                    'type'  => 'text',
                    'extra' => array(
                        'class' => 'small-text2',
                    ),
                    'tip'   => __( 'The text appended to job description excerpts.', 'gofetch-wpjm' ),
                ),
                array(
                    'title' => __( 'Keep Line Breaks', 'gofetch-wpjm' ),
                    'name'  => 'enable_wpautop',
                    'type'  => 'checkbox',
                    'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                    'tip'   => __( 'Disables WordPress native \'wpautop\' functionality, that removes line breaks < br />. Try unchecking this option if your jobs descriptions look weird.', 'gofetch-wpjm' ),
                ),
                array(
                    'title' => __( 'Format Plain Descriptions', 'gofetch-wpjm' ),
                    'name'  => 'auto_format_descriptions',
                    'type'  => 'checkbox',
                    'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                    'tip'   => __( 'Some feeds contain long plain text job descriptions with very few or no paragraphs at all. Enable this option to have the importer auto format the job descriptions.', 'gofetch-wpjm' ),
                ),
                array(
                    'title' => __( 'Minimum Paragraphs', 'gofetch-wpjm' ),
                    'name'  => 'format_descriptions_paragraph_check',
                    'type'  => 'text',
                    'extra' => array(
                        'class' => 'small-text',
                    ),
                    'desc'  => __( 'Auto format if number of paragraphs is lower then this number.', 'gofetch-wpjm' ),
                    'tip'   => __( 'Enable to auto format the description if it contain less than this number of paragraphs.', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'This option will be ignored if the \'Format Plain Descriptions\' options is disabled.', 'gofetch-wpjm' ),
                ),
                array(
                    'title' => __( 'Paragraphs Rule', 'gofetch-wpjm' ),
                    'name'  => 'format_descriptions_stops_split',
                    'type'  => 'text',
                    'extra' => array(
                        'class' => 'small-text',
                    ),
                    'desc'  => __( 'Full stops.', 'gofetch-wpjm' ),
                    'tip'   => __( 'The importer will generate paragraphs on every n full stops it finds.', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'This option will be ignored if the \'Format Plain Descriptions\' options is disabled.', 'gofetch-wpjm' ),
                ),
                array(
                    'title' => __( 'Paragraphs Line Breaks', 'gofetch-wpjm' ),
                    'name'  => 'add_empty_line_breaks',
                    'type'  => 'checkbox',
                    'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                    'tip'   => __( 'Add an empty line break, below each paragraph break, to create extra space, and make jobs easier to read.', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'This option will be ignored if the \'Format Plain Descriptions\' options is disabled.', 'gofetch-wpjm' ),
                )
            ),
        );
        $this->tab_sections['jobs']['seo'] = array(
            'title'  => __( 'SEO', 'gofetch-wpjm' ),
            'fields' => array(array(
                'title' => __( 'Block Search Indexing', 'gofetch-wpjm' ),
                'name'  => 'block_search_indexing',
                'type'  => 'checkbox',
                'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                'tip'   => __( 'Check this option to block search robots from indexing ALL your imported jobs pages (all providers).', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'This option should be checked if you import jobs from providers that do not allow indexing their jobs.', 'gofetch-wpjm' ),
            )),
        );
    }

    /**
     * Advanced settings tab.
     */
    protected function tab_advanced() {
        $this->tab_sections['advanced']['importer'] = array(
            'title'  => __( 'Importer', 'gofetch-wpjm' ),
            'fields' => array(array(
                'title' => __( 'Use CORS Proxy', 'gofetch-wpjm' ),
                'name'  => 'use_cors_proxy',
                'type'  => 'checkbox',
                'desc'  => __( 'Yes', 'gofetch-wpjm' ),
                'tip'   => __( 'Some RSS feeds that use <em>https://</em> might be considered invalid if your site does not use <em>https://</em>.', 'gofetch-wpjm' ) . '<br/><br/>' . sprintf( __( 'Check this option to let the plugin try to load these feeds through a <a href="%s">CORS proxy</a>.', 'gofetch-wpjm' ), 'https://crossorigin.me/' ) . ' ' . __( 'Leave it unchecked if you don\'t have issues loading RSS feeds.', 'gofetch-wpjm' ),
            )),
        );
        $this->tab_sections['advanced']['log'] = array(
            'title'  => __( 'Logging', 'gofetch-wpjm' ),
            'fields' => array(array(
                'title' => __( 'Debug Log', 'gofetch-wpjm' ),
                'name'  => 'debug_log',
                'type'  => 'checkbox',
                'desc'  => __( 'Enable', 'gofetch-wpjm' ),
                'tip'   => __( 'Enables debug logging. Use it to report any errors to the support team. Keep it disabled, otherwise.', 'gofetch-wpjm' ) . '<br/><br/>' . sprintf( __( '<code>NOTE:</code> You must <a href="%s">enable</a> <code>WP_DEBUG_LOG</code> on your \'wp-config.php\' file.', 'gofetch-wpjm' ), 'https://codex.wordpress.org/Editing_wp-config.php#Configure_Error_Logging' ),
            ), array(
                'title' => __( 'Clear Cache', 'gofetch-wpjm' ),
                'name'  => '_blank',
                'type'  => 'checkbox',
                'desc'  => __( 'Yes. Clear cache on \'Save Changes\'.', 'gofetch-wpjm' ),
                'tip'   => __( 'GOFJ caches some data for faster job imports and to help find duplicates. If you delete recent jobs and immediately try to import them again, the importer will usually refuse if it finds the same jobs in cache.', 'gofetch-wpjm' ) . '<br/><br/>' . __( 'Check this option to clear all cached data when you click \'Save Changes\'.', 'gofetch-wpjm' ),
                'value' => 'clear_cache',
            )),
        );
    }

    /**
     * Clears any cached data when a job is deleted.
     */
    public function clear_cache( $post_id = 0 ) {
        if ( $post_id ) {
            $post_type = get_post_type( $post_id );
        }
        if ( !$post_id || GoFetch_Jobs()->parent_post_type === $post_type ) {
            //
        }
    }

    /**
     * Adds inline JS.
     */
    public function inline_js() {
        global $goft_wpjm_options;
        $js_options = array();
        foreach ( $this->multi_select_options as $option ) {
            $js_options["{$option}"] = array_map( 'strval', (array) $goft_wpjm_options->{$option} );
        }
        $js_options = json_encode( $js_options );
        if ( empty( $_GET['page'] ) || !in_array( $_GET['page'], $this->valid_admin_pages ) ) {
            return;
        }
        ?>
<style>
.<?php 
        echo esc_attr( $this->multi_select_css_class );
        ?> + .select2-container--default .select2-selection--multiple .select2-selection__choice { margin-top: 10px; }
</style>
<script type="text/javascript">
	jQuery(document).ready(function($) {
		var gofjSel2Class = '<?php 
        echo esc_attr( $this->multi_select_css_class );
        ?>';
		var options = <?php 
        echo wp_strip_all_tags( $js_options );
        ?>;

		// Iterate through select2 options set as checbox and convert them to 'select' elements.
		// Dynamically select/unselect checkboxes based on the user selection
		$.each( options, function( input, values ) {

			var $select = $('<select/>')
				.attr( 'class', gofjSel2Class )
				.attr( 'multiple', true )
				.attr( 'data-option', input )

			var $checkbox = $( '[name="' + input + '[]"]' );

			$checkbox.each( function(){
				var val = $(this).val(),
					text = $(this).parent('label').text();
				var sel = ''
				if ( values ) {
					sel = Object.values( values ).indexOf( val ) >= 0;
				}
				var option = new Option( text, val, sel, sel );
				$select.append( option );
			})
			$checkbox.parent('label').hide();

			$checkbox.first().parent('td label').before( $select );
		})

		$('.' + gofjSel2Class).each( function(){
			var option = $(this).attr('name');
			if ( typeof options[ option ] !== 'undefined' ) {
				$(this).find('option[value=' + options[ option ] + ']').attr( 'selected', true );
			}
		})

		$('.' + gofjSel2Class).select2({
			width: '60%',
			placeholder: 'Click to choose ...',
			closeOnSelect: false,
			allowClear: true,
		})

		$('.' + gofjSel2Class).on('select2:select', function (e) {
			var option = $(this).data('option');
			var value = e.params.data.id;
			console.log(option)
			console.log(value)

			$('input[name="' + option + '[]"][value="' + value + '"]').attr( 'checked', true )
		})

		$('.' + gofjSel2Class).on('select2:unselect', function (e) {
			var option = $(this).data('option');
			var value = e.params.data.id;
			console.log(option)
			console.log(value)
			$('input[name="' + option + '[]"][value="' + value + '"]').attr( 'checked', false )
		})
	})
</script>
<?php 
    }

    /**
     * Clears cached data.
     */
    public function maybe_clear_cache( $options ) {
        if ( empty( $_POST['_blank'] ) || 'clear_cache' !== sanitize_text_field( wp_unslash( $_POST['_blank'] ) ) ) {
            return;
        }
        $this->clear_cache();
    }

    /**
     * The admin message.
     */
    public function admin_msg( $msg = '', $class = 'updated' ) {
        if ( empty( $msg ) ) {
            if ( !empty( $_POST['_blank'] ) && 'clear_cache' === sanitize_text_field( wp_unslash( $_POST['_blank'] ) ) ) {
                $msg = __( 'Cache <strong>cleared</strong>!', esc_html( $this->textdomain ) ) . '<br/><br/>';
            }
            $msg .= __( 'Settings <strong>saved</strong>.', esc_html( $this->textdomain ) );
        }
        echo scb_admin_notice( $msg, $class );
    }

    /**
     * Wraper for 'get_users()' that retrieve a list of users based on user selected roles.
     */
    public static function get_users( $args = array() ) {
        global $goft_wpjm_options;
        if ( !empty( $goft_wpjm_options->admin_jobs_roles ) ) {
            $args['role__in'] = $goft_wpjm_options->admin_jobs_roles;
        }
        return get_users( $args );
    }

    /**
     * Retrieves the key of a given opton name from a list of options.
     */
    public static function get_option_key( $options, $name ) {
        $key = false;
        foreach ( $options as $key => $option ) {
            if ( !empty( $option['name'] ) && $name === $option['name'] ) {
                break;
            }
        }
        return $key;
    }

    /**
     * Renders the provider logo uploader field.
     */
    public function logo_uploader() {
        global $goft_wpjm_options;
        $meta_field = 'meta[' . $goft_wpjm_options->setup_field_company_logo . ']';
        $field = array(
            'name'  => $meta_field,
            'type'  => 'text',
            'extra' => array(
                'class'       => 'goft-company-logo goft-image regular-text',
                'placeholder' => 'e.g: google.png',
                'section'     => 'meta',
            ),
            'tip'   => __( 'Company logo for the jobs being imported', 'gofetch-wpjm' ),
            'value' => ( !empty( $_POST[$meta_field] ) ? sanitize_text_field( $_POST[$meta_field] ) : '' ),
            'desc'  => html( 'input', array(
                'type'  => 'button',
                'name'  => 'upload_company_logo',
                'class' => 'goft-company-logo goft-upload button-secondary',
                'value' => __( 'Browse...', 'gofetch-wpjm' ),
            ) ),
        );
        return $this->image_uploader( $field, 'goft-company-logo' );
    }

    /**
     * Custom admin field for the company logo.
     */
    public function company_logo_uploader() {
        global $goft_wpjm_options;
        $image_id = $goft_wpjm_options->company_logo_default;
        $unique_id = sprintf( 'gofj-upl-id-%s', wp_unique_id() );
        if ( $image = wp_get_attachment_image_src( $image_id ) ) {
            $field = '<a href="#" class="gofj-upl"><img class="company-logo-default" src="' . esc_url( $image[0] ) . '" />
				<p class="gofj-rmv"><a href="#">Remove</a></p>
				<input type="hidden" class="' . esc_attr( $unique_id ) . '" name="company_logo_default" value="' . intval( $image_id ) . '" />';
        } else {
            $field = '<a href="#" class="gofj-upl">Upload ...</a>
				<p style="display:none" class="gofj-rmv"><a href="#">Remove</a></p>
				<input type="hidden" class="' . esc_attr( $unique_id ) . '" name="company_logo_default" />';
        }
        $field = html( 'span class="gofj-upl-container"', $field );
        $field .= GoFetch_Helper::image_uploader_js();
        return $field;
    }

}

$GLOBALS['goft_wpjm']['settings'] = new GoFetch_Admin_Settings();