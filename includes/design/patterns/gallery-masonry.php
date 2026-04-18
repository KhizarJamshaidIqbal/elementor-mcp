<?php
/**
 * Pattern: gallery.masonry
 *
 * Masonry gallery using Pro gallery widget. Lightbox enabled.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_gallery_masonry' ) ) {
	function emcp_pattern_gallery_masonry( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$heading = (string) ( $slots['heading'] ?? '' );
		$images  = is_array( $slots['images'] ?? null ) ? $slots['images'] : array();

		$items = array();
		foreach ( $images as $img ) {
			if ( empty( $img['url'] ) ) {
				continue;
			}
			$items[] = array(
				'url' => esc_url_raw( $img['url'] ),
				'id'  => (int) ( $img['id'] ?? 0 ),
				'alt' => sanitize_text_field( $img['alt'] ?? '' ),
			);
		}

		$gallery_widget = array(
			'type'        => 'widget',
			'widget_type' => 'gallery',
			'settings'    => emcp_array_deep_merge(
				array(
					'_title'         => 'Masonry gallery',
					'gallery'        => $items,
					'gallery_layout' => 'masonry',
					'columns'        => 3,
					'columns_tablet' => 2,
					'columns_mobile' => 1,
					'gap'            => array( 'unit' => 'px', 'size' => 16, 'sizes' => array() ),
					'image_size'     => 'medium_large',
					'link_to'        => 'file',
					'lightbox'       => 'yes',
				),
				$resolver->radius( 'md' )
			),
		);

		$children = array();
		if ( $heading !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $heading, 'align' => 'center', 'header_size' => 'h2', '_title' => 'Gallery heading' ),
					$resolver->typography( 'heading-xl' ),
					$resolver->color( 'title_color', 'text' )
				),
			);
		}
		$children[] = $gallery_widget;

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.lg' ),
				array(
					'flex_direction'        => 'column',
					'align_items'           => 'center',
					'content_width'         => 'boxed',
					'_title'                => 'Masonry gallery section',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface' ),
				$resolver->gap( 32 )
			),
			'children' => $children,
		);
	}
}

if ( ! function_exists( 'emcp_pattern_gallery_masonry_meta' ) ) {
	function emcp_pattern_gallery_masonry_meta(): array {
		return array(
			'category'    => 'gallery',
			'description' => 'Masonry gallery via Pro gallery widget. Lightbox enabled.',
			'slots'       => array(
				'heading' => 'string',
				'images'  => 'array<{url,id,alt}>',
			),
		);
	}
}
