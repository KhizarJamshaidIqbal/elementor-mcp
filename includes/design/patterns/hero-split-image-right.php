<?php
/**
 * Pattern: hero.split-image-right
 *
 * Two-column hero — text left + image right. Responsive stack on mobile.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_hero_split_image_right' ) ) {
	function emcp_pattern_hero_split_image_right( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$eyebrow         = (string) ( $slots['eyebrow'] ?? '' );
		$headline        = (string) ( $slots['headline'] ?? '' );
		$subhead         = (string) ( $slots['subhead'] ?? '' );
		$cta_label       = (string) ( $slots['cta_label'] ?? '' );
		$cta_url         = (string) ( $slots['cta_url'] ?? '' );
		$secondary_label = (string) ( $slots['secondary_label'] ?? '' );
		$secondary_url   = (string) ( $slots['secondary_url'] ?? '' );
		$image           = isset( $slots['image'] ) && is_array( $slots['image'] ) ? $slots['image'] : null;

		$text_children = array();
		if ( $eyebrow !== '' ) {
			$text_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $eyebrow, 'header_size' => 'div', 'align' => 'left', '_title' => 'Eyebrow' ),
					$resolver->typography( 'caption' ),
					$resolver->color( 'title_color', 'accent' )
				),
			);
		}
		$text_children[] = array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => emcp_array_deep_merge(
				array( 'title' => $headline, 'header_size' => 'h1', 'align' => 'left', '_title' => 'Headline' ),
				$resolver->typography( 'display-lg' ),
				$resolver->color( 'title_color', 'text' )
			),
		);
		if ( $subhead !== '' ) {
			$text_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => emcp_array_deep_merge(
					array( 'editor' => '<p>' . esc_html( $subhead ) . '</p>', 'align' => 'left', '_title' => 'Subhead' ),
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
					array( 'text' => $cta_label, 'link' => array( 'url' => esc_url_raw( $cta_url ) ), 'align' => 'left', '_title' => 'Primary CTA' ),
					$resolver->button( 'primary-large' )
				),
			);
		}
		if ( $secondary_label !== '' && $secondary_url !== '' ) {
			$cta_row[] = array(
				'type'        => 'widget',
				'widget_type' => 'button',
				'settings'    => emcp_array_deep_merge(
					array( 'text' => $secondary_label, 'link' => array( 'url' => esc_url_raw( $secondary_url ) ), 'align' => 'left', '_title' => 'Secondary CTA' ),
					$resolver->button( 'outline-md' )
				),
			);
		}
		if ( ! empty( $cta_row ) ) {
			$text_children[] = array(
				'type'     => 'container',
				'settings' => emcp_array_deep_merge(
					array( 'content_width' => 'full', 'flex_direction' => 'row', '_title' => 'Hero CTA row', 'flex_justify_content' => 'flex-start' ),
					$resolver->gap( 16 )
				),
				'children' => $cta_row,
			);
		}

		$image_widget = $image ? array(
			'type'        => 'widget',
			'widget_type' => 'image',
			'settings'    => emcp_array_deep_merge(
				array(
					'image' => array(
						'url' => esc_url_raw( $image['url'] ?? '' ),
						'id'  => (int) ( $image['id'] ?? 0 ),
						'alt' => sanitize_text_field( $image['alt'] ?? '' ),
					),
					'image_size' => 'full',
					'_title'     => 'Hero image',
				),
				$resolver->radius( 'card' ),
				$resolver->shadow( 'medium' )
			),
		) : array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => array( 'title' => '[image]', 'align' => 'center', '_title' => 'Image placeholder' ),
		);

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.xl' ),
				array(
					'flex_direction'        => 'row',
					'content_width'         => 'boxed',
					'_title'                => 'Hero Split',
					'background_background' => 'classic',
					'flex_align_items'      => 'center',
				),
				$resolver->color( 'background_color', 'surface' ),
				$resolver->gap( 48 )
			),
			'children' => array(
				array(
					'type'     => 'container',
					'settings' => emcp_array_deep_merge(
						array( 'content_width' => 'full', 'flex_direction' => 'column', '_title' => 'Hero text column' ),
						$resolver->gap( 20 )
					),
					'children' => $text_children,
				),
				array(
					'type'     => 'container',
					'settings' => array( 'content_width' => 'full', 'flex_direction' => 'column', '_title' => 'Hero image column' ),
					'children' => array( $image_widget ),
				),
			),
		);
	}
}

if ( ! function_exists( 'emcp_pattern_hero_split_image_right_meta' ) ) {
	function emcp_pattern_hero_split_image_right_meta(): array {
		return array(
			'category'    => 'hero',
			'description' => 'Two-column hero — text left + image right. Responsive stack.',
			'slots'       => array(
				'eyebrow'         => 'string',
				'headline'        => 'string (required)',
				'subhead'         => 'string',
				'cta_label'       => 'string',
				'cta_url'         => 'string URL',
				'secondary_label' => 'string',
				'secondary_url'   => 'string URL',
				'image'           => 'array {url,id,alt}',
				'image_query'     => 'string',
			),
		);
	}
}
