<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The class responsible for displaying the Add-ons browser.
 */
class BC_Framework_Plugin_Browser_List extends WP_List_Table {

	/**
	 * Additional arguments for the list table.
	 * @var string
	 */
	protected $args;

	/**
	 * The list table page slug.
	 * @var string
	 */
	protected $page_slug;

	/**
	 * The list table menu parent.
	 * @var string
	 */
	protected $menu_parent;

	/**
	 * The list of tabs.
	 * @var array
	 */
	protected $tabs;

	/**
	 * The errors returned during the items request.
	 * @var object
	 */
	protected $error;

	/**
	 * Constructor.
	 *
	 * Overrides the list class to display wp_sh_plugin_browser add-ons.
	 *
	 * @param string $page_slug   The page slug name.
	 * @param string $menu_parent The menu parent name.
	 * @param array  $args        Additional args for the list.
	 * @param bool   $refresh     Should the list be retrieved from the source directly or from the cache.
	 */
	public function __construct( $page_slug, $menu_parent, $args = array() ) {

		$defaults = array(
			'page'     => 1,
			'per_page' => 30,
		);
		$this->args = wp_parse_args( $args, $defaults );

		$this->page_slug   = $page_slug;
		$this->menu_parent = $menu_parent;

		// Make sure we run this only on our custom plugins page.
		if ( ! $this->condition() ) {
			return;
		}

		parent::__construct( $this->args );

		if ( current_user_can( 'install_plugins' ) ) {
			wp_enqueue_style( 'plugin-install' );
			wp_enqueue_script( 'plugin-install' );
			add_thickbox();
		}

		$this->prepare_items( $this->args );
	}

	/**
	 * Get a list of columns.
	 */
	function get_columns(){}

	/**
	 * Condition check for displaying the plugin browser page.
	 *
	 * @return boolean True if the plugin browser page should be displayed, False otherwise.
	 */
	function condition() {
		return ! empty( $_GET['page'] ) && $this->page_slug === $_GET['page']; // Input var okay.
	}

	/**
	 * Prepares the items before they are displayed.
	 *
	 * @param array $filters_list A list of pre-set filter/values provided to the module.
	 */
	public function prepare_items( $args = '', $refresh = false ) {
		global $tab;

		$tabs = apply_filters( "bc_plugin_browser_list_tabs_{$this->page_slug}", $this->args['tabs'] );

		// If a non-valid menu tab has been selected, and it's not a non-menu action.
		if ( empty( $this->args['tab'] ) || ( ! isset( $tabs[ $this->args['tab'] ] ) ) ) {
			$tab = key( $tabs );
		}

		$this->tabs = $tabs;

		$this->items = $this->get_items();

		// Something went wrong - skip earlier.
		if ( ! $this->items ) {
			return;
		}

		// Apply filters to the list of items.
		$this->set_items_filtered();
	}

	/**
	 * Query WordPress hosted plugins through WP plugins API.
	 */
	protected function query_plugins_api( $tab, $args = array() ) {

		// Fields to query on the plugins API.
		$defaults = array(
			'fields' => array(
				'slug'              => true,
				'short_description' => true,
				'icons'             => true,
				'rating'            => true,
				'ratings'           => true,
				'last_updated'      => true,
				'added'             => true,
			)
		);
		$wp_hosted_args = wp_parse_args( $args, $defaults );

		$results = array();

		// Only check for WordPress hosted plugins if there are custom args to search the API.
		if ( $wp_hosted_args && ! $wp_hosted_args !== $defaults ) {

			if ( ! function_exists('plugins_api') ) {
				include_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
			}

			// Query the plugins API for installed plugins.
			$api = plugins_api( 'query_plugins', $wp_hosted_args );

			if ( ! empty( $api->plugins ) ) {

				foreach( $api->plugins as $plugin ) {
					$plugin = (object) $plugin;
					$results[ $plugin->slug ] = $plugin;
				}
			}
		}
		return $results;
	}

	/**
	 * Fetches the plugins from cache (if not expired) or from a XML file, directly.
	 *
	 * @param int  $limit   The number of plugins to retrieve from the XML file.
	 * @param bool $refresh Should the content be fresh or read from the cache.
	 * @return array        The list of plugins.
	 */
	public function get_items( $limit = 0, $refresh = false ) {

		$transient = '_wp_sh_plugin_browser-list-' . $this->page_slug . '-' . $this->args['tab'];

		if ( $refresh || ( false === ( $products = get_transient( $transient ) ) ) ) {

			$tab = $this->args['tab'];

			// If there's not URL for a tab, fallback to the default. Plugins are added considering criteria on the default XML file.
			if ( empty( $this->args['tabs'][ $tab ]['url'] ) ) {
				$tab = $this->args['default_tab'];

				// Skip earlier if there's no default URL for the products catalogue.
				if ( empty( $this->args['tabs'][ $tab ]['url'] ) ) {
					return;
				}

			}
			$url = wp_strip_all_tags( $this->args['tabs'][ $tab ]['url'] );

			$response = wp_remote_get( $url );

			if ( is_wp_error( $response ) ) {
				return;
			}

			$xml = new SimpleXMLElement( $response['body'] );

			$items = $xml->products->product;

			if ( empty( $items ) ) {
				return;
			}

			$products = $this->set_products( $items, $this->args['tab'], $dynamic_tab = empty( $this->args['tabs'][ $this->args['tab'] ]['url'] ) );

			// Cache the results.
			set_transient( $transient, $products, WEEK_IN_SECONDS );
		} else {

			// Set any applicable filters to the items list - otherwise set when building the plugin list.
			$this->set_filters( $products );

		}
		return $products;
	}

