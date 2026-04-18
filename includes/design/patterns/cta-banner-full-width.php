<?php
/**
 * Pattern: cta.banner-full-width
 *
 * Modern full-bleed CTA banner — featured-image bg + dark multi-stop
 * gradient overlay + radial accent glow, glass-card content surface,
 * eyebrow pill, bold display headline, subhead, icon-prefixed dual
 * button row (primary white + WhatsApp icon / secondary ghost-outline
 * + arrow icon), 3-up trust-stat chips (no boring single text line).
 * 70vh min-height for desktop presence, shape-divider top for flow.
 *
 * Slots:
 *   - headline           string (required)
 *   - subhead            string
 *   - eyebrow            string (default 'BOOK YOUR ADVENTURE')
 *   - primary_label      string (required)
 *   - primary_url        string (required)
 *   - primary_icon       string FA class (default 'fab fa-whatsapp')
 *   - secondary_label    string
 *   - secondary_url      string
 *   - secondary_icon     string FA class (default 'fas fa-arrow-right')
 *   - trust_stats        array of { value, label } — 3-up chips row.
 *                        Empty default — caller supplies. Example:
 *                        [['value'=>'★ 4.9','label'=>'1,000+ reviews'],
 *                         ['value'=>'LICENSED','label'=>'Regulated operator'],
 *                         ['value'=>'FREE','label'=>'Hotel transfer']]
 *   - bg_image           array { url, id, alt } — static override
 *   - bg_image_query     string — stock search query
 *   - use_featured_image bool (default true → post-featured-image dynamic tag)
 *   - overlay_opacity    float 0..1 (default 0.88)
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_cta_banner_full_width' ) ) {
	function emcp_pattern_cta_banner_full_width( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$headline           = (string) ( $slots['headline'] ?? '' );
		$subhead            = (string) ( $slots['subhead'] ?? '' );
		$eyebrow            = (string) ( $slots['eyebrow'] ?? '' );
		$primary_label      = (string) ( $slots['primary_label'] ?? '' );
		$primary_url        = (string) ( $slots['primary_url'] ?? '' );
		$primary_icon       = (string) ( $slots['primary_icon'] ?? 'fab fa-whatsapp' );
		$secondary_label    = (string) ( $slots['secondary_label'] ?? '' );
		$secondary_url      = (string) ( $slots['secondary_url'] ?? '' );
		$secondary_icon     = (string) ( $slots['secondary_icon'] ?? 'fas fa-arrow-right' );
		// Trust stats default: empty array (caller supplies to keep pattern site-agnostic).
		$trust_stats        = isset( $slots['trust_stats'] ) && is_array( $slots['trust_stats'] ) ? $slots['trust_stats'] : array();
		$bg_image           = isset( $slots['bg_image'] ) && is_array( $slots['bg_image'] ) ? $slots['bg_image'] : null;
		$use_featured_image = isset( $slots['use_featured_image'] ) ? (bool) $slots['use_featured_image'] : true;
		$overlay_opacity    = isset( $slots['overlay_opacity'] ) ? (float) $slots['overlay_opacity'] : 0.88;

		$primary_brand = emcp_tokens_palette_get( $resolver->palette(), 'primary' ) ?? '#D2691E';
		$accent_brand  = emcp_tokens_palette_get( $resolver->palette(), 'accent' )  ?? '#F4A460';

		$inner_children = array();

		// Eyebrow pill with accent glow.
		if ( $eyebrow !== '' ) {
			$pill_html = sprintf(
				'<div class="emcp-cta-eyebrow-wrap" style="text-align:center"><span class="emcp-cta-eyebrow-pill" style="--accent:%s">%s</span></div>',
				esc_attr( $accent_brand ),
				esc_html( strtoupper( $eyebrow ) )
			);
			$inner_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'html',
				'settings'    => array(
					'_title' => 'CTA eyebrow',
					'html'   => $pill_html,
				),
			);
		}

		// Headline.
		$inner_children[] = array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => emcp_array_deep_merge(
				array(
					'title'                     => $headline,
					'align'                     => 'center',
					'header_size'               => 'h2',
					'_title'                    => 'CTA headline',
					'_css_classes'              => 'emcp-cta-headline',
					'title_color'               => '#FFFFFF',
					'typography_text_transform' => 'none',
				),
				$resolver->typography( 'display-lg' )
			),
		);

		// Subhead.
		if ( $subhead !== '' ) {
			$inner_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => emcp_array_deep_merge(
					array(
						'editor'       => '<p style="text-align:center">' . esc_html( $subhead ) . '</p>',
						'_title'       => 'CTA subhead',
						'_css_classes' => 'emcp-cta-subhead',
					),
					$resolver->typography( 'body-lg' ),
					array( 'color' => 'rgba(255,255,255,0.9)' )
				),
			);
		}

		// Buttons row — icon + text, modern pill.
		$buttons_row_children = array();
		if ( $primary_label !== '' && $primary_url !== '' ) {
			$primary_btn = emcp_array_deep_merge(
				array(
					'text'          => $primary_label,
					'link'          => array( 'url' => esc_url_raw( $primary_url ), 'is_external' => '', 'nofollow' => '' ),
					'align'         => 'center',
					'_title'        => 'Primary CTA',
					'_css_classes'  => 'emcp-cta-btn-primary',
					'selected_icon' => array( 'value' => $primary_icon, 'library' => ( strpos( $primary_icon, 'fab ' ) === 0 ? 'fa-brands' : 'fa-solid' ) ),
					'icon_align'    => 'left',
					'icon_indent'   => array( 'unit' => 'px', 'size' => 10, 'sizes' => array() ),
				),
				$resolver->button( 'primary-large' ),
				array(
					'background_color'              => '#FFFFFF',
					'button_text_color'             => $primary_brand,
					'button_hover_background_color' => '#FFF8F1',
					'button_hover_text_color'       => $primary_brand,
					'border_radius'                 => array( 'unit' => 'px', 'top' => '999', 'right' => '999', 'bottom' => '999', 'left' => '999', 'isLinked' => true ),
					'text_padding'                  => array( 'unit' => 'px', 'top' => '18', 'right' => '36', 'bottom' => '18', 'left' => '36', 'isLinked' => false ),
					'hover_animation'               => '',
				)
			);
			$buttons_row_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'button',
				'settings'    => $primary_btn,
			);
		}
		if ( $secondary_label !== '' && $secondary_url !== '' ) {
			$secondary_btn = emcp_array_deep_merge(
				array(
					'text'          => $secondary_label,
					'link'          => array( 'url' => esc_url_raw( $secondary_url ), 'is_external' => '', 'nofollow' => '' ),
					'align'         => 'center',
					'_title'        => 'Secondary CTA',
					'_css_classes'  => 'emcp-cta-btn-secondary',
					'selected_icon' => array( 'value' => $secondary_icon, 'library' => 'fa-solid' ),
					'icon_align'    => 'right',
					'icon_indent'   => array( 'unit' => 'px', 'size' => 8, 'sizes' => array() ),
				),
				$resolver->button( 'outline-md' ),
				array(
					'background_color'              => 'rgba(255,255,255,0.08)',
					'button_text_color'             => '#FFFFFF',
					'border_color'                  => 'rgba(255,255,255,0.45)',
					'border_width'                  => array( 'unit' => 'px', 'top' => '1.5', 'right' => '1.5', 'bottom' => '1.5', 'left' => '1.5', 'isLinked' => true ),
					'button_hover_background_color' => 'rgba(255,255,255,0.18)',
					'button_hover_text_color'       => '#FFFFFF',
					'border_radius'                 => array( 'unit' => 'px', 'top' => '999', 'right' => '999', 'bottom' => '999', 'left' => '999', 'isLinked' => true ),
					'text_padding'                  => array( 'unit' => 'px', 'top' => '16', 'right' => '28', 'bottom' => '16', 'left' => '28', 'isLinked' => false ),
					'hover_animation'               => '',
				)
			);
			$buttons_row_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'button',
				'settings'    => $secondary_btn,
			);
		}
		if ( ! empty( $buttons_row_children ) ) {
			$inner_children[] = array(
				'type'     => 'container',
				'settings' => emcp_array_deep_merge(
					array(
						'content_width'        => 'full',
						'flex_direction'       => 'row',
						'_title'               => 'CTA buttons row',
						'flex_justify_content' => 'center',
						'flex_align_items'     => 'center',
						'flex_wrap'            => 'wrap',
						'css_classes'          => 'emcp-cta-btn-row',
					),
					$resolver->gap( 14 )
				),
				'children' => $buttons_row_children,
			);
		}

		// 3-up trust stats — horizontal chips with dividers.
		if ( ! empty( $trust_stats ) ) {
			$stats_html = '<div class="emcp-cta-stats">';
			foreach ( $trust_stats as $stat ) {
				$val = isset( $stat['value'] ) ? (string) $stat['value'] : '';
				$lbl = isset( $stat['label'] ) ? (string) $stat['label'] : '';
				if ( $val === '' && $lbl === '' ) {
					continue;
				}
				$stats_html .= sprintf(
					'<div class="emcp-cta-stat"><div class="emcp-cta-stat-val">%s</div><div class="emcp-cta-stat-lbl">%s</div></div>',
					esc_html( $val ),
					esc_html( $lbl )
				);
			}
			$stats_html .= '</div>';
			$inner_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'html',
				'settings'    => array(
					'_title' => 'CTA trust stats',
					'html'   => $stats_html,
				),
			);
		}

		// Inner content column — glass card feel via CSS.
		$inner_col = array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				array(
					'content_width'        => 'full',
					'flex_direction'       => 'column',
					'flex_align_items'     => 'center',
					'flex_justify_content' => 'center',
					'_title'               => 'CTA inner column',
					'width'                => array( 'unit' => 'px', 'size' => 820, 'sizes' => array() ),
					'css_classes'          => 'emcp-cta-inner',
					'padding'              => array( 'unit' => 'px', 'top' => '0', 'right' => '24', 'bottom' => '0', 'left' => '24', 'isLinked' => false ),
				),
				$resolver->gap( 24 )
			),
			'children' => $inner_children,
		);

		// Background resolution — featured-image (dynamic) > static > solid brand.
		$bg_settings = array();
		if ( $bg_image ) {
			$bg_settings = emcp_array_deep_merge(
				$resolver->background_image( $bg_image ),
				$resolver->overlay( 'dark-gradient', $overlay_opacity )
			);
		} elseif ( $use_featured_image ) {
			// NOTE: NO background_color here — see Gotcha 17.
			$bg_settings = emcp_array_deep_merge(
				array(
					'background_background' => 'classic',
					'__dynamic__'           => array(
						'background_image' => '[elementor-tag id="emcpctaf" name="post-featured-image" settings="%7B%7D"]',
					),
					'background_position'   => 'center center',
					'background_repeat'     => 'no-repeat',
					'background_size'       => 'cover',
				),
				$resolver->overlay( 'dark-gradient', $overlay_opacity )
			);
		} else {
			$bg_settings = emcp_array_deep_merge(
				array( 'background_background' => 'classic' ),
				$resolver->color( 'background_color', 'primary' )
			);
		}

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.lg' ),
				$bg_settings,
				array(
					'flex_direction'       => 'column',
					'flex_align_items'     => 'center',
					'flex_justify_content' => 'center',
					'content_width'        => 'full',
					'_title'               => 'CTA banner',
					'css_classes'          => 'emcp-cta-banner',
					'min_height'           => array( 'size' => 70, 'unit' => 'vh' ),
				)
			),
			'children' => array( $inner_col ),
		);
	}
}

if ( ! function_exists( 'emcp_pattern_cta_banner_full_width_meta' ) ) {
	function emcp_pattern_cta_banner_full_width_meta(): array {
		return array(
			'category'    => 'cta',
			'description' => 'Modern full-bleed CTA — featured-image bg + multi-stop overlay + radial glow, glass eyebrow pill, display headline, subhead, icon-prefixed pill buttons (WhatsApp / arrow), 3-up trust stats row. 70vh.',
			'slots'       => array(
				'headline'           => 'string (required)',
				'subhead'            => 'string',
				'eyebrow'            => 'string (default "BOOK YOUR ADVENTURE")',
				'primary_label'      => 'string (required)',
				'primary_url'        => 'string URL (required)',
				'primary_icon'       => 'string FA class (default "fab fa-whatsapp")',
				'secondary_label'    => 'string',
				'secondary_url'      => 'string URL',
				'secondary_icon'     => 'string FA class (default "fas fa-arrow-right")',
				'trust_stats'        => 'array of {value,label} — 3-up chips',
				'bg_image'           => 'array {url,id,alt}',
				'bg_image_query'     => 'string (stock query)',
				'use_featured_image' => 'bool (default true)',
				'overlay_opacity'    => 'float 0..1 (default 0.88)',
			),
		);
	}
}
