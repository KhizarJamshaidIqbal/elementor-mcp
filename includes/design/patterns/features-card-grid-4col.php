<?php
/**
 * Pattern: features.card-grid-4col
 *
 * 4-column card grid — icon + title + body per card. More weight
 * than the 3-col icon grid (cards on surface with soft shadow).
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_features_card_grid_4col' ) ) {
	function emcp_pattern_features_card_grid_4col( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$heading  = (string) ( $slots['heading'] ?? '' );
		$subhead  = (string) ( $slots['subhead'] ?? '' );
		$features = is_array( $slots['features'] ?? null ) ? $slots['features'] : array();

		$cards = array();
		foreach ( $features as $feat ) {
			$icon    = (string) ( $feat['icon'] ?? 'fas fa-star' );
			$title   = (string) ( $feat['title'] ?? '' );
			$body    = (string) ( $feat['body'] ?? '' );
			$library = 'fa-solid';
			if ( strpos( $icon, 'far' ) === 0 )  $library = 'fa-regular';
			if ( strpos( $icon, 'fab' ) === 0 )  $library = 'fa-brands';

			$cards[] = array(
				'type'     => 'container',
				'settings' => emcp_array_deep_merge(
					array(
						'content_width'         => 'full',
						'flex_direction'        => 'column',
						'_title'                => 'Feature card: ' . $title,
						'padding'               => array( 'unit' => 'px', 'top' => '32', 'right' => '28', 'bottom' => '32', 'left' => '28', 'isLinked' => false ),
						'background_background' => 'classic',
					),
					$resolver->radius( 'card' ),
					$resolver->shadow( 'soft' ),
					$resolver->color( 'background_color', 'surface' ),
					$resolver->gap( 14 )
				),
				'children' => array(
					array(
						'type'        => 'widget',
						'widget_type' => 'icon',
						'settings'    => emcp_array_deep_merge(
							array(
								'selected_icon' => array( 'value' => $icon, 'library' => $library ),
								'view'          => 'default',
								'size'          => array( 'unit' => 'px', 'size' => 36, 'sizes' => array() ),
								'_title'        => 'Card icon',
							),
							$resolver->color( 'primary_color', 'accent' )
						),
					),
					array(
						'type'        => 'widget',
						'widget_type' => 'heading',
						'settings'    => emcp_array_deep_merge(
							array( 'title' => $title, 'header_size' => 'h3', 'align' => 'left', '_title' => 'Card title' ),
							$resolver->typography( 'heading-sm' ),
							$resolver->color( 'title_color', 'text' )
						),
					),
					array(
						'type'        => 'widget',
						'widget_type' => 'text-editor',
						'settings'    => emcp_array_deep_merge(
							array( 'editor' => '<p>' . esc_html( $body ) . '</p>', '_title' => 'Card body' ),
							$resolver->typography( 'body-md' ),
							$resolver->color( 'color', 'text-muted' )
						),
					),
				),
			);
		}

		$top = array();
		if ( $heading !== '' ) {
			$top[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $heading, 'align' => 'center', 'header_size' => 'h2', '_title' => 'Cards heading' ),
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
					array( 'editor' => '<p style="text-align:center">' . esc_html( $subhead ) . '</p>', '_title' => 'Cards subhead' ),
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
					'_title'                => 'Feature cards 4col',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface-alt' ),
				$resolver->gap( 40 )
			),
			'children' => array_merge(
				$top,
				array(
					array(
						'type'     => 'container',
						'settings' => emcp_array_deep_merge(
							array( 'content_width' => 'full', 'flex_direction' => 'row', '_title' => 'Cards row' ),
							$resolver->gap( 24 )
						),
						'children' => $cards,
					),
				)
			),
		);
	}
}

if ( ! function_exists( 'emcp_pattern_features_card_grid_4col_meta' ) ) {
	function emcp_pattern_features_card_grid_4col_meta(): array {
		return array(
			'category'    => 'features',
			'description' => '4-column card grid. Icon + title + body on surface with soft shadow.',
			'slots'       => array(
				'heading'  => 'string',
				'subhead'  => 'string',
				'features' => 'array<{icon,title,body}>',
			),
		);
	}
}
