<?php
/**
 * Semantic HTML → Elementor Widget Map.
 *
 * Single source of truth for "what Elementor widget represents a given
 * semantic HTML element" used by the Design Importer. Rules table is
 * ordered: the FIRST match wins.
 *
 * Each rule has:
 *   match:       {tag?, tag_in?, class_contains?, class_pattern?, has_child_tag?, has_data_attr?}
 *   widget_type: 'heading' | 'text-editor' | 'button' | 'icon-box' | 'accordion' | 'icon-list' | 'image' | 'html' | 'container'
 *   extractor:   callable(\DOMElement): array  Returns {widget_type, settings}.
 *
 * Each extractor is small — pulls content + href + icon from the DOM
 * node and delegates full-setting composition to Design_Importer
 * which has access to Token_Resolver.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_design_widget_map' ) ) {
	/**
	 * Returns the ordered widget-map rules.
	 *
	 * @return array<int, array>
	 */
	function emcp_design_widget_map(): array {
		return array(

			// 1. Explicit author override — data-emcp-widget="html" forces raw HTML widget.
			array(
				'match'       => array( 'has_data_attr' => 'emcp-widget' ),
				'widget_type' => '__data_attr__',
				'extractor'   => 'emcp_design_extractor_data_attr',
			),

			// 2. Accordion — <details> with <summary>.
			array(
				'match'       => array( 'tag' => 'details' ),
				'widget_type' => 'accordion',
				'extractor'   => 'emcp_design_extractor_accordion_item',
			),

			// 3. Buttons — <a>/<button> with class containing "btn".
			array(
				'match'       => array( 'tag' => 'a', 'class_pattern' => '/\bbtn(--[a-z0-9-]+)?\b/' ),
				'widget_type' => 'button',
				'extractor'   => 'emcp_design_extractor_button',
			),
			array(
				'match'       => array( 'tag' => 'button', 'class_pattern' => '/\bbtn(--[a-z0-9-]+)?\b/' ),
				'widget_type' => 'button',
				'extractor'   => 'emcp_design_extractor_button',
			),

			// 4. Icon box — compound card with heading + description.
			array(
				'match'       => array(
					'has_child_tag' => 'h1|h2|h3|h4|h5|h6',
					'class_pattern' => '/\b(card|icon-box|feature|tile|badge)\b/',
				),
				'widget_type' => 'icon-box',
				'extractor'   => 'emcp_design_extractor_icon_box',
			),

			// 5. Icon list — <nav>.
			array(
				'match'       => array( 'tag' => 'nav' ),
				'widget_type' => 'icon-list',
				'extractor'   => 'emcp_design_extractor_icon_list',
			),

			// 6. Images.
			array(
				'match'       => array( 'tag' => 'img' ),
				'widget_type' => 'image',
				'extractor'   => 'emcp_design_extractor_image',
			),

			// 7. Headings.
			array(
				'match'       => array( 'tag_in' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) ),
				'widget_type' => 'heading',
				'extractor'   => 'emcp_design_extractor_heading',
			),

			// 8. Paragraphs.
			array(
				'match'       => array( 'tag' => 'p' ),
				'widget_type' => 'text-editor',
				'extractor'   => 'emcp_design_extractor_paragraph',
			),

			// 9. Interactive/opaque content → HTML fallback.
			array(
				'match'       => array( 'tag_in' => array( 'form', 'svg', 'script', 'iframe' ) ),
				'widget_type' => 'html',
				'extractor'   => 'emcp_design_extractor_outer_html',
			),

			// 10. Container — structural wrappers, children walked recursively.
			array(
				'match'       => array( 'tag_in' => array( 'section', 'div', 'aside', 'header', 'footer', 'main', 'article' ) ),
				'widget_type' => 'container',
				'extractor'   => 'emcp_design_extractor_container',
			),
		);
	}
}

/* ═════════════════════════════════════════════════════════════════
   EXTRACTORS
   ═════════════════════════════════════════════════════════════════ */

if ( ! function_exists( 'emcp_design_extractor_heading' ) ) {
	function emcp_design_extractor_heading( \DOMElement $el ): array {
		$tag  = strtolower( $el->tagName );
		return array(
			'widget_type' => 'heading',
			'settings'    => array(
				'title'        => emcp_design_inner_html( $el ),
				'header_size'  => $tag,
				'_css_classes' => $el->getAttribute( 'class' ),
			),
		);
	}
}

if ( ! function_exists( 'emcp_design_extractor_paragraph' ) ) {
	function emcp_design_extractor_paragraph( \DOMElement $el ): array {
		return array(
			'widget_type' => 'text-editor',
			'settings'    => array(
				'editor'       => '<p>' . emcp_design_inner_html( $el ) . '</p>',
				'_css_classes' => $el->getAttribute( 'class' ),
			),
		);
	}
}

