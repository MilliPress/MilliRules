<?php
/**
 * Base Action
 *
 * Abstract base class for all action implementations.
 *
 * @package MilliRules
 * @since 1.0.0
 */

namespace MilliRules\Actions;

use MilliRules\PlaceholderResolver;

/**
 * Class BaseAction
 *
 * Provides common functionality for all actions including placeholder resolution.
 *
 * @since 1.0.0
 */
abstract class BaseAction implements ActionInterface {
	/**
	 * Action value.
	 *
	 * @since 1.0.0
	 * @var mixed
	 */
	protected $value;

	/**
	 * Full action configuration.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	protected array $config;

	/**
	 * Placeholder resolver instance.
	 *
	 * @since 1.0.0
	 * @var PlaceholderResolver
	 */
	protected PlaceholderResolver $resolver;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $config  The action configuration.
	 * @param array<string, mixed> $context The execution context.
	 */
	public function __construct( array $config, array $context ) {
		$this->config   = $config;
		$this->value    = $config['value'] ?? null;
		$this->resolver = new PlaceholderResolver( $context );
	}

	/**
	 * Resolve placeholders in a value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value to resolve.
	 * @return string The resolved value.
	 */
	protected function resolve_value( string $value ): string {
		return $this->resolver->resolve( $value );
	}
}
