<?php
/**
 * Spacing token definitions.
 *
 * Semantic section/block/inline spacing with responsive variants.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_tokens_spacing_scale' ) ) {
	/**
	 * Returns the semantic spacing scale (desktop px, top+bottom+h padding).
	 *
	 * @return array
	 */
	function emcp_tokens_spacing_scale(): array {
		return array(
			'section.hero' => array( 'top' => 160, 'right' => 48, 'bottom' => 96,  'left' => 48 ),
			'section.xl'   => array( 'top' => 140, 'right' => 48, 'bottom' => 140, 'left' => 48 ),
			'section.lg'   => array( 'top' => 120, 'right' => 48, 'bottom' => 120, 'left' => 48 ),
			'section.md'   => array( 'top' => 80,  'right' => 48, 'bottom' => 80,  'left' => 48 ),
			'section.sm'   => array( 'top' => 56,  'right' => 48, 'bottom' => 56,  'left' => 48 ),
			'block.lg'     => array( 'top' => 48,  'right' => 0,  'bottom' => 48,  'left' => 0 ),
			'block.md'     => array( 'top' => 32,  'right' => 0,  'bottom' => 32,  'left' => 0 ),
			'block.sm'     => array( 'top' => 20,  'right' => 0,  'bottom' => 20,  'left' => 0 ),
			'inline.md'    => array( 'top' => 0,   'right' => 24, 'bottom' => 0,   'left' => 24 ),
			'cta.card'     => array( 'top' => 40,  'right' => 40, 'bottom' => 40,  'left' => 40 ),
		);
	}
}

if ( ! function_exists( 'emcp_tokens_spacing_settings' ) ) {
	/**
	 * Resolves a spacing token to Elementor padding/margin settings with responsive variants.
	 *
	 * @param string $token Token name (e.g. 'section.lg').
	 * @param string $key   Setting key — 'padding' or 'margin'. Default 'padding'.
	 * @return array Elementor settings fragment.
	 */
	function emcp_tokens_spacing_settings( string $token, string $key = 'padding' ): array {
		$scale = emcp_tokens_spacing_scale();
		if ( ! isset( $scale[ $token ] ) ) {
			return array();
		}
		return emcp_responsive_dimensions( $key, $scale[ $token ], 0.65, 0.45 );
	}
}

if ( ! function_exists( 'emcp_tokens_gap_settings' ) ) {
	/**
	 * Builds flex gap settings with responsive variants.
	 *
	 * @param int $desktop Desktop gap in px.
	 * @return array Elementor flex_gap fragment.
	 */
	function emcp_tokens_gap_settings( int $desktop ): array {
		$tablet = (int) round( $desktop * 0.7 );
		$mobile = (int) round( $desktop * 0.5 );

		$build = function ( int $v ): array {
			return array( 'column' => (string) $v, 'row' => (string) $v, 'isLinked' => true, 'unit' => 'px', 'size' => $v );
		};

		return array(
			'flex_gap'        => $build( $desktop ),
			'flex_gap_tablet' => $build( $tablet ),
			'flex_gap_mobile' => $build( $mobile ),
		);
	}
}
