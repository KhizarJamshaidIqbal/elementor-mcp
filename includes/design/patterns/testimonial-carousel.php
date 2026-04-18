<?php
/**
 * Pattern: testimonial.carousel
 *
 * Rotating testimonial carousel using Pro testimonial-carousel widget.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_testimonial_carousel' ) ) {
	function emcp_pattern_testimonial_carousel( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$heading = (string) ( $slots['heading'] ?? 'What travelers say' );
		$items   = is_array( $slots['items'] ?? null ) ? $slots['items'] : array();

		$slides = array();
		foreach ( $items as $it ) {
			$slides[] = array(
				'content' => (string) ( $it['body'] ?? '' ),
				'name'    => (string) ( $it['author'] ?? '' ),
				'title'   => (string) ( $it['role'] ?? '' ),
				'rating'  => (int) ( $it['rating'] ?? 5 ),
				'image'   => array( 'url' => '', 'id' => '' ),
				'_id'     => substr( md5( (string) ( $it['author'] ?? '' ) . ( $it['body'] ?? '' ) ), 0, 7 ),
			);
		}

		$carousel_widget = array(
			'type'        => 'widget',
			'widget_type' => 'testimonial-carousel',
			'settings'    => emcp_array_deep_merge(
				array(
					'_title'           => 'Testimonial carousel',
					'slides'           => $slides,
					'slides_per_view'  => '1',
					'slides_to_scroll' => '1',
					'navigation'       => 'both',
					'pagination'       => 'bullets',
					'autoplay'         => 'yes',
					'autoplay_speed'   => 5000,
					'space_between'    => array( 'unit' => 'px', 'size' => 30, 'sizes' => array() ),
				),
				$resolver->typography( 'body-lg' ),
				$resolver->color( 'content_color', 'text' ),
				$resolver->color( 'name_color', 'text' ),
				$resolver->color( 'title_color', 'text-muted' )
			),
		);

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.lg' ),
				array(
					'flex_direction'        => 'column',
					'align_items'           => 'center',
					'content_width'         => 'boxed',
					'_title'                => 'Testimonial carousel',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface' ),
				$resolver->gap( 32 )
			),
			'children' => array(
				array(
					'type'        => 'widget',
					'widget_type' => 'heading',
					'settings'    => emcp_array_deep_merge(
						array( 'title' => $heading, 'align' => 'center', 'header_size' => 'h2', '_title' => 'Testimonial heading' ),
						$resolver->typography( 'heading-xl' ),
						$resolver->color( 'title_color', 'text' )
					),
				),
				$carousel_widget,
			),
		);
	}
}

if ( ! function_exists( 'emcp_pattern_testimonial_carousel_meta' ) ) {
	function emcp_pattern_testimonial_carousel_meta(): array {
		return array(
			'category'    => 'testimonial',
			'description' => 'Rotating testimonial carousel. Uses Pro testimonial-carousel widget with autoplay.',
			'slots'       => array(
				'heading' => 'string',
				'items'   => 'array<{body,author,role,rating}>',
			),
		);
	}
}
