<?php
/**
 * Is Singular Condition
 *
 * Checks if the current view is singular.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;

/**
 * Class IsSingular
 *
 * Checks if the current view is a singular post, page, or custom post type.
 * Uses WordPress's is_singular() conditional tag.
 *
 * Special behavior: When using the '=' operator, this condition compares post IDs
 * instead of boolean values, allowing you to check for specific posts.
 *
 * Supported operators:
 * - IS: Check if viewing a singular page (returns boolean)
 * - IS NOT/!=: Check if not viewing a singular page (returns boolean)
 * - =: Check if viewing a specific post ID (returns post ID for comparison)
 *
 * Examples:
 * Array syntax:
 * - Check if singular: ['type' => 'is_singular']
 * - Check if singular: ['type' => 'is_singular', 'value' => true, 'operator' => 'IS']
 * - Check if not singular: ['type' => 'is_singular', 'value' => false, 'operator' => 'IS']
 * - Check specific post: ['type' => 'is_singular', 'value' => 123, 'operator' => '=']
 * - Check not specific post: ['type' => 'is_singular', 'value' => 123, 'operator' => '!=']
 *
 * Builder syntax:
 * - ->is_singular() // check if singular
 * - ->is_singular(true) // check if singular
 * - ->is_singular(123, '=') // check if viewing post ID 123
 *
 * @since 1.0.0
 */
class IsSingular extends BaseCondition {
	/**
	 * Get the condition type.
	 *
	 * @since 1.0.0
	 *
	 * @return string The condition type identifier.
	 */
	public function get_type(): string {
		return 'is_singular';
	}

	/**
	 * Get the actual value from context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 * @return mixed Boolean if checking is_singular(), or post_id if checking a specific post.
	 */
	protected function get_actual_value( array $context ) {
		// If the operator is 'equals', return post_id for comparison.
		if ( 'EQUALS' === $this->operator || '=' === $this->operator ) {
			return $context['wp']['post']['id'] ?? 0;
		}

		// For the 'is' operator, return boolean.
		if ( ! function_exists( 'is_singular' ) ) {
			return false;
		}

		return is_singular();
	}
}
