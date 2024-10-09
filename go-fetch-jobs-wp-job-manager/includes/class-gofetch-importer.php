<?php

/**
 * Contains the all the core import functionality.
 *
 * @package GoFetchJobs/Importer
 */
if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly.
}
/**
 * The core importer class.
 */
class GoFetch_Importer {
    /**
     * Enable debug mode.
     */
    public static $debug_mode = false;

    /**
     * List of used taxonomies objects.
     *
     * @var object
     */
    protected static $used_taxonomies;

    /**
     * Property for forcing a feed to load.
     *
     * @var boolean
     */
    protected static $goft_wpjm_force_feed = false;

    /**
     * __construct.
     */
    public function __construct() {
        add_action( 'goft_wpjm_before_jobs_filtering', array($this, 'maybe_delete_jobs') );
        add_filter(
            'goft_wpjm_post_insert',
            array($this, 'maybe_expire_job'),
            10,
            2
        );
        add_filter( 'goft_wpjm_import_items_params', array(__CLASS__, 'sanitize_mappings') );
        add_filter( 'goft_wpjm_format_description', array(__CLASS__, 'strip_tags'), 10 );
        add_filter(
            'goft_wpjm_item_title',
            array(__CLASS__, 'prettify_title'),
            10,
            2
        );
        add_filter(
            'goft_wpjm_sample_item',
            array($this, 'sample_item'),
            20,
            2
        );
        add_filter(
            'goft_wpjm_valid_item',
            array($this, 'validate_item'),
            10,
            3
        );
        add_filter(
            'goft_wpjm_fetch_feed_item',
            array(__CLASS__, 'set_item_core_atts'),
            10,
            3
        );
        add_filter(
            'goft_wpjm_fetch_feed_item',
            array(__CLASS__, 'merge_unknown_items'),
            11,
            5
        );
        add_filter(
            'goft_wpjm_fetch_feed_sample_item',
            array(__CLASS__, 'unset_sample_item_core_atts'),
            11,
            3
        );
        add_filter(
            'goft_wpjm_processed_feed_item',
            array($this, 'processed_feed_item'),
            10,
            2
        );
        add_filter(
            'goft_wpjm_import_item_apply_regexp',
            array($this, 'item_apply_regexp'),
            10,
            3
        );
        add_filter(
            'goft_wpjm_skip_loading_as_feed',
            array($this, 'skip_loading_as_feed_if_provider_limits_requests'),
            10,
            2
        );
        add_filter(
            'goft_wpjm_content_blocked',
            array($this, 'provider_blocked_warning'),
            10,
            3
        );
        add_filter( 'goft_wpjm_job_date', array($this, 'maybe_override_job_date') );
    }

    /**
     * Some providers do not allow doing multiple requests in short period of time.
     * For these providers we should skip loading their content as an RSS feed since we alread know they do not load as regular RSS feeds.
     */
    public function skip_loading_as_feed_if_provider_limits_requests( $skip, $url ) {
        $providers = array('jobg8.com');
        foreach ( $providers as $provider ) {
            if ( false !== strpos( $url, $provider ) ) {
                $skip = true;
                break;
            }
        }
        return $skip;
    }

    /**
     * Set additional feed options.
     */
    public static function set_feed_options( $feed, $url ) {
        $feed->set_timeout( 30 );
        // Force feed if the user asks it.
        if ( self::$goft_wpjm_force_feed ) {
            $feed->force_feed( true );
        }
    }

    /**
     * Expire job that explicitly have the 'expired' attribute set to 'true'.
     */
    public function maybe_delete_jobs( $params ) {
        global $goft_wpjm_options;
        if ( empty( $params['replace_jobs'] ) || 'yes' !== $params['replace_jobs'] || empty( $goft_wpjm_options->setup_post_type ) ) {
            return;
        }
        $args = array(
            'post_type'   => $goft_wpjm_options->setup_post_type,
            'post_status' => array('publish', 'draft', 'pending'),
            'fields'      => 'ids',
            'nopaging'    => true,
            'meta_query'  => array(
                'relation' => 'and',
                array(
                    'key'     => '_goft_wpjm_is_external',
                    'compare' => 'exists',
                ),
                array(
                    'key'   => '_goft_jobfeed',
                    'value' => rtrim( rtrim( $params['rss_feed_import'], '&' ), '?' ),
                ),
            ),
        );
        $results = new WP_Query($args);
        if ( !empty( $results->posts ) ) {
            foreach ( $results->posts as $post_id ) {
                wp_delete_post( $post_id );
            }
        }
    }

    /**
     * Expire job that explicitly have the 'expired' attribute set to 'true'.
     */
    public function maybe_expire_job( $post_arr, $item ) {
        if ( !isset( $item['expired'] ) || !(bool) $item['expired'] ) {
            return $post_arr;
        }
        $post_array['post_status'] = 'expired';
        return $post_arr;
    }

    /**
     * Sanitize the field mappings, by trimming spaces.
     */
    public static function sanitize_mappings( $params ) {
        if ( empty( $params['field_mappings'] ) ) {
            return $params;
        }
        $mappings = $params['field_mappings'];
        if ( empty( $mappings ) || !is_array( $mappings ) ) {
            return $params;
        }
        $a = array_map( 'trim', array_keys( $mappings ) );
        $b = array_map( 'trim', $mappings );
        $params['field_mappings'] = array_combine( $a, $b );
        return $params;
    }

