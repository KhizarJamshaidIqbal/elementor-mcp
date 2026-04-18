<?php
/**
 * Inline Style Parser — converts HTML element `style` attributes into
 * Elementor widget/container settings arrays.
 *
 * This is the #1 fidelity gap fix for the Design Importer: previously every
 * `style="background:#1A1A2E; padding:80px; border-radius:16px;"` was silently
 * dropped, producing plain white containers. This parser maps common CSS
 * properties to the exact Elementor control keys they correspond to.
 *
 * Usage:
 *   $extra = emcp_parse_inline_styles( $el->getAttribute( 'style' ) );
 *   $settings = array_merge( $settings, $extra );
 *
 * Supported properties:
 *   background-color, background (solid color only)
 *   padding, padding-{top|right|bottom|left}
 *   margin, margin-{top|right|bottom|left}
 *   border-radius, border-{top|bottom}-{left|right}-radius
 *   min-height, height (→ min_height)
 *   max-width (→ custom_width + content_width)
 *   width (→ width control when unitized)
 *   color (→ color widget control for text)
 *   font-size, font-weight, line-height, letter-spacing, text-align, text-transform
 *   opacity
 *   border (shorthand) → border_width + border_color
 *   display:flex, flex-direction, align-items, justify-content, flex-wrap
 *   gap, column-gap, row-gap
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_parse_inline_styles' ) ) {
	/**
	 * Main entry point. Parses a `style` attribute string and returns an array
	 * of Elementor settings ready to merge into a widget/container settings array.
	 *
	 * @param string $style The raw `style` attribute value.
	 * @return array Elementor settings (may be empty if no parseable properties).
	 */
	function emcp_parse_inline_styles( string $style ): array {
		if ( '' === trim( $style ) ) {
			return array();
		}

		$props    = emcp_style_parse_props( $style );
		$settings = array();

		// Background color.
		$bg = $props['background-color'] ?? ( $props['background'] ?? null );
		if ( $bg && preg_match( '/^(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\)|hsla?\([^)]+\))$/', trim( $bg ) ) ) {
			$settings['background_background'] = 'classic';
			$settings['background_color']      = trim( $bg );
		}

		// Padding.
		if ( isset( $props['padding'] ) ) {
			$parsed = emcp_style_parse_box( $props, 'padding' );
			if ( $parsed ) {
				$settings['padding'] = $parsed;
			}
		}

		// Margin.
		if ( isset( $props['margin'] ) ) {
			$parsed = emcp_style_parse_box( $props, 'margin' );
			if ( $parsed ) {
				$settings['margin'] = $parsed;
			}
		}

		// Border radius (single uniform value → all corners).
		$br_keys = array(
			'border-radius',
			'border-top-left-radius',
			'border-top-right-radius',
			'border-bottom-right-radius',
			'border-bottom-left-radius',
		);
		$has_br  = false;
		foreach ( $br_keys as $k ) {
			if ( isset( $props[ $k ] ) ) {
				$has_br = true;
				break;
			}
		}
		if ( $has_br ) {
			// Map to Elementor border_radius (top = top-left, right = top-right, bottom = bottom-right, left = bottom-left).
			if ( isset( $props['border-radius'] ) ) {
				$parsed = emcp_style_parse_box( $props, 'border-radius' );
				if ( $parsed ) {
					$settings['border_radius'] = $parsed;
				}
			} else {
				$unit   = 'px';
				$top    = emcp_style_parse_size( $props['border-top-left-radius'] ?? '0' );
				$right  = emcp_style_parse_size( $props['border-top-right-radius'] ?? '0' );
				$bottom = emcp_style_parse_size( $props['border-bottom-right-radius'] ?? '0' );
				$left   = emcp_style_parse_size( $props['border-bottom-left-radius'] ?? '0' );
				if ( $top ) {
					$unit = $top['unit'];
				}
				$settings['border_radius'] = array(
					'top'      => $top ? (string) $top['size'] : '0',
					'right'    => $right ? (string) $right['size'] : '0',
					'bottom'   => $bottom ? (string) $bottom['size'] : '0',
					'left'     => $left ? (string) $left['size'] : '0',
					'unit'     => $unit,
					'isLinked' => false,
				);
			}
		}

		// Min-height / height → min_height.
		$height_val = $props['min-height'] ?? $props['height'] ?? null;
		if ( $height_val ) {
			$parsed = emcp_style_parse_size( $height_val );
			if ( $parsed ) {
				$settings['min_height'] = $parsed;
			}
		}

		// Max-width → content_width = 'custom' + custom_width.
		if ( isset( $props['max-width'] ) ) {
			$parsed = emcp_style_parse_size( $props['max-width'] );
			if ( $parsed ) {
				$settings['content_width']  = 'custom';
				$settings['custom_width']   = $parsed;
			}
		}

		// Width (for widgets, not containers).
		if ( isset( $props['width'] ) && ! isset( $props['max-width'] ) ) {
			$parsed = emcp_style_parse_size( $props['width'] );
			if ( $parsed && 'auto' !== $props['width'] && '%' === $parsed['unit'] ) {
				$settings['width'] = $parsed;
			}
		}

		// Text color.
		if ( isset( $props['color'] ) ) {
			$settings['color'] = trim( $props['color'] );
		}

		// Typography.
		if ( isset( $props['font-size'] ) ) {
			$parsed = emcp_style_parse_size( $props['font-size'] );
			if ( $parsed ) {
				$settings['typography_font_size']        = $parsed;
				$settings['typography_font_size_tablet'] = array( 'unit' => $parsed['unit'], 'size' => '' );
				$settings['typography_font_size_mobile'] = array( 'unit' => $parsed['unit'], 'size' => '' );
			}
		}
		if ( isset( $props['font-weight'] ) ) {
			$settings['typography_font_weight'] = trim( $props['font-weight'] );
		}
		if ( isset( $props['line-height'] ) ) {
			$parsed = emcp_style_parse_size( $props['line-height'] );
			if ( $parsed ) {
				$settings['typography_line_height'] = $parsed;
			}
		}
		if ( isset( $props['letter-spacing'] ) ) {
			$parsed = emcp_style_parse_size( $props['letter-spacing'] );
			if ( $parsed ) {
				$settings['typography_letter_spacing'] = $parsed;
			}
		}
		if ( isset( $props['text-align'] ) ) {
			$align_map = array(
				'left'    => 'left',
				'center'  => 'center',
				'right'   => 'right',
				'justify' => 'justify',
			);
			$ta = strtolower( trim( $props['text-align'] ) );
			if ( isset( $align_map[ $ta ] ) ) {
				$settings['text_align'] = $align_map[ $ta ];
			}
		}
		if ( isset( $props['text-transform'] ) ) {
			$tt_map = array(
				'uppercase'  => 'uppercase',
				'lowercase'  => 'lowercase',
				'capitalize' => 'capitalize',
				'none'       => 'none',
			);
			$tt = strtolower( trim( $props['text-transform'] ) );
			if ( isset( $tt_map[ $tt ] ) ) {
				$settings['typography_text_transform'] = $tt_map[ $tt ];
			}
		}

		// Opacity.
		if ( isset( $props['opacity'] ) ) {
			$op = (float) $props['opacity'];
			if ( $op >= 0.0 && $op <= 1.0 ) {
				$settings['opacity'] = array( 'unit' => 'px', 'size' => $op, 'sizes' => array() );
			}
		}

		// Border shorthand → border_width + border_color.
		if ( isset( $props['border'] ) ) {
			// e.g. "2px solid #333" or "1px solid rgba(255,255,255,0.2)"
			if ( preg_match( '/(\d+(?:\.\d+)?(?:px|em|rem))\s+\w+\s+(#[0-9a-fA-F]{3,8}|rgba?\([^)]+\))/', $props['border'], $bm ) ) {
				$bw = emcp_style_parse_size( $bm[1] );
				if ( $bw ) {
					$settings['border_width'] = array(
						'top'      => (string) $bw['size'],
						'right'    => (string) $bw['size'],
						'bottom'   => (string) $bw['size'],
						'left'     => (string) $bw['size'],
						'unit'     => $bw['unit'],
						'isLinked' => true,
					);
				}
				$settings['border_color'] = trim( $bm[2] );
			}
		}

		// Flexbox on containers.
		if ( isset( $props['display'] ) && 'flex' === strtolower( trim( $props['display'] ) ) ) {
			if ( isset( $props['flex-direction'] ) ) {
				$fd = strtolower( trim( $props['flex-direction'] ) );
				$settings['flex_direction'] = ( 'row' === $fd || 'row-reverse' === $fd ) ? 'row' : 'column';
			}
			if ( isset( $props['align-items'] ) ) {
				$settings['flex_align_items'] = emcp_style_map_flex_align( $props['align-items'] );
			}
			if ( isset( $props['justify-content'] ) ) {
				$settings['flex_justify_content'] = emcp_style_map_flex_justify( $props['justify-content'] );
			}
			if ( isset( $props['flex-wrap'] ) ) {
				$settings['flex_wrap'] = 'wrap' === strtolower( trim( $props['flex-wrap'] ) ) ? 'wrap' : 'nowrap';
			}
		}

		// Gap (flex/grid gap) → flex_gap.
		$gap_val = $props['gap'] ?? $props['column-gap'] ?? null;
		if ( $gap_val ) {
			$parsed = emcp_style_parse_size( $gap_val );
			if ( $parsed ) {
				$settings['flex_gap'] = array(
					'unit'     => $parsed['unit'],
					'size'     => $parsed['size'],
					'column'   => (string) $parsed['size'],
					'row'      => isset( $props['row-gap'] ) ? (string) ( emcp_style_parse_size( $props['row-gap'] )['size'] ?? $parsed['size'] ) : (string) $parsed['size'],
					'isLinked' => true,
				);
			}
		}

		return $settings;
	}
}

