<?php
/**
 * Pattern: logo-cloud.grayscale
 *
 * Row of client/partner logos. Muted opacity for minimal look.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_logo_cloud_grayscale' ) ) {
	function emcp_pattern_logo_cloud_grayscale( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$heading = (string) ( $slots['heading'] ?? '' );
		$logos   = is_array( $slots['logos'] ?? null ) ? $slots['logos'] : array();

		$logo_items = array();
		foreach ( $logos as $l ) {
			if ( empty( $l['url'] ) ) {
				continue;
			}
			$logo_items[] = array(
				'type'        => 'widget',
				'widget_type' => 'image',
				'settings'    => array(
					'image'      => array(
						'url' => esc_url_raw( $l['url'] ),
						'id'  => (int) ( $l['id'] ?? 0 ),
						'alt' => sanitize_text_field( $l['alt'] ?? '' ),
					),
					'image_size' => 'medium',
					'align'      => 'center',
					'width'      => array( 'unit' => 'px', 'size' => 140, 'sizes' => array() ),
					'opacity'    => array( 'unit' => 'px', 'size' => 0.6, 'sizes' => array() ),
					'_title'     => 'Logo',
				),
			);
		}

		$children = array();
		if ( $heading !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $heading, 'align' => 'center', 'header_size' => 'div', '_title' => 'Logo cloud heading' ),
					$resolver->typography( 'caption' ),
					$resolver->color( 'title_color', 'text-muted' )
				),
			);
		}
		$children[] = array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				array(
					'content_width'        => 'full',
					'flex_direction'       => 'row',
					'flex_align_items'     => 'center',
					'flex_justify_content' => 'space-between',
					'_title'               => 'Logos row',
				),
				$resolver->gap( 32 )
			),
			'children' => $logo_items,
		);

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.sm' ),
				array(
					'flex_direction'        => 'column',
					'align_items'           => 'center',
					'content_width'         => 'boxed',
					'_title'                => 'Logo cloud',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface' ),
				$resolver->gap( 20 )
			),
			'children' => $children,
		);
	}
}

if ( ! function_exists( 'emcp_pattern_logo_cloud_grayscale_meta' ) ) {
	function emcp_pattern_logo_cloud_grayscale_meta(): array {
		return array(
			'name'        => 'logo-cloud.grayscale',
			'category'    => 'logo-cloud',
			'description' => 'Row of client/partner logos. Muted opacity minimalist.',
			'slots'       => array(
				'heading' => 'string',
				'logos'   => 'array<{url,id,alt}>',
			),
		);
	}
}
