<?php

/**
 * Base Action
 *
 * Abstract base class for all action implementations.
 *
 * @package MilliRules
 * @author  Philipp Wellmer
 * @since   0.1.0
 */

namespace MilliRules\Actions;

use MilliRules\ArgumentValue;
use MilliRules\Context;
use MilliRules\PlaceholderResolver;

/**
 * Class BaseAction
 *
 * Provides common functionality for all actions including placeholder resolution.
 *
 * @since 0.1.0
 */
abstract class BaseAction implements ActionInterface
{
    /**
     * Action type identifier.
     *
     * @since 0.1.0
     * @var string
     */
    protected string $type;

    /**
     * Action arguments (both positional and named).
     *
     * Contains all arguments from dynamic method calls (numeric keys)
     * and custom() calls (string keys), excluding 'type'.
     *
     * @since 0.1.0
     * @var array<int|string, mixed>
     */
    protected array $args;

    /**
     * Placeholder resolver instance.
     *
     * @since 0.1.0
     * @var PlaceholderResolver
     */
    protected PlaceholderResolver $resolver;

    /**
     * Constructor.
     *
     * @since 0.1.0
     *
     * @param array<int|string, mixed> $config  The action configuration.
     * @param Context     $context The execution context.
     */
    public function __construct(array $config, Context $context)
    {
        $this->type = $config['type'] ?? '';

        // Extract arguments: all config keys except 'type'.
        // Both dynamic methods and custom() calls now use the same structure.
        $this->args = array_filter(
            $config,
            fn($key) => $key !== 'type',
            ARRAY_FILTER_USE_KEY
        );

        $this->resolver = new PlaceholderResolver($context);
    }

    /**
     * Resolve placeholders in a value.
     *
     * @since 0.1.0
     *
     * @param string $value The value to resolve.
     * @return string The resolved value.
     */
    protected function resolve_value(string $value): string
    {
        return $this->resolver->resolve($value);
    }

    /**
     * Get an argument value with fluent type conversion.
     *
     * Provides a fluent API for accessing action arguments with automatic
     * placeholder resolution and type-safe conversion.
     *
     * Supports both positional and named argument access:
     * - Positional: get_arg(0), get_arg(1) — used by action execute() methods
     * - Named: get_arg('to'), get_arg('flag') — used when accessing by schema key
     *
     * When a positional key (int) is not found, falls back to the Nth named
     * string key. This allows action execute() methods using get_arg(0) to
     * work regardless of whether the config came from the builder (positional
     * keys) or from stored data like REST/config files (named keys).
     *
     * Examples:
     *   $this->get_arg(0, 'default')->string()
     *   $this->get_arg('to', 'admin@example.com')->string()
     *   $this->get_arg('enabled', true)->bool()
     *   $this->get_arg('count', 0)->int()
     *
     * @since 0.4.0
     *
     * @param int|string $key     The argument key (positional or named).
     * @param mixed      $default The default value if key doesn't exist.
     * @return ArgumentValue Fluent wrapper for type conversion.
     */
    protected function get_arg($key, $default = null): ArgumentValue
    {
        $value = $this->args[ $key ] ?? null;

        // Positional fallback for named args.
        //
        // Builder-created rules store args with int keys: [0 => 'my-flag']
        // Data-stored rules use named keys from ArgumentSchema: ['flag' => 'my-flag']
        //
        // When an int key is missing, map it to the Nth non-internal string
        // key. Internal keys (prefixed with '_', e.g. '_locked') are skipped.
        if (null === $value && is_int($key) && ! array_key_exists($key, $this->args)) {
            $i = 0;
            foreach ($this->args as $k => $v) {
                if (! is_string($k) || ('' !== $k && '_' === $k[0])) {
                    continue;
                }
                if ($i === $key) {
                    $value = $v;
                    break;
                }
                $i++;
            }
        }

        return new ArgumentValue($value, $default, $this->resolver);
    }

    /**
     * Get the lock scope for this action type.
     *
     * **Engine-relevant, runtime-safe.** Called by the engine during rule
     * execution, which may happen during early bootstrap before the
     * application framework has fully initialized. Implementations MUST
     * NOT use framework-specific functions (e.g., translation functions,
     * hooks, or database calls) — return a plain string identifier only.
     *
     * Override in subclasses that participate in scoped locking. Paired
     * actions (e.g., add_flag + remove_flag) share a scope so that locking
     * one specific value prevents the other action from modifying it.
     *
     * Override example:
     *   public static function get_scope(): string
     *   {
     *       return 'flag';
     *   }
     *
     * Default: '' (unscoped — type-level locking).
     *
     * @since 1.1.0
     *
     * @return string The scope identifier, or '' for unscoped actions.
     */
    public static function get_scope(): string
    {
        return '';
    }

    /**
     * Set consumer-facing metadata for this action type.
     *
     * **Only required by consumer plugins** that want their actions to
     * appear in UIs (rule builders, REST schema endpoints, docs generators).
     * Actions that do NOT override this method have no consumer-visible
     * metadata and will typically be hidden from UIs — this is by design,
     * making UI visibility opt-in.
     *
     * Called only when consumers request full metadata via
     * Rules::get_action_meta() — never during engine execution. Safe to
     * use framework-specific functions (translation, etc.) because
     * consumers always run after the framework has fully initialized.
     *
     * Override example:
     *   public static function set_meta(ActionMeta $meta): void
     *   {
     *       $meta
     *           ->label('Add Flag')
     *           ->description('Add a flag to the request.')
     *           ->categories('flags')
     *           ->args()
     *               ->string(0)->label('Flag')->required();
     *   }
     *
     * @since 1.1.0
     *
     * @param ActionMeta $meta The metadata object to configure.
     * @return void
     */
    public static function set_meta(ActionMeta $meta): void
    {
        // Default: no metadata. Actions without metadata are hidden from UIs.
    }
}
