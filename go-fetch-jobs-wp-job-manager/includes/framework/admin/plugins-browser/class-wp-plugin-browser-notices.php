<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A class for dismissible notices.
 *
 * Notices are stored in a pre-set list of transients keyed by the notice status ('new' or 'dismissed').
 *
 * Notes:
 * . Notices are uniquely identified using a slug name
 * . Slugs are the only data stored in the database. Notices are displayed immediately and not stored.
 *
 */
class BC_Framework_Plugin_Browser_Dismissible_Notice {

	/**
	 * List of notices to be displayed on page load.
	 *
	 * @var array
	 */
	public static $notices;

	/**
	 * Constructor
	 *
	 * @param string $transient  The transient name.
	 * @param string $slug       The unique slug name for the notice.
	 * @param string $notice     Optional notice to display.
	 */
	public function __construct( $transient, $slug = '', $notice = '' ) {

		$data = self::get( $transient, '' );

		$update_transient = false;

		// Add the slug to the given transient.
		if ( empty( $data['new'][ $slug ] ) && empty( $data['dismissed'][ $slug ] ) ) {
			$data['new'][ $slug ] = $slug;

			$notice = $notice ? $notice : $slug;

			$update_transient = true;
		}

		self::$notices[ $slug ] = $notice;

		if ( $update_transient ) {
			set_transient( $transient, $data, 60 * 60 * 24 );
		}

	}

	/**
	 * Dismisses a notice by removing it from the 'new' list.
	 *
	 * @param  string  $transient  The transient name.
	 * @param  string  $slug       The slug name.
	 */
	public static function dismiss( $transient, $slug ) {

		$data = self::get( $transient, '' );

		if ( ! empty( $data['new'][ $slug ] ) ) {

			unset( $data['new'][ $slug ] );

			$data['dismissed'][ $slug ] = $slug;

			if ( ! empty( self::$notices[ $slug ] ) ) {
				unset( self::$notices[ $slug ] );
			}

			set_transient( $transient, $data, 60 * 60 * 24 );
		}

	}

	/**
	 * Retrieves a notice or list of notices by status.
	 *
	 * @param  string $transient  The transient name.
	 * @param  string $status     The notice status to get.
	 * @return array              The list of notices.
	 */
	public static function get( $transient, $status = 'new' ) {

		$data = get_transient( $transient );

		$defaults = array(
			'new'       => array(),
			'dismissed' => array(),
		);
		$data = wp_parse_args( $data, $defaults );

		foreach( $data['new'] as $slug => $content ) {

			if ( ! empty( self::$notices[ $slug ] ) ) {
				$data['new'][ $slug ] = self::$notices[ $slug ];

			// The notice was removed.
			} elseif( ! empty( $data['new'][ $slug ] ) ) {
				unset( $data['new'][ $slug ] );
				set_transient( $transient, $data, 60 * 60 * 24 );
			}

		}

		if ( $status ) {
			return $data[ $status ];
		} else {
			return $data;
		}

	}

}

/**
 * Creates a new dismissible notice or dismisses it.
 *
 * @param string $transient  The transient name to store the data.
 * @param string $slug       The unique slug name for the notice.
 * @param string $notice     Optional notice to display.
 */
function wp_product_showcase_dismissible_notice( $action, $transient, $slug, $notice = '' ) {

	if ( 'new' === $action ) {
		new BC_Framework_Plugin_Browser_Dismissible_Notice( $transient, $slug, $notice );
	} else {
		BC_Framework_Plugin_Browser_Dismissible_Notice::dismiss($transient, $slug );
	}
}
