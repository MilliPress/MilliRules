<?php
/**
 * Taxonomy Condition
 *
 * Checks the taxonomy name of the current term archive.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;

/**
 * Class Taxonomy
 *
 * Checks the taxonomy name on taxonomy/category/tag archive pages.
 * Returns the taxonomy name (e.g., 'category', 'post_tag', 'product_cat').
 * Returns empty string if not on a taxonomy archive.
 *
 * Supported operators:
 * - =: Exact taxonomy name match (default)
 * - !=: Taxonomy name doesn't match
 * - IN: Check if taxonomy is in array
 * - NOT IN: Check if taxonomy is not in array
 * - LIKE: Pattern matching with wildcards (* and ?)
 * - REGEXP: Regular expression matching
 *
 * Examples:
 * Array syntax:
 * - Check for category: ['type' => 'taxonomy', 'value' => 'category']
 * - Multiple taxonomies: ['type' => 'taxonomy', 'value' => ['category', 'post_tag'], 'operator' => 'IN']
 * - Custom taxonomy pattern: ['type' => 'taxonomy', 'value' => 'product_*', 'operator' => 'LIKE']
 *
 * Builder syntax:
 * - ->taxonomy('category') // exact match
 * - ->taxonomy(['category', 'post_tag'], 'IN') // multiple taxonomies
 *
 * @since 1.0.0
 */
class Taxonomy extends BaseCondition {
	/**
	 * Get the condition type.
	 *
	 * @since 1.0.0
	 *
	 * @return string The condition type identifier.
	 */
	public function get_type(): string {
		return 'taxonomy';
	}

	/**
	 * Get the actual value from context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 * @return string The current taxonomy name.
	 */
	protected function get_actual_value( array $context ): string {
		// Try to get from context first.
		if ( isset( $context['wp']['term']['taxonomy'] ) && is_string( $context['wp']['term']['taxonomy'] ) ) {
			return $context['wp']['term']['taxonomy'];
		}

		// Fall back to WordPress functions.
		if ( function_exists( 'get_queried_object' ) ) {
			// Check if we're on any taxonomy archive.
			if ( function_exists( 'is_tax' ) && is_tax() ) {
				$term = get_queried_object();
				if ( $term && isset( $term->taxonomy ) ) {
					return (string) $term->taxonomy;
				}
			}

			// Category archives.
			if ( function_exists( 'is_category' ) && is_category() ) {
				return 'category';
			}

			// Tag archives.
			if ( function_exists( 'is_tag' ) && is_tag() ) {
				return 'post_tag';
			}
		}

		return '';
	}
}
