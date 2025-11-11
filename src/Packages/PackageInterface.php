<?php
/**
 * Package Interface
 *
 * Defines the contract for MilliRules packages that provide conditions, actions,
 * and context building capabilities for different environments (PHP, WordPress, Laravel, etc.).
 *
 * Packages encapsulate:
 * - Condition/Action class namespaces
 * - Context building logic
 * - Placeholder resolution
 * - Rule registration and execution
 * - Availability detection
 * - Dependency management
 *
 * Example package names: 'PHP', 'WordPress', 'Laravel'
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 * @since 1.0.0
 */

namespace MilliRules\Packages;

/**
 * Interface PackageInterface
 *
 * Contract for MilliRules packages that provide environment-specific functionality.
 *
 * @since 1.0.0
 */
interface PackageInterface {
	/**
	 * Get the unique package identifier.
	 *
	 * Returns a unique name for this package (e.g., 'PHP', 'WordPress', 'Laravel').
	 * This name is used for package registration, dependency resolution, and namespace mapping.
	 *
	 * @since 1.0.0
	 *
	 * @return string The package name.
	 */
	public function get_name(): string;

	/**
	 * Get the namespaces provided by this package.
	 *
	 * Returns an array of fully-qualified namespaces that contain conditions and actions
	 * provided by this package.
	 *
	 * Example: ['MilliRules\Packages\PHP\Conditions', 'MilliRules\Packages\PHP\Actions']
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Array of namespace strings.
	 */
	public function get_namespaces(): array;

	/**
	 * Check if this package is available in the current environment.
	 *
	 * Performs runtime detection to determine if the package can be used.
	 * For example, WordPress package checks if WordPress functions exist.
	 *
	 * @since 1.0.0
	 *
	 * @return bool True if package is available, false otherwise.
	 */
	public function is_available(): bool;

	/**
	 * Get the names of packages required by this package.
	 *
	 * Returns an array of package names that must be loaded before this package.
	 * Used for dependency resolution during package loading.
	 *
	 * Example: WordPress package requires ['PHP']
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Array of required package names.
	 */
	public function get_required_packages(): array;

	/**
	 * Register this package's namespaces with the RuleEngine.
	 *
	 * Called during package loading to make conditions and actions discoverable
	 * by the RuleEngine's auto-resolution system.
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function register_namespaces(): void;

	/**
	 * Build the context array for this package.
	 *
	 * Creates and returns a context array containing all data needed for
	 * condition evaluation and action execution in this package's environment.
	 *
	 * The context structure varies by package:
	 * - PHP: ['request' => [...]]
	 * - WordPress: ['request' => [...], 'wp' => [...]]
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> The context array.
	 */
	public function build_context(): array;

	/**
	 * Get a placeholder resolver instance for this package.
	 *
	 * Returns a placeholder resolver configured with the given context,
	 * or null if this package doesn't provide placeholder resolution.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 * @return object|null PlaceholderResolver instance or null.
	 */
	public function get_placeholder_resolver( array $context );

	/**
	 * Register a rule with this package.
	 *
	 * Stores the rule for later execution by this package. The metadata contains
	 * package-specific information like execution hooks, priority, etc.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $rule     The rule configuration.
	 * @param array<string, mixed> $metadata Additional metadata (order, hooks, etc.).
	 * @return void
	 */
	public function register_rule( array $rule, array $metadata ): void;

	/**
	 * Execute rules for this package.
	 *
	 * Executes the given rules with the provided context and returns execution results.
	 * The result includes information about stopped execution, trigger actions, and debug data.
	 *
	 * @since 1.0.0
	 *
	 * @param array<int, array<string, mixed>> $rules   The rules to execute.
	 * @param array<string, mixed>             $context The execution context.
	 * @return array<string, mixed> Execution result with 'stopped', 'trigger_actions', and 'debug' keys.
	 */
	public function execute_rules( array $rules, array $context ): array;

	/**
	 * Get all rules registered with this package.
	 *
	 * Returns an array of all rules that have been registered with this package.
	 * Used by PackageManager to collect rules from all packages.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, array<string, mixed>> Array of registered rules.
	 */
	public function get_rules(): array;
}
