<?php

/**
 * Param Context
 *
 * Provides request parameters (GET/POST data).
 *
 * @package     MilliRules
 * @subpackage  PHP\Contexts
 * @author      Philipp Wellmer
 * @since       0.1.0
 */

namespace MilliRules\Packages\PHP\Contexts;

use MilliRules\Contexts\BaseContext;

/**
 * Class Param
 *
 * Provides 'param' context with request parameters from GET/POST.
 *
 * @since 0.1.0
 */
class Param extends BaseContext
{
    /**
     * Get the context key.
     *
     * @since 0.1.0
     *
     * @return string The context key 'param'.
     */
    public function get_key(): string
    {
        return 'param';
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
        return 'Request Parameter';
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
        return 'A URL query parameter by name, for example {param.utm_source}.';
    }

    /**
     * Build the param context data.
     *
     * Captures $_GET at execution time (when context is actually needed)
     * rather than at construction time.
     *
     * @since 0.1.0
     *
     * @return array<string, mixed> The param context.
     */
    protected function build(): array
    {
        return array(
            'param' => $_GET,
        );
    }
}
