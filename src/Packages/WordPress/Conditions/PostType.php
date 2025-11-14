<?php
/**
 * Post Type Condition
 *
 * Checks WordPress post type.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;

/**
 * Class PostType
 *
 * Checks the WordPress post type (post, page, or custom post type).
 * Uses WordPress APIs to determine the current post type.
 *
 * Supported operators:
 * - =: Exact post type match (default)
 * - !=: Post type doesn't match
 * - IN: Check if post type is in array
 * - LIKE: Pattern matching with wildcards (* and ?)
 * - REGEXP: Regular expression matching
 *
 * Examples:
 * Array syntax:
 * - Check for post: ['type' => 'post_type', 'value' => 'post']
 * - Check for page: ['type' => 'post_type', 'value' => 'page', 'operator' => '=']
 * - Check not page: ['type' => 'post_type', 'value' => 'page', 'operator' => '!=']
 * - Multiple types: ['type' => 'post_type', 'value' => ['post', 'page'], 'operator' => 'IN']
 * - Custom post type: ['type' => 'post_type', 'value' => 'product']
 * - Pattern match: ['type' => 'post_type', 'value' => 'wp_*', 'operator' => 'LIKE']
 *
 * Builder syntax:
 * - ->post_type('post') // exact match
 * - ->post_type('page') // exact match
 * - ->post_type(['post', 'page'], 'IN') // multiple types
 *
 * @since 1.0.0
 */
class PostType extends BaseCondition {
	/**
	 * Get the condition type.
	 *
	 * @since 1.0.0
	 *
	 * @return string The condition type identifier.
	 */
	public function get_type(): string {
		return 'post_type';
	}

	/**
	 * Get the actual value from WordPress.
	 *
	 * Does not use context - only WordPress APIs.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context (ignored).
	 * @return string The current post type.
	 */
	protected function get_actual_value( array $context ): string {
		$post = null;

		// Try to get post from the queried object.
		if ( function_exists( 'get_queried_object' ) ) {
			$queried = get_queried_object();

			// If it's a WP_Post object, use it.
			if ( $queried instanceof \WP_Post ) {
				$post = $queried;
			}
		}

		// Fallback to global $post if not found yet.
		if ( null === $post && isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ) {
			$post = $GLOBALS['post'];
		}

		// If we have a post, return its type.
		if ( null !== $post ) {
			return (string) $post->post_type;
		}

		return '';
	}
}
