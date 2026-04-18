<?php
/**
 * Typography token definitions.
 *
 * Semantic size scale (display / heading / body / caption) with family
 * pairings (headline vs body). Output is Elementor typography_* control
 * keys with responsive variants (_tablet, _mobile).
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_tokens_typography_scales' ) ) {
	/**
	 * Returns the catalog of available typography scales.
	 *
	 * @return array
	 */
	function emcp_tokens_typography_scales(): array {
		return array(
			'editorial-classic' => array(
				'families' => array(
					'headline' => 'Playfair Display',
					'body'     => 'Inter',
				),
				'sizes'    => emcp_tokens_typography_size_defaults(),
			),
			'modern-geometric'  => array(
				'families' => array(
					'headline' => 'Inter',
					'body'     => 'Inter',
				),
				'sizes'    => emcp_tokens_typography_size_defaults(),
			),
			'technical'         => array(
				'families' => array(
					'headline' => 'Space Grotesk',
					'body'     => 'Inter',
				),
				'sizes'    => emcp_tokens_typography_size_defaults(),
			),
		);
	}
}

if ( ! function_exists( 'emcp_tokens_typography_size_defaults' ) ) {
	/**
	 * Default semantic size scale shared by all typography presets.
	 *
	 * @return array
	 */
	function emcp_tokens_typography_size_defaults(): array {
		return array(
			'display-2xl' => array( 'size' => 96, 'weight' => 700, 'line_height' => 1.05, 'letter_spacing' => -0.02, 'family' => 'headline' ),
			'display-xl'  => array( 'size' => 72, 'weight' => 700, 'line_height' => 1.1,  'letter_spacing' => -0.02, 'family' => 'headline' ),
			'display-lg'  => array( 'size' => 56, 'weight' => 700, 'line_height' => 1.15, 'letter_spacing' => -0.01, 'family' => 'headline' ),
			'heading-xl'  => array( 'size' => 44, 'weight' => 700, 'line_height' => 1.2,  'letter_spacing' => -0.01, 'family' => 'headline' ),
			'heading-lg'  => array( 'size' => 36, 'weight' => 700, 'line_height' => 1.25, 'letter_spacing' => 0,     'family' => 'headline' ),
			'heading-md'  => array( 'size' => 28, 'weight' => 600, 'line_height' => 1.3,  'letter_spacing' => 0,     'family' => 'headline' ),
			'heading-sm'  => array( 'size' => 22, 'weight' => 600, 'line_height' => 1.35, 'letter_spacing' => 0,     'family' => 'headline' ),
			'body-xl'     => array( 'size' => 22, 'weight' => 400, 'line_height' => 1.55, 'letter_spacing' => 0,     'family' => 'body' ),
			'body-lg'     => array( 'size' => 18, 'weight' => 400, 'line_height' => 1.6,  'letter_spacing' => 0,     'family' => 'body' ),
			'body-md'     => array( 'size' => 16, 'weight' => 400, 'line_height' => 1.65, 'letter_spacing' => 0,     'family' => 'body' ),
			'body-sm'     => array( 'size' => 14, 'weight' => 400, 'line_height' => 1.6,  'letter_spacing' => 0,     'family' => 'body' ),
			'caption'     => array( 'size' => 12, 'weight' => 500, 'line_height' => 1.4,  'letter_spacing' => 0.02,  'family' => 'body' ),
		);
	}
}

if ( ! function_exists( 'emcp_tokens_typography_settings' ) ) {
	/**
	 * Resolves a typography token to Elementor control settings.
	 *
	 * @param string $scale Scale name (e.g. 'editorial-classic').
	 * @param string $size  Semantic size (e.g. 'display-xl').
	 * @return array Elementor settings fragment.
	 */
	function emcp_tokens_typography_settings( string $scale, string $size ): array {
		$scales = emcp_tokens_typography_scales();
		if ( ! isset( $scales[ $scale ]['sizes'][ $size ] ) ) {
			return array();
		}

		$spec   = $scales[ $scale ]['sizes'][ $size ];
		$family = $scales[ $scale ]['families'][ $spec['family'] ] ?? 'Inter';

		$desktop = (float) $spec['size'];
		$tablet  = round( $desktop * 0.78 );
		$mobile  = round( $desktop * 0.6 );

		return array(
			'typography_typography'       => 'custom',
			'typography_font_family'      => $family,
			'typography_font_weight'      => (string) $spec['weight'],
			'typography_font_size'        => array( 'unit' => 'px', 'size' => $desktop, 'sizes' => array() ),
			'typography_font_size_tablet' => array( 'unit' => 'px', 'size' => $tablet,  'sizes' => array() ),
			'typography_font_size_mobile' => array( 'unit' => 'px', 'size' => $mobile,  'sizes' => array() ),
			'typography_line_height'      => array( 'unit' => 'em', 'size' => (float) $spec['line_height'], 'sizes' => array() ),
			'typography_letter_spacing'   => array( 'unit' => 'em', 'size' => (float) $spec['letter_spacing'], 'sizes' => array() ),
		);
	}
}
