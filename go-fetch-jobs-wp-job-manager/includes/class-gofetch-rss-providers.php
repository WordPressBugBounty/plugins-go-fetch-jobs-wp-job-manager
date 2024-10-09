<?php

/**
 * Sets up the write panels used by the schedules (custom post types).
 *
 * @package GoFetch/Admin/Providers
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly
}
/**
 * Schedules meta boxes base class.
 */
class GoFetch_RSS_Providers {
    /**
     * Retrieves a list of tags that the RSS Feed loader can import.
     *
     * Note: adding more tags trough the filter requires having a related meta field for later storing the data.
     *
     * @see GoFetch_Dynamic_Import::meta_mappings()
     */
    public static function valid_item_tags() {
        $fields = array(
            'location',
            'geolocation',
            'latitude',
            'longitude',
            'company',
            'logo'
        );
        return apply_filters( 'goft_wpjm_providers_valid_item_tags', $fields );
    }

    /**
     * Similar to the 'valid_item_tags()' method but used to retrieve a list of tags found trough regular expressions.
     */
    public static function valid_regexp_tags() {
        $patterns = array(
            'location' => array(
                // e.g: <p>Location: London</p> OR Location : London <br/>
                '/Location.*?:(.*?)<.*?>/is',
                // e.g: <strong>Headquarters:</strong> New York, NY <br />
                '/Headquarters.*?:<.*?>(.*?)<.*?>/is',
                // e.g: <b>Location: </b><br/> San Francisco <br/>
                '/Location.*?<.*?>(.*?)<.*?>/is',
            ),
            'company'  => array(
                // e.g: Advertiser : Google <br/>
                '/Advertiser.*?:(.*?)<.*?>/is',
                // e.g: <b>Company: </b><br/> Google <br/>
                '/Company.*?<.*?>(.*?)<.*?>/is',
                // e.g: <p>Location: London</p> OR Location : London <br/>
                '/Company.*?:(.*?)<.*?>/is',
                // e.g: <b>Posted by: </b> Google </p>
                '/Posted by.*?<.*?>(.*?)<.*?>/is',
            ),
            'salary'   => array(
                // e.g: Salary : £40,000 - £55,000 per year
                '/Salary.*?:(.*?)$/is',
                // e.g: <p>Salary/Rate: 50.000 - 80.000</p>
                '/Salary\\/Rate.*?:(.*?)<.*?>/is',
            ),
        );
        return apply_filters( 'goft_wpjm_providers_valid_regexp_tags', $patterns );
    }

    /**
     * Base strcuture for a provider.
     */
    public static function get_base_provider( $data = array() ) {
        // Set provider data.
        $defaults = array(
            'id'          => 'unknown',
            'title'       => 'unknown',
            'website'     => 'unknown',
            'description' => 'unknown',
            'logo'        => '',
        );
        return wp_parse_args( (array) $data, $defaults );
    }

