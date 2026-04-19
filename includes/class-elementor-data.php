<?php
/**
 * Elementor data access layer.
 *
 * Wraps Elementor internals to provide a clean API for reading and writing
 * Elementor page data, widget registrations, and element trees.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Data access layer wrapping Elementor's internal APIs.
 *
 * @since 1.0.0
 */
class Elementor_MCP_Data {

	/**
	 * Gets the Elementor document for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return \Elementor\Core\Base\Document|\WP_Error The document instance or WP_Error.
	 */
	public function get_document( int $post_id ) {
		$document = \Elementor\Plugin::$instance->documents->get( $post_id );

		if ( ! $document ) {
			return new \WP_Error(
				'document_not_found',
				sprintf(
					/* translators: %d: post ID */
					__( 'Elementor document not found for post ID %d.', 'elementor-mcp' ),
					$post_id
				)
			);
		}

		return $document;
	}

	/**
	 * Gets the element tree for an Elementor page.
	 *
	 * Tries the Elementor document API first, falls back to reading raw
	 * post meta if the document returns empty data (common in CLI contexts).
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return array|\WP_Error The elements data array or WP_Error.
	 */
	public function get_page_data( int $post_id ) {
		$document = $this->get_document( $post_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$data = $document->get_elements_data();

		if ( is_array( $data ) && ! empty( $data ) ) {
			return $data;
		}

		// Fallback: read from raw post meta (handles CLI/proxy contexts).
		$raw = get_post_meta( $post_id, '_elementor_data', true );

		if ( ! empty( $raw ) && is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return array();
	}

	/**
	 * Gets the page-level settings for an Elementor document.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return array|\WP_Error The page settings array or WP_Error.
	 */
	public function get_page_settings( int $post_id ) {
		$document = $this->get_document( $post_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		return $document->get_settings();
	}

	/**
	 * Gets the document type for a post.
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id The post ID.
	 * @return string|\WP_Error The document type string or WP_Error.
	 */
	public function get_document_type( int $post_id ) {
		$document = $this->get_document( $post_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		return get_post_meta( $post_id, '_elementor_template_type', true );
	}

	/**
	 * Checks whether a post is built with Elementor.
	 *
	 * @since 1.4.4
	 *
	 * @param int $post_id The post ID.
	 * @return bool True when Elementor builder mode is enabled.
	 */
	public function is_elementor_page( int $post_id ): bool {
		return 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
	}

	/**
	 * Gets a post only if it is an Elementor-built page/post.
	 *
	 * @since 1.4.4
	 *
	 * @param int $post_id The post ID.
	 * @return \WP_Post|\WP_Error The post object or WP_Error.
	 */
	public function get_elementor_post( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'elementor-mcp' ) );
		}

		if ( ! $this->is_elementor_page( $post_id ) ) {
			return new \WP_Error(
				'not_elementor_page',
				__( 'This content exists, but it is not built with Elementor.', 'elementor-mcp' )
			);
		}

		return $post;
	}

	/**
	 * Finds a single Elementor-built post by slug.
	 *
	 * Searches supported content types only (`page` and `post`) unless an
	 * explicit post type is provided. Returns a deterministic ambiguity error
	 * when multiple Elementor matches are found.
	 *
	 * @since 1.4.4
	 *
	 * @param string $slug      The post slug.
	 * @param string $post_type Optional post type (`page` or `post`).
	 * @return \WP_Post|\WP_Error The resolved post object or WP_Error.
	 */
	public function get_elementor_post_by_slug( string $slug, string $post_type = '' ) {
		$slug = sanitize_title( $slug );

		if ( '' === $slug ) {
			return new \WP_Error( 'missing_slug', __( 'The slug parameter is required.', 'elementor-mcp' ) );
		}

		$types = $this->get_supported_post_types( $post_type );

		if ( is_wp_error( $types ) ) {
			return $types;
		}

		$query = new \WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'any',
				'posts_per_page'         => 10,
				'name'                   => $slug,
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		if ( empty( $query->posts ) ) {
			return new \WP_Error(
				'page_not_found',
				sprintf(
					/* translators: %s: page slug */
					__( 'No page or post found with slug "%s".', 'elementor-mcp' ),
					$slug
				)
			);
		}

		$elementor_posts = array_values(
			array_filter(
				$query->posts,
				function ( \WP_Post $post ): bool {
					return $this->is_elementor_page( $post->ID );
				}
			)
		);

		if ( empty( $elementor_posts ) ) {
			return new \WP_Error(
				'not_elementor_page',
				__( 'Matching content was found, but it is not built with Elementor.', 'elementor-mcp' ),
				array(
					'candidates' => $this->format_slug_candidates( $query->posts ),
				)
			);
		}

		if ( count( $elementor_posts ) > 1 ) {
			return new \WP_Error(
				'ambiguous_slug',
				__( 'Multiple Elementor pages matched this slug. Please provide post_type or post_id.', 'elementor-mcp' ),
				array(
					'candidates' => $this->format_slug_candidates( $elementor_posts ),
				)
			);
		}

		return $elementor_posts[0];
	}

	/**
	 * Builds a rich page payload used by page lookup tools.
	 *
	 * @since 1.4.4
	 *
	 * @param int $post_id The post ID.
	 * @return array|\WP_Error The payload array or WP_Error.
	 */
	public function get_rich_page_payload( int $post_id ) {
		$post = $this->get_elementor_post( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$elements      = $this->get_page_data( $post_id );
		$page_settings = $this->get_page_settings( $post_id );
		$document_type = $this->get_document_type( $post_id );

		if ( is_wp_error( $elements ) ) {
			return $elements;
		}

		if ( is_wp_error( $page_settings ) ) {
			return $page_settings;
		}

		if ( is_wp_error( $document_type ) ) {
			return $document_type;
		}

		return array(
			'post_id'           => $post->ID,
			'post_type'         => $post->post_type,
			'slug'              => $post->post_name,
			'title'             => $post->post_title,
			'status'            => $post->post_status,
			'link'              => get_permalink( $post->ID ) ?: '',
			'modified'          => $post->post_modified,
			'excerpt'           => $post->post_excerpt,
			'featured_image'    => $this->get_featured_image_data( $post->ID ),
			'template'          => $this->get_page_template( $post->ID ),
			'elementor_enabled' => true,
			'document_type'     => $document_type,
			'page_settings'     => $page_settings,
			'element_count'     => $this->count_elements( $elements ),
			'elements'          => $elements,
		);
	}

	/**
	 * Builds a compact page identifier payload.
	 *
	 * @since 1.4.4
	 *
	 * @param \WP_Post $post The post object.
	 * @return array The compact identifier payload.
	 */
	public function get_page_identifier_payload( \WP_Post $post ): array {
		return array(
			'post_id'   => $post->ID,
			'post_type' => $post->post_type,
			'slug'      => $post->post_name,
			'title'     => $post->post_title,
		);
	}

	/**
	 * Lists Elementor templates with optional filters.
	 *
	 * @since 1.7.5
	 *
	 * @param array $filters Optional filters.
	 * @return array|\WP_Error Template list or WP_Error.
	 */
	public function list_elementor_templates( array $filters = array() ) {
		$template_type = sanitize_key( (string) ( $filters['template_type'] ?? '' ) );
		$status        = sanitize_key( (string) ( $filters['status'] ?? 'publish' ) );

		if ( '' !== $status && 'any' !== $status ) {
			$allowed_statuses = get_post_stati( array(), 'names' );
			if ( ! in_array( $status, $allowed_statuses, true ) ) {
				return new \WP_Error(
					'invalid_template_status',
					__( 'Invalid template status provided.', 'elementor-mcp' ),
					array( 'status' => 400 )
				);
			}
		}

		$query_args = array(
			'post_type'      => 'elementor_library',
			'post_status'    => 'any' === $status ? 'any' : $status,
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( '' !== $template_type ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => '_elementor_template_type',
					'value' => $template_type,
				),
			);
		}

		$query     = new \WP_Query( $query_args );
		$templates = array();

		foreach ( $query->posts as $post ) {
			$templates[] = $this->get_template_list_item( $post );
		}

		return $templates;
	}

	/**
	 * Gets an Elementor template post by ID.
	 *
	 * @since 1.7.5
	 *
	 * @param int $post_id The template post ID.
	 * @return \WP_Post|\WP_Error Template post or WP_Error.
	 */
	public function get_elementor_template_post( int $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error(
				'template_not_found',
				__( 'Template not found.', 'elementor-mcp' ),
				array( 'status' => 404 )
			);
		}

		if ( 'elementor_library' !== $post->post_type ) {
			return new \WP_Error(
				'not_elementor_template',
				__( 'The requested content is not an Elementor template.', 'elementor-mcp' ),
				array( 'status' => 400 )
			);
		}

		return $post;
	}

