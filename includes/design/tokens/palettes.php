<?php
/**
 * Brand palette token definitions.
 *
 * Each palette ships with named semantic color slots. Token resolution
 * emits __globals__ references to Elementor kit color IDs when the
 * palette has been bound via Kit_Binder; otherwise hex literals.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_tokens_palettes' ) ) {
	/**
	 * Returns the catalog of available brand palettes.
	 *
	 * @return array<string, array<string, string>> Palette name => slot => hex.
	 */
	function emcp_tokens_palettes(): array {
		return array(
			'desert-warm' => array(
				'primary'            => '#D2691E',
				'secondary'          => '#8B4513',
				'accent'             => '#F4A460',
				'text'               => '#1F1A17',
				'text-muted'         => '#5C4B40',
				'surface'            => '#FFF8F1',
				'surface-alt'        => '#F5E6D3',
				'border'             => '#E8D5C0',
				'text-inverse'       => '#FFF8F1',
				'text-inverse-muted' => '#E8D5C0',
			),
			'luxury-dark' => array(
				'primary'            => '#C9A961',
				'secondary'          => '#8B7355',
				'accent'             => '#E6C97A',
				'text'               => '#F5F2EB',
				'text-muted'         => '#B8B0A3',
				'surface'            => '#0F0D0A',
				'surface-alt'        => '#1A1612',
				'border'             => '#2B2620',
				'text-inverse'       => '#0F0D0A',
				'text-inverse-muted' => '#3D3628',
			),
			'modern-clean' => array(
				'primary'            => '#2563EB',
				'secondary'          => '#1E293B',
				'accent'             => '#06B6D4',
				'text'               => '#0F172A',
				'text-muted'         => '#475569',
				'surface'            => '#FFFFFF',
				'surface-alt'        => '#F8FAFC',
				'border'             => '#E2E8F0',
				'text-inverse'       => '#FFFFFF',
				'text-inverse-muted' => '#CBD5E1',
			),

			/**
			 * Classic Desert Safari design-system palette
			 * (extracted from Claude FAQ.html :root vars).
			 *
			 * Design sources:
			 *   --sunset   #E9680C → primary
			 *   --midnight #1A1A2E → secondary (dark sections + body text)
			 *   --gold     #D4A853 → accent
			 *   --ink-500  #555555 → text-muted
			 *   --surface  #FFFFFF → surface
			 *   --sand-50  #FBF7F1 → surface-alt
			 *   --line rgba(26,26,46,.08) → border (≈ #EBEBEE on white)
			 */
			'emcp-classic-desert' => array(
				'primary'            => '#E9680C',
				'secondary'          => '#1A1A2E',
				'accent'             => '#D4A853',
				'text'               => '#1A1A2E',
				'text-muted'         => '#555555',
				'surface'            => '#FFFFFF',
				'surface-alt'        => '#FBF7F1',
				'border'             => '#EBEBEE',
				'text-inverse'       => '#FFFFFF',
				'text-inverse-muted' => '#9E9EB0',
			),
		);
	}
}

if ( ! function_exists( 'emcp_tokens_palette_get' ) ) {
	/**
	 * Looks up a palette slot value.
	 *
	 * @param string $palette_name Palette key (e.g. 'desert-warm').
	 * @param string $slot         Slot name (e.g. 'primary').
	 * @return string|null Hex color, or null if missing.
	 */
	function emcp_tokens_palette_get( string $palette_name, string $slot ): ?string {
		$palettes = emcp_tokens_palettes();
		return $palettes[ $palette_name ][ $slot ] ?? null;
	}
}