    /**
     * Retrieves a list of providers and their details.
     *
     * Weight: Higher is better.
     */
    public static function get_providers( $provider = '' ) {
        $providers = array(
            'freelancewritinggigs.com'    => array(
                'website'     => 'https://www.freelancewritinggigs.com/',
                'logo'        => GoFetch_Jobs()->plugin_url() . '/includes/images/logos/logo-freelancewritinggigs.png',
                'description' => 'Freelance Writing Job Board',
                'feed'        => array(
                    'base_url'         => 'https://www.freelancewritinggigs.com/?feed=job_feed',
                    'search_url'       => 'https://www.freelancewritinggigs.com/freelance-writing-job-ads/',
                    'query_args'       => array(
                        'keyword'  => array(
                            'search_keywords' => '',
                        ),
                        'location' => array(
                            'search_location' => '',
                        ),
                    ),
                    'full_description' => true,
                    'default'          => true,
                ),
                'special'     => array(
                    'scrape' => array(
                        'description' => array(
                            'nicename' => __( 'Full Job Description', 'gofetch-wpjm' ),
                            'query'    => '//div[@class="job_description"]',
                        ),
                        'company'     => array(
                            'nicename' => __( 'Company Name', 'gofetch-wpjm' ),
                            'query'    => '//div[@class="company"]//p[contains(@class,"name")]',
                        ),
                        'location'    => array(
                            'nicename' => __( 'Location', 'gofetch-wpjm' ),
                            'query'    => '//li[contains(@class,"location")]',
                        ),
                        'salary'      => array(
                            'nicename' => __( 'Salary', 'gofetch-wpjm' ),
                            'query'    => '//li[contains(@class,"salary")]',
                        ),
                        'logo'        => array(
                            'nicename' => __( 'Company Logo', 'gofetch-wpjm' ),
                            'query'    => '//div[@class="company"]//img[contains(@class,"company_logo")]/@data-ezsrc',
                        ),
                    ),
                ),
                'category'    => __( 'Blogging', 'gofetch-wpjm' ),
                'premium'     => false,
                'weight'      => 7,
            ),
            'jobs.wordpress.net'          => array(
                'website'     => 'https://jobs.wordpress.net/',
                'logo'        => GoFetch_Jobs()->plugin_url() . '/includes/images/logos/logo-wordpress.png',
                'description' => 'WordPress related Job Postings',
                'feed'        => array(
                    'base_url'         => 'https://jobs.wordpress.net/feed/',
                    'search_url'       => 'https://jobs.wordpress.net/?s=',
                    'query_args'       => array(
                        'keyword' => array(
                            's' => '',
                        ),
                    ),
                    'examples'         => array(
                        __( 'Latest Jobs', 'gofetch-wpjm' )                    => 'https://jobs.wordpress.net/feed/',
                        __( 'Latest Design Jobs', 'gofetch-wpjm' )             => 'https://jobs.wordpress.net/job_category/design/feed/',
                        __( 'Latest Plugin Development Jobs', 'gofetch-wpjm' ) => 'https://jobs.wordpress.net/job_category/plugin-development/feed/',
                    ),
                    'full_description' => true,
                ),
                'special'     => array(
                    'scrape' => array(
                        'description' => array(
                            'nicename' => __( 'Full Job Description', 'gofetch-wpjm' ),
                            'query'    => '//div[@class="entry-content"]',
                        ),
                        'company'     => array(
                            'nicename' => __( 'Company Name', 'gofetch-wpjm' ),
                            'query'    => '//div[contains(@class,"job-meta")]//dl[@class="job-company"]//dd',
                        ),
                        'location'    => array(
                            'nicename' => __( 'Location', 'gofetch-wpjm' ),
                            'query'    => '//div[contains(@class,"job-meta")]//dl[@class="job-location"]//dd',
                        ),
                        'salary'      => array(
                            'nicename' => __( 'Salary', 'gofetch-wpjm' ),
                            'query'    => '//div[contains(@class,"job-meta")]//dl[@class="job-budget"]//dd',
                        ),
                    ),
                ),
                'weight'      => 8,
                'premium'     => false,
                'category'    => __( 'WordPress', 'gofetch-wpjm' ),
            ),
            'jobs.theguardian.com'        => array(
                'website'     => 'https://jobs.theguardian.com/',
                'logo'        => GoFetch_Jobs()->plugin_url() . '/includes/images/logos/logo-theguardian.png',
                'description' => 'Great jobs on the Guardian Jobs site',
                'feed'        => array(
                    'base_url'        => 'https://jobs.theguardian.com/jobsrss/',
                    'search_url'      => 'https://jobs.theguardian.com/jobs/',
                    'meta'            => array('logo'),
                    'regexp_mappings' => array(
                        'title'       => array(
                            'company' => '/^(.*?):/is',
                        ),
                        'description' => array(
                            'location' => '/\\.([^.]+)$/is',
                            'salary'   => '/^(.*?):/is',
                        ),
                    ),
                    'query_args'      => array(
                        'keyword'  => array(
                            'keywords' => '',
                        ),
                        'location' => array(
                            'radialtown' => '',
                        ),
                    ),
                    'default'         => true,
                ),
                'special'     => array(
                    'scrape' => array(
                        'description' => array(
                            'nicename' => __( 'Full Job Description', 'gofetch-wpjm' ),
                            'query'    => '//div[contains(@class,"mds-edited-text mds-font-body-copy-bulk")][1]',
                            'exclude'  => '//div[contains(@class,"job-details__social-share")]',
                        ),
                        'company'     => array(
                            'nicename' => __( 'Company Name', 'gofetch-wpjm' ),
                            'query'    => '//dt[contains(@class,"mds-list__key")][contains(text(),"Employer")]/following-sibling::dd[contains(@class,"mds-list__value")][1]',
                        ),
                        'location'    => array(
                            'nicename' => __( 'Location', 'gofetch-wpjm' ),
                            'query'    => '//dt[contains(@class,"mds-list__key")][contains(text(),"Location")]/following-sibling::dd[contains(@class,"mds-list__value")][1]',
                        ),
                        'salary'      => array(
                            'nicename' => __( 'Salary', 'gofetch-wpjm' ),
                            'query'    => '//dt[contains(@class,"mds-list__key")][contains(text(),"Salary")]/following-sibling::dd[contains(@class,"mds-list__value")][1]',
                        ),
                        'logo'        => array(
                            'nicename' => __( 'Company Logo', 'gofetch-wpjm' ),
                            'query'    => '//div[contains(@class,"mds-grid-row")]//img[contains(@class,"logo")][1]/@src',
                        ),
                    ),
                ),
                'weight'      => 9,
                'premium'     => false,
                'category'    => __( 'Generic', 'gofetch-wpjm' ),
            ),
            'expressoemprego.pt'          => array(
                'website'     => 'https://expressoemprego.pt/',
                'logo'        => GoFetch_Jobs()->plugin_url() . '/includes/images/logos/logo-expresso-emprego.png',
                'description' => 'A sua Carreira é o nosso Trabalho',
                'feed'        => array(
                    'base_url'        => 'https://expressoemprego.pt/rss',
                    'search_url'      => 'https://expressoemprego.pt',
                    'meta'            => array('logo'),
                    'regexp_mappings' => array(
                        'company'  => '/^(.*?)\\|/is',
                        'location' => '/\\|(.*?)\\|/is',
                    ),
                    'examples'        => array(
                        __( 'Latest 50 Jobs', 'gofetch-wpjm' )        => 'https://www.expressoemprego.pt/rss/ultimas-ofertas',
                        __( 'Latest Internet Jobs', 'gofetch-wpjm' )  => 'https://expressoemprego.pt/rss/internet',
                        __( 'Latest Jobs in Lisbon', 'gofetch-wpjm' ) => 'https://expressoemprego.pt/rss/lisboa',
                    ),
                ),
                'quality'     => 'low',
                'weight'      => 1,
                'premium'     => false,
                'category'    => __( 'Generic', 'gofetch-wpjm' ),
            ),
            'cargadetrabalhos.net'        => array(
                'website'     => 'https://www.cargadetrabalhos.net/',
                'logo'        => GoFetch_Jobs()->plugin_url() . '/includes/images/logos/logo-carga-trabalhos.jpg',
                'description' => 'Emprego na área da comunicação',
                'feed'        => array(
                    'base_url'         => 'https://www.cargadetrabalhos.net/feed/',
                    'search_url'       => 'https://www.cargadetrabalhos.net',
                    'full_description' => true,
                    'default'          => true,
                ),
                'weight'      => 6,
                'premium'     => false,
                'category'    => __( 'Marketing', 'gofetch-wpjm' ),
            ),
            'jobs.marketinghire.com'      => array(
                'website'     => 'https://jobs.marketinghire.com/',
                'logo'        => GoFetch_Jobs()->plugin_url() . '/includes/images/logos/logo-marketinghire.gif',
                'description' => 'All Marketing jobs',
                'feed'        => array(
                    'base_url'         => 'https://jobs.marketinghire.com/jobs/?display=rss',
                    'search_url'       => 'https://jobs.marketinghire.com/jobs',
                    'meta'             => array('logo'),
                    'regexp_mappings'  => array(
                        'title'       => array(
                            'company' => '/.*\\|(.*)/',
                        ),
                        'description' => array(
                            'location' => '/^(.*?),/is',
                        ),
                    ),
                    'regexp_title'     => '/(.*)\\|/',
                    'query_args'       => array(
                        'keyword'  => array(
                            'keywords' => '',
                        ),
                        'location' => array(
                            'place' => '',
                        ),
                    ),
                    'full_description' => true,
                    'premium'          => false,
                    'default'          => true,
                ),
                'special'     => array(
                    'scrape' => array(
                        'description' => array(
                            'nicename' => __( 'Full Job Description', 'gofetch-wpjm' ),
                            'query'    => '//div[contains(@class,"bti-jd-main-container")]//div[contains(@class,"content")]',
                        ),
                        'company'     => array(
                            'nicename' => __( 'Company Name', 'gofetch-wpjm' ),
                            'query'    => '//div[contains(@class,"bti-job-results-highlighted")]//div[contains(@class,"bti-grid-search-contentarea")]//div[contains(@class,"card-subtitle")]',
                        ),
                        'location'    => array(
                            'nicename' => __( 'Location', 'gofetch-wpjm' ),
                            'query'    => '//div[contains(@class,"bti-grid-searchDetails-side")]//strong[contains(text(),"Location:")]/../text()',
                        ),
                        'salary'      => array(
                            'nicename' => __( 'Salary', 'gofetch-wpjm' ),
                            'query'    => '//div[contains(@class,"bti-grid-searchDetails-side")]//strong[contains(text(),"Salary:")]/../text()',
                        ),
                        'logo'        => array(
                            'nicename' => __( 'Company Logo', 'gofetch-wpjm' ),
                            'query'    => '//div[@id="bti-apply-emp-logo"]//img[@itemprop="URL"]/@src',
                        ),
                    ),
                ),
                'category'    => __( 'Marketing', 'gofetch-wpjm' ),
                'premium'     => false,
                'weight'      => 7,
            ),
            __( 'Other', 'gofetch-wpjm' ) => array(
                'website'     => '#',
                'logo'        => 'https://upload.wikimedia.org/wikipedia/commons/thumb/4/46/Generic_Feed-icon.svg/500px-Generic_Feed-icon.svg.png',
                'description' => 'Use RSS feed from other provider',
                'feed'        => array(
                    'base_url' => '#',
                ),
                'category'    => __( 'Other', 'gofetch-wpjm' ),
                'weight'      => 99,
            ),
        );
        $providers = apply_filters( 'goft_wpjm_providers', $providers );
        if ( $provider ) {
            $provider_id = self::find_provider( $provider );
            if ( empty( $providers[$provider] ) && !$provider_id ) {
                if ( $parent = self::get_provider_parent( $provider ) ) {
                    return $parent;
                }
                return array();
            } elseif ( !empty( $providers[$provider] ) ) {
                return $providers[$provider];
            } else {
                return $providers[$provider_id];
            }
        }
        return $providers;
    }

