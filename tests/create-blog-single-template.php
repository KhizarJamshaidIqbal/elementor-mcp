<?php
/**
 * One-off bootstrap — creates the Blog Single-Post theme template.
 *
 * Loads WordPress, fires plugins_loaded so the Elementor_MCP plugin
 * registers its design abilities, then invokes execute_design_theme_template
 * directly (bypassing MCP transport) to build a modern blog single-post
 * template and set display conditions to all blog posts.
 *
 * Re-run safe: checks if a template with the target title already
 * exists and skips creation unless --force is passed.
 *
 * Usage: php tests/create-blog-single-template.php [--force]
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Run from CLI only.\n" );
}

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	exit( "wp-load.php not found at $wp_load\n" );
}

// Pre-define DB_HOST for Local CLI invocations (Local uses non-standard MySQL port).
// If env var EMCP_DB_HOST is set, use it; else try 127.0.0.1:10024 (Local default).
if ( ! defined( 'DB_HOST' ) ) {
	define( 'DB_HOST', getenv( 'EMCP_DB_HOST' ) ?: '127.0.0.1:10024' );
}

define( 'WP_USE_THEMES', false );
require $wp_load;

$force        = in_array( '--force', $argv ?? array(), true );
$target_title = 'EMCP · Blog Single Post';

// Dedupe check — find all prior matches (could be multiple from stacked --force runs).
$existing = get_posts(
	array(
		'post_type'      => 'elementor_library',
		'post_status'    => 'any',
		'title'          => $target_title,
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
if ( ! empty( $existing ) ) {
	if ( ! $force ) {
		$id = (int) $existing[0];
		printf( "Template already exists: ID=%d (and %d total duplicates)\n", $id, count( $existing ) );
		printf( "  edit: %s\n", admin_url( 'post.php?post=' . $id . '&action=elementor' ) );
		printf( "Pass --force to delete all and rebuild.\n" );
		exit( 0 );
	}
	// --force path: delete ALL prior matches before creating a fresh one.
	foreach ( $existing as $old_id ) {
		$deleted = wp_delete_post( (int) $old_id, true );
		printf( "  deleted prior template: ID=%d (%s)\n", (int) $old_id, $deleted ? 'ok' : 'fail' );
	}
}

if ( ! class_exists( 'Elementor_MCP_Design_Abilities' ) ) {
	exit( "Elementor_MCP_Design_Abilities class not available. Is the plugin active?\n" );
}

// Acquire the dependency graph the registrar uses.
$data       = new Elementor_MCP_Data();
$factory    = new Elementor_MCP_Element_Factory();
$composite  = new Elementor_MCP_Composite_Abilities( $data, $factory );
$kit_binder = new Elementor_MCP_Kit_Binder();
$registry   = Elementor_MCP_Pattern_Registry::instance();
$compiler   = new Elementor_MCP_Design_Compiler( $kit_binder, $registry, null );
$design     = new Elementor_MCP_Design_Abilities( $data, $factory, $composite, $compiler, $registry );

// Modern blog single-post IR — 5 patterns, desert-warm, editorial-classic.
$ir = array(
	'title'         => $target_title,
	'template_type' => 'single-post',
	'brand_tokens'  => array(
		'palette'    => 'desert-warm',
		'typography' => 'editorial-classic',
	),
	'sections'      => array(
		array(
			'pattern' => 'hero.post-article',
			'slots'   => array(
				'use_dynamic'    => true,
				'category_label' => 'From the Blog',
				'meta_items'     => array( 'Dubai Safari Team', 'Travel Guide' ),
			),
		),
		array(
			'pattern' => 'content.post-body',
			'slots'   => array(
				'use_dynamic' => true,
				'max_width'   => 760,
			),
		),
		array(
			'pattern' => 'content.author-bio',
			'slots'   => array(
				'use_dynamic' => true,
			),
		),
		array(
			'pattern' => 'content.related-posts',
			'slots'   => array(
				'heading' => 'You may also like',
				'columns' => 3,
				'count'   => 3,
			),
		),
		array(
			'pattern' => 'cta.banner-full-width',
			'slots'   => array(
				'eyebrow'            => 'BOOK YOUR ADVENTURE',
				'headline'           => 'Ready for Your Desert Adventure?',
				'subhead'            => 'Join 1,000+ travelers exploring Dubai\'s red dunes. Expert guides, free hotel transfer, DTCM licensed.',
				'primary_label'      => 'Book on WhatsApp',
				'primary_url'        => 'https://wa.me/971524409525',
				'secondary_label'    => 'Browse Tours',
				'secondary_url'      => '/desert-safari-dubai/',
				'primary_icon'       => 'fab fa-whatsapp',
				'secondary_icon'     => 'fas fa-arrow-right',
				'trust_stats'        => array(
					array( 'value' => '★ 4.9',  'label' => '1,000+ Reviews' ),
					array( 'value' => 'DTCM',   'label' => 'Licensed' ),
					array( 'value' => 'FREE',   'label' => 'Hotel Transfer' ),
				),
				'use_featured_image' => true,
				'overlay_opacity'    => 0.88,
			),
		),
	),
	'conditions'    => array(
		'include/singular/post',
	),
);

$result = $design->execute_design_theme_template( $ir );

if ( is_wp_error( $result ) ) {
	printf( "ERROR (%s): %s\n", $result->get_error_code(), $result->get_error_message() );
	exit( 1 );
}

printf( "SUCCESS.\n" );
printf( "  template_id:      %d\n", $result['template_id'] );
printf( "  title:            %s\n", $result['title'] );
printf( "  template_type:    %s\n", $result['template_type'] );
printf( "  edit_url:         %s\n", $result['edit_url'] );
printf( "  elements_created: %d\n", $result['elements_created'] );
printf( "  conditions_set:   %s\n", implode( ', ', $result['conditions_set'] ) );

exit( 0 );
