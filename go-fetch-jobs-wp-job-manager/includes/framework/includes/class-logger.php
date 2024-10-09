<?php

/**
 * Log interface
 */
interface BC_Framework_Log {

	/**
	 * Logs a message
	 * @param string $message A message to log
	 * @param string $type The type of log message being recorded. Usually, 'major', 'minor' or 'info'
	 */
	public function log( $message, $type = 'info' );

	/**
	 * Outputs an array of log messages with time, message, and type information
	 * @return array
	 */
	public function get_log();

	/**
	 * Clears the log completely
	 */
	public function clear_log();

}

class BC_Framework_Logger implements BC_Framework_Log {

	protected $post_id;

	public function __construct( $post_id ) {
		$this->post_id = $post_id;
	}

	final public function log( $message, $type = 'info', $limit = 0 ) {

		add_post_meta( $this->post_id, '_log_messages', array(
			'time'    => current_time( 'timestamp' ),
			'message' => $message,
			'type'    => $type,
        ) );

		if ( $limit ) {
			$this->clear_log( $limit );
		}

	}

	final public function get_log() {

		$messages = get_post_meta( $this->post_id, '_log_messages' );

		$output = array();

		foreach( $messages as $data ){

			if ( ! isset( $data['type'] ) ) {
				$data['type'] = 'info';
			}

			$output[] = array(
				'time'    => date( 'Y-m-d H:i:s',  $data['time'] ),
				'message' => $data['message'],
				'type'    => $data['type']
			);

		}
		return $output;
	}

	final public function clear_log( $limit = 0 ) {
		global $wpdb;

		// Delete the first 'n' number of messages.
		if ( $limit ) {
			$messages = get_post_meta( $this->post_id, '_log_messages' );

			$total = count( $messages );

			if ( $total > $limit ) {

				foreach( $messages as $index => $message ) {
					delete_post_meta( $this->post_id, '_log_messages', $message );
					if ( $index >= ( $total - $limit ) - 1 ) return;
				}

			}

		} else {
			delete_post_meta( $this->post_id, '_log_messages' );
		}

	}

}

/**
 * Basic logger.
 *
 * These constants must be set in 'wp-config.php'.
 *
 * define( 'WP_DEBUG', true );
 * define( 'WP_DEBUG_DISPLAY', false );
 * define( 'WP_DEBUG_LOG', true );
 */
Class BC_Framework_Debug_Logger {

	/**
	 * Simple logger that outputs data to the debug log file.
	 */
	public static function log( $log, $run = true )  {

	    if ( ! $run || ! defined('WP_DEBUG_LOG') || ! WP_DEBUG_LOG ) {
	    	return;
	    }

	    if ( is_array( $log ) || is_object( $log ) ) {
	        error_log( print_r( $log, true ) );
	    } else {
	        error_log( $log );
	    }

	}

}

/**
 * Simple logger that outputs data to the debug log file.
 */
function bc_framework_simple_log( $log, $override_condition = null )  {
	_deprecated_function( __FUNCTION__, 'v.4', 'bc_framework_simple_log()' );
	BC_Framework_Debug_Logger::log( $log, $override_condition );
}
