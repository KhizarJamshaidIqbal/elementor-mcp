<?php
/**
 * Pattern: faq.page-full
 *
 * Full FAQ landing page per Claude design spec (FAQ.html):
 *   1. Hero — breadcrumb, eyebrow, display headline with gradient accent,
 *      subtitle, search form, popular tag pills. Twilight bg + dune SVG.
 *   2. Sticky category tabs — 7 pill buttons with icons + counts.
 *   3. Popular questions — eyebrow, headline, description, 3×2 icon cards.
 *   4. Full library — intro, sidebar index, 7 categorized accordions.
 *   5. Trust band — dark 4-up icon + title + subtitle grid.
 *   6. Final CTA — twilight bg overlay, WhatsApp green + Call orange buttons.
 *
 * Native Elementor widgets (heading, text-editor, icon-box, accordion,
 * icon-list, button, container). HTML widget only where Elementor lacks
 * an equivalent (search form, tab filter-nav+JS, dune SVG).
 *
 * CSS loaded externally via includes/design/css/faq-page.css (enqueued
 * by Article_Enhancer). Design's original BEM classes preserved verbatim
 * on each widget's _css_classes / css_classes so design CSS drops in
 * unchanged.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_faq_page_full' ) ) {

	function emcp_pattern_faq_page_full( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$children = array();

		$hero = emcp_faq_pf_hero( is_array( $slots['hero'] ?? null ) ? $slots['hero'] : array() );
		if ( $hero ) { $children[] = $hero; }

		$tabs = emcp_faq_pf_tabs( is_array( $slots['tabs'] ?? null ) ? $slots['tabs'] : array() );
		if ( $tabs ) { $children[] = $tabs; }

		$popular = emcp_faq_pf_popular( is_array( $slots['popular'] ?? null ) ? $slots['popular'] : array() );
		if ( $popular ) { $children[] = $popular; }

		$library = emcp_faq_pf_library(
			is_array( $slots['library_intro'] ?? null ) ? $slots['library_intro'] : array(),
			is_array( $slots['categories']    ?? null ) ? $slots['categories']    : array()
		);
		if ( $library ) { $children[] = $library; }

		$trust = emcp_faq_pf_trust( is_array( $slots['trust'] ?? null ) ? $slots['trust'] : array() );
		if ( $trust ) { $children[] = $trust; }

		$cta = emcp_faq_pf_final_cta( is_array( $slots['final_cta'] ?? null ) ? $slots['final_cta'] : array() );
		if ( $cta ) { $children[] = $cta; }

		return array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'padding'        => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
				'flex_gap'       => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
				'_title'         => 'FAQ page wrapper',
				'css_classes'    => 'emcp-faqpage',
			),
			'children' => $children,
		);
	}
}

/* ═════════════════════════════════════════════════════════════════
   1 · HERO
   ═════════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'emcp_faq_pf_hero' ) ) {
	function emcp_faq_pf_hero( array $cfg ): ?array {
		if ( empty( $cfg ) ) { return null; }

		$breadcrumb         = is_array( $cfg['breadcrumb']  ?? null ) ? $cfg['breadcrumb']  : array();
		$eyebrow            = (string) ( $cfg['eyebrow']    ?? '' );
		$headline           = (string) ( $cfg['headline']   ?? '' );
		$headline_accent    = (string) ( $cfg['headline_accent'] ?? '' );
		$subtitle           = (string) ( $cfg['subtitle']   ?? '' );
		$bg_image_url       = (string) ( $cfg['bg_image_url'] ?? '' );
		$search_placeholder = (string) ( $cfg['search_placeholder'] ?? 'Search…' );
		$popular_tags       = is_array( $cfg['popular_tags'] ?? null ) ? $cfg['popular_tags'] : array();

		$inner = array();

		// Breadcrumb — native icon-list inline with design class .hero__crumbs.
		if ( ! empty( $breadcrumb ) ) {
			$bc_items = array();
			foreach ( $breadcrumb as $i => $bc ) {
				$bc_items[] = array(
					'text'          => (string) ( $bc['label'] ?? '' ),
					'link'          => array( 'url' => (string) ( $bc['url'] ?? '' ), 'is_external' => '', 'nofollow' => '' ),
					'selected_icon' => $i === 0 ? array( 'value' => '', 'library' => '' ) : array( 'value' => 'fas fa-angle-right', 'library' => 'fa-solid' ),
					'_id'           => 'bc' . $i,
				);
			}
			$inner[] = array(
				'type'        => 'widget',
				'widget_type' => 'icon-list',
				'settings'    => array(
					'view'                      => 'inline',
					'icon_list'                 => $bc_items,
					'icon_align'                => 'left',
					'space_between'             => array( 'unit' => 'px', 'size' => 8, 'sizes' => array() ),
					'_title'       => 'Hero breadcrumb',
					'_css_classes' => 'hero__crumbs',
				),
			);
		}

		// Eyebrow — native heading with design classes .eyebrow .eyebrow--on-dark.
		if ( $eyebrow !== '' ) {
			$inner[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => array(
					'title'        => $eyebrow,
					'header_size'  => 'div',
					'align'        => 'left',
					'_title'       => 'Hero eyebrow',
					'_css_classes' => 'eyebrow eyebrow--on-dark',
				),
			);
		}

		// Headline — native heading with design class .hero__title + <em> gradient accent.
		$title_html = esc_html( $headline );
		if ( $headline_accent !== '' ) {
			$title_html .= ' <em>' . esc_html( $headline_accent ) . '</em>';
		}
		$inner[] = array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => array(
				'title'        => $title_html,
				'header_size'  => 'h1',
				'align'        => 'left',
				'_title'       => 'Hero headline',
				'_css_classes' => 'hero__title',
			),
		);

		if ( $subtitle !== '' ) {
			$inner[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => array(
					'editor'       => '<p>' . esc_html( $subtitle ) . '</p>',
					'align'        => 'left',
					'_title'       => 'Hero subtitle',
					'_css_classes' => 'hero__sub',
				),
			);
		}

		// Search form — HTML widget (no native search-input widget).
		$inner[] = array(
			'type'        => 'widget',
			'widget_type' => 'html',
			'settings'    => array(
				'_title' => 'Hero search',
				'html'   => sprintf(
					'<form class="hero__search" role="search" onsubmit="event.preventDefault();" aria-label="Search FAQs"><i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i><input type="search" placeholder="%s" aria-label="Search questions"><button type="submit">Search</button></form>',
					esc_attr( $search_placeholder )
				),
			),
		);

		// Popular pills — HTML widget (compound inline label + pill links with distinct styles).
		if ( ! empty( $popular_tags ) ) {
			$pills_html = '<div class="hero__hints" aria-label="Popular searches"><span>Popular:</span>';
			foreach ( $popular_tags as $pt ) {
				$pills_html .= sprintf(
					'<a href="%s">%s</a>',
					esc_url( (string) ( $pt['url'] ?? '#' ) ),
					esc_html( (string) ( $pt['label'] ?? '' ) )
				);
			}
			$pills_html .= '</div>';
			$inner[] = array(
				'type'        => 'widget',
				'widget_type' => 'html',
				'settings'    => array( '_title' => 'Hero popular pills', 'html' => $pills_html ),
			);
		}

		// Inner content column — capped 1200px via design .hero__inner.
		$inner_col = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'    => 'full',
				'flex_direction'   => 'column',
				'flex_align_items' => 'flex-start',
				'flex_gap'         => array( 'unit' => 'px', 'size' => 16, 'column' => '16', 'row' => '16', 'isLinked' => true ),
				'_title'           => 'Hero inner column',
				'css_classes'      => 'hero__inner',
			),
			'children' => $inner,
		);

		// Dune SVG silhouette on hero bottom edge (design .hero__dunes).
		$dune_svg = array(
			'type'        => 'widget',
			'widget_type' => 'html',
			'settings'    => array(
				'_title' => 'Hero dune silhouette',
				'html'   => '<svg class="hero__dunes" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true"><path fill="currentColor" d="M0,60 L0,44 C120,22 220,48 360,38 C500,28 620,8 780,18 C940,28 1080,52 1220,44 C1320,38 1400,28 1440,32 L1440,60 Z"/></svg>',
			),
		);

		// Outer hero section — design #hero styles handle overlay + min-height.
		$outer = array(
			'content_width'         => 'full',
			'flex_direction'        => 'column',
			'flex_align_items'      => 'center',
			'flex_justify_content'  => 'center',
			'padding'               => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
			'background_background' => 'classic',
			'background_position'   => 'center center',
			'background_repeat'     => 'no-repeat',
			'background_size'       => 'cover',
			'_title'                => 'FAQ Hero',
			'css_classes'           => 'hero',
			'_element_id'           => 'hero',
		);
		if ( $bg_image_url !== '' ) {
			$outer['background_image'] = array( 'url' => $bg_image_url, 'id' => 0, 'alt' => 'Desert twilight' );
		}

		return array(
			'type'     => 'container',
			'settings' => $outer,
			'children' => array( $inner_col, $dune_svg ),
		);
	}
}

/* ═════════════════════════════════════════════════════════════════
   2 · STICKY CATEGORY TABS
   ═════════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'emcp_faq_pf_tabs' ) ) {
	function emcp_faq_pf_tabs( array $tabs ): ?array {
		if ( empty( $tabs ) ) { return null; }

		// Design tab-nav is a scrollable pill row with data-filter attrs driving JS filter.
		$html = '<div class="tabs__inner"><ul class="tabs__list" role="tablist">';
		foreach ( $tabs as $i => $tab ) {
			$is_active = $i === 0;
			$html .= sprintf(
				'<li><button class="tabs__btn%s" role="tab" aria-selected="%s" data-filter="%s"><i class="%s" aria-hidden="true"></i> %s <span class="tabs__count">%s</span></button></li>',
				$is_active ? ' is-active' : '',
				$is_active ? 'true' : 'false',
				esc_attr( (string) ( $tab['id'] ?? '' ) ),
				esc_attr( (string) ( $tab['icon'] ?? 'fa-solid fa-circle' ) ),
				esc_html( (string) ( $tab['label'] ?? '' ) ),
				esc_html( (string) ( $tab['count'] ?? '' ) )
			);
		}
		$html .= '</ul></div>';

		$script = '<script>(function(){var bs=document.querySelectorAll(".tabs__btn"),cs=document.querySelectorAll(".faq-cat");if(!bs.length)return;bs.forEach(function(b){b.addEventListener("click",function(){var f=b.dataset.filter;bs.forEach(function(x){var a=x.dataset.filter===f;x.classList.toggle("is-active",a);x.setAttribute("aria-selected",a?"true":"false");});cs.forEach(function(c){var s=(f==="all")||(c.dataset.cat===f);c.classList.toggle("is-hidden",!s);});});});if(location.hash){var t=document.querySelector(location.hash);if(t&&t.tagName==="DETAILS")t.open=true;}})();</script>';

		return array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'padding'        => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
				'flex_direction' => 'column',
				'flex_gap'       => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
				'_title'         => 'FAQ tabs',
				'css_classes'    => 'tabs',
				'_element_id'    => 'tabs',
			),
			'children' => array(
				array( 'type' => 'widget', 'widget_type' => 'html', 'settings' => array( '_title' => 'Tabs nav', 'html' => $html ) ),
				array( 'type' => 'widget', 'widget_type' => 'html', 'settings' => array( '_title' => 'Tabs JS',  'html' => $script ) ),
			),
		);
	}
}

/* ═════════════════════════════════════════════════════════════════
   3 · POPULAR QUESTIONS (3×2 cards)
   ═════════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'emcp_faq_pf_popular' ) ) {
	function emcp_faq_pf_popular( array $cfg ): ?array {
		if ( empty( $cfg ) ) { return null; }

		$eyebrow         = (string) ( $cfg['eyebrow']         ?? '' );
		$headline        = (string) ( $cfg['headline']        ?? '' );
		$headline_accent = (string) ( $cfg['headline_accent'] ?? '' );
		$description     = (string) ( $cfg['description']     ?? '' );
		$cards           = is_array( $cfg['cards'] ?? null ) ? $cfg['cards'] : array();

		// Head block — native widgets inside .popular-head container.
		$head_children = array();
		if ( $eyebrow !== '' ) {
			$head_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => array(
					'title'        => $eyebrow,
					'header_size'  => 'div',
					'align'        => 'left',
					'_title'       => 'Popular eyebrow',
					'_css_classes' => 'eyebrow',
				),
			);
		}
		if ( $headline !== '' ) {
			$title_html = esc_html( $headline );
			if ( $headline_accent !== '' ) {
				$title_html .= ' <em>' . esc_html( $headline_accent ) . '</em>';
			}
			$head_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => array(
					'title'        => $title_html,
					'header_size'  => 'h2',
					'align'        => 'left',
					'_title'       => 'Popular headline',
				),
			);
		}
		if ( $description !== '' ) {
			$head_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => array(
					'editor' => '<p>' . esc_html( $description ) . '</p>',
					'align'  => 'left',
					'_title' => 'Popular description',
				),
			);
		}

		$head_container = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'flex_gap'       => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '14', 'isLinked' => false ),
				'_title'         => 'Popular head',
				'css_classes'    => 'popular-head',
			),
			'children' => $head_children,
		);

		// Cards — 6 native icon-box widgets with design .pop-card class.
		$card_children = array();
		foreach ( $cards as $i => $card ) {
			$icon = (string) ( $card['icon'] ?? 'fa-solid fa-circle' );
			$lib  = strpos( $icon, 'fa-brands' ) === 0 || strpos( $icon, 'fab ' ) === 0 ? 'fa-brands'
				: ( strpos( $icon, 'fa-regular' ) === 0 || strpos( $icon, 'far ' ) === 0 ? 'fa-regular' : 'fa-solid' );
			$tag  = (string) ( $card['tag']  ?? '' );
			$q    = (string) ( $card['question'] ?? '' );
			$a    = (string) ( $card['answer']   ?? '' );
			$link = (string) ( $card['link']     ?? '#' );

			// description_text carries tag + answer + "Read more" — design classes preserved so CSS styles each span individually.
			$desc_html = '<span class="pop-card__tag">' . esc_html( $tag ) . '</span>'
				. '<span class="pop-card__a">' . esc_html( $a ) . '</span>'
				. '<span class="pop-card__more">Read more <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>';

			$card_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'icon-box',
				'settings'    => array(
					'selected_icon'    => array( 'value' => $icon, 'library' => $lib ),
					'title_text'       => $q,
					'description_text' => $desc_html,
					'link'             => array( 'url' => $link, 'is_external' => '', 'nofollow' => '' ),
					'title_size'       => 'h3',
					'position'         => 'top',
					'_title'           => 'Popular card ' . ( $i + 1 ),
					'_css_classes'     => 'pop-card',
				),
			);
		}

		$cards_grid = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'    => 'full',
				'flex_direction'   => 'row',
				'flex_wrap'        => 'wrap',
				'flex_align_items' => 'stretch',
				'flex_gap'         => array( 'unit' => 'px', 'size' => 24, 'column' => '24', 'row' => '24', 'isLinked' => true ),
				'_title'           => 'Popular cards grid',
				'css_classes'      => 'pop-grid',
			),
			'children' => $card_children,
		);

		// Outer section — design `.section` padding via CSS, wrapper container.
		return array(
			'type'     => 'container',
			'settings' => array(
				'content_width'   => 'full',
				'flex_direction'  => 'column',
				'flex_align_items'=> 'center',
				'padding'         => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
				'flex_gap'        => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
				'_title'          => 'Popular section',
				'css_classes'     => 'section',
				'_element_id'     => 'popular',
			),
			'children' => array(
				array(
					'type'     => 'container',
					'settings' => array(
						'content_width'  => 'full',
						'flex_direction' => 'column',
						'flex_gap'       => array( 'unit' => 'px', 'size' => 32, 'column' => '32', 'row' => '32', 'isLinked' => true ),
						'_title'         => 'Popular container',
						'css_classes'    => 'container',
					),
					'children' => array( $head_container, $cards_grid ),
				),
			),
		);
	}
}

/* ═════════════════════════════════════════════════════════════════
   4 · FULL LIBRARY — intro + sidebar aside + categorized accordions
   ═════════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'emcp_faq_pf_library' ) ) {
	function emcp_faq_pf_library( array $intro, array $categories ): ?array {
		if ( empty( $categories ) && empty( $intro ) ) { return null; }

		// Intro head — native widgets inside .section__head wrapper.
		$head_children = array();
		if ( ! empty( $intro['eyebrow'] ) ) {
			$head_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => array(
					'title'        => (string) $intro['eyebrow'],
					'header_size'  => 'div',
					'align'        => 'center',
					'_title'       => 'Library eyebrow',
					'_css_classes' => 'eyebrow',
				),
			);
		}
		if ( ! empty( $intro['heading'] ) ) {
			$lib_title_html = esc_html( $intro['heading'] );
			if ( ! empty( $intro['heading_accent'] ) ) {
				$lib_title_html .= ' <em>' . esc_html( $intro['heading_accent'] ) . '</em>';
			}
			$head_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => array(
					'title'        => $lib_title_html,
					'header_size'  => 'h2',
					'align'        => 'center',
					'_title'       => 'Library headline',
					'_css_classes' => 'section__title',
				),
			);
		}
		if ( ! empty( $intro['subtitle'] ) ) {
			$head_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => array(
					'editor'       => '<p style="text-align:center">' . esc_html( $intro['subtitle'] ) . '</p>',
					'_title'       => 'Library subtitle',
					'_css_classes' => 'section__sub',
				),
			);
		}

		$head_container = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'flex_align_items' => 'center',
				'flex_gap'       => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '12', 'isLinked' => false ),
				'_title'         => 'Library head',
				'css_classes'    => 'section__head',
			),
			'children' => $head_children,
		);

		// Aside jump list — native icon-list with design class .faq-aside.
		$aside_items = array();
		foreach ( $categories as $i => $cat ) {
			$cid = (string) ( $cat['id'] ?? '' );
			$lbl = (string) ( $cat['heading'] ?? '' );
			$cnt = (string) ( $cat['count']   ?? '' );
			$aside_items[] = array(
				'text'          => $lbl . ' · ' . $cnt,
				'link'          => array( 'url' => '#cat-' . $cid, 'is_external' => '', 'nofollow' => '' ),
				'selected_icon' => array( 'value' => '', 'library' => '' ),
				'_id'           => 'as' . $i,
			);
		}
		$aside_widget = array(
			'type'        => 'widget',
			'widget_type' => 'icon-list',
			'settings'    => array(
				'view'          => 'traditional',
				'icon_list'     => $aside_items,
				'icon_align'    => 'left',
				'space_between' => array( 'unit' => 'px', 'size' => 2, 'sizes' => array() ),
				'_title'        => 'FAQ aside jump list',
			),
		);

		$aside_label = array(
			'type'        => 'widget',
			'widget_type' => 'heading',
			'settings'    => array(
				'title'        => 'On this page',
				'header_size'  => 'div',
				'_title'       => 'Aside label',
				'_css_classes' => 'faq-aside__label',
			),
		);

		$aside_col = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'flex_gap'       => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
				'_title'         => 'FAQ aside column',
				'css_classes'    => 'faq-aside',
			),
			'children' => array( $aside_label, $aside_widget ),
		);

		// Categories — each block has icon-box header + native accordion.
		$content_children = array();
		foreach ( $categories as $cat ) {
			$cat_id    = (string) ( $cat['id'] ?? '' );
			$cat_head  = (string) ( $cat['heading'] ?? '' );
			$cat_icon  = (string) ( $cat['icon']    ?? 'fa-solid fa-folder' );
			$cat_count = (string) ( $cat['count_label'] ?? '' );
			$items     = is_array( $cat['items'] ?? null ) ? $cat['items'] : array();
			$data_cat  = (string) ( $cat['data_cat'] ?? $cat_id );
			$icon_lib  = strpos( $cat_icon, 'fa-regular' ) === 0 || strpos( $cat_icon, 'far ' ) === 0 ? 'fa-regular'
				: ( strpos( $cat_icon, 'fa-brands' ) === 0 || strpos( $cat_icon, 'fab ' ) === 0 ? 'fa-brands' : 'fa-solid' );

			// Category header — native icon-box with design .faq-cat__head class.
			$cat_header_widget = array(
				'type'        => 'widget',
				'widget_type' => 'icon-box',
				'settings'    => array(
					'selected_icon'    => array( 'value' => $cat_icon, 'library' => $icon_lib ),
					'title_text'       => $cat_head,
					'description_text' => $cat_count,
					'title_size'       => 'h3',
					'position'         => 'left',
					'_title'           => 'Cat header ' . $cat_id,
					'_css_classes'     => 'faq-cat__head',
				),
			);

			// Accordion — native widget with design-matching styles via .elementor-accordion* selectors.
			$tabs = array();
			foreach ( $items as $it ) {
				$q = (string) ( $it['q'] ?? '' );
				$a = (string) ( $it['a'] ?? '' );
				$tabs[] = array(
					'tab_title'   => $q,
					'tab_content' => $a,
					'_id'         => substr( md5( $cat_id . $q ), 0, 7 ),
				);
			}
			$accordion_widget = array(
				'type'        => 'widget',
				'widget_type' => 'accordion',
				'settings'    => array(
					'tabs'                 => $tabs,
					'selected_icon'        => array( 'value' => 'fas fa-plus', 'library' => 'fa-solid' ),
					'selected_active_icon' => array( 'value' => 'fas fa-minus', 'library' => 'fa-solid' ),
					'title_html_tag'       => 'h4',
					'icon_align'           => 'right',
					'_title'               => 'Cat accordion ' . $cat_id,
				),
			);

			// Category block container — design .faq-cat with data-cat attr for JS filter.
			$content_children[] = array(
				'type'     => 'container',
				'settings' => array(
					'content_width'  => 'full',
					'flex_direction' => 'column',
					'flex_gap'       => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
					'padding'        => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
					'_title'         => 'Category ' . $cat_id,
					'css_classes'    => 'faq-cat',
					'_element_id'    => 'cat-' . $cat_id,
					// data-cat attr is not natively supported — relies on CSS/JS reading _element_id.
				),
				'children' => array( $cat_header_widget, $accordion_widget ),
			);
		}

		$content_col = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'flex_gap'       => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
				'_title'         => 'FAQ content',
				'css_classes'    => 'faq-content',
			),
			'children' => $content_children,
		);

		// Layout — grid 240px sidebar + 1fr content (design .faq-layout).
		$layout_row = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'    => 'full',
				'flex_direction'   => 'row',
				'flex_align_items' => 'flex-start',
				'flex_gap'         => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
				'_title'           => 'FAQ layout row',
				'css_classes'      => 'faq-layout',
			),
			'children' => array( $aside_col, $content_col ),
		);

		return array(
			'type'     => 'container',
			'settings' => array(
				'content_width'    => 'full',
				'flex_direction'   => 'column',
				'flex_align_items' => 'center',
				'padding'          => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
				'flex_gap'         => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
				'_title'           => 'FAQ library section',
				'css_classes'      => 'section faq-library',
				'_element_id'      => 'faq-accordion',
			),
			'children' => array(
				array(
					'type'     => 'container',
					'settings' => array(
						'content_width'  => 'full',
						'flex_direction' => 'column',
						'flex_gap'       => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
						'_title'         => 'FAQ library container',
						'css_classes'    => 'container',
					),
					'children' => array( $head_container, $layout_row ),
				),
			),
		);
	}
}

/* ═════════════════════════════════════════════════════════════════
   5 · TRUST BAND (dark 4-up)
   ═════════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'emcp_faq_pf_trust' ) ) {
	function emcp_faq_pf_trust( array $items ): ?array {
		if ( empty( $items ) ) { return null; }

		$badge_children = array();
		foreach ( $items as $i => $it ) {
			$icon     = (string) ( $it['icon']  ?? 'fa-solid fa-check' );
			$title    = (string) ( $it['title'] ?? '' );
			$subtitle = (string) ( $it['subtitle'] ?? '' );
			$lib      = strpos( $icon, 'fa-regular' ) === 0 || strpos( $icon, 'far ' ) === 0 ? 'fa-regular'
				: ( strpos( $icon, 'fa-brands' ) === 0 || strpos( $icon, 'fab ' ) === 0 ? 'fa-brands' : 'fa-solid' );
			$badge_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'icon-box',
				'settings'    => array(
					'selected_icon'    => array( 'value' => $icon, 'library' => $lib ),
					'title_text'       => $title,
					'description_text' => $subtitle,
					'title_size'       => 'div',
					'position'         => 'left',
					'_title'           => 'Trust ' . ( $i + 1 ),
					'_css_classes'     => 'trust-badge',
				),
			);
		}

		return array(
			'type'     => 'container',
			'settings' => array(
				'content_width'    => 'full',
				'flex_direction'   => 'column',
				'flex_align_items' => 'center',
				'padding'          => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
				'flex_gap'         => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
				'_title'           => 'FAQ trust band',
				'css_classes'      => 'trust-band',
				'_element_id'      => 'trust',
			),
			'children' => array(
				array(
					'type'     => 'container',
					'settings' => array(
						'content_width'    => 'full',
						'flex_direction'   => 'row',
						'flex_wrap'        => 'wrap',
						'flex_align_items' => 'center',
						'flex_gap'         => array( 'unit' => 'px', 'size' => 32, 'column' => '32', 'row' => '32', 'isLinked' => true ),
						'_title'           => 'Trust grid',
						'css_classes'      => 'container trust-grid',
					),
					'children' => $badge_children,
				),
			),
		);
	}
}

/* ═════════════════════════════════════════════════════════════════
   6 · FINAL CTA
   ═════════════════════════════════════════════════════════════════ */
