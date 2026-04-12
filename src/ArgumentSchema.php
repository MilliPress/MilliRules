<?php

/**
 * Argument Schema
 *
 * Declarative metadata for a single action or condition argument. Constructed
 * by ArgumentsBuilder inside an ActionMeta::args() or ConditionMeta::args()
 * context, never directly by consumer code.
 *
 * # Walking builder pattern
 *
 * An ArgumentSchema is chainable in two ways:
 *
 * 1. **Config setters** (label(), default(), min(), etc.) return `$this`, so
 *    you keep configuring the current argument.
 * 2. **Type methods** (integer(), string(), choice(), etc.) delegate back
 *    to the parent ArgumentsBuilder to start a NEW argument with that type.
 *
 * This lets the entire arguments declaration flow as a single chain:
 *
 *     $meta->args()
 *         ->integer('ttl')->format('seconds')->default(3600)->min(0)
 *         ->string('reason')->default('')
 *         ->choice('mode')->options(['auto', 'manual'])->default('auto');
 *
 * # Types vs formats
 *
 * Six engine-relevant types cover all core data shapes: string, integer,
 * number, boolean, choice, choices. UI-level specialization like 'seconds',
 * 'url', 'email' flows through the open `format` field. MilliRules stores
 * format but never interprets it — consumers (UIs, validators) pick their
 * own vocabulary.
 *
 * # Engine unchanged
 *
 * RuleEngine does NOT read ArgumentSchema at runtime. It's purely metadata
 * for consumers. validate() and sanitize() are opt-in utilities so every
 * consumer doesn't re-implement type coercion.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer <hello@millirules.com>
 * @since       1.2.0
 */

namespace MilliRules;

/**
 * Class ArgumentSchema
 *
 * @since 1.2.0
 */
class ArgumentSchema
{
    // Engine-level type constants.
    public const TYPE_STRING  = 'string';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_NUMBER  = 'number';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_CHOICE  = 'choice';
    public const TYPE_CHOICES = 'choices';

    /**
     * The parent builder, used for walking delegation when a type method
     * is called on an existing schema to start a new argument.
     *
     * @since 1.2.0
     * @var ArgumentsBuilder
     */
    private ArgumentsBuilder $parent;

    /**
     * The argument key (positional int or named string).
     *
     * @since 1.2.0
     * @var int|string
     */
    private $key;

    /**
     * The engine-level type.
     *
     * @since 1.2.0
     * @var string
     */
    private string $type;

    /**
     * Consumer-defined format hint (e.g., 'seconds', 'url', 'regex').
     *
     * @since 1.2.0
     * @var string
     */
    private string $format = '';

    /**
     * Human-readable label.
     *
     * @since 1.2.0
     * @var string
     */
    private string $label = '';

    /**
     * Help text description.
     *
     * @since 1.2.0
     * @var string
     */
    private string $description = '';

    /**
     * Default value.
     *
     * @since 1.2.0
     * @var mixed
     */
    private $default = null;

    /**
     * Whether default() has been called.
     *
     * @since 1.2.0
     * @var bool
     */
    private bool $has_default = false;

    /**
     * Whether the argument is required.
     *
     * @since 1.2.0
     * @var bool
     */
    private bool $required = false;

    /**
     * Minimum bound (int for string length / integer value, int|float for number value).
     *
     * @since 1.2.0
     * @var int|float|null
     */
    private $min = null;

    /**
     * Maximum bound (int for string length / integer value, int|float for number value).
     *
     * @since 1.2.0
     * @var int|float|null
     */
    private $max = null;

    /**
     * Allowed options for choice/choices types.
     *
     * Stored in normalized structured form:
     * [{'value' => mixed, 'label' => string}, ...]
     *
     * @since 1.2.0
     * @var array<int, array{value: mixed, label: string}>
     */
    private array $options = array();

    /**
     * Constructor.
     *
     * @internal Consumers should obtain schemas via ArgumentsBuilder type
     *           factories (e.g., $meta->args()->integer('ttl')), not by
     *           instantiating directly. The parent reference is required
     *           for walking delegation.
     *
     * @since 1.2.0
     *
     * @param ArgumentsBuilder $parent The parent builder.
     * @param int|string       $key    The argument key.
     * @param string           $type   One of the TYPE_* constants.
     */
    public function __construct(ArgumentsBuilder $parent, $key, string $type)
    {
        $this->parent = $parent;
        $this->key    = $key;
        $this->type   = $type;
    }

