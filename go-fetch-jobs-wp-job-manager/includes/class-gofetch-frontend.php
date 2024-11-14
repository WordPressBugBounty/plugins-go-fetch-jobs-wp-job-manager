<?php
/**
 * Provides public helper methods.
 *
 * @package GoFetch/Frontend
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Helper class.
 */
class GoFetch_Frontend {

	public function __construct() {

		if ( is_admin() ) {
			return;
		}

		add_filter( 'wp', array( $this, 'single_goft_job' ) );
		add_filter( 'wp_robots', array( $this, 'maybe_no_robots' ), 1 );
		add_action( 'goft_wpjm_single_goft_job', array( $this, 'single_job_page_hooks' ) );

		add_filter( 'goft_wpjm_read_more', array( $this, 'remove_more_on_scraped_descriptions' ), 10, 3 );
		add_filter( 'goft_wpjm_read_more', array( $this, 'remove_more_on_long_descriptions' ), 15, 3 );
		add_filter( 'goft_wpjm_read_more', array( $this, 'remove_more_on_full_descriptions' ), 15, 3 );

		add_action( 'goft_wpjm_source_link', array( $this, 'maybe_open_applications_on_click' ), 10, 3 );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ), 21 );
	}

	/**
	 * Actions that should run on the single job page.
	 */
	public function single_job_page_hooks( $post ) {
		global $goft_wpjm_options;

		// Disable 'wpautop' to avoid loosing original line breaks.
		if ( ! $goft_wpjm_options->enable_wpautop || apply_filters( 'goft_wpjm_disable_wpautop', false ) ) {
			remove_filter ( 'the_content', 'wpautop' );
			remove_filter( 'the_job_description', 'wpautop' );
		}

		add_filter( 'the_content', array( __CLASS__, 'goft_the_job_description' ), 50 );
	}

	/**
	 * Enqueue registered admin JS scripts and CSS styles.
	 */
	public function register_scripts() {

		$js_ext = ! defined( 'SCRIPT_DEBUG' ) || ! SCRIPT_DEBUG ? '.min' : '';

		wp_register_script(
			'gofj-vue',
			GoFetch_Jobs()->plugin_url() . "/includes/assets/js/vue{$js_ext}.js",
			array(),
			GoFetch_Jobs()->version,
			true
		);

	}

	/**
	 * Executes several functions on the job description
	 */
	public static function goft_the_job_description( $content ) {
		$original_content = $content;

		if ( $content ) {
			$content = self::fix_markup( $content );

			$content = self::auto_format_plain_text( $content );

			$content = html_entity_decode( $content );
			$content = apply_filters( 'goft_the_job_description_content', $content );

			// Remove line breaks before a list item.
			$content = str_replace( '<br/></li>', '</li>', $content );

			// Replace <h1><h2> tags with <strong> tags.
			$content = str_replace('</h1>', '</strong><br/>', str_replace('<h1>', '<br/><strong>', $content ) );
			$content = str_replace('</h2>', '</strong><br/>', str_replace('<h2>', '<br/><strong>', $content ) );

			// Consider text wrapped in **, as bold.
			$content = preg_replace( '/\*{2}(.*?)\*{2}/', '<strong>$1</strong>', $content);

			$content = self::add_empty_line_after_n_paragraphs( $content );

			$content = self::remove_empty_paragraphs( $content );

			$content = self::append_external_url( $content );
			$content = self::append_source( $content );
		}
		return apply_filters( 'goft_the_job_description', $content, $original_content );
	}

	/**
	 * Triggers an action when a users is viewing a single job page.
	 */
	public function single_goft_job() {
		global $post;

		if ( ! GoFetch_Jobs()->is_single_goft_job_page() ) {
			return;
		}

		do_action( 'goft_wpjm_single_goft_job', $post );
	}

	/**
	 * Adds a 'noindex' meta tag to disable jobs indexing for some providers
	 */
	public function maybe_no_robots( $robots ) {
		global $post, $goft_wpjm_options;

		if ( ! GoFetch_Jobs()->is_single_goft_job_page() ) {
			return $robots;
		}

		if ( $goft_wpjm_options->block_search_indexing ) {
			$robots['noindex'] = true;
			return $robots;
		}

		$source_data = get_post_meta( $post->ID, '_goft_source_data', true );

		$feed = '';

		if ( ! empty( $source_data['feed_url'] ) ) {
			$feed = $source_data['feed_url'];
		}

		return apply_filters( 'goft_no_robots', $robots, $feed );
	}

	/**
	 * 	Don't display the read more link if the job description was scraped.
	 */
	public function remove_more_on_scraped_descriptions( $more, $content, $link ) {
		global $post;

		$meta = get_post_meta( $post->ID, '_goft_wpjm_other', true );

		if ( ! empty( $meta['scrape']['description'] ) ) {
			return '';
		}

		return $more;
	}

	/**
	 * Don't display the read more link if the job description is complete.
	 */
	public function remove_more_on_full_descriptions( $more, $content, $link ) {
		global $post;

		$url = get_post_meta( $post->ID, '_goft_jobfeed', true );

		if ( $provider_id = GoFetch_RSS_Providers::find_provider_in_url( $url ) ) {
			$provider = GoFetch_RSS_Providers::get_providers( $provider_id );
			if ( ! empty( $provider['feed']['full_description'] ) ) {
				return '';
			}
		}
		return $more;
	}

	/**
	 * 	Don't display the read more link on long descriptions.
	 */
	public function remove_more_on_long_descriptions( $more, $content, $link ) {

		// @todo: check if the source has full job description

		if ( strlen( wp_strip_all_tags( $content ) ) > 1000 ) {
			return '';
		}

		return $more;
	}

	/**
	 * Fixes any messed markup (e.g: unclosed tags).
	 */
	public static function fix_markup( $content ) {
		$doc = new DOMDocument();
		$content = GoFetch_Helper::mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' );
		libxml_use_internal_errors( true );
		$doc->loadHTML( $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		$content = $doc->saveHTML();
		libxml_clear_errors();
		return $content;
	}

	/**
	 * Function to add empty paragraph on n consecutive paragraphs
	 */
	public static function add_empty_line_after_n_paragraphs( $html, $titleLength = 15 ) {
		global $goft_wpjm_options, $post;

		if ( ! $goft_wpjm_options->auto_format_descriptions || ! $goft_wpjm_options->add_empty_line_breaks ) {
			return $html;
		}

		$split = apply_filters( 'goft_wpjm_auto_format_plain_text_nth', $goft_wpjm_options->format_descriptions_stops_split, $post );

		$html = self::append_line_breaks_after_lists( $html );

		$dom = new DOMDocument();
		$html = GoFetch_Helper::mb_convert_encoding( $html, 'HTML-ENTITIES', 'UTF-8' );
		libxml_use_internal_errors( true );

		$dom->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

		$paragraphs = $dom->getElementsByTagName('p');
		$paragraphCount = $paragraphs->length;

		if ($paragraphCount <= 1) {
			return $html; // No need to add empty lines if only one or zero paragraphs.
		}

		$currentParagraph = 0;

		foreach ($paragraphs as $paragraph) {
			$currentParagraph++;
			$paragraphText = strip_tags($paragraph->textContent); // Remove HTML tags for length check
			$sentences = explode('.', $paragraphText);

			foreach ($sentences as $sentence) {
				// Skip empty sentences
				if (trim($sentence) === '') {
					continue;
				}

				// Add an empty line before short sentences (potential titles)
				$sentenceLength = strlen(trim($sentence));
				if ($sentenceLength <= $titleLength) {
					$emptyLine = $dom->createElement('br');
					$paragraph->parentNode->insertBefore($emptyLine, $paragraph);
				}

				// Add an empty line after every nth paragraph (except the last paragraph), if the sentence is long enough
				if ($currentParagraph % $split === 0 && $currentParagraph < $paragraphCount && $sentenceLength > $titleLength) {
					$emptyLine = $dom->createElement('br');
					$paragraph->parentNode->insertBefore($emptyLine, $paragraph->nextSibling);
				}
			}
		}

		$html = $dom->saveHTML();
		libxml_clear_errors();

		return $html;
	}

	public static function append_line_breaks_after_lists( $html ) {
		// Regular expression to match <ul> or <ol> opening tags
		$pattern = '/(<\/(?:ul|ol)>)/i';

		// Use preg_replace to add a line break after the opening tags
		$html = preg_replace( $pattern, '$1<br/>', $html );

		return $html;
	}

	/**
	 * Set possible list items on their own line.
	 */
	public static function maybe_convert_to_list_items($text) {
		// Regular expression to match the pattern for list items
		$pattern = '/[\s]*[-\.]{1}[\s]{1}?/';

		$text = preg_replace_callback($pattern, function ($matches) {
			return '<br/>' . $matches[0];
		}, $text);

		return $text;
	}

	/**
	 * Auto format plain text descriptions without any paragraphs.
	 */
	public static function auto_format_plain_text( $description ) {
		global $post, $goft_wpjm_options;

		$paragraphs = preg_split( '#(\r\n?|\n)+#', $description );

		if ( ! $goft_wpjm_options->auto_format_descriptions || count( $paragraphs ) > (int) apply_filters( 'goft_wpjm_auto_format_plain_text_paragraphs', $goft_wpjm_options->format_descriptions_paragraph_check, $post ) ) {
			return $description;
		}

		$span_br   = apply_filters( 'goft_wpjm_auto_format_lists', 'span_br' );
		$span_br_match = apply_filters( 'goft_wpjm_auto_format_list_match', '<span><\/span>' );

		if ( $span_br ) {
			$description = preg_replace( '/' . "{$span_br_match}" . '/', "<br/>", $description );
		}

		// Split sentences by '.', '?' or '!', if they are followed by a space.
		$sentences = preg_split( '/(?<=[.?!])\s+(?=[A-Z])/', $description, -1, PREG_SPLIT_DELIM_CAPTURE );

		$split = apply_filters( 'goft_wpjm_auto_format_plain_text_nth', $goft_wpjm_options->format_descriptions_stops_split, $post );

		$i = 0;

		$formatted_desc = '';

		// If there's only a couple of sentences, make sure to have at least one split to make the description more natural.
		if ( count( $sentences ) <= 2 ) {
			$split = 1;
		}

		foreach ( $sentences as $key => $sentence ) {

			// Avoid adding extra punctuation on last sentence.
			if ( $key >= count( $sentences ) - 1 ) {
				$formatted_desc .= "{$sentence}";
				continue;
			}

			$formatted_desc .= "{$sentence}";
			$formatted_desc  = rtrim( $formatted_desc, '.' ) . '. ';

			if ( $split > 0 && ( ++$i % $split == 0 ) ) {
				$formatted_desc .= '<br/><br/>';
				$i = 0;
			}
		}

		$ul       = apply_filters( 'goft_wpjm_auto_format_lists', '*' );
		$ul_match = apply_filters( 'goft_wpjm_auto_format_list_match', '\*' );

		if ( $ul ) {
			$formatted_desc = preg_replace( '/\s' . "{$ul_match}" . '\s/', "<br/> {$ul} ", $formatted_desc );
		}
		return $formatted_desc;
	}

	/**
	 * Remove empty paragraphs.
	 */
	public static function remove_empty_paragraphs( $content ) {
		global $post;

		if ( ! apply_filters( 'goft_wpjm_remove_empty_paragraphs', true, $post ) ) {
			return $content;
		}

		$content = preg_replace( '/<p[^>]*>(?:\s+|(?:&nbsp;)+|(?:&#013;)|(?:<br\s*\/?>)+)*<\/p>/', '', $content);

		// Remove double line breaks.
		$content = preg_replace( '/(<br\s*\/?>\s*){2,}/', '<br/><br/>', $content );

		return $content;
	}


	/**
	 * Append the job details external URL to the post content.
	 */
	public static function append_external_url( $content ) {
		global $post, $goft_wpjm_options;

		$append_external_url = $goft_wpjm_options->allow_visitors_apply || is_user_logged_in();

		if ( ! apply_filters( 'goft_wpjm_append_external_url', $append_external_url ) ) {
			return $content;
		}

		// Skip earlier if the content already contains the '[...]' with the external url.
		if ( false !== stripos( $content, '[&#8230;]' ) ) {
			return $content;
		}

		$source_data = get_post_meta( $post->ID, '_goft_source_data', true );

		$link = get_post_meta( $post->ID, '_goft_external_link', true );
		$link = GoFetch_Importer::add_query_args( $source_data, $link );

		// If the content is wrapped in <p> tags make sure the <a> is added inside it.
		$content_inline = '/p>' === trim( substr( $content, -4 ) ) ? substr( $content, 0, -5 ) : $content;

		$read_more = apply_filters( 'goft_wpjm_read_more', $goft_wpjm_options->read_more_text, $content, $link );

		if ( $read_more ) {

			$link_atts = apply_filters( 'goft_wpjm_read_more_link_attributes', array(
				'class'  => 'goftj-exernal-link',
				'href'   => $link,
				'rel'    => 'noopener noreferrer nofollow',
				'target' => '_blank',
			), $post );

			$content = sprintf( '%1$s %2$s', $content_inline, html( 'a', $link_atts, $read_more ) ) . '</p>';
		} else {
			$content = $content_inline;
		}
		return $content;
	}

	/**
	 * Append the source job URL to the post content.
	 */
	public static function append_source( $content ) {
		global $post, $goft_wpjm_options;


		if ( ! apply_filters( 'goft_wpjm_append_source_data', true ) ) {
			return $content;
		}

		$source_data = get_post_meta( $post->ID, '_goft_source_data', true );

		if ( 'none' === $goft_wpjm_options->source_output ) {
			return $content;
		}

		if ( empty( $source_data['name'] ) || 'unknown' === strtolower( $source_data['name'] ) || empty( $source_data['website'] ) || 'unknown' === strtolower( $source_data['website'] ) ) {
			return $content;
		}
?>
		<style type="text/css">
			p.goft-source-wrapper { margin-top: 25px; }
			.entry-content a.goftj-logo-exernal-link {
				box-shadow: none;
			}
			img.goftj-source-logo {
				max-height: 32px;
				margin-left: 10px;
				width: auto;
			}
			.goftj-logo-exernal-link {
				vertical-align: middle;
			}
		</style>
<?php
		$external_link = get_post_meta( $post->ID, '_goft_external_link', true );

		$link = GoFetch_Importer::add_query_args( $source_data, $external_link );

		if ( ! empty( $source_data['logo'] ) && ( empty( $goft_wpjm_options->source_output ) || 'logo' === $goft_wpjm_options->source_output ) ) {

			$atts = apply_filters( 'goft_wpjm_source_image_attributes', array(
				'src'   => esc_url( $source_data['logo'] ),
				'rel'   => 'noopener noreferrer nofollow',
				'title' => esc_attr( $source_data['name'] ),
				'class' => 'goftj-source-logo',
			) );
			$source = html( 'img', $atts );

		} else {
			$source = html( 'span class="goftj-source"', explode( '|', $source_data['name'] )[0] );
		}

		if ( $link ) {

			$atts = array(
				'class'  => 'goftj-logo-exernal-link',
				'rel'    => 'noopener noreferrer nofollow',
				'title'  => esc_attr( $source_data['name'] ),
				'target' => '_blank',
			);

			if ( $goft_wpjm_options->allow_visitors_apply || is_user_logged_in() ) {
				$atts['href'] = esc_url( $link );
			}

			$atts = apply_filters( 'goft_wpjm_source_link_attributes', $atts, $post );

			$tag = 'a';

			if ( empty( $atts['href'] ) ) {
				$tag = 'span';
			}
			$source = html( $tag, $atts, $source );
		}

		$source = html( 'p class="goft-source-wrapper"', sprintf( __( '<em class="goft-source">Source <span>&#8690;</span></em><br/> %s', 'gofetch-wpjm' ), $source ) );

		return $content . $source;
	}

	/**
	 * The alternative application markup with extra link attributes.
	 */
	public static function application_details_url( $apply ) {
		global $post, $goft_wpjm_options;

		$link = $apply->url;

		$atts = apply_filters( 'goft_wpjm_source_link_attributes', array(
			'class'  => 'goftj-logo-exernal-link',
			'rel'    => 'noopener noreferrer nofollow',
			'href'   => $link,
			'target' => '_blank',
		), $post );

		if ( ! $goft_wpjm_options->apply_to_job_text ) {
			$text = __( 'To apply, please visit the following URL:', 'gofetch-wpjm' );
		}

		if ( $goft_wpjm_options->apply_to_job_hide_link ) {
			$text = html( 'p', html( 'a', array_map( 'esc_attr', $atts ), $goft_wpjm_options->apply_to_job_text ) );
		} else {
			$text = html( 'p', $goft_wpjm_options->apply_to_job_text . html( 'a', $atts, esc_url( $link ) . '&rarr;' ) );
		}
		echo apply_filters( 'goft_wpjm_source_link', $text, $atts, $apply->url );
	}

	/**
	 * Open application URL's directly, on a new page, if set in settings page.
	 */
	public function maybe_open_applications_on_click( $text, $atts, $apply_url ) {
		global $goft_wpjm_options;

		if ( ! $goft_wpjm_options->apply_on_click ) {
			return $text;
		}
		?>
		<script>
		document.getElementsByClassName('application_button')[0].addEventListener( 'click', function(e) {
			window.open(
				'<?php echo esc_url_raw( $apply_url ); ?>',
				'<?php echo esc_attr( $goft_wpjm_options->apply_on_click ); ?>'
			);
			e.preventDefault();
			e.stopPropagation();
			return false;
		});
		</script>
		<?php
			return $text;
	}

}

new GoFetch_Frontend();
