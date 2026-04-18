<?php
/**
 * Pattern: hero.gradient-mesh
 *
 * Modern SaaS-style hero — primary→accent gradient background,
 * large centered headline. Feels technical and clean.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_hero_gradient_mesh' ) ) {
	function emcp_pattern_hero_gradient_mesh( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$eyebrow   = (string) ( $slots['eyebrow'] ?? '' );
		$headline  = (string) ( $slots['headline'] ?? '' );
		$subhead   = (string) ( $slots['subhead'] ?? '' );
		$cta_label = (string) ( $slots['cta_label'] ?? '' );
		$cta_url   = (string) ( $slots['cta_url'] ?? '' );

		$primary = emcp_tokens_palette_get( $resolver->palette(), 'primary' ) ?? '#2563EB';
		$accent  = emcp_tokens_palette_get( $resolver->palette(), 'accent' )  ?? '#06B6D4';

		$children = array();
		if ( $eyebrow !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $eyebrow, 'header_size' => 'div', 'align' => 'center', '_title' => 'Eyebrow' ),
					$resolver->typography( 'caption' ),
					$resolver->color( 'title_color', 'text-inverse-muted' )
				),
			);
		}
		$children[] = array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => emcp_array_deep_merge(
				array( 'title' => $headline, 'header_size' => 'h1', 'align' => 'center', '_title' => 'Headline' ),
				$resolver->typography( 'display-2xl' ),
				$resolver->color( 'title_color', 'text-inverse' )
			),
		);
		if ( $subhead !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => emcp_array_deep_merge(
					array( 'editor' => '<p style="text-align:center">' . esc_html( $subhead ) . '</p>', '_title' => 'Subhead' ),
					$resolver->typography( 'body-xl' ),
					$resolver->color( 'color', 'text-inverse-muted' )
				),
			);
		}
		if ( $cta_label !== '' && $cta_url !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'button',
				'settings'    => emcp_array_deep_merge(
					array( 'text' => $cta_label, 'link' => array( 'url' => esc_url_raw( $cta_url ) ), 'align' => 'center', '_title' => 'Primary CTA' ),
					$resolver->button( 'primary-large' )
				),
			);
		}

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.hero' ),
				array(
					'flex_direction'            => 'column',
					'align_items'               => 'center',
					'content_width'             => 'boxed',
					'_title'                    => 'Hero Gradient Mesh',
					'background_background'     => 'gradient',
					'background_color'          => $primary,
					'background_color_b'        => $accent,
					'background_gradient_angle' => array( 'unit' => 'deg', 'size' => 135, 'sizes' => array() ),
					'background_color_b_stop'   => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
					'min_height'                => array( 'size' => 80, 'unit' => 'vh' ),
				),
				$resolver->gap( 24 )
			),
			'children' => $children,
		);
	}
}

if ( ! function_exists( 'emcp_pattern_hero_gradient_mesh_meta' ) ) {
	function emcp_pattern_hero_gradient_mesh_meta(): array {
		return array(
			'category'    => 'hero',
			'description' => 'Modern gradient hero (primary→accent). Big centered headline + subhead + CTA.',
			'slots'       => array(
				'eyebrow'   => 'string',
				'headline'  => 'string (required)',
				'subhead'   => 'string',
				'cta_label' => 'string',
				'cta_url'   => 'string URL',
			),
		);
	}
}
