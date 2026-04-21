<?php

/**
 * Generic Is Condition
 *
 * Generic fallback for WordPress is_* conditional functions.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\WordPress\Conditions;

use MilliRules\Conditions\BaseCondition;
use MilliRules\Conditions\ConditionMeta;
use MilliRules\Context;

/**
 * Class IsConditional
 *
 * Provides a generic fallback for WordPress conditional functions that start with "is_".
 * This class dynamically calls the corresponding WordPress function when no specific
 * condition class exists.
 *
 * Modes:
 * - Boolean mode (no args, no value): calls the is_* function with no arguments
 *   and checks the boolean result. Default operator is 'IS' (expects TRUE).
 *   Examples:
 *     ->is_404()                    // is_404() IS TRUE
 *     ['type' => 'is_404']          // is_404() IS TRUE
 *
 * - Value-based mode (value set): passes the value to the is_* function as its
 *   first argument. The operator determines negation and whether the value is
 *   a single item or a list.
 *   Examples:
 *     ['type' => 'is_category', 'value' => 'news', 'operator' => 'IS']
 *         → is_category('news') === true
 *     ['type' => 'is_category', 'value' => ['news','sports'], 'operator' => 'IN']
 *         → is_category(['news','sports']) === true
 *
 * - Function-call mode (with args): uses the raw method arguments to call the
 *   is_* function, and compares the resulting boolean value to TRUE using an
 *   optional operator taken from the last argument if it looks like an operator.
 *   Examples:
 *     ->is_singular('page')                 // is_singular('page') IS TRUE
 *     ->is_tax('genre', 'sci-fi')          // is_tax('genre','sci-fi') IS TRUE
 *     ->is_tax('genre', 'sci-fi', '!=')    // is_tax('genre','sci-fi') != TRUE
 *
 * Raw arguments are provided by ConditionBuilder via the 'args' key.
 * Other packages can reuse this convention for their own condition types.
 *
 * @since 0.1.0
 */
class IsConditional extends BaseCondition
{
    /**
     * Define argument mapping for IsConditional.
     *
     * IsConditional uses custom argument handling - returns empty array
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
     * for WordPress is_* conditionals.
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

        return 'is_*';
    }

    /**
     * Check if the condition matches.
     *
     * Calls the WordPress conditional function and interprets the boolean
     * result based on the operator. The value (if not boolean) is passed
     * to the function as its argument — WordPress conditionals like
     * is_category() accept both a single value and an array natively.
     *
     * @since 0.1.0
     *
     * @param Context $context The execution context.
     * @return bool True if the condition matches, false otherwise.
     */
    public function matches(Context $context): bool
    {
        $fn = $this->get_type();

        if (empty($fn) || ! function_exists($fn)) {
            return false;
        }

        // Builder path: explicit args array.
        if (isset($this->config['args']) && is_array($this->config['args']) && ! empty($this->config['args'])) {
            $args = $this->config['args'];
            while ($args && '' === end($args)) {
                array_pop($args);
            }
            $result = $args ? (bool) call_user_func_array($fn, $args) : (bool) call_user_func($fn);
        } elseif (! is_bool($this->value) && $this->value !== '' && $this->value !== null) {
            // Value-based path: pass value as the function's argument.
            $result = (bool) call_user_func($fn, $this->value);
        } else {
            // Boolean mode: no arguments.
            $result = (bool) call_user_func($fn);
        }

        // IS/IN → match when function returns true.
        // IS NOT/NOT IN/!= → match when function returns false.
        return in_array($this->operator, array( 'IS NOT', 'NOT IN', '!=' ), true)
            ? ! $result
            : $result;
    }

