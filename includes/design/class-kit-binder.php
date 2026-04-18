<?php
/**
 * Kit Binder — writes brand palette/typography tokens into the active
 * Elementor Kit as named system colors.
 *
 * Binding lets patterns emit __globals__ references to kit IDs instead
 * of hex literals, so pages inherit future kit updates. When Elementor
 * Kit isn't available, returns empty globals map and patterns fall back
 * to hex literals.
 *
 * Non-destructive: only appends slots that don't already exist in the kit
 * (dedupe by slot title).
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Binds brand tokens into the active Elementor Kit.
 *
 * @since 1.5.0
 */
class Elementor_MCP_Kit_Binder {

	/**
	 * Binds a palette to the active kit's system_colors.
	 *
	 * Returns a slot => global_id map usable by Token_Resolver for
	 * emitting __globals__ references.
	 *
	 * @param string $name Palette name (e.g. 'desert-warm').
	 * @return array<string, string> Slot name => kit global color ID.
	 */
	public function bind_palette( string $name ): array {
		if ( empty( $name ) ) {
			return array();
		}

		$palettes = emcp_tokens_palettes();
		if ( ! isset( $palettes[ $name ] ) ) {
			return array();
		}

		return $this->bind_palette_array( $name, $palettes[ $name ] );
	}

	/**
	 * Binds an inline palette array (not looked up by name from
	 * `emcp_tokens_palettes()`). Used by Design_Importer which
	 * extracts palette slots from a design's CSS `:root` on the fly.
	 *
	 * Same append-to-kit semantics as `bind_palette()` — dedupe by
	 * title, only append missing slots, persist via
	 * `_elementor_page_settings` postmeta.
	 *
	 * @since 1.5.0
	 *
	 * @param string                $name    Palette identifier (used as title prefix for dedupe).
	 * @param array<string, string> $palette Slot name => hex color.
	 * @return array<string, string> Slot name => kit global color ID.
	 */
	public function bind_palette_array( string $name, array $palette ): array {
		if ( empty( $name ) || empty( $palette ) ) {
			return array();
		}

		$kit = $this->get_active_kit();
		if ( ! $kit ) {
			return array();
		}

		$settings = $kit->get_settings();
		$colors   = is_array( $settings['system_colors'] ?? null ) ? $settings['system_colors'] : array();
		$index    = $this->index_colors_by_title( $colors );

		$globals    = array();
		$appended   = false;
		$slot_order = array( 'primary', 'secondary', 'accent', 'text', 'text-muted', 'surface', 'surface-alt', 'border', 'text-inverse', 'text-inverse-muted' );

		foreach ( $slot_order as $slot ) {
			if ( ! isset( $palette[ $slot ] ) ) {
				continue;
			}

			$title = $this->slot_title( $name, $slot );

			if ( isset( $index[ $title ] ) ) {
				$globals[ $slot ] = $index[ $title ]['_id'];
				continue;
			}

			$new_id   = substr( md5( $name . '_' . $slot ), 0, 7 );
			$colors[] = array(
				'_id'   => $new_id,
				'title' => $title,
				'color' => $palette[ $slot ],
			);
			$globals[ $slot ] = $new_id;
			$appended         = true;
		}

		if ( $appended ) {
			$settings['system_colors'] = $colors;
			$this->save_kit_settings( $kit, $settings );
		}

		return $globals;
	}

	/**
	 * Returns the active Elementor Kit document, or null.
	 */
	private function get_active_kit() {
		if ( ! class_exists( '\\Elementor\\Plugin' ) ) {
			return null;
		}

		$kits_manager = \Elementor\Plugin::$instance->kits_manager ?? null;
		if ( ! $kits_manager ) {
			return null;
		}

		$kit_id = $kits_manager->get_active_id();
		if ( ! $kit_id ) {
			return null;
		}

		$document = \Elementor\Plugin::$instance->documents->get( $kit_id );
		return $document ? $document : null;
	}

	/**
	 * Indexes system_colors array by title for quick dedupe lookup.
	 */
	private function index_colors_by_title( array $colors ): array {
		$index = array();
		foreach ( $colors as $row ) {
			if ( isset( $row['title'] ) ) {
				$index[ $row['title'] ] = $row;
			}
		}
		return $index;
	}

	/**
	 * Builds the deterministic kit title for a palette slot.
	 */
	private function slot_title( string $name, string $slot ): string {
		return 'EMCP · ' . $name . ' · ' . $slot;
	}

	/**
	 * Saves kit settings. Elementor's Document::save() requires both
	 * `elements` and `settings` — passing settings-only silently no-ops.
	 * For kits we write `_elementor_page_settings` postmeta directly
	 * (which is exactly what Kit documents read on load) and then
	 * flush Elementor CSS caches so new globals propagate.
	 */
	private function save_kit_settings( $kit, array $settings ): void {
		if ( ! is_object( $kit ) || ! method_exists( $kit, 'get_main_id' ) ) {
			return;
		}
		$kit_id = $kit->get_main_id();
		if ( ! $kit_id ) {
			return;
		}

		update_post_meta( $kit_id, '_elementor_page_settings', $settings );

		// Purge kit + global CSS so fresh globals apply on next page render.
		if ( class_exists( '\\Elementor\\Plugin' ) ) {
			$plugin = \Elementor\Plugin::$instance;
			if ( ! empty( $plugin->files_manager ) && method_exists( $plugin->files_manager, 'clear_cache' ) ) {
				$plugin->files_manager->clear_cache();
			}
		}
	}
}