    /**
     * Iteratively imports RSS feeds considering pagination, if supported by the provider.
     */
    public static function import_feed(
        $orig_url,
        $params = array(),
        $cache = true,
        $is_file_upload = false
    ) {
        global $goft_wpjm_options;
        $provider = array();
        $url_parts = parse_url( $orig_url );
        // Fix URL's with double forward slashes like http://somedomain.com//some_page.
        $sanitized_url_path = str_replace( '//', '/', $url_parts['path'] );
        $url = str_replace( $url_parts['path'], $sanitized_url_path, $orig_url );
        // Remove last '&' from the URL.
        $url = preg_replace( '/(&)$/is', '', $url );
        $url = filter_var( strip_tags( $url ) );
        if ( false === strpos( $url, 'https' ) ) {
            $url = set_url_scheme( wp_specialchars_decode( $url ), 'http' );
        }
        $provider_match = ( strpos( $url, '//api.' ) !== false || strpos( $url, '/api' ) !== false || strpos( $url, '.json' ) !== false ? 'api.' : '' );
        if ( $provider_id = GoFetch_RSS_Providers::find_provider_in_url( $url, $provider_match ) ) {
            $provider = GoFetch_RSS_Providers::get_providers( $provider_id );
        }
        $pages = 1;
        $limit = 0;
        if ( !empty( $provider['feed']['pagination'] ) ) {
            // Get URL query string parts.
            $parts = parse_url( $url, PHP_URL_QUERY );
            if ( !empty( $parts ) ) {
                // Parse the query string.
                parse_str( $parts, $query_string_parts );
                $limit_qarg = $provider['feed']['pagination']['params']['limit'];
                $page_qarg = $provider['feed']['pagination']['params']['page'];
                $max_results = $provider['feed']['pagination']['results'];
                $pagination_type = ( !empty( $provider['feed']['pagination']['type'] ) ? $provider['feed']['pagination']['type'] : '' );
                // Limit passed as a parameter (not added through the feed URL).
                if ( !empty( $params['feed-limit'] ) ) {
                    $limit = (int) $params['feed-limit'];
                    // Limit passed through the feed URL directly.
                } elseif ( !empty( $query_string_parts[$limit_qarg] ) ) {
                    $limit = (int) $query_string_parts[$limit_qarg];
                }
                if ( $limit > $max_results ) {
                    $pages = (int) ceil( $limit / max( 1, $max_results ) );
                }
            }
        }
        // __LOG.
        $fetch_start_time = current_time( 'timestamp' );
        $vars = array(
            'context'  => 'GOFT :: FETCHING FEED',
            'orig_url' => $orig_url,
            'url'      => $url,
            'pages'    => $pages,
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        $results = array();
        for ($i = 1; $i <= $pages; $i++) {
            // Append the pagination and limit query args to the URL to paginate results.
            if ( $pages > 1 ) {
                $page_qarg_val = $i;
                // Use pagination based on offset.
                if ( 'offset' === $pagination_type && $i > 1 ) {
                    $page_qarg_val *= $max_results + 1;
                }
                if ( !apply_filters(
                    'goft_wpjm_use_custom_feed_pagination',
                    false,
                    $provider_id,
                    $url,
                    $page_qarg_val,
                    $pages
                ) ) {
                    $url = add_query_arg( array(
                        $page_qarg  => $page_qarg_val,
                        $limit_qarg => $max_results,
                    ), $url );
                } else {
                    $url = apply_filters(
                        'goft_wpjm_custom_feed_pagination',
                        $url,
                        $provider_id,
                        $page_qarg_val,
                        $pages
                    );
                }
            }
            GoFetch_Helper::temp_remove_memory_limits();
            $results_temp = self::_import_feed( $url, $provider_id, $provider );
            if ( is_wp_error( $results_temp ) && 1 === $i ) {
                $results = $results_temp;
                break;
            }
            if ( 1 === $i ) {
                $results = $results_temp;
            } elseif ( !is_wp_error( $results_temp ) && !empty( $results_temp['items'] ) ) {
                $new_items = $results_temp['items'];
                $results['items'] = array_merge( $results['items'], $new_items );
            }
            $unique_items = array();
            // Get unique items only, since pagination can generate duplicates.
            foreach ( $results['items'] as $item ) {
                // Create a temp unique job key, by hashing and serializing the item values.
                // Duplicates will generate a duplicate key, that will be skipped.
                $temp_jobkey = md5( serialize( array_values( $item ) ) );
                $unique_items[$temp_jobkey] = $item;
            }
            //
            $results['items'] = $unique_items;
            // Clear memory.
            $results_temp = $unique_items = null;
        }
        if ( is_wp_error( $results ) || empty( $results['items'] ) ) {
            return $results;
        }
        // For paginated feeds make sure the final items array count is not superior to user specified limit.
        if ( $pages > 1 && count( $results['items'] ) > $limit ) {
            array_splice( $results['items'], $limit );
        }
        // __LOG.
        $vars = array(
            'context'  => 'GOFT :: FEED FETCHED SUCCESSFULLY!',
            'duration' => date( 'i:s', current_time( 'timestamp' ) - $fetch_start_time ),
            'results'  => ( !empty( $results['items'] ) ? count( $results['items'] ) : 'No results!' ),
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        // If we're caching the items split the list in chunks to avoid DB errors.
        if ( $cache && !empty( $results['items'] ) ) {
            $chunked_items = self::maybe_chunkify_list( $results['items'] );
            self::cache_feed_items( $chunked_items );
            // Clear memory.
            $chunked_items = null;
        }
        return $results;
    }

    /**
     * Check if the content we got was blocked by CF.
     */
    public static function content_is_cf_blocked( $html, $xpath = false ) {
        if ( !$xpath ) {
            $dom = new DOMDocument();
            $dom->loadHTML( $html, LIBXML_NOERROR );
            $xpath = new DOMXPath($dom);
        }
        $tags = $xpath->query( '//*[contains(text(),"Checking if the site connection is secure") or contains(text(),"Access denied") or contains(text(),"error code") or contains(text(),"Enable JavaScript and cookies to continue") or contains(text(),"you have been blocked")]' );
        return $tags->length;
    }

    /**
     * Identify XML files that use a <Field name=""> structure.
     */
    protected static function uses_field_tag_names( $xml ) {
        if ( !empty( $xml->job ) ) {
            $root = $xml->job;
        } else {
            if ( !empty( $xml->item ) ) {
                $root = $xml->item;
            } else {
                if ( !empty( $xml->channel->item ) ) {
                    $root = $xml->channel->item;
                }
            }
        }
        return !empty( $root[0]->Field );
    }

    /**
     * Convert XML files that use a <Field name=""> structure to a regular list of fields.
     */
    protected static function get_items_from_field_tag_names( $xml ) {
        $items = array();
        foreach ( $xml->job as $job ) {
            $flat_job = (array) $job;
            $index = 0;
            foreach ( $job->Field as $Field ) {
                $value = $flat_job['Field'][$index];
                foreach ( (array) $Field->attributes() as $a => $b ) {
                    $item[$b['name']] = $value;
                }
                $index++;
            }
            $items[] = $item;
        }
        return $items;
    }

    /**
     * Import a custom content type.
     */
    public static function import_custom_content_type( $url_or_data, $is_file_upload = false, $retries = 0 ) {
        $valid_provider = GoFetch_RSS_Providers::find_provider_in_url( $url_or_data );
        if ( !gfjwjm_fs()->is_plan( 'professional' ) && !$valid_provider ) {
            return new WP_Error(-1001, __( 'Content is not readable. <br/>Please upgrade to a <b>Professional</b> or <b>Business</b> plan to load non-standard RSS feeds, XML or JSON files.', 'gofetch-wpjm' ));
        }
        if ( $is_file_upload ) {
            $response = $url_or_data;
        } else {
            $url_or_data = apply_filters( 'goft_wpjm_url_or_data', $url_or_data );
            $args = array(
                'timeout' => 20,
                'headers' => GoFetch_Helper::random_headers(),
            );
            $response = wp_remote_get( $url_or_data, $args );
            if ( is_wp_error( $response ) ) {
                return $response;
            }
            if ( !empty( $response['body'] ) ) {
                if ( GoFetch_Helper::is_gziped( $url_or_data ) ) {
                    GoFetch_Helper::temp_remove_memory_limits();
                    $response = gzdecode( $response['body'] );
                } else {
                    $response = $response['body'];
                }
            }
        }
        // If we get a cloudflare error, retry.
        if ( self::content_is_cf_blocked( $response ) ) {
            if ( $retries <= apply_filters( 'goft_wpjm_importer_content_cf_unblock_max_retries', 5 ) ) {
                sleep( rand( 1, 2 ) );
                return self::import_custom_content_type( $url_or_data, $is_file_upload, ++$retries );
            } else {
                return apply_filters(
                    'goft_wpjm_content_blocked',
                    new WP_Error(-1005, __( 'Content is blocked by Cloudflare.', 'gofetch-wpjm' )),
                    $valid_provider,
                    $url_or_data
                );
            }
        }
        $feed = array();
        if ( GoFetch_Helper::is_json( $response ) ) {
            // Decode JSON to array.
            $response = $orig_response = GoFetch_Helper::json_decode( $response, true );
            $nested_elements = 0;
            // Shift the array until we have a non associative list of items.
            while ( GoFetch_Helper::is_assoc( $response ) || $nested_elements > 3 ) {
                $response = array_shift( $response );
                $nested_elements++;
            }
            if ( !is_array( $response ) ) {
                // Try again by iterating through the JSON key/value pairs and look for the first array
                if ( GoFetch_Helper::is_assoc( $orig_response ) ) {
                    $max = 0;
                    $items = array();
                    foreach ( $orig_response as $key => $value ) {
                        if ( is_array( $value ) ) {
                            $total_children = count( $value );
                            $items[$total_children] = $value;
                            if ( $total_children > $max ) {
                                $max = $total_children;
                            }
                        }
                    }
                }
                $response = $items[$max];
                if ( !is_array( $response ) ) {
                    return apply_filters(
                        'goft_wpjm_json_content_not_readable_error',
                        new WP_Error(-1002, __( 'JSON Content is not readable.', 'gofetch-wpjm' )),
                        $valid_provider,
                        $url_or_data
                    );
                }
            }
            $items = $response;
            // Clear any CDATA.
            $feed['items'] = array_map( array('GoFetch_Helper', 'remove_cdata'), $items );
            $feed['type'] = 'json';
        } else {
            $xml = simplexml_load_string( $response, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOERROR );
            if ( !$xml ) {
                return apply_filters(
                    'goft_wpjm_xml_content_not_readable_error',
                    new WP_Error(-1003, $response),
                    $valid_provider,
                    $url_or_data
                );
            }
            // @todo: use different strategy to flag job feeds (check if its a provider)
            $is_job_feed = !empty( $xml->job ) || !empty( $xml->item ) || !empty( $xml->channel->item );
            if ( self::uses_field_tag_names( $xml ) ) {
                $items = self::get_items_from_field_tag_names( $xml );
            } else {
                //
                $json_xml = json_encode( $xml );
                $items = json_decode( $json_xml, true );
                // Identify the main items.
                $items = GoFetch_Helper::get_xml_main_items( $items, $is_job_feed );
            }
            // For lists with only one item, make sure we have an array.
            if ( !isset( $items[0] ) ) {
                $items = array($items);
            }
            if ( !empty( $items ) ) {
                $feed['items'] = GoFetch_Helper::traverse_and_build_list( $items );
            }
            if ( !is_array( $feed['items'] ) ) {
                return new WP_Error(-1009, __( 'Could not read content.', 'gofetch-wpjm' ));
            }
            $feed['type'] = 'xml';
        }
        return $feed;
    }

    /**
     * Imports RSS feed items from a given URL.
     */
    protected static function _import_feed( $url, $provider_id, &$provider = '' ) {
        $custom_content_type = false;
        $import_callback = array(__CLASS__, 'fetch_feed_items');
        if ( $provider_id && GoFetch_Helper::is_api_provider( $provider_id, $provider ) ) {
            $provider['id'] = $provider_id;
            if ( $provider && !empty( $provider['API']['callback']['fetch_feed'] ) ) {
                $feed = call_user_func( $provider['API']['callback']['fetch_feed'], $url, $provider );
                if ( is_wp_error( $feed ) ) {
                    return $feed;
                }
                $import_callback = $provider['API']['callback']['fetch_feed_items'];
            } else {
                return new WP_Error('unknown_api', __( 'Invalid/Unknown API feed ', 'gofetch-wpjm' ));
            }
            $host = $provider['website'];
            $feed_title = $provider['description'];
            $feed_desc = $provider['description'];
            $feed_image_url = $provider['logo'];
            // Make sure we have an array of arrays.
            if ( !empty( $feed ) && empty( $feed[0] ) ) {
                $feed = array($feed);
            }
        } else {
            if ( !apply_filters( 'goft_wpjm_skip_loading_as_feed', false, $url ) ) {
                $feed = self::fetch_feed( $url, $provider );
            } else {
                // Force an error to load the URL as a custom content type file.
                $feed = new WP_Error('-999', 'Not an RSS feed');
            }
            // Try loading as custom XML/JSON.
            if ( is_wp_error( $feed ) || GoFetch_Helper::is_gziped( $url ) ) {
                // If we got an error and its not a feed, try loading as XML/JSON.
                if ( !is_a( $feed, 'SimplePie' ) || GoFetch_Helper::is_gziped( $url ) ) {
                    $feed = apply_filters( 'goft_wpjm_import_custom_content_type', self::import_custom_content_type( $url ), $url );
                }
                // @todo: MAYBE CUT BIG FILES TO SMALLER ITEMS
                if ( is_wp_error( $feed ) ) {
                    return $feed;
                }
                $custom_content_type = true;
            }
            if ( !$custom_content_type ) {
                $feed_title = $feed->get_title();
                $feed_desc = $feed->get_description();
                $parsed_url = wp_parse_url( $feed->get_permalink() );
            } else {
                $feed_title = '';
                $feed_desc = '';
                $parsed_url = wp_parse_url( $url );
            }
            // If the host URL is empty on the feed try to locate it from the user feed URL.
            if ( empty( $parsed_url['host'] ) ) {
                $parsed_url['host'] = GoFetch_RSS_Providers::find_provider_in_url( $url );
            }
            if ( !empty( $parsed_url['host'] ) ) {
                $provider_id = ( $provider_id ? $provider_id : str_replace( 'www.', '', $parsed_url['host'] ) );
                $host = $parsed_url['host'];
            } else {
                $provider_id = ( $provider_id ? $provider_id : 'unknown' );
                $host = __( 'Unknown', 'gofetch-wpjm' );
            }
            if ( !$provider ) {
                $provider = GoFetch_RSS_Providers::get_providers( $provider_id );
            }
            $provider['id'] = $provider_id;
        }
        // Set provider data.
        $defaults = array(
            'id'          => $provider_id,
            'title'       => $feed_title,
            'website'     => $host,
            'description' => $feed_desc,
            'logo'        => '',
        );
        $provider = wp_parse_args( (array) $provider, $defaults );
        // If this is a multi-site child provider skip part of the base data from the parent and use the defaults.
        if ( !empty( $provider['inherit'] ) ) {
            $provider['title'] = $defaults['title'];
            $provider['website'] = $defaults['website'];
            $provider['description'] = $defaults['description'];
        }
        $results = call_user_func(
            $import_callback,
            $feed,
            $url,
            $provider,
            $custom_content_type
        );
        // Clear memory.
        $feed = null;
        return apply_filters( 'goft_wpjm_feed_results', $results );
    }

    /**
     * Fetch and return items from the RSS feed.
     */
    public static function fetch_feed_items(
        $feed,
        $url,
        $provider,
        $custom_content_type = false
    ) {
        global $goft_wpjm_options;
        $new_items = $sample_item = array();
        $provider['custom'] = false;
        // Check for a non regular feed structure.
        if ( !$custom_content_type ) {
            $items = $feed->get_items();
        } else {
            $provider['custom'] = true;
            // Process as custom feed.
            $items = $feed['items'];
        }
        // __LOG.
        // Maybe log import info.
        $import_start_time = current_time( 'timestamp' );
        $vars = array(
            'context'  => 'GOFT :: STARTING RSS FEED IMPORT PROCESS',
            'provider' => $provider['id'],
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        // __END LOG.
        // Make sure we have an array of arrays.
        if ( !empty( $items ) && empty( $items[0] ) ) {
            $items = array($items);
        }
        if ( !$custom_content_type ) {
            // Always set a default 'base' namespace with the valid item tags.
            $namespaces = self::get_namespaces_for_feed( $url );
            $namespaces['base'] = '';
        }
        foreach ( (array) $items as $item ) {
            if ( $custom_content_type ) {
                $new_item = self::process_custom_content_type_item( $item );
            } else {
                $new_item = self::process_feed_item( $item, $namespaces, $provider );
            }
            $new_item = apply_filters( 'goft_wpjm_processed_feed_item', $new_item, $provider );
            // Find the item with the most attributes to use as sample.
            if ( count( array_keys( $new_item ) ) > count( array_keys( $sample_item ) ) ) {
                // Make sure all sample content is trimmed.
                $sample_item = array_map( 'htmlspecialchars_decode', $new_item );
                $sample_item = array_map( 'wp_strip_all_tags', $sample_item );
                $sample_item = array_map( array(__CLASS__, 'shortened_description'), $sample_item );
                $sample_item = array_map( function ( $item ) {
                    if ( strpos( $item, '|' ) !== false ) {
                        $item = '[MULTIPLE TERMS] - ' . str_replace( '|', ', ', $item );
                    }
                    return $item;
                }, $sample_item );
            }
            $new_item = apply_filters(
                'goft_wpjm_fetch_feed_item',
                $new_item,
                $provider,
                $url
            );
            $sample_item = apply_filters(
                'goft_wpjm_fetch_feed_sample_item',
                $sample_item,
                $provider,
                $item
            );
            // __LOG.
            // Maybe log import info.
            $vars = array(
                'context'  => 'GOFT :: IMPORTING ITEM FROM RSS FEED',
                'new_item' => $new_item,
            );
            BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
            // __END LOG.
            $new_items[] = $new_item;
        }
        // __LOG.
        $vars = array(
            'context'  => 'GOFT :: FINISHED RSS FEED IMPORT PROCESS',
            'duration' => date( 'i:s', current_time( 'timestamp' ) - $import_start_time ),
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        // __END LOG.
        // Additional provider attributes.
        if ( empty( $provider['name'] ) && !empty( $provider['title'] ) ) {
            $provider['name'] = $provider['title'];
        }
        if ( empty( $provider['name'] ) && !empty( $provider['id'] ) ) {
            $provider['name'] = $provider['id'];
        }
        $content_type = ( $custom_content_type ? strtoupper( $feed['type'] ) : 'RSS' );
        // Clear memory.
        $items = $feed = null;
        // __LOG.
        // Maybe log import info.
        $vars = array(
            'context' => 'GOFT :: ITEMS COLLECTED FROM FEED',
            'items'   => count( $new_items ),
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        // __END LOG.
        return array(
            'provider'    => $provider,
            'items'       => $new_items,
            'sample_item' => $sample_item,
            'type'        => $content_type,
        );
    }

    /**
     * Proccess a custom XML/JSON item.
     */
    public static function process_custom_content_type_item( $item ) {
        // Skip array values.
        foreach ( $item as $key => $value ) {
            if ( is_array( $value ) ) {
                unset($item[$key]);
            }
        }
        if ( !empty( $item['publishdate'] ) ) {
            $item['date'] = self::get_valid_date( $item['publishdate'], $_type = 'custom' );
        } elseif ( !empty( $item['creationdate'] ) ) {
            $item['date'] = self::get_valid_date( $item['creationdate'], $_type = 'custom' );
        }
        return $item;
    }

    /**
     * Processes a regular feed item.
     */
    public static function process_feed_item( $item, $namespaces, $provider ) {
        global $goft_wpjm_options;
        // Get the XML main meta data.
        $new_item = array();
        $image = '';
        // Get feed item data.
        $description = $item->get_description();
        $title = $item->get_title();
        $permalink = $item->get_permalink();
        $date = self::get_valid_date( $item );
        //
        $new_item['provider_id'] = $provider['id'];
        $new_item['title'] = trim( wp_strip_all_tags( $title ) );
        $new_item['link'] = esc_url_raw( html_entity_decode( $permalink ) );
        $new_item['date'] = $date;
        $new_item['description'] = self::format_description( $description );
        if ( empty( $new_item['logo'] ) ) {
            if ( $enclosure = $item->get_enclosure() ) {
                $image = $enclosure->get_link();
            }
            if ( !empty( $image ) ) {
                $new_item['logo'] = $image;
                $new_item['logo_html'] = html( 'img', array(
                    'src'   => $image,
                    'class' => 'goft-og-image',
                ) );
            }
        }
        // Apply regexp title filter in the end to make sure data can be extracted from it, before change.
        //$new_item['title'] = apply_filters( 'goft_wpjm_item_title', $new_item['title'], $provider );
        return $new_item;
    }

    /**
     * The public wrapper method for the import process.
     */
    public static function import( $items, $params ) {
        global $goft_wpjm_options, $wpdb;
        if ( !defined( 'GOFJ_IMPORTING' ) ) {
            define( 'GOFJ_IMPORTING', true );
        }
        GoFetch_Helper::temp_remove_memory_limits();
        $import_start_time = current_time( 'timestamp' );
        $params['source'] = self::get_feed_source( $params );
        // __LOG.
        // Maybe log import info.
        $vars = array(
            'context' => 'GOFT :: IMPORTING FEED',
            'params'  => $params,
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        if ( empty( $items ) ) {
            return array(
                'imported'    => 0,
                'duplicates'  => 0,
                'in_rss_feed' => 0,
                'limit'       => 0,
            );
        } else {
            self::$used_taxonomies = apply_filters( 'goft_used_taxonomies', get_object_taxonomies( GoFetch_Jobs()->parent_post_type, 'objects' ) );
            $items = apply_filters( 'goft_wpjm_import_items_before_filter', $items );
            $defaults = array(
                'post_type'                   => GoFetch_Jobs()->parent_post_type,
                'post_author'                 => 1,
                'tax_input'                   => array(),
                'smart_tax_input'             => '',
                'replace_jobs'                => '',
                'meta'                        => array(),
                'from_date'                   => '',
                'to_date'                     => '',
                'limit'                       => '',
                'keywords'                    => '',
                'keywords_filtering'          => '',
                'keywords_comparison'         => 'OR',
                'keywords_exclude'            => '',
                'keywords_exclude_comparison' => 'OR',
                'import_images'               => true,
                'special'                     => '',
                'field_mappings'              => array(),
            );
            $params = apply_filters( 'goft_wpjm_import_items_params', wp_parse_args( $params, $defaults ), $items );
            do_action( 'goft_wpjm_before_jobs_filtering', $params );
            $results = self::filter_items( $items, array(
                'post_type' => $params['post_type'],
            ), $params );
            list( $unique_items, $excluded_items, $duplicate_items ) = array_values( $results );
            $items_process = array(
                'insert' => apply_filters( 'goft_wpjm_import_items_after_filter', $unique_items, $params ),
            );
            $stats = array(
                'insert' => 0,
            );
            // Bulk performance optimization.
            wp_defer_term_counting( true );
            wp_defer_comment_counting( true );
            $wpdb->query( 'SET autocommit = 0;' );
            //
            $post_ids = array();
            foreach ( $items_process as $operation => $_items ) {
                $params['operation'] = $operation;
                // Iterate through all the items in the list.
                foreach ( $_items as $item ) {
                    if ( !empty( $item['date'] ) ) {
                        if ( !empty( $params['from_date'] ) ) {
                            if ( 'insert' === $operation && date( 'Y-m-d', strtotime( $item['date'] ) ) < date( 'Y-m-d', strtotime( $params['from_date'] ) ) ) {
                                $excluded_items[] = $item;
                                continue;
                            }
                        }
                        if ( 'insert' === $operation && !empty( $params['to_date'] ) ) {
                            if ( date( 'Y-m-d', strtotime( $item['date'] ) ) > date( 'Y-m-d', strtotime( $params['to_date'] ) ) ) {
                                $excluded_items[] = $item;
                                continue;
                            }
                        }
                    }
                    if ( !apply_filters(
                        'goft_wpjm_import_rss_item',
                        true,
                        $item,
                        $params
                    ) ) {
                        $excluded_items[] = $item;
                        continue;
                    }
                    $import_result = self::_import( $item, $params );
                    if ( $import_result && !is_wp_error( $import_result ) ) {
                        $post_ids[] = $import_result;
                        $stats[$operation]++;
                    } else {
                        // Failed to insert or skipped.
                        $excluded_items[] = $item;
                    }
                }
            }
            // Restore. Deferred database process are commited at this time.
            $wpdb->query( 'COMMIT;' );
            $wpdb->query( 'SET autocommit = 1;' );
            wp_defer_term_counting( false );
            wp_defer_comment_counting( false );
            //
            do_action( 'goft_wpjm_after_insert_jobs_commit', $post_ids );
            $results = array(
                'in_rss_feed' => count( $items ),
                'imported'    => $stats['insert'],
                'limit'       => ( !empty( $params['limit'] ) && $params['limit'] < abs( count( $items ) ) ? abs( count( $items ) - $params['limit'] ) : 0 ),
                'duplicates'  => count( $duplicate_items ),
                'excluded'    => count( $excluded_items ),
                'duration'    => current_time( 'timestamp' ) - $import_start_time,
            );
        }
        // __LOG.
        // Maybe log import info.
        $vars = array(
            'context'  => 'GOFT :: FINISHED IMPORTING FEED',
            'results'  => $results,
            'duration' => date( 'i:s', current_time( 'timestamp' ) - $import_start_time ),
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        // Clear memory.
        $items_process = $_items = null;
        return $results;
    }

    /**
     * Get the feed source from the feed URL.
     */
    protected static function get_feed_source( $params ) {
        $feed_url = sanitize_text_field( $params['rss_feed_import'] );
        if ( !$feed_url ) {
            return array(
                'name'    => 'Unknown',
                'website' => 'Unknown',
            );
        }
        $website = parse_url( $feed_url );
        $website_parts = explode( '.', $website['host'] );
        $source_defaults = array(
            'name'    => ucfirst( $website_parts[1] ),
            'website' => $website['scheme'] . '://' . $website['host'],
            'logo'    => '',
            'args'    => array(),
        );
        return wp_parse_args( wp_array_slice_assoc( wp_unslash( $params['source'] ), array_keys( $source_defaults ) ), $source_defaults );
    }

    // __Private.
    /**
     * Assign meta values based on the user mappings.
     */
    private static function map_item_param_fields( $params, $item ) {
        // Legacy filter.
        $default_mappings = apply_filters( 'goft_wpjm_meta_mappings', GoFetch_Dynamic_Import::default_mappings() );
        // Flag empty mappings with '[ignore]' so that 'wp_parse_args' does not overrid them.
        $params['field_mappings'] = GoFetch_Helper::handle_empty_mappings( $params['field_mappings'], '[ignore]' );
        // Get the default mappings for all valid fields on the feed.
        $params['field_mappings'] = wp_parse_args( array_filter( (array) $params['field_mappings'] ), $default_mappings );
        // Restore empty mappings to ''.
        $params['field_mappings'] = GoFetch_Helper::handle_empty_mappings( $params['field_mappings'], '', '[ignore]' );
        // Always include the scrape fields on the final mappings.
        foreach ( GoFetch_Dynamic_Import::scrape_fields() as $field ) {
            if ( !isset( $params['field_mappings'][$field] ) ) {
                $params['field_mappings'][$field] = GoFetch_Dynamic_Import::default_field_mapping( $field, $field );
            }
        }
        foreach ( $params['field_mappings'] as $item_field => $custom_field ) {
            $is_tax = false;
            // Skip if not a string.
            if ( is_array( $item_field ) ) {
                continue;
            }
            $item_field = trim( $item_field );
            if ( false !== strpos( $item_field, '____auto' ) ) {
                $item_field = str_replace( '____auto', '', $item_field );
            }
            // Skip reserved values.
            if ( !isset( $item[$item_field] ) || is_array( $item[$item_field] ) || strpos( $item[$item_field], '_reserved_' ) !== false ) {
                continue;
            }
            // Map custom/taxonomy fields.
            if ( $taxonomy = get_taxonomy( $custom_field ) ) {
                if ( in_array( $params['post_type'], $taxonomy->object_type ) ) {
                    $value = $item[$item_field];
                    if ( !$value && !empty( $params['tax_input'][$custom_field] ) ) {
                        $value = $params['tax_input'][$custom_field];
                    }
                    $params['tax_input'][$custom_field] = $value;
                    $is_tax = true;
                }
            }
            switch ( $custom_field ) {
                case 'post_title':
                case 'post_content':
                case 'post_author':
                    $params['post'][$custom_field] = $item[$item_field];
                    break;
                default:
                    if ( !$is_tax ) {
                        // Fill only if the item value is not empty. Defaults to user meta, if available.
                        if ( empty( $params['meta'][$custom_field] ) || !empty( $item[$item_field] ) ) {
                            $params['meta'][$custom_field] = $item[$item_field];
                        }
                    }
            }
        }
        return apply_filters( 'goft_wpjm_import_item_params', $params, $item );
    }

    /**
     * The main import method.
     * Creates a new post for each imported job and adds any related meta data.
     */
    public static function _import( $item, $params ) {
        global $goft_wpjm_options;
        // __LOG.
        $vars = array(
            'context' => 'GOFT :: IMPORTING',
            'item'    => $item,
            'params'  => $params,
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        // __END LOG.
        if ( !empty( $params['test'] ) ) {
            // __LOG.
            $vars = array(
                'context' => 'GOFT :: IMPORT TESTING MODE - SKIPPING INSERT',
                'item'    => $item,
            );
            BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
            // __END LOG.
            return true;
        }
        $original_item = $item;
        $item = apply_filters( 'goft_wpjm_prepare_item', $original_item, $params );
        // Bail early, if the item is  not valid anymore.
        if ( !$item ) {
            return false;
        }
        $params = self::map_item_param_fields( $params, $item );
        // Last chance to invalidate items before they hit the database.
        if ( !apply_filters(
            'goft_wpjm_valid_item',
            true,
            $item,
            $params
        ) ) {
            return false;
        }
        do_action( 'goft_wpjm_before_insert_job', $item, $params );
        $meta = array();
        $post_data = self::_insert_post( $item, $params );
        // __LOG.
        $vars = array(
            'context'   => 'GOFT :: INSERT POST RESULT',
            'post_data' => $post_data,
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        // __END LOG.
        // Insert the main post with the core data taken from the feed item.
        if ( !is_wp_error( $post_data ) ) {
            // Prepare meta before adding it to the post.
            $meta = self::_prepare_item_meta(
                $item,
                $original_item,
                $params['meta'],
                $post_data,
                $params
            );
            // __LOG.
            $vars = array(
                'context' => 'GOFT :: INSERTED POST META',
                'meta'    => $meta,
            );
            BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
            // __END LOG.
            if ( !is_array( $post_data ) && is_numeric( $post_data ) ) {
                // Add any existing meta to the new post.
                self::_add_meta( $post_data, $meta );
                do_action(
                    'goft_wpjm_after_insert_job',
                    $post_data,
                    $item,
                    $params,
                    $meta
                );
                // return the post_id.
                return $post_data;
            }
        }
        if ( is_wp_error( $post_data ) ) {
            // __LOG.
            $vars = array(
                'context' => 'GOFT :: INSERT POST ERROR',
                'error'   => $post_data,
            );
            BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
            // __END LOG.
            do_action(
                'goft_wpjm_insert_job_failed',
                $item,
                $params,
                $meta
            );
            return $post_data;
        } else {
            return apply_filters(
                'goft_wpjm_custom_job_insert',
                false,
                $post_data,
                $item,
                $params,
                $meta
            );
        }
        return false;
    }

    /**
     * Insert a post given an item and related parameters.
     */
    private static function _insert_post( $item, $params ) {
        global $goft_wpjm_options;
        // Look for content retrieved on another feed field.
        $post_content = ( !empty( $params['post']['post_content'] ) ? $params['post']['post_content'] : $item['description'] );
        $post_content = apply_filters(
            'goft_wpjm_post_content',
            wp_kses_post( $post_content ),
            $item,
            $params
        );
        $post_title = ( !empty( $params['post']['post_title'] ) ? $params['post']['post_title'] : $item['title'] );
        $post_title = apply_filters(
            'goft_wpjm_post_title',
            wp_kses_post( $post_title ),
            $item,
            $params
        );
        $post_arr = array();
        // Check if there's a different mapping for the 'date' field.
        if ( !empty( $params['field_mappings'] ) && ($date_key = array_search( 'date', $params['field_mappings'] )) ) {
            $post_date = $item[$date_key];
            $post_date = date( 'Y-m-d', strtotime( $post_date ) );
            $post_arr['post_date'] = apply_filters(
                'goft_wpjm_post_date',
                wp_kses_post( $post_date ),
                $item,
                $params
            );
        }
        // Use smart taxonomies terms assignment considering the user keywords selection.
        if ( !empty( $params['smart_tax_input'] ) ) {
            if ( 'title' === $goft_wpjm_options->keyword_matching ) {
                $content = $post_title;
            } else {
                if ( 'content' === $goft_wpjm_options->keyword_matching ) {
                    $content = $post_content;
                } else {
                    $content = $post_title . ' ' . $post_content;
                }
            }
            // Include additional meta to match against the terms.
            $fields = array_intersect( array_keys( $item ), apply_filters( 'goft_wpjm_post_tax_input_fields', array() ) );
            foreach ( $fields as $field ) {
                $content .= ' ' . $item[$field];
            }
            if ( method_exists( 'GoFetch_Premium_Starter_More_Features', 'smart_tax_terms_input' ) ) {
                $params['tax_input'] = GoFetch_Premium_Starter_More_Features::smart_tax_terms_input(
                    $params['tax_input'],
                    $item,
                    $content,
                    self::$used_taxonomies,
                    $params['smart_tax_input']
                );
            }
        }
        $params['tax_input'] = apply_filters(
            'goft_wpjm_post_tax_input',
            $params['tax_input'],
            $item,
            $params
        );
        $post_arr = array_merge( $post_arr, array(
            'post_title'   => $post_title,
            'post_content' => $post_content,
            'post_status'  => apply_filters( 'goft_wpjm_job_status', $goft_wpjm_options->post_status ),
            'post_type'    => $params['post_type'],
            'post_author'  => (int) $params['post_author'],
        ) );
        if ( empty( $post_arr['post_date'] ) ) {
            if ( !empty( $item['date'] ) ) {
                $post_arr['post_date'] = date( 'Y-m-d', strtotime( $item['date'] ) );
            } else {
                if ( !empty( $item['pubdate'] ) ) {
                    $post_arr['post_date'] = date( 'Y-m-d', strtotime( $item['pubdate'] ) );
                } else {
                    $post_arr['post_date'] = current_time( 'Y-m-d' );
                }
            }
            $post_arr['post_date'] = apply_filters( 'goft_wpjm_job_date', $post_arr['post_date'] );
        }
        if ( apply_filters(
            'goft_wpjm_skip_import_job',
            false,
            $item,
            $params,
            $post_arr
        ) ) {
            return $post_arr;
        }
        if ( !empty( $params['operation'] ) && 'update' === $params['operation'] ) {
            if ( !empty( $item['post_id'] ) ) {
                // Post will be updated.
                $post_arr['ID'] = $item['post_id'];
            } else {
                // __LOG.
                $vars = array(
                    'context' => 'GOFT :: WARNING - POST NEEDS UPDATING BUT COULD NOT FIND DB ITEM POST ID',
                    'item'    => $item,
                );
                BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
                // __END LOG.
                return;
            }
        }
        do_action(
            'goft_wpjm_before_job_insert',
            $post_arr,
            $item,
            $params
        );
        $post_arr = apply_filters(
            'goft_wpjm_post_insert',
            $post_arr,
            $item,
            $params
        );
        $post_id = wp_insert_post( $post_arr );
        if ( $post_id && !is_wp_error( $post_id ) ) {
            if ( !empty( $params['tax_input'] ) ) {
                self::_add_taxonomies( $post_id, $params['tax_input'] );
            }
        }
        return $post_id;
    }

    /**
     * Assign terms to a given post ID.
     */
    private static function _add_taxonomies( $post_id, $tax_input ) {
        global $goft_wpjm_options;
        $delimiter1 = ',';
        $delimiter2 = '|';
        foreach ( $tax_input as $tax => $terms ) {
            if ( !is_array( $terms ) ) {
                if ( strpos( $terms, '|' ) !== false ) {
                    $delimiter1 = '|';
                    $delimiter2 = ',';
                }
            }
            if ( !is_array( $terms ) && !empty( $delimiter1 ) ) {
                $terms = explode( $delimiter1, $terms );
                $final_terms = array();
                foreach ( $terms as $term ) {
                    $final_terms = array_merge( $final_terms, explode( $delimiter2, $term ) );
                }
                $terms = array_map( 'trim', $final_terms );
            }
            // Skip early if terms are empty.
            if ( empty( $terms ) ) {
                continue;
            }
            $final_terms = array();
            // Iterate through current item terms but do not create them on the DB if user did not allow it.
            foreach ( $terms as $term ) {
                if ( !$goft_wpjm_options->smart_assign_create_terms && !term_exists( $term, $tax ) ) {
                    continue;
                }
                $final_terms[] = $term;
                wp_set_object_terms( $post_id, $final_terms, $tax );
            }
        }
    }

    /**
     * Adds meta data to a given post ID.
     */
    private static function _add_meta( $post_id, $meta ) {
        foreach ( $meta as $meta_key => $meta_value ) {
            if ( apply_filters(
                'goft_wpjm_update_meta',
                false,
                $meta_key,
                $meta_value,
                $post_id
            ) ) {
                continue;
            }
            update_post_meta( $post_id, $meta_key, $meta_value );
        }
    }

    /**
     * Prepares all the meta for a given item before it is saved in the database.
     *
     * @uses apply_filters() Calls 'goft_wpjm_item_meta_value'.
     */
    private static function _prepare_item_meta(
        $item,
        $original_item,
        $meta,
        $post_id,
        $params
    ) {
        // Add the feed URL to the source data.
        $params['source']['feed_url'] = $params['rss_feed_import'];
        $meta['_goft_wpjm_is_external'] = 1;
        $meta['_goft_external_link'] = $item['link'];
        $meta['_goft_unique_jobkey'] = $item['jobkey'];
        $meta['_goft_jobfeed'] = $item['jobfeed'];
        $meta['_goft_source_data'] = $params['source'];
        if ( !empty( $item['other'] ) ) {
            $meta['_goft_wpjm_other'] = $item['other'];
        }
        $meta['_goft_wpjm_original_item'] = $original_item;
        $meta['_goft_wpjm_import_params'] = $params;
        // Get the custom field mappings.
        $cust_field_mappings = apply_filters( 'goft_wpjm_custom_field_mappings', array() );
        $final_meta = array();
        foreach ( $meta as $meta_key => $meta_value ) {
            if ( !$meta_key ) {
                continue;
            }
            // If any of the custom fields is found on items being imported get the value and override the defaults.
            if ( isset( $cust_field_mappings[$meta_key] ) ) {
                $known_field = $cust_field_mappings[$meta_key];
                if ( isset( $item[$known_field] ) ) {
                    $meta_value = sanitize_text_field( $item[$known_field] );
                }
            }
            /**
             * @todo: maybe use a placeholder featured image and use a filter to override the featured image SRC image.
             */
            if ( !apply_filters(
                'goft_wpjm_item_skip_meta',
                false,
                $meta_value,
                $meta_key,
                $item,
                $post_id,
                $params
            ) ) {
                $final_meta[$meta_key] = apply_filters(
                    'goft_wpjm_item_meta_value',
                    $meta_value,
                    $meta_key,
                    $item,
                    $post_id,
                    $params
                );
            }
        }
        return apply_filters( 'goft_wpjm_item_meta', $final_meta );
    }

    /**
     * Make sure that fields like 'title' and 'description' are set
     * by checking for their alternative mapping options: post_title, post_content.
     */
    public static function standardize_item_keys( $item, $field_mappings ) {
        $base_alt_fields = GoFetch_Dynamic_Import::nuclear_fields_alt_mappings();
        // If there are alt fields, update the items.
        if ( !empty( $field_mappings ) && array_intersect( array_values( $field_mappings ), array_keys( $base_alt_fields ) ) ) {
            // Iterate through the mappings and add add the base field name/value, by looking for the alternative field value.
            foreach ( $field_mappings as $field => $alt_field ) {
                if ( isset( $base_alt_fields[$alt_field] ) ) {
                    $item[$base_alt_fields[$alt_field]] = $item[$field];
                }
            }
        }
        $item['title'] = trim( wp_strip_all_tags( $item['title'] ) );
        $item['description'] = self::format_description( $item['description'] );
        return $item;
    }

    /**
     * Filter the items and return a list of results.
     */
    private static function filter_items( $items, $args = array(), $params = array() ) {
        global $goft_wpjm_options, $wpdb;
        $debug_mode = GoFetch_Importer::$debug_mode;
        // __LOG.
        $fetch_start_time = current_time( 'timestamp' );
        $vars = array(
            'context' => 'GOFT :: FILTERING ITEMS',
            'args'    => $args,
            'params'  => $params,
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        $defaults = array(
            'limit'                       => 0,
            'keywords'                    => '',
            'keywords_comparison'         => 'OR',
            'keywords_filtering'          => '',
            'keywords_exclude'            => '',
            'keywords_exclude_comparison' => 'OR',
        );
        $params = wp_parse_args( $params, $defaults );
        $limit = $params['limit'];
        $keywords = $params['keywords'];
        $keywords_filtering = $params['keywords_filtering'];
        $keywords_comparison = $params['keywords_comparison'];
        $keywords_exclude = $params['keywords_exclude'];
        $keywords_exclude_comparison = $params['keywords_exclude_comparison'];
        try {
            // Get the item title based on the user mappings
            $title_field = GoFetch_Admin_Builder::get_core_mapped_field( 'post_title', $params['field_mappings'] );
            if ( !$title_field ) {
                $title_field = 'title';
            }
            $all_items_titles = wp_list_pluck( $items, $title_field );
            $like_titles = '';
            foreach ( $all_items_titles as $all_items_title ) {
                if ( empty( $all_items_title ) ) {
                    continue;
                }
                $all_items_title = str_replace( '&#039;', '\\&#039;', $all_items_title );
                if ( $like_titles ) {
                    $like_titles .= ' OR ';
                }
                $like_titles .= ' TRIM( LOWER( c.meta_value ) ) LIKE "%%' . addslashes( strtolower( trim( $all_items_title, ENT_QUOTES ) ) ) . '%%"';
            }
            if ( !empty( $like_titles ) && strpos( strtolower( $like_titles ), ' like ' ) >= 0 ) {
                // Get all existing jobs from the same provider to look for duplicates.
                // Look for matches of any of the new titles "coming in" to speed up unique items checking.
                $sql = "\n\t\t\t\t\tSELECT DISTINCT a.* FROM {$wpdb->posts} a, {$wpdb->postmeta} b, {$wpdb->postmeta} c\n\t\t\t\t\tWHERE a.ID = b.post_id\n\t\t\t\t\tAND a.ID = c.post_id\n\t\t\t\t\tAND post_type = '%s'\n\t\t\t\t\tAND (post_status = 'publish' OR post_status = 'draft' OR post_status = 'pending')\n\t\t\t\t\tAND b.meta_key = '_goft_wpjm_is_external'\n\t\t\t\t\tAND c.meta_key = '_goft_wpjm_original_item'\n\t\t\t\t\tAND (" . $like_titles . ")\n\t\t\t\t\tORDER BY post_date DESC\n\t\t\t\t";
                $results = $wpdb->get_results( $wpdb->prepare( $sql, GoFetch_Jobs()->parent_post_type ) );
            }
            if ( !empty( $results ) ) {
                // Get existing external posts.
                foreach ( $results as $post ) {
                    // Make sure we grab the original data to check for updates.
                    $original_item = get_post_meta( $post->ID, '_goft_wpjm_original_item', true );
                    $source_data = get_post_meta( $post->ID, '_goft_source_data', true );
                    if ( !empty( $original_item ) ) {
                        $original_item_def = array(
                            'title'       => '',
                            'description' => '',
                        );
                        $original_item = wp_parse_args( $original_item, $original_item_def );
                        $title = $original_item['title'];
                        $description = $original_item['description'];
                    } else {
                        $title = $post->post_title;
                        $description = $post->post_content;
                    }
                    // Generate a unique job key if we don't have one.
                    $jobkey = self::generate_job_key( $source_data['feed_url'] );
                    $external_posts[] = array(
                        'jobkey'      => $jobkey,
                        'post_id'     => $post->ID,
                        'title'       => GoFetch_Helper::clean_and_sanitize( $title, $_alphanumeric_only = true ),
                        'description' => GoFetch_Helper::clean_and_sanitize( $description, $_alphanumeric_only = true ),
                        'from_db'     => true,
                    );
                }
            } else {
                $external_posts = array();
            }
        } catch ( Exception $e ) {
            // __LOG.
            $vars = array(
                'context' => 'GOFT :: EXTERNAL POSTS QUERY FAILED',
            );
            BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
            $external_posts = array();
        }
        if ( $debug_mode ) {
            var_dump( $external_posts );
        }
        $unique_items = $duplicate_items = $excluded_items = array();
        if ( $keywords ) {
            $keywords = explode( ',', $keywords );
            $keywords = stripslashes_deep( $keywords );
            $keywords = array_filter( $keywords );
        }
        if ( $keywords_exclude ) {
            $keywords_exclude = explode( ',', $keywords_exclude );
            $keywords_exclude = stripslashes_deep( $keywords_exclude );
            $keywords_exclude = array_filter( $keywords_exclude );
        }
        // Affect the item directly by passing it by reference.
        foreach ( $items as &$item ) {
            // Standardize item field names and update the '$item' var directly (passed by reference).
            $item = self::standardize_item_keys( $item, $params['field_mappings'] );
            // Generate a job key using the feed URL.
            $item['jobkey'] = self::generate_job_key( $params['rss_feed_import'] );
            $content = '';
            if ( 'all_fields' === $goft_wpjm_options->keyword_matching ) {
                $item_keys = array_keys( $item );
                foreach ( $item_keys as $item_key ) {
                    $content .= ' ' . $item[$item_key];
                }
            } else {
                if ( 'all' === $goft_wpjm_options->keyword_matching || 'title' === $goft_wpjm_options->keyword_matching ) {
                    $content .= $item['title'];
                }
                if ( 'all' === $goft_wpjm_options->keyword_matching || 'content' === $goft_wpjm_options->keyword_matching ) {
                    $content .= ' ' . $item['description'];
                }
            }
            $content = trim( $content );
            $jobkey = $item['jobkey'];
            $dup_post_id = 0;
            if ( !empty( $external_posts ) ) {
                $dup_post_id = GoFetch_Helper::find_duplicate( $item, $external_posts );
            }
            if ( !$dup_post_id ) {
                if ( $debug_mode ) {
                    var_dump( $item );
                }
                // Match keywords.
                if ( $keywords_filtering && ($keywords || $keywords_exclude) && $content ) {
                    $exclude = false;
                    // Positive keywords.
                    if ( $keywords && !GoFetch_Helper::match_keywords( $content, $keywords, $keywords_comparison ) ) {
                        $exclude = true;
                    }
                    // Negative keywords.
                    if ( $keywords_exclude && GoFetch_Helper::match_keywords( $content, $keywords_exclude, $keywords_exclude_comparison ) ) {
                        $exclude = true;
                    }
                    // Allow overriding the keywords matching result.
                    $exclude = apply_filters(
                        'goft_wpjm_exclude_item',
                        $exclude,
                        $item,
                        $keywords,
                        $keywords_exclude,
                        $keywords_comparison,
                        $keywords_exclude_comparison
                    );
                    if ( $exclude ) {
                        $excluded_items[] = $item;
                        continue;
                    } else {
                        $unique_items[] = $item;
                    }
                } else {
                    $unique_items[] = $item;
                }
                // Limit the results if requested by the user.
                if ( $limit && count( $unique_items ) >= $limit ) {
                    break;
                }
            } else {
                $duplicate_items[] = $item;
            }
        }
        // __LOG.
        $vars = array(
            'context'         => 'GOFT :: FILTER ITEMS COUNTS',
            'unique_items'    => count( $unique_items ),
            'excluded_items'  => count( $excluded_items ),
            'duplicate_items' => count( $duplicate_items ),
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        // __END LOG.
        $results = array(
            'unique_items'    => $unique_items,
            'excluded_items'  => $excluded_items,
            'duplicate_items' => $duplicate_items,
        );
        wp_reset_postdata();
        // __LOG.
        $vars = array(
            'context'  => 'GOFT :: FILERERED ITEMS SUCCESSFULLY!',
            'duration' => date( 'i:s', current_time( 'timestamp' ) - $fetch_start_time ),
        );
        BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        if ( $debug_mode ) {
            var_dump( $vars );
            exit;
        }
        return $results;
    }

    /**
     * Mirors WP 'fetch_feed()' but uses raw data instead on an URL.
     *
     * @since 1.3.2
     */
    protected static function fetch_feed_raw_data( $data, $url ) {
        if ( !class_exists( 'SimplePie', false ) ) {
            require_once ABSPATH . WPINC . '/class-simplepie.php';
        }
        require_once ABSPATH . WPINC . '/class-wp-feed-cache-transient.php';
        require_once ABSPATH . WPINC . '/class-wp-simplepie-file.php';
        require_once ABSPATH . WPINC . '/class-wp-simplepie-sanitize-kses.php';
        $feed = new SimplePie();
        $feed->set_sanitize_class( 'WP_SimplePie_Sanitize_KSES' );
        // We must manually overwrite $feed->sanitize because SimplePie's
        // constructor sets it before we have a chance to set the sanitization class
        $feed->sanitize = new WP_SimplePie_Sanitize_KSES();
        // Register the cache handler using the recommended method for SimplePie 1.3 or later.
        if ( method_exists( 'SimplePie_Cache', 'register' ) ) {
            SimplePie_Cache::register( 'wp_transient', 'WP_Feed_Cache_Transient' );
            $feed->set_cache_location( 'wp_transient' );
        } else {
            // Back-compat for SimplePie 1.2.x.
            require_once ABSPATH . WPINC . '/class-wp-feed-cache.php';
            $feed->set_cache_class( 'WP_Feed_Cache' );
        }
        $feed->set_file_class( 'WP_SimplePie_File' );
        $feed->set_raw_data( $data );
        /** This filter is documented in wp-includes/class-wp-feed-cache-transient.php */
        $feed->set_cache_duration( apply_filters( 'wp_feed_cache_transient_lifetime', 12 * HOUR_IN_SECONDS, $url ) );
        /**
         * Fires just before processing the SimplePie feed object.
         *
         * @since 3.0.0
         *
         * @param object &$feed SimplePie feed object, passed by reference.
         * @param mixed  $url   URL of feed to retrieve. If an array of URLs, the feeds are merged.
         */
        do_action_ref_array( 'wp_feed_options', array(&$feed, $url) );
        $feed->init();
        $feed->set_output_encoding( get_option( 'blog_charset' ) );
        if ( $feed->error() ) {
            return new WP_Error('simplepie-error', $feed->error());
        }
        return $feed;
    }

    /**
     * Defaults to 'fetch_feed()' to load the feed but provides fallback fetch feed alternatives in case of errors.
     *
     * @since 1.3.2
     */
    public static function fetch_feed( $url, $provider ) {
        global $goft_wpjm_options;
        add_action(
            'wp_feed_options',
            array(__CLASS__, 'set_feed_options'),
            10,
            2
        );
        $feed = fetch_feed( $url );
        $valid_feed = true;
        if ( is_wp_error( $feed ) ) {
            if ( apply_filters( 'goft_wpjm_fetch_feed_simplexml', false !== stripos( $feed->get_error_message(), 'feed could not be found at' ) ) ) {
                $context = stream_context_create( array(
                    'http' => array(
                        'header'        => 'Accept: application/xml',
                        'ignore_errors' => true,
                    ),
                ) );
                $xml = @file_get_contents( $url, false, $context );
                $feed = self::fetch_feed_raw_data( $xml, $url );
                $valid_feed = !is_wp_error( $feed );
            } elseif ( apply_filters( 'goft_wpjm_fetch_feed_crossorigin', $goft_wpjm_options->use_cors_proxy && is_wp_error( $feed ) && false !== strpos( $url, 'https' ) ) ) {
                // Try again with CORS proxy.
                $url = 'http://crossorigin.me/' . $url;
                // Use 'file_get_contents()' to check for error and avoiding 'fetch_feed' to hang.
                $context = stream_context_create( array(
                    'http' => array(
                        'header'        => 'Accept: application/xml\\r\\nOrigin: ' . home_url(),
                        'ignore_errors' => true,
                    ),
                ) );
                $valid_feed = @file_get_contents( $url, false, $context );
                if ( $valid_feed ) {
                    $feed = fetch_feed( $url );
                }
            }
        }
        if ( apply_filters( 'goft_wpjm_fetch_feed_force', is_wp_error( $feed ) && $valid_feed ) ) {
            self::$goft_wpjm_force_feed = true;
            // Temporarily disable SSL verify if forcing feed to load.
            add_filter( 'https_ssl_verify', '__return_false' );
            $feed = fetch_feed( $url );
            // Restore SSL verify if forcing feed to load.
            add_filter( 'https_ssl_verify', '__return_true' );
        }
        remove_action(
            'wp_feed_options',
            array(__CLASS__, 'set_feed_options'),
            10,
            2
        );
        return $feed;
    }

    /**
     * Split the list in chunks for a given array count.
     */
    protected static function maybe_chunkify_list( $list, $max = 10 ) {
        if ( count( $list ) <= $max ) {
            return $list;
        }
        // Separate the items list in chunks to avoid DB errors with big RSS feeds.
        return array_chunk( $list, $max );
    }

    /**
     * Cache the RSS feed in the database.
     */
    public static function cache_feed_items( $items, $expiration = 3600 ) {
        global $_wp_using_ext_object_cache, $goft_wpjm_options;
        // Temporarily turn off the object cache while we deal with transients since
        // some caching plugins like W3 Total Cache conflicts with our work.
        $_wp_using_ext_object_cache_previous = $_wp_using_ext_object_cache;
        $_wp_using_ext_object_cache = false;
        // If items are not separated in chunks make sure we have an array of arrays.
        if ( empty( $items[0][0] ) ) {
            $chunks[] = $items;
        } else {
            $chunks = $items;
        }
        $skip_chunks = false;
        foreach ( $chunks as $key => $chunk ) {
            delete_transient( "_goft-rss-feed-{$key}" );
            $result = set_transient( "_goft-rss-feed-{$key}", $chunk, $expiration );
            if ( !$result ) {
                $skip_chunks = true;
                break;
            }
        }
        if ( !$skip_chunks ) {
            set_transient( '_goft-rss-feed-chunks', count( $chunks ), $expiration );
        } else {
            delete_transient( '_goft-rss-feed-chunks' );
            // __LOG.
            $vars = array(
                'context' => 'GOFT :: SITE DOES NOT SUPPORT TRANSIENTS! SKIPPED!',
            );
            BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
            // __END LOG.
        }
        // Clear memory.
        $chunks = null;
        // Restore the caching values.
        $_wp_using_ext_object_cache = $_wp_using_ext_object_cache_previous;
    }

    // __Helpers.
    /**
     * Retrieves external links considering custom user arguments.
     */
    public static function add_query_args( $params, $link ) {
        if ( empty( $params['source']['args'] ) && empty( $params['args'] ) ) {
            return add_query_arg( apply_filters( 'goft_wpjm_external_link_qargs', array(), $params ), $link );
        }
        $args = ( !empty( $params['source']['args'] ) ? $params['source']['args'] : $params['args'] );
        $qargs = array();
        $query_args = explode( ',', $args );
        foreach ( $query_args as $arg ) {
            if ( $temp_qargs = explode( '=', $arg ) ) {
                $qargs = array_merge( $qargs, array(
                    trim( $temp_qargs[0] ) => trim( $temp_qargs[1] ),
                ) );
            }
        }
        return add_query_arg( apply_filters( 'goft_wpjm_external_link_qargs', $qargs, $params ), $link );
    }

    /**
     * Retrieves parts from an item text using regex patterns.
     */
    private static function get_item_regex_parts( $text, $patterns ) {
        $parts = array();
        foreach ( (array) $patterns as $key => $pattern ) {
            preg_match( $pattern, html_entity_decode( $text ), $matches );
            end( $matches );
            $last_index = key( $matches );
            // Skip anything longer then 50 chars as it's probably fetching wrong data.
            if ( !empty( $matches[$last_index] ) && strlen( trim( $matches[$last_index] ) ) < 50 ) {
                $parts[$key] = trim( $matches[$last_index] );
            }
        }
        return $parts;
    }

    /**
     * Try to retrieve all the namespaces within an RSS feed.
     */
    private static function get_namespaces_for_feed( $url, $convert = false ) {
        $namespaces = array();
        $feed = @file_get_contents( $url );
        // If we don't get XML data try using 'wp_remote_get'.
        if ( !$feed ) {
            $response = wp_remote_get( $url );
            if ( !is_wp_error( $response ) ) {
                $feed = wp_remote_retrieve_body( $response );
            }
        }
        if ( $convert && function_exists( 'iconv' ) ) {
            // Ignore errors with some UTF-8 feed.
            $feed = iconv( 'UTF-8', 'UTF-8//IGNORE', $feed );
        } elseif ( !function_exists( 'iconv' ) ) {
            // __LOG.
            $fetch_start_time = current_time( 'timestamp' );
            $vars = array(
                'context' => 'GOFT :: GET NAMESPACES SKIPPED :: ICONV() NOT INSTALLED',
                'url'     => $url,
            );
            BC_Framework_Debug_Logger::log( $vars, $goft_wpjm_options->debug_log );
        }
        try {
            libxml_use_internal_errors( true );
            $xml = new SimpleXmlElement($feed);
            $feed = null;
            if ( empty( $xml->channel->item ) ) {
                return $namespaces;
            }
            foreach ( $xml->channel->item as $entry ) {
                $curr_namespaces = $entry->getNameSpaces( true );
                $namespaces = array_merge( $namespaces, $curr_namespaces );
            }
            libxml_clear_errors();
        } catch ( Exception $e ) {
            if ( !$convert ) {
                self::get_namespaces_for_feed( $url, true );
            }
        }
        // Clear memory.
        $feed = $xml = null;
        return $namespaces;
    }

    /**
     * Applies additional processing to an item.
     */
    public function processed_feed_item( $item, $provider ) {
        if ( is_a( $item, 'SimplePie_Item' ) || !is_array( $item ) ) {
            return $item;
        }
        if ( apply_filters(
            'goft_wpjm_import_apply_regexp',
            !empty( $provider['feed']['regexp_mappings'] ),
            $provider,
            $item
        ) ) {
            $item = apply_filters(
                'goft_wpjm_import_item_apply_regexp',
                $item,
                $provider,
                $item
            );
        }
        if ( !empty( $item['title'] ) ) {
            $item['title'] = apply_filters( 'goft_wpjm_item_title', $item['title'], $provider );
        }
        return $item;
    }

    /**
     * Apply regular expressions to a XML item.
     */
    public static function item_apply_regexp( $new_item, $provider, $item ) {
        $description = $item['description'];
        // If there are regexp tags defined, iterate through them.
        if ( !empty( $provider['feed']['regexp_mappings'] ) ) {
            $search_parts['title'] = $new_item['title'];
            $search_parts['description'] = $new_item['description'];
            // Should we do regexp matching over specific item tags, like 'title' or 'description' ?
            if ( !empty( $provider['feed']['regexp_mappings']['title'] ) || !empty( $provider['feed']['regexp_mappings']['description'] ) ) {
                // If so, these will override namespace tags matching done later below (as an exception).
                foreach ( $provider['feed']['regexp_mappings'] as $tag => $regexp ) {
                    $parts = self::get_item_regex_parts( $search_parts[$tag], $regexp );
                    if ( $parts ) {
                        $new_item = array_merge( $new_item, $parts );
                        //$custom_tags = array_merge( $custom_tags, array_keys( $parts ) );
                        // As an exception, give priority to regexp matches in 'title' or 'description' over existing namespaces tags iteration.
                        $skip_tags[key( $parts )] = key( $parts );
                    }
                }
                // Otherwise, do regexp mappings only on the item 'description' tag.
            } else {
                $parts = self::get_item_regex_parts( $description, $provider['feed']['regexp_mappings'] );
                if ( $parts ) {
                    $new_item = array_merge( $new_item, $parts );
                    //$custom_tags = array_merge( $custom_tags, array_keys( $parts ) );
                }
            }
        } else {
            // Get all the valid item tags for the providers.
            $valid_regexp_tags = GoFetch_RSS_Providers::valid_regexp_tags();
            // ... Otherwise, iterate through the defaults.
            foreach ( $valid_regexp_tags as $key => $patterns ) {
                foreach ( $patterns as $pattern ) {
                    $parts = self::get_item_regex_parts( $description, array(
                        $key => $pattern,
                    ) );
                    if ( $parts ) {
                        break;
                    }
                }
                if ( !empty( $parts[$key] ) ) {
                    $new_item = array_merge( $new_item, $parts );
                    //$custom_tags = array_merge( $custom_tags, array_keys( $parts ) );
                }
            }
        }
        return $new_item;
    }

    /**
     * Imports an image to the DB.
     */
    private static function sideload_import_image( $url, $post_id ) {
        $image = media_sideload_image( $url, $post_id );
        return $image;
    }

    /**
     * Try to retrieve a valid formatted date from the feed item.
     *
     * @since 1.3.1
     */
    public static function get_valid_date( $item, $type = 'rss' ) {
        if ( 'rss' === $type ) {
            $date = $item->get_date( 'Y-m-d' );
            // If the date is empty try to get the data directly checking the most common date tags.
            if ( !$date ) {
                foreach ( array('pubdate', 'date') as $tag ) {
                    $date = $item->get_item_tags( '', $tag );
                    if ( !empty( $date[0]['data'] ) ) {
                        $date = date( 'Y-m-d', strtotime( html_entity_decode( $date[0]['data'] ) ) );
                        break;
                    }
                }
            }
        } else {
            $date = $item;
        }
        // Automatically default invalid dates like '1970-01-01' to the current date.
        if ( strtotime( $date ) < strtotime( '2000-01-01' ) ) {
            $date = current_time( 'Y-m-d' );
        }
        return wp_strip_all_tags( $date );
    }

    /**
     * Formats a given RSS feed description.
     *
     * @since 1.3
     */
    public static function format_description( $description ) {
        if ( is_array( $description ) ) {
            $description = implode( '', $description );
        }
        $formatted_description = html_entity_decode( $description );
        return apply_filters( 'goft_wpjm_format_description', trim( $formatted_description ), $description );
    }

    /**
     * Return a shortened description.
     */
    public static function shortened_description( $description, $length = 100 ) {
        if ( is_array( $description ) ) {
            $description = implode( '', $description );
        }
        $shortened = '';
        if ( strlen( $description ) > 100 ) {
            $shortened = '...';
        }
        return substr( wp_strip_all_tags( $description ), 0, $length ) . $shortened;
    }

    /**
     * Strips tags from text.
     */
    public static function strip_tags( $text ) {
        $allowed_tags = wp_kses_allowed_html( 'post' );
        $allowed_tags = apply_filters( 'goft_wpjm_allowed_tags', $allowed_tags );
        $text = wp_kses( $text, $allowed_tags );
        // @todo: make sure links are not stripped of the 'href'.
        // Remove all attributes from all tags.
        return preg_replace( '/<(?!a\\s)([a-z][a-z0-9]*)[^>]*?(\\/?)>/i', '<$1$2>', $text );
    }

    /**
     * Make a job title, prettier, if there'a regexp set for the provider..
     */
    public static function prettify_title( $title, $provider ) {
        if ( !empty( $provider['feed']['regexp_title'] ) ) {
            preg_match( $provider['feed']['regexp_title'], $title, $matches );
            if ( !empty( $matches[1] ) ) {
                $title = $matches[1];
            }
        }
        return $title;
    }

    /**
     * Generates a job key for a single item and retrieves it.
     */
    public static function generate_job_key( $url ) {
        if ( !$url ) {
            return false;
        }
        $url_parts = parse_url( $url );
        $host = '';
        if ( !empty( $url_parts['host'] ) ) {
            $host = $url_parts['host'];
        }
        return md5( $host );
    }

    /**
     * Unset specific attributes from the sample job.
     */
    public function sample_item( $item, $provider ) {
        $item['provider_id'] = null;
        unset($item['provider_id']);
        return $item;
    }

    /**
     * Validate an item before importing it.
     */
    public function validate_item( $valid, $item, $params ) {
        // @todo: check if 'link' is valid.
        return $valid;
    }

    /**
     * Set core attributes on each imported item.
     */
    public static function set_item_core_atts( $item, $provider, $url ) {
        $defaults = array(
            'provider_id' => $provider['id'],
            'date'        => current_time( 'mysql' ),
            'location'    => '',
            'company'     => '',
        );
        $item = wp_parse_args( $item, $defaults );
        $item['jobfeed'] = $url;
        return $item;
    }

    /**
     * Identify unknown keys in the feed item and merge them.
     */
    public static function merge_unknown_items(
        $item,
        $provider,
        $url,
        $original_item = array(),
        $defaults = array()
    ) {
        if ( empty( $original_item ) ) {
            return $item;
        }
        $keys = array_keys( $original_item );
        $unknown_keys = array_diff( $keys, array_keys( $defaults ) );
        $other_item = array();
        foreach ( $unknown_keys as $key ) {
            $other_item[$key] = $original_item[$key];
        }
        return array_merge( $item, $other_item );
    }

    /**
     * Unset core attributes and others from the sample item.
     */
    public static function unset_sample_item_core_atts( $sample_item, $provider, $orig_item ) {
        if ( empty( $sample_item ) ) {
            return $sample_item;
        }
        $defaults = array(
            'link_atts' => '',
            'jobkey'    => '',
            'jobfeed'   => '',
        );
        $sample_item = wp_parse_args( (array) $sample_item, $defaults );
        $unset = array_intersect_key( $defaults, $sample_item );
        foreach ( $unset as $key => $value ) {
            if ( isset( $sample_item[$key] ) ) {
                unset($sample_item[$key]);
            }
        }
        if ( isset( $sample_item['description'] ) ) {
            $sample_item['description'] = wp_strip_all_tags( html_entity_decode( $sample_item['description'] ) );
        }
        if ( isset( $sample_item['logo'] ) ) {
            $sample_item['logo'] = sprintf( '<img src="%s" style="max-height: 32px; max-width: 100%%;" />', esc_url( str_replace( '%', '%%', $sample_item['logo'] ) ) );
        }
        return $sample_item;
    }

    /**
     * Display message about providers that actively block requests.
     */
    public function provider_blocked_warning( $error, $provider, $url_or_data ) {
        if ( 'indeed.com' === $provider ) {
            $message = '<br/><br/>' . __( '<strong>IMPORTANT</strong>', 'gofetch-wpjm' );
            $message .= '<br/>' . __( '<em>Inded</em>, has recently started blocking HTTP requests to their feeds. You can try reducing the number of requests you do their site, to see if it helps.', 'gofetch-wpjm' );
            $message .= __( 'If you keep seeing this error, we recommend using an alternative provider.', 'gofetch-wpjm' );
            $message .= '<br/><br/>' . __( 'Unfortunately, this is something we can\'t control. <em>Indeed\'s</em> feeds might be removed soon, if we find it to be blocking 100% of the requests.', 'gofetch-wpjm' );
            $message = new WP_Error('-1006', $message);
        }
        return $error;
    }

    /**
     * Override the job date, if the user selected the non-default value.
     */
    public function maybe_override_job_date( $date ) {
        global $goft_wpjm_options;
        if ( $goft_wpjm_options->job_date === 'current' ) {
            $date = current_time( 'Y-m-d' );
        }
        return $date;
    }

}

new GoFetch_Importer();