<?php
/**
 * Diagnostic — inspects the created blog single-post template:
 * metas, conditions, Elementor Pro theme-builder registration,
 * whether it applies to a sample blog post.
 *
 * Read-only.
 */

if ( 'cli' !== PHP_SAPI ) { exit( "CLI only.\n" ); }
if ( ! defined( 'DB_HOST' ) ) { define( 'DB_HOST', '127.0.0.1:10024' ); }
define( 'WP_USE_THEMES', false );
require dirname( __DIR__, 4 ) . '/wp-load.php';

$template_id = (int) ( $argv[1] ?? 2761 );

printf( "== Template %d ==\n", $template_id );
$post = get_post( $template_id );
if ( ! $post ) { exit( "Not found.\n" ); }

printf( "title:          %s\n", $post->post_title );
printf( "post_status:    %s\n", $post->post_status );
printf( "post_type:      %s\n", $post->post_type );
printf( "template_type:  %s\n", get_post_meta( $template_id, '_elementor_template_type', true ) );
printf( "version:        %s\n", get_post_meta( $template_id, '_elementor_version', true ) );
printf( "edit_mode:      %s\n", get_post_meta( $template_id, '_elementor_edit_mode', true ) );

$cond = get_post_meta( $template_id, '_elementor_conditions', true );
printf( "conditions:     %s\n", is_array( $cond ) ? json_encode( $cond ) : (string) $cond );

$data = get_post_meta( $template_id, '_elementor_data', true );
$decoded = is_string( $data ) ? json_decode( $data, true ) : $data;
if ( is_array( $decoded ) ) {
	printf( "root elements:  %d\n", count( $decoded ) );
	foreach ( $decoded as $i => $el ) {
		printf( "  [%d] elType=%s id=%s settings._title=%s children=%d\n",
			$i,
			$el['elType'] ?? '?',
			$el['id'] ?? '?',
			$el['settings']['_title'] ?? '',
			is_array( $el['elements'] ?? null ) ? count( $el['elements'] ) : 0
		);
	}
} else {
	printf( "data not array; raw type=%s\n", gettype( $data ) );
}

$terms = wp_get_post_terms( $template_id, 'elementor_library_type', array( 'fields' => 'slugs' ) );
printf( "library_type:   %s\n", implode( ', ', (array) $terms ) );

printf( "\n== Elementor / Pro ==\n" );
printf( "Elementor:     %s\n", defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'NOT LOADED' );
printf( "Elementor Pro: %s\n", defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : 'NOT LOADED' );

if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
	printf( "ThemeBuilder Module: available\n" );
	try {
		$mod = \ElementorPro\Modules\ThemeBuilder\Module::instance();
		$mgr = $mod->get_conditions_manager();
		if ( $mgr && method_exists( $mgr, 'get_conditions_config' ) ) {
			$live = $mgr->get_conditions_config( $template_id );
			printf( "conditions via ConditionsManager: %s\n", json_encode( $live ) );
		}
	} catch ( \Throwable $e ) {
		printf( "conditions manager error: %s\n", $e->getMessage() );
	}
} else {
	printf( "ThemeBuilder Module: NOT available\n" );
}

// Which template applies to a sample post?
$sample_post = get_posts( array( 'post_type' => 'post', 'posts_per_page' => 1, 'fields' => 'ids' ) );
if ( ! empty( $sample_post ) ) {
	$pid = (int) $sample_post[0];
	printf( "\n== Sample post %d (%s) ==\n", $pid, get_the_title( $pid ) );
	printf( "permalink: %s\n", get_permalink( $pid ) );

	if ( class_exists( '\\ElementorPro\\Modules\\ThemeBuilder\\Module' ) ) {
		global $wp_query;
		query_posts( array( 'p' => $pid ) );
		if ( have_posts() ) {
			the_post();
			$mod = \ElementorPro\Modules\ThemeBuilder\Module::instance();
			if ( method_exists( $mod, 'get_conditions_manager' ) ) {
				$docs = $mod->get_conditions_manager()->get_documents_for_location( 'single' );
				$ids  = array();
				foreach ( $docs as $d ) {
					if ( is_object( $d ) && method_exists( $d, 'get_main_id' ) ) {
						$ids[] = $d->get_main_id();
					}
				}
				printf( "documents_for_location('single'): %s\n", json_encode( $ids ) );
			}
		}
		wp_reset_query();
	}
}
