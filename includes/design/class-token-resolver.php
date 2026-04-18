<?php
/**
 * Design Token Resolver.
 *
 * Single entry point for resolving design tokens (palette slots,
 * typography sizes, spacing scales, effects) into Elementor control
 * settings fragments. Pattern library functions call into this class
 * to stay implementation-agnostic.
 *
 * When a palette has been bound to the active Elementor Kit via
 * Kit_Binder, color lookups emit __globals__ references so pages
 * inherit kit updates automatically. Falls back to hex literals when
 * no binding exists.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves design tokens to Elementor settings.
 *
 * @since 1.5.0
 */
class Elementor_MCP_Token_Resolver {

	/**
	 * Singleton instance.
	 *
	 * @var Elementor_MCP_Token_Resolver|null
	 */
	private static $instance = null;

	/**
	 * Active palette name for this resolver instance.
	 *
	 * @var string
	 */
	private $palette = '';

	/**
	 * Active typography scale name.
	 *
	 * @var string
	 */
	private $typography = '';

	/**
	 * Kit global ID map for the active palette (slot => global_id).
	 * When populated, color resolution uses __globals__ refs.
	 *
	 * @var array<string, string>
	 */
	private $color_globals = array();

	/**
	 * Extracted CSS `:root` tokens from the design (set when Design
	 * Importer runs). Shape matches `emcp_tokens_css_var_extract()`.
	 *
	 * @since 1.5.0
	 * @var array
	 */
	private $css_tokens = array();

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
	 * Resets the resolver for a new compile pass.
	 *
	 * @param string                $palette       Palette name.
	 * @param string                $typography    Typography scale name.
	 * @param array<string, string> $color_globals Slot => kit global ID map.
	 */
	public function configure( string $palette, string $typography, array $color_globals = array() ): void {
		$this->palette       = $palette;
		$this->typography    = $typography;
		$this->color_globals = $color_globals;
	}

	/**
	 * Gets the active palette name.
	 */
	public function palette(): string {
		return $this->palette;
	}

	/**
	 * Resolves a color slot to Elementor settings for a given setting key.
	 *
	 * @param string $setting_key Elementor control ID (e.g. 'title_color').
	 * @param string $slot        Palette slot (e.g. 'primary').
	 * @return array Settings fragment.
	 */
	public function color( string $setting_key, string $slot ): array {
		if ( ! empty( $this->color_globals[ $slot ] ) ) {
			return array(
				$setting_key  => '',
				'__globals__' => array( $setting_key => 'globals/colors?id=' . $this->color_globals[ $slot ] ),
			);
		}

		$hex = $this->palette ? emcp_tokens_palette_get( $this->palette, $slot ) : null;
		if ( null === $hex ) {
			return array();
		}
		return array( $setting_key => $hex );
	}

	/**
	 * Resolves a typography token.
	 */
	public function typography( string $size ): array {
		if ( empty( $this->typography ) ) {
			return array();
		}
		return emcp_tokens_typography_settings( $this->typography, $size );
	}

	/**
	 * Resolves a spacing token.
	 */
	public function spacing( string $token, string $key = 'padding' ): array {
		return emcp_tokens_spacing_settings( $token, $key );
	}

	/**
	 * Resolves a gap token.
	 */
	public function gap( int $desktop ): array {
		return emcp_tokens_gap_settings( $desktop );
	}

	/**
	 * Resolves a shadow token.
	 */
	public function shadow( string $token ): array {
		return emcp_tokens_shadow_settings( $token );
	}

	/**
	 * Resolves a border-radius token.
	 */
	public function radius( string $token ): array {
		return emcp_tokens_radius_settings( $token );
	}

	/**
	 * Resolves a container overlay token.
	 */
	public function overlay( string $token, float $opacity = 0.55 ): array {
		return emcp_tokens_overlay_settings( $token, $opacity );
	}

	/**
	 * Builds background-image settings from an image array.
	 */
	public function background_image( ?array $image ): array {
		return emcp_tokens_background_image( $image );
	}

	/**
	 * Builds shape divider settings. If $color omitted, auto-uses the
	 * palette's `surface` slot so the divider matches our standard
	 * content-section background tone (no visible gap strip).
	 */
	public function shape_divider( string $shape, string $position = 'bottom', bool $negative = true, string $color = '' ): array {
		if ( '' === $color ) {
			$color = $this->palette ? ( emcp_tokens_palette_get( $this->palette, 'surface' ) ?? '#FFFFFF' ) : '#FFFFFF';
		}
		return emcp_tokens_shape_divider( $shape, $position, $negative, $color );
	}

	/**
	 * Resolves a button variant to widget settings.
	 */
	public function button( string $variant ): array {
		return emcp_tokens_button_settings( $variant, $this->palette );
	}

	/**
	 * Installs CSS-var tokens extracted from a design's `<style>`
	 * block (produced by `emcp_tokens_css_var_extract()`). Used by
	 * Design_Importer so `resolve_css_var()` can resolve literal
	 * `--sunset`-style var references to hex values.
	 *
	 * @since 1.5.0
	 *
	 * @param array $tokens Shape: {palette, typography, spacing, radii, shadows, gradients, raw}.
	 */
	public function set_css_tokens( array $tokens ): void {
		$this->css_tokens = $tokens;
	}

	/**
	 * Looks up a raw CSS var value from the extracted tokens.
	 * Returns null when the var isn't present. Strips leading `--`.
	 *
	 * @since 1.5.0
	 *
	 * @param string $var_name CSS var name (with or without leading `--`).
	 * @return string|null
	 */
	public function resolve_css_var( string $var_name ): ?string {
		if ( empty( $this->css_tokens ) ) {
			return null;
		}
		if ( function_exists( 'emcp_tokens_css_var_value' ) ) {
			return emcp_tokens_css_var_value( $this->css_tokens, $var_name );
		}
		$name = ltrim( strtolower( $var_name ), '-' );
		return $this->css_tokens['raw'][ $name ] ?? null;
	}
}
