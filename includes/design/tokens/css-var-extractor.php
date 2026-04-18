<?php
/**
 * CSS Variable Extractor — parses a design's `<style>` block and
 * extracts `:root { --name: value; }` declarations into a structured
 * token map compatible with the plugin's existing palette/typography
 * schemas.
 *
 * Conventions recognized (classified by var-name prefix):
 *   --*color*|--*primary*|--*sunset*|--*gold* → palette slot
 *   --f-*  → typography family
 *   --fs-* → typography size
 *   --sp-* → spacing scale
 *   --r-*  → radius
 *   --sh-* → shadow
 *   --g-*  → gradient (stored raw)
 *   everything else → raw map for resolve_css_var() lookup
 *
 * Regex-based (no CSS parser dep). Handles whitespace + comments +
 * multi-declaration-per-line + clamp() values.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_tokens_css_var_extract' ) ) {
	/**
	 * Parses a CSS block and returns structured design tokens.
	 *
	 * @param string $css Full `<style>` block contents or raw CSS.
	 * @return array {
	 *     @type array  $palette    Slot name => hex color.
	 *     @type array  $typography {families:[headline,body], sizes:{size_name:css_value}}
	 *     @type array  $spacing    Name => css value.
	 *     @type array  $radii      Name => css value.
	 *     @type array  $shadows    Name => css value.
	 *     @type array  $gradients  Name => raw linear-gradient(...) value.
	 *     @type array  $raw        var_name (without --) => value. For resolve_css_var().
	 * }
	 */
	function emcp_tokens_css_var_extract( string $css ): array {
		$tokens = array(
			'palette'    => array(),
			'typography' => array(
				'families' => array(),
				'sizes'    => array(),
			),
			'spacing'    => array(),
			'radii'      => array(),
			'shadows'    => array(),
			'gradients'  => array(),
			'raw'        => array(),
		);

		// 1. Strip CSS comments to simplify matching.
		$css = preg_replace( '#/\*.*?\*/#s', '', $css );

		// 2. Find :root blocks (supports multiple :root rules).
		if ( ! preg_match_all( '/:root\s*\{([^}]*)\}/s', (string) $css, $root_blocks ) ) {
			return $tokens;
		}

		// 3. Extract every `--name: value;` declaration.
		foreach ( $root_blocks[1] as $block ) {
			if ( ! preg_match_all( '/--([a-zA-Z0-9_-]+)\s*:\s*([^;]+);/', $block, $matches, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $matches as $m ) {
				$name  = strtolower( trim( $m[1] ) );
				$value = trim( $m[2] );
				$tokens['raw'][ $name ] = $value;
				emcp_tokens_classify_var( $name, $value, $tokens );
			}
		}

		// 4. Post-process palette → ensure standard slot names when we can map from raw keys.
		$tokens['palette'] = emcp_tokens_normalize_palette( $tokens['raw'] );

		return $tokens;
	}
}

if ( ! function_exists( 'emcp_tokens_classify_var' ) ) {
	/**
	 * Routes a CSS var to the appropriate token bucket based on its
	 * name prefix / value shape. Mutates $tokens in place.
	 */
	function emcp_tokens_classify_var( string $name, string $value, array &$tokens ): void {
		// Gradients first — value-based check beats name-based because
		// var names vary widely ("--g-sunset", "--grad-primary").
		if ( preg_match( '/^(linear|radial|conic)-gradient\s*\(/i', $value ) ) {
			$tokens['gradients'][ $name ] = $value;
			return;
		}

		// Typography family: --f-display, --f-body.
		if ( preg_match( '/^f-(.+)/', $name, $m ) ) {
			$tokens['typography']['families'][ $m[1] ] = $value;
			return;
		}

		// Typography size: --fs-hero, --fs-h1.
		if ( preg_match( '/^fs-(.+)/', $name, $m ) ) {
			$tokens['typography']['sizes'][ $m[1] ] = $value;
			return;
		}

		// Spacing: --sp-1 .. --sp-10.
		if ( preg_match( '/^sp-(.+)/', $name, $m ) ) {
			$tokens['spacing'][ $m[1] ] = $value;
			return;
		}

		// Radius: --r-sm, --r-md, --r-pill.
		if ( preg_match( '/^r-(.+)/', $name, $m ) ) {
			$tokens['radii'][ $m[1] ] = $value;
			return;
		}

		// Shadow: --sh-sm, --sh-md, --sh-sunset.
		if ( preg_match( '/^sh-(.+)/', $name, $m ) ) {
			$tokens['shadows'][ $m[1] ] = $value;
			return;
		}

		// Hex color (palette candidate) — actual slot assignment happens
		// in emcp_tokens_normalize_palette() based on known name aliases.
		// (We still track it here for observability during tests.)
	}
}

if ( ! function_exists( 'emcp_tokens_normalize_palette' ) ) {
	/**
	 * Maps raw CSS var names to standard palette slot names so the
	 * Kit_Binder can bind them without renaming.
	 *
	 * Slot name precedence (first match wins):
	 *   primary   ← sunset, primary, brand, accent-primary
	 *   secondary ← midnight, secondary, navy, dark
	 *   accent    ← gold, accent, highlight
	 *   text      ← ink-900, text, text-primary, body-color
	 *   text-muted← ink-500, text-muted, text-secondary, muted
	 *   surface   ← surface, bg, background
	 *   surface-alt ← sand-50, sand, surface-alt, surface-muted
	 *   border    ← line, border
	 *
	 * Only returns slots that have a matching raw var AND a hex value.
	 */
	function emcp_tokens_normalize_palette( array $raw ): array {
		$rules = array(
			'primary'     => array( 'sunset', 'primary', 'brand', 'brand-primary' ),
			'secondary'   => array( 'midnight', 'secondary', 'navy', 'dark', 'brand-dark' ),
			'accent'      => array( 'gold', 'accent', 'highlight', 'brand-accent' ),
			'text'        => array( 'ink-900', 'ink-800', 'text', 'text-primary', 'body-color' ),
			'text-muted'  => array( 'ink-500', 'ink-600', 'text-muted', 'text-secondary', 'muted' ),
			'surface'     => array( 'surface', 'bg', 'background', 'white' ),
			'surface-alt' => array( 'sand-50', 'sand', 'surface-alt', 'surface-muted', 'bg-alt' ),
			'border'      => array( 'line', 'border', 'divider' ),
		);

		$palette = array();
		foreach ( $rules as $slot => $candidates ) {
			foreach ( $candidates as $name ) {
				if ( isset( $raw[ $name ] ) && preg_match( '/^#[0-9a-fA-F]{3,8}$/', $raw[ $name ] ) ) {
					$palette[ $slot ] = $raw[ $name ];
					break;
				}
			}
		}

		// Defaults for slots the design didn't explicitly declare — keep
		// Kit_Binder's downstream behavior consistent.
		if ( empty( $palette['text-inverse'] ) ) {
			$palette['text-inverse'] = '#FFFFFF';
		}
		if ( empty( $palette['text-inverse-muted'] ) ) {
			$palette['text-inverse-muted'] = '#CBD5E1';
		}

		return $palette;
	}
}

if ( ! function_exists( 'emcp_tokens_css_var_value' ) ) {
	/**
	 * Looks up a raw CSS var value from the extracted tokens array.
	 * Used by Token_Resolver::resolve_css_var().
	 */
	function emcp_tokens_css_var_value( array $tokens, string $var_name ): ?string {
		$name = ltrim( strtolower( $var_name ), '-' );
		return $tokens['raw'][ $name ] ?? null;
	}
}