if ( ! function_exists( 'emcp_design_extractor_button' ) ) {
	function emcp_design_extractor_button( \DOMElement $el ): array {
		$text = trim( $el->textContent );
		$href = $el->getAttribute( 'href' );
		$icon = emcp_design_find_icon_class( $el );
		$lib  = emcp_design_icon_library( $icon );

		return array(
			'widget_type' => 'button',
			'settings'    => array(
				'text'          => $text,
				'link'          => array( 'url' => $href, 'is_external' => ( strpos( $href, 'http' ) === 0 ? 'on' : '' ), 'nofollow' => '' ),
				'selected_icon' => $icon ? array( 'value' => $icon, 'library' => $lib ) : array( 'value' => '', 'library' => '' ),
				'icon_align'    => 'left',
				'_css_classes'  => $el->getAttribute( 'class' ),
			),
		);
	}
}

if ( ! function_exists( 'emcp_design_extractor_icon_box' ) ) {
	function emcp_design_extractor_icon_box( \DOMElement $el ): array {
		$heading = emcp_design_first_child_tag( $el, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ) );
		$para    = emcp_design_first_child_tag( $el, array( 'p' ) );
		$icon    = emcp_design_find_icon_class( $el );
		$lib     = emcp_design_icon_library( $icon );
		$link    = strtolower( $el->tagName ) === 'a' ? $el->getAttribute( 'href' ) : '';

		$title = $heading ? trim( $heading->textContent ) : '';
		$desc  = $para ? emcp_design_inner_html( $para ) : '';

		return array(
			'widget_type' => 'icon-box',
			'settings'    => array(
				'selected_icon'    => $icon ? array( 'value' => $icon, 'library' => $lib ) : array( 'value' => '', 'library' => '' ),
				'title_text'       => $title,
				'description_text' => $desc,
				'link'             => array( 'url' => $link, 'is_external' => '', 'nofollow' => '' ),
				'title_size'       => $heading ? strtolower( $heading->tagName ) : 'h3',
				'position'         => 'top',
				'_css_classes'     => $el->getAttribute( 'class' ),
			),
		);
	}
}

if ( ! function_exists( 'emcp_design_extractor_accordion_item' ) ) {
	/**
	 * One <details> → one accordion tab. Design_Importer groups
	 * consecutive <details> siblings into a single accordion widget.
	 */
	function emcp_design_extractor_accordion_item( \DOMElement $el ): array {
		$summary = emcp_design_first_child_tag( $el, array( 'summary' ) );
		$q       = $summary ? trim( $summary->textContent ) : '';

		// Answer = all element children except the <summary>.
		$answer_html = '';
		foreach ( $el->childNodes as $child ) {
			if ( $child instanceof \DOMElement && strtolower( $child->tagName ) === 'summary' ) {
				continue;
			}
			$answer_html .= $el->ownerDocument->saveHTML( $child );
		}

		return array(
			'widget_type' => 'accordion',
			'settings'    => array(
				'_tab_title'   => $q,
				'_tab_content' => $answer_html,
				'_tab_id'      => substr( md5( $q ), 0, 7 ),
				'_css_classes' => $el->getAttribute( 'class' ),
			),
		);
	}
}

if ( ! function_exists( 'emcp_design_extractor_icon_list' ) ) {
	function emcp_design_extractor_icon_list( \DOMElement $el ): array {
		$items = array();
		$i     = 0;
		foreach ( $el->getElementsByTagName( 'a' ) as $a ) {
			$items[] = array(
				'text'          => trim( $a->textContent ),
				'link'          => array( 'url' => $a->getAttribute( 'href' ), 'is_external' => '', 'nofollow' => '' ),
				'selected_icon' => array( 'value' => '', 'library' => '' ),
				'_id'           => 'il' . $i,
			);
			$i++;
		}
		return array(
			'widget_type' => 'icon-list',
			'settings'    => array(
				'view'         => 'inline',
				'icon_list'    => $items,
				'_css_classes' => $el->getAttribute( 'class' ),
			),
		);
	}
}

if ( ! function_exists( 'emcp_design_extractor_image' ) ) {
	function emcp_design_extractor_image( \DOMElement $el ): array {
		return array(
			'widget_type' => 'image',
			'settings'    => array(
				'image'        => array( 'url' => $el->getAttribute( 'src' ), 'id' => 0, 'alt' => $el->getAttribute( 'alt' ) ),
				'_css_classes' => $el->getAttribute( 'class' ),
			),
		);
	}
}

if ( ! function_exists( 'emcp_design_extractor_outer_html' ) ) {
	function emcp_design_extractor_outer_html( \DOMElement $el ): array {
		return array(
			'widget_type' => 'html',
			'settings'    => array(
				'html' => $el->ownerDocument->saveHTML( $el ),
			),
		);
	}
}

if ( ! function_exists( 'emcp_design_extractor_data_attr' ) ) {
	function emcp_design_extractor_data_attr( \DOMElement $el ): array {
		$type = $el->getAttribute( 'data-emcp-widget' );
		return array(
			'widget_type' => $type ?: 'html',
			'settings'    => array(
				'html'         => $el->ownerDocument->saveHTML( $el ),
				'_css_classes' => $el->getAttribute( 'class' ),
			),
		);
	}
}

