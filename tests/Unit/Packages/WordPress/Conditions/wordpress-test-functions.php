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

// -----------------------------------------------------------------------------
// has_* functions for HasConditional tests
// -----------------------------------------------------------------------------

if (! function_exists('has_post_thumbnail')) {
    /**
     * Simulate WordPress has_post_thumbnail() function.
     * Always returns true for testing.
     *
     * @param int|WP_Post $post Optional. Post ID or WP_Post object. Default is global `$post`.
     */
    function has_post_thumbnail($post = null): bool
    {
        return true;
    }
}

if (! function_exists('has_block')) {
    /**
     * Simulate WordPress has_block() function.
     * Returns true only for 'core/paragraph' block.
     *
     * @param string           $block_name Full block type to look for.
     * @param int|string|WP_Post|null $post Optional. Post content, post ID, or WP_Post object.
     */
    function has_block(string $block_name, $post = null): bool
    {
        return $block_name === 'core/paragraph';
    }
}

if (! function_exists('has_term')) {
    /**
     * Simulate WordPress has_term() function.
     * Returns true only for ('news', 'category') arguments.
     *
     * @param string|int|array $term     The term name/id/slug or array of them.
     * @param string           $taxonomy The taxonomy name.
     * @param int|WP_Post|null $post     Optional. Post to check instead of the current post.
     */
    function has_term($term = '', string $taxonomy = '', $post = null): bool
    {
        return $term === 'news' && $taxonomy === 'category';
    }
}

if (! function_exists('has_shortcode')) {
    /**
     * Simulate WordPress has_shortcode() function.
     * Returns true only for 'gallery' shortcode.
     *
     * @param string $content Content to search for shortcodes.
     * @param string $tag     Shortcode tag to check.
     */
    function has_shortcode(string $content, string $tag): bool
    {
        return $tag === 'gallery';
    }
}