	/**
	 * Build the dynamic filter list using item values for later use in dropdowns.
	 *
	 * @param array $items A single or list of items to look for filter values.
	 */
	public function set_filters( $items ) {

		if ( empty( $this->args['filters'] ) ) {
			return;
		}

		$filters_keys = array_keys( $this->args['filters'] );

		$filters = array();

		if ( ! isset( $items[0] ) ) {
			$items = array( $items );
		}

		foreach( $items as $plugin ) {

			// Build the dynamic filters, considering the XML tags specified in the 'filters' parameter.

			/**
			 * Valid filter tags XML syntax:
			 *
			 * <tag>tag1, tag2<tag>
			 *
			 * OR
			 *
			 * <tags>
			 *   <tag>
			 *     <name>tag1</name>
			 *   </tag>
			 * </tags>
			 *
			 */

			foreach( $filters_keys as $key ) {

				if ( ! empty( $plugin[ $key ] ) ) {

					$filter = (array) $this->args['filters'][ $key ];
					$title  = reset( $filter );

					$tags  = $plugin[ $key ];

					if ( ! empty( $tags['name'] ) ) {
						$tags = $tags['name'];
					}

					if ( ! is_array( $tags ) )  {
						$tags = explode( ',', $tags );
					}

					$f_tags = array();

					foreach( (array) $tags as $index => $tag ) {

						if ( empty( $tag[0] ) ) {
							$tag = array( $tag );
						}

						foreach( (array) $tag as $i => $t ) {

							if ( ! empty( $t['name'] ) ) {
								$tag_name = $t['name'];
							} else {
								$tag_name = $t;
							}

							$tag_name = trim( strip_tags( $tag_name ) );

							$filters[ $key ][ '' ]        = trim( strip_tags( $title ) );
							$filters[ $key ][ $tag_name ] = $tag_name;

							$f_tags[] = $tag_name;
						}
					}

					// Store the filter key/value(s) pair(s) on a special filter key.
					$plugin[ 'f_' . $key ] = $f_tags;


					if ( ! is_array( $this->args['filters'][ $key ] ) ) {
						$this->args['filters'][ $key ] = $filters[ $key];
					} else {
						$this->args['filters'][ $key ] = array_merge( $this->args['filters'][ $key ], $filters[ $key] );
					}
				}
			}
		}

		if ( empty( $plugin ) ) {
			$plugin = $items;
		}
		return $plugin;
	}

	/**
	 * Applies any user filters and pagination to the list of items.
	 */
	public function set_items_filtered() {

		$filter_by   = $this->get_filter_by();

		$this->items = $this->list_filter( $this->items, $filter_by );

		// Look for a keyword search.
		if ( ! empty( $_GET['s'] ) ) { // Input var okay.

			$keyword = sanitize_text_field( wp_unslash( $_GET['s'] ) ); // Input var okay.
			$keyword = wp_strip_all_tags( $keyword );

			$filter_by = array( 'title' => $keyword, 'description' => $keyword );

			$this->items = $this->list_filter( $this->items, $filter_by, $operator = 'OR', $match = true );
		}

		$this->set_pagination_args( array(
			'total_items' => count( $this->items ),
			'per_page'    => $this->args['per_page'],
		) );

		// Limit the plugins list based on the current page.
		$this->items = array_slice( $this->items, ( $this->args['page'] - 1 ) * $this->args['per_page'], $this->args['per_page'] );
	}

	/**
	 * Outputs the available tabs.
	 */
	protected function get_views() {
		$display_tabs = array();

		$admin_url = strpos( $this->menu_parent, '.php' ) === false ? self_admin_url( 'admin.php' ) : self_admin_url( $this->menu_parent );

		foreach ( (array) $this->tabs as $action => $text ) {
			$class = ( $action === $this->args['tab'] ) ? ' current' : '';
			$href = add_query_arg( array( 'page' => $this->page_slug, 'tab' => $action ), $admin_url );
			$display_tabs[ admin_url( "products-install-{$action}" ) ] = "<a href='" . esc_url( $href ) . "' class='" . esc_attr( $class ) . "'>" . wp_kses_post( $text['name'] ) . '</a>';
		}

		return $display_tabs;
	}

