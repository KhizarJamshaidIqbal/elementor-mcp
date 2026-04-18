<?php
/**
 * Design Importer — converts a Claude-designed HTML file (hero+tabs+
 * popular+accordion+trust+CTA-style marketing pages) into a ready-to-
 * apply Elementor IR tree.
 *
 * Pipeline per import:
 *   1. Fetch HTML (URL or inline)
 *   2. Extract CSS `:root` vars → tokens + palette (css-var-extractor)
 *   3. Parse <body> with DOMDocument
 *   4. Walk DOM → apply widget-map rules → build Elementor IR
 *   5. Strip header/footer nodes (configurable via $options)
 *   6. Collapse consecutive <details> siblings into single accordion widgets
 *
 * Output: `{structure, brand_tokens, tokens, css, stats}` that
 * Design_Abilities::execute_import_design() can apply to a post.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Converts design HTML to Elementor IR.
 *
 * @since 1.5.0
 */
class Elementor_MCP_Design_Importer {

	/**
	 * Tags dropped during walk (never generate widgets).
	 *
	 * @var string[]
	 */
	private $skip_tags = array( 'head', 'title', 'meta', 'link', 'style', 'script', 'noscript' );

	/**
	 * Class substrings that mark page chrome (topnav/footer).
	 *
	 * @var string[]
	 */
	private $skip_class_contains = array( 'topnav', 'site-header', 'site-footer' );

	/**
	 * Imports an HTML design file into Elementor IR.
	 *
	 * @param array $input {
	 *     @type string $html           Inline HTML. Ignored if `url` provided.
	 *     @type string $url            URL to fetch HTML from.
	 *     @type bool   $skip_header    Skip topnav-like elements. Default true.
	 *     @type bool   $skip_footer    Skip footer-like elements. Default true.
	 *     @type string $wrapper_class  Wrapper container css_classes. Default 'emcp-imported-page'.
	 * }
	 * @return array|\WP_Error
	 */
	public function import( array $input ) {
		$html = (string) ( $input['html'] ?? '' );
		$url  = (string) ( $input['url'] ?? '' );

		if ( '' === $html && '' !== $url ) {
			$fetched = $this->fetch_url( $url );
			if ( is_wp_error( $fetched ) ) {
				return $fetched;
			}
			$html = $fetched;
		}

		if ( '' === trim( $html ) ) {
			return new \WP_Error( 'emcp_import_empty', __( 'Design HTML is empty.', 'elementor-mcp' ) );
		}

		$skip_header     = (bool) ( $input['skip_header']     ?? true );
		$skip_footer     = (bool) ( $input['skip_footer']     ?? true );
		$sideload_images = (bool) ( $input['sideload_images'] ?? true );
		$wrapper_class   = (string) ( $input['wrapper_class'] ?? 'emcp-imported-page' );

		// 1. Tokens from <style> :root vars.
		$style_block = $this->extract_style_block( $html );
		$tokens      = function_exists( 'emcp_tokens_css_var_extract' )
			? emcp_tokens_css_var_extract( $style_block )
			: array( 'palette' => array(), 'typography' => array( 'families' => array(), 'sizes' => array() ), 'raw' => array() );

		// Phase C: publish --css-var → value map so inline-style-parser resolves var() / rgba(var()).
		if ( function_exists( 'emcp_inline_style_current_vars' ) ) {
			$raw_vars = array();
			if ( isset( $tokens['raw'] ) && is_array( $tokens['raw'] ) ) {
				foreach ( $tokens['raw'] as $name => $value ) {
					// Ensure `--` prefix on keys for direct lookup by the resolver.
					$key              = ( 0 === strpos( (string) $name, '--' ) ) ? $name : '--' . ltrim( (string) $name, '-' );
					$raw_vars[ $key ] = (string) $value;
				}
			}
			emcp_inline_style_current_vars( $raw_vars );
		}

		// 2. DOMDocument parse.
		$dom = new \DOMDocument();
		libxml_use_internal_errors( true );
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8"?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		if ( ! $loaded ) {
			return new \WP_Error( 'emcp_import_dom_fail', __( 'Failed to parse design HTML.', 'elementor-mcp' ) );
		}

