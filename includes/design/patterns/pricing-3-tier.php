<?php
/**
 * Pattern: pricing.3-tier
 *
 * 3-column pricing. Middle tier featured (brand-primary bg, large shadow).
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_pricing_3_tier' ) ) {
	function emcp_pattern_pricing_3_tier( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$heading = (string) ( $slots['heading'] ?? 'Pricing' );
		$subhead = (string) ( $slots['subhead'] ?? '' );
		$tiers   = is_array( $slots['tiers'] ?? null ) ? $slots['tiers'] : array();

		$cards = array();
		foreach ( $tiers as $tier ) {
			$is_featured = ! empty( $tier['featured'] );
			$name        = (string) ( $tier['name'] ?? '' );
			$price       = (string) ( $tier['price'] ?? '' );
			$period      = (string) ( $tier['period'] ?? '' );
			$cta_label   = (string) ( $tier['cta_label'] ?? 'Choose' );
			$cta_url     = (string) ( $tier['cta_url'] ?? '#' );
			$features    = is_array( $tier['features'] ?? null ) ? $tier['features'] : array();

			$feat_list = array();
			foreach ( $features as $f ) {
				$feat_list[] = array(
					'text'          => (string) $f,
					'selected_icon' => array( 'value' => 'fas fa-check', 'library' => 'fa-solid' ),
					'_id'           => substr( md5( (string) $f ), 0, 7 ),
				);
			}

			$card_settings = emcp_array_deep_merge(
				array(
					'content_width'         => 'full',
					'flex_direction'        => 'column',
					'_title'                => 'Pricing tier: ' . $name,
					'padding'               => array( 'unit' => 'px', 'top' => '40', 'right' => '32', 'bottom' => '40', 'left' => '32', 'isLinked' => false ),
					'background_background' => 'classic',
				),
				$resolver->radius( 'card' ),
				$resolver->shadow( $is_featured ? 'large' : 'soft' ),
				$is_featured
					? $resolver->color( 'background_color', 'primary' )
					: $resolver->color( 'background_color', 'surface' ),
				$resolver->gap( 18 )
			);

			$cards[] = array(
				'type'     => 'container',
				'settings' => $card_settings,
				'children' => array(
					array(
						'type'        => 'widget',
						'widget_type' => 'heading',
						'settings'    => emcp_array_deep_merge(
							array( 'title' => $name, 'align' => 'center', 'header_size' => 'h3', '_title' => 'Tier name' ),
							$resolver->typography( 'heading-md' ),
							$resolver->color( 'title_color', $is_featured ? 'text-inverse' : 'text' )
						),
					),
					array(
						'type'        => 'widget',
						'widget_type' => 'heading',
						'settings'    => emcp_array_deep_merge(
							array( 'title' => $price . ( $period !== '' ? ' <small>' . $period . '</small>' : '' ), 'align' => 'center', 'header_size' => 'div', '_title' => 'Price' ),
							$resolver->typography( 'display-lg' ),
							$resolver->color( 'title_color', $is_featured ? 'text-inverse' : 'accent' )
						),
					),
					array(
						'type'        => 'widget',
						'widget_type' => 'icon-list',
						'settings'    => emcp_array_deep_merge(
							array(
								'icon_list'     => $feat_list,
								'view'          => 'default',
								'icon_align'    => 'left',
								'space_between' => array( 'unit' => 'px', 'size' => 14, 'sizes' => array() ),
								'text_indent'   => array( 'unit' => 'px', 'size' => 10, 'sizes' => array() ),
								'_title'        => 'Tier features',
							),
							$resolver->typography( 'body-md' ),
							$resolver->color( 'text_color', $is_featured ? 'text-inverse' : 'text' ),
							$resolver->color( 'icon_color', $is_featured ? 'text-inverse' : 'accent' )
						),
					),
					array(
						'type'        => 'widget',
						'widget_type' => 'button',
						'settings'    => emcp_array_deep_merge(
							array(
								'text'   => $cta_label,
								'link'   => array( 'url' => esc_url_raw( $cta_url ), 'is_external' => '', 'nofollow' => '' ),
								'align'  => 'center',
								'_title' => 'Tier CTA',
							),
							$resolver->button( $is_featured ? 'secondary-md' : 'outline-md' )
						),
					),
				),
			);
		}

		$children = array(
			array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $heading, 'align' => 'center', 'header_size' => 'h2', '_title' => 'Pricing heading' ),
					$resolver->typography( 'heading-xl' ),
					$resolver->color( 'title_color', 'text' )
				),
			),
		);
		if ( $subhead !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => emcp_array_deep_merge(
					array( 'editor' => '<p style="text-align:center">' . esc_html( $subhead ) . '</p>', '_title' => 'Pricing subhead' ),
					$resolver->typography( 'body-lg' ),
					$resolver->color( 'color', 'text-muted' )
				),
			);
		}
		$children[] = array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				array( 'content_width' => 'full', 'flex_direction' => 'row', '_title' => 'Pricing row', 'flex_align_items' => 'stretch' ),
				$resolver->gap( 32 )
			),
			'children' => $cards,
		);

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.lg' ),
				array(
					'flex_direction'        => 'column',
					'align_items'           => 'center',
					'content_width'         => 'boxed',
					'_title'                => 'Pricing 3-tier',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface-alt' ),
				$resolver->gap( 40 )
			),
			'children' => $children,
		);
	}
}

if ( ! function_exists( 'emcp_pattern_pricing_3_tier_meta' ) ) {
	function emcp_pattern_pricing_3_tier_meta(): array {
		return array(
			'category'    => 'pricing',
			'description' => '3-column pricing. Middle tier featured (primary bg, large shadow).',
			'slots'       => array(
				'heading' => 'string',
				'subhead' => 'string',
				'tiers'   => 'array<{name,price,period,featured,features:array<string>,cta_label,cta_url}>',
			),
		);
	}
}
