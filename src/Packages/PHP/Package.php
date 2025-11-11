<?php
/**
 * PHP Package
 *
 * Provides PHP execution context with HTTP request conditions, actions, and context for MilliRules.
 * This package is always available and provides framework-agnostic PHP/HTTP functionality.
 *
 * Features:
 * - HTTP request conditions (URL, method, headers, cookies, params)
 * - PHP context building with HTTP request data from $_SERVER, $_COOKIE, $_GET
 * - Request placeholder resolution ({request:uri}, {cookie:name}, etc.)
 *
 * This package has no dependencies and is always available.
 *
 * @package     MilliRules
 * @subpackage  PHP
 * @author      Philipp Wellmer
 * @since 1.0.0
 */

namespace MilliRules\Packages\PHP;

use MilliRules\Packages\BasePackage;

/**
 * Class Package
 *
 * PHP package implementation providing PHP execution context with HTTP request-based conditions.
 *
 * @since 1.0.0
 */
class Package extends BasePackage {
	/**
	 * Get the unique package identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string The package name 'PHP'.
	 */
	public function get_name(): string {
		return 'PHP';
	}

	/**
	 * Get the namespaces provided by this package.
	 *
	 * Returns namespaces for PHP conditions and actions.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Array of namespace strings.
	 */
	public function get_namespaces(): array {
		return array(
			'MilliRules\Packages\PHP\Conditions',
			'MilliRules\Packages\PHP\Actions',
		);
	}

	/**
	 * Check if this package is available in the current environment.
	 *
	 * PHP package is always available as it only requires basic PHP functionality.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Always returns true.
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Get the names of packages required by this package.
	 *
	 * PHP package has no dependencies.
	 *
	 * @since 1.0.0
	 *
	 * @return array<int, string> Empty array (no dependencies).
	 */
	public function get_required_packages(): array {
		return array();
	}

	/**
	 * Build the context array for this package.
	 *
	 * Creates PHP execution context with HTTP request data using ContextProvider.
	 * Includes request method, URI, headers, cookies, and parameters.
	 *
	 * @since 1.0.0
	 *
	 * @return array<string, mixed> PHP context array with 'request' key.
	 */
	public function build_context(): array {
		$provider = new ContextProvider();
		return $provider->build_context();
	}

	/**
	 * Get a placeholder resolver instance for this package.
	 *
	 * Returns PlaceholderResolver configured with the given context.
	 * Supports placeholders like {request:uri}, {cookie:name}, {param:key}, {header:name}.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 * @return PlaceholderResolver PlaceholderResolver instance.
	 */
	public function get_placeholder_resolver( array $context ) {
		return new PlaceholderResolver( $context );
	}
}
