<?php
/**
 * Loads the latest version of the plugin framework using the same principle as in Scribu's 'scbFramework'.
 *
 * Uses @scribu's scbFramework (https://github.com/scribu/wp-scb-framework)
 *
 * @package Framework\Load
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once 'includes/wp-scb-framework/load.php';

$GLOBALS['_bc_files'] = array( 6, __FILE__, array(
	'includes/class-utils.php'                    => 'BC_Framework_Utils',
	'includes/class-logger.php'                   => 'BC_Framework_Logger',
	'admin/class-tooltips.php'                    => 'BC_Framework_ToolTips',
	'admin/pointers-tour/class-pointers-tour.php' => 'BC_Framework_Pointers_Tour',
	'admin/class-bc-admin-page.php'               => 'BC_Framework_Admin_Page' ,
	'admin/class-scb-tabs-page.php'               => 'BC_Framework_Tabs_Page',
	'admin/plugins-browser/load.php'              => 'BC_Framework_Plugin_Browser',
) );

if ( ! class_exists( 'BC_SCB_Load' ) ) :
class BC_SCB_Load {

	private static $candidates = array();
	private static $classes;
	private static $callbacks = array();

	private static $loaded;

	static function init( $callback = '' ) {
		list( $rev, $file, $classes ) = $GLOBALS['_bc_files'];

		self::$candidates[ $file ] = $rev;
		self::$classes[ $file ]    = $classes;

		if ( ! empty( $callback ) ) {
			self::$callbacks[ $file ] = $callback;

			add_action( 'activate_plugin',  array( __CLASS__, 'delayed_activation' ) );
		}

		if ( did_action( 'plugins_loaded' ) ) {
			self::load();
		} else {
			add_action( 'plugins_loaded', array( __CLASS__, 'load' ), 9, 0 );
		}
	}


	public static function delayed_activation( $plugin ) {
		$plugin_dir = dirname( $plugin );

		if ( '.' == $plugin_dir ) {
			return;
		}

		foreach ( self::$callbacks as $file => $callback ) {
			if ( dirname( dirname( plugin_basename( $file ) ) ) == $plugin_dir ) {
				self::load( false );
				call_user_func( $callback );
				do_action( 'scb_activation_' . $plugin );
				break;
			}
		}
	}

	public static function load( $do_callbacks = true ) {
		arsort( self::$candidates );

		$file = key( self::$candidates );

		$path = dirname( $file ) . '/';

		foreach ( self::$classes[ $file ] as $dir => $class_name ) {
			if ( class_exists( $class_name ) ) {
				continue;
			}

			$fpath = $path . $dir;

			if ( file_exists( $fpath ) ) {
				include $fpath;
				self::$loaded[] = $fpath;
			}
		}

		if ( $do_callbacks ) {
			foreach ( self::$callbacks as $callback ) {
				call_user_func( $callback );
			}
		}
	}

}
endif;

// __Initialize frameworks considering the latest revision.

if ( ! function_exists( 'bc_scb_init' ) ) :
function bc_scb_init( $callback = '' ) {
	BC_SCB_Load::init( $callback );

	do_action( 'bc_scb_framework_loaded' );
}
endif;

scb_init( 'bc_scb_init' );
