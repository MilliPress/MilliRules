<?php
/**
 * Query Var Condition
 *
 * Checks WordPress query variables.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;

/**
 * Class QueryVar
 *
 * Checks WordPress query variables (e.g., 'paged', 'post_type', 's', 'm', etc.).
 * Uses get_query_var() to retrieve the value or falls back to context.
 *
 * Supported operators:
 * - =: Exact value match (default)
 * - !=: Value doesn't match
 * - IN: Check if value is in array
 * - NOT IN: Check if value is not in array
 * - LIKE: Pattern matching with wildcards (* and ?)
 * - EXISTS: Check if query var exists
 * - NOT EXISTS: Check if query var doesn't exist
 *
 * Examples:
 * Array syntax:
 * - Check paged: ['type' => 'query_var', 'name' => 'paged', 'value' => 2]
 * - Check search exists: ['type' => 'query_var', 'name' => 's', 'operator' => 'EXISTS']
 * - Check post type: ['type' => 'query_var', 'name' => 'post_type', 'value' => 'product']
 *
 * Builder syntax:
 * - ->query_var('paged', 2) // exact match
 * - ->query_var('s', null, 'EXISTS') // search exists
 *
 * @since 1.0.0
 */
class QueryVar extends BaseCondition {
	/**
	 * Query variable name.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $query_var_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $config  The condition configuration.
	 * @param array<string, mixed> $context The execution context.
	 */
	public function __construct( array $config, array $context ) {
		parent::__construct( $config, $context );

		$name_value = $config['name'] ?? '';
		$this->query_var_name = is_string( $name_value ) ? $name_value : '';
	}

	/**
	 * Get the condition type.
	 *
	 * @since 1.0.0
	 *
	 * @return string The condition type identifier.
	 */
	public function get_type(): string {
		return 'query_var';
	}

	/**
	 * Get the actual value from context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 * @return mixed The query var value, or null if not set.
	 */
	protected function get_actual_value( array $context ) {
		if ( empty( $this->query_var_name ) ) {
			return null;
		}

		// Try to get from context first.
		if ( isset( $context['wp']['query_vars'][ $this->query_var_name ] ) ) {
			return $context['wp']['query_vars'][ $this->query_var_name ];
		}

		// Fall back to WordPress function.
		if ( function_exists( 'get_query_var' ) ) {
			$value = get_query_var( $this->query_var_name );
			// get_query_var returns false or empty string for non-existent vars.
			// Return null to make EXISTS/NOT EXISTS operators work correctly.
			if ( false === $value || '' === $value ) {
				return null;
			}
			return $value;
		}

		return null;
	}
}
