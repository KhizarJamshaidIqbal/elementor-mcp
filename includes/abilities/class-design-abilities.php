<?php
/**
 * Design abilities — Native-Design Pipeline MCP tools.
 *
 * Registers 6 MCP tools:
 *   - elementor-mcp/list-patterns         → catalog of registered patterns
 *   - elementor-mcp/preview-pattern       → resolve one pattern to JSON (no save)
 *   - elementor-mcp/design-page           → compile Design IR + save via build-page
 *   - elementor-mcp/apply-design-to-page  → apply Design IR to existing page
 *   - elementor-mcp/design-theme-template → create Elementor Pro Theme Builder template
 *   - elementor-mcp/import-design         → convert any HTML → native Elementor page
 *
 * In Phase 0 (no patterns registered yet) list-patterns returns an
 * empty list; preview-pattern and design-page return structured errors
 * guiding the AI to ship patterns before expecting output. All six
 * abilities register cleanly against the MCP Adapter and pass schema
 * discovery.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the design abilities.
 *
 * @since 1.5.0
 */
class Elementor_MCP_Design_Abilities {

	/**
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * @var Elementor_MCP_Element_Factory
	 */
	private $factory;

	/**
	 * @var Elementor_MCP_Composite_Abilities
	 */
	private $composite;

	/**
	 * @var Elementor_MCP_Design_Compiler
	 */
	private $compiler;

	/**
	 * @var Elementor_MCP_Pattern_Registry
	 */
	private $registry;

	/**
	 * @var Elementor_MCP_Design_Importer|null
	 */
	private $importer;

	/**
	 * Constructor.
	 */
	public function __construct(
		Elementor_MCP_Data $data,
		Elementor_MCP_Element_Factory $factory,
		Elementor_MCP_Composite_Abilities $composite,
		Elementor_MCP_Design_Compiler $compiler,
		Elementor_MCP_Pattern_Registry $registry,
		?Elementor_MCP_Design_Importer $importer = null
	) {
		$this->data      = $data;
		$this->factory   = $factory;
		$this->composite = $composite;
		$this->compiler  = $compiler;
		$this->registry  = $registry;
		$this->importer  = $importer;
	}

	/**
	 * Ability names registered by this class.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'elementor-mcp/list-patterns',
			'elementor-mcp/preview-pattern',
			'elementor-mcp/design-page',
			'elementor-mcp/apply-design-to-page',
			'elementor-mcp/design-theme-template',
			'elementor-mcp/import-design',
		);
	}

	/**
	 * Registers all design abilities.
	 */
	public function register(): void {
		$this->register_list_patterns();
		$this->register_preview_pattern();
		$this->register_design_page();
		$this->register_apply_design_to_page();
		$this->register_design_theme_template();
		$this->register_import_design();
	}

