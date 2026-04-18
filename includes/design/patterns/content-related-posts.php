<?php
/**
 * Pattern: content.related-posts
 *
 * "You may also like" grid for end of single-post templates. Uses
 * Elementor Pro `posts` widget (classic skin). Pattern outer gets
 * `css_classes=emcp-related-section` so article-styles.css can
 * restyle default posts output into modern cards with image zoom,
 * hover lift, brand-colored titles, clean meta, read-more arrow CTA.
 *
 * Slots:
 *   - heading     string  Default 'You may also like'.
 *   - eyebrow     string  Small label above heading. Default 'KEEP READING'.
 *   - columns     int     1..4. Default 3.
 *   - count       int     1..12. Default 3.
 *   - post_type   string  Default 'post'.
 *   - excerpt_len int     Words 5..40. Default 14.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_content_related_posts' ) ) {
	function emcp_pattern_content_related_posts( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$heading     = (string) ( $slots['heading'] ?? 'You may also like' );
		$eyebrow     = (string) ( $slots['eyebrow'] ?? 'KEEP READING' );
		$columns     = max( 1, min( 4, (int) ( $slots['columns'] ?? 3 ) ) );
		$count       = max( 1, min( 12, (int) ( $slots['count'] ?? 3 ) ) );
		$post_type   = (string) ( $slots['post_type'] ?? 'post' );
		$excerpt_len = max( 5, min( 40, (int) ( $slots['excerpt_len'] ?? 14 ) ) );

		$children = array();

		if ( $eyebrow !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array(
						'title'                     => $eyebrow,
						'header_size'               => 'div',
						'align'                     => 'center',
						'_title'                    => 'Related eyebrow',
						'_css_classes'              => 'emcp-related-eyebrow',
						'typography_text_transform' => 'uppercase',
						'typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0.16, 'sizes' => array() ),
					),
					$resolver->typography( 'caption' ),
					$resolver->color( 'title_color', 'accent' )
				),
			);
		}

		$children[] = array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => emcp_array_deep_merge(
				array(
					'title'        => $heading,
					'align'        => 'center',
					'header_size'  => 'h2',
					'_title'       => 'Related heading',
					'_css_classes' => 'emcp-related-heading',
				),
				$resolver->typography( 'heading-xl' ),
				$resolver->color( 'title_color', 'text' )
			),
		);

		$children[] = array(
			'type'        => 'widget',
			'widget_type' => 'posts',
			'settings'    => array(
				'_title'                         => 'Related posts grid',
				'_css_classes'                   => 'emcp-related-grid',
				'classic_columns'                => (string) $columns,
				'classic_columns_tablet'         => '2',
				'classic_columns_mobile'         => '1',
				'classic_posts_per_page'         => $count,
				'posts_post_type'                => $post_type,
				'orderby'                        => 'rand',
				'order'                          => 'desc',
				'classic_show_image'             => 'yes',
				'classic_image_ratio'            => array( 'unit' => 'px', 'size' => 0.62, 'sizes' => array() ),
				'classic_show_title'             => 'yes',
				'classic_show_excerpt'           => 'yes',
				'classic_excerpt_length'         => $excerpt_len,
				'classic_show_meta_data'         => array( 'date' ),
				'classic_meta_separator'         => '·',
				'classic_show_read_more'         => 'yes',
				'classic_read_more_text'         => 'Read more →',
				'classic_row_gap'                => array( 'unit' => 'px', 'size' => 36, 'sizes' => array() ),
				'classic_column_gap'             => array( 'unit' => 'px', 'size' => 32, 'sizes' => array() ),
				'pagination_type'                => 'none',
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
					'_title'                => 'Related posts',
					'css_classes'           => 'emcp-related-section',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface-alt' ),
				$resolver->gap( 16 )
			),
			'children' => $children,
		);
	}
}

if ( ! function_exists( 'emcp_pattern_content_related_posts_meta' ) ) {
	function emcp_pattern_content_related_posts_meta(): array {
		return array(
			'category'    => 'content',
			'description' => 'Related posts grid. Card hover lift, image zoom, brand titles, clean meta, read-more arrow CTA.',
			'slots'       => array(
				'heading'     => 'string (default "You may also like")',
				'eyebrow'     => 'string (default "KEEP READING")',
				'columns'     => 'int 1..4 (default 3)',
				'count'       => 'int 1..12 (default 3)',
				'post_type'   => 'string (default "post")',
				'excerpt_len' => 'int 5..40 words (default 14)',
			),
		);
	}
}
