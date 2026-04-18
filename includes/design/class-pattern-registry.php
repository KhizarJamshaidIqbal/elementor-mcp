<?php
/**
 * Pattern Registry — discovers and registers design patterns.
 *
 * Each pattern is a PHP file in includes/design/patterns/ that defines
 * one function `emcp_pattern_{slug_with_underscores}` taking ($slots, $resolver)
 * and returning a `build-page`-compatible element tree fragment. Each
 * file may also define a `{callback}_meta()` function returning
 * descriptor metadata (category, description, slots schema) for the
 * list-patterns tool.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and loads design patterns on demand.
 *
 * @since 1.5.0
 */
class Elementor_MCP_Pattern_Registry {

	/**
	 * Singleton.
	 *
	 * @var Elementor_MCP_Pattern_Registry|null
	 */
	private static $instance = null;

	/**
	 * Registered pattern metadata, keyed by pattern name.
	 *
	 * @var array<string, array>
	 */
	private $patterns = array();

	/**
	 * Whether discovery has run.
	 *
	 * @var bool
	 */
	private $discovered = false;

	/**
	 * Singleton accessor.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Discovers patterns in includes/design/patterns/.
	 * Safe to call multiple times — runs once.
	 */
	public function discover(): void {
		if ( $this->discovered ) {
			return;
		}
		$this->discovered = true;

		$dir = ELEMENTOR_MCP_DIR . 'includes/design/patterns';
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = glob( $dir . '/*.php' );
		if ( ! is_array( $files ) ) {
			return;
		}

		foreach ( $files as $file ) {
			require_once $file;

			$basename = basename( $file, '.php' );
			$callback = $this->filename_to_callback( $basename );
			$meta_fn  = $callback . '_meta';

			if ( ! function_exists( $callback ) ) {
				continue;
			}

			$meta = function_exists( $meta_fn )
				? call_user_func( $meta_fn )
				: array();

			// Pattern name precedence: meta['name'] override → filename derivation.
			$pattern_name = isset( $meta['name'] ) && is_string( $meta['name'] ) && '' !== $meta['name']
				? $meta['name']
				: $this->filename_to_pattern_name( $basename );

			$this->patterns[ $pattern_name ] = array(
				'name'        => $pattern_name,
				'category'    => $meta['category'] ?? $this->infer_category( $pattern_name ),
				'description' => $meta['description'] ?? '',
				'slots'       => is_array( $meta['slots'] ?? null ) ? $meta['slots'] : array(),
				'callback'    => $callback,
				'file'        => $file,
			);
		}

		/**
		 * Filters the registered patterns.
		 */
		$this->patterns = apply_filters( 'elementor_mcp_patterns', $this->patterns );
	}

	/**
	 * Returns all pattern metadata.
	 */
	public function all(): array {
		$this->discover();
		return $this->patterns;
	}

	/**
	 * Returns a single pattern descriptor or null.
	 */
	public function get( string $name ): ?array {
		$this->discover();
		return $this->patterns[ $name ] ?? null;
	}

	/**
	 * Converts `hero-overlay-waves` filename to `hero.overlay-waves` pattern name.
	 */
	private function filename_to_pattern_name( string $basename ): string {
		$pos = strpos( $basename, '-' );
		if ( false === $pos ) {
			return $basename;
		}
		return substr( $basename, 0, $pos ) . '.' . substr( $basename, $pos + 1 );
	}

	/**
	 * Converts `hero-overlay-waves` filename to `emcp_pattern_hero_overlay_waves`.
	 */
	private function filename_to_callback( string $basename ): string {
		return 'emcp_pattern_' . str_replace( '-', '_', $basename );
	}

	/**
	 * Infers category from pattern name prefix.
	 */
	private function infer_category( string $pattern_name ): string {
		$pos = strpos( $pattern_name, '.' );
		if ( false === $pos ) {
			return 'misc';
		}
		return substr( $pattern_name, 0, $pos );
	}
}
