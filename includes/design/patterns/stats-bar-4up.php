<?php
/**
 * Pattern: stats-bar.4-up
 *
 * 4 big-number stat chips in a row. E.g. "2 hrs | 4.9★ | 16+ | AED 180".
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_stats_bar_4up' ) ) {
	function emcp_pattern_stats_bar_4up( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$stats = is_array( $slots['stats'] ?? null ) ? $slots['stats'] : array();

		$cells = array();
		foreach ( $stats as $s ) {
			$value = (string) ( $s['value'] ?? '' );
			$label = (string) ( $s['label'] ?? '' );
			$cells[] = array(
				'type'     => 'container',
				'settings' => emcp_array_deep_merge(
					array( 'content_width' => 'full', 'flex_direction' => 'column', '_title' => 'Stat: ' . $value ),
					$resolver->gap( 6 )
				),
				'children' => array(
					array(
						'type'        => 'widget',
						'widget_type' => 'heading',
						'settings'    => emcp_array_deep_merge(
							array( 'title' => $value, 'align' => 'center', 'header_size' => 'h3', '_title' => 'Stat value' ),
							$resolver->typography( 'display-lg' ),
							$resolver->color( 'title_color', 'accent' )
						),
					),
					array(
						'type'        => 'widget',
						'widget_type' => 'heading',
						'settings'    => emcp_array_deep_merge(
							array( 'title' => $label, 'align' => 'center', 'header_size' => 'div', '_title' => 'Stat label' ),
							$resolver->typography( 'caption' ),
							$resolver->color( 'title_color', 'text-muted' )
						),
					),
				),
			);
		}

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.sm' ),
				array(
					'flex_direction'        => 'row',
					'content_width'         => 'boxed',
					'_title'                => 'Stats bar 4up',
					'background_background' => 'classic',
					'flex_justify_content'  => 'space-between',
					'flex_align_items'      => 'center',
				),
				$resolver->color( 'background_color', 'surface-alt' ),
				$resolver->gap( 24 )
			),
			'children' => $cells,
		);
	}
}

if ( ! function_exists( 'emcp_pattern_stats_bar_4up_meta' ) ) {
	function emcp_pattern_stats_bar_4up_meta(): array {
		return array(
			'name'        => 'stats-bar.4-up',
			'category'    => 'stats-bar',
			'description' => '4 big-number stat chips in a row.',
			'slots'       => array( 'stats' => 'array<{value,label}>' ),
		);
	}
}