if ( ! function_exists( 'emcp_faq_pf_final_cta' ) ) {
	function emcp_faq_pf_final_cta( array $cfg ): ?array {
		if ( empty( $cfg ) ) { return null; }

		$eyebrow         = (string) ( $cfg['eyebrow']         ?? '' );
		$headline        = (string) ( $cfg['headline']        ?? '' );
		$headline_accent = (string) ( $cfg['headline_accent'] ?? '' );
		$subtitle        = (string) ( $cfg['subtitle']        ?? '' );
		$primary_label   = (string) ( $cfg['primary_label']   ?? '' );
		$primary_url     = (string) ( $cfg['primary_url']     ?? '' );
		$primary_icon    = (string) ( $cfg['primary_icon']    ?? 'fa-brands fa-whatsapp' );
		$secondary_label = (string) ( $cfg['secondary_label'] ?? '' );
		$secondary_url   = (string) ( $cfg['secondary_url']   ?? '' );
		$secondary_icon  = (string) ( $cfg['secondary_icon']  ?? 'fa-solid fa-phone' );
		$note            = (string) ( $cfg['note']            ?? '' );
		$bg_image_url    = (string) ( $cfg['bg_image_url']    ?? '' );

		$inner_children = array();
		if ( $eyebrow !== '' ) {
			$inner_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => array(
					'title'        => $eyebrow,
					'header_size'  => 'div',
					'align'        => 'center',
					'_title'       => 'Final CTA eyebrow',
					'_css_classes' => 'eyebrow eyebrow--on-dark',
				),
			);
		}
		if ( $headline !== '' ) {
			$cta_title_html = esc_html( $headline );
			if ( $headline_accent !== '' ) {
				$cta_title_html .= ' <em>' . esc_html( $headline_accent ) . '</em>';
			}
			$inner_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => array(
					'title'        => $cta_title_html,
					'header_size'  => 'h2',
					'align'        => 'center',
					'_title'       => 'Final CTA headline',
					'_css_classes' => 'final-cta__title',
				),
			);
		}
		if ( $subtitle !== '' ) {
			$inner_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => array(
					'editor'       => '<p style="text-align:center">' . esc_html( $subtitle ) . '</p>',
					'_title'       => 'Final CTA subtitle',
					'_css_classes' => 'final-cta__sub',
				),
			);
		}

		// Buttons row.
		$btn_children = array();
		if ( $primary_label !== '' && $primary_url !== '' ) {
			$p_lib = strpos( $primary_icon, 'fab ' ) === 0 || strpos( $primary_icon, 'fa-brands' ) === 0 ? 'fa-brands' : 'fa-solid';
			$btn_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'button',
				'settings'    => array(
					'text'          => $primary_label,
					'link'          => array( 'url' => $primary_url, 'is_external' => 'on', 'nofollow' => '' ),
					'size'          => 'lg',
					'selected_icon' => array( 'value' => $primary_icon, 'library' => $p_lib ),
					'icon_align'    => 'left',
					'_title'        => 'Final CTA primary',
					'_css_classes'  => 'btn btn--whatsapp btn--lg',
				),
			);
		}
		if ( $secondary_label !== '' && $secondary_url !== '' ) {
			$s_lib = strpos( $secondary_icon, 'fab ' ) === 0 || strpos( $secondary_icon, 'fa-brands' ) === 0 ? 'fa-brands' : 'fa-solid';
			$btn_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'button',
				'settings'    => array(
					'text'          => $secondary_label,
					'link'          => array( 'url' => $secondary_url, 'is_external' => '', 'nofollow' => '' ),
					'size'          => 'lg',
					'selected_icon' => array( 'value' => $secondary_icon, 'library' => 'fa-solid' ),
					'icon_align'    => 'left',
					'_title'        => 'Final CTA secondary',
					'_css_classes'  => 'btn btn--primary btn--lg',
				),
			);
		}
		if ( ! empty( $btn_children ) ) {
			$inner_children[] = array(
				'type'     => 'container',
				'settings' => array(
					'content_width'        => 'full',
					'flex_direction'       => 'row',
					'flex_wrap'            => 'wrap',
					'flex_align_items'     => 'center',
					'flex_justify_content' => 'center',
					'flex_gap'             => array( 'unit' => 'px', 'size' => 14, 'column' => '14', 'row' => '14', 'isLinked' => true ),
					'_title'               => 'Final CTA buttons row',
					'css_classes'          => 'final-cta__actions',
				),
				'children' => $btn_children,
			);
		}
		if ( $note !== '' ) {
			$inner_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => array(
					'title'        => $note,
					'header_size'  => 'div',
					'align'        => 'center',
					'_title'       => 'Final CTA note',
					'_css_classes' => 'final-cta__note',
				),
			);
		}

		$inner_col = array(
			'type'     => 'container',
			'settings' => array(
				'content_width'    => 'full',
				'flex_direction'   => 'column',
				'flex_align_items' => 'center',
				'flex_gap'         => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0', 'isLinked' => true ),
				'_title'           => 'Final CTA inner column',
				'css_classes'      => 'final-cta__inner',
			),
			'children' => $inner_children,
		);

		$outer = array(
			'content_width'         => 'full',
			'flex_direction'        => 'column',
			'flex_align_items'      => 'center',
			'flex_justify_content'  => 'center',
			'padding'               => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
			'background_background' => 'classic',
			'_title'                => 'FAQ final CTA',
			'css_classes'           => 'final-cta',
			'_element_id'           => 'final-cta',
		);
		if ( $bg_image_url !== '' ) {
			$outer['background_image']    = array( 'url' => $bg_image_url, 'id' => 0, 'alt' => 'Desert twilight' );
			$outer['background_position'] = 'center center';
			$outer['background_repeat']   = 'no-repeat';
			$outer['background_size']     = 'cover';
		}

		return array(
			'type'     => 'container',
			'settings' => $outer,
			'children' => array( $inner_col ),
		);
	}
}

