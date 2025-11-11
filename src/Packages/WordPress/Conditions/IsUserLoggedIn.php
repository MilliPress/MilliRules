<?php
/**
 * Is User Logged In Condition
 *
 * Checks if the user is logged in.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;

/**
 * Class IsUserLoggedIn
 *
 * Checks if the current WordPress user is logged in.
 * Uses WordPress's is_user_logged_in() function.
 *
 * Supported operators:
 * - IS: Check if user is logged in (default)
 * - IS NOT/!=: Check if user is not logged in
 * - =: Exact boolean match
 *
 * Examples:
 * Array syntax:
 * - Check if logged in: ['type' => 'is_user_logged_in']
 * - Check if logged in: ['type' => 'is_user_logged_in', 'value' => true, 'operator' => 'IS']
 * - Check if not logged in: ['type' => 'is_user_logged_in', 'value' => false, 'operator' => 'IS']
 * - Check if not logged in: ['type' => 'is_user_logged_in', 'operator' => 'IS NOT']
 *
 * Builder syntax:
 * - ->is_user_logged_in() // check if logged in
 * - ->is_user_logged_in(true) // check if logged in
 * - ->is_user_logged_in(false) // check if not logged in
 *
 * @since 1.0.0
 */
class IsUserLoggedIn extends BaseCondition {
	/**
	 * Get the condition type.
	 *
	 * @since 1.0.0
	 *
	 * @return string The condition type identifier.
	 */
	public function get_type(): string {
		return 'is_user_logged_in';
	}

	/**
	 * Get the actual value from context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 * @return bool Whether the user is logged in.
	 */
	protected function get_actual_value( array $context ): bool {
		if ( ! function_exists( 'is_user_logged_in' ) ) {
			return false;
		}

		return is_user_logged_in();
	}
}
