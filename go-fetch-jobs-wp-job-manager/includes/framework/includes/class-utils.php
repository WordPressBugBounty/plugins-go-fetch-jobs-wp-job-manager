<?php
/**
 * Contains common utility functions.
 *
 * @package Framework\Utils
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BC_Framework_Utils {

	/**
	 * Displays a formatted data based on WP date settings.
	 */
	public static function display_date( $date_time, $format = 'datetime', $gmt_offset = false ) {
		if ( is_string( $date_time ) ) {
			$date_time = strtotime( $date_time );
		}

		if ( $gmt_offset ) {
			$date_time = $date_time + ( get_option( 'gmt_offset' ) * 3600 );
		}

		if ( $format == 'date' ) {
			$date_format = get_option( 'date_format' );
		} elseif ( $format == 'time' ) {
			$date_format = get_option( 'time_format' );
		} else {
			$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		}

		return date_i18n( $date_format, $date_time );
	}

	/**
	 * Checks for a valid timestamp.
	 */
	public static function is_valid_timestamp( $timestamp ) {
	    return ( (string ) (int) $timestamp === $timestamp )
	        && ($timestamp <= PHP_INT_MAX)
	        && ($timestamp >= ~PHP_INT_MAX);
	}

	/**
	 * Flattens an array by converting array values into single values.
	 */
	public static function flatten_array( &$array ) {
		return array_walk( $array, create_function( '&$value', '$value = $value[0];' ) );
	}

	/**
	 * Clears all the content for a given array.
	 */
	public static function clear_array( &$array, $value = '' ) {
		return array_walk( $array, create_function( '&$value', '$value = "' . $value . '";' ) );
	}

	/**
	 * Similar to 'wp_list_filter()' but retrieves the position of a given key/value pair within a list.
	 */
	public static function list_find_pos( $list, $args = array() ) {

		if ( ! is_array( $list ) ) {
			return array();
		}

		if ( empty( $args ) ) {
			return $list;
		}

		$count = count( $args );

		$pos = 0;

		foreach ( $list as $key => $obj ) {
			$to_match = (array) $obj;

			foreach ( $args as $m_key => $m_value ) {
				if ( array_key_exists( $m_key, $to_match ) && $m_value == $to_match[ $m_key ] ) {
					return $pos;
				}
			}

			$pos++;
		}

		return -1;
	}

	/**
	 * Get the current user IP.
	 */
	public static function get_user_ip() {

  		// Check ip from share internet.
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		// Check ip is pass from proxy.
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		$ip = ( $ip == '::1' ? '127.0.0.1' : $ip );

		// Avoid multiple IP's.
		$ip = explode( ',', $ip );
		$ip = reset( $ip );

		return $ip;
	}

}
