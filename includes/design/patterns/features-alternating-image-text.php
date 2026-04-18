<?php
/**
 * Pattern: features.alternating-image-text
 *
 * Zig-zag feature blocks — odd rows: image-left/text-right,
 * even rows: text-left/image-right. Editorial storytelling.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_features_alternating_image_text' ) ) {
	function emcp_pattern_features_alternating_image_text( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$heading = (string) ( $slots['heading'] ?? '' );
		$rows    = is_array( $slots['rows'] ?? null ) ? $slots['rows'] : array();

		$row_containers = array();
		foreach ( $rows as $i => $row ) {
			$title       = (string) ( $row['title'] ?? '' );
			$body        = (string) ( $row['body'] ?? '' );
			$image       = isset( $row['image'] ) && is_array( $row['image'] ) ? $row['image'] : null;
			$image_right = ( $i % 2 === 1 );

			$text_col = array(
				'type'     => 'container',
				'settings' => emcp_array_deep_merge(
					array( 'content_width' => 'full', 'flex_direction' => 'column', '_title' => 'Row text col' ),
					$resolver->gap( 16 )
				),
				'children' => array(
					array(
						'type'        => 'widget',
						'widget_type' => 'heading',
						'settings'    => emcp_array_deep_merge(
							array( 'title' => $title, 'header_size' => 'h3', 'align' => 'left', '_title' => 'Row title' ),
							$resolver->typography( 'heading-lg' ),
							$resolver->color( 'title_color', 'text' )
						),
					),
					array(
						'type'        => 'widget',
						'widget_type' => 'text-editor',
						'settings'    => emcp_array_deep_merge(
							array( 'editor' => '<p>' . esc_html( $body ) . '</p>', '_title' => 'Row body' ),
							$resolver->typography( 'body-lg' ),
							$resolver->color( 'color', 'text-muted' )
						),
					),
				),
			);

			$image_col = array(
				'type'     => 'container',
				'settings' => array( 'content_width' => 'full', 'flex_direction' => 'column', '_title' => 'Row image col' ),
				'children' => array(
					$image ? array(
						'type'        => 'widget',
						'widget_type' => 'image',
						'settings'    => emcp_array_deep_merge(
							array(
								'image'      => array(
									'url' => esc_url_raw( $image['url'] ?? '' ),
									'id'  => (int) ( $image['id'] ?? 0 ),
									'alt' => sanitize_text_field( $image['alt'] ?? '' ),
								),
								'image_size' => 'full',
								'_title'     => 'Row image',
							),
							$resolver->radius( 'card' ),
							$resolver->shadow( 'soft' )
						),
					) : array(
						'type'        => 'widget',
						'widget_type' => 'heading',
						'settings'    => array( 'title' => '[image]', '_title' => 'Row image placeholder' ),
					),
				),
			);

			$row_containers[] = array(
				'type'     => 'container',
				'settings' => emcp_array_deep_merge(
					array(
						'content_width'    => 'full',
						'flex_direction'   => 'row',
						'_title'           => 'Alt row ' . ( $i + 1 ),
						'flex_align_items' => 'center',
					),
					$resolver->gap( 48 )
				),
				'children' => $image_right ? array( $text_col, $image_col ) : array( $image_col, $text_col ),
			);
		}

		$children = array();
		if ( $heading !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $heading, 'align' => 'center', 'header_size' => 'h2', '_title' => 'Alt heading' ),
					$resolver->typography( 'heading-xl' ),
					$resolver->color( 'title_color', 'text' )
				),
			);
		}
		$children = array_merge( $children, $row_containers );

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.lg' ),
				array(
					'flex_direction'        => 'column',
					'align_items'           => 'center',
					'content_width'         => 'boxed',
					'_title'                => 'Alternating feature blocks',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface' ),
				$resolver->gap( 80 )
			),
			'children' => $children,
		);
	}
}

if ( ! function_exists( 'emcp_pattern_features_alternating_image_text_meta' ) ) {
	function emcp_pattern_features_alternating_image_text_meta(): array {
		return array(
			'category'    => 'features',
			'description' => 'Zig-zag feature blocks alternating image/text sides. Editorial storytelling.',
			'slots'       => array(
				'heading' => 'string',
				'rows'    => 'array<{title,body,image:{url,id,alt}|null}>',
			),
		);
	}
}
