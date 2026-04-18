<?php
/**
 * Button variant token definitions.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_tokens_button_settings' ) ) {
	/**
	 * Resolves a button variant token to Elementor button widget settings.
	 *
	 * @param string $variant 'primary-large', 'primary-md', 'secondary-md', 'outline-md', 'ghost-md'.
	 * @param string $palette Palette name for color resolution. Default empty (uses hex fallbacks).
	 * @return array Elementor button settings fragment.
	 */
	function emcp_tokens_button_settings( string $variant = 'primary-md', string $palette = '' ): array {
		$size_tables = array(
			'primary-large' => array( 'size' => 'xl', 'font_size' => 18, 'pad_y' => 20, 'pad_x' => 40 ),
			'primary-md'    => array( 'size' => 'md', 'font_size' => 16, 'pad_y' => 14, 'pad_x' => 28 ),
			'secondary-md'  => array( 'size' => 'md', 'font_size' => 16, 'pad_y' => 14, 'pad_x' => 28 ),
			'outline-md'    => array( 'size' => 'md', 'font_size' => 16, 'pad_y' => 14, 'pad_x' => 28 ),
			'ghost-md'      => array( 'size' => 'md', 'font_size' => 16, 'pad_y' => 14, 'pad_x' => 28 ),
		);

		$size = $size_tables[ $variant ] ?? $size_tables['primary-md'];

		$primary  = $palette ? ( emcp_tokens_palette_get( $palette, 'primary' ) ?? '#2563EB' ) : '#2563EB';
		$text     = $palette ? ( emcp_tokens_palette_get( $palette, 'text-inverse' ) ?? '#FFFFFF' ) : '#FFFFFF';
		$contrast = $palette ? ( emcp_tokens_palette_get( $palette, 'text' ) ?? '#0F172A' ) : '#0F172A';
		$accent   = $palette ? ( emcp_tokens_palette_get( $palette, 'accent' ) ?? '#06B6D4' ) : '#06B6D4';

		$common = array(
			'size'                      => $size['size'],
			'button_text_color'         => $text,
			'typography_typography'     => 'custom',
			'typography_font_weight'    => '600',
			'typography_font_size'      => array( 'unit' => 'px', 'size' => $size['font_size'], 'sizes' => array() ),
			'typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0, 'sizes' => array() ),
			'border_radius'             => array( 'unit' => 'px', 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'isLinked' => true ),
			'text_padding'              => array(
				'unit'     => 'px',
				'top'      => (string) $size['pad_y'],
				'right'    => (string) $size['pad_x'],
				'bottom'   => (string) $size['pad_y'],
				'left'     => (string) $size['pad_x'],
				'isLinked' => false,
			),
			'hover_animation'           => 'grow',
		);

		switch ( $variant ) {
			case 'primary-large':
			case 'primary-md':
				return array_merge( $common, array(
					'background_color'              => $primary,
					'button_hover_background_color' => $accent,
					'button_hover_text_color'       => $text,
				) );

			case 'secondary-md':
				return array_merge( $common, array(
					'background_color'              => $contrast,
					'button_text_color'             => $text,
					'button_hover_background_color' => $primary,
					'button_hover_text_color'       => $text,
				) );

			case 'outline-md':
				return array_merge( $common, array(
					'background_color'              => 'rgba(0,0,0,0)',
					'button_text_color'             => $primary,
					'border_border'                 => 'solid',
					'border_width'                  => array( 'unit' => 'px', 'top' => '2', 'right' => '2', 'bottom' => '2', 'left' => '2', 'isLinked' => true ),
					'border_color'                  => $primary,
					'button_hover_background_color' => $primary,
					'button_hover_text_color'       => $text,
				) );

			case 'ghost-md':
				return array_merge( $common, array(
					'background_color'              => 'rgba(0,0,0,0)',
					'button_text_color'             => $primary,
					'button_hover_background_color' => 'rgba(0,0,0,0.08)',
					'button_hover_text_color'       => $primary,
				) );

			default:
				return $common;
		}
	}
}