if ( ! function_exists( 'emcp_style_parse_props' ) ) {
	/**
	 * Parses a raw CSS `style` attribute string into a key→value map.
	 * Handles multi-value properties like `rgba(...)` and `linear-gradient(...)`.
	 *
	 * @param string $style Raw style attribute.
	 * @return array<string,string>
	 */
	function emcp_style_parse_props( string $style ): array {
		$result = array();
		// Split on semicolons not inside parentheses.
		$parts = preg_split( '/;(?![^(]*\))/', $style );
		if ( ! $parts ) {
			return $result;
		}
		foreach ( $parts as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			// Split on first colon only (values may contain colons in e.g. URLs).
			$colon = strpos( $part, ':' );
			if ( false === $colon ) {
				continue;
			}
			$key   = strtolower( trim( substr( $part, 0, $colon ) ) );
			$value = trim( substr( $part, $colon + 1 ) );
			// Strip trailing `!important` — Elementor controls apply their own priority.
			$value = preg_replace( '/\s*!\s*important\s*$/i', '', $value );
			if ( '' !== $key ) {
				$result[ $key ] = $value;
			}
		}
		return $result;
	}
}

if ( ! function_exists( 'emcp_style_parse_size' ) ) {
	/**
	 * Converts a CSS size string to an Elementor size array.
	 *
	 * Supported units: px, em, rem, %, vw, vh, vmin, vmax, svh, svw.
	 * Returns null for `auto`, `inherit`, `unset`, `none`, or unrecognised values.
	 *
	 * @param string $val CSS value e.g. "24px", "70vh", "2.5rem".
	 * @return array{size:float,unit:string,sizes:array}|null
	 */
	function emcp_style_parse_size( string $val ): ?array {
		$val = trim( $val );
		if ( in_array( $val, array( 'auto', 'inherit', 'unset', 'none', 'initial', '' ), true ) ) {
			return null;
		}
		// Match numeric value + unit (unit optional → px assumed).
		if ( preg_match( '/^(-?[\d.]+)\s*(px|em|rem|%|vw|vh|vmin|vmax|svh|svw)?$/i', $val, $m ) ) {
			$size = (float) $m[1];
			$unit = '' !== ( $m[2] ?? '' ) ? strtolower( $m[2] ) : 'px';
			return array( 'unit' => $unit, 'size' => $size, 'sizes' => array() );
		}
		return null;
	}
}

