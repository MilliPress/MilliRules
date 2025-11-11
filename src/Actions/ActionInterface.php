<?php
/**
 * Action Interface
 *
 * Contract for all action classes in the rules system.
 *
 * @package MilliRules
 * @since 1.0.0
 */

namespace MilliRules\Actions;

/**
 * Interface ActionInterface
 *
 * Defines the contract that all action classes must implement.
 *
 * @since 1.0.0
 */
interface ActionInterface {
	/**
	 * Execute the action based on the provided context.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context containing request and application data.
	 * @return void
	 */
	public function execute( array $context ): void;

	/**
	 * Get the type identifier for this action.
	 *
	 * @since 1.0.0
	 *
	 * @return string The action type (e.g., 'callback', 'custom_action').
	 */
	public function get_type(): string;
}