    // -----------------------------------------------------------------
    // Fluent setters — return self for continued configuration
    // -----------------------------------------------------------------

    /**
     * Set the consumer-defined format hint.
     *
     * MilliRules stores but never interprets this value. Consumers pick
     * their own vocabulary ('url', 'seconds', 'regex', 'email', etc.).
     *
     * @since 1.2.0
     *
     * @param string $format The format hint.
     * @return self
     */
    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Set the human-readable label.
     *
     * @since 1.2.0
     *
     * @param string $label The label (typically translated).
     * @return self
     */
    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Set the help text description.
     *
     * @since 1.2.0
     *
     * @param string $description The description (typically translated).
     * @return self
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Mark the argument as required.
     *
     * @since 1.2.0
     *
     * @param bool $required Whether the argument is required.
     * @return self
     */
    public function required(bool $required = true): self
    {
        $this->required = $required;
        return $this;
    }

    /**
     * Set the default value for the argument.
     *
     * Closures are rejected because schemas must be JSON-serializable.
     * Null is accepted and tracked separately from "no default set".
     *
     * @since 1.2.0
     *
     * @param mixed $value The default value.
     * @return self
     * @throws \InvalidArgumentException If the value is a closure.
     */
    public function default($value): self
    {
        if ($value instanceof \Closure) {
            throw new \InvalidArgumentException(
                'ArgumentSchema::default() does not accept closures; defaults must be JSON-serializable'
            );
        }

        $this->default     = $value;
        $this->has_default = true;
        return $this;
    }

    /**
     * Set the minimum bound.
     *
     * For string: minimum length in characters (int).
     * For integer: minimum value (int).
     * For number: minimum value (int or float).
     * For other types: throws.
     *
     * @since 1.2.0
     *
     * @param int|float $min The minimum bound.
     * @return self
     * @throws \InvalidArgumentException If the type does not support bounds, or $min is not numeric.
     */
    public function min($min): self
    {
        $this->assert_supports_bounds('min');
        if (! is_int($min) && ! is_float($min)) {
            throw new \InvalidArgumentException(
                'ArgumentSchema::min() expects int or float, got ' . gettype($min)
            );
        }
        $this->min = $min;
        return $this;
    }

    /**
     * Set the maximum bound.
     *
     * For string: maximum length in characters (int).
     * For integer: maximum value (int).
     * For number: maximum value (int or float).
     * For other types: throws.
     *
     * @since 1.2.0
     *
     * @param int|float $max The maximum bound.
     * @return self
     * @throws \InvalidArgumentException If the type does not support bounds, or $max is not numeric.
     */
    public function max($max): self
    {
        $this->assert_supports_bounds('max');
        if (! is_int($max) && ! is_float($max)) {
            throw new \InvalidArgumentException(
                'ArgumentSchema::max() expects int or float, got ' . gettype($max)
            );
        }
        $this->max = $max;
        return $this;
    }

    /**
     * Set the allowed options for choice/choices types.
     *
     * Accepts either:
     * - Simple form: ['a', 'b', 'c']  (each entry becomes {value, label} with value == label)
     * - Structured form: [['value' => 'a', 'label' => 'Apple'], ...]
     *
     * @since 1.2.0
     *
     * @param array<int, mixed> $options The allowed options.
     * @return self
     * @throws \InvalidArgumentException If called on a non-choice type.
     */
    public function options(array $options): self
    {
        $this->assert_supports_options();
        $this->options = self::normalize_options($options);
        return $this;
    }

    // -----------------------------------------------------------------
    // Walking type methods — delegate to parent to start a new argument
    // -----------------------------------------------------------------

    /**
     * Start a new string argument on the parent builder.
     *
     * @since 1.2.0
     *
     * @param int|string $key The new argument's key.
     * @return self The new argument (not this one).
     */
    public function string($key): self
    {
        return $this->parent->string($key);
    }

    /**
     * Start a new integer argument on the parent builder.
     *
     * @since 1.2.0
     *
     * @param int|string $key The new argument's key.
     * @return self The new argument (not this one).
     */
    public function integer($key): self
    {
        return $this->parent->integer($key);
    }

    /**
     * Start a new number (float) argument on the parent builder.
     *
     * @since 1.2.0
     *
     * @param int|string $key The new argument's key.
     * @return self The new argument (not this one).
     */
    public function number($key): self
    {
        return $this->parent->number($key);
    }

