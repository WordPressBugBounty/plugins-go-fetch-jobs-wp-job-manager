<?php
/**
 * Provides a base abstract class for easier creation of HTML tables.
 *
 * @package GoFetch/Table
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Base class for HTML tables.
 */
abstract class GoFetch_HTML_Table {

	protected function table( $items, $attributes = array(), $args = array() ){

		$args = wp_parse_args( $args, array(
			'wrapper_html'   => 'table',
			'header_wrapper' => 'thead',
			'body_wrapper'   => 'tbody',
			'footer_wrapper' => 'tfoot',
		) );

		extract( $args );

		$table_body = '';

		$table_body .= html( $header_wrapper, array(), $this->header( $items ) );
		$table_body .= html( $body_wrapper, array(), $this->rows( $items ) );
		//$table_body .= html( $footer_wrapper, array(), $this->footer( $items ) );

		return html( $wrapper_html, $attributes, $table_body );
	}

	protected function header( $data ){}

	protected function footer( $data ){}

	protected function rows( array $items ) {

		$i = 0;

		$table_body = '';

		foreach( $items as $item ) {
			$table_body .= $this->row( $item, array( 'class' => ( $i%2 == 0 ? 'alternate ' . $item['type'] : $item['type'] ) ) );
			$i++;
		}

		return $table_body;
	}

	abstract protected function row( $item = array(), $atts = array() );

	protected function cells( $cells, $atts = array(), $type = 'td' ){

		$output = '';

		foreach( $cells as $key => $value ) {

			if ( empty( $atts[ $key ] ) ) {
				$atts[ $key ] = '';
			}

			$output .= html( $type, ( ! empty( $atts[ $key ] ) ? $atts[ $key ] : array() ), $value );
		}
		return $output;

	}

}