if ( ! function_exists( 'emcp_style_parse_box' ) ) {
	/**
	 * Parses a CSS box shorthand (padding / margin / border-radius) into an
	 * Elementor dimension array: {top, right, bottom, left, unit, isLinked}.
	 *
	 * Handles:
	 *   - 1-value shorthand: "20px"
	 *   - 2-value shorthand: "20px 40px"
	 *   - 3-value shorthand: "10px 20px 30px"
	 *   - 4-value shorthand: "10px 20px 30px 40px"
	 *   - Individual longhand override: padding-top, padding-right, etc.
	 *
	 * @param array<string,string> $props Full props map (for longhands).
	 * @param string               $prop  Base property name ('padding', 'margin', 'border-radius').
	 * @return array{top:string,right:string,bottom:string,left:string,unit:string,isLinked:bool}|null
	 */
	function emcp_style_parse_box( array $props, string $prop ): ?array {
		$unit = 'px';
		$t    = '0';
		$r    = '0';
		$b    = '0';
		$l    = '0';

		if ( isset( $props[ $prop ] ) ) {
			// Split shorthand by whitespace (not inside parens).
			$raw    = preg_replace( '/\s+/', ' ', trim( $props[ $prop ] ) );
			$tokens = preg_split( '/\s+/', $raw );
			if ( ! $tokens ) {
				return null;
			}
			// Parse first value to get unit.
			$first = emcp_style_parse_size( $tokens[0] );
			if ( ! $first ) {
				return null;
			}
			$unit = $first['unit'];
			$v    = array_map(
				function ( $tok ) use ( $unit ) {
					$s = emcp_style_parse_size( $tok );
					return $s ? (string) $s['size'] : '0';
				},
				$tokens
			);

			switch ( count( $v ) ) {
				case 1:
					$t = $r = $b = $l = $v[0];
					break;
				case 2:
					$t = $b = $v[0];
					$r = $l = $v[1];
					break;
				case 3:
					$t = $v[0];
					$r = $l = $v[1];
					$b = $v[2];
					break;
				default: // 4+.
					$t = $v[0];
					$r = $v[1];
					$b = $v[2];
					$l = $v[3];
					break;
			}
		}

		// Longhand overrides (e.g. padding-top overrides shorthand top).
		$sides = array(
			'border-radius' => array( 'top-left', 'top-right', 'bottom-right', 'bottom-left' ),
			'default'       => array( 'top', 'right', 'bottom', 'left' ),
		);
		$side_keys = isset( $sides[ $prop ] ) ? $sides[ $prop ] : $sides['default'];

		foreach ( array( 'top', 'right', 'bottom', 'left' ) as $idx => $side ) {
			$lh = $prop . '-' . $side_keys[ $idx ];
			if ( isset( $props[ $lh ] ) ) {
				$parsed = emcp_style_parse_size( $props[ $lh ] );
				if ( $parsed ) {
					$unit = $parsed['unit'];
					$$side = (string) $parsed['size'];
				}
			}
		}

		$is_linked = ( $t === $r && $r === $b && $b === $l );

		return array(
			'top'      => $t,
			'right'    => $r,
			'bottom'   => $b,
			'left'     => $l,
			'unit'     => $unit,
			'isLinked' => $is_linked,
		);
	}
}