    /**
     * Start a new boolean argument on the parent builder.
     *
     * @since 1.2.0
     *
     * @param int|string $key The new argument's key.
     * @return self The new argument (not this one).
     */
    public function boolean($key): self
    {
        return $this->parent->boolean($key);
    }

    /**
     * Start a new single-choice argument on the parent builder.
     *
     * @since 1.2.0
     *
     * @param int|string $key The new argument's key.
     * @return self The new argument (not this one).
     */
    public function choice($key): self
    {
        return $this->parent->choice($key);
    }

    /**
     * Start a new multi-choice argument on the parent builder.
     *
     * @since 1.2.0
     *
     * @param int|string $key The new argument's key.
     * @return self The new argument (not this one).
     */
    public function choices($key): self
    {
        return $this->parent->choices($key);
    }

    /**
     * Forward unknown method calls to the parent ArgumentsBuilder.
     *
     * This lets consumers escape the arguments chain and continue with
     * meta-level methods after declaring arguments:
     *
     *     $meta
     *         ->args()
     *             ->integer('ttl')->default(3600)
     *             ->string('reason')->default('')
     *         ->extend('millicache:icon', 'clock');  // forwarded via parent
     *
     * The ArgumentsBuilder's own __call() further forwards to the parent
     * ActionMeta (or ConditionMeta). So the delegation chain is:
     *
     *     ArgumentSchema → ArgumentsBuilder → ActionMeta
     *
     * @since 1.2.0
     *
     * @param string            $method The method name.
     * @param array<int, mixed> $args   The method arguments.
     * @return mixed
     * @throws \BadMethodCallException If the method exists nowhere in the chain.
     */
    public function __call(string $method, array $args)
    {
        // Delegate to the parent builder; its own __call() handles further
        // forwarding to the metadata object (ActionMeta / ConditionMeta).
        return $this->parent->$method(...$args);
    }

    // -----------------------------------------------------------------
    // Introspection getters
    // -----------------------------------------------------------------

    /**
     * Get the argument key.
     *
     * @since 1.2.0
     *
     * @return int|string
     */
    public function get_key()
    {
        return $this->key;
    }

    /**
     * Get the engine-level type.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public function get_type(): string
    {
        return $this->type;
    }

    /**
     * Get the consumer-defined format hint.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public function get_format(): string
    {
        return $this->format;
    }

    /**
     * Get the label.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public function get_label(): string
    {
        return $this->label;
    }

    /**
     * Get the description.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public function get_description(): string
    {
        return $this->description;
    }

    /**
     * Get the default value.
     *
     * Returns null if no default was set OR if the default was explicitly
     * set to null. Use has_default() to distinguish.
     *
     * @since 1.2.0
     *
     * @return mixed
     */
    public function get_default()
    {
        return $this->default;
    }

    /**
     * Whether a default value was explicitly set.
     *
     * @since 1.2.0
     *
     * @return bool
     */
    public function has_default(): bool
    {
        return $this->has_default;
    }

    /**
     * Whether the argument is required.
     *
     * @since 1.2.0
     *
     * @return bool
     */
    public function is_required(): bool
    {
        return $this->required;
    }

    /**
     * Get the minimum bound.
     *
     * @since 1.2.0
     *
     * @return int|float|null
     */
    public function get_min()
    {
        return $this->min;
    }

    /**
     * Get the maximum bound.
     *
     * @since 1.2.0
     *
     * @return int|float|null
     */
    public function get_max()
    {
        return $this->max;
    }

    /**
     * Get the normalized options (choice/choices types).
     *
     * @since 1.2.0
     *
     * @return array<int, array{value: mixed, label: string}>
     */
    public function get_options(): array
    {
        return $this->options;
    }

