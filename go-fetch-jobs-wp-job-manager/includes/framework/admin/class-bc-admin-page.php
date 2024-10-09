<?php
/**
 * Admin.
 *
 * @package Framework\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data-aware form generator.
 */
class BC_Framework_Admin_page extends scbAdminPage {

	/**
	 * This is where the page content goes.
	 *
	 * @return void
	 */
	function page_content() {}

	/**
	 * Wraps a content in a table row.
	 *
	 * @param string $title
	 * @param string $content
	 *
	 * @return string
	 */
	public static function row_wrap( $title, $content, $tip = '', $class = '' ) {

		return html( "tr class='" . implode( ' ', (array) $class ) . "'",
			html( "th scope='row'", $title ),
			html( "td class='tip'", $tip ),
			html( "td", $content )
		);
	}

	/**
	 * Outputs <table> rows.
	 */
	public function table_row( $field, $formdata = false ) {

		if ( empty( $field['tip'] ) ) {
			$tip = '';
		} else {
			$tooltips_support = BC_Framework_ToolTips::supports_wp_pointer();

			$tip  = html( 'span', array(
				'class'        => 'dashicons-before dashicons-editor-help tip-icon bc-tip',
				'title'        => $tooltips_support ? __( 'Click to read additional info...' ) : '',
				'data-tooltip' => $tooltips_support ? $field['tip'] : __( 'Click for more info' ),
			) );

			if ( ! BC_Framework_ToolTips::supports_wp_pointer() ) {
				$tip .= html( "div class='tip-content'", $field['tip'] );
			}
		}

		if ( isset( $field['desc'] ) ) {
			$field['desc'] = html( 'span class="description"', $field['desc'] );
		}

		$atts = ( ! empty( $field['tr'] ) ? $field['tr'] : '' );

		if ( ! empty( $field['section_break'] ) ) {

			return html( "tr class='" . implode( ' ', (array) $atts )  . " hr-break'",
				html( "th colspan=3", $this->input( $field, $formdata ) )
			);

		} else  {

			if ( ! empty( $field['line_break'] ) ) {

				return html( "tr class='" . implode( ' ', (array) $atts )  . " hr-break'",
					html( "th", '' ),
					html( "td", '' ),
					html( "td", $this->input( $field, $formdata ) )
				);

			} else  {

			return $this->row_wrap( $field['title'], $this->input( $field, $formdata ), $tip, $atts );

			}

		}

	}

	/**
	 * Outputs content used on the page footer.
	 */
	public function page_footer() {
		parent::page_footer();

		if ( BC_Framework_ToolTips::supports_wp_pointer() ) {
			return;
		}

	?>
		<script type="text/javascript">
			(function() {
				var forms = document.getElementsByTagName('form');
				for (var i = 0; i < forms.length; i++) {
					forms[i].reset();
				}
			}());

			jQuery(function($) {
				$(document).on('.tip-icon', 'click', function(ev) {
					var $row = $(this).closest('tr');
					$(this).addClass('tip-icon-selected dashicons-marker');

					var $show = $row.next('.tip-show');

					if ( $show.length ) {
						$show.remove();
						$(this).removeClass('tip-icon-selected dashicons-marker');
					} else {
						$show = $('<tr class="tip-show">').html(
							$('<td colspan="3" class="dashicons-before dashicons-editor-help">').html( $row.find('.tip-content').html() )
						);
						$row.after( $show );
					}
				});
			});
		</script>
	<?php
	}

	/**
	 * Outputs content used on the page head.
	 */
	public function page_head() {
		global $_wp_admin_css_colors;
		$admin_color = get_user_option( 'admin_color' );
		$colors = $_wp_admin_css_colors[ $admin_color ]->colors;
	?>
		<style type="text/css">
			.wrap h3 { margin-bottom: 0; }
			.wrap .form-table + h3 { margin-top: 2em; }
			.form-table td label { display: block; }
			td.tip { width: 16px; }
			.dashicons-before.tip-icon:before{ color: <?php echo esc_attr( $colors[2] ); ?>; }
			.tip-icon { margin-top: 3px; cursor: pointer; }
			.tip-content { display: none; }
			.tip-show { background: #EAEAEA; border-bottom: 5px solid #F1F1F1; font-style: italic; }
			.tip-show .dashicons-before:before { color: #5F5F5F; padding-right: 20px; font-size: 30px; line-height: 0.8; float: left; }
			.dashicons-before.tip-icon.tip-icon-selected:before { color: #5F5F5F; }
		</style>
	<?php
	}

}
