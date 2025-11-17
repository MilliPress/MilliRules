<?php

/**
 * Test helper functions for WordPress conditional testing
 *
 * These functions simulate WordPress conditional functions for testing purposes.
 * They are loaded before test execution to provide a test environment
 * where WordPress is_* functions are available.
 */

if (! function_exists('is_404')) {
    /**
     * Simulate WordPress is_404() function.
     * Always returns true for testing.
     */
    function is_404(): bool
    {
        return true;
    }
}

if (! function_exists('is_tax')) {
    /**
     * Simulate WordPress is_tax() function.
     * Returns true only for ('genre', 'sci-fi') arguments.
     *
     * @param mixed ...$args
     */
    function is_tax(): bool
    {
        $args = func_get_args();
        return $args === array('genre', 'sci-fi');
    }
}

if (! function_exists('is_singular')) {
    /**
     * Simulate WordPress is_singular() function.
     * Returns true only for 'page' argument.
     *
     * @param mixed ...$args
     */
    function is_singular(): bool
    {
        $args = func_get_args();
        return $args === array('page');
    }
}
