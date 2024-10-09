<?php

/**
 * Provides the guided tour functionality.
 *
 * @package GoFetchJobs/Admin/Tutorial
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly
}
/**
 * Provides the info to use on the guided tour and help pages.
 */
class GoFetch_Guided_Tutorial extends BC_Framework_Pointers_Tour {
    var $plugin_name;

    public function __construct() {
        $this->plugin_name = 'Go Fetch Jobs';
        $slug = sprintf( 'toplevel_page_%s', GoFetch_Jobs()->slug );
        parent::__construct( $slug, array(
            'version'     => '1.0',
            'prefix'      => 'gofetch-wpjm-tour',
            'text_domain' => 'gofetch-wpjm',
            'help'        => true,
        ) );
    }

    /**
     * The guided tour steps.
     */
    protected function pointers() {
        $pointers['step0'] = array(
            'title'     => html( 'h3', sprintf( __( 'WELCOME TO <em>%s</em>!', 'gofetch-wpjm' ), strtoupper( $this->plugin_name ) ) ),
            'content'   => html( 'p', __( 'This is a quick guided tour through the plugin functionality.', 'gofetch-wpjm' ) ) . html( 'p', __( 'It is <strong>strongly recommended</strong> you follow it to the end, to have a basic understanding on how it works.', 'gofetch-wpjm' ) ) . html( 'p', html( 'strong', __( 'LET\'S START!', 'gofetch-wpjm' ) ) ),
            'anchor_id' => 'h2:first',
            'edge'      => 'top',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step1'] = array(
            'title'     => html( 'h3', sprintf( __( 'INTRODUCTION', 'gofetch-wpjm' ), $this->plugin_name ) ),
            'content'   => html( 'p', __( 'This is the main screen for controlling operations and where the "magic" happens.', 'gofetch-wpjm' ) ) . html( 'p', __( 'Here you can also create automatic import templates for regular manual imports.', 'gofetch-wpjm' ) ) . html( 'p', sprintf( __( 'Templates can also be used on scheduled imports to keep your jobs database healthy with fresh jobs%s.', 'gofetch-wpjm' ), $this->premium_only( 'refer', 'premium' ) ) ) . html( 'p', __( 'There are plenty of options in this screen for granular control over the jobs being imported but if you prefer to keep it simple, just toggle the <em>Basic</em> option in the <em>Screen Options</em> tab on the top right.', 'gofetch-wpjm' ) ) . html( 'p class="hide-in-help-tabs"', html( 'span class="dashicons-before dashicons-info"', '&nbsp;' ) . ' ' . __( 'If you need to revisit the guided tour later or just disable it use the - <em>Screen Options</em> - tab on top of the page. If you need more help click the - <em>Help</em> - tab, also on top of the page.', 'gofetch-wpjm' ) ) . html( 'p', $this->premium_only( 'link', 'premium' ) ),
            'anchor_id' => 'h2:first',
            'edge'      => 'top',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step2'] = array(
            'title'     => html( 'h3', __( 'LOAD TEMPLATES', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'Templates you have previously saved will be displayed on the templates list. Saved templates contain the RSS feed and all the related import settings.', 'gofetch-wpjm' ) ) . html( 'p', __( 'To load a saved template simply choose a template from the list.', 'gofetch-wpjm' ) ) . html( 'p', __( 'Use the <em>Refresh</em> button after you\'ve saved a new template to update the templates list.', 'gofetch-wpjm' ) ) . html( 'p', __( 'To remove a template just click <em>Delete</em>.', 'gofetch-wpjm' ) ),
            'anchor_id' => '.tr-templates span.description',
            'edge'      => 'left',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step3'] = array(
            'title'     => html( 'h3', __( 'JOB PROVIDERS', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'The Job Providers dropdown provides a list of known job sites that provide API\'s or RSS feeds.', 'gofetch-wpjm' ) ) . html( 'p', sprintf( __( 'You\'ll find several pre-set feeds ready to use, and a custom feed builder for some providers%s.', 'gofetch-wpjm' ), $this->premium_only( 'refer', 'premium' ) ) ) . html( 'p', __( 'The list is grouped by category and provides a short description for each of the job providers.', 'gofetch-wpjm' ) ) . html( 'p', __( 'Depending on your version (<em>Free</em>, <em>Premium</em>), this list will contain a different shorter/bigger set of known providers.', 'gofetch-wpjm' ) ) . html( 'p', $this->premium_only( 'link' ) ),
            'anchor_id' => '.tr-providers .select2-container',
            'edge'      => 'left',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step4'] = array(
            'title'     => html( 'h3', __( 'FEED URL - INPUT', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'The feed URL that contains the jobs you want to import goes here.', 'gofetch-wpjm' ) ) . html( 'p', __( 'Pick one from the providers list or use one from your favorite job site, if available.', 'gofetch-wpjm' ) ),
            'anchor_id' => '#rss_feed_import',
            'edge'      => 'bottom',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step4_2'] = array(
            'title'     => html( 'h3', __( 'FEED URL - LOAD', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'After pasting a feed here, click <em>Load</em> to have it scanned and get a sample of the content', 'gofetch-wpjm' ) ) . html( 'p class="hide-in-help-tabs"', __( 'For this tutorial, click <em>Next</em> to automatically load an RSS feed example.', 'gofetch-wpjm' ) ) . html( 'p class="hide-in-help-tabs"', __( 'The tutorial will continue when the RSS feed example finishes loading.', 'gofetch-wpjm' ) ),
            'anchor_id' => '.tr-rss-url .import-feed',
            'edge'      => 'left',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step5'] = array(
            'title'     => html( 'h3', __( 'CONTENT SAMPLE/FIELD MAPPINGS', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'Every time you load an RSS feed, a sample of the feed content will be displayed.', 'gofetch-wpjm' ) ) . html( 'p', __( 'Here you can map each of the feed tags with fields on your database and also see what data is provided for each tag. Some feeds will provide more info then others.', 'gofetch-wpjm' ) ) . html( 'p', __( 'The sample table shows the fields provided by the feed, their respective content and the total jobs it contains.', 'gofetch-wpjm' ) ),
            'anchor_id' => '.tr-sample td:last-of-type',
            'edge'      => 'bottom',
            'align'     => 'left',
            'where'     => array($this->screen_id),
            'bind'      => 'goftj_rss_content_loaded',
        );
        $pointers['step6'] = array(
            'title'     => html( 'h3', __( 'PROVIDER DETAILS', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'Here you can fill in all the information for the current jobs provider.', 'gofetch-wpjm' ) ) . html( 'p', __( 'If you have chosen a job provider from the list this information should be filled automatically, otherwise you need to fill it.', 'gofetch-wpjm' ) ) . html( 'p', __( 'Either way, you can click <em>Edit</em> to change any values.', 'gofetch-wpjm' ) ) . html( 'p', __( 'This information is displayed in each job page below the job description to credit the job provider.', 'gofetch-wpjm' ) ),
            'anchor_id' => '.tr-provider-details',
            'edge'      => 'bottom',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step8'] = array(
            'title'     => html( 'h3', __( 'JOBS SETUP - TERMS', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'In this section you can specify the default terms that should be assigned to the jobs being imported.', 'gofetch-wpjm' ) ) . html( 'p', __( 'Click <em>Edit</em> to change the terms to the ones that best fit the jobs you are importing.', 'gofetch-wpjm' ) ),
            'anchor_id' => '.tr-taxonomies',
            'edge'      => 'bottom',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step10'] = array(
            'title'     => html( 'h3', __( 'JOBS SETUP - DETAILS', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', sprintf( __( 'Here you can further customize the custom fields default data for the jobs being imported, including featuring%s jobs.', 'gofetch-wpjm' ), $this->premium_only( 'refer', 'premium' ) ) ) . html( 'p', __( 'Click <em>Edit</em> to change the default custom fields data.', 'gofetch-wpjm' ) ) . html( 'p', $this->premium_only( 'link', 'premium' ) ),
            'anchor_id' => '.tr-meta',
            'edge'      => 'bottom',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step10_1'] = array(
            'title'     => html( 'h3', __( 'JOBS SETUP - SEARCH LOGOS', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'Check this option to instruct the importer to search for company logos directly inside the RSS feed.', 'gofetch-wpjm' ) ) . html( 'p', __( 'Note that with this option checked the import process will take more time.', 'gofetch-wpjm' ) ) . html( 'p', __( 'Also note that only some job providers have logos directly inside their feeds.', 'gofetch-wpjm' ) ),
            'anchor_id' => '.tr-special-logos-options .description',
            'edge'      => 'left',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step11'] = array(
            'title'     => html( 'h3', __( 'JOBS SETUP - POSTED BY', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'Choose the user that should be assigned to all the jobs being imported.', 'gofetch-wpjm' ) ),
            'anchor_id' => '#select2-job_lister-container',
            'edge'      => 'left',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step12'] = array(
            'title'     => html( 'h3', __( 'FILTER - LIMIT', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'Use this field to specify a limit for the jobs being imported.', 'gofetch-wpjm' ) ),
            'anchor_id' => '.tr-limit input',
            'edge'      => 'left',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step13'] = array(
            'title'     => html( 'h3', __( 'REPLACE JOBS', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'Check this option to replace any previously imported jobs using this exact feed URL.', 'gofetch-wpjm' ) ),
            'anchor_id' => '.tr-replace-jobs span.description',
            'edge'      => 'left',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step13_1'] = array(
            'title'     => html( 'h3', __( 'TEMPLATE NAME', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', sprintf( __( 'If you intend to replicate this import at a later date or use this template in a scheduled import%s, fill in a meaningful template name here, and click <em>Save</em>.', 'gofetch-wpjm' ), $this->premium_only( 'refer', 'premium' ) ) ) . html( 'p', __( 'All import settings (except <em>Posted by</em> and <em>Filters</em>) will be saved in the template.', 'gofetch-wpjm' ) ) . html( 'p', $this->premium_only( 'link', 'premium' ) ),
            'anchor_id' => '.tr-template-name span.description',
            'edge'      => 'left',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['step14'] = array(
            'title'     => html( 'h3', __( 'GO FETCH JOBS!', 'gofetch-wpjm' ) ),
            'content'   => html( 'p', __( 'When you are ready, click this button to start the import process and see the magic happens!', 'gofetch-wpjm' ) ),
            'anchor_id' => '.import-posts',
            'edge'      => 'left',
            'align'     => 'left',
            'where'     => array($this->screen_id),
        );
        $pointers['help'] = array(
            'title'     => html( 'h3', sprintf( __( 'THANK YOU FOR USING <em>%s</em>!', 'gofetch-wpjm' ), strtoupper( $this->plugin_name ) ) ),
            'content'   => html( 'p', __( 'If you need to revisit this guided tour later or need specific help on an option use the - <em>Screen Options</em> - or - <em>Help</em> - tabs.', 'gofetch-wpjm' ) ),
            'anchor_id' => 'h2:first',
            'edge'      => 'top',
            'align'     => 'right',
            'where'     => array($this->screen_id),
        );
        return $pointers;
    }

    /**
     * Custom CSS styles to be added on the page header.
     */
    public function css_styles() {
        ?>
	<style type="text/css">
		.contextual-help-tabs-wrap .hide-in-help-tabs {
			display: none;
		}

		.gofetch-wpjm-tour1_0_help .wp-pointer-arrow {
			left: 150px;
		}
	</style>
<?php 
    }

    /**
     * Helper for outputting premium plan only notes.
     */
    protected function premium_only( $part = 'refer', $plan = '' ) {
        if ( gfjwjm_fs()->can_use_premium_code() ) {
            return '';
        }
        switch ( $plan ) {
            case 'starter':
                $plan_desc = 'Starter';
                break;
            case 'professional':
                $plan_desc = 'Professional';
                break;
            case 'business':
                $plan_desc = 'Business';
                break;
            default:
                $plan_desc = 'Premium';
        }
        if ( 'refer' === $part ) {
            return ' (<span class="dashicons dashicons-lock"></span>)';
        } else {
            return sprintf( __( '(<span class="dashicons dashicons-lock"></span>) <a href="%1$s">%2$s plans only</a>', 'gofetch-wpjm' ), admin_url( 'admin.php?page=' . GoFetch_Jobs()->slug . '-pricing' ), $plan_desc );
        }
    }

}