if ( ! function_exists( 'emcp_style_map_flex_align' ) ) {
	/**
	 * Maps CSS `align-items` values to Elementor `flex_align_items` values.
	 *
	 * @param string $val CSS value.
	 * @return string Elementor value.
	 */
	function emcp_style_map_flex_align( string $val ): string {
		$map = array(
			'flex-start' => 'flex-start',
			'start'      => 'flex-start',
			'flex-end'   => 'flex-end',
			'end'        => 'flex-end',
			'center'     => 'center',
			'stretch'    => 'stretch',
			'baseline'   => 'baseline',
		);
		$key = strtolower( trim( $val ) );
		return $map[ $key ] ?? 'flex-start';
	}
}

if ( ! function_exists( 'emcp_style_map_flex_justify' ) ) {
	/**
	 * Maps CSS `justify-content` values to Elementor `flex_justify_content` values.
	 *
	 * @param string $val CSS value.
	 * @return string Elementor value.
	 */
	function emcp_style_map_flex_justify( string $val ): string {
		$map = array(
			'flex-start'    => 'flex-start',
			'start'         => 'flex-start',
			'flex-end'      => 'flex-end',
			'end'           => 'flex-end',
			'center'        => 'center',
			'space-between' => 'space-between',
			'space-around'  => 'space-around',
			'space-evenly'  => 'space-evenly',
		);
		$key = strtolower( trim( $val ) );
		return $map[ $key ] ?? 'flex-start';
	}
}
