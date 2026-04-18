<?php
/**
 * Pattern: hero.post-article
 *
 * Editorial blog hero. Outer container holds featured image bg + dark
 * overlay + wave shape divider. An inner content column (max 880px,
 * centered) holds the pill, title, and meta — this avoids flex
 * stretching that causes full-width pills and left-drifting meta.
 *
 * Pill uses an HTML widget (inline span) for auto-width reliability —
 * heading-in-container approaches stretch to 100% in flex layouts.
 * Title forces text_transform: none to override theme uppercase CSS.
 *
 * Slots:
 *   - headline        string        Empty = post-title dynamic tag.
 *   - category_label  string        Pill above title.
 *   - bg_image        array|null    Static { url, id, alt }.
 *   - bg_image_query  string        Stock query (auto resolved).
 *   - meta_items      array<string> Chips, e.g. ['Dec 2026','8 min'].
 *   - use_dynamic     bool          Default true.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_hero_post_article' ) ) {
	function emcp_pattern_hero_post_article( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$use_dynamic    = isset( $slots['use_dynamic'] ) ? (bool) $slots['use_dynamic'] : true;
		$headline       = (string) ( $slots['headline'] ?? '' );
		$category_label = (string) ( $slots['category_label'] ?? '' );
		$bg_image       = isset( $slots['bg_image'] ) && is_array( $slots['bg_image'] ) ? $slots['bg_image'] : null;
		$meta_items     = is_array( $slots['meta_items'] ?? null ) ? $slots['meta_items'] : array();

		// H1 — force white + no uppercase transform + normal case letter spacing.
		$heading_settings = emcp_array_deep_merge(
			array(
				'title'                   => $headline !== '' ? $headline : 'Post title',
				'align'                   => 'center',
				'header_size'             => 'h1',
				'_title'                  => 'Post title',
				'title_color'             => '#FFFFFF',
			),
			$resolver->typography( 'display-lg' ),
			array(
				'typography_text_transform' => 'none',
			)
		);
		if ( $use_dynamic && $headline === '' ) {
			$heading_settings['__dynamic__'] = array(
				'title' => '[elementor-tag id="emcppt1" name="post-title" settings="%7B%7D"]',
			);
		}

		$inner_children = array();

		// Category pill — HTML widget for inline auto-width.
		if ( $category_label !== '' ) {
			$pill_bg    = emcp_tokens_palette_get( $resolver->palette(), 'accent' ) ?? '#F4A460';
			$pill_text  = esc_html( strtoupper( $category_label ) );
			$pill_style = sprintf(
				'display:inline-block;background:%s;color:#FFFFFF;padding:6px 18px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:0.12em;line-height:1.2;',
				esc_attr( $pill_bg )
			);
			$inner_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'html',
				'settings'    => array(
					'_title' => 'Category pill',
					'html'   => '<div style="text-align:center;"><span style="' . $pill_style . '">' . $pill_text . '</span></div>',
				),
			);
		}

		// H1 title.
		$inner_children[] = array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => $heading_settings,
		);

		// Meta chips inline row.
		if ( ! empty( $meta_items ) ) {
			$inner_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'icon-list',
				'settings'    => emcp_array_deep_merge(
					array(
						'view'                      => 'inline',
						'icon_list'                 => array_map(
							function ( $text ) {
								return array(
									'text'          => (string) $text,
									'selected_icon' => array( 'value' => 'fas fa-circle', 'library' => 'fa-solid' ),
									'_id'           => substr( md5( (string) $text ), 0, 7 ),
								);
							},
							$meta_items
						),
						'icon_align'                => 'left',
						'icon_self_align'           => 'center',
						'space_between'             => array( 'unit' => 'px', 'size' => 20, 'sizes' => array() ),
						'align'                     => 'center',
						'text_indent'               => array( 'unit' => 'px', 'size' => 10, 'sizes' => array() ),
						'_title'                    => 'Post meta',
						'text_color'                => '#FFFFFF',
						'icon_color'                => '#F4A460',
						'icon_size'                 => array( 'unit' => 'px', 'size' => 5, 'sizes' => array() ),
						'typography_text_transform' => 'uppercase',
						'typography_letter_spacing' => array( 'unit' => 'em', 'size' => 0.1, 'sizes' => array() ),
					),
					$resolver->typography( 'caption' )
				),
			);
		}

		// Inner content column — boxed 880px, centered, sane gap.
		$inner_column = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'        => 'full',
				'flex_direction'       => 'column',
				'flex_align_items'     => 'center',
				'flex_justify_content' => 'center',
				'_title'               => 'Hero content column',
				'width'                => array( 'unit' => 'px', 'size' => 880, 'sizes' => array() ),
				'flex_gap'             => array( 'unit' => 'px', 'size' => 20, 'column' => '20', 'row' => '20', 'isLinked' => true ),
				'padding'              => array( 'unit' => 'px', 'top' => '0', 'right' => '24', 'bottom' => '0', 'left' => '24', 'isLinked' => false ),
			),
			'children' => $inner_children,
		);

		// Outer hero bg settings.
		$hero_bg = array();
		if ( $bg_image ) {
			$hero_bg = emcp_array_deep_merge(
				$resolver->background_image( $bg_image ),
				$resolver->overlay( 'dark-gradient', 0.85 )
			);
		} elseif ( $use_dynamic ) {
			$hero_bg = emcp_array_deep_merge(
				array(
					'background_background' => 'classic',
					'__dynamic__'           => array(
						'background_image' => '[elementor-tag id="emcppfi" name="post-featured-image" settings="%7B%7D"]',
					),
					'background_position'   => 'center center',
					'background_repeat'     => 'no-repeat',
					'background_size'       => 'cover',
				),
				$resolver->overlay( 'dark-gradient', 0.85 )
			);
		} else {
			$hero_bg = array( 'background_background' => 'classic', 'background_color' => '#0F0D0A' );
		}

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.hero' ),
				$hero_bg,
				$resolver->shape_divider( 'waves', 'bottom', true ),
				array(
					'flex_direction'       => 'column',
					'flex_align_items'     => 'center',
					'flex_justify_content' => 'center',
					'min_height'           => array( 'size' => 70, 'unit' => 'vh' ),
					'_title'               => 'Post Hero',
					'content_width'        => 'full',
				)
			),
			'children' => array( $inner_column ),
		);
	}
}

if ( ! function_exists( 'emcp_pattern_hero_post_article_meta' ) ) {
	function emcp_pattern_hero_post_article_meta(): array {
		return array(
			'category'    => 'hero',
			'description' => 'Editorial blog hero — bg image + dark overlay, boxed inner column with inline pill + white centered H1 + meta chips.',
			'slots'       => array(
				'headline'       => 'string (empty = post-title dynamic tag)',
				'category_label' => 'string (pill)',
				'bg_image'       => 'array {url,id,alt}',
				'bg_image_query' => 'string',
				'meta_items'     => 'array<string>',
				'use_dynamic'    => 'bool (default true)',
			),
		);
	}
}