	/**
	 * Permission check — page creation.
	 */
	public function check_create_permission(): bool {
		return current_user_can( 'publish_pages' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * Permission check — editing an existing page.
	 */
	public function check_edit_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	// -------------------------------------------------------------------------
	// list-patterns
	// -------------------------------------------------------------------------

	private function register_list_patterns(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/list-patterns',
			array(
				'label'               => __( 'List Design Patterns', 'elementor-mcp' ),
				'description'         => __( 'Returns the catalog of registered design patterns with slot schemas. Use this to discover what patterns exist before calling design-page.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_list_patterns' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'category' => array(
							'type'        => 'string',
							'description' => __( 'Optional category filter (hero, features, cta, etc).', 'elementor-mcp' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'count'    => array( 'type' => 'integer' ),
						'patterns' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name'        => array( 'type' => 'string' ),
									'category'    => array( 'type' => 'string' ),
									'description' => array( 'type' => 'string' ),
									'slots'       => array( 'type' => 'object' ),
								),
							),
						),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * list-patterns handler.
	 */
	public function execute_list_patterns( $input ) {
		$category_filter = isset( $input['category'] ) ? sanitize_key( $input['category'] ) : '';
		$all             = $this->registry->all();
		$items           = array();

		foreach ( $all as $pattern ) {
			if ( $category_filter && ( $pattern['category'] ?? '' ) !== $category_filter ) {
				continue;
			}
			$items[] = array(
				'name'        => (string) $pattern['name'],
				'category'    => (string) ( $pattern['category'] ?? '' ),
				'description' => (string) ( $pattern['description'] ?? '' ),
				'slots'       => is_array( $pattern['slots'] ?? null ) && ! empty( $pattern['slots'] )
					? $pattern['slots']
					: new \stdClass(),
			);
		}

		return array(
			'count'    => count( $items ),
			'patterns' => $items,
		);
	}

	// -------------------------------------------------------------------------
	// preview-pattern
	// -------------------------------------------------------------------------

	private function register_preview_pattern(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/preview-pattern',
			array(
				'label'               => __( 'Preview Design Pattern', 'elementor-mcp' ),
				'description'         => __( 'Resolves a single pattern against provided slots and returns the compiled Elementor fragment WITHOUT saving. Use to iterate on slot values before calling design-page.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_preview_pattern' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'pattern'      => array( 'type' => 'string', 'description' => __( 'Pattern name (e.g. hero.overlay-waves).', 'elementor-mcp' ) ),
						'slots'        => array( 'type' => 'object', 'description' => __( 'Pattern slots.', 'elementor-mcp' ) ),
						'brand_tokens' => array(
							'type'       => 'object',
							'properties' => array(
								'palette'    => array( 'type' => 'string' ),
								'typography' => array( 'type' => 'string' ),
							),
						),
					),
					'required'   => array( 'pattern' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'pattern'  => array( 'type' => 'string' ),
						'fragment' => array( 'type' => 'array' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * preview-pattern handler.
	 */
	public function execute_preview_pattern( $input ) {
		$ir = array(
			'title'        => 'Preview',
			'sections'     => array(
				array(
					'pattern' => $input['pattern'] ?? '',
					'slots'   => $input['slots'] ?? array(),
				),
			),
			'brand_tokens' => $input['brand_tokens'] ?? array(),
		);

		$payload = $this->compiler->compile( $ir );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return array(
			'pattern'  => (string) ( $input['pattern'] ?? '' ),
			'fragment' => $payload['structure'],
		);
	}

	// -------------------------------------------------------------------------
	// design-page
	// -------------------------------------------------------------------------

	private function register_design_page(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/design-page',
			array(
				'label'               => __( 'Design Page', 'elementor-mcp' ),
				'description'         => __( 'Compiles a Design IR (high-level page description with patterns + slots + brand tokens) into a native Elementor page. Binds palette/typography to the active kit, resolves image queries via stock search, runs the pattern library, and saves via build-page. Prefer this over add-* widget tools for full-page creation.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_design_page' ),
				'permission_callback' => array( $this, 'check_create_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'         => array( 'type' => 'string' ),
						'status'        => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ) ),
						'post_type'     => array( 'type' => 'string', 'enum' => array( 'page', 'post' ) ),
						'brand_tokens'  => array(
							'type'       => 'object',
							'properties' => array(
								'palette'    => array( 'type' => 'string' ),
								'typography' => array( 'type' => 'string' ),
							),
						),
						'page_settings' => array( 'type' => 'object' ),
						'sections'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'pattern' => array( 'type' => 'string' ),
									'slots'   => array( 'type' => 'object' ),
								),
								'required' => array( 'pattern' ),
							),
						),
					),
					'required'   => array( 'title', 'sections' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'          => array( 'type' => 'integer' ),
						'title'            => array( 'type' => 'string' ),
						'edit_url'         => array( 'type' => 'string' ),
						'preview_url'      => array( 'type' => 'string' ),
						'elements_created' => array( 'type' => 'integer' ),
						'palette_bound'    => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * design-page handler.
	 */
	public function execute_design_page( $input ) {
		$payload = $this->compiler->compile( is_array( $input ) ? $input : array() );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$result = $this->composite->execute_build_page( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->flush_elementor_cache();

		return array_merge(
			(array) $result,
			array( 'palette_bound' => (string) ( $input['brand_tokens']['palette'] ?? '' ) )
		);
	}

	// -------------------------------------------------------------------------
	// apply-design-to-page
	// -------------------------------------------------------------------------

	private function register_apply_design_to_page(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/apply-design-to-page',
			array(
				'label'               => __( 'Apply Design to Existing Page', 'elementor-mcp' ),
				'description'         => __( 'Compiles a Design IR and replaces the _elementor_data on an existing page. Keeps the post ID, URL, and slug. Destructive — overwrites current content. Use for redesigning a page without creating a new URL.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_apply_design_to_page' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'      => array( 'type' => 'integer' ),
						'brand_tokens' => array(
							'type'       => 'object',
							'properties' => array(
								'palette'    => array( 'type' => 'string' ),
								'typography' => array( 'type' => 'string' ),
							),
						),
						'sections'     => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'pattern' => array( 'type' => 'string' ),
									'slots'   => array( 'type' => 'object' ),
								),
								'required' => array( 'pattern' ),
							),
						),
					),
					'required'   => array( 'post_id', 'sections' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'           => array( 'type' => 'integer' ),
						'edit_url'          => array( 'type' => 'string' ),
						'preview_url'       => array( 'type' => 'string' ),
						'elements_replaced' => array( 'type' => 'integer' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * apply-design-to-page handler.
	 */
	public function execute_apply_design_to_page( $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'emcp_invalid_post', __( 'Valid post_id required.', 'elementor-mcp' ) );
		}
		$post_type = get_post_type( $post_id );
		if ( 'page' !== $post_type && 'post' !== $post_type ) {
			return new \WP_Error( 'emcp_invalid_post_type', __( 'post_id must reference a page or post.', 'elementor-mcp' ) );
		}

		$ir = array(
			'title'        => get_the_title( $post_id ),
			'sections'     => is_array( $input['sections'] ?? null ) ? $input['sections'] : array(),
			'brand_tokens' => $input['brand_tokens'] ?? array(),
		);

		$payload = $this->compiler->compile( $ir );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$elements = $this->realize_structure( $payload['structure'] );
		$result   = $this->data->save_page_data( $post_id, $elements );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->flush_elementor_cache();

		return array(
			'post_id'           => $post_id,
			'edit_url'          => admin_url( 'post.php?post=' . $post_id . '&action=elementor' ),
			'preview_url'       => (string) get_permalink( $post_id ),
			'elements_replaced' => count( $elements ),
		);
	}

	/**
	 * Converts declarative structure array (same format build-page consumes)
	 * into realized Elementor element tree. Mirrors Composite_Abilities' internal
	 * flex layout logic so we can save directly to an existing page without
	 * creating a new post.
	 */
	private function realize_structure( array $items ): array {
		return $this->build_recursive( $items, false, '' );
	}

	/**
	 * Mirror of Composite_Abilities::build_elements.
	 */
	private function build_recursive( array $items, bool $is_inner, string $parent_direction ): array {
		$elements    = array();
		$is_in_row   = ( 'row' === $parent_direction || 'row-reverse' === $parent_direction );
		$child_count = count( $items );
		$equal_width = ( $is_in_row && $child_count > 1 ) ? round( 100 / $child_count, 2 ) : 0;

		foreach ( $items as $item ) {
			$type = $item['type'] ?? '';

			if ( 'container' === $type ) {
				$settings  = is_array( $item['settings'] ?? null ) ? $item['settings'] : array();
				$children  = is_array( $item['children'] ?? null ) ? $item['children'] : array();
				$direction = $settings['flex_direction'] ?? '';

				if ( $is_in_row && $child_count > 1 ) {
					$has_width = isset( $settings['width'] ) || isset( $settings['_flex_size'] ) || isset( $settings['_flex_grow'] );
					if ( ! $has_width ) {
						$settings['content_width'] = 'full';
						$settings['width']         = array( 'size' => $equal_width, 'unit' => '%' );
					}
				}

				$child_elements = $this->build_recursive( $children, true, $direction );
				$container      = $this->factory->create_container( $settings, $child_elements );
				if ( $is_inner ) {
					$container['isInner'] = true;
				}
				$elements[] = $container;

			} elseif ( 'widget' === $type ) {
				$widget_type = $item['widget_type'] ?? '';
				$settings    = is_array( $item['settings'] ?? null ) ? $item['settings'] : array();

				if ( empty( $widget_type ) ) {
					continue;
				}

				$widget = $this->factory->create_widget( $widget_type, $settings );

				if ( $is_in_row && $child_count > 1 ) {
					$col_settings = array(
						'content_width'  => 'full',
						'flex_direction' => 'column',
						'width'          => array( 'size' => $equal_width, 'unit' => '%' ),
					);
					$col            = $this->factory->create_container( $col_settings, array( $widget ) );
					$col['isInner'] = true;
					$elements[]     = $col;
				} else {
					$elements[] = $widget;
				}
			}
		}

		return $elements;
	}

	/**
	 * Flushes Elementor CSS cache so newly saved elements render with fresh styles.
	 */
	private function flush_elementor_cache(): void {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return;
		}
		$plugin = \Elementor\Plugin::$instance;
		if ( empty( $plugin ) || empty( $plugin->files_manager ) ) {
			return;
		}
		if ( method_exists( $plugin->files_manager, 'clear_cache' ) ) {
			$plugin->files_manager->clear_cache();
		}
	}

	// -------------------------------------------------------------------------
	// design-theme-template
	// -------------------------------------------------------------------------

	private function register_design_theme_template(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/design-theme-template',
			array(
				'label'               => __( 'Design Theme Template', 'elementor-mcp' ),
				'description'         => __( 'Compiles a Design IR into an Elementor Theme Builder template (single-post, single-page, archive, header, footer, etc.) and optionally sets display conditions. Use for building theme templates that apply to many posts at once.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_design_theme_template' ),
				'permission_callback' => array( $this, 'check_create_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'         => array( 'type' => 'string' ),
						'template_type' => array(
							'type'        => 'string',
							'enum'        => array( 'single-post', 'single-page', 'archive', 'header', 'footer', 'search-results', 'single', 'loop-item' ),
							'description' => __( 'Elementor template type. Default: single-post.', 'elementor-mcp' ),
						),
						'brand_tokens'  => array(
							'type'       => 'object',
							'properties' => array(
								'palette'    => array( 'type' => 'string' ),
								'typography' => array( 'type' => 'string' ),
							),
						),
						'sections'      => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'pattern' => array( 'type' => 'string' ),
									'slots'   => array( 'type' => 'object' ),
								),
								'required' => array( 'pattern' ),
							),
						),
						'conditions'    => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Elementor display conditions (e.g. ["include/singular/post"] for all blog posts, ["include/singular/post/post_category/5"] for category 5).', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'title', 'sections' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'template_id'      => array( 'type' => 'integer' ),
						'title'            => array( 'type' => 'string' ),
						'template_type'    => array( 'type' => 'string' ),
						'edit_url'         => array( 'type' => 'string' ),
						'conditions_set'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'elements_created' => array( 'type' => 'integer' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * design-theme-template handler.
	 */
	public function execute_design_theme_template( $input ) {
		$title         = sanitize_text_field( $input['title'] ?? '' );
		$template_type = sanitize_key( $input['template_type'] ?? 'single-post' );
		$conditions    = is_array( $input['conditions'] ?? null ) ? $input['conditions'] : array();

		if ( empty( $title ) ) {
			return new \WP_Error( 'emcp_missing_title', __( 'Template title required.', 'elementor-mcp' ) );
		}

		$ir = array(
			'title'        => $title,
			'sections'     => is_array( $input['sections'] ?? null ) ? $input['sections'] : array(),
			'brand_tokens' => $input['brand_tokens'] ?? array(),
		);

		$payload = $this->compiler->compile( $ir );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$elements = $this->realize_structure( $payload['structure'] );

		// Create the Elementor library post.
		$elementor_version = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.30.0';

		$template_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_type'   => 'elementor_library',
				'meta_input'  => array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => $template_type,
					'_elementor_version'       => $elementor_version,
				),
			),
			true
		);
		if ( is_wp_error( $template_id ) ) {
			return $template_id;
		}

		// Set the template type taxonomy term.
		wp_set_object_terms( $template_id, $template_type, 'elementor_library_type' );

		// Tag as EMCP-generated so Article Enhancer activates for posts using this template.
		update_post_meta( $template_id, '_emcp_generated', 1 );

		// Save element data via existing document pipeline (triggers CSS regen).
		$save_result = $this->data->save_page_data( $template_id, $elements );
		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		$conditions_set = $this->set_template_display_conditions( $template_id, $conditions );

		$this->flush_elementor_cache();

		return array(
			'template_id'      => $template_id,
			'title'            => $title,
			'template_type'    => $template_type,
			'edit_url'         => admin_url( 'post.php?post=' . $template_id . '&action=elementor' ),
			'conditions_set'   => $conditions_set,
			'elements_created' => count( $elements ),
		);
	}

	/**
	 * Sets Elementor Pro Theme Builder display conditions for a template.
	 *
	 * Accepts `'include/singular/post'` style strings from the caller,
	 * then converts each to `['include','singular','post']` path-parts
	 * which is the format Pro's `save_conditions()` expects (it runs
	 * `implode('/', $condition)` over each item). Going through
	 * `save_conditions()` also triggers `Conditions_Cache::regenerate()`
	 * so templates appear in location lookups immediately — the
	 * postmeta-only fallback leaves Pro's cache stale.
	 *
	 * @param int      $template_id Template post ID.
	 * @param string[] $conditions  Condition strings like 'include/singular/post'.
	 * @return string[] Conditions that were set (normalized paths).
	 */
	private function set_template_display_conditions( int $template_id, array $conditions ): array {
		if ( empty( $conditions ) ) {
			return array();
		}

		$paths = array();
		$parts_list = array();
		foreach ( $conditions as $cond ) {
			$cond = trim( (string) $cond, "/ \t\n\r\0\x0B" );
			if ( '' === $cond ) {
				continue;
			}
			$parts = explode( '/', $cond );
			$parts = array_values( array_filter( $parts, function ( $p ) { return '' !== $p; } ) );
			if ( empty( $parts ) ) {
				continue;
			}
			$paths[]     = implode( '/', $parts );
			$parts_list[] = $parts;
		}
		if ( empty( $paths ) ) {
			return array();
		}

		// Try Elementor Pro's conditions manager (also regenerates cache).
		if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
			try {
				$module = \ElementorPro\Modules\ThemeBuilder\Module::instance();
				if ( $module && method_exists( $module, 'get_conditions_manager' ) ) {
					$manager = $module->get_conditions_manager();
					if ( $manager && method_exists( $manager, 'save_conditions' ) ) {
						$saved = $manager->save_conditions( $template_id, $parts_list );
						if ( $saved ) {
							return $paths;
						}
					}
				}
			} catch ( \Throwable $e ) {
				// Fall through to postmeta fallback.
			}
		}

		// Fallback: write postmeta directly (Pro cache may be stale).
		update_post_meta( $template_id, '_elementor_conditions', $paths );
		return $paths;
	}

	// -------------------------------------------------------------------------
	// import-design
	// -------------------------------------------------------------------------

	private function register_import_design(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/import-design',
			array(
				'label'               => __( 'Import HTML Design', 'elementor-mcp' ),
				'description'         => __( 'Converts any Claude-generated HTML design into a native Elementor page. Feed it the raw HTML output from a Claude design task — it auto-maps semantic HTML elements to Elementor widgets (heading, text-editor, button, icon-box, accordion, etc.) and applies the result to a new or existing page. No PHP pattern file needed for one-off page designs.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_import_design' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'html'         => array(
							'type'        => 'string',
							'description' => __( 'Full HTML string from Claude design output. May include <style> blocks — CSS :root vars are extracted as design tokens.', 'elementor-mcp' ),
						),
						'url'          => array(
							'type'        => 'string',
							'description' => __( 'URL to fetch HTML from. Ignored if html is provided.', 'elementor-mcp' ),
						),
						'page_id'      => array(
							'type'        => 'integer',
							'description' => __( 'Existing page/post ID to overwrite with imported design. Supply either page_id OR title, not both.', 'elementor-mcp' ),
						),
						'title'        => array(
							'type'        => 'string',
							'description' => __( 'Title for a newly created page. Required when page_id is not provided.', 'elementor-mcp' ),
						),
						'post_type'    => array(
							'type'        => 'string',
							'enum'        => array( 'page', 'post' ),
							'description' => __( 'Post type for new page creation. Default: page.', 'elementor-mcp' ),
						),
						'post_status'  => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'publish' ),
							'description' => __( 'Status for new page creation. Default: draft.', 'elementor-mcp' ),
						),
						'skip_header'  => array(
							'type'        => 'boolean',
							'description' => __( 'Skip <header> / .topnav / .site-header elements. Default true.', 'elementor-mcp' ),
						),
						'skip_footer'  => array(
							'type'        => 'boolean',
							'description' => __( 'Skip <footer> / .site-footer elements. Default true.', 'elementor-mcp' ),
						),
						'dry_run'      => array(
							'type'        => 'boolean',
							'description' => __( 'When true, parse the HTML and return stats/unmapped_elements WITHOUT saving to any page. Useful for inspecting what the importer would produce before committing. Default false.', 'elementor-mcp' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'             => array( 'type' => 'integer' ),
						'edit_url'            => array( 'type' => 'string' ),
						'preview_url'         => array( 'type' => 'string' ),
						'elements_created'    => array( 'type' => 'integer' ),
						'native_widgets'      => array( 'type' => 'integer' ),
						'html_fallbacks'      => array( 'type' => 'integer' ),
						'tokens_extracted'    => array( 'type' => 'boolean' ),
						'widget_coverage_pct' => array( 'type' => 'integer', 'description' => 'native_widgets ÷ (native+html) as 0-100 %.' ),
						'fidelity_hint'       => array( 'type' => 'string', 'description' => 'Human-readable coverage grade: excellent/good/fair/poor + suggested action.' ),
						'needs_review'        => array( 'type' => 'boolean', 'description' => 'True when unmapped_elements contains any no_rule_leaf entries.' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * import-design handler.
	 *
	 * Calls Elementor_MCP_Design_Importer::import(), converts the resulting
	 * Design IR to Elementor JSON via realize_structure(), then saves to a
	 * new or existing page.
	 */
	public function execute_import_design( $input ) {
		if ( null === $this->importer ) {
			return new \WP_Error(
				'emcp_importer_unavailable',
				__( 'Design importer is not available. Ensure elementor-mcp plugin is fully loaded.', 'elementor-mcp' )
			);
		}

		$html        = (string) ( $input['html'] ?? '' );
		$url         = (string) ( $input['url'] ?? '' );
		$page_id     = isset( $input['page_id'] ) ? (int) $input['page_id'] : 0;
		$title       = sanitize_text_field( $input['title'] ?? '' );
		$post_type   = sanitize_key( $input['post_type'] ?? 'page' );
		$post_status = sanitize_key( $input['post_status'] ?? 'draft' );
		$skip_header = (bool) ( $input['skip_header'] ?? true );
		$skip_footer = (bool) ( $input['skip_footer'] ?? true );
		$dry_run     = (bool) ( $input['dry_run']     ?? false );

		// Must have either page_id or title (unless dry_run — no page needed).
		if ( ! $dry_run && $page_id <= 0 && '' === $title ) {
			return new \WP_Error(
				'emcp_import_missing_target',
				__( 'Provide either page_id (to overwrite an existing page) or title (to create a new page).', 'elementor-mcp' )
			);
		}

		// Run the importer.
		$result = $this->importer->import( array(
			'html'         => $html,
			'url'          => $url,
			'skip_header'  => $skip_header,
			'skip_footer'  => $skip_footer,
			'wrapper_class' => 'emcp-imported-page',
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Bind extracted CSS-var palette to Elementor Kit so color
		// globals work out of the box (matches existing design-page flow).
		$palette_globals = array();
		if ( ! empty( $result['brand_tokens']['palette'] ) && class_exists( 'Elementor_MCP_Kit_Binder' ) ) {
			$binder = new \Elementor_MCP_Kit_Binder();
			if ( method_exists( $binder, 'bind_palette_array' ) ) {
				$palette_globals = $binder->bind_palette_array(
					'emcp-import-' . substr( md5( serialize( $result['brand_tokens']['palette'] ) ), 0, 7 ),
					$result['brand_tokens']['palette']
				);
			}
		}

		// Convert Design IR → Elementor JSON.
		$elements = $this->realize_structure( $result['structure'] );

		// QW5: precompute coverage metrics (same shape for dry-run and final return).
		$stats_all    = $result['stats'] ?? array();
		$unmapped_all = $result['unmapped_elements'] ?? array();
		$metrics      = $this->compute_import_metrics( $stats_all, $unmapped_all );

		// Dry-run: return stats without persisting anything.
		if ( $dry_run ) {
			return array(
				'dry_run'              => true,
				'elements_created'     => count( $elements ),
				'elements_mapped'      => (int) ( $stats_all['elements_mapped']      ?? 0 ),
				'native_widgets'       => (int) ( $stats_all['native_widgets']       ?? 0 ),
				'html_fallbacks'       => (int) ( $stats_all['html_widgets']         ?? 0 ),
				'accordions_collapsed' => (int) ( $stats_all['accordions_collapsed'] ?? 0 ),
				'tokens_extracted'     => is_array( $result['tokens']['raw'] ?? null ) ? count( $result['tokens']['raw'] ) : 0,
				'widget_coverage_pct'  => $metrics['widget_coverage_pct'],
				'fidelity_hint'        => $metrics['fidelity_hint'],
				'unmapped_elements'    => $unmapped_all,
				'needs_review'         => $metrics['needs_review'],
			);
		}

		// Apply to page_id or create a new page.
		if ( $page_id > 0 ) {
			$save_result = $this->data->save_page_data( $page_id, $elements );
			if ( is_wp_error( $save_result ) ) {
				return $save_result;
			}
		} else {
			$page_id = wp_insert_post(
				array(
					'post_title'  => $title,
					'post_status' => in_array( $post_status, array( 'draft', 'publish' ), true ) ? $post_status : 'draft',
					'post_type'   => in_array( $post_type, array( 'page', 'post' ), true ) ? $post_type : 'page',
				),
				true
			);
			if ( is_wp_error( $page_id ) ) {
				return $page_id;
			}
			$save_result = $this->data->save_page_data( $page_id, $elements );
			if ( is_wp_error( $save_result ) ) {
				return $save_result;
			}
		}

		$this->flush_elementor_cache();

		return array(
			'post_id'              => $page_id,
			'edit_url'             => admin_url( 'post.php?post=' . $page_id . '&action=elementor' ),
			'preview_url'          => (string) get_permalink( $page_id ),
			'elements_created'     => count( $elements ),
			'elements_mapped'      => (int) ( $stats_all['elements_mapped']      ?? 0 ),
			'native_widgets'       => (int) ( $stats_all['native_widgets']       ?? 0 ),
			'html_fallbacks'       => (int) ( $stats_all['html_widgets']         ?? 0 ),
			'accordions_collapsed' => (int) ( $stats_all['accordions_collapsed'] ?? 0 ),
			'tokens_extracted'     => is_array( $result['tokens']['raw'] ?? null ) ? count( $result['tokens']['raw'] ) : 0,
			'palette_bound_slots'  => count( $palette_globals ),
			// QW5: coarse fidelity signal — helps Claude decide if re-annotation pass is worth it.
			'widget_coverage_pct'  => $metrics['widget_coverage_pct'],
			'fidelity_hint'        => $metrics['fidelity_hint'],
			// Layer 3: Claude reads unmapped_elements to decide if re-annotation is needed.
			// Each entry: {tag, class, id, snippet (≤300 chars), reason, hint}.
			// reason = 'no_rule_leaf' → add data-emcp-widget attr and re-import.
			// reason = 'forced_html_rule' → form/svg/iframe, expected — no action needed.
			// reason = 'css_rule_unresolved' → <style> class rule dropped, add inline style attr to element.
			'unmapped_elements'    => $unmapped_all,
			'needs_review'         => $metrics['needs_review'],
		);
	}

	/**
	 * Computes summary fidelity metrics from importer stats.
	 *
	 * Returns:
	 *   - widget_coverage_pct (int 0-100): native_widgets ÷ (native + html fallback)
	 *   - fidelity_hint       (string): human-readable grade Claude can quote back
	 *   - needs_review        (bool): true when any 'no_rule_leaf' entries exist
	 *
	 * @param array $stats    Raw stats from Design_Importer::import().
	 * @param array $unmapped Entries from importer's unmapped_elements list.
	 * @return array{widget_coverage_pct:int,fidelity_hint:string,needs_review:bool}
	 */
	private function compute_import_metrics( array $stats, array $unmapped ): array {
		$native    = (int) ( $stats['native_widgets'] ?? 0 );
		$fallback  = (int) ( $stats['html_widgets']   ?? 0 );
		$total     = $native + $fallback;
		$coverage  = $total > 0 ? (int) round( ( $native / $total ) * 100 ) : 100;

		$hint = 'excellent';
		if ( $coverage < 95 ) {
			$hint = 'good';
		}
		if ( $coverage < 80 ) {
			$hint = 'fair — several elements fell to html widget; check unmapped_elements for fix suggestions';
		}
		if ( $coverage < 60 ) {
			$hint = 'poor — add data-emcp-widget hints on unmapped elements or inline style attrs on class-styled elements, then re-import';
		}

		$needs_review = count( array_filter(
			$unmapped,
			static function ( $u ) {
				return isset( $u['reason'] ) && 'no_rule_leaf' === $u['reason'];
			}
		) ) > 0;

		return array(
			'widget_coverage_pct' => $coverage,
			'fidelity_hint'       => $hint,
			'needs_review'        => $needs_review,
		);
	}
}