    /**
     * Serialize to a REST/JSON-friendly array.
     *
     * Wire format (stable):
     *   [
     *     'key'         => int|string,
     *     'type'        => string,
     *     'format'      => string,
     *     'label'       => string,
     *     'description' => string,
     *     'default'     => mixed,
     *     'has_default' => bool,
     *     'required'    => bool,
     *     'min'         => int|null,
     *     'max'         => int|null,
     *     'options'     => array<int, array{value: mixed, label: string}>,
     *   ]
     *
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return array(
            'key'         => $this->key,
            'type'        => $this->type,
            'format'      => $this->format,
            'label'       => $this->label,
            'description' => $this->description,
            'default'     => $this->default,
            'has_default' => $this->has_default,
            'required'    => $this->required,
            'min'         => $this->min,
            'max'         => $this->max,
            'options'     => $this->options,
        );
    }

    // -----------------------------------------------------------------
    // Consumer utilities: validate() and sanitize()
    //
    // MilliRules' RuleEngine does NOT call these. They are opt-in
    // utilities for consumers (validators, UIs, CLIs) to share a single
    // source of truth for type coercion and validation rules.
    // -----------------------------------------------------------------

    /**
     * Validate a value against this schema.
     *
     * Returns null on success, or a plain English error message on failure.
     * MilliRules does not ship translated error messages — consumers wrap
     * the return value in their own translation layer if needed.
     *
     * @since 1.2.0
     *
     * @param mixed $value The value to validate.
     * @return string|null Null if valid, error message if invalid.
     */
    public function validate($value): ?string
    {
        // Required check comes first.
        if ($this->required && ( null === $value || '' === $value || array() === $value )) {
            return sprintf("Argument '%s' is required", (string) $this->key);
        }

        // Null on a non-required field is always valid.
        if (null === $value) {
            return null;
        }

        switch ($this->type) {
            case self::TYPE_STRING:
                if (! is_scalar($value)) {
                    return sprintf("Argument '%s' must be a string", (string) $this->key);
                }
                $length = strlen((string) $value);
                if (null !== $this->min && $length < $this->min) {
                    return sprintf("Argument '%s' must be at least %s characters", (string) $this->key, (string) $this->min);
                }
                if (null !== $this->max && $length > $this->max) {
                    return sprintf("Argument '%s' must be at most %s characters", (string) $this->key, (string) $this->max);
                }
                return null;

            case self::TYPE_INTEGER:
                if (! self::is_int_coercible($value)) {
                    return sprintf("Argument '%s' must be an integer", (string) $this->key);
                }
                $int = (int) $value;
                if (null !== $this->min && $int < $this->min) {
                    return sprintf("Argument '%s' must be at least %s", (string) $this->key, (string) $this->min);
                }
                if (null !== $this->max && $int > $this->max) {
                    return sprintf("Argument '%s' must be at most %s", (string) $this->key, (string) $this->max);
                }
                return null;

            case self::TYPE_NUMBER:
                if (! is_numeric($value)) {
                    return sprintf("Argument '%s' must be a number", (string) $this->key);
                }
                $num = (float) $value;
                if (null !== $this->min && $num < (float) $this->min) {
                    return sprintf("Argument '%s' must be at least %s", (string) $this->key, (string) $this->min);
                }
                if (null !== $this->max && $num > (float) $this->max) {
                    return sprintf("Argument '%s' must be at most %s", (string) $this->key, (string) $this->max);
                }
                return null;

            case self::TYPE_BOOLEAN:
                if (! self::is_bool_coercible($value)) {
                    return sprintf("Argument '%s' must be a boolean", (string) $this->key);
                }
                return null;

            case self::TYPE_CHOICE:
                if (! $this->is_in_options($value)) {
                    return sprintf("Argument '%s' must be one of the allowed options", (string) $this->key);
                }
                return null;

            case self::TYPE_CHOICES:
                if (! is_array($value)) {
                    return sprintf("Argument '%s' must be an array", (string) $this->key);
                }
                foreach ($value as $item) {
                    if (! $this->is_in_options($item)) {
                        return sprintf("Argument '%s' contains an invalid option", (string) $this->key);
                    }
                }
                return null;
        }

        return null;
    }

    /**
     * Coerce a raw value to the schema's declared type.
     *
     * Examples:
     *   integer schema + "3600" → 3600
     *   boolean schema + "yes" → true
     *   choices schema + ["a", "invalid", "b"] → ["a", "b"] (filters to valid)
     *
     * Null values are replaced with the default if set, otherwise the
     * type's zero value.
     *
     * @since 1.2.0
     *
     * @param mixed $value The raw value.
     * @return mixed The coerced value.
     */
    public function sanitize($value)
    {
        if (null === $value) {
            if ($this->has_default) {
                return $this->default;
            }
            return $this->type_zero_value();
        }

        switch ($this->type) {
            case self::TYPE_STRING:
                return is_scalar($value) ? (string) $value : '';

            case self::TYPE_INTEGER:
                return (int) $value;

            case self::TYPE_NUMBER:
                return (float) $value;

            case self::TYPE_BOOLEAN:
                return self::coerce_bool($value);

            case self::TYPE_CHOICE:
                if ($this->is_in_options($value)) {
                    return $value;
                }
                if ($this->has_default) {
                    return $this->default;
                }
                return $this->type_zero_value();

            case self::TYPE_CHOICES:
                $array = is_array($value) ? $value : array($value);
                return array_values(array_filter(
                    $array,
                    fn($item) => $this->is_in_options($item)
                ));
        }

        return $value;
    }

