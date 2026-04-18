<?php
/**
 * Deep array merge utility for composing Elementor settings arrays.
 *
 * Unlike array_merge_recursive(), this replaces scalars instead of
 * coercing them into arrays. Required for correctly composing token
 * outputs where later values must override earlier ones cleanly.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'emcp_array_deep_merge' ) ) {
	/**
	 * Deep-merges any number of associative arrays.
	 *
	 * Later arrays override earlier ones. For nested associative arrays,
	 * merging recurses. For numeric/indexed arrays, later array replaces
	 * earlier one entirely (does not concatenate).
	 *
	 * @param array ...$arrays Arrays to merge, in order of increasing precedence.
	 * @return array The merged array.
	 */
	function emcp_array_deep_merge( array ...$arrays ): array {
		$result = array();

		foreach ( $arrays as $array ) {
			foreach ( $array as $key => $value ) {
				if (
					is_array( $value )
					&& isset( $result[ $key ] )
					&& is_array( $result[ $key ] )
					&& emcp_is_assoc_array( $value )
					&& emcp_is_assoc_array( $result[ $key ] )
				) {
					$result[ $key ] = emcp_array_deep_merge( $result[ $key ], $value );
				} else {
					$result[ $key ] = $value;
				}
			}
		}

		return $result;
	}
}

if ( ! function_exists( 'emcp_is_assoc_array' ) ) {
	/**
	 * Tests whether an array is associative (string keys) vs indexed (numeric).
	 *
	 * @param array $array The array to test.
	 * @return bool True if associative.
	 */
	function emcp_is_assoc_array( array $array ): bool {
		if ( array() === $array ) {
			return false;
		}
		return array_keys( $array ) !== range( 0, count( $array ) - 1 );
	}
}
