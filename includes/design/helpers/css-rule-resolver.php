<?php
/**
 * CSS Class-Rule Resolver — parses `<style>` blocks into a selector→declarations
 * map and merges declarations for a given DOM element into a flat style string.
 *
 * Fixes the fidelity gap where design stylesheets like
 * `.hero__title { font-size: 80px; color: #fff }` were silently dropped — only
 * `:root` vars were captured previously. After running this resolver, every
 * element's matching class rules are flattened into a style-attribute-shaped
 * string, which then passes through `emcp_parse_inline_styles()` for final
 * Elementor settings.
 *
 * Supported selectors:
 *   .single-class
 *   .class-a, .class-b               (comma list, split by caller)
 *   .parent .child                   (descendant — class-only)
 *   .class.other                     (AND — both classes must be present)
 *   div.class                        (tag + class compound)
 *
 * NOT supported (caller strips @-rules; unsupported compounds silently miss):
 *   @media / @supports / @keyframes
 *   element-only selectors (div, h1)
 *   [attr=…]
 *   :pseudo-classes (:hover, :focus)
 *   > child / + sibling / ~ general
 *
 * Inline style on element wins over class-rule output (specificity handled by
 * caller via string concatenation `resolved + ';' + inline`).
 *
 * @package Elementor_MCP
 * @since   1.5.2
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_css_build_rule_map' ) ) {
	/**
	 * Parses a CSS text blob into an ordered list of `{selector, decls}` rules.
	 * Preserves source order so last-match-wins behaviour works downstream.
	 *
	 * @param string $css Raw CSS from a <style> block.
	 * @return array<int,array{selector:string,decls:string}>
	 */
	function emcp_css_build_rule_map( string $css ): array {
		$out = array();
		// Strip comments.
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );
		// Strip `:root { }` (handled by var-extractor).
		$css = preg_replace( '#:root\s*\{[^}]*\}#s', '', (string) $css );
		// Strip @-rules (media/supports/keyframes) — single pass, safe-ish.
		$css = preg_replace( '#@[a-z-]+[^{}]*\{(?:[^{}]*\{[^}]*\})*[^}]*\}#is', '', (string) $css );
		if ( ! preg_match_all( '/([^{}]+)\{([^}]+)\}/s', (string) $css, $matches, PREG_SET_ORDER ) ) {
			return $out;
		}
		foreach ( $matches as $m ) {
			$selector_list = trim( $m[1] );
			$decls         = trim( $m[2] );
			if ( '' === $selector_list || '' === $decls ) {
				continue;
			}
			foreach ( explode( ',', $selector_list ) as $sel ) {
				$sel = trim( $sel );
				if ( '' === $sel || false === strpos( $sel, '.' ) ) {
					// Only retain selectors that include at least one class.
					continue;
				}
				$out[] = array( 'selector' => $sel, 'decls' => $decls );
			}
		}
		return $out;
	}
}

if ( ! function_exists( 'emcp_css_element_classes' ) ) {
	/**
	 * Returns a set-like map of all classes on an element.
	 *
	 * @param \DOMElement $el Element.
	 * @return array<string,true>
	 */
	function emcp_css_element_classes( \DOMElement $el ): array {
		$classes = preg_split( '/\s+/', trim( $el->getAttribute( 'class' ) ) );
		$set     = array();
		foreach ( $classes as $c ) {
			if ( '' !== $c ) {
				$set[ $c ] = true;
			}
		}
		return $set;
	}
}

if ( ! function_exists( 'emcp_css_compound_matches' ) ) {
	/**
	 * Tests a single compound selector (`.card.primary`, `div.foo`) against one element.
	 * Only `.class` + leading tag name tokens are honoured.
	 */
	function emcp_css_compound_matches( string $compound, \DOMElement $el ): bool {
		if ( preg_match( '/[>+~\[:]/', $compound ) ) {
			return false;
		}
		if ( ! preg_match_all( '/\.([A-Za-z0-9_-]+)/', $compound, $m ) ) {
			return false;
		}
		// Leading tag like `div.foo` — must match element tag if present.
		$leading = preg_replace( '/\..*$/', '', $compound );
		if ( '' !== $leading && strtolower( $leading ) !== strtolower( $el->tagName ) ) {
			return false;
		}
		$classes = emcp_css_element_classes( $el );
		foreach ( $m[1] as $req ) {
			if ( ! isset( $classes[ $req ] ) ) {
				return false;
			}
		}
		return true;
	}
}

