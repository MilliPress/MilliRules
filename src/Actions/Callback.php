<?php
/**
 * Callback Action
 *
 * Wrapper class for callback-based actions registered via Rules::register_action().
 *
 * @package MilliRules
 * @since 1.0.0
 */

namespace MilliRules\Actions;

/**
 * Class Callback
 *
 * Allows developers to use closures or callables as action logic without
 * creating dedicated action classes.
 *
 * @since 1.0.0
 */
class Callback implements ActionInterface {
	/**
	 * The callback function to execute.
	 *
	 * @since 1.0.0
	 * @var callable
	 */
	private $callback;

	/**
	 * The action type identifier.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private string $type;

	/**
	 * Full action configuration.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	private array $config;

	/**
	 * Execution context.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	private array $context;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param string               $type The action type identifier.
	 * @param callable             $callback The callback function to execute.
	 * @param array<string, mixed> $config The action configuration.
	 * @param array<string, mixed> $context The execution context.
	 */
	public function __construct( string $type, callable $callback, array $config, array $context ) {
		$this->type     = $type;
		$this->callback = $callback;
		$this->config   = $config;
		$this->context  = $context;
	}

	/**
	 * Execute the action by calling the callback.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context (unused, already stored in constructor).
	 * @return void
	 */
	public function execute( array $context ): void {
		try {
			// Call the callback with context and config.
			call_user_func( $this->callback, $this->context, $this->config );

		} catch ( \Exception $e ) {
			error_log(
				sprintf(
					'MilliRules: Error in callback action "%s": %s',
					$this->type,
					$e->getMessage()
				)
			);
		} catch ( \Throwable $e ) {
			error_log(
				sprintf(
					'MilliRules: Fatal error in callback action "%s": %s',
					$this->type,
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Get the action type identifier.
	 *
	 * @since 1.0.0
	 *
	 * @return string The action type.
	 */
	public function get_type(): string {
		return $this->type;
	}
}
