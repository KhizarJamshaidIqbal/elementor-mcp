<?php
/**
 * Pattern: hero.minimal-center
 *
 * Clean centered hero on palette surface. Eyebrow + headline +
 * subhead + dual CTA row. Conversion-focused.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_hero_minimal_center' ) ) {
	function emcp_pattern_hero_minimal_center( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$eyebrow         = (string) ( $slots['eyebrow'] ?? '' );
		$headline        = (string) ( $slots['headline'] ?? '' );
		$subhead         = (string) ( $slots['subhead'] ?? '' );
		$cta_label       = (string) ( $slots['cta_label'] ?? '' );
		$cta_url         = (string) ( $slots['cta_url'] ?? '' );
		$secondary_label = (string) ( $slots['secondary_label'] ?? '' );
		$secondary_url   = (string) ( $slots['secondary_url'] ?? '' );

		$children = array();
		if ( $eyebrow !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $eyebrow, 'header_size' => 'div', 'align' => 'center', '_title' => 'Eyebrow' ),
					$resolver->typography( 'caption' ),
					$resolver->color( 'title_color', 'accent' )
				),
			);
		}
		$children[] = array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => emcp_array_deep_merge(
				array( 'title' => $headline, 'header_size' => 'h1', 'align' => 'center', '_title' => 'Headline' ),
				$resolver->typography( 'display-xl' ),
				$resolver->color( 'title_color', 'text' )
			),
		);
		if ( $subhead !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => emcp_array_deep_merge(
					array( 'editor' => '<p style="text-align:center">' . esc_html( $subhead ) . '</p>', '_title' => 'Subhead' ),
					$resolver->typography( 'body-lg' ),
					$resolver->color( 'color', 'text-muted' )
				),
			);
		}
		$cta_row = array();
		if ( $cta_label !== '' && $cta_url !== '' ) {
			$cta_row[] = array(
				'type'        => 'widget',
				'widget_type' => 'button',
				'settings'    => emcp_array_deep_merge(
					array( 'text' => $cta_label, 'link' => array( 'url' => esc_url_raw( $cta_url ), 'is_external' => '', 'nofollow' => '' ), 'align' => 'center', '_title' => 'Primary CTA' ),
					$resolver->button( 'primary-large' )
				),
			);
		}
		if ( $secondary_label !== '' && $secondary_url !== '' ) {
			$cta_row[] = array(
				'type'        => 'widget',
				'widget_type' => 'button',
				'settings'    => emcp_array_deep_merge(
					array( 'text' => $secondary_label, 'link' => array( 'url' => esc_url_raw( $secondary_url ), 'is_external' => '', 'nofollow' => '' ), 'align' => 'center', '_title' => 'Secondary CTA' ),
					$resolver->button( 'ghost-md' )
				),
			);
		}
		if ( ! empty( $cta_row ) ) {
			$children[] = array(
				'type'     => 'container',
				'settings' => emcp_array_deep_merge(
					array( 'content_width' => 'full', 'flex_direction' => 'row', '_title' => 'CTA row', 'flex_justify_content' => 'center' ),
					$resolver->gap( 16 )
				),
				'children' => $cta_row,
			);
		}

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.hero' ),
				array(
					'flex_direction'        => 'column',
					'align_items'           => 'center',
					'content_width'         => 'boxed',
					'_title'                => 'Hero Minimal Center',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface' ),
				$resolver->gap( 20 )
			),
			'children' => $children,
		);
	}
}

if ( ! function_exists( 'emcp_pattern_hero_minimal_center_meta' ) ) {
	function emcp_pattern_hero_minimal_center_meta(): array {
		return array(
			'category'    => 'hero',
			'description' => 'Clean centered hero on palette surface. Eyebrow + headline + subhead + dual CTA.',
			'slots'       => array(
				'eyebrow'         => 'string',
				'headline'        => 'string (required)',
				'subhead'         => 'string',
				'cta_label'       => 'string',
				'cta_url'         => 'string URL',
				'secondary_label' => 'string',
				'secondary_url'   => 'string URL',
			),
		);
	}
}
