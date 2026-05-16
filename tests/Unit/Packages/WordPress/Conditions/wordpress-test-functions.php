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
     * Determines whether the query has resulted in a 404 (returns no results).
     */
    function is_404(): bool
    {
        return true;
    }
}

if (! function_exists('is_category')) {
    /**
     * Determines whether the query is for an existing category archive page.
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
     * Determines whether the query is for an existing custom taxonomy archive page.
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
     * Determines whether the query is for an existing single post of any post type.
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
     * Determines whether the query is for an existing author archive page.
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
     * Determines whether a post has an image attached.
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
     * Determines whether a $post or a string contains a specific block type.
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
     * Checks if the current post has any of given terms.
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
     * Determines whether the passed content contains the specified shortcode.
     *
     * @param string $content Content to search for shortcodes.
     * @param string $tag     Shortcode tag to check.
     */
    function has_shortcode(string $content, string $tag): bool
    {
        return $tag === 'gallery';
    }
}

// -----------------------------------------------------------------------------
// Multisite helpers
// -----------------------------------------------------------------------------

if (! function_exists('get_current_blog_id')) {
    /**
     * Retrieves the current site ID.
     *
     * Reads from $GLOBALS['millirules_test_current_blog_id'] so tests can
     * vary the simulated blog ID per case. Defaults to 1 (single-site
     * default) when the global is unset.
     */
    function get_current_blog_id(): int
    {
        return (int) ( $GLOBALS['millirules_test_current_blog_id'] ?? 1 );
    }
}