if ( ! function_exists( 'emcp_pattern_faq_page_full_meta' ) ) {
	function emcp_pattern_faq_page_full_meta(): array {
		return array(
			'category'    => 'faq',
			'description' => 'Full FAQ landing page per Claude design spec — hero (breadcrumb+search+pills), sticky tabs, 3×2 popular cards, full accordion library with aside index, dark trust band, final CTA. Design BEM classes preserved (.hero__*, .pop-card, .faq-cat, .trust-badge, .final-cta__*) so external faq-page.css drops in unchanged.',
			'slots'       => array(
				'hero'          => 'array { breadcrumb[], eyebrow, headline, headline_accent, subtitle, bg_image_url, search_placeholder, popular_tags[] }',
				'tabs'          => 'array of { id, label, count, icon }',
				'popular'       => 'array { eyebrow, headline, headline_accent, description, cards[] }',
				'library_intro' => 'array { eyebrow, heading, heading_accent, subtitle }',
				'categories'    => 'array of { id, heading, icon, count, count_label, data_cat?, items[] }',
				'trust'         => 'array of { icon, title, subtitle }',
				'final_cta'     => 'array { eyebrow, headline, headline_accent, subtitle, primary_label, primary_url, primary_icon, secondary_*, note, bg_image_url }',
			),
		);
	}
}