if ( ! function_exists( 'emcp_css_selector_matches' ) ) {
	/**
	 * Tests whether a CSS selector matches the given element.
	 *
	 * Rightmost compound must match $el directly; each prior compound must
	 * match SOME ancestor (descendant combinator only).
	 *
	 * @param string      $selector CSS selector (no @-rules).
	 * @param \DOMElement $el       Target element.
	 * @return bool
	 */
	function emcp_css_selector_matches( string $selector, \DOMElement $el ): bool {
		$compounds = preg_split( '/\s+/', trim( $selector ) );
		if ( ! $compounds ) {
			return false;
		}
		$compounds = array_reverse( $compounds );
		if ( ! emcp_css_compound_matches( $compounds[0], $el ) ) {
			return false;
		}
		if ( count( $compounds ) === 1 ) {
			return true;
		}
		$ancestor = $el->parentNode;
		for ( $i = 1, $n = count( $compounds ); $i < $n; $i++ ) {
			$found = false;
			while ( $ancestor instanceof \DOMElement ) {
				if ( emcp_css_compound_matches( $compounds[ $i ], $ancestor ) ) {
					$found    = true;
					$ancestor = $ancestor->parentNode;
					break;
				}
				$ancestor = $ancestor->parentNode;
			}
			if ( ! $found ) {
				return false;
			}
		}
		return true;
	}
}

if ( ! function_exists( 'emcp_css_current_rule_map' ) ) {
	/**
	 * Ambient accessor for the current import's rule map. Design_Importer sets
	 * this at the start of each import() call; free-function extractors read it
	 * without needing to thread the map through every signature.
	 *
	 * Pass `null` to clear. Pass an array to set.
	 *
	 * @param array|null $set Setter value (null → getter mode).
	 * @return array<int,array{selector:string,decls:string}>
	 */
	function emcp_css_current_rule_map( $set = false ): array {
		static $map = array();
		if ( is_array( $set ) ) {
			$map = $set;
		} elseif ( null === $set ) {
			$map = array();
		}
		return $map;
	}
}

if ( ! function_exists( 'emcp_css_resolve_element_style' ) ) {
	/**
	 * Returns a merged declarations string for an element, walking every rule
	 * in source order. Later rules override earlier same-prop values.
	 *
	 * @param \DOMElement                                    $el       Element.
	 * @param array<int,array{selector:string,decls:string}> $rule_map Rule list.
	 * @return string Flattened declarations (no selector wrapping); empty if none.
	 */
	function emcp_css_resolve_element_style( \DOMElement $el, array $rule_map ): string {
		$accumulated = array();
		foreach ( $rule_map as $rule ) {
			if ( ! emcp_css_selector_matches( $rule['selector'], $el ) ) {
				continue;
			}
			$parts = preg_split( '/;(?![^(]*\))/', $rule['decls'] );
			if ( ! $parts ) {
				continue;
			}
			foreach ( $parts as $p ) {
				$p = trim( $p );
				if ( '' === $p ) {
					continue;
				}
				$colon = strpos( $p, ':' );
				if ( false === $colon ) {
					continue;
				}
				$k = strtolower( trim( substr( $p, 0, $colon ) ) );
				$v = trim( substr( $p, $colon + 1 ) );
				if ( '' !== $k ) {
					$accumulated[ $k ] = $v;
				}
			}
		}
		if ( empty( $accumulated ) ) {
			return '';
		}
		$out = array();
		foreach ( $accumulated as $k => $v ) {
			$out[] = $k . ':' . $v;
		}
		return implode( ';', $out );
	}
}
