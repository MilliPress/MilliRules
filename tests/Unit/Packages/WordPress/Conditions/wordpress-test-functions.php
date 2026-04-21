<?php

/**
 * Test helper functions for WordPress conditional testing
 *
 * These functions simulate WordPress conditional functions for testing purposes.
 * They are loaded before test execution to provide a test environment
 * where WordPress is_* functions are available.
 *
 * Docblocks mirror WordPress core signatures so that docblock-based type
 * detection (IsConditional::get_first_param_type) works correctly in tests.
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

if (! function_exists('is_category')) {
    /**
     * Simulate WordPress is_category() function.
     * Returns true for 'news' or any array containing 'news'.
     *
     * @param int|string|int[]|string[] $category Category ID, name, slug, or array of them.
     */
    function is_category($category = ''): bool
    {
        if (is_array($category)) {
            return in_array('news', $category, true);
        }
        return $category === 'news';
    }
}

if (! function_exists('is_tax')) {
    /**
     * Simulate WordPress is_tax() function.
     * Returns true only for ('genre', 'sci-fi') arguments.
     *
     * @param string|string[]           $taxonomy Taxonomy slug or array of slugs.
     * @param int|string|int[]|string[] $term     Term ID, name, slug, or array of them.
     */
    function is_tax($taxonomy = '', $term = ''): bool
    {
        return $taxonomy === 'genre' && $term === 'sci-fi';
    }
}

if (! function_exists('is_singular')) {
    /**
     * Simulate WordPress is_singular() function.
     * Returns true only for 'page' argument.
     *
     * @param string|string[] $post_types Post type or array of post types.
     */
    function is_singular($post_types = ''): bool
    {
        return $post_types === 'page';
    }
}

if (! function_exists('is_author')) {
    /**
     * Simulate WordPress is_author() function.
     * Returns true when called with no arguments (boolean mode)
     * or when called with 'john'.
     *
     * @param int|string $author Author ID or nicename.
     */
    function is_author($author = null): bool
    {
        if ($author === null) {
            return true;
        }
        return $author === 'john';
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