if ( ! function_exists( 'emcp_design_extractor_container' ) ) {
	function emcp_design_extractor_container( \DOMElement $el ): array {
		$style = $el->getAttribute( 'style' );
		$cls   = $el->getAttribute( 'class' );
		$id    = $el->getAttribute( 'id' );

		$flex_direction = 'column';
		if ( preg_match( '/display\s*:\s*flex/i', $style ) && preg_match( '/flex-direction\s*:\s*row/i', $style ) ) {
			$flex_direction = 'row';
		}

		$settings = array(
			'content_width'  => 'full',
			'flex_direction' => $flex_direction,
			'css_classes'    => $cls,
		);
		if ( $id ) {
			$settings['_element_id'] = $id;
		}

		return array(
			'widget_type' => 'container',
			'settings'    => $settings,
		);
	}
}

/* ═════════════════════════════════════════════════════════════════
   DOM HELPERS
   ═════════════════════════════════════════════════════════════════ */

if ( ! function_exists( 'emcp_design_inner_html' ) ) {
	function emcp_design_inner_html( \DOMElement $el ): string {
		$html = '';
		foreach ( $el->childNodes as $child ) {
			$html .= $el->ownerDocument->saveHTML( $child );
		}
		return $html;
	}
}

if ( ! function_exists( 'emcp_design_first_child_tag' ) ) {
	function emcp_design_first_child_tag( \DOMElement $el, array $tags ): ?\DOMElement {
		$tags = array_map( 'strtolower', $tags );
		foreach ( $el->getElementsByTagName( '*' ) as $child ) {
			if ( in_array( strtolower( $child->tagName ), $tags, true ) ) {
				return $child;
			}
		}
		return null;
	}
}

if ( ! function_exists( 'emcp_design_find_icon_class' ) ) {
	/**
	 * First Font Awesome icon class found inside the element (recursive).
	 * Returns e.g. `fa-solid fa-arrow-right`. Empty string if none.
	 */
	function emcp_design_find_icon_class( \DOMElement $el ): string {
		foreach ( $el->getElementsByTagName( 'i' ) as $i ) {
			$cls = $i->getAttribute( 'class' );
			if ( preg_match( '/\b(fa-solid|fa-regular|fa-brands|fas|far|fab)\b[^\"]*?\b(fa-[a-z0-9-]+)\b/', $cls, $m ) ) {
				return $m[1] . ' ' . $m[2];
			}
		}
		return '';
	}
}

if ( ! function_exists( 'emcp_design_icon_library' ) ) {
	function emcp_design_icon_library( string $icon_class ): string {
		if ( strpos( $icon_class, 'fa-brands' ) === 0 || strpos( $icon_class, 'fab ' ) === 0 ) {
			return 'fa-brands';
		}
		if ( strpos( $icon_class, 'fa-regular' ) === 0 || strpos( $icon_class, 'far ' ) === 0 ) {
			return 'fa-regular';
		}
		return 'fa-solid';
	}
}

if ( ! function_exists( 'emcp_design_match_rule' ) ) {
	/**
	 * Tests whether a DOM element matches a rule's `match` spec.
	 */
	function emcp_design_match_rule( \DOMElement $el, array $match ): bool {
		$tag = strtolower( $el->tagName );
		$cls = $el->getAttribute( 'class' );

		if ( isset( $match['tag'] ) && $tag !== $match['tag'] ) {
			return false;
		}
		if ( isset( $match['tag_in'] ) && ! in_array( $tag, array_map( 'strtolower', $match['tag_in'] ), true ) ) {
			return false;
		}
		if ( isset( $match['class_contains'] ) ) {
			$needles = (array) $match['class_contains'];
			$ok = false;
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $cls, $needle ) ) {
					$ok = true;
					break;
				}
			}
			if ( ! $ok ) {
				return false;
			}
		}
		if ( isset( $match['class_pattern'] ) && ! preg_match( $match['class_pattern'], $cls ) ) {
			return false;
		}
		if ( isset( $match['has_child_tag'] ) ) {
			$tags = explode( '|', $match['has_child_tag'] );
			$has  = false;
			foreach ( $tags as $t ) {
				if ( $el->getElementsByTagName( trim( $t ) )->length > 0 ) {
					$has = true;
					break;
				}
			}
			if ( ! $has ) {
				return false;
			}
		}
		if ( isset( $match['has_data_attr'] ) && ! $el->hasAttribute( 'data-' . $match['has_data_attr'] ) ) {
			return false;
		}
		return true;
	}
}

if ( ! function_exists( 'emcp_design_find_rule' ) ) {
	/**
	 * Returns the first widget-map rule matching this element, or null.
	 */
	function emcp_design_find_rule( \DOMElement $el ): ?array {
		foreach ( emcp_design_widget_map() as $rule ) {
			if ( emcp_design_match_rule( $el, $rule['match'] ) ) {
				return $rule;
			}
		}
		return null;
	}
}