	/**
	 * Outputs the filters.
	 */
	protected function get_filters() {

		$filters = '';

		// Get all available filters.
		if ( ! ( $filter_list = $this->get_filter_list() ) ) {
			return $filters;
		}

		// Get any user requested filters.
		$filter_by = $this->get_filter_by();

		// Iterate through all the filters to build the drop-downs.
		foreach ( $filter_list as $key => $filter ) {

			$options = '';

			if ( ! is_array( $filter ) ) {
				continue;
			}

			foreach ( $filter as $group => $items ) {

				$group_options = '';

				foreach ( (array) $items as $slug => $title ) {

					$value = ! empty( $filter_by[ $key ] ) ? $filter_by[ $key ]  : '';

					$atts['value'] = $slug ? $slug : $group;

					if ( $atts['value'] === $value ) {
						$atts['selected'] = 'selected';
					} else {
						unset( $atts['selected'] );
					}
					$option = html( 'option', $atts, $title );

					if ( $slug ) {
						$group_options .= $option;
					} else {
						$options .= $option;
					}
				}

				// Group dropdown items if requested.
				if ( ! empty( $group_options ) ) {
					$options .= html( 'optgroup', array( 'label' => $group ), $group_options );
				}
			}

			$filters .= html( 'select', array( 'name' => esc_attr( "$key" ), 'class' => '' ), $options );
		}
		return $filters;
	}

	/**
	 * Override parent views so we can use the filter bar display.
	 */
	public function views() {

		$views = $this->get_views();

		/** This filter is documented in wp-admin/inclues/class-wp-list-table.php */
		$views = apply_filters( "views_{$this->screen->id}", $views );
?>
		<div class="clear"></div>
<?php
		/**
		 * Fires before the plugin list is displayed.
		 */
		do_action( "bc_plugin_browser_list_before_filter_{$this->page_slug}" );
?>
		<div class="wp-filter">
			<ul class="filter-links">
				<?php
				if ( ! empty( $views ) ) {
					foreach ( $views as $class => $view ) {
						$class = esc_attr( $class );
						$views[ $class ] = "\t<li class='" . esc_attr( $class ) ."'>$view";
					}

					echo implode( " </li>\n", array_map( 'wp_kses_post', $views ) ) . "</li>\n";
				}
				?>
			</ul>

			<?php
				if ( 'true' == $this->args['search'] ) {
					$this->search_form();
				}
			?>
		</div>
<?php
		/**
		 * Fires before the add-ons mp table is displayed.
		 *
		 * Recommended for marketplace marketing campaigns.
		 */
		do_action( "bc_plugin_browser_list_before_table_{$this->page_slug}" );
	}

	/**
	 * Outputs the page content.
	 */
	public function display() {
		$singular = $this->_args['singular'];

		$data_attr = '';

		if ( $singular ) {
			$data_attr = " data-wp-lists='list:$singular'";
		}

		$this->display_tablenav( 'top' );
?>
		<div class="wp-list-table <?php echo esc_attr( implode( ' ', $this->get_table_classes() ) ); ?>">

			<div id="the-list" <?php echo esc_attr( $data_attr ); ?> >
				<?php $this->display_rows_or_placeholder(); ?>
			</div>
		</div>
<?php
		$this->display_tablenav( 'bottom' );
	}

	/**
	 * Outputs the pagination bar.
	 *
	 * @param string $which The position for the pagination bar: 'top' or 'bottom'.
	 */
	protected function display_tablenav( $which ) {

		if ( 'top' === $which ) :
			wp_referer_field();
		?>

			<div class="tablenav top">
				<div class="alignleft actions">
					<?php
					/**
					 * Fires before the table header pagination is displayed.
					 */
					do_action( "bc_plugin_browser_list_table_header_{$this->page_slug}" ); ?>
				</div>
				<?php $this->pagination( $which ); ?>
				<br class="clear" />
			</div>

		<?php else : ?>

			<div class="tablenav bottom">
				<?php $this->pagination( $which ); ?>
				<br class="clear" />
			</div>

		<?php
		endif;
	}

	/**
	 * Retrieve a list of CSS classes to be used on the table listing.
	 *
	 * @return array The list of CSS classes.
	 */
	protected function get_table_classes() {
		return array( 'widefat', $this->_args['plural'] );
	}

