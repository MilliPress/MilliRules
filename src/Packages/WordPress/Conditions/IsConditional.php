<?php
/**
 * Generic Is Condition
 *
 * Generic fallback for WordPress is_* conditional functions.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;

/**
 * Class IsConditional
 *
 * Provides a generic fallback for WordPress conditional functions that start with "is_".
 * This class dynamically calls the corresponding WordPress function when no specific
 * condition class exists.
 *
 * This condition only handles simple boolean conditionals that take no arguments.
 * For conditionals that require arguments (like is_singular('post')), a dedicated
 * condition class should be used instead.
 *
 * Examples:
 * Array syntax:
 * - Check if 404: ['type' => 'is_404', 'value' => true, 'operator' => 'IS']
 * - Check if not 404: ['type' => 'is_404', 'value' => false, 'operator' => 'IS']
 * - Check if home: ['type' => 'is_home', 'value' => true, 'operator' => 'IS']
 *
 * Builder syntax:
 * - ->is_404() // check if 404 page
 * - ->is_home() // check if home page
 * - ->is_search() // check if search page
 *
 * @since 1.0.0
 */
class IsConditional extends BaseCondition {
	/**
	 * Get the condition type.
	 *
	 * @since 1.0.0
	 *
	 * @return string The condition type identifier.
	 */
	public function get_type(): string {
		$type_value = $this->config['type'] ?? '';
		return is_string( $type_value ) ? $type_value : '';
	}

	/**
	 * Get the actual value from context.
	 *
	 * Dynamically calls the WordPress conditional function corresponding to the type.
	 * For example, type 'is_home' will call the is_home() function.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 * @return bool The result of the WordPress conditional function, or false if it doesn't exist.
	 */
	protected function get_actual_value( array $context ) {
		$fn = $this->get_type();

		// Check if the function exists.
		if ( empty( $fn ) || ! function_exists( $fn ) ) {
			return false;
		}

		// Call the WordPress conditional function with no arguments.
		return (bool) call_user_func( $fn );
	}
}
