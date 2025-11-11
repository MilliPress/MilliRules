<?php
/**
 * Base Package
 *
 * Abstract base class for MilliRules packages providing default implementations
 * for common package functionality.
 *
 * Subclasses must implement:
 * - get_name(): Return unique package identifier
 * - get_namespaces(): Return array of condition/action namespaces
 * - is_available(): Check if package can be used in current environment
 *
 * Subclasses can override:
 * - build_context(): Default returns empty array
 * - get_placeholder_resolver(): Default returns null
 * - get_required_packages(): Default returns empty array (no dependencies)
 * - register_rule(): Default stores in flat list
 * - execute_rules(): Default uses RuleEngine directly
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 * @since 1.0.0
 */

namespace MilliRules\Packages;

use MilliRules\Rules;
use MilliRules\RuleEngine;

/**
 * Class BasePackage
 *
 * Base implementation of PackageInterface with sensible defaults.
 *
 * @since 1.0.0
 */
abstract class BasePackage implements PackageInterface {
	/**
	 * Registered rules for this package.
	 *
	 * Stores rules as flat array with metadata merged in.
	 *
	 * @since 1.0.0
	 * @var array<int, array<string, mixed>>
	 */
	protected array $rules = array();

	/**
	 * Get the unique package identifier.
	 *
	 * Subclasses must implement this to return their package name.
	 *
	 * @since 1.0.0
	 *
	 * @return string The package name.
	 */
	abstract public function get_name(): string;

	/**
	 * Get the namespaces provided by this package.
	 *
	 * Subclasses must implement this to return their namespaces.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Array of namespace strings.
	 */
	abstract public function get_namespaces(): array;

	/**
	 * Check if this package is available in the current environment.
	 *
	 * Subclasses must implement this to perform environment detection.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if package is available, false otherwise.
	 */
	abstract public function is_available(): bool;

	/**
	 * Get the names of packages required by this package.
	 *
	 * Default implementation returns no dependencies.
	 * Override to specify required packages.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Array of required package names.
	 */
	public function get_required_packages(): array {
		return array();
	}

	/**
	 * Register this package's namespaces for condition/action resolution.
	 *
	 * Iterates through namespaces from get_namespaces() and registers
	 * each with the Rules API for auto-resolution of conditions and actions.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_namespaces(): void {
		$namespaces = $this->get_namespaces();

		foreach ( $namespaces as $namespace ) {
			// Determine if this is a Conditions or Actions namespace.
			if ( strpos( $namespace, '\\Conditions' ) !== false ) {
				Rules::register_namespace( 'Conditions', $namespace );
			} elseif ( strpos( $namespace, '\\Actions' ) !== false ) {
				Rules::register_namespace( 'Actions', $namespace );
			}
		}
	}

	/**
	 * Build the context array for this package.
	 *
	 * Default implementation returns empty array.
	 * Override to provide package-specific context data.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The context array.
	 */
	public function build_context(): array {
		return array();
	}

	/**
	 * Get a placeholder resolver instance for this package.
	 *
	 * Default implementation returns null (no placeholder resolution).
	 * Override to provide package-specific placeholder resolver.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 * @return object|null PlaceholderResolver instance or null.
	 */
	public function get_placeholder_resolver( array $context ) {
		return null;
	}

	/**
	 * Register a rule with this package.
	 *
	 * Stores rule in flat array with metadata merged in.
	 * Subclasses can override for hook-based or other registration patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $rule     The rule configuration.
	 * @param array<string, mixed> $metadata Additional metadata (order, hooks, etc.).
	 * @return void
	 */
	public function register_rule( array $rule, array $metadata ): void {
		// Merge metadata into rule for convenience.
		$rule['_metadata'] = $metadata;
		$this->rules[]     = $rule;
	}

	/**
	 * Execute rules for this package with error handling.
	 *
	 * Default implementation:
	 * 1. Sorts rules by order
	 * 2. Creates RuleEngine instance
	 * 3. Executes all rules
	 * 4. Executes trigger actions
	 * 5. Returns result
	 *
	 * Error handling:
	 * - Catches all exceptions during execution
	 * - Logs errors with package name and exception message
	 * - Returns error result structure (never throws exceptions)
	 * - Allows graceful degradation
	 *
	 * Subclasses can override for hook-based or custom execution patterns.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $rules   The rules to execute.
	 * @param array<string, mixed>             $context The execution context.
	 * @return array<string, mixed> Execution result with statistics and context.
	 */
	public function execute_rules( array $rules, array $context ): array {
		try {
			// Sort rules by order field.
			$sorted_rules = $this->sort_rules_by_order( $rules );

			// Create engine and execute.
			$engine = new RuleEngine();
			return $engine->execute( $sorted_rules, $context );

		} catch ( \Exception $e ) {
			// Log error with package name for context.
			$package_name = $this->get_name();
			error_log(
				"MilliRules: Error executing rules for package '{$package_name}': " . $e->getMessage()
			);

			// Return error result structure (consistent with RuleEngine::execute() format).
			return array(
				'stopped'         => false,
				'trigger_actions' => array(),
				'debug'           => array(
					'rules_processed'  => 0,
					'rules_skipped'    => 0,
					'rules_matched'    => 0,
					'actions_executed' => 0,
					'context'          => $context,
					'error'            => $e->getMessage(),
				),
			);
		}
	}

	/**
	 * Get all registered rules for this package.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Array of registered rules.
	 */
	public function get_rules(): array {
		return $this->rules;
	}

	/**
	 * Sort rules by their order field in ascending order.
	 *
	 * Rules without order field are treated as having order = 10.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $rules The rules to sort.
	 * @return array<int, array<string, mixed>> Sorted rules.
	 */
	protected function sort_rules_by_order( array $rules ): array {
		usort(
			$rules,
			function ( $a, $b ) {
				$order_a = $a['_metadata']['order'] ?? 10;
				$order_b = $b['_metadata']['order'] ?? 10;
				return $order_a <=> $order_b;
			}
		);

		return $rules;
	}

	/**
	 * Clear all registered rules from this package.
	 *
	 * Removes all rules from the package's storage.
	 * Called by PackageManager::clear() during reset operations.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function clear(): void {
		$this->rules = array();
	}
}
