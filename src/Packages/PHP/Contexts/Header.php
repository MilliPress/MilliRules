<?php

/**
 * Header Context
 *
 * Provides HTTP request headers.
 *
 * @package     MilliRules
 * @subpackage  PHP\Contexts
 * @author      Philipp Wellmer
 * @since       1.3.0
 */

namespace MilliRules\Packages\PHP\Contexts;

use MilliRules\Contexts\BaseContext;

/**
 * Class Header
 *
 * Provides 'header' context with the request headers, for `{header.accept}`.
 *
 * @since 1.3.0
 */
class Header extends BaseContext
{
    /**
     * Get the context key.
     *
     * @since 1.3.0
     *
     * @return string The context key 'header'.
     */
    public function get_key(): string
    {
        return 'header';
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
        return 'Request Header';
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
        return 'A request header by name, for example {header.accept}. '
            . 'This is the shorter form of the {request.headers} map.';
    }

    /**
     * The request context normalises headers across SAPIs.
     *
     * @since 1.3.0
     *
     * @return array<int, string> The context keys this context depends on.
     */
    public function get_dependencies(): array
    {
        return array( 'request' );
    }

    /**
     * Build the header context data.
     *
     * @since 1.3.0
     *
     * @return array<string, mixed> The header context.
     */
    protected function build(): array
    {
        $headers = $this->context->get('request.headers', array());

        return array(
            'header' => is_array($headers) ? $headers : array(),
        );
    }
}