	/**
	 * Builds a rich template payload used by MCP and REST read tools.
	 *
	 * @since 1.7.5
	 *
	 * @param int $post_id The template post ID.
	 * @return array|\WP_Error Rich payload or WP_Error.
	 */
	public function get_rich_template_payload( int $post_id ) {
		$post = $this->get_elementor_template_post( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$elements      = $this->get_page_data( $post_id );
		$page_settings = $this->get_page_settings( $post_id );

		if ( is_wp_error( $elements ) ) {
			return $elements;
		}

		if ( is_wp_error( $page_settings ) ) {
			return $page_settings;
		}

		$template_type = $this->resolve_elementor_template_type( $post_id );

		return array(
			'post_id'             => $post->ID,
			'post_type'           => $post->post_type,
			'slug'                => $post->post_name,
			'title'               => $post->post_title,
			'status'              => $post->post_status,
			'link'                => get_permalink( $post->ID ) ?: '',
			'modified'            => $post->post_modified,
			'featured_image'      => $this->get_featured_image_data( $post->ID ),
			'elementor_enabled'   => $this->is_elementor_page( $post_id ),
			'document_type'       => $template_type,
			'template_type'       => $template_type,
			'template_type_terms' => $this->get_elementor_template_terms( $post_id ),
			'display_conditions'  => $this->get_template_display_conditions( $post_id ),
			'page_settings'       => $page_settings,
			'element_count'       => $this->count_elements( $elements ),
			'elements'            => $elements,
			'structure'           => $this->simplify_element_structure( $elements ),
		);
	}

	/**
	 * Lists navigation menus and assigned theme locations.
	 *
	 * @since 1.7.5
	 *
	 * @return array Menu list payloads.
	 */
	public function list_nav_menus(): array {
		$menus         = wp_get_nav_menus();
		$location_map  = $this->get_nav_menu_location_map();
		$results       = array();

		foreach ( $menus as $menu ) {
			$results[] = array(
				'id'          => intval( $menu->term_id ),
				'name'        => (string) $menu->name,
				'slug'        => (string) $menu->slug,
				'description' => (string) $menu->description,
				'item_count'  => intval( $menu->count ),
				'locations'   => $location_map[ intval( $menu->term_id ) ] ?? array(),
			);
		}

		return $results;
	}

	/**
	 * Builds a full navigation menu payload.
	 *
	 * @since 1.7.5
	 *
	 * @param int $menu_id The nav menu term ID.
	 * @return array|\WP_Error Menu payload or WP_Error.
	 */
	public function get_nav_menu_payload( int $menu_id ) {
		$menu = wp_get_nav_menu_object( $menu_id );

		if ( ! $menu ) {
			return new \WP_Error(
				'menu_not_found',
				__( 'Navigation menu not found.', 'elementor-mcp' ),
				array( 'status' => 404 )
			);
		}

		$items = wp_get_nav_menu_items(
			$menu->term_id,
			array(
				'post_status' => 'any',
			)
		);

		if ( is_wp_error( $items ) ) {
			return $items;
		}

		$flat_items = array();
		foreach ( (array) $items as $item ) {
			$flat_items[] = $this->normalize_nav_menu_item( $item );
		}

		return array(
			'id'          => intval( $menu->term_id ),
			'name'        => (string) $menu->name,
			'slug'        => (string) $menu->slug,
			'description' => (string) $menu->description,
			'item_count'  => count( $flat_items ),
			'locations'   => $this->get_nav_menu_location_map()[ intval( $menu->term_id ) ] ?? array(),
			'items'       => $this->nest_nav_menu_items( $flat_items ),
			'flat_items'  => $flat_items,
		);
	}

	/**
	 * Updates safe core WordPress post fields without touching Elementor data.
	 *
	 * @since 1.4.4
	 *
	 * @param int   $post_id The post ID.
	 * @param array $fields  Allowed field updates.
	 * @return array|\WP_Error Result summary or WP_Error.
	 */
	public function update_post_fields( int $post_id, array $fields ) {
		$post = $this->get_elementor_post( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$postarr        = array( 'ID' => $post_id );
		$updated_fields = array();

		if ( array_key_exists( 'title', $fields ) ) {
			$postarr['post_title'] = sanitize_text_field( (string) $fields['title'] );
			$updated_fields[]      = 'title';
		}

		if ( array_key_exists( 'slug', $fields ) ) {
			$slug = sanitize_title( (string) $fields['slug'] );
			if ( '' === $slug ) {
				return new \WP_Error( 'invalid_slug', __( 'The slug field cannot be empty.', 'elementor-mcp' ) );
			}
			$postarr['post_name'] = $slug;
			$updated_fields[]     = 'slug';
		}

		if ( array_key_exists( 'status', $fields ) ) {
			$status           = sanitize_key( (string) $fields['status'] );
			$allowed_statuses = array( 'draft', 'publish', 'pending', 'private', 'future' );
			if ( ! in_array( $status, $allowed_statuses, true ) ) {
				return new \WP_Error( 'invalid_status', __( 'Invalid post status provided.', 'elementor-mcp' ) );
			}
			$postarr['post_status'] = $status;
			$updated_fields[]       = 'status';
		}

		if ( array_key_exists( 'excerpt', $fields ) ) {
			$postarr['post_excerpt'] = wp_kses_post( (string) $fields['excerpt'] );
			$updated_fields[]        = 'excerpt';
		}

		if ( array_key_exists( 'menu_order', $fields ) ) {
			$postarr['menu_order'] = intval( $fields['menu_order'] );
			$updated_fields[]      = 'menu_order';
		}

		if ( count( $postarr ) > 1 ) {
			$result = wp_update_post( $postarr, true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		if ( array_key_exists( 'featured_image', $fields ) ) {
			$featured_image = absint( $fields['featured_image'] );
			if ( $featured_image > 0 ) {
				$attachment = get_post( $featured_image );
				if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
					return new \WP_Error( 'invalid_featured_image', __( 'featured_image must be a valid attachment ID.', 'elementor-mcp' ) );
				}
				set_post_thumbnail( $post_id, $featured_image );
			} else {
				delete_post_thumbnail( $post_id );
			}
			$updated_fields[] = 'featured_image';
		}

		if ( array_key_exists( 'template', $fields ) ) {
			$template = sanitize_text_field( (string) $fields['template'] );
			if ( '' === $template || 'default' === $template ) {
				delete_post_meta( $post_id, '_wp_page_template' );
			} else {
				update_post_meta( $post_id, '_wp_page_template', $template );
			}
			$updated_fields[] = 'template';
		}

		if ( empty( $updated_fields ) ) {
			return new \WP_Error( 'missing_update_fields', __( 'No core post fields were provided to update.', 'elementor-mcp' ) );
		}

		$payload = $this->get_rich_page_payload( $post_id );

		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$payload['updated_fields'] = $updated_fields;

		return $payload;
	}

	/**
	 * Updates arbitrary post meta while protecting Elementor-owned internals.
	 *
	 * @since 1.4.4
	 *
	 * @param int      $post_id          The post ID.
	 * @param array    $meta             Meta updates.
	 * @param string[] $delete_meta_keys Meta keys to delete.
	 * @return array|\WP_Error Operation summary or WP_Error.
	 */
	public function update_post_meta_fields( int $post_id, array $meta = array(), array $delete_meta_keys = array() ) {
		$post = $this->get_elementor_post( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$blocked = array(
			'_elementor_data',
			'_elementor_page_settings',
			'_elementor_edit_mode',
			'_elementor_version',
			'_elementor_css',
		);

		$updated = array();
		$deleted = array();

		foreach ( $meta as $meta_key => $meta_value ) {
			$meta_key = sanitize_text_field( (string) $meta_key );

			if ( '' === $meta_key ) {
				continue;
			}

			if ( in_array( $meta_key, $blocked, true ) ) {
				return new \WP_Error(
					'protected_meta_key',
					sprintf(
						/* translators: %s: meta key */
						__( 'Meta key "%s" is managed by Elementor and cannot be updated directly.', 'elementor-mcp' ),
						$meta_key
					)
				);
			}

			update_post_meta( $post_id, $meta_key, $meta_value );
			$updated[] = $meta_key;
		}

		foreach ( $delete_meta_keys as $meta_key ) {
			$meta_key = sanitize_text_field( (string) $meta_key );

			if ( '' === $meta_key ) {
				continue;
			}

			if ( in_array( $meta_key, $blocked, true ) ) {
				return new \WP_Error(
					'protected_meta_key',
					sprintf(
						/* translators: %s: meta key */
						__( 'Meta key "%s" is managed by Elementor and cannot be deleted directly.', 'elementor-mcp' ),
						$meta_key
					)
				);
			}

			delete_post_meta( $post_id, $meta_key );
			$deleted[] = $meta_key;
		}

		if ( empty( $updated ) && empty( $deleted ) ) {
			return new \WP_Error( 'missing_meta_changes', __( 'No meta changes were provided.', 'elementor-mcp' ) );
		}

		return array(
			'post_id'           => $post_id,
			'updated_meta_keys' => $updated,
			'deleted_meta_keys' => $deleted,
		);
	}

	/**
	 * Creates a duplicate of an Elementor-built post, page, or supported CPT.
	 *
	 * Duplicates the core post record, taxonomies, and post meta, then refreshes
	 * Elementor CSS for the cloned document.
	 *
	 * @since 1.4.4
	 *
	 * @param int   $post_id The source post ID.
	 * @param array $options Duplicate options.
	 * @return array|\WP_Error Rich payload for the duplicate or WP_Error.
	 */
	public function duplicate_elementor_post( int $post_id, array $options = array() ) {
		$post = $this->get_elementor_post( $post_id );

		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$status = sanitize_key( (string) ( $options['status'] ?? 'draft' ) );
		if ( ! in_array( $status, array( 'draft', 'publish', 'pending', 'private' ), true ) ) {
			return new \WP_Error(
				'invalid_duplicate_status',
				__( 'Invalid duplicate status. Supported values: draft, publish, pending, private.', 'elementor-mcp' )
			);
		}

		$title_suffix = sanitize_text_field( (string) ( $options['title_suffix'] ?? '' ) );
		$new_title    = $post->post_title;
		if ( '' !== $title_suffix ) {
			$new_title .= ' ' . $title_suffix;
		}

		$current_user    = wp_get_current_user();
		$new_post_author = $current_user instanceof \WP_User && $current_user->ID ? $current_user->ID : intval( $post->post_author );

		$new_post_id = wp_insert_post(
			array(
				'comment_status' => $post->comment_status,
				'ping_status'    => $post->ping_status,
				'post_author'    => $new_post_author,
				'post_content'   => $post->post_content,
				'post_excerpt'   => $post->post_excerpt,
				'post_parent'    => $post->post_parent,
				'post_password'  => $post->post_password,
				'post_status'    => $status,
				'post_title'     => $new_title,
				'post_type'      => $post->post_type,
				'to_ping'        => $post->to_ping,
				'menu_order'     => $post->menu_order,
			),
			true
		);

		if ( is_wp_error( $new_post_id ) ) {
			return $new_post_id;
		}

		$copied_taxonomies = $this->copy_post_taxonomies( $post->ID, $new_post_id, $post->post_type );
		$copied_meta_keys  = $this->copy_post_meta( $post->ID, $new_post_id );

		// Elementor data integrity guard.
		//
		// copy_post_meta() round-trips every meta value through add_post_meta(),
		// which internally calls wp_unslash() on the value. For _elementor_data —
		// a long JSON string full of escape sequences like \", \/ and \uXXXX —
		// that unslash pass strips backslashes and silently corrupts the JSON,
		// leaving the duplicated page with an unreadable element tree that
		// renders as raw fallback HTML.
		//
		// To guarantee correctness we read the source element tree via the
		// Elementor document API (which always returns a clean array) and
		// re-save it onto the new post through save_page_data(), which routes
		// through $document->save() and triggers CSS regeneration.
		$source_elements = $this->get_page_data( $post->ID );
		$elementor_copy_status = 'skipped_empty_source';

		if ( is_array( $source_elements ) && ! empty( $source_elements ) ) {
			$save_result = $this->save_page_data( $new_post_id, $source_elements );

			if ( is_wp_error( $save_result ) ) {
				// Roll back the empty duplicate to avoid leaving an orphan page.
				wp_delete_post( $new_post_id, true );
				return new \WP_Error(
					'duplicate_elementor_save_failed',
					sprintf(
						/* translators: %s: underlying error message */
						__( 'Failed to copy Elementor element tree to duplicate: %s', 'elementor-mcp' ),
						$save_result->get_error_message()
					)
				);
			}

			// Verify the write actually landed — defends against silent corruption.
			$verify_raw = get_post_meta( $new_post_id, '_elementor_data', true );
			$verify_ok  = false;
			if ( is_string( $verify_raw ) && '' !== $verify_raw ) {
				$verify_decoded = json_decode( $verify_raw, true );
				$verify_ok      = is_array( $verify_decoded ) && ! empty( $verify_decoded );
			}

			if ( ! $verify_ok ) {
				wp_delete_post( $new_post_id, true );
				return new \WP_Error(
					'duplicate_elementor_verify_failed',
					__( 'Duplicate created but _elementor_data failed JSON integrity check after save.', 'elementor-mcp' )
				);
			}

			$elementor_copy_status = 'copied_via_document_save';
		}

		$this->refresh_elementor_css( $new_post_id );

		$payload = $this->get_rich_page_payload( $new_post_id );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$payload['source_post_id']        = $post->ID;
		$payload['copied_taxonomies']     = $copied_taxonomies;
		$payload['copied_meta_key_count'] = count( $copied_meta_keys );
		$payload['elementor_copy_status'] = $elementor_copy_status;

		return $payload;
	}

	/**
	 * Gets all registered Elementor widget types.
	 *
	 * @since 1.0.0
	 *
	 * @return \Elementor\Widget_Base[] Array of widget instances keyed by widget name.
	 */
	public function get_registered_widgets(): array {
		return \Elementor\Plugin::$instance->widgets_manager->get_widget_types();
	}

	/**
	 * Gets the controls for a specific widget type.
	 *
	 * @since 1.0.0
	 *
	 * @param string $widget_type The widget type name.
	 * @return array|\WP_Error The controls array or WP_Error if widget not found.
	 */
	public function get_widget_controls( string $widget_type ) {
		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );

		if ( ! $widget ) {
			return new \WP_Error(
				'widget_not_found',
				sprintf(
					/* translators: %s: widget type name */
					__( 'Widget type "%s" not found.', 'elementor-mcp' ),
					$widget_type
				)
			);
		}

		return $widget->get_controls();
	}

	/**
	 * Recursively searches for an element by ID within an element tree.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data The element tree array.
	 * @param string $id   The element ID to find.
	 * @return array|null The element array if found, null otherwise.
	 */
	public function find_element_by_id( array $data, string $id ): ?array {
		foreach ( $data as $element ) {
			if ( isset( $element['id'] ) && $element['id'] === $id ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = $this->find_element_by_id( $element['elements'], $id );
				if ( null !== $found ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Saves page data using Elementor's native save mechanism.
	 *
	 * Tries document save() first (triggers CSS regeneration). If that fails
	 * (e.g. non-browser context like WP-CLI or REST API), falls back to direct
	 * meta update and manual CSS cache invalidation.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id The post ID.
	 * @param array $data    The elements data array.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function save_page_data( int $post_id, array $data ) {
		$document = $this->get_document( $post_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		// Attempt native Elementor save (handles CSS regen, cache busting).
		$result = $document->save( array( 'elements' => $data ) );

		if ( false === $result ) {
			// Fallback: direct meta write for non-browser contexts (CLI, REST proxy).
			$json = wp_json_encode( $data );

			if ( false === $json ) {
				return new \WP_Error(
					'json_encode_failed',
					__( 'Failed to encode element data as JSON.', 'elementor-mcp' )
				);
			}

			update_post_meta( $post_id, '_elementor_data', wp_slash( $json ) );

			// Ensure Elementor meta flags are set.
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

			if ( defined( 'ELEMENTOR_VERSION' ) ) {
				update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
			}

			$this->invalidate_elementor_css_cache( $post_id );
		}

		return true;
	}

	/**
	 * Saves page-level settings.
	 *
	 * Tries native Elementor save first, falls back to direct meta for
	 * non-browser contexts (WP-CLI, REST API proxy).
	 *
	 * @since 1.0.0
	 *
	 * @param int   $post_id  The post ID.
	 * @param array $settings The page settings array.
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function save_page_settings( int $post_id, array $settings ) {
		$document = $this->get_document( $post_id );

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		$result = $document->save( array( 'settings' => $settings ) );

		if ( false === $result ) {
			// Fallback: merge settings into existing page settings meta.
			$existing = get_post_meta( $post_id, '_elementor_page_settings', true );
			if ( ! is_array( $existing ) ) {
				$existing = array();
			}

			$merged = array_merge( $existing, $settings );
			update_post_meta( $post_id, '_elementor_page_settings', $merged );

			$this->invalidate_elementor_css_cache( $post_id );
		}

		return true;
	}

	/**
	 * Inserts an element into the page data tree.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data      The element tree (passed by reference).
	 * @param string $parent_id The parent element ID. Empty string for top-level.
	 * @param array  $element   The element to insert.
	 * @param int    $position  The insertion position (-1 = append).
	 * @return bool True if inserted, false if parent not found.
	 */
	public function insert_element( array &$data, string $parent_id, array $element, int $position = -1 ): bool {
		// Top-level insertion.
		if ( empty( $parent_id ) ) {
			if ( $position < 0 || $position >= count( $data ) ) {
				$data[] = $element;
			} else {
				array_splice( $data, $position, 0, array( $element ) );
			}
			return true;
		}

		// Find parent and insert.
		foreach ( $data as &$item ) {
			if ( isset( $item['id'] ) && $item['id'] === $parent_id ) {
				if ( ! isset( $item['elements'] ) ) {
					$item['elements'] = array();
				}

				if ( $position < 0 || $position >= count( $item['elements'] ) ) {
					$item['elements'][] = $element;
				} else {
					array_splice( $item['elements'], $position, 0, array( $element ) );
				}

				return true;
			}

			if ( ! empty( $item['elements'] ) && is_array( $item['elements'] ) ) {
				if ( $this->insert_element( $item['elements'], $parent_id, $element, $position ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Removes an element from the page data tree.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data       The element tree (passed by reference).
	 * @param string $element_id The element ID to remove.
	 * @return bool True if removed, false if not found.
	 */
	public function remove_element( array &$data, string $element_id ): bool {
		foreach ( $data as $index => &$item ) {
			if ( isset( $item['id'] ) && $item['id'] === $element_id ) {
				array_splice( $data, $index, 1 );
				return true;
			}

			if ( ! empty( $item['elements'] ) && is_array( $item['elements'] ) ) {
				if ( $this->remove_element( $item['elements'], $element_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Recursively reassigns fresh IDs to all elements in a tree.
	 *
	 * @since 1.0.0
	 *
	 * @param array $elements The element tree.
	 * @return array The tree with new IDs.
	 */
	public function reassign_ids( array $elements ): array {
		foreach ( $elements as &$element ) {
			$element['id'] = Elementor_MCP_Id_Generator::generate();

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = $this->reassign_ids( $element['elements'] );
			}
		}

		return $elements;
	}

	/**
	 * Reassigns a fresh ID to a single element and all its children.
	 *
	 * @since 1.0.0
	 *
	 * @param array $element The element array.
	 * @return array The element with new IDs.
	 */
	public function reassign_element_ids( array $element ): array {
		$element['id'] = Elementor_MCP_Id_Generator::generate();

		if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
			$element['elements'] = $this->reassign_ids( $element['elements'] );
		}

		return $element;
	}

	/**
	 * Recursively counts all elements in a tree.
	 *
	 * @since 1.0.0
	 *
	 * @param array $elements The element tree.
	 * @return int Total count.
	 */
	public function count_elements( array $elements ): int {
		$count = count( $elements );

		foreach ( $elements as $element ) {
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$count += $this->count_elements( $element['elements'] );
			}
		}

		return $count;
	}

	/**
	 * Updates settings for a specific element in the tree.
	 *
	 * Modifies `$data` by reference. Returns true if element was found
	 * and updated, false if the element ID was not found.
	 *
	 * @since 1.0.0
	 *
	 * @param array  $data       The element tree (passed by reference).
	 * @param string $element_id The element ID to update.
	 * @param array  $settings   The settings to merge.
	 * @return bool True if updated, false if not found.
	 */
	public function update_element_settings( array &$data, string $element_id, array $settings ): bool {
		foreach ( $data as &$item ) {
			if ( isset( $item['id'] ) && $item['id'] === $element_id ) {
				if ( ! isset( $item['settings'] ) ) {
					$item['settings'] = array();
				}
				$item['settings'] = array_merge( $item['settings'], $settings );
				return true;
			}

			if ( ! empty( $item['elements'] ) && is_array( $item['elements'] ) ) {
				if ( $this->update_element_settings( $item['elements'], $element_id, $settings ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Resolves supported post types for page lookup tools.
	 *
	 * @since 1.4.4
	 *
	 * @param string $post_type Optional post type.
	 * @return array|\WP_Error Array of supported types or WP_Error.
	 */
	private function get_supported_post_types( string $post_type = '' ) {
		if ( '' === $post_type ) {
			return array( 'page', 'post' );
		}

		$post_type = sanitize_key( $post_type );

		if ( ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
			return new \WP_Error( 'invalid_post_type', __( 'Only "page" and "post" are supported for slug lookup.', 'elementor-mcp' ) );
		}

		return array( $post_type );
	}

	/**
	 * Formats slug match candidates for error output.
	 *
	 * @since 1.4.4
	 *
	 * @param \WP_Post[] $posts Candidate posts.
	 * @return array<int, array<string, mixed>> Candidate summaries.
	 */
	private function format_slug_candidates( array $posts ): array {
		return array_map(
			function ( \WP_Post $post ): array {
				return array(
					'post_id'   => $post->ID,
					'post_type' => $post->post_type,
					'title'     => $post->post_title,
					'slug'      => $post->post_name,
				);
			},
			$posts
		);
	}

	/**
	 * Gets featured image information for a post.
	 *
	 * @since 1.4.4
	 *
	 * @param int $post_id The post ID.
	 * @return array<string, mixed> Featured image payload.
	 */
	private function get_featured_image_data( int $post_id ): array {
		$image_id = get_post_thumbnail_id( $post_id );

		if ( ! $image_id ) {
			return array(
				'id'  => 0,
				'url' => '',
				'alt' => '',
			);
		}

		return array(
			'id'  => $image_id,
			'url' => wp_get_attachment_image_url( $image_id, 'full' ) ?: '',
			'alt' => (string) get_post_meta( $image_id, '_wp_attachment_image_alt', true ),
		);
	}

	/**
	 * Gets the page template slug for a post.
	 *
	 * @since 1.4.4
	 *
	 * @param int $post_id The post ID.
	 * @return string Template slug or "default".
	 */
	private function get_page_template( int $post_id ): string {
		$template = get_page_template_slug( $post_id );

		if ( empty( $template ) ) {
			return 'default';
		}

		return (string) $template;
	}

	/**
	 * Builds a lightweight template list item.
	 *
	 * @since 1.7.5
	 *
	 * @param \WP_Post $post Template post.
	 * @return array<string, mixed> Template summary.
	 */
	private function get_template_list_item( \WP_Post $post ): array {
		return array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'slug'     => $post->post_name,
			'status'   => $post->post_status,
			'type'     => $this->resolve_elementor_template_type( $post->ID ),
			'date'     => $post->post_date,
			'modified' => $post->post_modified,
		);
	}

	/**
	 * Resolves the canonical Elementor template type for a template post.
	 *
	 * @since 1.7.5
	 *
	 * @param int $post_id Template post ID.
	 * @return string Template type slug or empty string.
	 */
	private function resolve_elementor_template_type( int $post_id ): string {
		$template_type = (string) get_post_meta( $post_id, '_elementor_template_type', true );

		if ( '' !== $template_type ) {
			return $template_type;
		}

		$terms = $this->get_elementor_template_terms( $post_id );

		return $terms[0] ?? '';
	}

	/**
	 * Gets Elementor template type taxonomy terms for a template post.
	 *
	 * @since 1.7.5
	 *
	 * @param int $post_id Template post ID.
	 * @return string[] Template type slugs.
	 */
	private function get_elementor_template_terms( int $post_id ): array {
		$terms = wp_get_post_terms(
			$post_id,
			'elementor_library_type',
			array(
				'fields' => 'slugs',
			)
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return array();
		}

		return array_values( array_map( 'strval', $terms ) );
	}

	/**
	 * Gets normalized display conditions for a template.
	 *
	 * @since 1.7.5
	 *
	 * @param int $post_id Template post ID.
	 * @return array Display conditions.
	 */
	private function get_template_display_conditions( int $post_id ): array {
		$conditions = get_post_meta( $post_id, '_elementor_conditions', true );

		if ( is_array( $conditions ) ) {
			return $conditions;
		}

		return array();
	}

	/**
	 * Creates a simplified element tree for inspection UIs.
	 *
	 * @since 1.7.5
	 *
	 * @param array $elements Raw Elementor elements.
	 * @return array Simplified structure.
	 */
	private function simplify_element_structure( array $elements ): array {
		$result = array();

		foreach ( $elements as $element ) {
			$item = array(
				'id'     => $element['id'] ?? '',
				'elType' => $element['elType'] ?? '',
			);

			if ( ! empty( $element['widgetType'] ) ) {
				$item['widgetType'] = $element['widgetType'];
			}

			if ( ! empty( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$key_settings = $this->extract_structure_settings_summary( $element );
				if ( ! empty( $key_settings ) ) {
					$item['settings_summary'] = $key_settings;
				}
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$item['elements'] = $this->simplify_element_structure( $element['elements'] );
			}

			$result[] = $item;
		}

		return $result;
	}

	/**
	 * Extracts a short settings summary for structure responses.
	 *
	 * @since 1.7.5
	 *
	 * @param array $element Elementor element array.
	 * @return array<string, mixed> Short settings summary.
	 */
	private function extract_structure_settings_summary( array $element ): array {
		$settings = $element['settings'] ?? array();
		$summary  = array();

		foreach ( array( 'title', 'editor', 'text', 'image', 'link', 'html', 'header_size' ) as $field ) {
			if ( ! isset( $settings[ $field ] ) || '' === $settings[ $field ] ) {
				continue;
			}

			$value = $settings[ $field ];
			if ( is_string( $value ) && strlen( $value ) > 100 ) {
				$value = substr( $value, 0, 100 ) . '...';
			}
			$summary[ $field ] = $value;
		}

		if ( 'container' === ( $element['elType'] ?? '' ) ) {
			foreach ( array( 'flex_direction', 'content_width', 'container_type' ) as $field ) {
				if ( isset( $settings[ $field ] ) && '' !== $settings[ $field ] ) {
					$summary[ $field ] = $settings[ $field ];
				}
			}
		}

		return $summary;
	}

	/**
	 * Returns a map of menu term IDs to assigned theme locations.
	 *
	 * @since 1.7.5
	 *
	 * @return array<int, array<int, array<string, string>>> Menu location map.
	 */
	private function get_nav_menu_location_map(): array {
		$assigned   = get_nav_menu_locations();
		$registered = get_registered_nav_menus();
		$map        = array();

		foreach ( $assigned as $location => $menu_id ) {
			$menu_id = intval( $menu_id );
			if ( $menu_id <= 0 ) {
				continue;
			}

			if ( ! isset( $map[ $menu_id ] ) ) {
				$map[ $menu_id ] = array();
			}

			$map[ $menu_id ][] = array(
				'slug'  => (string) $location,
				'label' => (string) ( $registered[ $location ] ?? $location ),
			);
		}

		return $map;
	}

	/**
	 * Normalizes a WordPress nav menu item into a stable payload.
	 *
	 * @since 1.7.5
	 *
	 * @param \WP_Post $item Nav menu item post.
	 * @return array<string, mixed> Normalized menu item.
	 */
	private function normalize_nav_menu_item( \WP_Post $item ): array {
		$linked_object = $this->normalize_nav_menu_linked_object( $item );
		$item_classes  = array_values(
			array_filter(
				array_map(
					'strval',
					array_filter( (array) $item->classes )
				)
			)
		);

		return array(
			'id'                    => intval( $item->ID ),
			'db_id'                 => intval( $item->db_id ),
			'label'                 => (string) $item->title,
			'title'                 => (string) $item->title,
			'url'                   => (string) ( $item->url ?: ( $linked_object['url'] ?? '' ) ),
			'target'                => (string) $item->target,
			'attr_title'            => (string) $item->attr_title,
			'description'           => (string) $item->description,
			'classes'               => $item_classes,
			'xfn'                   => (string) $item->xfn,
			'menu_order'            => intval( $item->menu_order ),
			'parent_id'             => intval( $item->menu_item_parent ),
			'object_type'           => (string) $item->object,
			'object_id'             => intval( $item->object_id ),
			'type'                  => (string) $item->type,
			'type_label'            => (string) $item->type_label,
			'status'                => (string) $item->post_status,
			'invalid'               => ! empty( $item->invalid ),
			'missing_target_object' => empty( $linked_object['exists'] ),
			'linked_object'         => $linked_object,
		);
	}

	/**
	 * Resolves the object a nav menu item points to.
	 *
	 * @since 1.7.5
	 *
	 * @param \WP_Post $item Nav menu item post.
	 * @return array<string, mixed> Linked object payload.
	 */
	private function normalize_nav_menu_linked_object( \WP_Post $item ): array {
		$type      = (string) $item->type;
		$object    = (string) $item->object;
		$object_id = intval( $item->object_id );

		if ( 'custom' === $type ) {
			return array(
				'kind'   => 'custom',
				'type'   => 'custom',
				'id'     => 0,
				'title'  => (string) $item->title,
				'status' => '',
				'url'    => (string) $item->url,
				'exists' => true,
			);
		}

		if ( 'post_type_archive' === $type ) {
			return array(
				'kind'   => 'post_type_archive',
				'type'   => $object,
				'id'     => 0,
				'title'  => (string) $item->title,
				'status' => '',
				'url'    => get_post_type_archive_link( $object ) ?: (string) $item->url,
				'exists' => true,
			);
		}

		if ( 'taxonomy' === $type ) {
			$term = get_term( $object_id, $object );
			if ( $term && ! is_wp_error( $term ) ) {
				$term_link = get_term_link( $term );
				return array(
					'kind'   => 'term',
					'type'   => (string) $term->taxonomy,
					'id'     => intval( $term->term_id ),
					'title'  => (string) $term->name,
					'status' => '',
					'url'    => is_wp_error( $term_link ) ? '' : (string) $term_link,
					'exists' => true,
				);
			}

			return array(
				'kind'   => 'term',
				'type'   => $object,
				'id'     => $object_id,
				'title'  => (string) $item->title,
				'status' => '',
				'url'    => (string) $item->url,
				'exists' => false,
			);
		}

		$linked_post = $object_id > 0 ? get_post( $object_id ) : null;
		if ( $linked_post ) {
			return array(
				'kind'   => 'post',
				'type'   => (string) $linked_post->post_type,
				'id'     => intval( $linked_post->ID ),
				'title'  => (string) $linked_post->post_title,
				'status' => (string) $linked_post->post_status,
				'url'    => get_permalink( $linked_post->ID ) ?: '',
				'exists' => true,
			);
		}

		return array(
			'kind'   => 'post',
			'type'   => $object,
			'id'     => $object_id,
			'title'  => (string) $item->title,
			'status' => '',
			'url'    => (string) $item->url,
			'exists' => false,
		);
	}

	/**
	 * Builds a nested menu tree from a flat menu item list.
	 *
	 * @since 1.7.5
	 *
	 * @param array $items     Flat menu items.
	 * @param int   $parent_id Parent menu item ID.
	 * @return array Nested menu items.
	 */
	private function nest_nav_menu_items( array $items, int $parent_id = 0 ): array {
		$branch = array();

		foreach ( $items as $item ) {
			if ( intval( $item['parent_id'] ?? 0 ) !== $parent_id ) {
				continue;
			}

			$item['children'] = $this->nest_nav_menu_items( $items, intval( $item['id'] ?? 0 ) );
			$branch[]         = $item;
		}

		return $branch;
	}

	/**
	 * Copies all object taxonomies from one post to another.
	 *
	 * @since 1.4.4
	 *
	 * @param int    $source_post_id The source post ID.
	 * @param int    $target_post_id The target post ID.
	 * @param string $post_type      The post type.
	 * @return string[] Copied taxonomy slugs.
	 */
	private function copy_post_taxonomies( int $source_post_id, int $target_post_id, string $post_type ): array {
		$copied     = array();
		$taxonomies = get_object_taxonomies( $post_type, 'names' );

		if ( empty( $taxonomies ) || ! is_array( $taxonomies ) ) {
			return $copied;
		}

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $source_post_id, $taxonomy, array( 'fields' => 'ids' ) );

			if ( is_wp_error( $terms ) ) {
				continue;
			}

			wp_set_object_terms( $target_post_id, $terms, $taxonomy, false );
			$copied[] = $taxonomy;
		}

		return $copied;
	}

	/**
	 * Copies post meta from one post to another.
	 *
	 * Skips transient/editor lock keys that should not follow the duplicate.
	 *
	 * @since 1.4.4
	 *
	 * @param int $source_post_id The source post ID.
	 * @param int $target_post_id The target post ID.
	 * @return string[] Copied meta keys.
	 */
	private function copy_post_meta( int $source_post_id, int $target_post_id ): array {
		$all_meta       = get_post_meta( $source_post_id );
		$copied_meta    = array();
		$blocked_keys   = array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_elementor_css',
			// _elementor_data is re-saved via the document API in duplicate_elementor_post()
			// so we skip it here to avoid JSON corruption caused by WordPress's
			// internal wp_unslash() pass inside add_metadata().
			'_elementor_data',
		);

		foreach ( $all_meta as $meta_key => $meta_values ) {
			if ( in_array( $meta_key, $blocked_keys, true ) ) {
				continue;
			}

			foreach ( (array) $meta_values as $meta_value ) {
				$unserialized = maybe_unserialize( $meta_value );
				// wp_slash() compensates for the wp_unslash() pass inside add_metadata()
				// so backslash-containing strings (escaped quotes, unicode escapes, JSON)
				// survive the round-trip intact. wp_slash() handles arrays recursively.
				add_post_meta( $target_post_id, $meta_key, wp_slash( $unserialized ) );
			}

			$copied_meta[] = $meta_key;
		}

		return $copied_meta;
	}

	/**
	 * Regenerates Elementor CSS when possible, falling back to cache invalidation.
	 *
	 * @since 1.4.4
	 *
	 * @param int $post_id The post ID.
	 */
	private function refresh_elementor_css( int $post_id ): void {
		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			try {
				\Elementor\Core\Files\CSS\Post::create( $post_id )->update();
				return;
			} catch ( \Throwable $e ) {
				// Fall back to cache invalidation below.
			}
		}

		$this->invalidate_elementor_css_cache( $post_id );
	}

	/**
	 * Invalidates Elementor CSS meta and generated file cache for a post.
	 *
	 * @since 1.4.4
	 *
	 * @param int $post_id The post ID.
	 */
	private function invalidate_elementor_css_cache( int $post_id ): void {
		delete_post_meta( $post_id, '_elementor_css' );

		$upload_dir = wp_get_upload_dir();
		if ( empty( $upload_dir['basedir'] ) ) {
			return;
		}

		$css_path = $upload_dir['basedir'] . '/elementor/css/post-' . $post_id . '.css';
		if ( file_exists( $css_path ) ) {
			wp_delete_file( $css_path );
		}
	}
}
