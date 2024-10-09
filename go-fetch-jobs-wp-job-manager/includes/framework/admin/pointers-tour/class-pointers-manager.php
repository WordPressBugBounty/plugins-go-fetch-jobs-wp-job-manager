<?php
/**
 * Based on the work by: Giuseppe Mazzapica (https://gm.zoomlab.it)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 *  The pointers manager class.
 */
class BC_Framework_Pointers_Manager implements BC_Framework_Pointers_Manager_Interface {

	/**
	 * The guided tour version.
  *
	 * @var string
	 */
	private $version;

	/**
	 * The prefix to use for the dismissed pointers.
  *
	 * @var string
	 */
	private $prefix;

	/**
	 * The list of pointers.
  *
	 * @var array
	 */
	private $pointers = array();

	/**
	 * The list of active instances.
  *
	 * @var array
	 */
	private static $instances;

	/**
	 * [instance description]
	 */
	public static function instance( $screen_id, $pointers, $version, $prefix ) {
		if ( empty( self::$instances[ $screen_id ] ) ) {
		  self::$instances[ $screen_id ] = new self( $screen_id, $pointers, $version, $prefix );
		}
		return self::$instances[ $screen_id ];
	}

	/**
	 * [__construct description]
	 */
	public function __construct( $screen_id, $pointers, $version, $prefix ) {
		$this->pointers = $pointers;
		$this->version  = str_replace( '.', '_', $version );
		$this->prefix   = $prefix;

		$this->parse();
	}

	/**
	 * Load pointers and setup id with prefix and version.
	 * Cast pointers to objects.
	 */
	public function parse() {
		$pointers = apply_filters( 'bc-pointers-manager-pointers', $this->pointers );

		$this->pointers = array();

		foreach ( $pointers as $i => $pointer ) {
		  $pointer['id'] = "{$this->prefix}{$this->version}_{$i}";
		  $this->pointers[ $pointer['id'] ] = (object) $pointer;
		}

	}

	/**
	 * Remove from parse pointers dismissed ones and pointers
	 * that should not be shown on given page
	 *
	 * @param string $page Current admin screen
	 */
	public function filter( $page ) {

		if ( empty( $this->pointers ) ) {
		  return array();
		}

		$screen = get_current_screen();

		$uid        = get_current_user_id();
		$no         = explode( ',', (string) get_user_meta( $uid, 'dismissed_wp_pointers', true ) );
		$active_ids = array_diff( array_keys( $this->pointers ), $no );
		$good       = array();

		foreach ( $this->pointers as $i => $pointer ) {
		  if (
			in_array( $i, $active_ids, true )                   // is active
			&& isset( $pointer->where )                         // has where
			&& in_array( $screen->id, (array) $pointer->where, true ) // current screen is in where
		  ) {
			  $good[] = $pointer;
		  }
		}

		$count = count( $good );

		if ( $good === 0 ) {
		  return array();
		}

		foreach ( array_values( $good ) as $i => $pointer ) {
		  $good[ $i ]->next = $i + 1 < $count ? $good[ $i + 1 ]->id : '';
		}

		return $good;
	}

	/**
	 * Dismiss the pointers so they are not visible.
	 */
	public function dismiss_pointers() {

		if ( empty( $this->pointers ) ) {
		  return;
		}

		$uid           = get_current_user_id();
		$dismissed     = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );
		$dismissed_ids = array_merge( $dismissed, array_keys( $this->pointers ) );

		if ( ! $dismissed_ids ) {
		  return;
		}

		update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', implode( ',', $dismissed_ids ) );
	}

	/**
	 * Restores the pointers so they are visible again.
	 */
	public function restore_pointers() {

		if ( empty( $this->pointers ) ) {
			return;
		}

		$uid           = get_current_user_id();
		$dismissed     = array_filter( explode( ',', (string) get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) );
		$dismissed_ids = array_diff( $dismissed, array_keys( $this->pointers ) );

		update_user_meta( get_current_user_id(), 'dismissed_wp_pointers', implode( ',', $dismissed_ids ) );
	}

	/**
	 * Retrieves the pointers list.
	 */
	public function get_pointers() {
		return $this->pointers;
	}

}
