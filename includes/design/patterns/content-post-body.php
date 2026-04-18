<?php
/**
 * Pattern: content.post-body
 *
 * 2-column article layout. Article body LEFT (wide/flexible), sticky
 * sidebar RIGHT with TOC slot + Related + Recent posts.
 *
 * LEFT: `theme-post-content` (dynamic) or text-editor (static).
 * RIGHT:
 *   - TOC placeholder (filled client-side by article-enhancer JS)
 *   - Related posts (Pro `posts` widget, same category, random)
 *   - Recent posts (Pro `posts` widget, latest by date)
 *
 * Columns stack on mobile via CSS grid.
 *
 * Slots:
 *   - body_html        string Static HTML when use_dynamic=false.
 *   - use_dynamic      bool   Default true → theme-post-content.
 *   - show_sidebar     bool   Default true.
 *   - related_count    int    Default 4.
 *   - recent_count     int    Default 5.
 *   - post_type        string Default 'post'.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_content_post_body' ) ) {
	function emcp_pattern_content_post_body( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$use_dynamic   = isset( $slots['use_dynamic'] ) ? (bool) $slots['use_dynamic'] : true;
		$body_html     = (string) ( $slots['body_html'] ?? '' );
		$show_sidebar  = isset( $slots['show_sidebar'] ) ? (bool) $slots['show_sidebar'] : true;
		$related_count = max( 1, min( 12, (int) ( $slots['related_count'] ?? 4 ) ) );
		$recent_count  = max( 1, min( 12, (int) ( $slots['recent_count'] ?? 5 ) ) );
		$post_type     = (string) ( $slots['post_type'] ?? 'post' );

		// ─── LEFT: article body ───────────────────────────────
		if ( $use_dynamic ) {
			$body_widget = array(
				'type'        => 'widget',
				'widget_type' => 'theme-post-content',
				'settings'    => emcp_array_deep_merge(
					$resolver->typography( 'body-lg' ),
					$resolver->color( 'color', 'text' ),
					array( '_title' => 'Article body' )
				),
			);
		} else {
			if ( $body_html === '' ) {
				$body_html = '<p>Article content goes here.</p>';
			}
			$body_widget = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => emcp_array_deep_merge(
					array( 'editor' => $body_html, '_title' => 'Article body' ),
					$resolver->typography( 'body-lg' ),
					$resolver->color( 'color', 'text' )
				),
			);
		}

		$main_column = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'_title'         => 'Article main',
				'css_classes'   => 'emcp-article-main',
			),
			'children' => array( $body_widget ),
		);

		if ( ! $show_sidebar ) {
			return array(
				'type'     => 'container',
				'settings' => emcp_array_deep_merge(
					$resolver->spacing( 'section.md' ),
					array(
						'flex_direction'        => 'column',
						'align_items'           => 'flex-start',
						'content_width'         => 'boxed',
						'_title'                => 'Article (no sidebar)',
						'background_background' => 'classic',
					),
					$resolver->color( 'background_color', 'surface' )
				),
				'children' => array( $main_column ),
			);
		}

		// ─── RIGHT: sticky sidebar ────────────────────────────
		// Sidebar contains only the TOC by default. Related + Recent
		// posts sections are opt-in via slots (disabled by default
		// because the raw `posts` widget output doesn't blend well
		// with the article palette without a separate pattern pass).
		$show_related = isset( $slots['show_related'] ) ? (bool) $slots['show_related'] : false;
		$show_recent  = isset( $slots['show_recent'] ) ? (bool) $slots['show_recent'] : false;

		$sidebar_children = array();

		// TOC slot (filled by enhancer JS).
		$sidebar_children[] = array(
			'type'        => 'widget',
			'widget_type' => 'html',
			'settings'    => array(
				'_title' => 'TOC slot',
				'html'   => '<div class="emcp-toc-sidebar-slot"></div>',
			),
		);

		// Related posts section (opt-in via show_related slot).
		if ( $show_related ) {
		$sidebar_children[] = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'_title'         => 'Related posts sidebar',
				'css_classes'   => 'emcp-aside-section',
			),
			'children' => array(
				array(
					'type'        => 'widget',
					'widget_type' => 'heading',
					'settings'    => array(
						'title'        => 'Related reads',
						'header_size'  => 'div',
						'_title'       => 'Related heading',
						'css_classes' => 'emcp-aside-title',
					),
				),
				array(
					'type'        => 'widget',
					'widget_type' => 'posts',
					'settings'    => array(
						'_title'                 => 'Related posts widget',
						'classic_columns'        => '1',
						'classic_columns_tablet' => '1',
						'classic_columns_mobile' => '1',
						'classic_posts_per_page' => $related_count,
						'posts_post_type'        => $post_type,
						'orderby'                => 'rand',
						'order'                  => 'desc',
						'classic_show_image'     => 'yes',
						'classic_image_ratio'    => array( 'unit' => 'px', 'size' => 0.66, 'sizes' => array() ),
						'classic_show_title'     => 'yes',
						'classic_show_excerpt'   => '',
						'classic_show_meta_data' => array( 'date' ),
						'classic_show_read_more' => '',
						'classic_row_gap'        => array( 'unit' => 'px', 'size' => 12, 'sizes' => array() ),
						'pagination_type'        => 'none',
					),
				),
			),
		);
		}

		// Recent posts section (opt-in via show_recent slot).
		if ( $show_recent ) {
		$sidebar_children[] = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'_title'         => 'Recent posts sidebar',
				'css_classes'   => 'emcp-aside-section',
			),
			'children' => array(
				array(
					'type'        => 'widget',
					'widget_type' => 'heading',
					'settings'    => array(
						'title'        => 'Recent posts',
						'header_size'  => 'div',
						'_title'       => 'Recent heading',
						'css_classes' => 'emcp-aside-title',
					),
				),
				array(
					'type'        => 'widget',
					'widget_type' => 'posts',
					'settings'    => array(
						'_title'                 => 'Recent posts widget',
						'classic_columns'        => '1',
						'classic_columns_tablet' => '1',
						'classic_columns_mobile' => '1',
						'classic_posts_per_page' => $recent_count,
						'posts_post_type'        => $post_type,
						'orderby'                => 'date',
						'order'                  => 'desc',
						'classic_show_image'     => 'yes',
						'classic_image_ratio'    => array( 'unit' => 'px', 'size' => 0.66, 'sizes' => array() ),
						'classic_show_title'     => 'yes',
						'classic_show_excerpt'   => '',
						'classic_show_meta_data' => array( 'date' ),
						'classic_show_read_more' => '',
						'classic_row_gap'        => array( 'unit' => 'px', 'size' => 12, 'sizes' => array() ),
						'pagination_type'        => 'none',
					),
				),
			),
		);
		}

		$aside_column = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'_title'         => 'Article aside',
				'css_classes'    => 'emcp-article-aside',
				'width'          => array( 'unit' => 'px', 'size' => 280, 'sizes' => array() ),
			),
			'children' => $sidebar_children,
		);

		// ─── OUTER 2-col row ──────────────────────────────────
		// content_width=full lets our CSS layout cap at 1320px (wider
		// than kit boxed), giving main column ~960-1000px on desktop.
		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.md' ),
				array(
					'flex_direction'        => 'row',
					'flex_align_items'      => 'flex-start',
					'flex_gap'              => array( 'unit' => 'px', 'size' => 40, 'column' => '40', 'row' => '32', 'isLinked' => true ),
					'content_width'         => 'full',
					'_title'                => 'Article 2-col',
					'css_classes'           => 'emcp-article-layout',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface' )
			),
			'children' => array( $main_column, $aside_column ),
		);
	}
}

if ( ! function_exists( 'emcp_pattern_content_post_body_meta' ) ) {
	function emcp_pattern_content_post_body_meta(): array {
		return array(
			'category'    => 'content',
			'description' => '2-col article — body LEFT (theme-post-content), sticky sidebar RIGHT with TOC slot + Related + Recent posts. Stacks on mobile.',
			'slots'       => array(
				'body_html'     => 'string (use_dynamic=false only)',
				'use_dynamic'   => 'bool (default true)',
				'show_sidebar'  => 'bool (default true)',
				'related_count' => 'int 1..12 (default 4)',
				'recent_count'  => 'int 1..12 (default 5)',
				'post_type'     => 'string (default "post")',
			),
		);
	}
}
