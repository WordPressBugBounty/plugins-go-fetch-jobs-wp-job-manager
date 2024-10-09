<?php
/**
 * Tooltips.
 *
 * @package Framework\Tooltips
 *
 * @author: AppThemes (www.appthemes.com) (modified by SebeT)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BC_Framework_ToolTips {

	protected static  $_instance = null ;

	protected $screens;

	protected $selector;

	public static function instance( $pagehook, $args = array() ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $pagehook, $args );
		}
		return self::$_instance;
	}

	public function __construct( $pagehook, $args = array() ) {

		$defaults = array(
			'selector' => '.bc-tip',
		);
		$args = wp_parse_args( $args, $defaults );

		// the screens where the tooltips should be enqueued
		$this->screens = array_flip( (array) $pagehook );

		$this->selector = $args['selector'];

		add_action( 'admin_enqueue_scripts', array( $this, '_enqueue_css_js' ) );
		add_action( 'admin_print_styles', array( $this, '_print_css' ) );
		add_action( 'admin_print_footer_scripts', array( $this, '_print_js' ) );
	}

	public function condition() {
		global $page_hook, $current_screen;

		return isset( $this->screens[ $page_hook ] ) || isset( $current_screen ) || in_array( get_current_screen()->post_type, array_keys( $this->screens ) );
	}

	public function _enqueue_css_js() {

		if ( ! $this->condition() ) {
			return;
		}

		// maybe use WP Pointers as tooltips
		if ( self::supports_wp_pointer() ) {
			wp_enqueue_style('wp-pointer');
			wp_enqueue_script('wp-pointer');

			// custom styles for the  WP Pointer - must be added after the main CSS file
			$custom_styles = ""
					. ".bc-wp-pointer .wp-pointer-content{ background-color: #444; color: #fff; padding: 0; }"
					. ".bc-wp-pointer .wp-pointer-content a{ color: #0492CA } "
					. ".bc-wp-pointer .wp-pointer-content code { background-color: rgb(88, 88, 88); font-size: 11px; }"
					. ".bc-wp-pointer .wp-pointer-arrow-inner{ cursor: help; }"
					. ".bc-wp-pointer.wp-pointer-left .wp-pointer-arrow-inner { border-right-color: #444; }"
					. ".bc-wp-pointer.wp-pointer-right .wp-pointer-arrow-inner { border-left-color: #444; }";

			wp_add_inline_style( 'wp-pointer', $custom_styles );
		}
	}

	public function _print_css() {

		if ( ! $this->condition() ) {
			return;
		}

		global $_wp_admin_css_colors;
		$admin_color = get_user_option( 'admin_color' );
		$colors = $_wp_admin_css_colors[ $admin_color ]->colors;
?>
		<style type="text/css">
			.bc-tip:before { content: "\f223"; color: #CFCFCF; }
			.bc-tip-hover:before { color: inherit; }
			.wrap h3 { margin-bottom: 0; }
			.wrap .form-table + h3 { margin-top: 2em; }
			.form-table td label { display: block; }
			.form-table td.tip { width: 16px; vertical-align: top; }
			.dashicons-before.tip-icon:before{ color: <?php echo esc_attr( $colors[2] ); ?>; }
			.tip-icon { margin-top: 3px; cursor: pointer; }
			.tip-content { display: none; }
			.tip-show { background: #EAEAEA; border-bottom: 5px solid #F1F1F1; font-style: italic; }
			.tip-show .dashicons-before:before { color: #5F5F5F; padding-right: 20px; font-size: 30px; line-height: 0.8; float: left; }
			.dashicons-before.tip-icon.tip-icon-selected:before { color: #5F5F5F; }
		</style>
<?php
	}

	public function _print_js() {

		if ( ! $this->condition() ) {
			return;
		}
?>
		<script type="text/javascript">

			if ( <?php echo ( self::supports_wp_pointer() ? '0' : '1' ) ; ?> ) {
				(function() {
					var forms = document.getElementsByTagName('form');
					for (var i = 0; i < forms.length; i++) {
						forms[i].reset();
					}
				}());
			}

			jQuery( function($) {

				if ( <?php echo ( self::supports_wp_pointer() ? '0' : '1' ) ; ?> ) {

					$(document).on( 'click', '.tip-icon', function(ev) {
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

				}

				var hover_class = 'wp-ui-text-highlight bc-tip-hover';

				// check that we can use WP Pointer
				if ( <?php echo ( self::supports_wp_pointer() ? '1' : '0' ) ; ?> ) {

					$(document).on( 'mouseenter mouseleave', "<?php echo $this->selector; ?>", function(ev) {

						// dimisss any opened pointer
						dismiss();

						$(this).addClass( hover_class );

						var tooltip = $(this).attr('data-tooltip');

						// Use literal HTML tags on code wrapped in <code class="literal"></code>

						const regex = /<code class="literal">(.*?)<\/code>/g;

						while ((m = regex.exec(tooltip)) !== null) {
							// This is necessary to avoid infinite loops with zero-width matches
							if (m.index === regex.lastIndex) {
								regex.lastIndex++;
							}

							m.forEach((match, groupIndex) => {
								console.log(`Found match, group ${groupIndex}: ${match}`);

								if ( 1 === groupIndex ) {
									var htmlCodeEscaped = '<code>' + match.replace(/</g, "&lt;").replace(/>/g, "&gt;") + '</code>';
									tooltip = tooltip.replace( match, htmlCodeEscaped );
								}

							});
						}

						var content = '<p>' + tooltip + '</p>';

						var position = $(this).position(),
							offset   = $(this).offset();

						$(this).pointer({
							pointerClass: 'bc-wp-pointer wp-pointer',
							content: content,
							buttons: function( event, t ) {
							},
							position: {
								edge: ( ( $(window).width() - offset.left ) < 400 ? 'right' : 'left' ),
								align: 'center'
							},
							pointerWidth: ( $(window).width() - offset.left ) > 750 ? 550 : $(window).width() / 3,
							show: function( e, t ) {
								t.pointer.addClass('mouse-hover-content');

								t.pointer.bind( 'mouseleave', function (e) {
									t.pointer.removeClass('mouse-hover-content')
								});

								// bind a delayed 'mouseleave' event to make sure the tooltip is not hidden when user is moving cursor inside the baloon
								$(this).bind( 'mouseleave', function(event) {

									setTimeout(function () {

										var target = $(event.relatedTarget).closest('.bc-wp-pointer.wp-pointer');

										// hide on mouseleave only if user is not hovering the tooltip content
										if ( target.hasClass('mouse-hover-content') ) {
											return;
										}

										// otherwise, dismiss the tooltip
										t.pointer.trigger('mouseleave.pointer');
									}, 1);

								});

								// dismiss pointer on mouseleave
								t.pointer.bind( 'mouseleave.pointer', function(e) {
									e.preventDefault();
									t.element.pointer('close');
									dismiss();
								});

								t.pointer.show();
								t.opened();
							},
						}).pointer('open');

					});

					// dismiss tips on ESC key
					$(document).keyup( function(e) {
					  if (e.keyCode == 27) {
						  dismiss();
					  }
					});

					function dismiss() {
						$('.bc-wp-pointer.wp-pointer').hide();
						$('.bc-tip').removeClass( hover_class );
					}

				} else {

					// default to older static tips on mobile or older WP versions

					$("<?php echo $this->selector; ?>").on( 'mouseenter mouseleave', function( ev ) {
						$(this).addClass( hover_class );
						$(this).attr( 'title', $(this).attr('data-tooltip') );

						$(this).bind( 'mouseleave', function(event) {
							var tip_opened = $(this).closest('tr').next('.tip-show');

							var icon_tip = $(this);

							setTimeout( function () {

								// hide on mouseleave only if user is not hovering the tooltip content
								if ( ! tip_opened.length ) {
									icon_tip.removeClass( hover_class );
								}

							}, 1);
						});

					});

					$(document).on( 'click', "<?php echo $this->selector; ?>", function( ev ) {
						var $row = $(this).closest('tr');

						var $show = $row.next('.tip-show');

						$(this).addClass( hover_class );

						var icon_tip = $(this);

						if ( $show.length ) {
							$show.bind( 'remove', function() {
								icon_tip.removeClass( hover_class );
							});
							$show.remove();
						} else {
							$show = $('<tr class="tip-show">').html(
								$('<td colspan="3">').html( $row.find('.tip-content').html() )
							);

							$row.after( $show );
						}
					});
				}

			});
		</script>
<?php
	}

	/*
	 * Checks if we can use WP Pointers as tooltips.
	 */
	public static function supports_wp_pointer() {
		global $wp_version;

		return ! wp_is_mobile() && $wp_version >= 3.3;
	}

	/**
	 * Wraps a content in a table row.
	 *
	 * @param string $title
	 * @param string $content
	 *
	 * @return string
	 */
	protected static function row_wrap( $title, $content, $tip = '', $class = '' ) {
		return html( "tr class='" . implode( ' ', (array) $class ) . "'",
			html( "th scope='row'", $title ),
			html( "td class='tip'", $tip ),
			html( "td", $content )
		);
	}

	/**
	 * Outputs <table> rows.
	 */
	public static function table_row( $field, $formdata ) {

		if ( empty( $field['tip'] ) ) {
			$tip = '';
		} else {
			$tooltips_support = BC_Framework_ToolTips::supports_wp_pointer();

			$tip = html( 'span', array(
				'class'        => 'dashicons-before dashicons-editor-help tip-icon bc-tip',
				'title'        => $tooltips_support ? __( 'Click to read additional info...' ) : '',
				'data-tooltip' => $tooltips_support ? $field['tip'] : __( 'Click for more info' ),
			) );

			if ( ! BC_Framework_ToolTips::supports_wp_pointer() ) {
				$tip .= html( "div class='tip-content'", $field['tip'] );
			}
		}

		$atts = ( ! empty( $field['tr'] ) ? $field['tr'] : '' );

		if ( isset( $field['desc'] ) ) {
			$field['desc'] = html( 'span class="description"', $field['desc'] );
		}

		if ( ! empty( $field['section_break'] ) ) {

			return html( "tr class='" . implode( ' ', (array) $atts )  . " hr-break'",
				html( "th colspan=3", scbForms::input( $field, $formdata ) )
			);

		} else  {

			if ( ! empty( $field['line_break'] ) ) {

				return html( "tr class='" . implode( ' ', (array) $atts )  . " hr-break'",
					html( "th scope='row'", '' ),
					html( "td", '' ),
					html( "td", scbForms::input( $field, $formdata ) )
				);

			} else  {

				return self::row_wrap( $field['title'], scbForms::input( $field, $formdata ), $tip, $atts );

			}

		}

	}

}