    /**
     * Retrieves the RSS feed setup instructions for a given provider.
     */
    public static function setup_instructions_for( $provider ) {
        global $goft_wpjm_options;
        $setup = $header = $multi_region = $steps_li = $skip_copy_url = '';
        $steps = 0;
        $data = self::get_providers( $provider );
        if ( empty( $data['feed']['search_url'] ) ) {
            $data['feed']['search_url'] = $data['feed']['base_url'];
        }
        // __Header.
        if ( !empty( $data['feed']['base_url'] ) && !empty( $data['logo'] ) ) {
            $atts = array(
                'src'   => esc_url( $data['logo'] ),
                'class' => 'provider-logo-orig',
            );
            $header = html( 'a', array(
                'href'   => esc_url( $data['website'] ),
                'target' => '_blank',
            ), html( 'img', $atts ) );
            $header .= html( 'p', html( 'em', $data['description'] ) );
        }
        $header = html( 'div class="provider-header"', $header );
        // __Meta.
        $base_data = array(
            'title'       => __( 'Title', 'gofetch-wpjm' ),
            'description' => __( 'Description', 'gofetch-wpjm' ),
            'date'        => __( 'Date', 'gofetch-wpjm' ),
        );
        $meta_data = array();
        if ( !empty( $data['feed']['base-data-less'] ) ) {
            $base_data = array_diff( array_keys( $base_data ), $data['feed']['base-data-less'] );
        }
        if ( !empty( $data['feed']['meta'] ) ) {
            $meta_data = array_merge( $meta_data, $data['feed']['meta'] );
        }
        if ( !empty( $data['feed']['meta-less'] ) ) {
            $meta_data = array_diff( $meta_data, $data['feed']['meta-less'] );
        }
        if ( !empty( $data['feed']['tag_mappings'] ) ) {
            $meta_data = array_merge( $meta_data, array_keys( $data['feed']['tag_mappings'] ) );
        }
        if ( !empty( $data['feed']['regexp_mappings'] ) ) {
            $regexp_keys_all = $data['feed']['regexp_mappings'];
            foreach ( $regexp_keys_all as $key => $d ) {
                if ( is_array( $d ) ) {
                    $regexp_keys[] = key( $d );
                } else {
                    $regexp_keys[] = $key;
                }
            }
            $meta_data = array_merge( $meta_data, $regexp_keys );
        }
        if ( GoFetch_Helper::supports_scraping( $data ) ) {
            $meta_data = array_merge( $meta_data, array_keys( $data['special']['scrape'] ) );
        }
        $multi_region = $api = $data_info = '';
        if ( empty( $data['API'] ) ) {
            // __Multi Region.
            if ( isset( $data['multi-region'] ) ) {
                $multi_region = '<br/>' . __( 'This is a multi region jobs site. These instructions are meant for a specific country site but they should also work with any of the other available country sites.', 'gofetch-wpjm' );
            }
        } else {
            if ( !empty( $data['API']['required_fields'] ) ) {
                $classes = '';
                if ( isset( $data['API']['required_fields'] ) ) {
                    foreach ( $data['API']['required_fields'] as $field ) {
                        if ( !$goft_wpjm_options->{$field} ) {
                            $classes = 'required-field ' . implode( ' ', $data['API']['required_fields'] );
                            continue;
                        }
                    }
                }
                $dismiss_el = html( 'span class="dashicons-before dashicons-dismiss gofjerror"', '' );
                $message_el = html( 'div', sprintf( __( 'To use this provider, please check that you have filled all the required fields, on the respective settings page.', 'gofetch-wpjm' ) ) );
                $dismiss_message_el = html( 'div class="provider-data secondary-container dashicons-gofjerror gofjerror"', $dismiss_el . $message_el );
                $api = '<br/>' . __( 'Jobs are pulled using the provider API.', 'gofetch-wpjm' );
                $api .= ' ' . sprintf( __( 'For information on all the data retrieved from the API please visit your <a href="%s">Publisher Account</a>.', 'gofetch-wpjm' ), $data['API']['info'] );
                if ( $classes ) {
                    $api .= html( 'span class="' . esc_attr( $classes ) . '"', '<br/>' . $dismiss_message_el );
                }
                $api .= '<br/>';
            } else {
                $api = '<br/>' . __( 'Jobs are pulled using the provider API. No API key required.', 'gofetch-wpjm' );
            }
        }
        switch ( $provider ) {
            case __( 'Other', 'gofetch-wpjm' ):
                $other_li = html( 'li', __( 'Visit any job site jobs search page, click \'View Source\' on your browser and search for the <code>RSS</code> word. In case you find matches look for any RSS related links near it.</p>', 'gofetch-wpjm' ) );
                $other_li .= html( 'li', __( 'Google directly for <code>my job site provider + RSS feeds</code></p>', 'gofetch-wpjm' ) );
                $other_li .= html( 'li', sprintf( __( 'Search for job sites directly from an RSS Reader like <a href="%1$s" target="_blank">Feedly</a>.</p>', 'gofetch-wpjm' ), 'https://feedly.com' ) );
                $setup .= html( 'p', sprintf( __( 'To use other job feed providers outside the providers list try the following:', 'gofetch-wpjm' ) ), html( 'ul', $other_li ) );
                $setup .= '<br/>' . html( 'p', __( 'In any case, most job sites usually offer a pre-set list of RSS feeds or a custom RSS builder based on a job search.', 'gofetch-wpjm' ) . ' ' . __( 'Just follow the instructions for similar providers and you should be ready to go.', 'gofetch-wpjm' ) );
                break;
            default:
                if ( !array_intersect( (array) $data['category'], array('ATS', 'API') ) ) {
                    $steps_li .= html( 'li', sprintf( __( 'Visit the provider jobs page by clicking <a href="%2$s" target="_blank">here</a>.</p>', 'gofetch-wpjm' ), ++$steps, esc_url( $data['feed']['search_url'] ) ) );
                    $steps_li .= html( 'li', sprintf( __( 'Look for any RSS feed links.</p>', 'gofetch-wpjm' ), ++$steps ) );
                }
        }
        if ( $steps ) {
            if ( !$skip_copy_url ) {
                if ( !empty( $data['API'] ) ) {
                    $steps_li .= html( 'li', sprintf( __( 'Use the XML feed URL from your account as reference and change any parameters you like.</p>', 'gofetch-wpjm' ), ++$steps ) );
                }
            }
            if ( !empty( $data['feed']['sample'] ) ) {
                $samples = '';
                foreach ( (array) $data['feed']['sample'] as $sample ) {
                    $samples .= ( $samples ? ' ' . __( 'OR', 'gofetch-wpjm' ) . ' ' : '' );
                    $samples .= sprintf( '<a href="%1$s" target="_blank">%1$s</a>', $sample );
                }
                $steps_li .= html( 'li', sprintf( __( 'You should have an URL similar to this %2$s (depending on your criteria).</p>', 'gofetch-wpjm' ), ++$steps, $samples ) );
            }
            $steps_li .= html( 'li', sprintf( __( 'Paste the RSS/XML feed URL on the <code>URL</code> input field below.', 'gofetch-wpjm' ), ++$steps ) );
            $setup .= html( 'ol', $steps_li );
        }
        // Display single example.
        if ( !empty( $data['feed']['example'] ) ) {
            $setup .= '<br/>';
            $setup .= html( 'p', html( 'strong', __( 'Example', 'gofetch-wpjm' ) ) . self::copy_paste() );
            $setup .= html( 'p class="provider-other-feeds"', sprintf( '<span class="dashicons dashicons-rss"></span> <a href="%1$s" class="provider-rss" target="_blank">%1$s</a></p>', esc_url( $data['feed']['example'] ) ) );
        }
        if ( !empty( $data['feed']['default'] ) ) {
            $data['feed']['fixed'] = array(
                __( 'Latest Jobs', 'gofetch-wpjm' ) => $data['feed']['base_url'],
            );
        }
        // Display the fixed RSS feeds.
        if ( !empty( $data['feed']['fixed'] ) ) {
            $setup .= '<br/>';
            if ( count( $data['feed']['fixed'] ) === 1 ) {
                if ( $steps ) {
                    $setup .= sprintf( __( '<p><strong>OR</strong> ... use the default RSS feed from the provider %1$s:</p>', 'gofetch-wpjm' ), self::copy_paste(), ++$steps );
                } else {
                    $setup .= sprintf( __( '<br/><p>Please click the link below:</p>', 'gofetch-wpjm' ), ++$steps );
                }
            } else {
                $setup .= sprintf( __( '<p>Choose your preferred RSS feed from the provider list %1$s:</p>', 'gofetch-wpjm' ), self::copy_paste(), ++$steps );
            }
            $setup .= '<br/>';
            foreach ( $data['feed']['fixed'] as $desc => $url ) {
                if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
                    $setup .= html( 'p class="provider-other-feeds"', sprintf( '<span class="dashicons dashicons-rss"></span> <a href="%2$s" class="provider-rss" target="_blank">%1$s</a></p>', $desc, esc_url( $url ) ) );
                } else {
                    $setup .= html( $url, $desc );
                }
            }
            $setup .= '<br/>';
            if ( !empty( $data['feed']['feeds_url'] ) ) {
                $desc = __( 'See full list of RSS feeds here', 'gofetch-wpjm' );
                if ( !empty( $data['feed']['feeds_url_desc'] ) ) {
                    $desc = $data['feed']['feeds_url_desc'];
                }
                $setup .= html( 'p class="provider-other-feeds"', sprintf( '<span class="dashicons dashicons-rss"></span> <a href="%1$s" target="_blank">%2$s</a></p>', esc_url( $data['feed']['feeds_url'] ), $desc ) );
            }
        }
        // Display examples.
        if ( !empty( $data['feed']['examples'] ) ) {
            $setup .= '<br/><br/>';
            $setup .= html( 'p', sprintf( __( 'Here are some quick ready to use feeds: %1$s', 'gofetch-wpjm' ), self::copy_paste(), ++$steps ) );
            $setup .= '<br/>';
            foreach ( $data['feed']['examples'] as $desc => $url ) {
                if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
                    $setup .= html( 'p class="provider-other-feeds"', sprintf( '<span class="dashicons dashicons-rss"></span> <a href="%2$s" class="provider-rss" target="_blank">%1$s</a></p>', $desc, esc_url( $url ) ) );
                } else {
                    $setup .= html( $url, $desc );
                }
            }
            $setup .= '<br/>';
        }
        if ( !empty( $notes ) ) {
            $setup .= '<br/>' . $notes;
        }
        if ( !array_intersect( (array) $data['category'], array('ATS', 'API') ) ) {
            // Wrap the manual setup.
            $manual_setup = html( 'p', html( 'a', array(
                'href'         => '#',
                'class'        => 'provider-expand-feed-manual-setup',
                'data-child'   => 'feed-manual-setup',
                'data-default' => __( 'View Manual Setup Instructions', 'gofetch-wpjm' ),
            ), __( 'View Manual Setup Instructions', 'gofetch-wpjm' ) ) );
            $setup = $manual_setup . html( 'div class="feed-manual-setup"', $setup );
        }
        $setup = $header . $api . $multi_region . $data_info . $setup;
        // Wrap the builder, if available.
        if ( !empty( $data['feed']['query_args'] ) ) {
            if ( gfjwjm_fs()->can_use_premium_code() ) {
                $feed_builder = html( 'p', html( 'a', array(
                    'href'         => '#',
                    'class'        => 'provider-expand-feed-builder',
                    'data-child'   => 'feed-builder',
                    'data-default' => __( 'Use Feed Builder', 'gofetch-wpjm' ),
                ), __( 'Use RSS Feed Builder', 'gofetch-wpjm' ) ) );
                $setup .= $feed_builder . html( 'div class="feed-builder"', self::output_rss_feed_builder( $provider, $data ) );
            } else {
                $feed_builder = html( 'p', html( 'a', array(
                    'href'         => '#',
                    'class'        => 'provider-expand-feed-builder-example',
                    'data-child'   => 'feed-builder',
                    'data-default' => __( 'Feed Builder (non-working example)', 'gofetch-wpjm' ),
                ), GoFetch_Admin::limited_plan_warn() ) );
                $setup .= $feed_builder . html( 'div class="feed-builder"', self::output_rss_feed_builder( $provider, $data ) );
            }
        }
        return apply_filters( 'goft_wpjm_setup_instructions_for', $setup, $provider );
    }

    /**
     * Get the user saved setting to help identify API's not ready to use.
     */
    public static function required_query_params( $provider ) {
        global $goft_wpjm_options;
        $data = self::get_providers( $provider );
        $settings = array();
        if ( !isset( $data['feed']['required_query_params'] ) ) {
            return $settings;
        }
        $required_query_params = $data['feed']['required_query_params'];
        foreach ( $required_query_params as $name => $field ) {
            if ( empty( $goft_wpjm_options->{$field} ) ) {
                $settings[$name] = $field;
            }
        }
        return $settings;
    }

    /**
     * Outputs a dropdown will all the available RSS providers.
     */
    public static function output_providers_dropdown( $atts = array() ) {
        $choices_n = array();
        foreach ( self::get_providers() as $name => $data ) {
            if ( isset( $data['deprecated'] ) ) {
                //continue;
            }
            $weight = ( !empty( $data['weight'] ) ? $data['weight'] : 1 );
            $choices_n[$weight][] = $name;
        }
        krsort( $choices_n );
        // Retrieve choices sorted by provider weight.
        foreach ( $choices_n as $providers ) {
            foreach ( $providers as $provider ) {
                $data = self::get_providers( $provider );
                $defaults = array(
                    'description' => '',
                    'category'    => __( 'Other', 'gofetch-wpjm' ),
                );
                $data = wp_parse_args( $data, $defaults );
                $cats = (array) $data['category'];
                foreach ( $cats as $category ) {
                    $categories[$category][$provider] = sprintf(
                        '%1$s %2$s %3$s',
                        ( isset( $data['API'] ) ? '<em>[API]</em>' : '' ),
                        ( isset( $data['multi_region_match'] ) || isset( $data['multi-region'] ) || isset( $data['region_domains'] ) ? __( '<em>[Multi-Region]</em>', 'gofetch-wpjm' ) : '' ),
                        ( !empty( $data['description'] ) ? $data['description'] : '' )
                    );
                }
            }
        }
        ksort( $categories );
        $options = $optgroup = '';
        // Iterate through the categories.
        foreach ( $categories as $category => $providers ) {
            foreach ( $providers as $provider => $desc ) {
                $options .= html( 'option', array(
                    'value'     => esc_attr( $provider ),
                    'data-desc' => esc_attr( $desc ),
                ), $provider . ' ' . $desc );
            }
            $optgroup .= html( 'optgroup', array(
                'label' => esc_attr( $category ),
            ), $options );
            $options = '';
        }
        $optgroup = html( 'option', array(
            'value' => '',
        ), __( 'Choose a Job Provider . . .', 'gofetch-wpjm' ) ) . $optgroup;
        $defaults = array(
            'name' => 'goftj_rss_providers',
            'size' => '20',
        );
        $atts = wp_parse_args( $atts, $defaults );
        $html = html( 'select', $atts, $optgroup );
        $html .= '<br/>' . html( 'div class="secondary-container"', '<span class="dashicons-before dashicons-info"></span>' . html( 'div', sprintf( 'Please note that <em>%s</em> cannot guarantee that all the providers will keep their RSS feeds available forever.<br/>Consider using a secondary RSS feed provider to avoid being dependent on a single one.', 'Go Fetch Jobs' ) ) );
        return $html;
    }

    /**
     * Output the main builder markup.
     */
    public static function output_rss_feed_builder( $provider, $data ) {
        global $goft_wpjm_options;
        $output = '';
        $limited = !gfjwjm_fs()->can_use_premium_code();
        ob_start();
        ?>
		<?php 
        if ( !$limited ) {
            ?>

			<p><?php 
            echo __( 'This is a basic builder to help you setup a customized feed. If you need to further refine the feed please read the manual setup instructions.', 'gofetch-wphm' );
            ?></p><br/>

		<?php 
        } else {
            ?>

			<br><?php 
            echo sprintf( __( 'This is a non working feed builder. Full functionality is available on <a href="%s">premium plans</a> only. Fields displayed will vary depending on the provider.</br> The <em>Free</em> version only provides a fixed URL example that you can copy&paste, below.', 'gofetch-wphm' ), esc_url( gfjwjm_fs()->get_upgrade_url() ) );
            ?></p>

		<?php 
        }
        ?>

		<p>

			<?php 
        if ( isset( $data['multi-region'] ) ) {
            $multi_region = '<br/>' . __( 'This is a multi region provider. It is also available for other countries.', 'gofetch-wpjm' );
            echo wp_kses_post( $multi_region );
        }
        if ( isset( $data['region_domains'] ) ) {
            $default_domain = '';
            if ( !empty( $data['region_option'] ) ) {
                $region_option = $data['region_option'];
                $default_domain = $goft_wpjm_options->{$region_option};
            } else {
                if ( !empty( $data['region_default'] ) ) {
                    $default_domain = $data['region_default'];
                }
            }
            ?><label for="feed-domain"><strong><?php 
            _e( 'Feed Country/State', 'gofetch-wpjm' );
            ?></strong></label><?php 
            if ( isset( $data['region_groups'] ) && $data['region_groups'] ) {
                $optgroup_html = '';
                foreach ( $data['region_domains'] as $group => $items ) {
                    $items_html = '';
                    foreach ( $items as $key => $value ) {
                        $selected = selected( $key === $default_domain, true, false );
                        $items_html .= html( 'option ' . esc_attr( $selected ) . ' value="' . esc_attr( $key ) . '"', $value );
                    }
                    $optgroup_html .= html( 'optgroup label="' . esc_attr( $group ) . '"', $items_html );
                }
                $select = html( 'select name="feed-region_domains" data-qarg="feed-param-region_domains" class="country-sel gofj-multiselect"', $optgroup_html );
                echo wp_kses_post( $select ) . '<br/><br/>';
            } else {
                if ( isset( $data['region_param_domain'] ) ) {
                    $field_name = $data['region_param_domain'];
                } else {
                    $field_name = 'region_domains';
                }
                $field = array(
                    'title'   => __( 'Custom Fields', 'gofetch-wpjm' ),
                    'name'    => sprintf( 'feed-%s', $field_name ),
                    'type'    => 'select',
                    'choices' => $data['region_domains'],
                    'default' => $default_domain,
                    'extra'   => array(
                        'data-qarg' => sprintf( 'feed-param-%s', $field_name ),
                        'class'     => 'country-sel',
                    ),
                    'desc'    => '<br/><small><strong>Note:</strong> Some countries might not have RSS feeds working. Check for any errors displayed below.</small>',
                );
                $output_field = scbForms::input( $field, array() ) . '<br/>';
                if ( isset( $data['region_param_domain'] ) ) {
                    echo html( 'p class="domain-param params opt-param-' . esc_attr( $field_name ) . '"', $output_field . '<input type="hidden" name="feed-param-' . esc_attr( $field_name ) . '">' );
                } else {
                    echo scbForms::input( $field, array() ) . '<br/>';
                }
            }
        }
        ?>

			<label for="feed-url"><strong><?php 
        _e( 'Feed Base URL', 'gofetch-wpjm' );
        ?></strong></label>
			<input <?php 
        disabled( $limited );
        ?> type="text" class="regular-text2" name="feed-url"><a href="#" <?php 
        disabled( $limited );
        ?> class="button secondary reset-feed-url" title="<?php 
        echo __( 'Reset to the original Feed URL.', 'gofetch-wpjm' );
        ?>"><?php 
        echo __( 'Reset', 'gofetch-wpjm' );
        ?></a>
		</p>

		<br/>

		<p class="params opt-param-keyword">
			<label for="feed-keyword"><strong><?php 
        _e( 'Keyword(s)', 'gofetch-wpjm' );
        ?></strong></label><span class="feed-param-keyword"></span>
			<input <?php 
        disabled( $limited );
        ?> type="text" class="regular-text" name="feed-keyword" data-qarg="feed-param-keyword" placeholder="<?php 
        echo __( 'e.g.: design, writer, doctor, etc', 'gofetch-wpjm' );
        ?>">
		</p>

		<p class="params opt-param-location">
			<label for="feed-location"><strong><?php 
        _e( 'Location', 'gofetch-wpjm' );
        ?></strong></label><span class="feed-param-location"></span>
			<input <?php 
        disabled( $limited );
        ?> type="text" class="regular-text" name="feed-location" data-qarg="feed-param-location" placeholder="<?php 
        echo __( 'e.g.: new york, lisbon, london, etc', 'gofetch-wpjm' );
        ?>">
		</p>

		<p class="params opt-param-radius">
			<label for="feed-radius"><strong><?php 
        _e( 'Radius', 'gofetch-wpjm' );
        ?></strong></label><span class="feed-param-radius"></span>
			<input <?php 
        disabled( $limited );
        ?> type="text" class="regular-text" style="width: 100px" style name="feed-radius" data-qarg="feed-param-radius" placeholder="<?php 
        echo __( 'e.g.: 100', 'gofetch-wpjm' );
        ?>">
		</p>

		<div class="clear"></div>

		<p class="params opt-param-state">
			<label for="feed-state"><strong><?php 
        _e( 'State', 'gofetch-wpjm' );
        ?></strong></label><span class="feed-param-state"></span>
			<input <?php 
        disabled( $limited );
        ?> type="text" class="regular-text" name="feed-state" data-qarg="feed-param-state" placeholder="<?php 
        echo __( 'e.g.: new york, florida, etc', 'gofetch-wpjm' );
        ?>">
			</p>

		<p class="params opt-param-type">
			<label for="feed-type"><strong><?php 
        _e( 'Job Type', 'gofetch-wpjm' );
        ?></strong></label> <span class="feed-param-type"></span>
			<input <?php 
        disabled( $limited );
        ?> type="text" class="regular-text" name="feed-type" data-qarg="feed-param-type" placeholder="<?php 
        echo __( 'e.g.: fulltime, freelance', 'gofetch-wpjm' );
        ?>">
		</p>

		<p class="params opt-param-category">
			<label for="feed-category"><strong><?php 
        _e( 'Job Category', 'gofetch-wpjm' );
        ?></strong></label> <span class="feed-param-category"></span>
			<input <?php 
        disabled( $limited );
        ?> type="text" class="regular-text" name="feed-category" data-qarg="feed-param-category" placeholder="<?php 
        echo __( 'e.g.: writer, design', 'gofetch-wpjm' );
        ?>">
		</p>

		<?php 
        do_action( 'goft_wpjm_feed_builder_fields', $provider, $data );
        ?>

		<?php 
        $default_limit = ( !empty( $data['feed']['query_args']['limit']['default'] ) ? $data['feed']['query_args']['limit']['default'] : '' );
        ?>

		<p class="params opt-param-limit">
			<label for="feed-limit"><strong><?php 
        _e( 'Limit', 'gofetch-wpjm' );
        ?></strong>
				<span class="tip"><span class="dashicons-before dashicons-editor-help tip-icon bc-tip" data-tooltip="<?php 
        echo esc_attr( __( 'Number of offers to retrieve.', 'gofetch-wpjm' ) );
        ?>"></span></span>
			</label><span class="feed-param-limit"></span>
			<input <?php 
        disabled( $limited );
        ?> type="text" class="regular-text" value="<?php 
        echo esc_attr( $default_limit );
        ?>" style="width: 100px" style name="feed-limit" data-qarg="feed-param-limit" placeholder="<?php 
        echo __( 'e.g.: 50', 'gofetch-wpjm' );
        ?>">
		</p>

		<?php 
        if ( !empty( $data['feed']['pagination'] ) ) {
            ?>
			<div class="clear"></div>

			<div class="provider-data secondary-container">
				<span class="dashicons-before dashicons-info"></span>
				<div>
					<?php 
            echo __( '<strong>This feed supports pagination!</strong> Although not recommended, you can import more jobs than the default limit.', 'gofetch-wpjm' );
            ?>
					<br/>
					<?php 
            echo __( 'Please note that the higher the limit, the slower the import process will be.', 'gofetch-wpjm' );
            ?>
				</div>
		</div>
		<?php 
        }
        ?>

		<div class="clear"></div>

		<?php 
        ?>

		<?php 
        if ( isset( $data['feed']['scraping'] ) && !$data['feed']['scraping'] || empty( $data['feed']['full_description'] ) ) {
            ?>

			<div class="clear"></div>

			<div class="provider-data secondary-container warning">
				<span class="dashicons-before other dashicons-warning"></span>
				<div>
					<?php 
            echo __( 'Please note that this provider will only return job descriptions excerpts, not full job descriptions.', 'gofetch-wpjm' );
            if ( gfjwjm_fs()->is__premium_only() && isset( $data['special']['scrape'] ) ) {
                echo '<br/>';
                echo __( 'The \'Scrape Metadata\' option below, might help you get full job descriptions.', 'gofetch-wpjm' );
            }
            ?>
				</div>
			</div>

		<?php 
        }
        ?>

		<?php 
        if ( isset( $data['crsf'] ) ) {
            ?>

			<div class="clear"></div>

			<div class="provider-data secondary-container gofjerror">
				<span class="dashicons-before dashicons-warning dashicons-gofjerror"></span>
				<div>
					<?php 
            $message = __( '<strong>IMPORTANT</strong>', 'gofetch-wpjm' );
            $message .= '<br/>' . __( 'This provider has started to block HTTP requests to their feeds and might be deprecated soon.', 'gofetch-wpjm' );
            $message .= '<br/>' . __( 'If you get errors, or keep not getting any jobs, please try another provider.', 'gofetch-wpjm' );
            echo wp_kses_post( $message );
            ?>
				</div>
			</div>

		<?php 
        }
        ?>

		<div class="clear"></div>

		<?php 
        if ( !empty( $data['partner'] ) ) {
            ?>
			<div class="clear"></div>

			<div class="provider-data secondary-container partner revenue-share">
				<span class="dashicons dashicons-before dashicons-star-filled"></span>
				<div>
					<?php 
            echo wp_kses_post( $data['partner_msg'] );
            ?>
				</div>
		</div>
		<?php 
        }
        ?>

		<?php 
        echo self::feed_placeholder( $limited );
        ?>

		<?php 
        if ( !$limited ) {
            ?>

			<input type="hidden" name="feed-param-keyword">
			<input type="hidden" name="feed-param-location">
			<input type="hidden" name="feed-param-state">
			<input type="hidden" name="feed-param-radius">
			<input type="hidden" name="feed-param-limit">
			<input type="hidden" name="feed-param-type">
			<input type="hidden" name="feed-param-category">
			<input type="hidden" name="feed-param-split-multi" value="<?php 
            echo (int) (!empty( $data['feed']['split_params'] ));
            ?>">
			<input type="hidden" name="feed-params-sep" value="&amp;">
			<input type="hidden" name="feed-params-sep-pos" value="after">

			<input type="hidden" name="feed-params-gofj-country-code">
			<input type="hidden" name="feed-params-gofj-country-locale">
			<input type="hidden" name="feed-params-gofj-country-name">

		<?php 
        }
        $output = ob_get_clean();
        return $output;
    }

    /**
     * Ouputs the feed placeholder markup.
     */
    public static function feed_placeholder( $limited = false ) {
        ob_start();
        ?>
		<p class="provider-rss-custom-placeholder">
			<label for="provider-rss-custom-placeholder">
				<?php 
        if ( !$limited ) {
            ?>
					<span class="dashicons dashicons-rss"></span><strong><?php 
            _e( 'Your Custom Feed', 'gofetch-wpjm' );
            ?></strong>
					<?php 
            echo self::copy_paste();
            ?>
				<?php 
        } elseif ( !empty( $data['feed']['example'] ) ) {
            ?>
					<span class="dashicons dashicons-rss"></span><strong><?php 
            _e( 'Your RSS Feed', 'gofetch-wpjm' );
            ?></strong>
					<?php 
            echo self::copy_paste();
            echo html( 'p class="provider-other-feeds"', sprintf( '<a href="%2$s" class="provider-rss" target="_blank">%1$s</a></p>', $data['feed']['example'], esc_url( $data['feed']['example'] ) ) );
            echo html( 'p style="background: #f5f5f5; padding: 10px;"', sprintf( __( '%s <em>Premium</em> versions will auto-generate RSS feed URL\'s based on criteria set on the fields above', 'gofetch-wpjm' ), GoFetch_Admin::limited_plan_warn() ) );
            ?>

				<?php 
        }
        ?>
			</label>
			<?php 
        if ( !$limited ) {
            ?>
				<a class="provider-rss-custom" href="#" target="_blank"></a>
			<?php 
        }
        ?>
		</p>
<?php 
        return ob_get_clean();
    }

    /**
     * Retrieves a copy&paste HTML message.
     */
    public static function copy_paste() {
        return '<code class="copy-paste-info"><span class="icon icon-goft-paste"></span> click the link(s) below to copy&paste</code>';
    }

    /**
     * Check for a provider
     */
    public static function get_provider_parent( $provider_id ) {
        // Check for a parent provider if this is a multi-region provider.
        foreach ( self::get_providers() as $id => $data ) {
            if ( !empty( $data['multi_region_match'] ) && false !== strpos( $provider_id, $data['multi_region_match'] ) ) {
                $provider = self::get_providers( $id );
                $provider['parent_id'] = $id;
                $provider['inherit'] = true;
                return $provider;
            }
        }
        return false;
    }

    /**
     * Try to locate a provider on a URL string and retrieve it on success.
     */
    public static function find_provider_in_url( $url, $provider_match = '' ) {
        $providers = self::get_providers();
        $parsed_url = parse_url( $url );
        foreach ( $providers as $provider_id => $meta ) {
            $percent = 0;
            if ( !$provider_match && isset( $parsed_url['host'] ) ) {
                similar_text( $parsed_url['host'], $provider_id, $percent );
            }
            if ( false !== strpos( $url, $provider_id ) || $percent >= 90 ) {
                if ( !$provider_match || $provider_match && false !== strpos( $provider_id, $provider_match ) || $percent >= 70 ) {
                    return $provider_id;
                }
            }
            $url_parts = parse_url( $meta['feed']['base_url'] );
            // If we have the special 'url_match' prop, check against it first.
            if ( isset( $meta['feed']['url_match'] ) ) {
                $match = false;
                foreach ( (array) $meta['feed']['url_match'] as $url_match ) {
                    if ( false !== strpos( $url, $url_match ) ) {
                        $match = true;
                        break;
                    }
                }
                if ( $match ) {
                    return $provider_id;
                }
            } elseif ( false !== strpos( $url, $meta['feed']['base_url'] ) || !empty( $url_parts['host'] ) && false !== strpos( $url, $url_parts['host'] ) ) {
                return $provider_id;
            }
        }
        return apply_filters(
            'goft_wpjm_provider_in_url',
            false,
            $url,
            $provider_match
        );
    }

    /**
     * Try to locate a provider on a URL string and retrieve it on success.
     */
    public static function find_provider( $provider ) {
        $providers = self::get_providers();
        foreach ( $providers as $provider_id => $meta ) {
            similar_text( $provider, $provider_id, $percent );
            if ( $percent >= 90 ) {
                return $provider_id;
            }
        }
        return false;
    }

    /**
     * Retrieve a smaller URL given a provider URL.
     *
     * @since 1.3.1
     */
    public static function simple_url( $url ) {
        $source = ( $url ? str_replace( array('http://', 'https://'), '', untrailingslashit( $url ) ) : '-' );
        return str_replace( 'www.', '', $source );
    }

    /**
     * Get a list of providers grouped by category and plan type.
     */
    public static function get_providers_list( $category = '' ) {
        $providers = GoFetch_RSS_Providers::get_providers();
        foreach ( $providers as $provider => $data ) {
            $free = ( !isset( $data['premium'] ) ? 'premium' : 'free' );
            foreach ( (array) $data['category'] as $category ) {
                $meta[strtolower( $category )][$free][] = $provider;
            }
        }
        if ( !empty( $category ) ) {
            return $meta[strtolower( $category )];
        }
        return $meta;
    }

}