    /**
     * Get the actual value from context.
     *
     * Not used directly — matches() handles the full comparison. Kept
     * because BaseCondition declares this method abstract.
     *
     * @since 0.1.0
     *
     * @param Context $context The execution context.
     * @return bool The result of the WordPress conditional function, or false if it doesn't exist.
     */
    protected function get_actual_value(Context $context): bool
    {
        $fn = $this->get_type();

        if (empty($fn) || ! function_exists($fn)) {
            return false;
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
    private function looks_like_operator(string $value): bool
    {
        $upper = strtoupper(trim($value));
        $supported = array('=', '!=', 'IS', 'IS NOT');
        return in_array($upper, $supported, true);
    }

    /** @inheritDoc */
    public static function is_discoverable(): bool
    {
        return false;
    }

    /**
     * Auto-generate metadata from the WordPress function this condition wraps.
     *
     * Combines reflection (parameter count, optionality) with docblock
     * parsing (types, descriptions) to produce accurate argument schemas.
     *
     * Single-param functions use value-based mapping (['value']) with
     * a properly labeled and described argument. Multi-param functions
     * use custom mapping ([]) with indexed argument schemas.
     *
     * The first parameter's docblock type determines available operators:
     * - Type includes 'array' → IS, IS NOT, IN, NOT IN
     * - Otherwise             → IS, IS NOT
     *
     * @since 1.1.0
     *
     * @param ConditionMeta $meta The metadata object (type is the specific function name).
     * @return void
     */
    public static function set_meta(ConditionMeta $meta): void
    {
        $fn = $meta->get_type();

        $meta
            ->label(ucwords(str_replace('_', ' ', $fn)))
            ->categories('wordpress');

        if (! function_exists($fn)) {
            $meta->operators('IS', 'IS NOT');
            return;
        }

        try {
            $ref = new \ReflectionFunction($fn);
        } catch (\ReflectionException $e) {
            $meta->operators('IS', 'IS NOT');
            return;
        }

        $description = self::parse_docblock_summary($ref->getDocComment() ?: '');
        if ('' !== $description) {
            $meta->description($description);
        }

        $params = $ref->getParameters();

        if (empty($params)) {
            $meta->operators('IS', 'IS NOT');
            return;
        }

        $doc_params    = self::parse_docblock_params($ref->getDocComment() ?: '');
        $first_type    = $doc_params[0]['type'] ?? null;
        $accepts_array = $first_type && self::type_includes_array($first_type);

        if (count($params) === 1) {
            // Value-based: single argument mapped to 'value'.
            $meta->argument_mapping(array( 'value' ));
            $doc    = $doc_params[0] ?? array();
            $schema = $meta->args()
                ->string('value')
                ->label(ucwords(str_replace('_', ' ', $params[0]->getName())));

            if (! empty($doc['description'])) {
                $schema->description($doc['description']);
            }
            if ($accepts_array) {
                $schema->also_accepts('array');
            }
            // Value is never required — omitting it falls back to boolean mode.

            // IN/NOT IN only makes sense for value mode where the single
            // argument is the comparison target.
            if ($accepts_array) {
                $meta->operators('IS', 'IS NOT', 'IN', 'NOT IN');
            } else {
                $meta->operators('IS', 'IS NOT');
            }
        } else {
            // Multi-param (args mode): indexed argument schemas.
            // Operators describe boolean result interpretation only.
            $builder = $meta->args();
            foreach ($params as $i => $param) {
                $doc    = $doc_params[ $i ] ?? array();
                $type   = $doc['type'] ?? null;
                $schema = $builder->string($i)
                    ->label(ucwords(str_replace('_', ' ', $param->getName())));

                if (! empty($doc['description'])) {
                    $schema->description($doc['description']);
                }
                if ($type && self::type_includes_array($type)) {
                    $schema->also_accepts('array');
                }
                if (! $param->isOptional()) {
                    $schema->required();
                }
            }

            $meta->operators('IS', 'IS NOT');
        }
    }

    /**
     * Parse all @param tags from a docblock.
     *
     * Returns an indexed array (matching parameter order) of arrays
     * with 'type', 'name', and 'description' keys.
     *
     * Handles multi-line descriptions (continuation lines that are
     * indented but don't start with @) and WordPress-style formatting
     * where type and variable may be separated by extra whitespace.
     *
     * @since 1.1.0
     *
     * @param string $doc The raw docblock string.
     * @return array<int, array{type: string, name: string, description: string}>
     */
    private static function parse_docblock_params(string $doc): array
    {
        if ('' === $doc) {
            return array();
        }

        // Match @param lines: type, $name, and the rest of the line as description start.
        // The type may contain unions (string|int), arrays (int[]), and generics.
        if (! preg_match_all(
            '/@param\s+(\S+)\s+\$(\w+)\s*(.*?)(?=\n\s*\*\s*@|\n\s*\*\/|\z)/s',
            $doc,
            $matches,
            PREG_SET_ORDER
        )) {
            return array();
        }

        $params = array();
        foreach ($matches as $match) {
            // Clean up the description: strip leading * from continuation lines,
            // collapse whitespace, and trim.
            $desc = preg_replace('/\n\s*\*\s*/', ' ', $match[3]);
            $desc = trim(preg_replace('/\s+/', ' ', $desc));

            // Strip "Optional." prefix — the schema's required flag handles this.
            $desc = preg_replace('/^Optional\.\s*/i', '', $desc);

            $params[] = array(
                'type'        => $match[1],
                'name'        => $match[2],
                'description' => $desc,
            );
        }

        return $params;
    }

    /**
     * Extract the summary line from a docblock.
     *
     * The summary is the first non-empty line after the opening `/**`.
     * Stops at the first blank line, `@` tag, or closing `*​/`.
     *
     * @since 1.1.0
     *
     * @param string $doc The raw docblock string.
     * @return string The summary text, or empty string if none found.
     */
    private static function parse_docblock_summary(string $doc): string
    {
        if ('' === $doc) {
            return '';
        }

        // Strip opening /** and closing */, then split into lines.
        $body = preg_replace('/^\/\*\*|\*\/$/s', '', $doc);
        $lines = preg_split('/\r?\n/', $body);

        $summary = '';
        foreach ($lines as $line) {
            // Strip leading whitespace and * prefix.
            $line = preg_replace('/^\s*\*\s?/', '', $line);

            // Stop at blank line or @tag.
            if ('' !== $summary && ( '' === trim($line) || strpos(ltrim($line), '@') === 0 )) {
                break;
            }

            if ('' !== trim($line) && strpos(ltrim($line), '@') !== 0) {
                $summary .= ( '' !== $summary ? ' ' : '' ) . trim($line);
            }
        }

        return $summary;
    }

    /**
     * Check whether a docblock type string includes 'array'.
     *
     * Handles union types like 'string|int|array', standalone 'array',
     * and typed arrays like 'int[]'.
     *
     * @since 1.1.0
     *
     * @param string $type The docblock type string.
     * @return bool
     */
    private static function type_includes_array(string $type): bool
    {
        $parts = preg_split('/[|&]/', $type);

        foreach ($parts as $part) {
            $part = trim($part);
            if ('array' === $part || 'mixed' === $part || substr($part, -2) === '[]') {
                return true;
            }
        }

        return false;
    }
}
