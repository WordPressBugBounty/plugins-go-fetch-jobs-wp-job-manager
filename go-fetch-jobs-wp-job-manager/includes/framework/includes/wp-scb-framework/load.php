<?php

$GLOBALS['_scb_bc_data'] = array( 60, __FILE__, array(
	'scbBcUtil',
	'scbBcOptions',
	'scbBcForms',
	'scbBcTable',
	'scbBcWidget',
	'scbBcAdminPage',
	'scbBcBoxesPage',
	'scbBcPostMetabox',
	'scbBcCron',
	'scbBcHooks',
) );

if ( ! class_exists( 'scbBcLoad' ) ) :
/**
 * The main idea behind this class is to load the most recent version of the scb classes available.
 *
 * It waits until all plugins are loaded and then does some crazy hacks to make activation hooks work.
 */
class scbBcLoad {

	private static $candidates = array();
	private static $classes;
	private static $callbacks = array();

	private static $loaded;

	static function init( $callback = '' ) {
		list( $rev, $file, $classes ) = $GLOBALS['_scb_bc_data'];

		self::$candidates[ $file ] = $rev;
		self::$classes[ $file ] = $classes;

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
				do_action( 'scb_bc_activation_' . $plugin );
				break;
			}
		}
	}

	public static function load( $do_callbacks = true ) {
		arsort( self::$candidates );

		$file = key( self::$candidates );

		$path = dirname( $file ) . '/';

		foreach ( self::$classes[ $file ] as $class_name ) {

			if ( class_exists( $class_name ) ) {
				continue;
			}

			$fpath = $path . substr( $class_name, 3 ) . '.php';
			$fpath = str_replace( "/Bc", '/', $fpath );

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

	static function get_info() {
		arsort( self::$candidates );

		return array( self::$loaded, self::$candidates );
	}
}
endif;

if ( ! function_exists( 'scb_bc_init' ) ) :
function scb_bc_init( $callback = '' ) {
	scbBcLoad::init( $callback );
}
endif;

