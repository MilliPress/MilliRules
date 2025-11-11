<?php
/**
 * Is Home Condition
 *
 * Checks if current view is home/front page.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;

/**
 * Class IsHome
 *
 * Checks if the current view is the WordPress home page or front page.
 * Uses WordPress's is_home() conditional tag.
 *
 * Supported operators:
 * - IS: Check if viewing home page (default)
 * - IS NOT/!=: Check if not viewing home page
 * - =: Exact boolean match
 *
 * Examples:
 * Array syntax:
 * - Check if home: ['type' => 'is_home']
 * - Check if home: ['type' => 'is_home', 'value' => true, 'operator' => 'IS']
 * - Check if not home: ['type' => 'is_home', 'value' => false, 'operator' => 'IS']
 * - Check if not home: ['type' => 'is_home', 'operator' => 'IS NOT']
 *
 * Builder syntax:
 * - ->is_home() // check if home
 * - ->is_home(true) // check if home
 * - ->is_home(false) // check if not home
 *
 * @since 1.0.0
 */
class IsHome extends BaseCondition {
	/**
	 * Get the condition type.
	 *
	 * @since 1.0.0
	 *
	 * @return string The condition type identifier.
	 */
	public function get_type(): string {
		return 'is_home';
	}

	/**
	 * Get the actual value from context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 * @return bool Whether the current view is the home page.
	 */
	protected function get_actual_value( array $context ): bool {
		if ( ! function_exists( 'is_home' ) ) {
			return false;
		}

		return is_home();
	}
}
