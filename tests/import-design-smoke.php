<?php
/**
 * Smoke test — Design Importer end-to-end.
 *
 * Feeds a local Claude-designed HTML file through Design_Importer →
 * Kit_Binder → Data::save_page_data, creating a new Elementor page
 * and reporting stats.
 *
 * Usage:
 *   php tests/import-design-smoke.php --source=<path> [--page-id=N] [--title=Str]
 *
 * Defaults:
 *   --source  C:\Users\epsol\Downloads\FAQ.html
 *   --title   "EMCP Imported Design — Smoke"
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Run from CLI only.\n" );
}

$wp_load = dirname( __DIR__, 4 ) . '/wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	exit( "wp-load.php not found at $wp_load\n" );
}
if ( ! defined( 'DB_HOST' ) ) {
	define( 'DB_HOST', getenv( 'EMCP_DB_HOST' ) ?: '127.0.0.1:10024' );
}
define( 'WP_USE_THEMES', false );
require $wp_load;

// ─── Arg parsing ─────────────────────────────────────────────────
$source  = 'C:\\Users\\epsol\\Downloads\\FAQ.html';
$page_id = 0;
$title   = 'EMCP Imported Design — Smoke';

foreach ( $argv as $arg ) {
	if ( strpos( $arg, '--source=' ) === 0 ) {
		$source = substr( $arg, 9 );
	} elseif ( strpos( $arg, '--page-id=' ) === 0 ) {
		$page_id = (int) substr( $arg, 10 );
	} elseif ( strpos( $arg, '--title=' ) === 0 ) {
		$title = substr( $arg, 8 );
	}
}

if ( ! file_exists( $source ) ) {
	exit( "Source file not found: $source\n" );
}
$html = file_get_contents( $source );
if ( false === $html ) {
	exit( "Failed to read source: $source\n" );
}

if ( ! class_exists( 'Elementor_MCP_Design_Abilities' ) ) {
	exit( "Elementor_MCP_Design_Abilities class not available. Is the plugin active?\n" );
}

// ─── Acquire dependency graph ────────────────────────────────────
$data       = new Elementor_MCP_Data();
$factory    = new Elementor_MCP_Element_Factory();
$composite  = new Elementor_MCP_Composite_Abilities( $data, $factory );
$kit_binder = new Elementor_MCP_Kit_Binder();
$registry   = Elementor_MCP_Pattern_Registry::instance();
$compiler   = new Elementor_MCP_Design_Compiler( $kit_binder, $registry, null );
$importer   = new Elementor_MCP_Design_Importer();
$design     = new Elementor_MCP_Design_Abilities( $data, $factory, $composite, $compiler, $registry, $importer );

// ─── Invoke ──────────────────────────────────────────────────────
$input = array(
	'html'        => $html,
	'skip_header' => true,
	'skip_footer' => true,
);
if ( $page_id > 0 ) {
	$input['page_id'] = $page_id;
} else {
	$input['title']       = $title;
	$input['post_type']   = 'page';
	$input['post_status'] = 'draft';
}

$result = $design->execute_import_design( $input );
if ( is_wp_error( $result ) ) {
	printf( "ERROR (%s): %s\n", $result->get_error_code(), $result->get_error_message() );
	exit( 1 );
}

// ─── Report ──────────────────────────────────────────────────────
printf( "SUCCESS.\n" );
printf( "  source:               %s\n", $source );
printf( "  post_id:              %d\n", $result['post_id'] );
printf( "  edit_url:             %s\n", $result['edit_url'] );
printf( "  preview_url:          %s\n", $result['preview_url'] );
printf( "  elements_created:     %d\n", $result['elements_created'] );
printf( "  elements_mapped:      %d\n", $result['elements_mapped']      ?? 0 );
printf( "  native_widgets:       %d\n", $result['native_widgets']       ?? 0 );
printf( "  html_fallbacks:       %d\n", $result['html_fallbacks']       ?? 0 );
printf( "  accordions_collapsed: %d\n", $result['accordions_collapsed'] ?? 0 );
printf( "  tokens_extracted:     %s\n", is_int( $result['tokens_extracted'] ?? null ) ? (string) $result['tokens_extracted'] : var_export( $result['tokens_extracted'] ?? 0, true ) );
printf( "  palette_bound_slots:  %d\n", $result['palette_bound_slots']  ?? 0 );

exit( 0 );
