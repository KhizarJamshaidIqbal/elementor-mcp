<?php
/**
 * Smoke test: pattern portability guard.
 *
 * Fails if any pattern file in `includes/design/patterns/*.php` contains
 * site-specific tokens (Safari / Dubai / DTCM / AED / 971 / safaridesertdubai)
 * outside of PHP comments/docblocks.
 *
 * Comments are allowed to mention site names in EXAMPLES (helps devs learn
 * the slot shape). Runtime strings — string literals, default values, array
 * values — must be site-agnostic. Caller supplies content via slots.
 *
 * Exit code 0 = all patterns clean.
 * Exit code 1 = leakage found. Prints offending file + line + token.
 *
 * Usage:
 *   php tests/smoke-pattern-portability.php
 *
 * CI integration:
 *   Add to pre-commit / pre-push hook or Git Actions.
 *
 * @package Elementor_MCP
 * @since   1.4.4
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Run from CLI only.\n" );
}

$patterns_dir = dirname( __DIR__ ) . '/includes/design/patterns';
if ( ! is_dir( $patterns_dir ) ) {
	fwrite( STDERR, "ERROR: patterns directory not found at $patterns_dir\n" );
	exit( 1 );
}

/**
 * Tokens forbidden in pattern runtime code.
 * Case-insensitive match. These fail if found on any non-comment line.
 */
$forbidden = array(
	'Safari',
	'Dubai',
	'DTCM',
	'AED',
	'safaridesertdubai',
	'+971',
);

/**
 * Tokens forbidden even in comments (hard-ban — site URLs can't hide in docblock examples).
 */
$forbidden_everywhere = array(
	'safaridesertdubai.local',
	'safaridesertdubai.com',
);

$files = glob( $patterns_dir . '/*.php' );
if ( $files === false || empty( $files ) ) {
	fwrite( STDERR, "ERROR: no pattern files found in $patterns_dir\n" );
	exit( 1 );
}

$total_files = count( $files );
$clean_files = 0;
$offending   = array();

foreach ( $files as $file ) {
	$rel_path = 'includes/design/patterns/' . basename( $file );
	$lines    = file( $file, FILE_IGNORE_NEW_LINES );
	if ( $lines === false ) {
		$offending[] = array(
			'file'  => $rel_path,
			'line'  => 0,
			'token' => '<file read failed>',
		);
		continue;
	}

	$file_clean = true;

	foreach ( $lines as $i => $line ) {
		$trimmed = ltrim( $line );

		// Hard-ban: site URLs anywhere.
		foreach ( $forbidden_everywhere as $token ) {
			if ( stripos( $line, $token ) !== false ) {
				$offending[] = array(
					'file'  => $rel_path,
					'line'  => $i + 1,
					'token' => $token,
					'text'  => trim( $line ),
				);
				$file_clean = false;
			}
		}

		// Softer tokens: skip comment/docblock lines (examples allowed).
		// PHP comment starts: `*`, `//`, `/*`, `*/`, `#`.
		$is_comment = $trimmed === ''
			|| strpos( $trimmed, '*' ) === 0
			|| strpos( $trimmed, '//' ) === 0
			|| strpos( $trimmed, '/*' ) === 0
			|| strpos( $trimmed, '#' ) === 0;

		if ( $is_comment ) {
			continue;
		}

		foreach ( $forbidden as $token ) {
			if ( stripos( $line, $token ) !== false ) {
				$offending[] = array(
					'file'  => $rel_path,
					'line'  => $i + 1,
					'token' => $token,
					'text'  => trim( $line ),
				);
				$file_clean = false;
			}
		}
	}

	if ( $file_clean ) {
		$clean_files++;
	}
}

// Report.
printf( "╔═══════════════════════════════════════════════╗\n" );
printf( "║  Pattern Portability Smoke Test               ║\n" );
printf( "╠═══════════════════════════════════════════════╣\n" );
printf( "║  Patterns scanned: %-3d                         ║\n", $total_files );
printf( "║  Clean:            %-3d                         ║\n", $clean_files );
printf( "║  Offending:        %-3d                         ║\n", $total_files - $clean_files );
printf( "╚═══════════════════════════════════════════════╝\n" );

if ( empty( $offending ) ) {
	printf( "\nOK All %d patterns are portable. Plugin will travel clean to any WP site.\n", $total_files );
	exit( 0 );
}

printf( "\nFAIL Site-specific content found in pattern runtime (should live in test scripts / MCP args instead):\n\n" );
foreach ( $offending as $entry ) {
	printf( "  %s:%d  [%s]\n", $entry['file'], $entry['line'], $entry['token'] );
	if ( isset( $entry['text'] ) ) {
		$mb_avail = function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' );
		$len      = $mb_avail ? mb_strlen( $entry['text'] ) : strlen( $entry['text'] );
		$snippet  = $len > 120
			? ( $mb_avail ? mb_substr( $entry['text'], 0, 117 ) : substr( $entry['text'], 0, 117 ) ) . '...'
			: $entry['text'];
		printf( "    -> %s\n", $snippet );
	}
}
printf( "\nFix: move site-specific defaults to neutral placeholders + supply content via slot at call time.\n" );
exit( 1 );
