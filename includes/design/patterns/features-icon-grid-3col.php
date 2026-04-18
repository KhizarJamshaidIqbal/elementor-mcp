<?php
/**
 * Pattern: features.icon-grid-3col
 *
 * 3-column icon-box grid. Each feature = icon + title + body.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_features_icon_grid_3col' ) ) {
	function emcp_pattern_features_icon_grid_3col( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$heading  = (string) ( $slots['heading'] ?? '' );
		$subhead  = (string) ( $slots['subhead'] ?? '' );
		$features = is_array( $slots['features'] ?? null ) ? $slots['features'] : array();

		$cards = array();
		foreach ( $features as $feat ) {
			$icon  = (string) ( $feat['icon'] ?? 'fas fa-star' );
			$title = (string) ( $feat['title'] ?? '' );
			$body  = (string) ( $feat['body'] ?? '' );

			$library = 'fa-solid';
			if ( strpos( $icon, 'far' ) === 0 )  $library = 'fa-regular';
			if ( strpos( $icon, 'fab' ) === 0 )  $library = 'fa-brands';

			$cards[] = array(
				'type'        => 'widget',
				'widget_type' => 'icon-box',
				'settings'    => emcp_array_deep_merge(
					array(
						'_title'           => 'Feature: ' . $title,
						'selected_icon'    => array( 'value' => $icon, 'library' => $library ),
						'title_text'       => $title,
						'description_text' => $body,
						'view'             => 'default',
						'shape'            => 'square',
						'position'         => 'top',
						'title_size'       => 'h3',
						'size'             => array( 'unit' => 'px', 'size' => 28, 'sizes' => array() ),
					),
					$resolver->typography( 'heading-sm' ),
					$resolver->color( 'primary_color', 'accent' )
				),
			);
		}

		$top = array();
		if ( $heading !== '' ) {
			$top[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $heading, 'align' => 'center', 'header_size' => 'h2', '_title' => 'Features heading' ),
					$resolver->typography( 'heading-xl' ),
					$resolver->color( 'title_color', 'text' )
				),
			);
		}
		if ( $subhead !== '' ) {
			$top[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => emcp_array_deep_merge(
					array( 'editor' => '<p style="text-align:center">' . esc_html( $subhead ) . '</p>', '_title' => 'Features subhead' ),
					$resolver->typography( 'body-lg' ),
					$resolver->color( 'color', 'text-muted' )
				),
			);
		}

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.lg' ),
				array(
					'flex_direction'        => 'column',
					'align_items'           => 'center',
					'content_width'         => 'boxed',
					'_title'                => 'Feature grid 3col',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface' ),
				$resolver->gap( 40 )
			),
			'children' => array_merge(
				$top,
				array(
					array(
						'type'     => 'container',
						'settings' => emcp_array_deep_merge(
							array(
								'content_width'  => 'full',
								'flex_direction' => 'row',
								'_title'         => 'Features row',
							),
							$resolver->gap( 32 )
						),
						'children' => $cards,
					),
				)
			),
		);
	}
}

if ( ! function_exists( 'emcp_pattern_features_icon_grid_3col_meta' ) ) {
	function emcp_pattern_features_icon_grid_3col_meta(): array {
		return array(
			'category'    => 'features',
			'description' => '3-column icon-box grid. Icon + title + body per card.',
			'slots'       => array(
				'heading'  => 'string',
				'subhead'  => 'string',
				'features' => 'array<{icon,title,body}>',
			),
		);
	}
}
