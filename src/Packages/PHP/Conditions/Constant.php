<?php
/**
 * PHP Constant Condition
 *
 * Checks PHP constant values (including WordPress constants defined in wp-config.php).
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\PHP\Conditions;

use MilliRules\Conditions\BaseCondition;

/**
 * Class Constant
 *
 * Checks if a PHP constant exists and optionally matches a value.
 * Works with all PHP constants, including WordPress constants defined in wp-config.php
 * which are available before WordPress fully loads.
 *
 * Supports checking:
 * - Existence: Use 'EXISTS' operator to check if constant is defined
 * - Value matching: Use '=', '!=', '>', etc. to compare constant value
 * - Boolean checks: Use 'IS' operator for true/false comparison
 *
 * Examples:
 * - Check if WP_DEBUG exists: ['type' => 'constant', 'name' => 'WP_DEBUG', 'operator' => 'EXISTS']
 * - Check if WP_DEBUG is true: ['type' => 'constant', 'name' => 'WP_DEBUG', 'value' => true]
 * - Check specific value: ['type' => 'constant', 'name' => 'WP_ENVIRONMENT_TYPE', 'value' => 'production']
 * - Check PHP_VERSION_ID: ['type' => 'constant', 'name' => 'PHP_VERSION_ID', 'operator' => '>=', 'value' => 80000]
 * - Using builder: ->constant('DOING_CRON', true) // checks if DOING_CRON is true
 *
 * @since 1.0.0
 */
class Constant extends BaseCondition {
	/**
	 * Get the condition type.
	 *
	 * @since 1.0.0
	 *
	 * @return string The condition type identifier.
	 */
	public function get_type(): string {
		return 'constant';
	}

	/**
	 * Get the actual value from context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 * @return mixed The constant value, or null if not defined.
	 */
	protected function get_actual_value( array $context ) {
		$constant_name = $this->config['name'] ?? $this->config['constant'] ?? '';

		if ( ! is_string( $constant_name ) || empty( $constant_name ) ) {
			return null;
		}

		if ( ! defined( $constant_name ) ) {
			return null;
		}

		return constant( $constant_name );
	}
}
