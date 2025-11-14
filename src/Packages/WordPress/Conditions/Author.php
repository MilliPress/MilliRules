<?php
/**
 * Author Condition
 *
 * Checks the post author by ID, login, or nicename.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;

/**
 * Class Author
 *
 * Unified author condition that matches against ID, login, or nicename.
 * Works for posts, pages, and custom post types with an author.
 *
 * The condition automatically matches the expected value against:
 * - Author ID (numeric values)
 * - Author login (string values)
 * - Author nicename (string values)
 *
 * Supported operators:
 * - =: Exact match against any field (default)
 * - !=: Does not match any field
 * - IN: Check if any value matches any field
 * - NOT IN: Check if no values match any field
 * - LIKE: Pattern matching against login/nicename
 * - REGEXP: Regular expression matching against login/nicename
 *
 * Examples:
 * Array syntax:
 * - Match by ID: ['type' => 'author', 'value' => 1]
 * - Match by login: ['type' => 'author', 'value' => 'admin']
 * - Match by nicename: ['type' => 'author', 'value' => 'john-doe']
 * - Multiple values: ['type' => 'author', 'value' => [1, 'editor', 'admin'], 'operator' => 'IN']
 *
 * Builder syntax:
 * - ->author(1) // match by ID
 * - ->author('admin') // match by login or nicename
 * - ->author([1, 'editor', 'admin'], 'IN') // match any
 *
 * @since 1.0.0
 */
class Author extends BaseCondition {
	/**
	 * Get the condition type.
	 *
	 * @since 1.0.0
	 *
	 * @return string The condition type identifier.
	 */
	public function get_type(): string {
		return 'author';
	}

	/**
	 * Get the actual value from WordPress.
	 *
	 * Returns an associative array with author information.
	 * Does not use context - only WordPress APIs.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context (ignored).
	 * @return array<string, mixed>|null Author data array or null if no author.
	 */
	protected function get_actual_value( array $context ) {
		$author_id = 0;

		// Try to get author from the queried object (posts, pages, etc.).
		if ( function_exists( 'get_queried_object' ) ) {
			$queried = get_queried_object();

			// If it's a WP_Post object, get its author.
			if ( $queried && isset( $queried->post_author ) ) {
				$author_id = (int) $queried->post_author;
			}
		}

		// Fallback to global $post if no author found yet.
		if ( 0 === $author_id && isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
			$author_id = (int) $GLOBALS['post']->post_author;
		}

		// If we still don't have an author, return null.
		if ( 0 === $author_id ) {
			return null;
		}

		// Get the author's user data.
		if ( ! function_exists( 'get_userdata' ) ) {
			return null;
		}

		$user = get_userdata( $author_id );

		if ( ! $user ) {
			return null;
		}

		return array(
			'id'       => (int) $user->ID,
			'login'    => (string) $user->user_login,
			'nicename' => (string) $user->user_nicename,
		);
	}

	/**
	 * Compare actual and expected values.
	 *
	 * Overrides parent to handle matching against multiple fields (id, login, nicename).
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $actual   The actual value from WordPress (array with id/login/nicename or null).
	 * @param mixed $expected The expected value from config (scalar or array).
	 * @return bool True if comparison matches, false otherwise.
	 */
	protected function compare( $actual, $expected ): bool {
		// If no author data, handle based on operator.
		if ( null === $actual || ! is_array( $actual ) ) {
			// For negative operators, not having an author can be considered a match.
			if ( in_array( $this->operator, array( '!=', 'IS NOT', 'NOT IN', 'NOT EXISTS' ), true ) ) {
				return true;
			}
			return false;
		}

		$actual_id       = $actual['id'] ?? 0;
		$actual_login    = $actual['login'] ?? '';
		$actual_nicename = $actual['nicename'] ?? '';

		// Convert expected to array for unified handling.
		$expected_values = is_array( $expected ) ? $expected : array( $expected );

		// Check if any expected value matches any actual field.
		$has_match = false;

		foreach ( $expected_values as $expected_value ) {
			// Resolve placeholders if needed.
			$resolved_value = is_string( $expected_value ) ? $this->resolver->resolve( $expected_value ) : $expected_value;

			// Try matching against ID (if expected value is numeric).
			if ( is_numeric( $resolved_value ) ) {
				if ( parent::compare( $actual_id, (int) $resolved_value ) ) {
					$has_match = true;
					break;
				}
			}

			// Try matching against login (for all operators).
			if ( parent::compare( $actual_login, $resolved_value ) ) {
				$has_match = true;
				break;
			}

			// Try matching against nicename (for string-based operators).
			if ( in_array( $this->operator, array( '=', 'EQUALS', 'IN', 'LIKE', 'REGEXP' ), true ) ) {
				if ( parent::compare( $actual_nicename, $resolved_value ) ) {
					$has_match = true;
					break;
				}
			}
		}

		// Apply operator logic.
		switch ( $this->operator ) {
			case '!=':
			case 'IS NOT':
			case 'NOT IN':
			case 'NOT LIKE':
				return ! $has_match;

			default:
				return $has_match;
		}
	}
}
