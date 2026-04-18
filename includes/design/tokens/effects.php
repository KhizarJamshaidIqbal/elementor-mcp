<?php
/**
 * Visual effect token definitions — shadows, border radii, overlays, dividers, backgrounds.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_tokens_shadow_settings' ) ) {
	/**
	 * Resolves a shadow token to Elementor box_shadow settings.
	 *
	 * @param string $token One of: 'none', 'subtle', 'soft', 'medium', 'large', 'hover'.
	 * @return array Elementor settings fragment.
	 */
	function emcp_tokens_shadow_settings( string $token ): array {
		$shadows = array(
			'subtle' => array( 'horizontal' => 0, 'vertical' => 2,  'blur' => 8,  'spread' => 0,  'color' => 'rgba(0,0,0,0.06)' ),
			'soft'   => array( 'horizontal' => 0, 'vertical' => 20, 'blur' => 40, 'spread' => 0,  'color' => 'rgba(0,0,0,0.08)' ),
			'medium' => array( 'horizontal' => 0, 'vertical' => 24, 'blur' => 48, 'spread' => -8, 'color' => 'rgba(0,0,0,0.12)' ),
			'large'  => array( 'horizontal' => 0, 'vertical' => 32, 'blur' => 64, 'spread' => -12, 'color' => 'rgba(0,0,0,0.18)' ),
			'hover'  => array( 'horizontal' => 0, 'vertical' => 28, 'blur' => 56, 'spread' => -4, 'color' => 'rgba(0,0,0,0.14)' ),
		);

		if ( 'none' === $token || ! isset( $shadows[ $token ] ) ) {
			return array();
		}

		return array(
			'box_shadow_box_shadow_type' => 'yes',
			'box_shadow_box_shadow'      => $shadows[ $token ],
		);
	}
}

if ( ! function_exists( 'emcp_tokens_radius_settings' ) ) {
	/**
	 * Resolves a border-radius token.
	 *
	 * @param string $token One of: 'none', 'sm', 'md', 'card', 'lg', 'pill', 'round'.
	 * @return array Elementor border_radius fragment.
	 */
	function emcp_tokens_radius_settings( string $token ): array {
		$radii = array(
			'none'  => 0,
			'sm'    => 4,
			'md'    => 8,
			'card'  => 16,
			'lg'    => 24,
			'pill'  => 999,
			'round' => 9999,
		);

		$value = $radii[ $token ] ?? 0;

		return array(
			'border_radius' => array(
				'unit'     => 'px',
				'top'      => (string) $value,
				'right'    => (string) $value,
				'bottom'   => (string) $value,
				'left'     => (string) $value,
				'isLinked' => true,
			),
		);
	}
}

if ( ! function_exists( 'emcp_tokens_overlay_settings' ) ) {
	/**
	 * Resolves a container background overlay token.
	 *
	 * @param string $token   One of: 'none', 'dark-flat', 'dark-gradient', 'brand-gradient', 'light-veil'.
	 * @param float  $opacity Opacity 0..1.
	 * @return array Elementor settings fragment.
	 */
	function emcp_tokens_overlay_settings( string $token, float $opacity = 0.55 ): array {
		switch ( $token ) {
			case 'dark-flat':
				return array(
					'background_overlay_background' => 'classic',
					'background_overlay_color'      => '#000000',
					'background_overlay_opacity'    => array( 'unit' => 'px', 'size' => $opacity, 'sizes' => array() ),
				);

			case 'dark-gradient':
				return array(
					'background_overlay_background'     => 'gradient',
					'background_overlay_color'          => 'rgba(0,0,0,0)',
					'background_overlay_color_b'        => '#000000',
					'background_overlay_color_b_stop'   => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
					'background_overlay_gradient_angle' => array( 'unit' => 'deg', 'size' => 180, 'sizes' => array() ),
					'background_overlay_opacity'        => array( 'unit' => 'px', 'size' => $opacity, 'sizes' => array() ),
				);

			case 'brand-gradient':
				return array(
					'background_overlay_background'     => 'gradient',
					'background_overlay_color'          => 'rgba(0,0,0,0)',
					'background_overlay_color_b'        => '#1F1A17',
					'background_overlay_gradient_angle' => array( 'unit' => 'deg', 'size' => 270, 'sizes' => array() ),
					'background_overlay_opacity'        => array( 'unit' => 'px', 'size' => $opacity, 'sizes' => array() ),
				);

			case 'light-veil':
				return array(
					'background_overlay_background' => 'classic',
					'background_overlay_color'      => '#FFFFFF',
					'background_overlay_opacity'    => array( 'unit' => 'px', 'size' => $opacity, 'sizes' => array() ),
				);

			case 'none':
			default:
				return array();
		}
	}
}

if ( ! function_exists( 'emcp_tokens_shape_divider' ) ) {
	/**
	 * Adds a shape divider (top or bottom) to a container.
	 *
	 * Divider color should match the ADJACENT section's background so
	 * the wave blends cleanly (no visible gap strip). Pass the
	 * concrete hex via $color — default '#FFFFFF' only fits against a
	 * white neighbor.
	 *
	 * @param string $shape    'waves', 'curve', 'triangle', 'tilt', 'arrow', 'split', 'book'.
	 * @param string $position 'top' or 'bottom'.
	 * @param bool   $negative Overlay above content (true) or below (false).
	 * @param string $color    Hex color of adjacent section. Default '#FFFFFF'.
	 * @return array Elementor settings fragment.
	 */
	function emcp_tokens_shape_divider( string $shape, string $position = 'bottom', bool $negative = true, string $color = '#FFFFFF' ): array {
		$prefix = 'shape_divider_' . $position;
		return array(
			$prefix                    => $shape,
			$prefix . '_color'         => $color,
			$prefix . '_width'         => array( 'unit' => '%', 'size' => 120, 'sizes' => array() ),
			$prefix . '_height'        => array( 'unit' => 'px', 'size' => 60, 'sizes' => array() ),
			$prefix . '_negative'      => $negative ? 'yes' : '',
			$prefix . '_above_content' => '',
		);
	}
}

if ( ! function_exists( 'emcp_tokens_background_image' ) ) {
	/**
	 * Builds a classic background image settings fragment.
	 *
	 * @param array|null $image ['url' => ..., 'id' => ..., 'alt' => ...] or null.
	 * @return array Elementor settings fragment.
	 */
	function emcp_tokens_background_image( ?array $image ): array {
		if ( empty( $image ) || empty( $image['url'] ) ) {
			return array();
		}
		return array(
			'background_background' => 'classic',
			'background_image'      => array(
				'url'    => esc_url_raw( $image['url'] ),
				'id'     => isset( $image['id'] ) ? (int) $image['id'] : '',
				'alt'    => sanitize_text_field( $image['alt'] ?? '' ),
				'source' => 'library',
				'size'   => '',
			),
			'background_position'   => 'center center',
			'background_repeat'     => 'no-repeat',
			'background_size'       => 'cover',
		);
	}
}
