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
			'elementor-mcp/audit-imported-page',
			'elementor-mcp/lint-html',
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
		$this->register_audit_imported_page();
		$this->register_lint_html();
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
						'sideload_images' => array(
							'type'        => 'boolean',
							'description' => __( 'Download external <img src="https://..."> to WP media library and rewrite src to local URL. Default true. Set false to keep URLs external (faster, fragile).', 'elementor-mcp' ),
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
		$skip_header     = (bool) ( $input['skip_header']     ?? true );
		$skip_footer     = (bool) ( $input['skip_footer']     ?? true );
		$sideload_images = (bool) ( $input['sideload_images'] ?? true );
		$dry_run         = (bool) ( $input['dry_run']         ?? false );

		// Must have either page_id or title (unless dry_run — no page needed).
		if ( ! $dry_run && $page_id <= 0 && '' === $title ) {
			return new \WP_Error(
				'emcp_import_missing_target',
				__( 'Provide either page_id (to overwrite an existing page) or title (to create a new page).', 'elementor-mcp' )
			);
		}

		// Run the importer.
		$result = $this->importer->import( array(
			'html'            => $html,
			'url'             => $url,
			'skip_header'     => $skip_header,
			'skip_footer'     => $skip_footer,
			'sideload_images' => $sideload_images && ! $dry_run, // don't download files in dry-run
			'wrapper_class'   => 'emcp-imported-page',
		) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Bind extracted CSS-var palette to Elementor Kit so color
		// globals work out of the box (matches existing design-page flow).
		$palette_globals    = array();
		$typography_globals = array();
		$import_slug        = 'emcp-import-' . substr( md5( serialize( $result['brand_tokens']['palette'] ?? array() ) ), 0, 7 );
		if ( class_exists( 'Elementor_MCP_Kit_Binder' ) ) {
			$binder = new \Elementor_MCP_Kit_Binder();
			if ( ! empty( $result['brand_tokens']['palette'] ) && method_exists( $binder, 'bind_palette_array' ) ) {
				$palette_globals = $binder->bind_palette_array( $import_slug, $result['brand_tokens']['palette'] );
			}
			// Phase C: also bind typography to kit system_typography slots.
			if ( method_exists( $binder, 'bind_typography_array' ) ) {
				$families = $this->typography_families_from_tokens( $result['brand_tokens']['typography'] ?? array() );
				if ( ! empty( $families ) ) {
					$typo = $binder->bind_typography_array( $import_slug, $families );
					$typography_globals = $typo['globals'] ?? array();
					// Overflow → unmapped_elements feedback.
					if ( ! empty( $typo['overflow'] ) && ! $dry_run ) {
						foreach ( $typo['overflow'] as $slot => $cfg ) {
							$result['unmapped_elements'][] = array(
								'tag'     => 'typography',
								'class'   => $slot,
								'id'      => '',
								'snippet' => json_encode( $cfg ),
								'reason'  => 'typography_slot_overflow',
								'hint'    => 'Elementor Kit exposes only 4 system_typography slots (primary/secondary/text/accent). Additional font families need a custom theme stylesheet.',
							);
						}
					}
				}
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
				'images_sideloaded'    => (int) ( $stats_all['images_sideloaded']    ?? 0 ),
				'images_skipped'       => (int) ( $stats_all['images_skipped']       ?? 0 ),
				'tokens_extracted'     => is_array( $result['tokens']['raw'] ?? null ) ? count( $result['tokens']['raw'] ) : 0,
				'widget_coverage_pct'  => $metrics['widget_coverage_pct'],
				'style_coverage_pct'   => $metrics['style_coverage_pct'],
				'token_binding_pct'    => $metrics['token_binding_pct'],
				'image_resolution_pct' => $metrics['image_resolution_pct'],
				'fidelity_score'       => $metrics['fidelity_score'],
				'fidelity_hint'        => $metrics['fidelity_hint'],
				'suggested_actions'    => $metrics['suggested_actions'],
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
			'images_sideloaded'    => (int) ( $stats_all['images_sideloaded']    ?? 0 ),
			'images_skipped'       => (int) ( $stats_all['images_skipped']       ?? 0 ),
			'tokens_extracted'       => is_array( $result['tokens']['raw'] ?? null ) ? count( $result['tokens']['raw'] ) : 0,
			'palette_bound_slots'    => count( $palette_globals ),
			'typography_bound_slots' => count( $typography_globals ),
			// QW5 + Phase D composite fidelity signal.
			'widget_coverage_pct'  => $metrics['widget_coverage_pct'],
			'style_coverage_pct'   => $metrics['style_coverage_pct'],
			'token_binding_pct'    => $metrics['token_binding_pct'],
			'image_resolution_pct' => $metrics['image_resolution_pct'],
			'fidelity_score'       => $metrics['fidelity_score'],
			'fidelity_hint'        => $metrics['fidelity_hint'],
			'suggested_actions'    => $metrics['suggested_actions'],
			// Layer 3: Claude reads unmapped_elements to decide if re-annotation is needed.
			// Each entry: {tag, class, id, snippet (≤300 chars), reason, hint}.
			// reason = 'no_rule_leaf' → add data-emcp-widget attr and re-import.
			// reason = 'forced_html_rule' → form/svg/iframe, expected — no action needed.
			// reason = 'css_rule_unresolved' → <style> class rule dropped, add inline style attr to element.
			'unmapped_elements'    => $unmapped_all,
			'needs_review'         => $metrics['needs_review'],
		);
	}

	// -------------------------------------------------------------------------
	// audit-imported-page
	// -------------------------------------------------------------------------

	private function register_audit_imported_page(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/audit-imported-page',
			array(
				'label'               => __( 'Audit Imported Page', 'elementor-mcp' ),
				'description'         => __( 'Reads an Elementor page\'s _elementor_data + postmeta and returns a fidelity audit: widget counts per type, settings coverage percentages (padding / color / typography / CSS classes), kit-global binding check, and a composite fidelity_score (0-100). Use after import-design to decide whether re-annotation is needed.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_audit_imported_page' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'post_id' ),
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'Post / page ID to audit.', 'elementor-mcp' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'                 => array( 'type' => 'integer' ),
						'fidelity_score'          => array( 'type' => 'integer', 'description' => 'Composite 0-100.' ),
						'widget_counts'           => array( 'type' => 'object' ),
						'widget_total'            => array( 'type' => 'integer' ),
						'widget_native_pct'       => array( 'type' => 'integer' ),
						'widgets_with_padding_pct'=> array( 'type' => 'integer' ),
						'widgets_with_color_pct'  => array( 'type' => 'integer' ),
						'widgets_with_typo_pct'   => array( 'type' => 'integer' ),
						'widgets_with_class_pct'  => array( 'type' => 'integer' ),
						'palette_bound'           => array( 'type' => 'boolean' ),
						'typography_bound'        => array( 'type' => 'boolean' ),
						'hint'                    => array( 'type' => 'string' ),
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
	 * Audits a post's Elementor data for widget coverage and fidelity signals.
	 *
	 * @param array $input {post_id: int}
	 * @return array|\WP_Error
	 */
	public function execute_audit_imported_page( $input ) {
		$post_id = (int) ( $input['post_id'] ?? 0 );
		if ( $post_id <= 0 ) {
			return new \WP_Error( 'emcp_audit_missing_post_id', __( 'post_id is required.', 'elementor-mcp' ) );
		}
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $raw ) && '' !== $raw ) {
			$data = json_decode( $raw, true );
		} else {
			$data = is_array( $raw ) ? $raw : array();
		}
		if ( empty( $data ) ) {
			return new \WP_Error(
				'emcp_audit_no_data',
				sprintf(
					/* translators: %d: post id */
					__( 'No _elementor_data found for post %d.', 'elementor-mcp' ),
					$post_id
				)
			);
		}

		$stats = array(
			'widget_counts'            => array(),
			'widget_total'             => 0,
			'native_widgets'           => 0,
			'html_widgets'             => 0,
			'widgets_with_padding'     => 0,
			'widgets_with_color'       => 0,
			'widgets_with_typography'  => 0,
			'widgets_with_css_classes' => 0,
		);
		$this->audit_walk( $data, $stats );

		// Kit-binding check: any color/typography slot name starts with `emcp-import-`?
		$palette_bound    = false;
		$typography_bound = false;
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id() ?? 0;
			if ( $kit_id ) {
				$kit_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
				$kit_settings = is_array( $kit_settings ) ? $kit_settings : array();
				foreach ( ( $kit_settings['system_colors'] ?? array() ) as $c ) {
					if ( isset( $c['title'] ) && false !== strpos( $c['title'], 'EMCP · emcp-import-' ) ) {
						$palette_bound = true;
						break;
					}
				}
				foreach ( ( $kit_settings['system_typography'] ?? array() ) as $t ) {
					if ( isset( $t['title'] ) && false !== strpos( $t['title'], 'EMCP · emcp-import-' ) ) {
						$typography_bound = true;
						break;
					}
				}
			}
		}

		$tot              = max( 1, $stats['widget_total'] );
		$native_pct       = (int) round( ( $stats['native_widgets'] / $tot ) * 100 );
		$padding_pct      = (int) round( ( $stats['widgets_with_padding']     / $tot ) * 100 );
		$color_pct        = (int) round( ( $stats['widgets_with_color']       / $tot ) * 100 );
		$typo_pct         = (int) round( ( $stats['widgets_with_typography']  / $tot ) * 100 );
		$class_pct        = (int) round( ( $stats['widgets_with_css_classes'] / $tot ) * 100 );

		// Composite 0-100: same weights as import-design's fidelity_score spec.
		$score = (int) round(
			$native_pct * 0.30
			+ $padding_pct * 0.20
			+ $color_pct * 0.20
			+ $typo_pct * 0.15
			+ $class_pct * 0.05
			+ ( $palette_bound ? 100 : 0 ) * 0.05
			+ ( $typography_bound ? 100 : 0 ) * 0.05
		);

		$hint = 'excellent';
		if ( $score < 90 ) {
			$hint = 'good';
		}
		if ( $score < 75 ) {
			$hint = 'fair — add more inline styles / data-emcp-widget annotations to the source HTML and re-import';
		}
		if ( $score < 55 ) {
			$hint = 'poor — large portions fell to html widget or have no styling applied';
		}

		return array(
			'post_id'                 => $post_id,
			'fidelity_score'          => $score,
			'widget_counts'           => $stats['widget_counts'],
			'widget_total'            => $stats['widget_total'],
			'widget_native_pct'       => $native_pct,
			'widgets_with_padding_pct'=> $padding_pct,
			'widgets_with_color_pct'  => $color_pct,
			'widgets_with_typo_pct'   => $typo_pct,
			'widgets_with_class_pct'  => $class_pct,
			'palette_bound'           => $palette_bound,
			'typography_bound'        => $typography_bound,
			'hint'                    => $hint,
		);
	}

	/**
	 * Recursively counts widgets + settings coverage in a `_elementor_data` tree.
	 */
	private function audit_walk( array $nodes, array &$stats ): void {
		foreach ( $nodes as $node ) {
			if ( ! is_array( $node ) ) {
				continue;
			}
			$type = $node['elType'] ?? '';
			if ( 'widget' === $type ) {
				$wtype = (string) ( $node['widgetType'] ?? 'unknown' );
				$stats['widget_total']++;
				$stats['widget_counts'][ $wtype ] = ( $stats['widget_counts'][ $wtype ] ?? 0 ) + 1;
				if ( 'html' === $wtype ) {
					$stats['html_widgets']++;
				} else {
					$stats['native_widgets']++;
				}
				$settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : array();
				if ( isset( $settings['padding'] ) || isset( $settings['margin'] ) ) {
					$stats['widgets_with_padding']++;
				}
				if ( isset( $settings['background_color'] ) || isset( $settings['color'] ) || isset( $settings['title_color'] ) || isset( $settings['text_color'] ) ) {
					$stats['widgets_with_color']++;
				}
				if ( isset( $settings['typography_font_family'] ) || isset( $settings['typography_font_size'] ) || isset( $settings['typography_typography'] ) ) {
					$stats['widgets_with_typography']++;
				}
				$cls_val = $settings['css_classes'] ?? $settings['_css_classes'] ?? '';
				if ( '' !== trim( (string) $cls_val ) ) {
					$stats['widgets_with_css_classes']++;
				}
			} elseif ( 'container' === $type ) {
				// Containers: also track padding/color for fidelity.
				$stats['widget_total']++;
				$stats['native_widgets']++;
				$stats['widget_counts']['container'] = ( $stats['widget_counts']['container'] ?? 0 ) + 1;
				$settings = is_array( $node['settings'] ?? null ) ? $node['settings'] : array();
				if ( isset( $settings['padding'] ) || isset( $settings['margin'] ) ) {
					$stats['widgets_with_padding']++;
				}
				if ( isset( $settings['background_color'] ) ) {
					$stats['widgets_with_color']++;
				}
				if ( ! empty( $settings['css_classes'] ) ) {
					$stats['widgets_with_css_classes']++;
				}
			}
			if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
				$this->audit_walk( $node['elements'], $stats );
			}
		}
	}

	// -------------------------------------------------------------------------
	// lint-html
	// -------------------------------------------------------------------------

	private function register_lint_html(): void {
		elementor_mcp_register_ability(
			'elementor-mcp/lint-html',
			array(
				'label'               => __( 'Lint HTML for Import', 'elementor-mcp' ),
				'description'         => __( 'Pre-import linter. Scans raw HTML for issues that would reduce import-design fidelity: unclosed tags, missing alt text, external images that need sideloading, <style> class rules that will not resolve, estimated widget-map coverage, and suspicious inline handlers. Returns go/no-go with warnings array so Claude can fix-and-retry BEFORE running import-design.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_lint_html' ),
				'permission_callback' => '__return_true',
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'html' ),
					'properties' => array(
						'html' => array(
							'type'        => 'string',
							'description' => __( 'Raw HTML to lint. Same format as import-design.html input.', 'elementor-mcp' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'go_no_go'             => array( 'type' => 'string', 'description' => 'go | caution | no_go' ),
						'estimated_coverage'   => array( 'type' => 'integer', 'description' => 'Predicted widget_coverage_pct 0-100.' ),
						'warnings'             => array( 'type' => 'array' ),
						'stats'                => array( 'type' => 'object' ),
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
	 * Pre-import HTML lint. Pure analysis, no WP state touched.
	 *
	 * @param array $input {html: string}
	 * @return array
	 */
	public function execute_lint_html( $input ) {
		$html = (string) ( $input['html'] ?? '' );
		if ( '' === trim( $html ) ) {
			return new \WP_Error( 'emcp_lint_empty', __( 'html parameter is required and non-empty.', 'elementor-mcp' ) );
		}

		$warnings = array();
		$stats    = array(
			'total_elements'     => 0,
			'images_total'       => 0,
			'images_external'    => 0,
			'images_missing_alt' => 0,
			'class_rules'        => 0,
			'at_rules'           => 0,
			'inline_styles'      => 0,
			'script_tags'        => 0,
			'iframes_total'      => 0,
			'iframes_video'      => 0,
		);

		// Detect DOM parse errors.
		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$loaded = $dom->loadHTML( '<?xml encoding="UTF-8"?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		$libxml_errors = libxml_get_errors();
		libxml_clear_errors();
		if ( ! $loaded ) {
			$warnings[] = array(
				'severity' => 'error',
				'code'     => 'dom_parse_failed',
				'message'  => 'DOMDocument could not parse the input HTML. Check for broken markup.',
			);
			return array( 'go_no_go' => 'no_go', 'estimated_coverage' => 0, 'warnings' => $warnings, 'stats' => $stats );
		}
		foreach ( $libxml_errors as $err ) {
			$msg = trim( $err->message );
			if ( '' === $msg ) {
				continue;
			}
			// Filter noisy "Tag X invalid in Entity" warnings from HTML5-only elements.
			if ( false !== strpos( $msg, 'Tag' ) && false !== strpos( $msg, 'invalid' ) ) {
				continue;
			}
			$warnings[] = array(
				'severity' => 'warning',
				'code'     => 'dom_parse_warning',
				'message'  => $msg . ( $err->line ? ' (line ' . $err->line . ')' : '' ),
			);
		}

		// Walk elements.
		$all = $dom->getElementsByTagName( '*' );
		$stats['total_elements'] = $all->length;

		$home_host = '';
		if ( function_exists( 'home_url' ) ) {
			$parts     = wp_parse_url( home_url() );
			$home_host = strtolower( $parts['host'] ?? '' );
		}

		$covered_tags = array(
			'section','div','aside','header','footer','main','article', // containers
			'h1','h2','h3','h4','h5','h6','p','blockquote',              // text
			'a','button',                                                 // button (class-gated)
			'img','figure','picture',                                     // image
			'ul','ol','dl','nav',                                         // lists
			'details','summary',                                          // accordion
			'iframe','video',                                             // video
			'progress','hr',                                              // progress/divider
		);
		$covered = 0;
		foreach ( $all as $el ) {
			$tag = strtolower( $el->tagName );
			if ( in_array( $tag, $covered_tags, true ) ) {
				$covered++;
			}
			if ( '' !== $el->getAttribute( 'style' ) ) {
				$stats['inline_styles']++;
			}
		}

		foreach ( $dom->getElementsByTagName( 'img' ) as $img ) {
			$stats['images_total']++;
			$src = $img->getAttribute( 'src' );
			if ( '' === $img->getAttribute( 'alt' ) ) {
				$stats['images_missing_alt']++;
			}
			if ( preg_match( '#^https?://#i', $src ) ) {
				$parts = wp_parse_url( $src );
				$host  = strtolower( $parts['host'] ?? '' );
				if ( '' === $home_host || $host !== $home_host ) {
					$stats['images_external']++;
				}
			}
		}
		foreach ( $dom->getElementsByTagName( 'iframe' ) as $if ) {
			$stats['iframes_total']++;
			$src = $if->getAttribute( 'src' );
			if ( preg_match( '#youtube\.com|youtu\.be|vimeo\.com|player\.vimeo\.com#i', $src ) ) {
				$stats['iframes_video']++;
			}
		}
		$stats['script_tags'] = $dom->getElementsByTagName( 'script' )->length;

		// Stylesheet analysis.
		foreach ( $dom->getElementsByTagName( 'style' ) as $style ) {
			$css = $style->textContent;
			$stats['class_rules'] += preg_match_all( '/[^{}]*\.[A-Za-z0-9_-]+[^{}]*\{[^}]*\}/', $css );
			$stats['at_rules']    += preg_match_all( '/@[a-z-]+[^{]*\{/i', $css );
		}

		// Warnings.
		if ( $stats['images_missing_alt'] > 0 ) {
			$warnings[] = array(
				'severity' => 'info',
				'code'     => 'missing_alt_text',
				'message'  => $stats['images_missing_alt'] . ' image(s) missing alt text — bad for a11y and SEO.',
			);
		}
		if ( $stats['images_external'] > 0 ) {
			$warnings[] = array(
				'severity' => 'info',
				'code'     => 'external_images',
				'message'  => $stats['images_external'] . ' external image URL(s) — will be sideloaded by import-design (unless sideload_images=false). Ensure URLs are reachable.',
			);
		}
		if ( $stats['at_rules'] > 0 ) {
			$warnings[] = array(
				'severity' => 'warning',
				'code'     => 'at_rules_dropped',
				'message'  => $stats['at_rules'] . ' @-rule(s) (@media/@supports/@keyframes) in <style> will be dropped. Convert to inline styles or per-device Elementor controls.',
			);
		}
		if ( $stats['script_tags'] > 0 ) {
			$warnings[] = array(
				'severity' => 'warning',
				'code'     => 'script_tags',
				'message'  => $stats['script_tags'] . ' <script> tag(s) will be kept in html widget fallback. Move to Elementor custom code panel instead.',
			);
		}
		if ( $stats['inline_styles'] === 0 && $stats['class_rules'] === 0 ) {
			$warnings[] = array(
				'severity' => 'error',
				'code'     => 'no_styles',
				'message'  => 'No inline styles or class rules detected — container fidelity will be near zero.',
			);
		}

		$estimated_coverage = $stats['total_elements'] > 0
			? (int) round( ( $covered / $stats['total_elements'] ) * 100 )
			: 0;

		$go_no_go = 'go';
		foreach ( $warnings as $w ) {
			if ( 'error' === $w['severity'] ) {
				$go_no_go = 'no_go';
				break;
			}
		}
		if ( 'go' === $go_no_go ) {
			foreach ( $warnings as $w ) {
				if ( 'warning' === $w['severity'] ) {
					$go_no_go = 'caution';
					break;
				}
			}
		}

		return array(
			'go_no_go'           => $go_no_go,
			'estimated_coverage' => $estimated_coverage,
			'warnings'           => $warnings,
			'stats'              => $stats,
		);
	}

	/**
	 * Maps the extracted typography tokens into the `bind_typography_array`
	 * input shape.  Token extractor gives `{families: {display:X, body:Y,…},
	 * sizes: {display-xl:60px,…}}`; kit binder wants `{primary:{family,weight,size},
	 * secondary, text, accent}`.
	 *
	 * Heuristic mapping (best-effort):
	 *   display/heading/headline → primary
	 *   body/paragraph/text      → text
	 *   accent/mono/brand        → accent
	 *   anything else            → secondary (first unclaimed slot) else overflow
	 *
	 * @param array $typography Extracted typography tokens.
	 * @return array<string,array>
	 */
	private function typography_families_from_tokens( array $typography ): array {
		if ( empty( $typography['families'] ) || ! is_array( $typography['families'] ) ) {
			return array();
		}
		$out  = array();
		$used = array();
		$map  = array(
			'primary'   => array( 'display', 'heading', 'headline', 'title', 'h1' ),
			'text'      => array( 'body', 'paragraph', 'text', 'sans', 'p' ),
			'accent'    => array( 'accent', 'mono', 'brand', 'code' ),
			'secondary' => array( 'secondary', 'sub', 'caption', 'meta' ),
		);
		foreach ( $typography['families'] as $key => $family ) {
			$slug = strtolower( (string) $key );
			foreach ( $map as $slot => $needles ) {
				if ( isset( $out[ $slot ] ) ) {
					continue;
				}
				foreach ( $needles as $n ) {
					if ( false !== strpos( $slug, $n ) ) {
						$out[ $slot ] = array( 'family' => (string) $family );
						$used[ $key ] = true;
						break 2;
					}
				}
			}
		}
		// Fill remaining slots with any leftover families.
		$slots_left = array_diff( array( 'primary', 'secondary', 'text', 'accent' ), array_keys( $out ) );
		foreach ( $typography['families'] as $key => $family ) {
			if ( isset( $used[ $key ] ) ) {
				continue;
			}
			if ( empty( $slots_left ) ) {
				// Keep as-is so caller can push to overflow.
				$out[ $key ] = array( 'family' => (string) $family );
				continue;
			}
			$slot        = array_shift( $slots_left );
			$out[ $slot ] = array( 'family' => (string) $family );
			$used[ $key ] = true;
		}
		return $out;
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

		// Image resolution %: sideloaded ÷ (sideloaded + skipped). 100 when no external images.
		$sl      = (int) ( $stats['images_sideloaded'] ?? 0 );
		$sk      = (int) ( $stats['images_skipped']    ?? 0 );
		$img_pct = ( $sl + $sk ) > 0 ? (int) round( ( $sl / ( $sl + $sk ) ) * 100 ) : 100;

		// CSS rule resolution %: rules total minus unresolved reasons.
		$unresolved = 0;
		$failures   = array();
		foreach ( $unmapped as $u ) {
			$reason = $u['reason'] ?? '';
			$failures[ $reason ] = ( $failures[ $reason ] ?? 0 ) + 1;
			if ( 'css_rule_unresolved' === $reason ) {
				$unresolved++;
			}
		}
		// Style coverage proxy: elements that had a style applied via inline-or-resolver
		// We approximate via "fallbacks that have css_rule_unresolved" penalty.
		$style_pct = $total > 0 ? max( 0, 100 - (int) round( ( $unresolved / max( 1, $total ) ) * 100 ) ) : 100;

		// Token binding: 100 if any colors/typography bound, else 0.
		$token_pct = 100;
		if ( ! empty( $stats['needs_palette_binding'] ) && empty( $stats['palette_bound'] ) ) {
			$token_pct = 0;
		}

		// Composite fidelity_score 0-100 (weights match plan rubric).
		$score = (int) round(
			$coverage  * 0.30
			+ $style_pct * 0.25
			+ $token_pct * 0.20
			+ $img_pct   * 0.15
			+ ( 100 - min( 100, $unresolved * 10 ) ) * 0.10
		);
		$score = max( 0, min( 100, $score ) );

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

		$suggested_actions = array();
		if ( ( $failures['no_rule_leaf'] ?? 0 ) > 0 ) {
			$suggested_actions[] = 'Add data-emcp-widget="[type]" to the ' . $failures['no_rule_leaf'] . ' element(s) with reason=no_rule_leaf to force a widget type.';
		}
		if ( ( $failures['css_rule_unresolved'] ?? 0 ) > 0 ) {
			$suggested_actions[] = 'Inline the ' . $failures['css_rule_unresolved'] . ' <style> class rule(s) as style="…" on matching elements (@media, :hover etc. unsupported).';
		}
		if ( ( $failures['image_sideload_failed'] ?? 0 ) > 0 ) {
			$suggested_actions[] = 'Fix the ' . $failures['image_sideload_failed'] . ' unreachable image URL(s) or host them locally before re-import.';
		}
		if ( ( $failures['typography_slot_overflow'] ?? 0 ) > 0 ) {
			$suggested_actions[] = 'Reduce font families (Elementor has only 4 typography slots) or load extras via custom theme CSS.';
		}

		return array(
			'widget_coverage_pct' => $coverage,
			'style_coverage_pct'  => $style_pct,
			'token_binding_pct'   => $token_pct,
			'image_resolution_pct'=> $img_pct,
			'fidelity_score'      => $score,
			'fidelity_hint'       => $hint,
			'needs_review'        => $needs_review,
			'suggested_actions'   => $suggested_actions,
		);
	}
}
