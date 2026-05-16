<?php

/**
 * Current Site Condition
 *
 * Matches against the current blog ID. Useful primarily for network-defined
 * rules that should apply only to a subset of subsites. Single-site installs
 * always evaluate to blog ID 1, so the condition still works (just degenerate
 * — every check compares against 1).
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;
use MilliRules\Conditions\ConditionMeta;
use MilliRules\Context;

/**
 * Class CurrentSite
 *
 * Supported operators:
 * - =: Exact blog ID match (default)
 * - !=: Blog ID does not match
 * - IN: Blog ID is in array
 * - NOT IN: Blog ID is not in array
 *
 * Examples:
 * Array syntax:
 * - Single site:  ['type' => 'current_site', 'value' => 5]
 * - Excluded:     ['type' => 'current_site', 'value' => 1, 'operator' => '!=']
 * - Multi site:   ['type' => 'current_site', 'value' => [2, 5, 7], 'operator' => 'IN']
 *
 * Builder syntax:
 * - ->current_site(5)
 * - ->current_site([2, 5, 7], 'IN')
 *
 * @since 1.2.0
 */
class CurrentSite extends BaseCondition
{
    /**
     * Get the condition type.
     *
     * @since 1.2.0
     *
     * @return string The condition type identifier.
     */
    public function get_type(): string
    {
        return 'current_site';
    }

    /**
     * Get the current blog ID as a string.
     *
     * Returned as a string so equality and IN comparisons line up with
     * BaseCondition::compare_values(), which stringifies both sides before
     * comparing for =, !=, IN, and NOT IN.
     *
     * Falls back to '1' when get_current_blog_id() is unavailable (non-
     * WordPress test environments), matching single-site default behavior.
     *
     * @since 1.2.0
     *
     * @param Context $context The execution context.
     * @return string The current blog ID.
     */
    protected function get_actual_value(Context $context): string
    {
        return function_exists('get_current_blog_id')
            ? (string) get_current_blog_id()
            : '1';
    }

    /**
     * @since 1.2.0
     *
     * @param ConditionMeta $meta The metadata object to configure.
     */
    public static function set_meta(ConditionMeta $meta): void
    {
        $meta
            ->label('Current Site')
            ->description(
                'Match the WordPress site (blog) the request belongs to. '
                . 'Only meaningful on multisite — single-site installs always evaluate to blog 1.'
            )
            ->categories('multisite', 'context')
            ->operators('=', '!=', 'IN', 'NOT IN')
            ->args()
                ->integer('value')->label('Site ID')->required();
    }
}
