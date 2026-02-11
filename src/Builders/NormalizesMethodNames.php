<?php

/**
 * Normalizes Method Names Trait
 *
 * Provides camelCase to snake_case method name conversion
 * for fluent API classes that support both naming conventions.
 *
 * @package     MilliRules
 * @subpackage  Builders
 * @author      Philipp Wellmer <hello@millirules.com>
 */

namespace MilliRules\Builders;

trait NormalizesMethodNames
{
    /**
     * Convert a camelCase method name to snake_case.
     *
     * Examples:
     * - 'whenAll'       → 'when_all'
     * - 'setConditions' → 'set_conditions'
     * - 'when_all'      → 'when_all' (no-op)
     * - 'register'      → 'register' (no-op)
     *
     * @since 0.7.0
     *
     * @param string $method The method name.
     * @return string The snake_case method name.
     */
    private function normalize_method_name(string $method): string
    {
        $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $method);

        return strtolower(is_string($replaced) ? $replaced : $method);
    }
}