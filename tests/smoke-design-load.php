<?php
/**
 * Smoke test for Design Pipeline Phase 0.
 *
 * Loads new design-pipeline files directly (bypassing WordPress) and
 * asserts that classes, functions, and token resolutions all work.
 *
 * Run: php tests/smoke-design-load.php
 * Exit code 0 = pass, non-zero = fail.
 *
 * Not a PHPUnit test — intentionally dependency-free.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'ELEMENTOR_MCP_DIR', __DIR__ . '/../' );

// WP function stubs.
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return $url;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $str ) {
		return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $str ) {
		return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) {
		return trim( strip_tags( (string) $str ) );
	}
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		$key = strtolower( (string) $key );
		return preg_replace( '/[^a-z0-9_\-]/', '', $key );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = null ) {
		return $text;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $tag, $value ) {
		return $value;
	}
}
if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		public $code;
		public $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof WP_Error;
	}
}

$fail = 0;
$pass = 0;

function emcp_assert( $cond, $label ) {
	global $fail, $pass;
	if ( $cond ) {
		echo "  PASS  $label\n";
		$pass++;
	} else {
		echo "  FAIL  $label\n";
		$fail++;
	}
}

echo "=== Phase 0 Smoke Test ===\n\n";

// 1. Load files.
require_once __DIR__ . '/../includes/design/helpers/array-merge.php';
require_once __DIR__ . '/../includes/design/helpers/responsive.php';
require_once __DIR__ . '/../includes/design/tokens/palettes.php';
require_once __DIR__ . '/../includes/design/tokens/typography.php';
require_once __DIR__ . '/../includes/design/tokens/spacing.php';
require_once __DIR__ . '/../includes/design/tokens/effects.php';
require_once __DIR__ . '/../includes/design/tokens/buttons.php';
require_once __DIR__ . '/../includes/design/class-token-resolver.php';
require_once __DIR__ . '/../includes/design/class-kit-binder.php';
require_once __DIR__ . '/../includes/design/class-pattern-registry.php';

// 2. Helpers.
echo "Helpers:\n";
$merged = emcp_array_deep_merge(
	array( 'a' => 1, 'nested' => array( 'x' => 1 ) ),
	array( 'b' => 2, 'nested' => array( 'y' => 2 ) )
);
emcp_assert(
	isset( $merged['a'] ) && isset( $merged['b'] ) && 1 === $merged['nested']['x'] && 2 === $merged['nested']['y'],
	'deep merge combines scalars + recurses nested'
);
emcp_assert( true === emcp_is_assoc_array( array( 'x' => 1 ) ), 'assoc detection: positive' );
emcp_assert( false === emcp_is_assoc_array( array( 1, 2, 3 ) ), 'assoc detection: negative' );

$rs = emcp_responsive_size( 'typography_font_size', 72.0 );
emcp_assert(
	isset( $rs['typography_font_size_tablet'] ) && isset( $rs['typography_font_size_mobile'] ),
	'responsive size has tablet+mobile'
);

// 3. Palettes.
echo "\nPalettes:\n";
$p = emcp_tokens_palettes();
emcp_assert( isset( $p['desert-warm']['primary'] ) && '#D2691E' === $p['desert-warm']['primary'], 'desert-warm.primary present' );
emcp_assert( '#2563EB' === emcp_tokens_palette_get( 'modern-clean', 'primary' ), 'palette_get works' );
emcp_assert( null === emcp_tokens_palette_get( 'nonexistent', 'primary' ), 'unknown palette returns null' );

// 4. Typography.
echo "\nTypography:\n";
$t = emcp_tokens_typography_settings( 'editorial-classic', 'display-xl' );
emcp_assert( 'Playfair Display' === $t['typography_font_family'], 'editorial uses Playfair' );
emcp_assert( isset( $t['typography_font_size_mobile'] ), 'typography emits mobile variant' );
emcp_assert( array() === emcp_tokens_typography_settings( 'missing', 'display-xl' ), 'unknown scale returns empty' );

// 5. Spacing.
echo "\nSpacing:\n";
$s = emcp_tokens_spacing_settings( 'section.lg' );
emcp_assert( isset( $s['padding_mobile']['top'] ), 'spacing emits mobile padding' );
emcp_assert( array() === emcp_tokens_spacing_settings( 'nonexistent.key' ), 'unknown spacing returns empty' );

// 6. Effects.
echo "\nEffects:\n";
$sh = emcp_tokens_shadow_settings( 'soft' );
emcp_assert( isset( $sh['box_shadow_box_shadow'] ), 'shadow resolves' );
$r = emcp_tokens_radius_settings( 'card' );
emcp_assert( '16' === (string) $r['border_radius']['top'], 'radius.card = 16' );
$ov = emcp_tokens_overlay_settings( 'dark-gradient' );
emcp_assert( 'gradient' === $ov['background_overlay_background'], 'dark-gradient overlay' );

// 7. Buttons.
echo "\nButtons:\n";
$b = emcp_tokens_button_settings( 'primary-large', 'desert-warm' );
emcp_assert( '#D2691E' === $b['background_color'], 'primary-large uses desert-warm primary' );

// 8. Token resolver.
echo "\nToken Resolver:\n";
$resolver = Elementor_MCP_Token_Resolver::instance();
$resolver->configure( 'desert-warm', 'editorial-classic', array() );
$c = $resolver->color( 'title_color', 'text-inverse' );
emcp_assert( '#FFF8F1' === $c['title_color'], 'resolver emits hex when no globals' );

$resolver->configure( 'desert-warm', 'editorial-classic', array( 'primary' => 'abc1234' ) );
$c2 = $resolver->color( 'title_color', 'primary' );
emcp_assert(
	isset( $c2['__globals__']['title_color'] ) && 'globals/colors?id=abc1234' === $c2['__globals__']['title_color'],
	'resolver emits __globals__ when bound'
);

// 9. Pattern registry.
echo "\nPattern Registry:\n";
$reg = Elementor_MCP_Pattern_Registry::instance();
$reg->discover();
emcp_assert( is_array( $reg->all() ), 'registry returns array' );
emcp_assert( null === $reg->get( 'nonexistent.pattern' ), 'unknown pattern returns null' );

// 10. Phase 1 + 2 patterns — ensure all discovered.
echo "\nPhase 1 + 2 Patterns:\n";
$expected_patterns = array(
	// Phase 1
	'hero.post-article',
	'content.post-body',
	'content.author-bio',
	'content.related-posts',
	'cta.banner-full-width',
	// Phase 2
	'hero.minimal-center',
	'hero.split-image-right',
	'hero.gradient-mesh',
	'features.icon-grid-3col',
	'features.card-grid-4col',
	'features.alternating-image-text',
	'features.checklist-2col',
	'stats-bar.4-up',
	'logo-cloud.grayscale',
	'testimonial.carousel',
	'pricing.3-tier',
	'faq.accordion-centered',
	'gallery.masonry',
);
foreach ( $expected_patterns as $name ) {
	$d = $reg->get( $name );
	emcp_assert( null !== $d && is_array( $d ), "pattern '$name' registered" );
	if ( $d ) {
		emcp_assert( function_exists( $d['callback'] ), "callback '{$d['callback']}' exists" );
	}
}

// 11. Pattern compilation smoke — invoke each pattern and validate shape.
echo "\nPattern Compilation:\n";
$resolver->configure( 'desert-warm', 'editorial-classic', array() );

$hero_out = call_user_func(
	$reg->get( 'hero.post-article' )['callback'],
	array(
		'headline'       => 'Test Post',
		'category_label' => 'Guide',
		'meta_items'     => array( 'Dec 2026', '8 min read' ),
		'use_dynamic'    => false,
	),
	$resolver
);
emcp_assert( 'container' === ( $hero_out['type'] ?? '' ), 'hero returns container' );
// Hero uses nested structure: outer container → single inner column → content children.
emcp_assert( is_array( $hero_out['children'] ?? null ) && count( $hero_out['children'] ) >= 1, 'hero has children' );

$body_out = call_user_func(
	$reg->get( 'content.post-body' )['callback'],
	array( 'use_dynamic' => false, 'body_html' => '<p>Hello</p>' ),
	$resolver
);
emcp_assert( 'container' === ( $body_out['type'] ?? '' ), 'post-body returns container' );

$author_out = call_user_func(
	$reg->get( 'content.author-bio' )['callback'],
	array( 'use_dynamic' => false, 'author_name' => 'Jane Doe', 'author_bio' => 'Writer.' ),
	$resolver
);
emcp_assert( 'container' === ( $author_out['type'] ?? '' ), 'author-bio returns container' );

$rel_out = call_user_func(
	$reg->get( 'content.related-posts' )['callback'],
	array( 'heading' => 'More reads', 'columns' => 3, 'count' => 3 ),
	$resolver
);
emcp_assert( 'container' === ( $rel_out['type'] ?? '' ), 'related-posts returns container' );

$cta_out = call_user_func(
	$reg->get( 'cta.banner-full-width' )['callback'],
	array(
		'headline'      => 'Book now',
		'subhead'       => 'Limited slots',
		'primary_label' => 'Book',
		'primary_url'   => 'https://example.com/book',
	),
	$resolver
);
emcp_assert( 'container' === ( $cta_out['type'] ?? '' ), 'cta returns container' );

// Summary.
echo "\n=== Results: $pass pass / $fail fail ===\n";
exit( $fail > 0 ? 1 : 0 );