		$body = $this->find_body( $dom );
		if ( ! $body ) {
			return new \WP_Error( 'emcp_import_no_body', __( 'Design HTML has no <body>.', 'elementor-mcp' ) );
		}

		$stats = array(
			'elements_mapped'      => 0,
			'html_widgets'         => 0,
			'native_widgets'       => 0,
			'accordions_collapsed' => 0,
			'images_sideloaded'    => 0,
			'images_skipped'       => 0,
			'unmapped_elements'    => array(), // Layer 3: elements that fell to html-widget fallback.
		);

		// Phase C: sideload external images → WP media library, rewrite src + stamp attachment ID.
		if ( $sideload_images ) {
			$this->sideload_external_images( $dom, $stats );
		}

		// Phase C: build CSS class-rule map from <style> block + publish to ambient accessor.
		//         Extractors (container, heading, etc.) read it to merge class styles into settings.
		$rule_map = array();
		if ( '' !== trim( $style_block ) && function_exists( 'emcp_css_build_rule_map' ) ) {
			$rule_map = emcp_css_build_rule_map( $style_block );
		}
		if ( function_exists( 'emcp_css_current_rule_map' ) ) {
			emcp_css_current_rule_map( $rule_map );
		}

		// QW4: track <style> class rules that won't be applied until Phase C's resolver ships.
		//      Surfaces gap #3 ("class rules silently dropped") to Claude via unmapped_elements.
		if ( '' !== trim( $style_block ) ) {
			$class_rules = $this->extract_class_rules( $style_block );
			foreach ( $class_rules as $selector => $declarations ) {
				$stats['unmapped_elements'][] = array(
					'tag'     => 'style',
					'class'   => $selector,
					'id'      => '',
					'snippet' => substr( $selector . '{' . $declarations . '}', 0, 300 ),
					'reason'  => 'css_rule_unresolved',
					'hint'    => 'Class rules from <style> blocks are not yet merged into widget settings. Inline style="…" on the matching element works today.',
				);
			}
		}

