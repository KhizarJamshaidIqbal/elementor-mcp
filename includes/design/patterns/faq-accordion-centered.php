<?php
/**
 * Pattern: faq.accordion-centered
 *
 * Centered FAQ accordion with plus/minus icon affordance.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_faq_accordion_centered' ) ) {
	function emcp_pattern_faq_accordion_centered( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$heading = (string) ( $slots['heading'] ?? 'Frequently Asked Questions' );
		$subhead = (string) ( $slots['subhead'] ?? '' );
		$items   = is_array( $slots['items'] ?? null ) ? $slots['items'] : array();

		$tabs = array();
		foreach ( $items as $it ) {
			$tabs[] = array(
				'tab_title'   => (string) ( $it['q'] ?? '' ),
				'tab_content' => '<p>' . esc_html( (string) ( $it['a'] ?? '' ) ) . '</p>',
				'_id'         => substr( md5( (string) ( $it['q'] ?? '' ) ), 0, 7 ),
			);
		}

		$accordion_widget = array(
			'type'        => 'widget',
			'widget_type' => 'accordion',
			'settings'    => emcp_array_deep_merge(
				array(
					'_title'                => 'FAQ accordion',
					'tabs'                  => $tabs,
					'selected_icon'         => array( 'value' => 'fas fa-plus', 'library' => 'fa-solid' ),
					'selected_active_icon'  => array( 'value' => 'fas fa-minus', 'library' => 'fa-solid' ),
					'title_html_tag'        => 'h3',
				),
				$resolver->typography( 'heading-sm' ),
				$resolver->color( 'title_color', 'text' ),
				$resolver->color( 'tab_active_color', 'accent' )
			),
		);

		$top_children = array(
			array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $heading, 'align' => 'center', 'header_size' => 'h2', '_title' => 'FAQ heading' ),
					$resolver->typography( 'heading-xl' ),
					$resolver->color( 'title_color', 'text' )
				),
			),
		);
		if ( $subhead !== '' ) {
			$top_children[] = array(
				'type'        => 'widget',
				'widget_type' => 'text-editor',
				'settings'    => emcp_array_deep_merge(
					array( 'editor' => '<p style="text-align:center">' . esc_html( $subhead ) . '</p>', '_title' => 'FAQ subhead' ),
					$resolver->typography( 'body-lg' ),
					$resolver->color( 'color', 'text-muted' )
				),
			);
		}

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.lg' ),
				array(
					'flex_direction'        => 'column',
					'align_items'           => 'center',
					'content_width'         => 'boxed',
					'_title'                => 'FAQ accordion centered',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface' ),
				$resolver->gap( 32 )
			),
			'children' => array_merge(
				$top_children,
				array(
					array(
						'type'     => 'container',
						'settings' => array(
							'content_width'  => 'full',
							'width'          => array( 'unit' => 'px', 'size' => 820 ),
							'flex_direction' => 'column',
							'_title'         => 'FAQ column',
						),
						'children' => array( $accordion_widget ),
					),
				)
			),
		);
	}
}

if ( ! function_exists( 'emcp_pattern_faq_accordion_centered_meta' ) ) {
	function emcp_pattern_faq_accordion_centered_meta(): array {
		return array(
			'category'    => 'faq',
			'description' => 'Centered FAQ accordion with plus/minus icon.',
			'slots'       => array(
				'heading' => 'string',
				'subhead' => 'string',
				'items'   => 'array<{q,a}>',
			),
		);
	}
}
