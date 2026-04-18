<?php
/**
 * Design Compiler — converts a high-level Design IR into a build-page
 * payload compatible with Elementor_MCP_Composite_Abilities.
 *
 * Pipeline per compile:
 *   1. Bind palette to Elementor Kit (Kit_Binder) → globals map
 *   2. Configure Token_Resolver (palette + typography + globals)
 *   3. For each section: resolve *_image_query slots via stock images,
 *      load pattern callback, invoke with slots + resolver, collect output
 *   4. Return {title, status, post_type, page_settings, structure} ready
 *      for Composite_Abilities::execute_build_page()
 *
 * Compilation is pure aside from palette binding and optional image
 * sideload. Actual post creation happens downstream.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compiles Design IR to build-page payload.
 *
 * @since 1.5.0
 */
class Elementor_MCP_Design_Compiler {

	/**
	 * @var Elementor_MCP_Kit_Binder
	 */
	private $kit_binder;

	/**
	 * @var Elementor_MCP_Pattern_Registry
	 */
	private $registry;

	/**
	 * Stock image abilities instance (for bg_image_query resolution). Optional.
	 *
	 * @var object|null
	 */
	private $stock_images;

	/**
	 * Constructor.
	 */
	public function __construct(
		Elementor_MCP_Kit_Binder $kit_binder,
		Elementor_MCP_Pattern_Registry $registry,
		$stock_images = null
	) {
		$this->kit_binder   = $kit_binder;
		$this->registry     = $registry;
		$this->stock_images = $stock_images;
	}

	/**
	 * Compiles a Design IR to a build-page payload.
	 *
	 * @param array $ir Design IR.
	 * @return array|\WP_Error Payload or error.
	 */
	public function compile( array $ir ) {
		$title = sanitize_text_field( $ir['title'] ?? '' );
		if ( empty( $title ) ) {
			return new \WP_Error( 'emcp_design_missing_title', __( 'Design IR requires a title.', 'elementor-mcp' ) );
		}

		$sections = $ir['sections'] ?? array();
		if ( empty( $sections ) || ! is_array( $sections ) ) {
			return new \WP_Error( 'emcp_design_missing_sections', __( 'Design IR requires at least one section.', 'elementor-mcp' ) );
		}

		$brand          = is_array( $ir['brand_tokens'] ?? null ) ? $ir['brand_tokens'] : array();
		$palette_name   = sanitize_key( $brand['palette'] ?? '' );
		$typography_key = sanitize_key( $brand['typography'] ?? '' );

		$color_globals = $palette_name ? $this->kit_binder->bind_palette( $palette_name ) : array();

		$resolver = Elementor_MCP_Token_Resolver::instance();
		$resolver->configure( $palette_name, $typography_key, $color_globals );

		$structure = array();

		foreach ( $sections as $index => $section ) {
			$name  = sanitize_text_field( $section['pattern'] ?? '' );
			$slots = is_array( $section['slots'] ?? null ) ? $section['slots'] : array();

			if ( empty( $name ) ) {
				return new \WP_Error(
					'emcp_design_missing_pattern',
					sprintf(
						/* translators: %d: section index */
						__( 'Section %d has no pattern name.', 'elementor-mcp' ),
						(int) $index
					)
				);
			}

			$descriptor = $this->registry->get( $name );
			if ( null === $descriptor ) {
				return new \WP_Error(
					'emcp_design_unknown_pattern',
					sprintf(
						/* translators: %s: pattern name */
						__( 'Unknown pattern "%s". Use list-patterns to see the catalog.', 'elementor-mcp' ),
						$name
					)
				);
			}

			$slots = $this->resolve_image_slots( $slots );

			$fragment = call_user_func( $descriptor['callback'], $slots, $resolver );
			if ( is_wp_error( $fragment ) ) {
				return $fragment;
			}
			if ( empty( $fragment ) || ! is_array( $fragment ) ) {
				continue;
			}

			// Pattern may return single element or array of elements.
			if ( isset( $fragment['type'] ) ) {
				$structure[] = $fragment;
			} else {
				foreach ( $fragment as $node ) {
					if ( is_array( $node ) && isset( $node['type'] ) ) {
						$structure[] = $node;
					}
				}
			}
		}

		return array(
			'title'         => $title,
			'status'        => sanitize_key( $ir['status'] ?? 'draft' ),
			'post_type'     => sanitize_key( $ir['post_type'] ?? 'page' ),
			'page_settings' => is_array( $ir['page_settings'] ?? null ) ? $ir['page_settings'] : array(),
			'structure'     => $structure,
		);
	}

	/**
	 * Resolves *_image_query slots by searching + sideloading images.
	 * For each key ending in `_image_query` with no corresponding
	 * `_image` present, searches stock images, sideloads the first
	 * match, and replaces slot with the resolved image array.
	 *
	 * @param array $slots
	 * @return array
	 */
	private function resolve_image_slots( array $slots ): array {
		if ( ! $this->stock_images ) {
			return $slots;
		}

		foreach ( $slots as $key => $value ) {
			if ( ! is_string( $value ) || '' === $value ) {
				continue;
			}
			if ( substr( $key, -12 ) !== '_image_query' ) {
				continue;
			}

			$image_key = substr( $key, 0, -6 );
			if ( ! empty( $slots[ $image_key ] ) ) {
				continue;
			}

			$resolved = $this->resolve_single_image( $value );
			if ( null !== $resolved ) {
				$slots[ $image_key ] = $resolved;
			}
		}

		return $slots;
	}

	/**
	 * Runs a single image search + sideload. Returns image array or null on failure.
	 *
	 * @param string $query
	 * @return array|null
	 */
	private function resolve_single_image( string $query ): ?array {
		if ( ! method_exists( $this->stock_images, 'execute_search_images' ) ) {
			return null;
		}
		if ( ! method_exists( $this->stock_images, 'execute_sideload_image' ) ) {
			return null;
		}

		$search = $this->stock_images->execute_search_images( array( 'query' => $query, 'per_page' => 1 ) );
		if ( is_wp_error( $search ) || empty( $search['results'][0]['url'] ) ) {
			return null;
		}

		$sideload = $this->stock_images->execute_sideload_image(
			array(
				'url'   => $search['results'][0]['url'],
				'title' => $query,
				'alt'   => $search['results'][0]['title'] ?? $query,
			)
		);
		if ( is_wp_error( $sideload ) || empty( $sideload['url'] ) ) {
			return null;
		}

		return array(
			'url' => $sideload['url'],
			'id'  => (int) ( $sideload['attachment_id'] ?? $sideload['id'] ?? 0 ),
			'alt' => $search['results'][0]['title'] ?? $query,
		);
	}
}
