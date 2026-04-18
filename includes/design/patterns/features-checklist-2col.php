<?php
/**
 * Pattern: features.checklist-2col
 *
 * Two-column checklist — green-check icon + label per line.
 * Good for inclusions, feature comparisons.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_pattern_features_checklist_2col' ) ) {
	function emcp_pattern_features_checklist_2col( array $slots, Elementor_MCP_Token_Resolver $resolver ): array {
		$heading = (string) ( $slots['heading'] ?? '' );
		$items   = is_array( $slots['items'] ?? null ) ? $slots['items'] : array();

		$half  = (int) ceil( count( $items ) / 2 );
		$col_a = array_slice( $items, 0, $half );
		$col_b = array_slice( $items, $half );

		$build_col = function ( array $texts ) use ( $resolver ): array {
			$list_items = array();
			foreach ( $texts as $t ) {
				$list_items[] = array(
					'text'          => (string) $t,
					'selected_icon' => array( 'value' => 'fas fa-check', 'library' => 'fa-solid' ),
					'_id'           => substr( md5( (string) $t ), 0, 7 ),
				);
			}
			return array(
				'type'        => 'widget',
				'widget_type' => 'icon-list',
				'settings'    => emcp_array_deep_merge(
					array(
						'icon_list'       => $list_items,
						'view'            => 'default',
						'icon_align'      => 'left',
						'icon_self_align' => 'center',
						'space_between'   => array( 'unit' => 'px', 'size' => 16, 'sizes' => array() ),
						'text_indent'     => array( 'unit' => 'px', 'size' => 12, 'sizes' => array() ),
						'_title'          => 'Checklist',
					),
					$resolver->typography( 'body-md' ),
					$resolver->color( 'text_color', 'text' ),
					$resolver->color( 'icon_color', 'accent' )
				),
			);
		};

		$children = array();
		if ( $heading !== '' ) {
			$children[] = array(
				'type'        => 'widget',
				'widget_type' => 'heading',
				'settings'    => emcp_array_deep_merge(
					array( 'title' => $heading, 'align' => 'center', 'header_size' => 'h2', '_title' => 'Checklist heading' ),
					$resolver->typography( 'heading-xl' ),
					$resolver->color( 'title_color', 'text' )
				),
			);
		}
		$children[] = array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				array( 'content_width' => 'full', 'flex_direction' => 'row', '_title' => 'Checklist cols' ),
				$resolver->gap( 40 )
			),
			'children' => array(
				array(
					'type'     => 'container',
					'settings' => array( 'content_width' => 'full', 'flex_direction' => 'column', '_title' => 'Checklist col A' ),
					'children' => array( $build_col( $col_a ) ),
				),
				array(
					'type'     => 'container',
					'settings' => array( 'content_width' => 'full', 'flex_direction' => 'column', '_title' => 'Checklist col B' ),
					'children' => array( $build_col( $col_b ) ),
				),
			),
		);

		return array(
			'type'     => 'container',
			'settings' => emcp_array_deep_merge(
				$resolver->spacing( 'section.md' ),
				array(
					'flex_direction'        => 'column',
					'align_items'           => 'center',
					'content_width'         => 'boxed',
					'_title'                => 'Checklist 2col',
					'background_background' => 'classic',
				),
				$resolver->color( 'background_color', 'surface' ),
				$resolver->gap( 32 )
			),
			'children' => $children,
		);
	}
}

if ( ! function_exists( 'emcp_pattern_features_checklist_2col_meta' ) ) {
	function emcp_pattern_features_checklist_2col_meta(): array {
		return array(
			'category'    => 'features',
			'description' => 'Two-column checklist with green-check icons. Classic inclusions list.',
			'slots'       => array(
				'heading' => 'string',
				'items'   => 'array<string>',
			),
		);
	}
}
