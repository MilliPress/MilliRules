<?php

/**
 * Cookie Context
 *
 * Provides cookie data from HTTP request.
 *
 * @package     MilliRules
 * @subpackage  PHP\Contexts
 * @author      Philipp Wellmer
 * @since       0.1.0
 */

namespace MilliRules\Packages\PHP\Contexts;

use MilliRules\Contexts\BaseContext;

/**
 * Class Cookie
 *
 * Provides 'cookie' context with cookie data from the HTTP request.
 *
 * @since 0.1.0
 */
class Cookie extends BaseContext
{
    /**
     * Get the context key.
     *
     * @since 0.1.0
     *
     * @return string The context key 'cookie'.
     */
    public function get_key(): string
    {
        return 'cookie';
    }

    /**
     * Get the human-readable label.
     *
     * @since 1.3.0
     *
     * @return string The label.
     */
    public function get_label(): string
    {
        return 'Cookie';
    }

    /**
     * Get the description.
     *
     * @since 1.3.0
     *
     * @return string The description.
     */
    public function get_description(): string
    {
        return 'A cookie by name, for example {cookie.geo_country}.';
    }

    /**
     * Build the cookie context data.
     *
     * Captures $_COOKIE at execution time (when context is actually needed)
     * rather than at construction time.
     *
     * @since 0.1.0
     *
     * @return array<string, mixed> The cookie context.
     */
    protected function build(): array
    {
        return array(
            'cookie' => $_COOKIE,
        );
    }
}