    // -----------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------

    /**
     * Throw if the current type does not support min/max bounds.
     *
     * @since 1.2.0
     *
     * @param string $caller The calling method name (for error message).
     * @return void
     * @throws \InvalidArgumentException If the type does not support bounds.
     */
    private function assert_supports_bounds(string $caller): void
    {
        $supported = array(self::TYPE_STRING, self::TYPE_INTEGER, self::TYPE_NUMBER);
        if (! in_array($this->type, $supported, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "ArgumentSchema::%s() is only valid on string, integer, or number types (got '%s')",
                    $caller,
                    $this->type
                )
            );
        }
    }

    /**
     * Throw if the current type does not support options().
     *
     * @since 1.2.0
     *
     * @return void
     * @throws \InvalidArgumentException If the type does not support options.
     */
    private function assert_supports_options(): void
    {
        $supported = array(self::TYPE_CHOICE, self::TYPE_CHOICES);
        if (! in_array($this->type, $supported, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "ArgumentSchema::options() is only valid on choice or choices types (got '%s')",
                    $this->type
                )
            );
        }
    }

    /**
     * Normalize an options array to the structured form.
     *
     * @since 1.2.0
     *
     * @param array<int, mixed> $options The input options.
     * @return array<int, array{value: mixed, label: string}>
     */
    private static function normalize_options(array $options): array
    {
        $normalized = array();
        foreach ($options as $entry) {
            if (is_array($entry) && array_key_exists('value', $entry)) {
                $value = $entry['value'];
                $label = isset($entry['label']) && is_string($entry['label'])
                    ? $entry['label']
                    : (string) $value;
                $normalized[] = array('value' => $value, 'label' => $label);
                continue;
            }
            $normalized[] = array(
                'value' => $entry,
                'label' => is_scalar($entry) ? (string) $entry : '',
            );
        }
        return $normalized;
    }

    /**
     * Check whether a value is in the normalized options list.
     *
     * @since 1.2.0
     *
     * @param mixed $value The value to check.
     * @return bool
     */
    private function is_in_options($value): bool
    {
        foreach ($this->options as $option) {
            if ($option['value'] === $value) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check whether a value can be coerced to an integer without loss.
     *
     * @since 1.2.0
     *
     * @param mixed $value
     * @return bool
     */
    private static function is_int_coercible($value): bool
    {
        if (is_int($value)) {
            return true;
        }
        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return true;
        }
        if (is_float($value) && $value === (float) (int) $value) {
            return true;
        }
        return false;
    }

    /**
     * Check whether a value can be coerced to a boolean.
     *
     * @since 1.2.0
     *
     * @param mixed $value
     * @return bool
     */
    private static function is_bool_coercible($value): bool
    {
        if (is_bool($value)) {
            return true;
        }
        if (is_int($value) && ( 0 === $value || 1 === $value )) {
            return true;
        }
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            return in_array($lower, array('true', 'false', 'yes', 'no', '1', '0', ''), true);
        }
        return false;
    }

    /**
     * Coerce a value to a boolean using the same rules as ArgumentValue.
     *
     * @since 1.2.0
     *
     * @param mixed $value
     * @return bool
     */
    private static function coerce_bool($value): bool
    {
        if (null === $value) {
            return false;
        }
        if (is_string($value)) {
            $lower = strtolower(trim($value));
            if (in_array($lower, array('true', 'yes', '1'), true)) {
                return true;
            }
            if (in_array($lower, array('false', 'no', '0', ''), true)) {
                return false;
            }
        }
        return (bool) $value;
    }

    /**
     * Get the zero value for the current type.
     *
     * @since 1.2.0
     *
     * @return mixed
     */
    private function type_zero_value()
    {
        switch ($this->type) {
            case self::TYPE_STRING:
                return '';
            case self::TYPE_INTEGER:
                return 0;
            case self::TYPE_NUMBER:
                return 0.0;
            case self::TYPE_BOOLEAN:
                return false;
            case self::TYPE_CHOICE:
                return isset($this->options[0]) ? $this->options[0]['value'] : null;
            case self::TYPE_CHOICES:
                return array();
        }
        return null;
    }
}
