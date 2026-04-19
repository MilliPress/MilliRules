<?php

/**
 * Generic Has Condition
 *
 * Generic fallback for WordPress has_* conditional functions.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;
use MilliRules\Conditions\ConditionMeta;
use MilliRules\Context;

/**
 * Class HasConditional
 *
 * Provides a generic fallback for WordPress conditional functions that start with "has_".
 * This class dynamically calls the corresponding WordPress function when no specific
 * condition class exists.
 *
 * Modes:
 * - Boolean mode (no args): calls the has_* function with no arguments and compares
 *   the boolean result to the configured value (default TRUE) using the configured
 *   operator (default 'IS').
 *   Examples:
 *     ->has_post_thumbnail()                    // has_post_thumbnail() IS TRUE
 *     ['type' => 'has_post_thumbnail']          // has_post_thumbnail() IS TRUE
 *     ['type' => 'has_post_thumbnail','value'=>false,'operator'=>'IS']
 *
 * - Function-call mode (with args): uses the raw method arguments to call the
 *   has_* function, and compares the resulting boolean value to TRUE using an
 *   optional operator taken from the last argument if it looks like an operator.
 *   Examples:
 *     ->has_block('core/paragraph')             // has_block('core/paragraph') IS TRUE
 *     ->has_term('news', 'category')            // has_term('news','category') IS TRUE
 *     ->has_term('news', 'category', '!=')      // has_term('news','category') != TRUE
 *
 * Raw arguments are provided by ConditionBuilder via the 'args' key.
 * Other packages can reuse this convention for their own condition types.
 *
 * @since 0.1.0
 */
class HasConditional extends BaseCondition
{
    /**
     * Define argument mapping for HasConditional.
     *
     * HasConditional uses custom argument handling - returns empty array
     * to signal that all args should be passed through for custom interpretation.
     * See BaseCondition::get_argument_mapping() for detailed explanation.
     *
     * @since 0.1.0
     *
     * @return array<int, string>
     */
    public static function get_argument_mapping(): array
    {
        return [];  // Empty = pass through as 'args' for custom handling
    }

    /**
     * Constructor.
     *
     * Sets up comparison defaults and interprets builder arguments
     * for WordPress has_* conditionals.
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $config  The condition configuration.
     * @param Context              $context The execution context.
     */
    public function __construct(array $config, Context $context)
    {
        // Interpret raw builder arguments before applying defaults.
        //
        // Builder input has 'args' without 'value' — the args need
        // interpretation (operator extraction, etc.).
        // Stored data has 'args' alongside 'value' and 'operator',
        // so it skips this block entirely.
        $has_raw_args = isset($config['args']) && is_array($config['args']) && ! empty($config['args']);
        if ($has_raw_args && ! isset($config['value'])) {
            $args = $config['args'];

            // Extract trailing operator if present.
            $maybe_op = end($args);
            $operator = null;

            if (is_string($maybe_op) && self::looks_like_operator($maybe_op)) {
                $operator = $maybe_op;
                array_pop($args);
            }

            // Store cleaned function args.
            $config['args'] = $args;
            $config['value'] = true;
            $config['operator'] = $operator !== null ? $operator : 'IS';
        }

        // Apply defaults for anything not yet set.
        if (! isset($config['operator'])) {
            $config['operator'] = 'IS';
        }
        if (! isset($config['value'])) {
            $config['value'] = true;
        }

        parent::__construct($config, $context);
    }

    /**
     * Get the condition type.
     *
     * @since 0.1.0
     *
     * @return string The condition type identifier.
     */
    public function get_type(): string
    {
        $type_value = $this->config['type'] ?? '';

        if (is_string($type_value) && $type_value !== '') {
            return $type_value;
        }

        return 'has_*';
    }

    /**
     * Get the actual value from context.
     *
     * Dynamically calls the WordPress conditional function corresponding to the type.
     * For example, type 'has_post_thumbnail' will call the has_post_thumbnail() function.
     *
     * If 'args' is present in config, calls the function with those arguments:
     *   has_term('news','category')
     * Otherwise, calls the function with no arguments.
     *
     * @since 0.1.0
     *
     * @param Context $context The execution context.
     * @return bool The result of the WordPress conditional function, or false if it doesn't exist.
     */
    protected function get_actual_value(Context $context): bool
    {
        $fn = $this->get_type();

        // Check if the function exists.
        if (empty($fn) || ! function_exists($fn)) {
            return false;
        }

        // Call with or without function arguments.
        if (isset($this->config['args']) && is_array($this->config['args']) && ! empty($this->config['args'])) {
            // Right-trim trailing empty strings.
            $args = $this->config['args'];
            while ($args && '' === end($args)) {
                array_pop($args);
            }
            if ($args) {
                return (bool) call_user_func_array($fn, $args);
            }
        }

        return (bool) call_user_func($fn);
    }

    /**
     * Check if a string looks like a comparison operator we support.
     *
     * Supported operators for this condition:
     * - '='
     * - '!='
     * - 'IS'
     * - 'IS NOT'
     *
     * @since 0.1.0
     *
     * @param string $value Candidate operator string.
     * @return bool
     */
    private static function looks_like_operator(string $value): bool
    {
        $upper = strtoupper(trim($value));
        $supported = array('=', '!=', 'IS', 'IS NOT');
        return in_array($upper, $supported, true);
    }



    /**
     * Auto-generate metadata from the WordPress function this condition wraps.
     *
     * Since set_meta() is called after the framework has initialized, the
     * WordPress function is available for reflection. The label is derived
     * from the function name (has_block → "Has Block"), and arguments are
     * extracted from the function's parameters.
     *
     * @since 1.1.0
     *
     * @param ConditionMeta $meta The metadata object (type is the specific function name).
     * @return void
     */
    public static function set_meta(ConditionMeta $meta): void
    {
        $fn = $meta->get_type();

        $meta->label(ucwords(str_replace('_', ' ', $fn)));

        if (function_exists($fn)) {
            IsConditional::extract_function_args($meta, $fn);
        }

        $meta
            ->categories('wordpress')
            ->operators('IS', 'IS NOT');
    }
}
