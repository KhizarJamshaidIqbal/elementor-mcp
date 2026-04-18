<?php
/**
 * Responsive breakpoint projection helpers.
 *
 * Given a desktop value, auto-derive tablet and mobile variants following
 * conservative heuristics: typography scales down ~22%/40%, spacing shrinks
 * at mobile. Keeps pattern library concise by avoiding hand-written
 * responsive triples on every setting.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_responsive_size' ) ) {
	/**
	 * Builds a responsive size triplet (desktop/tablet/mobile) for settings
	 * keyed by $base_key.
	 *
	 * @param string $base_key     Setting key (e.g. 'padding', 'typography_font_size').
	 * @param float  $desktop      Desktop value.
	 * @param float  $tablet_ratio Multiplier for tablet. Default 0.75.
	 * @param float  $mobile_ratio Multiplier for mobile. Default 0.55.
	 * @param string $unit         Unit string. Default 'px'.
	 * @return array Three keyed entries ready to merge into widget settings.
	 */
	function emcp_responsive_size(
		string $base_key,
		float $desktop,
		float $tablet_ratio = 0.75,
		float $mobile_ratio = 0.55,
		string $unit = 'px'
	): array {
		return array(
			$base_key             => array( 'size' => $desktop, 'unit' => $unit ),
			$base_key . '_tablet' => array( 'size' => round( $desktop * $tablet_ratio ), 'unit' => $unit ),
			$base_key . '_mobile' => array( 'size' => round( $desktop * $mobile_ratio ), 'unit' => $unit ),
		);
	}
}

if ( ! function_exists( 'emcp_responsive_dimensions' ) ) {
	/**
	 * Builds a responsive dimensions box (top/right/bottom/left) for padding/margin.
	 *
	 * @param string $base_key     'padding' or 'margin'.
	 * @param array  $desktop      ['top'=>..,'right'=>..,'bottom'=>..,'left'=>..].
	 * @param float  $tablet_ratio Default 0.65.
	 * @param float  $mobile_ratio Default 0.45.
	 * @return array Three keyed entries.
	 */
	function emcp_responsive_dimensions(
		string $base_key,
		array $desktop,
		float $tablet_ratio = 0.65,
		float $mobile_ratio = 0.45
	): array {
		$build = function ( array $dim, float $ratio ): array {
			return array(
				'unit'     => 'px',
				'top'      => (string) (int) round( ( $dim['top'] ?? 0 ) * $ratio ),
				'right'    => (string) (int) round( ( $dim['right'] ?? 0 ) * $ratio ),
				'bottom'   => (string) (int) round( ( $dim['bottom'] ?? 0 ) * $ratio ),
				'left'     => (string) (int) round( ( $dim['left'] ?? 0 ) * $ratio ),
				'isLinked' => false,
			);
		};

		return array(
			$base_key             => $build( $desktop, 1.0 ),
			$base_key . '_tablet' => $build( $desktop, $tablet_ratio ),
			$base_key . '_mobile' => $build( $desktop, $mobile_ratio ),
		);
	}
}