		// 3. Walk body children.
		$top_level_nodes = array();
		foreach ( $body->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}
			if ( $this->should_skip_element( $child, $skip_header, $skip_footer ) ) {
				continue;
			}
			$ir = $this->walk_element( $child, $stats );
			if ( $ir ) {
				$top_level_nodes[] = $ir;
			}
		}

		// 4. Collapse consecutive <details> → one accordion widget.
		$top_level_nodes = $this->collapse_accordion_siblings( $top_level_nodes, $stats );

		// 5. Wrap everything in an outer container carrying the CSS scope class.
		//    QW1: only force padding=0/flex_gap=0 when <body> has NO inline style.
		//    When body has inline styles, parse them so spacing intent is preserved.
		$body_style   = $body->getAttribute( 'style' );
		$body_parsed  = ( '' !== trim( $body_style ) && function_exists( 'emcp_parse_inline_styles' ) )
			? emcp_parse_inline_styles( $body_style )
			: array();
		$wrap_settings = array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'_title'         => 'Imported design wrapper',
			'css_classes'    => $wrapper_class,
		);
		if ( empty( $body_parsed ) ) {
			// No body-level styling supplied → safe to reset spacing so importer output
			// doesn't pick up a theme default. Existing behaviour.
			$wrap_settings['padding']  = array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true );
			$wrap_settings['flex_gap'] = array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true );
		} else {
			// Body has inline style → merge parsed values (background_color / padding / etc.)
			$wrap_settings = array_merge( $wrap_settings, $body_parsed );
			// Preserve the wrapper class even if body style accidentally set css_classes.
			$wrap_settings['css_classes'] = $wrapper_class;
		}
		$wrapper = array(
			'type'     => 'container',
			'settings' => $wrap_settings,
			'children' => $top_level_nodes,
		);

		$return = array(
			'structure'         => array( $wrapper ),
			'brand_tokens'      => array(
				'palette'    => $tokens['palette'],
				'typography' => $tokens['typography'],
			),
			'tokens'            => $tokens,
			'css'               => $style_block,
			'stats'             => $stats,
			'unmapped_elements' => $stats['unmapped_elements'], // Layer 3: Claude reads this to re-annotate.
		);

		// Clear ambient rule map + var map so a second import() call starts fresh.
		if ( function_exists( 'emcp_css_current_rule_map' ) ) {
			emcp_css_current_rule_map( null );
		}
		if ( function_exists( 'emcp_inline_style_current_vars' ) ) {
			emcp_inline_style_current_vars( null );
		}

		return $return;
	}

	/**
	 * Recursively walks an element and returns its Elementor IR node.
	 * Null if the element should be dropped.
	 */
	private function walk_element( \DOMElement $el, array &$stats ): ?array {
		$rule = function_exists( 'emcp_design_find_rule' ) ? emcp_design_find_rule( $el ) : null;
		if ( ! $rule ) {
			if ( $this->has_element_children( $el ) ) {
				return $this->walk_as_container( $el, $stats );
			}
			$stats['html_widgets']++;
			$stats['unmapped_elements'][] = array(
				'tag'     => $el->tagName,
				'class'   => $el->getAttribute( 'class' ),
				'id'      => $el->getAttribute( 'id' ),
				'snippet' => substr( $el->ownerDocument->saveHTML( $el ), 0, 300 ),
				'reason'  => 'no_rule_leaf',
				'hint'    => 'Add data-emcp-widget="[widget_type]" to force a mapping.',
			);
			return array(
				'type'        => 'widget',
				'widget_type' => 'html',
				'settings'    => array( 'html' => $el->ownerDocument->saveHTML( $el ) ),
			);
		}

		$stats['elements_mapped']++;

		if ( 'container' === $rule['widget_type'] ) {
			return $this->walk_as_container( $el, $stats, $rule );
		}

		$extracted = call_user_func( $rule['extractor'], $el );
		$wtype     = $extracted['widget_type'] ?? 'html';
		if ( 'html' === $wtype ) {
			$stats['html_widgets']++;
			$stats['unmapped_elements'][] = array(
				'tag'     => $el->tagName,
				'class'   => $el->getAttribute( 'class' ),
				'id'      => $el->getAttribute( 'id' ),
				'snippet' => substr( $el->ownerDocument->saveHTML( $el ), 0, 300 ),
				'reason'  => 'forced_html_rule',
				'hint'    => 'Rule matched but extractor returned html widget (form/svg/iframe — expected).',
			);
		} else {
			$stats['native_widgets']++;
		}

		return array(
			'type'        => 'widget',
			'widget_type' => $wtype,
			'settings'    => $extracted['settings'] ?? array(),
		);
	}

	/**
	 * Walks an element as a container — runs container extractor,
	 * then recursively maps children.
	 */
	private function walk_as_container( \DOMElement $el, array &$stats, ?array $rule = null ): ?array {
		if ( null === $rule ) {
			$rule = array(
				'widget_type' => 'container',
				'extractor'   => 'emcp_design_extractor_container',
			);
		}

		$extracted = call_user_func( $rule['extractor'], $el );
		$settings  = $extracted['settings'] ?? array();

		$children = array();
		foreach ( $el->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}
			if ( in_array( strtolower( $child->tagName ), $this->skip_tags, true ) ) {
				continue;
			}
			$child_ir = $this->walk_element( $child, $stats );
			if ( $child_ir ) {
				$children[] = $child_ir;
			}
		}

		$children = $this->collapse_accordion_siblings( $children, $stats );

		return array(
			'type'     => 'container',
			'settings' => $settings,
			'children' => $children,
		);
	}

	/**
	 * Merges consecutive single-tab accordion items (one per <details>)
	 * into a single accordion widget with tabs[].
	 */
	private function collapse_accordion_siblings( array $siblings, array &$stats ): array {
		$out    = array();
		$buffer = array();

		$flush = function () use ( &$out, &$buffer, &$stats ) {
			if ( empty( $buffer ) ) {
				return;
			}
			$stats['accordions_collapsed']++;
			$tabs = array();
			foreach ( $buffer as $item ) {
				$s      = $item['settings'];
				$tabs[] = array(
					'tab_title'   => $s['_tab_title']   ?? '',
					'tab_content' => $s['_tab_content'] ?? '',
					'_id'         => $s['_tab_id']      ?? substr( md5( (string) ( $s['_tab_title'] ?? '' ) ), 0, 7 ),
				);
			}
			$out[] = array(
				'type'        => 'widget',
				'widget_type' => 'accordion',
				'settings'    => array(
					'tabs'                 => $tabs,
					'selected_icon'        => array( 'value' => 'fas fa-plus', 'library' => 'fa-solid' ),
					'selected_active_icon' => array( 'value' => 'fas fa-minus', 'library' => 'fa-solid' ),
					'title_html_tag'       => 'h4',
				),
			);
			$buffer = array();
		};

		foreach ( $siblings as $node ) {
			$is_accordion_leaf = isset( $node['type'], $node['widget_type'] )
				&& 'widget' === $node['type']
				&& 'accordion' === $node['widget_type']
				&& isset( $node['settings']['_tab_title'] );
			if ( $is_accordion_leaf ) {
				$buffer[] = $node;
				continue;
			}
			$flush();
			$out[] = $node;
		}
		$flush();

		return $out;
	}

	/**
	 * Skip page chrome that conflicts with WP theme header/footer.
	 */
	private function should_skip_element( \DOMElement $el, bool $skip_header, bool $skip_footer ): bool {
		$tag = strtolower( $el->tagName );
		if ( in_array( $tag, $this->skip_tags, true ) ) {
			return true;
		}
		$cls = $el->getAttribute( 'class' );
		foreach ( $this->skip_class_contains as $needle ) {
			if ( false === strpos( $cls, $needle ) ) {
				continue;
			}
			if ( 'topnav' === $needle || 'site-header' === $needle ) {
				return $skip_header;
			}
			if ( 'site-footer' === $needle ) {
				return $skip_footer;
			}
		}
		if ( 'header' === $tag && $skip_header ) {
			return true;
		}
		if ( 'footer' === $tag && $skip_footer ) {
			return true;
		}
		return false;
	}

	private function has_element_children( \DOMElement $el ): bool {
		foreach ( $el->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				return true;
			}
		}
		return false;
	}

	private function find_body( \DOMDocument $dom ): ?\DOMElement {
		$bodies = $dom->getElementsByTagName( 'body' );
		if ( $bodies->length > 0 ) {
			return $bodies->item( 0 );
		}
		foreach ( $dom->childNodes as $n ) {
			if ( $n instanceof \DOMElement ) {
				return $n;
			}
		}
		return null;
	}

	/**
	 * Pre-pass: download every external `<img src="http(s)://…">` to the WP
	 * media library, rewrite `src` to the local URL, stamp `data-emcp-attachment-id`
	 * so the image extractor can use it.
	 *
	 * Non-fatal on failure — adds `unmapped_elements` entry + bumps `images_skipped`.
	 */
	private function sideload_external_images( \DOMDocument $dom, array &$stats ): void {
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			if ( defined( 'ABSPATH' ) && is_dir( ABSPATH . 'wp-admin/includes' ) ) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
			} else {
				return; // no WP — running in CLI test harness, skip silently
			}
		}

		$home_host = '';
		if ( function_exists( 'home_url' ) ) {
			$parts     = wp_parse_url( home_url() );
			$home_host = strtolower( $parts['host'] ?? '' );
		}

		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			if ( ! $img instanceof \DOMElement ) {
				continue;
			}
			$src = $img->getAttribute( 'src' );
			if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
				continue;
			}
			if ( ! preg_match( '#^https?://#i', $src ) ) {
				continue; // relative or data URI
			}
			$parts = wp_parse_url( $src );
			$host  = strtolower( $parts['host'] ?? '' );
			if ( '' !== $home_host && $host === $home_host ) {
				continue; // already local
			}

			$tmp = download_url( $src, 20 );
			if ( is_wp_error( $tmp ) ) {
				$stats['images_skipped']++;
				$stats['unmapped_elements'][] = array(
					'tag'     => 'img',
					'class'   => $img->getAttribute( 'class' ),
					'id'      => $img->getAttribute( 'id' ),
					'snippet' => substr( $src, 0, 300 ),
					'reason'  => 'image_sideload_failed',
					'hint'    => 'download_url failed: ' . $tmp->get_error_message() . '. Image left external.',
				);
				continue;
			}
			$url_path = wp_parse_url( $src, PHP_URL_PATH );
			$filename = $url_path ? basename( $url_path ) : 'image.jpg';
			if ( ! preg_match( '/\.\w+$/', $filename ) ) {
				$filename .= '.jpg';
			}
			$file_array = array(
				'name'     => sanitize_file_name( $filename ),
				'tmp_name' => $tmp,
			);
			$attachment_id = media_handle_sideload( $file_array, 0 );
			if ( is_wp_error( $attachment_id ) ) {
				if ( file_exists( $tmp ) ) {
					wp_delete_file( $tmp );
				}
				$stats['images_skipped']++;
				$stats['unmapped_elements'][] = array(
					'tag'     => 'img',
					'class'   => $img->getAttribute( 'class' ),
					'id'      => $img->getAttribute( 'id' ),
					'snippet' => substr( $src, 0, 300 ),
					'reason'  => 'image_sideload_failed',
					'hint'    => 'media_handle_sideload failed: ' . $attachment_id->get_error_message(),
				);
				continue;
			}
			$alt = $img->getAttribute( 'alt' );
			if ( '' !== $alt ) {
				update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
			}
			$local_url = wp_get_attachment_url( $attachment_id );
			if ( $local_url ) {
				$img->setAttribute( 'src', $local_url );
			}
			$img->setAttribute( 'data-emcp-attachment-id', (string) $attachment_id );
			$stats['images_sideloaded']++;
		}
	}

	private function extract_style_block( string $html ): string {
		if ( preg_match( '#<style[^>]*>(.+?)</style>#is', $html, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Extracts class-selector rules from a CSS block into a map.
	 *
	 * Deliberately naive regex tokenizer — skips `:root`, `@media`, `@supports`,
	 * element selectors, and pseudo-classes. Goal is to surface the MAGNITUDE
	 * of dropped styling so Claude knows to add matching inline styles.
	 *
	 * @param string $css Raw CSS text.
	 * @return array<string,string> Selector → declarations block (inner text).
	 */
	private function extract_class_rules( string $css ): array {
		$out = array();
		// Strip comments + :root block so they don't pollute the match.
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		$css = preg_replace( '#:root\s*\{[^}]*\}#s', '', (string) $css );
		// Strip @-rules (media/supports/keyframes) for now — they bring their own parsing cost.
		$css = preg_replace( '#@[a-z-]+[^{}]*\{(?:[^{}]*\{[^}]*\})*[^}]*\}#is', '', (string) $css );
		if ( ! preg_match_all( '/([^{}]+)\{([^}]+)\}/s', (string) $css, $matches, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( $matches as $m ) {
			$selector     = trim( $m[1] );
			$declarations = trim( $m[2] );
			// Only keep selectors that mention at least one class.
			if ( false === strpos( $selector, '.' ) ) {
				continue;
			}
			$out[ $selector ] = $declarations;
		}
		return $out;
	}

	private function fetch_url( string $url ) {
		if ( ! function_exists( 'wp_remote_get' ) ) {
			return new \WP_Error( 'emcp_import_http_unavailable', __( 'wp_remote_get() unavailable.', 'elementor-mcp' ) );
		}
		$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'emcp_import_http_status',
				sprintf( __( 'HTTP %d fetching design URL.', 'elementor-mcp' ), $code )
			);
		}
		return (string) wp_remote_retrieve_body( $res );
	}
}
