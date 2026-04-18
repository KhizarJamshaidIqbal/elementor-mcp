<?php
/**
 * DOM Structural Diff — compares input HTML against rendered Elementor frontend
 * output to produce a similarity score (0-100).
 *
 * CLI:
 *   php tests/fidelity/dom-diff.php --input=path/to/source.html --rendered=http://site.local/page/
 *
 * Strategy:
 *   1. Parse each DOM into a normalized tag-class tree (strip whitespace text nodes,
 *      collapse equivalent wrappers).
 *   2. Compute element-count histograms per tag.
 *   3. Compute class histograms (classes appearing in both).
 *   4. Weighted score: 0.5 * tag_overlap + 0.3 * class_overlap + 0.2 * depth_similarity.
 *
 * Output: JSON {score, tag_overlap, class_overlap, depth_similarity, missing_tags,
 * missing_classes, extra_tags, extra_classes}.
 *
 * Exit codes: 0 = score ≥ 75, 2 = score < 75.
 *
 * @package Elementor_MCP
 * @since   1.7.0
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( "Run from CLI only.\n" );
}

// Tiny arg parser.
$opts = array();
foreach ( $argv as $arg ) {
	if ( '--' === substr( $arg, 0, 2 ) && false !== strpos( $arg, '=' ) ) {
		list( $k, $v ) = explode( '=', substr( $arg, 2 ), 2 );
		$opts[ $k ]    = $v;
	}
}

$input_path   = $opts['input']    ?? '';
$rendered_src = $opts['rendered'] ?? '';

if ( '' === $input_path || '' === $rendered_src ) {
	fwrite( STDERR, "Usage: php dom-diff.php --input=<path.html> --rendered=<url-or-path>\n" );
	exit( 1 );
}

if ( ! file_exists( $input_path ) ) {
	fwrite( STDERR, "Input HTML not found: $input_path\n" );
	exit( 1 );
}
$input_html = file_get_contents( $input_path );

if ( preg_match( '#^https?://#i', $rendered_src ) ) {
	$ctx = stream_context_create(
		array(
			'http' => array(
				'timeout'       => 15,
				'ignore_errors' => true,
				'header'        => "User-Agent: emcp-dom-diff\r\n",
			),
		)
	);
	$rendered_html = @file_get_contents( $rendered_src, false, $ctx );
	if ( false === $rendered_html ) {
		fwrite( STDERR, "Failed to fetch rendered URL: $rendered_src\n" );
		exit( 1 );
	}
} else {
	if ( ! file_exists( $rendered_src ) ) {
		fwrite( STDERR, "Rendered file not found: $rendered_src\n" );
		exit( 1 );
	}
	$rendered_html = file_get_contents( $rendered_src );
}

/**
 * Parse HTML → {tag_hist, class_hist, depth}.
 */
function emcp_dom_diff_parse( string $html ): array {
	$dom = new DOMDocument();
	libxml_use_internal_errors( true );
	$dom->loadHTML( '<?xml encoding="UTF-8"?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();

	$tag_hist   = array();
	$class_hist = array();
	$max_depth  = 0;

	$noise_tags = array( 'script', 'style', 'noscript', 'link', 'meta', 'head' );

	$walk = function ( DOMElement $el, int $depth ) use ( &$walk, &$tag_hist, &$class_hist, &$max_depth, $noise_tags ) {
		$tag = strtolower( $el->tagName );
		if ( in_array( $tag, $noise_tags, true ) ) {
			return;
		}
		$tag_hist[ $tag ] = ( $tag_hist[ $tag ] ?? 0 ) + 1;
		$classes           = preg_split( '/\s+/', trim( $el->getAttribute( 'class' ) ) );
		foreach ( $classes as $c ) {
			if ( '' === $c ) {
				continue;
			}
			// Strip Elementor's auto-generated classes (won't exist in input).
			if ( 0 === strpos( $c, 'elementor-' ) || 0 === strpos( $c, 'e-' ) ) {
				continue;
			}
			$class_hist[ $c ] = ( $class_hist[ $c ] ?? 0 ) + 1;
		}
		if ( $depth > $max_depth ) {
			$max_depth = $depth;
		}
		foreach ( $el->childNodes as $child ) {
			if ( $child instanceof DOMElement ) {
				$walk( $child, $depth + 1 );
			}
		}
	};

	$body = $dom->getElementsByTagName( 'body' )->item( 0 );
	if ( ! $body instanceof DOMElement ) {
		foreach ( $dom->childNodes as $n ) {
			if ( $n instanceof DOMElement ) {
				$walk( $n, 0 );
				break;
			}
		}
	} else {
		$walk( $body, 0 );
	}

	return array(
		'tag_hist'   => $tag_hist,
		'class_hist' => $class_hist,
		'depth'      => $max_depth,
	);
}

$input_parsed    = emcp_dom_diff_parse( $input_html );
$rendered_parsed = emcp_dom_diff_parse( $rendered_html );

/**
 * Histogram overlap 0-1 = Σ min(a[k], b[k]) ÷ max(Σ a, Σ b).
 */
function emcp_dom_diff_overlap( array $a, array $b ): float {
	$sum_a = array_sum( $a );
	$sum_b = array_sum( $b );
	$denom = max( $sum_a, $sum_b );
	if ( $denom <= 0 ) {
		return 1.0;
	}
	$shared = 0;
	foreach ( $a as $k => $va ) {
		if ( isset( $b[ $k ] ) ) {
			$shared += min( $va, $b[ $k ] );
		}
	}
	return $shared / $denom;
}

$tag_overlap   = emcp_dom_diff_overlap( $input_parsed['tag_hist'],   $rendered_parsed['tag_hist'] );
$class_overlap = emcp_dom_diff_overlap( $input_parsed['class_hist'], $rendered_parsed['class_hist'] );
$depth_a       = max( 1, $input_parsed['depth'] );
$depth_b       = max( 1, $rendered_parsed['depth'] );
$depth_sim     = min( $depth_a, $depth_b ) / max( $depth_a, $depth_b );

$score = (int) round( ( $tag_overlap * 0.5 + $class_overlap * 0.3 + $depth_sim * 0.2 ) * 100 );

$missing_tags    = array_diff_key( $input_parsed['tag_hist'],   $rendered_parsed['tag_hist'] );
$missing_classes = array_diff_key( $input_parsed['class_hist'], $rendered_parsed['class_hist'] );
$extra_tags      = array_diff_key( $rendered_parsed['tag_hist'], $input_parsed['tag_hist'] );
$extra_classes   = array_diff_key( $rendered_parsed['class_hist'], $input_parsed['class_hist'] );

echo json_encode(
	array(
		'score'            => $score,
		'tag_overlap'      => round( $tag_overlap, 3 ),
		'class_overlap'    => round( $class_overlap, 3 ),
		'depth_similarity' => round( $depth_sim, 3 ),
		'input_depth'      => $input_parsed['depth'],
		'rendered_depth'   => $rendered_parsed['depth'],
		'missing_tags'     => array_keys( $missing_tags ),
		'missing_classes'  => array_keys( $missing_classes ),
		'extra_tags'       => array_keys( $extra_tags ),
		'extra_classes'    => array_keys( $extra_classes ),
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";

exit( $score >= 75 ? 0 : 2 );