	/**
	 * Outputs the search form.
	 */
	private function search_form() {

		if ( isset( $_REQUEST['s'] ) ) { // Input var okay.
			$term = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ); // Input var okay.
		} else {
			$term = '';
		}
?>
		<form id="wp-shp-browser" class="search-form search-plugins" method="get" action="">
			<ul class="filter-links plugins-browser-filter">
				<?php echo wp_kses_post( $this->get_filters() ); ?>
			</ul>
			<label>
				<span class="screen-reader-text"><?php echo __( 'Search Plugins', 'wp-shp-browser' ); ?></span>
				<input type="search" name="s" value="<?php echo esc_attr( $term ) ?>" class="wp-filter-search" placeholder="<?php echo esc_attr__( 'Search Plugins', 'wp-shp-browser' ); ?>">
			</label>
			<input type="submit" name="" id="search-submit" class="button screen-reader-text" value="<?php echo esc_attr__( 'Search Plugins', 'wp-shp-browser' ); ?>">
			<input type="hidden" name="page" value="<?php echo ( isset( $_REQUEST['page'] ) ? esc_attr( sanitize_text_field( $_REQUEST['page'] ) ) : '' ); ?>">
			<input type="hidden" name="tab" value="<?php echo ( isset( $_REQUEST['tab'] ) ? esc_attr( sanitize_text_field( $_REQUEST['tab'] ) ) : '' ); ?>">
		</form>
<?php
	}

	/**
	 * Retrieves all the add-ons from a XML file as an array of objects.
	 *
	 * @param array  $items List of XML objects.
	 * @param string $tab   The tab being used.
	 * @return array        List of plugins as stdClass objects.
	 */
	public function set_products( $items, $tab, $dynamic_tab = false ) {

		$api_plugins       = $this->query_plugins_api( $tab, $this->args['wp_hosted_args'] );
		$installed_plugins = get_plugins();

		$plugins_allowedtags = array(
			'a'    => array( 'href' => array(),'title' => array(), 'target' => array() ),
			'abbr' => array( 'title' => array() ),'acronym' => array( 'title' => array() ),
			'code' => array(), 'pre' => array(), 'em' => array(),'strong' => array(),
			'ul'   => array(), 'ol' => array(), 'li' => array(), 'p' => array(), 'br' => array()
		);

		$sorted_items = array();

		$defaults = array(
			'name'        => '',
			'url'         => '',
			'file'        => '',
			'description' => '',
			'icons'       => array(),
			'author'      => '',
			'version'     => '',
			'wordpress'   => true,
			'host' => array(
				'name' => 'WordPress Org',
				'url'  => 'https://wordpress.org/plugins/',
			),
			'price_min'     => '',
			'price_max'     => '',
			'regular_price' => '',
			'added'         => current_time('mysql'),
			'last_updated'  => '',
			'rank'          => 1,
			'rating'        => '',
			'num_ratings'   => 0,
			'tabs'          => '',
		);

		$filters = array();

		foreach ( $items as $key => $item ) {

			$plugin = BC_Framework_Plugin_Browser_Core::xml2array( $item );

			if ( empty( $plugin['slug'] ) ) {
				continue;
			}

			// Check if plugin is WordPress hosted and get the info from WP directly.
			if ( ! empty( $api_plugins[ $plugin['slug'] ] ) ) {

				// WordPress hosted plugin.

				$wp_plugin = $api_plugins[ $plugin['slug'] ];

				$plugin = array_merge( (array) $wp_plugin, $plugin );

				$plugin['wordpress']   = true;
				$plugin['description'] = $wp_plugin->short_description;
				$plugin['url']         = 'https://wordpress.org/plugins/' . $plugin['slug'];

			} else {

				// Self-hosted plugin.

				$plugin['wordpress'] = false;

				// Icons.

				if ( ! empty( $plugin['icons'] ) ) {

					foreach( $plugin['icons'] as $key => $icons ) {
						$wp_icons[ str_replace( '_', '', $key ) ] = $icons;
					}

					$plugin['icons'] = $wp_icons;

				}
			}

			// Author(s).

			// Default the 'authors' key to the vendor name if empty.
			if ( empty( $plugin['authors'] ) && ! empty( $this->args['vendor']['name'] ) ) {
				$plugin['authors']['author'] = array(
					'name' => $this->args['vendor']['name'],
					'url'  => $this->args['vendor']['url'],
				);
			}

			if ( empty( $plugin['author'] ) && ! empty( $plugin['authors']['author'] ) ) {

				$plugin['author'] = '';

				$authors = $plugin['authors']['author'];

				if ( empty( $plugin['authors']['author'][0] ) ) {
					$authors = array( $plugin['authors']['author'] );
				}

				foreach( $authors as $author ) {
					$plugin['author'] .= $plugin['author'] ? ', ' : '';
					$plugin['author'] .= html( 'a', array( 'href' => esc_url( $author['url'] ), 'rel' => 'nofollow' ), wp_kses( $author['name'], $plugins_allowedtags ) );
				}

			}

			$plugin = apply_filters( 'bc_plugin_browser_plugin_data', wp_parse_args( $plugin, $defaults ) );

			// Sanitize fields.

			$plugin['title']       = wp_kses( $plugin['name'], $plugins_allowedtags );
			$plugin['name']        = strip_tags( $plugin['title'] . ' ' . $plugin['version'] );
			$plugin['description'] = strip_tags( $plugin['description'] );
			$plugin['version']     = wp_kses( $plugin['version'], $plugins_allowedtags );;

			$plugin['price_min']   = wp_kses( $plugin['price_min'], $plugins_allowedtags );
			$plugin['price_max']   = wp_kses( $item['price_max'], $plugins_allowedtags );

			$plugin['added']       = strip_tags( $plugin['added'] );

			// Requirements.

			if ( ! empty( $plugin['requirements'] ) ) {

				if ( empty( $plugin['requirements']['requirement'] ) ) {
					$plugin['requirements'] = array( 'requirement' => $plugin['requirements'] );
				}

				foreach( $plugin['requirements'] as $key => $requirement ) {

					$requirements = '';

					if ( empty( $requirement[0] ) ) {
						$requirement = array( $requirement );
					}

					foreach( (array) $requirement as $k => $req ) {

						if ( ! empty( $req['name'] ) ) {
							$req_name = $req['name'];
						} else {
							$req_name = $req;
						}

						$req_name = trim( strip_tags( $req_name ) );

						$requirements .= $requirements ? ', ' : '';

						if ( ! empty( $req['url'] ) ) {
							$requirements .= html( 'a', array( 'href' => esc_url( $req['url'] ), 'rel' => 'nofollow' ), $req_name );
						} else {
							$requirements .= $req_name;
						}

					}

				}

				$plugin['requirements'] = $requirements;
			} else {
				$plugin['requirements'] = __( 'None', 'wp-shp-browser' );
			}


			// Status

			$plugin['status'] = $this->install_status( $plugin, $installed_plugins );


			// Filters.

			// Set any applicable filters for the plugin.
			$plugin = $this->set_filters( $plugin );


			/**
			 * Add the plugin to the current tab.
			 *
			 * If the 'tabs' tag exist the plugin is only added if the current tab is in the 'tabs' list .
			 */

			$tabs = $plugin['tabs'];

			if ( $tabs ) {
				$tabs = explode( ',', $tabs );
				$tabs = array_map( 'trim', $tabs );
				$tabs = array_map( 'strip_tags', $tabs );
			}

			if ( 'popular' === $tab ) {

				// Mark popular plugins using the 'num_ratings' or if the 'tabs' tag contains 'popular'.
				if ( ! empty( $this->args['popular_min_rates'] ) && $plugin['num_ratings'] >= $this->args['popular_min_rates'] || $tabs && in_array( $tab, $tabs ) ) {
					$products[ $plugin['num_ratings'] ][] = $plugin;
				}

			} elseif( ! $dynamic_tab || ( $tabs && in_array( $tab, $tabs ) ) ) {
				$products[ strtotime( $plugin['added'] ) ][] = $plugin;
			}
		}

		krsort( $products, SORT_NUMERIC );

		$products = call_user_func_array( 'array_merge', $products );

		return $products;
	}

	/**
	 * Outputs a given plugin using custom markup.
	 *
	 * @param array $plugin Plugin informatio.
	 */
	public function single_row( $plugin ) {

		$action_links = $links = array();

		$status = $plugin['status'];

		if ( $plugin['wordpress'] ) {

			if ( current_user_can( 'install_plugins' ) || current_user_can( 'update_plugins' ) ) {

				switch ( $status['status'] ) {

					case 'install':
						if ( $status['url'] ) {
							$action_links[ $status['status'] ] = '<a class="install-now button" data-slug="' . esc_attr( $plugin['slug'] ) . '" href="' . esc_url( $status['url'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Install %s now' ), $plugin['name'] ) ) . '" data-name="' . esc_attr( $plugin['name'] ) . '">' . __( 'Install Now' ) . '</a>';
						}
						break;

					case 'update_available':
						if ( $status['url'] ) {
							$action_links[ $status['status'] ] = '<a class="update-now button" data-plugin="' . esc_attr( $status['file'] ) . '" data-slug="' . esc_attr( $plugin['slug'] ) . '" href="' . esc_url( $status['url'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Update %s now' ), $plugin['name'] ) ) . '" data-name="' . esc_attr( $plugin['name'] ) . '">' . __( 'Update Now' ) . '</a>';
						}
						break;

					case 'latest_installed':
					case 'newer_installed':
						$action_links[ $status['status'] ] = '<span class="button button-disabled" title="' . esc_attr__( 'This plugin is already installed and is up to date' ) . ' ">' . _x( 'Installed', 'plugin' ) . '</span>';
						break;

					default:
				}
			}

			if ( ! is_multisite() ) {
				$details_link = network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . $plugin['slug'] .	'&TB_iframe=true&width=600&height=550' );

				$links[ 'details' ] = '<a href="' . esc_url( $details_link ) . '" class="thickbox" aria-label="' . esc_attr( sprintf( __( 'More information about %s' ), $plugin['name'] ) ) . '" data-title="' . esc_attr( $plugin['name'] ) . '">' . __( 'More Details' ) . '</a>';
			}

		} else {

			switch ( $status['status'] ) {

				case 'update_available':
					$action_links[ $status['status'] ] = '<a class="update-now button" data-plugin="' . esc_attr( $plugin['file'] ) . '" data-slug="' . esc_attr( $plugin['slug'] ) . '" href="' . esc_url( $plugin['url'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Get %s Update' ), $plugin['name'] ) ) . '" data-name="' . esc_attr( $plugin['name'] ) . '">' . __( 'Get Update' ) . '</a>';
					break;

				case 'latest_installed':
				case 'newer_installed':
					$action_links[ $status['status'] ] = '<span class="button button-disabled" title="' . esc_attr__( 'This plugin is already installed and is up to date' ) . ' ">' . _x( 'Installed', 'plugin' ) . '</span>';
					break;

				default:
			}


			$links[ 'details' ] = '<a href="' . esc_url( $plugin['url'] ) . '" aria-label="' . esc_attr( sprintf( __( 'More information about %s' ), $plugin['name'] ) ) . '" data-title="' . esc_attr( $plugin['name'] ) . '" target="_blank" rel="nofollow">' . __( 'More Details' ) .
								  '<span class="details-external dashicons dashicons-external"></span></a>';

			if ( ! is_multisite() ) {
				$details_link = $links[ 'details' ];
			}
		}

		if ( ! empty( $plugin['demo'] ) ) {
			$action_links[ 'demo' ] = '<a class="demo button-primary" data-slug="' . esc_attr( $plugin['slug'] ) . '" href="' . esc_url( $plugin['demo'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Visit %s Demo Site' ), $plugin['name'] ) ) . '" data-name="' . esc_attr( $plugin['name'] ) . '" target="_blank" rel="nofollow">' . __( 'Demo', 'wp-shp-browser' ) . '</a>';
		}

		if ( !empty( $plugin['icons']['svg'] ) ) {
			$plugin_icon_url = $plugin['icons']['svg'];
		} elseif ( !empty( $plugin['icons']['2x'] ) ) {
			$plugin_icon_url = $plugin['icons']['2x'];
		} elseif ( !empty( $plugin['icons']['1x'] ) ) {
			$plugin_icon_url = $plugin['icons']['1x'];
		} elseif ( !empty( $plugin['icons']['default'] ) ) {
			$plugin_icon_url = $plugin['icons']['default'];
		} else {
			$plugin_icon_url = '';
		}

		/**
		 * Filter the install action links for a plugin.
		 *
		 * @param array $action_links An array of plugin action hyperlinks. Defaults are links to Details and Install Now.
		 * @param array $plugin       The plugin currently being listed.
		 */
		$action_links = apply_filters( "bc_plugin_browser_plugin_install_action_links_{$this->page_slug}", array_merge( $action_links, $links ), $plugin );

		$date_format            = __( 'M j, Y @ H:i' );
		$last_updated_timestamp = $plugin['last_updated'] ? strtotime( $plugin['last_updated'] ) : strtotime( $plugin['added'] ) ;

		ob_start();
?>
		<div class="plugin-card plugin-card-<?php echo sanitize_html_class( $plugin['slug'] ); ?>">
			<div class="plugin-card-top">
				<div class="name column-name">
					<h3>
						<a href="<?php echo esc_url( $plugin['url'] ); ?>" class="thickbox">
							<?php echo esc_html( $plugin['title'] ); ?>
							<img src="<?php echo esc_attr( $plugin_icon_url ) ?>" class="plugin-icon" alt="">
						</a>
					</h3>
				</div>
				<div class="action-links">
					<?php if ( $action_links ): ?>
						<ul class="plugin-action-buttons">
							<li><?php echo implode( '</li><li>', $action_links ); ?></li>
						</ul>
					<?php endif; ?>
				</div>
				<div class="desc column-description">
					<p><?php echo esc_html( $plugin['description'] ); ?></p>
					<div class="authors">
						<cite><?php echo sprintf( __( 'By %1$s', 'wp-shp-browser' ), $plugin['author'] ); ?> </cite>
					</div>
				</div>
			</div>
			<div class="plugin-card-bottom">
				<?php if ( ! empty( $plugin['rating'] ) ): ?>
					<div class="vers column-rating">
						<?php wp_star_rating( array( 'rating' => $plugin['rating'], 'type' => 'percent', 'number' => $plugin['num_ratings'] ) ); ?>
						<span class="num-ratings">(<?php echo number_format_i18n( $plugin['num_ratings'] ); ?>)</span>
					</div>
				<?php endif; ?>
				<div class="column-updated">
					<?php if ( empty( $plugin['last_updated'] ) ): ?>
						<strong><?php _e( 'Added:', 'wp-shp-browser' ); ?></strong>
					<?php else: ?>
						<strong><?php _e( 'Last Updated:', 'wp-shp-browser' ); ?></strong>
					<?php endif; ?>
					 <span title="<?php echo esc_attr( date_i18n( $date_format, $last_updated_timestamp ) ); ?>">
						<?php printf( __( '%s ago', 'wp-shp-browser' ), human_time_diff( $last_updated_timestamp ) ); ?>
					</span>
				</div>
				<div class="column-requirements">
					<strong><?php echo __( 'Requirements:', 'wp-shp-browser' ); ?></strong> <span title="<?php echo __( 'Requirements', 'wp-shp-browser' ); ?>"><?php echo esc_html( $plugin['requirements'] ); ?></span>
				</div>
				<div class="column-hosted">
					<a href="<?php echo esc_url( $plugin['host']['url'] ); ?>" class="thickbox" aria-label="<?php echo esc_attr( sprintf( __( 'Hosted by %s', 'wp-shp-browser'), $plugin['host']['name'] ) ); ?>" title="<?php echo esc_attr( sprintf( __( 'Hosted by %s', 'wp-shp-browser'), $plugin['host']['name'] ) ); ?>">@ <?php echo esc_html( $plugin['host']['name'] ); ?></a>
				</div>
			</div>

			<?php if ( ! empty( $plugin['price_min'] ) ): ?>

				<div class="plugin-card-bottom plugin-card-premium">
					<div class="column-premium">
						<strong><?php echo __( 'Go Premium', 'wp-shp-browser' ); ?></strong>
						<?php if ( ! empty( $details_link ) ) : ?>
							<a href="<?php echo esc_url( ! empty( $plugin['url'] ) ? $plugin['url'] : $details_link ); ?>" target="_blank" rel="nofollow"><?php echo __( 'More Details', 'wp-shp-browser' ); ?>
						<?php endif; ?>
						<span class="details-external dashicons dashicons-external"></span></a>
					</div>
					<div class="column-price">
						<span>
							<?php
								$class      = '';
								$sale_price = '';

								if ( ! empty( $plugin['price_min'] ) ) {
									echo __( 'Starting at', 'wp-shp-browser' );
								} else {
									$class = 'single-price';
								}

								if ( ! empty( $plugin['regular_price'] ) ) {
									$sale_price = $plugin['regular_price'];
								}
							?>

							<h3 class="<?php echo esc_attr( $class ); ?>"><?php echo sprintf( '%1$s%2$s', html( 'span class="price-sale"', $sale_price ), $plugin['price_min'] ); ?></h3>
						</span>
					</div>
				</div>

			<?php else : ?>

				<div class="plugin-card-bottom plugin-card-premium">
					<div class="column-premium">
						<strong><?php echo __( 'Free!', 'wp-shp-browser' ); ?></strong>
						<?php if ( ! empty( $details_link ) ) : ?>
							<a href="<?php echo esc_url( ! empty( $plugin['url'] ) ? $plugin['url'] : $details_link ); ?>" target="_blank" rel="nofollow"><?php echo __( 'More Details', 'wp-shp-browser' ); ?>
						<?php endif; ?>
						<span class="details-external dashicons dashicons-external"></span></a>
					</div>
					<div class="column-price">
						<span>
							<h3>Free!</h3>
						</span>
					</div>
				</div>

			<?php endif; ?>

			<?php
			/**
			 * Fires after the all the content for each plugin is displayed.
			 */
			do_action( "bc_plugin_browser_list_after_{$this->page_slug}", $plugin ); ?>
		</div>
<?php
		$output = ob_get_clean();

		echo apply_filters( "bc_plugin_browser_list_markup_{$this->page_slug}", $output, $plugin );
	}

	/**
	 * Outputs the no items message.
	 */
	public function no_items() {

		if ( isset( $this->error ) ) {
			$message = $this->error->get_error_message() . '<p class="hide-if-no-js"><a href="#" class="button" onclick="document.location.reload(); return false;">' . __( 'Try again', 'wp-shp-browser' ) . '</a></p>';
		} else {
			$message = __( 'No plugins match your request.', 'wp-shp-browser' );
		}
		echo '<div class="no-plugin-results">' . $message . '</div>';
	}

	/**
	 * Retrieves a list of all the available Add-ons filters.
	 *
	 * @return array An associative array of available filters.
	 */
	public function get_filter_list() {
		return apply_filters( "bc_plugin_browser_list_filters_{$this->page_slug}", $this->args['filters'] );
	}

	/**
	 * Retrieves the requested user selected filters values, if any.
	 *
	 * @return array An associative array of selected filter/values.
	 */
	public function get_filter_by() {

		$filters = $this->get_filter_list();

		$params    = array_map( 'esc_attr', $_GET ); // Input var okay.
		$filter_by = wp_parse_args( $params, $filters );

		// Make sure the 'filter_by' only contains valid filter keys.
		$filter_by = array_intersect_key( $filter_by, $filters );

		// Iterate through the valid filters and try assign a default value if none selected.
		foreach ( $filter_by as $filter => $items ) {

			$values[ $filter ] = array();

			// Flatten any grouped items in the current filter.
			foreach ( (array) $items as $item ) {

				if ( is_array( $item ) ) {
					$values[ $filter ] = array_merge( $values[ $filter ], $item );
				} else {
					$values[ $filter ][] = $item;
				}

			}

			if ( empty( $values[ $filter ] ) ) {
				$values[ $filter ] = $items;
			}

			// Get rid of the empty arrays to have a real count of this filter items.
			$values[ $filter ] = array_filter( $values[ $filter ], 'strlen' );

			if ( ! $values[ $filter ] ) {
				// User selected 'All' - show all results for this filter.
				unset( $filter_by[ $filter ] );
			} elseif ( ! is_array( $values[ $filter ] ) ) {
				// User selected a value for this filter.
				continue;
			} else {

				if ( count( $values[ $filter ] ) > 1 ) {

					// Default to the active product if available on the list of items.
					if ( 'product' === $filter && $active_product && isset( $values[ $filter ][ $active_product ] ) ) {
						$filter_by[ $filter ] = $active_product;
					} else {
						// Default to 'All' - show all results for this filter.
						unset( $filter_by[ $filter ] );
					}

				} else {
					// One value only filter (use it as the default value if none other requested in '$_GET').
					$filter_by[ $filter ] = reset( $values[ $filter ] );
				}

			}

		}
		return $filter_by;
	}

	/**
	 * Retrieves install status information for a given plugin.
	 *
	 * @param  array   $plugin   Plugin information.
	 * @param  array   $plugins  List of all available plugins with meta.
	 * @return string            The plugin status.
	 */
	public function install_status( $plugin, $plugins ) {

   		if ( $plugin['wordpress'] ) {
			$status = install_plugin_install_status( $plugin );

			$stati = $status['status'];
		} else {

			$status['status'] = '';

			if ( ! empty( $plugins[ $plugin['file'] ]['Version'] ) ) {

				if ( $plugin['version'] && version_compare( $plugins[ $plugin['file'] ]['Version'], $plugin['version'], '<' ) ) {
					$status['status'] = 'update_available';
				} else {
					$status['status'] = 'latest_installed';
				}

			}

		}

		if ( 'update_available' === $status['status'] ) {
			wp_product_showcase_dismissible_notice( 'new', '_wp_sh_plugin_browser_available_updates', $plugin['slug'] );
		} else {
			wp_product_showcase_dismissible_notice( 'dismiss', '_wp_sh_plugin_browser_available_updates', $plugin['slug'] );
		}
		return $status;
	}


	// __Helpers.

	/**
	 * NOTE: Mirrors 'wp_list_filter()' but also matches objects with array values.
	 *
	 * @todo: move to framework
	 *
	 * Filters a list of objects, based on a set of key => value arguments.
	 *
	 * @param array   $list     An array of objects to filter.
	 * @param array   $args     (optional) An array of key => value arguments to match
	 *                          against each object. Default empty array.
	 * @param string  $operator (optional) The logical operation to perform. 'AND' means
	 *                          all elements from the array must match. 'OR' means only
	 *                          one element needs to match. 'NOT' means no elements may
	 *                          match. Default 'AND'.
	 * @param boolean $match    (optional) Compare values using 'preg_match()'.
	 * @return array            List of matches.
	 */
	protected function list_filter( $list, $args = array(), $operator = 'AND', $match = false ) {
		if ( ! is_array( $list ) ) {
			return array();
		}
		if ( empty( $args ) ) {
			return $list;
		}

		$operator = strtoupper( $operator );
		$count    = count( $args );
		$filtered = array();

		foreach ( $list as $key => $obj ) {
			$to_match = (array) $obj;
			$matched  = 0;

			foreach ( $args as $m_key => $m_value ) {

				if ( array_key_exists( $m_key, $to_match ) && ( ( ! $match && in_array( $m_value, (array) $to_match[ $m_key ] ) ) || ( $match && preg_match( "#{$m_value}#i", $to_match[ $m_key ] ) ) ) ) {
					$matched++;
				} else {

					$m_key = 'f_' . $m_key;

					if ( array_key_exists( $m_key, $to_match ) && ( ( ! $match && in_array( $m_value, (array) $to_match[ $m_key ] ) ) || ( $match && preg_match( "#{$m_value}#i", $to_match[ $m_key ] ) ) ) ) {
						$matched++;
					}
				}
			}

			if ( ( 'AND' === $operator && $matched === $count )
			  || ( 'OR' === $operator && $matched > 0 )
			  || ( 'NOT' === $operator && 0 === $matched ) ) {
				$filtered[ $key ] = $obj;
			}
		}

		return $filtered;
	}

}
