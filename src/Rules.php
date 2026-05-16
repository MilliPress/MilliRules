<?php

/**
 * Rules API
 *
 * Main API class with builder pattern and rule management.
 * Provides a fluent interface for creating and registering rules.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer <hello@millicache.com>
 * @since 0.1.0
 */

namespace MilliRules;

use MilliRules\Logger;
use MilliRules\Actions\ActionMeta;
use MilliRules\Conditions\ConditionMeta;
use MilliRules\Builders\ConditionBuilder;
use MilliRules\Builders\ActionBuilder;
use MilliRules\Builders\NormalizesMethodNames;
use MilliRules\Packages\PackageManager;

/**
 * Class Rules
 *
 * Main public API for MilliRules.
 *
 * Provides a fluent interface for creating and managing rules, plus static methods
 * for all registration operations. This is the single entry point for the MilliRules API.
 *
 * Registration Methods:
 * - Rules::register_condition()    - Register custom condition callbacks
 * - Rules::register_action()       - Register custom action callbacks
 * - Rules::register_namespace()    - Register namespaces for condition/action resolution
 * - Rules::register_placeholder()  - Register custom placeholder resolvers
 *
 * Utility Methods:
 * - Rules::compare_values()        - Compare values using operators (=, !=, >, LIKE, etc.)
 *
 * Builder Methods:
 * - Rules::create()                - Create a new rule builder instance
 *
 * Features:
 * - Fluent API for building rules
 * - Auto-detection of required packages
 * - Auto-detection of rule types (php vs wp)
 * - Intelligent hook registration
 * - Immediate action execution when rules match
 *
 * Execution Model:
 * - Rules execute in priority order (set via ->order())
 * - Actions execute immediately when their rule matches
 * - WordPress hooks (set via ->on()) control when rules execute
 *
 * @package     MilliRules
 * @author      Philipp Wellmer <hello@millicache.com>
 * @since 0.1.0
 */
class Rules
{
    use NormalizesMethodNames;

    /**
     * Match types supported by the rule engine.
     *
     * @since 1.1.0
     * @var array<int, string>
     */
    public const MATCH_TYPES = array( 'all', 'any', 'none' );

    /**
     * Custom condition callbacks registry.
     *
     * @since 0.1.0
     * @var array<string, callable>
     */
    private static array $custom_conditions = array();

    /**
     * Custom action callbacks registry.
     *
     * @since 0.1.0
     * @var array<string, callable>
     */
    private static array $custom_actions = array();

    /**
     * Action metadata registry (callback-based actions).
     *
     * Stores ActionMeta instances created by register_action() so fluent
     * chaining (->scope(), ->label(), etc.) mutates the stored instance.
     *
     * @since 1.1.0
     * @var array<string, ActionMeta>
     */
    private static array $action_metas = array();

    /**
     * Resolved metadata cache (both callback- and class-based).
     *
     * Keyed by action type. Uses null sentinel for "resolution attempted,
     * nothing found" to avoid re-resolving on every lookup. Cleared on
     * re-registration and in test teardown.
     *
     * @since 1.1.0
     * @var array<string, ?ActionMeta>
     */
    private static array $meta_cache = array();

    /**
     * Resolved scope cache for the engine hot path.
     *
     * Separate from $meta_cache because scope resolution NEVER calls
     * set_meta() — it only reads from $action_metas (for callback-based)
     * or from $class::get_scope() (for class-based). This keeps the
     * engine's build_lock_key() path safe to call during early bootstrap,
     * before framework-specific functions are available.
     *
     * @since 1.1.0
     * @var array<string, string>
     */
    private static array $scope_cache = array();

    /**
     * Condition metadata registry (callback-based conditions).
     *
     * @since 1.1.0
     * @var array<string, ConditionMeta>
     */
    private static array $condition_metas = array();

    /**
     * Resolved condition metadata cache (both callback- and class-based).
     *
     * @since 1.1.0
     * @var array<string, ?ConditionMeta>
     */
    private static array $condition_meta_cache = array();

    /**
     * Cached result of get_all_action_metas().
     *
     * Null means not yet computed. Cleared when new actions are registered.
     *
     * @since 1.1.0
     * @var array<string, ActionMeta>|null
     */
    private static ?array $all_action_metas_cache = null;

    /**
     * Cached result of get_all_condition_metas().
     *
     * Null means not yet computed. Cleared when new conditions are registered.
     *
     * @since 1.1.0
     * @var array<string, ConditionMeta>|null
     */
    private static ?array $all_condition_metas_cache = null;

    /**
     * Hard-coded namespace to package mapping.
     *
     * Used as fallback when PackageManager is not available or doesn't have mapping.
     * Maps namespace prefixes to package names for dependency detection.
     *
     * @since 0.1.0
     * @var array<string, string>
     */
    private static array $namespace_package_map = array(
        'MilliRules\\Packages\\PHP\\'        => 'PHP',
        'MilliRules\\Packages\\WordPress\\'  => 'WP',
        'MilliRules\\Conditions\\'           => 'Core',
        'MilliRules\\Actions\\'              => 'Core',
    );

    /**
     * Rule configuration being built.
     *
     * @since 0.1.0
     * @var array<string, mixed>
     */
    private array $rule = array();

    /**
     * Explicitly set type from create() method.
     *
     * Stored to bypass auto-detection when user provides explicit type.
     *
     * @since 0.1.0
     * @var string|null
     */
    private ?string $explicit_type = null;

    /**
     * The hook on which this rule should execute.
     *
     * @since 0.1.0
     * @var string
     */
    private string $hook = 'wp';

    /**
     * The priority for the hook.
     *
     * @since 0.1.0
     * @var int
     */
    private int $hook_priority = 10;

    /**
     * Whether condition groups are being accumulated via and().
     *
     * @since 1.1.0
     * @var bool
     */
    private bool $has_groups = false;

    /**
     * Create a new rule builder.
     *
     * Rule Order System:
     *
     * Lower number = Executes FIRST (0, 1, 2, 3...)
     * Higher number = Executes LAST (...997, 998, 999)
     *
     * When multiple rules modify the same value (e.g., TTL),
     * the LAST executed rule (highest order) wins.
     *
     * Order Ranges:
     * - 0:      Core default rules (overridable)
     * - 10:     Normal user rules (DEFAULT)
     * - 20-50:  Later user rules
     * - 100+:   Final overrides
     *
     * Hook Priority (separate concept via ->on()):
     * - Controls WHEN in WordPress lifecycle
     * - Lower number = Earlier execution
     * - Default: 10
     *
     * Type Auto-Detection:
     * - If $type is null (default), rule type will be auto-detected during register()
     * - Auto-detection based on used conditions/actions and hooks
     * - 'php' for early execution (before WordPress), 'wp' for WordPress context
     *
     * @since 0.1.0
     *
     * @param string      $id   The rule ID.
     * @param string|null $type Optional. The rule type: 'php' or 'wp'. If null, auto-detected. Default: null.
     * @return self The rule builder instance.
     */
    public static function create(string $id, ?string $type = null): self
    {
        $instance = new self();
        $instance->rule = array(
            'id'         => $id,
            'title'      => '',
            'order'      => 10,
            'enabled'    => true,
            'match_type' => 'all',
            'conditions' => array(),
            'actions'    => array(),
            '_metadata'  => array(),
        );

        // Store explicit type if provided (bypasses auto-detection).
        if (null !== $type) {
            $instance->explicit_type = $type;
        }

        return $instance;
    }

    // ===========================
    // Callback Registration API
    // ===========================

    /**
     * Register a custom condition callback.
     *
     * Returns a ConditionMeta instance for fluent declaration of metadata
     * (label, description, categories, operators, arguments).
     *
     * @since 0.1.0
     * @since 1.1.0 Returns ConditionMeta for fluent metadata declaration.
     *
     * @param string   $type     The condition type identifier.
     * @param callable(array<string, mixed>, Context): bool $callback The callback function that receives args array and Context.
     *                                                  Signature: function(array $args, Context $context): bool
     * @return ConditionMeta Fluent metadata declaration for the registered condition.
     * @throws \InvalidArgumentException If callback is not callable.
     */
    public static function register_condition(string $type, callable $callback): ConditionMeta
    {
        if (! is_callable($callback)) {
            throw new \InvalidArgumentException("Callback for condition type '{$type}' is not callable"); // phpcs:ignore WordPress.Security.EscapeOutput
        }

        self::$custom_conditions[ $type ] = $callback;

        $meta = new ConditionMeta($type);
        self::$condition_metas[ $type ] = $meta;

        unset(self::$condition_meta_cache[ $type ]);
        self::$all_condition_metas_cache = null;

        return $meta;
    }

    /**
     * Register metadata for a condition type without providing a callback.
     *
     * Use this to attach metadata (label, operators, categories, etc.) to
     * class-based conditions that are resolved via namespace convention and
     * don't need a callback registration. Useful for batch-registering
     * metadata for dynamic condition classes (e.g., is_* / has_* conditionals).
     *
     * @since 1.1.0
     *
     * @param string $type The condition type identifier.
     * @return ConditionMeta Fluent metadata declaration.
     */
    public static function register_condition_meta(string $type): ConditionMeta
    {
        $meta = new ConditionMeta($type);
        self::$condition_metas[ $type ] = $meta;

        unset(self::$condition_meta_cache[ $type ]);
        self::$all_condition_metas_cache = null;

        return $meta;
    }

    /**
     * Register metadata for an action type without providing a callback.
     *
     * Use this to attach metadata (label, categories, arguments, etc.) to
     * class-based actions that are resolved via namespace convention and
     * don't need a callback registration.
     *
     * @since 1.1.0
     *
     * @param string $type The action type identifier.
     * @return ActionMeta Fluent metadata declaration.
     */
    public static function register_action_meta(string $type): ActionMeta
    {
        $meta = new ActionMeta($type);
        self::$action_metas[ $type ] = $meta;

        unset(self::$meta_cache[ $type ]);
        unset(self::$scope_cache[ $type ]);
        self::$all_action_metas_cache = null;

        return $meta;
    }

    /**
     * Register a custom action callback.
     *
     * Returns an ActionMeta instance for fluent declaration of metadata
     * (scope, label, description, category).
     *
     * @since 0.1.0
     * @since 1.1.0 Returns ActionMeta for fluent metadata declaration.
     *
     * @param string   $type     The action type identifier.
     * @param callable(array<string, mixed>, Context): void $callback The callback function that receives args array and Context.
     *                                                  Signature: function(array $args, Context $context): void
     * @return ActionMeta Fluent metadata declaration for the registered action.
     * @throws \InvalidArgumentException If callback is not callable.
     */
    public static function register_action(string $type, callable $callback): ActionMeta
    {
        if (! is_callable($callback)) {
            throw new \InvalidArgumentException("Callback for action type '{$type}' is not callable"); // phpcs:ignore WordPress.Security.EscapeOutput
        }

        self::$custom_actions[ $type ] = $callback;

        $meta = new ActionMeta($type);
        self::$action_metas[ $type ] = $meta;

        // Invalidate caches so subsequent lookups see the new instance.
        unset(self::$meta_cache[ $type ]);
        unset(self::$scope_cache[ $type ]);
        self::$all_action_metas_cache = null;

        return $meta;
    }

    /**
     * Register a namespace for condition/action resolution.
     *
     * Allows packages and plugins to register custom namespaces for the RuleEngine
     * to search when resolving condition and action types.
     *
     * When a package name is provided, the namespace is also mapped to that package
     * for automatic package detection. This ensures rules using conditions/actions
     * from this namespace are correctly associated with the specified package.
     *
     * @since 0.1.0
     * @since 0.1.0
     *
     * @param string      $type      The type: 'Conditions' or 'Actions'.
     * @param string      $namespace The namespace to search (e.g., 'MyPlugin\\Conditions').
     * @param string|null $package   Optional package name this namespace belongs to (e.g., 'WP', 'PHP').
     * @return void
     */
    public static function register_namespace(string $type, string $namespace, ?string $package = null): void
    {
        RuleEngine::register_namespace($type, $namespace);

        // Register package mapping for this namespace if specified.
        if (null !== $package) {
            // Ensure namespace ends with backslash for consistent matching.
            $namespace_key = rtrim($namespace, '\\') . '\\';
            self::$namespace_package_map[ $namespace_key ] = $package;
        }
    }

    /**
     * Register a custom placeholder resolver.
     *
     * Allows registering custom placeholder resolvers for use in rule values.
     * Placeholders are resolved at execution time and can reference dynamic values
     * from the execution context.
     *
     * Example:
     * Rules::register_placeholder('custom', function($context, $parts) {
     *     return $context['custom'][$parts[0]] ?? '';
     * });
     *
     * Usage: ['value' => '{custom:key}']
     *
     * @since 0.1.0
     *
     * @param string   $placeholder The placeholder name (e.g., 'custom' for {custom:value}).
     * @param callable(Context, array<int, string>): mixed $resolver The resolver callback that receives Context and parts array.
     *                                                   Signature: function(Context $context, array $parts): mixed
     * @return void
     */
    public static function register_placeholder(string $placeholder, callable $resolver): void
    {
        PlaceholderResolver::register_placeholder($placeholder, $resolver);
    }

    /**
     * Unregister a rule by its ID.
     *
     * Removes a rule from all packages. Use this to completely disable a rule
     * that was previously registered.
     *
     * Example:
     * Rules::unregister('my_rule');
     *
     * @since 0.6.0
     *
     * @param string $rule_id The ID of the rule to unregister.
     * @return bool True if a rule was found and removed, false otherwise.
     */
    public static function unregister(string $rule_id): bool
    {
        return PackageManager::unregister_rule($rule_id);
    }

    /**
     * Check if a custom condition is registered.
     *
     * @since 0.1.0
     *
     * @param string $type The condition type.
     * @return bool True if registered, false otherwise.
     */
    public static function has_custom_condition(string $type): bool
    {
        return isset(self::$custom_conditions[ $type ]);
    }

    /**
     * Check if a custom action is registered.
     *
     * @since 0.1.0
     *
     * @param string $type The action type.
     * @return bool True if registered, false otherwise.
     */
    public static function has_custom_action(string $type): bool
    {
        return isset(self::$custom_actions[ $type ]);
    }

    /**
     * Get a registered custom condition callback.
     *
     * @since 0.1.0
     *
     * @param string $type The condition type.
     * @return callable|null The callback or null if not found.
     */
    public static function get_custom_condition(string $type): ?callable
    {
        return self::$custom_conditions[ $type ] ?? null;
    }

    /**
     * Get a registered custom action callback.
     *
     * @since 0.1.0
     *
     * @param string $type The action type.
     * @return callable|null The callback or null if not found.
     */
    public static function get_custom_action(string $type): ?callable
    {
        return self::$custom_actions[ $type ] ?? null;
    }

    /**
     * Get all registered custom condition callbacks.
     *
     * @since 0.7.1
     *
     * @return array<string, callable> Array of type => callback pairs.
     */
    public static function get_custom_conditions(): array
    {
        return self::$custom_conditions;
    }

    /**
     * Get all registered custom action callbacks.
     *
     * @since 0.7.1
     *
     * @return array<string, callable> Array of type => callback pairs.
     */
    public static function get_custom_actions(): array
    {
        return self::$custom_actions;
    }

    /**
     * Get the lock scope for an action type (engine hot path).
     *
     * Fast path that NEVER calls set_meta(). This is critical because the
     * engine may run during early bootstrap, before framework-specific
     * functions (like translation) are available.
     *
     * Resolves scope from either:
     * 1. Callback-based: reads from the meta stored at registration time
     *    (set via Rules::register_action()->scope()). No set_meta() call.
     * 2. Class-based: calls $class::get_scope() static method directly.
     *    No set_meta() call.
     *
     * Results are cached per type. Returns '' for unknown action types
     * or unscoped actions.
     *
     * @since 1.1.0
     *
     * @param string $type The action type.
     * @return string The scope identifier, or '' if unscoped / unknown.
     */
    public static function get_action_scope(string $type): string
    {
        if (array_key_exists($type, self::$scope_cache)) {
            return self::$scope_cache[ $type ];
        }

        // Callback-based: scope lives on the meta registered via
        // register_action()->scope(). Read it directly — no set_meta() call.
        if (isset(self::$action_metas[ $type ])) {
            return self::$scope_cache[ $type ] = self::$action_metas[ $type ]->get_scope();
        }

        // Class-based: call the static get_scope() method directly.
        // Designed to be safe during early bootstrap — no framework calls.
        $class_name = RuleEngine::type_to_class_name($type, 'Actions');
        if (class_exists($class_name) && is_subclass_of($class_name, Actions\BaseAction::class)) {
            /** @var class-string<Actions\BaseAction> $class_name */
            return self::$scope_cache[ $type ] = $class_name::get_scope();
        }

        return self::$scope_cache[ $type ] = '';
    }

    /**
     * Get the full metadata for an action type.
     *
     * Resolves metadata from either:
     * 1. Callback-based registry (populated by register_action())
     * 2. Class-based static set_meta() method on BaseAction subclasses
     *
     * **May require framework functions**: for class-based actions, this
     * calls set_meta(), which may use framework-specific functions (like
     * translation). Do NOT call this during early bootstrap before the
     * framework is initialized. Use get_action_scope() if you only need
     * the scope.
     *
     * Results are cached per type. Returns null if no metadata is found
     * for the given type.
     *
     * @since 1.1.0
     *
     * @param string $type The action type.
     * @return ActionMeta|null The metadata, or null if not found.
     */
    public static function get_action_meta(string $type): ?ActionMeta
    {
        if (array_key_exists($type, self::$meta_cache)) {
            return self::$meta_cache[ $type ];
        }

        // Callback-based: stored by register_action().
        if (isset(self::$action_metas[ $type ])) {
            self::$meta_cache[ $type ] = self::$action_metas[ $type ];
            return self::$meta_cache[ $type ];
        }

        // Class-based: construct meta with the correct type and let the
        // subclass configure it via set_meta(). The engine owns the type
        // string so subclasses can't get it wrong. Scope is pre-populated
        // from the static get_scope() method.
        $class_name = RuleEngine::type_to_class_name($type, 'Actions');
        if (class_exists($class_name) && is_subclass_of($class_name, Actions\BaseAction::class)) {
            /** @var class-string<Actions\BaseAction> $class_name */
            $meta = new ActionMeta($type);
            $meta->scope($class_name::get_scope());
            $class_name::set_meta($meta);
            self::$meta_cache[ $type ] = $meta;
            return $meta;
        }

        self::$meta_cache[ $type ] = null;
        return null;
    }

    /**
     * Get the full metadata for a condition type.
     *
     * Resolves metadata from either:
     * 1. Callback-based registry (populated by register_condition())
     * 2. Class-based static set_meta() method on BaseCondition subclasses
     *
     * For class-based conditions, the argument_mapping from
     * BaseCondition::get_argument_mapping() is automatically included.
     *
     * Results are cached per type. Returns null if no metadata is found.
     *
     * @since 1.1.0
     *
     * @param string $type The condition type.
     * @return ConditionMeta|null The metadata, or null if not found.
     */
    public static function get_condition_meta(string $type): ?ConditionMeta
    {
        if (array_key_exists($type, self::$condition_meta_cache)) {
            return self::$condition_meta_cache[ $type ];
        }

        // Callback-based: stored by register_condition().
        if (isset(self::$condition_metas[ $type ])) {
            self::$condition_meta_cache[ $type ] = self::$condition_metas[ $type ];
            return self::$condition_meta_cache[ $type ];
        }

        // Class-based: construct meta and let the subclass configure it.
        $class_name = RuleEngine::type_to_class_name($type, 'Conditions');
        if (class_exists($class_name) && is_subclass_of($class_name, Conditions\BaseCondition::class)) {
            /** @var class-string<Conditions\BaseCondition> $class_name */
            $meta = new ConditionMeta($type);
            $meta->argument_mapping($class_name::get_argument_mapping());
            $class_name::set_meta($meta);
            self::$condition_meta_cache[ $type ] = $meta;
            return $meta;
        }

        self::$condition_meta_cache[ $type ] = null;
        return null;
    }

    /**
     * Get metadata for all available action types.
     *
     * Discovers all action types from both class-based (via namespace scanning)
     * and callback-based registrations, and resolves their metadata.
     *
     * Results are cached after first call. The cache is cleared when new
     * actions are registered via register_action() or register_action_meta().
     *
     * @since 1.1.0
     *
     * @return array<string, ActionMeta> Map of type string => ActionMeta.
     */
    public static function get_all_action_metas(): array
    {
        if (null !== self::$all_action_metas_cache) {
            return self::$all_action_metas_cache;
        }

        // Discover class-based types by scanning registered namespaces.
        $types = RuleEngine::scan_namespace_types('Actions');

        // Merge callback-based types (keys are type strings).
        foreach (self::$custom_actions as $type => $callback) {
            $types[ $type ] = true;
        }

        $result = array();

        foreach ($types as $type => $unused) {
            $meta = self::get_action_meta($type);
            if (null !== $meta) {
                $result[ $type ] = $meta;
            }
        }

        self::$all_action_metas_cache = $result;
        return $result;
    }

    /**
     * Get metadata for all available condition types.
     *
     * Discovers all condition types from both class-based (via namespace scanning)
     * and callback-based registrations, and resolves their metadata.
     *
     * Results are cached after first call. The cache is cleared when new
     * conditions are registered via register_condition() or register_condition_meta().
     *
     * @since 1.1.0
     *
     * @return array<string, ConditionMeta> Map of type string => ConditionMeta.
     */
    public static function get_all_condition_metas(): array
    {
        if (null !== self::$all_condition_metas_cache) {
            return self::$all_condition_metas_cache;
        }

        // Discover class-based types by scanning registered namespaces.
        $types = RuleEngine::scan_namespace_types('Conditions');

        // Merge callback-based types (keys are type strings).
        foreach (self::$custom_conditions as $type => $callback) {
            $types[ $type ] = true;
        }

        $result = array();

        foreach ($types as $type => $unused) {
            $meta = self::get_condition_meta($type);
            if (null !== $meta) {
                $result[ $type ] = $meta;
            }
        }

        self::$all_condition_metas_cache = $result;
        return $result;
    }

    /**
     * Helper method to compare values using WP_Query-style operators.
     *
     * @since 0.1.0
     *
     * @param mixed  $actual   The actual value from context.
     * @param mixed  $expected The expected value from config.
     * @param string $operator The comparison operator.
     * @return bool True if comparison matches, false otherwise.
     */
    public static function compare_values($actual, $expected, string $operator = '='): bool
    {
        return Conditions\BaseCondition::compare_values($actual, $expected, $operator);
    }

    // ===========================
    // Validation API
    // ===========================

    /**
     * Validate a rule configuration against the engine's registry.
     *
     * Checks that the rule's match_type, condition types, operators, action
     * types, and action arguments are all recognized by the engine. Returns
     * an array of plain-English error strings (empty array = valid).
     *
     * This validates engine-level concerns only. Storage-layer concerns
     * (ID format, title length, order range) are the consumer's responsibility.
     *
     * @since 1.1.0
     *
     * @param array<string, mixed> $rule The rule configuration array.
     * @return array<int, string> Error messages. Empty if valid.
     */
    public static function validate(array $rule): array
    {
        $errors = array();

        // Match type.
        $match_type = $rule['match_type'] ?? 'all';
        if (! in_array($match_type, self::MATCH_TYPES, true)) {
            $errors[] = sprintf(
                "Invalid match_type '%s'. Must be one of: %s.",
                $match_type,
                implode(', ', self::MATCH_TYPES)
            );
        }

        // Conditions.
        $conditions = $rule['conditions'] ?? array();
        if (is_array($conditions)) {
            self::validate_conditions($conditions, $errors);
        }

        // Actions.
        $actions = $rule['actions'] ?? array();
        if (is_array($actions)) {
            self::validate_actions($actions, $errors);
        }

        return $errors;
    }

    /**
     * Validate a conditions array (recursive for nested groups).
     *
     * @since 1.1.0
     *
     * @param array<int, mixed>    $conditions The conditions array.
     * @param array<int, string>   &$errors    Error collector.
     * @return void
     */
    private static function validate_conditions(array $conditions, array &$errors): void
    {
        foreach ($conditions as $i => $condition) {
            if (! is_array($condition)) {
                $errors[] = sprintf('Condition #%d must be an array.', $i + 1);
                continue;
            }

            // Condition group: has match_type + conditions, no type.
            if (isset($condition['match_type'], $condition['conditions']) && ! isset($condition['type'])) {
                if (! in_array($condition['match_type'], self::MATCH_TYPES, true)) {
                    $errors[] = sprintf(
                        "Condition group #%d has invalid match_type '%s'.",
                        $i + 1,
                        $condition['match_type']
                    );
                }
                if (is_array($condition['conditions'])) {
                    self::validate_conditions($condition['conditions'], $errors);
                }
                continue;
            }

            // Individual condition.
            $type = $condition['type'] ?? '';
            if (! is_string($type) || '' === $type) {
                $errors[] = sprintf('Condition #%d is missing a type.', $i + 1);
                continue;
            }

            $meta = self::get_condition_meta($type);
            if (null === $meta) {
                $errors[] = sprintf("Condition #%d has unknown type '%s'.", $i + 1, $type);
                continue;
            }

            // Operator validation (only if the condition declares allowed operators).
            $operator         = $condition['operator'] ?? '';
            $allowed_operators = $meta->get_operators();

            if (
                is_string($operator)
                && '' !== $operator
                && ! empty($allowed_operators)
                && ! in_array(strtoupper($operator), $allowed_operators, true)
            ) {
                $errors[] = sprintf(
                    "Condition #%d ('%s') has unsupported operator '%s'.",
                    $i + 1,
                    $type,
                    $operator
                );
            }
        }
    }

    /**
     * Validate an actions array.
     *
     * @since 1.1.0
     *
     * @param array<int, mixed>    $actions The actions array.
     * @param array<int, string>   &$errors Error collector.
     * @return void
     */
    private static function validate_actions(array $actions, array &$errors): void
    {
        foreach ($actions as $i => $action) {
            if (! is_array($action)) {
                $errors[] = sprintf('Action #%d must be an array.', $i + 1);
                continue;
            }

            $type = $action['type'] ?? '';
            if (! is_string($type) || '' === $type) {
                $errors[] = sprintf('Action #%d is missing a type.', $i + 1);
                continue;
            }

            $meta = self::get_action_meta($type);
            if (null === $meta) {
                $errors[] = sprintf("Action #%d has unknown type '%s'.", $i + 1, $type);
                continue;
            }

            // Argument validation via ArgumentSchema.
            foreach ($meta->get_arguments() as $arg_schema) {
                $key   = $arg_schema->get_key();
                $value = $action[ $key ] ?? null;
                $error = $arg_schema->validate($value);

                if (null !== $error) {
                    $errors[] = sprintf("Action #%d ('%s'): %s", $i + 1, $type, $error);
                }
            }
        }
    }

    // ===========================
    // Rule Builder API
    // ===========================

    /**
     * Set the hook on which this rule should execute.
     *
     * Note: Using on() automatically sets the rule type to 'wp' as hooks are WordPress-specific.
     * If you explicitly set type='php' and then call on(), the type will be overridden to 'wp'
     * with a warning logged.
     *
     * @since 0.1.0
     * @since 0.1.0
     *
     * @param string $hook     The WordPress hook name (default: 'wp').
     * @param int    $priority The hook priority (default: 10).
     * @return self
     */
    public function on(string $hook = 'wp', int $priority = 10): self
    {
        $this->hook = $hook;
        $this->hook_priority = $priority;
        return $this;
    }

    /**
     * Set the rule title.
     *
     * @since 0.1.0
     *
     * @param string $title The rule title.
     * @return self
     */
    public function title(string $title): self
    {
        $this->rule['title'] = $title;
        return $this;
    }

    /**
     * Set the rule order (execution sequence within a hook).
     *
     * Order determines the sequence rules execute:
     * - Lower number = Executes FIRST (0 is first)
     * - Higher number = Executes LAST (999 is last)
     *
     * When rules modify the same values (e.g., TTL), the LAST-executed rule wins.
     *
     * @since 0.1.0
     *
     * @param int $order The rule order (0-999). Default: 10.
     * @return self
     */
    public function order(int $order): self
    {
        $this->rule['order'] = $order;
        return $this;
    }

    /**
     * Set the rule-enabled state.
     *
     * @since 0.1.0
     *
     * @param bool $enabled Whether the rule is enabled.
     * @return self
     */
    public function enabled(bool $enabled = true): self
    {
        $this->rule['enabled'] = $enabled;
        return $this;
    }

    /**
     * Lock this rule to prevent overwriting or unregistering.
     *
     * Locked rules cannot be overwritten by another rule with the same ID,
     * nor can they be unregistered. This guards the entire rule — conditions,
     * actions, and metadata — from replacement.
     *
     * Use this for safety-critical core rules where both the conditions and
     * actions must be preserved (e.g., "don't cache POST requests").
     *
     * Note: This is separate from ActionBuilder::lock() which locks individual
     * action types from being executed by later rules. Rule-level lock()
     * prevents the rule definition itself from being replaced.
     *
     * @since 1.1.0
     *
     * @return self
     */
    public function lock(): self
    {
        $this->rule['_locked'] = true;
        return $this;
    }

    /**
     * Set conditions with the 'all' match type.
     *
     * @since 0.1.0
     *
     * @param array<int, array<string, mixed>>|null $conditions AArray of condition configurations (null for builder).
     * @return ($conditions is null ? ConditionBuilder : self)
     */
    public function when(?array $conditions = null)
    {
        return $this->when_all($conditions);
    }

    /**
     * Set conditions with the 'all' match type.
     *
     * @since 0.1.0
     *
     * @param array<int, array<string, mixed>>|null $conditions Array of condition configurations (null for builder).
     * @return ($conditions is null ? ConditionBuilder : self)
     */
    public function when_all(?array $conditions = null)
    {
        if (null === $conditions) {
            return new ConditionBuilder($this, 'all');
        }

        $this->rule['match_type'] = 'all';
        $this->rule['conditions'] = $conditions;
        return $this;
    }

    /**
     * Set conditions with 'any' match type.
     *
     * @since 0.1.0
     *
     * @param array<int, array<string, mixed>>|null $conditions Array of condition configurations (null for builder).
     * @return ($conditions is null ? ConditionBuilder : self)
     */
    public function when_any(?array $conditions = null)
    {
        if (null === $conditions) {
            return new ConditionBuilder($this, 'any');
        }

        $this->rule['match_type'] = 'any';
        $this->rule['conditions'] = $conditions;
        return $this;
    }

    /**
     * Set conditions with the 'none' match type.
     *
     * @since 0.1.0
     *
     * @param array<int, array<string, mixed>>|null $conditions Array of condition configurations (null for builder).
     * @return ($conditions is null ? ConditionBuilder : self)
     */
    public function when_none(?array $conditions = null)
    {
        if (null === $conditions) {
            return new ConditionBuilder($this, 'none');
        }

        $this->rule['match_type'] = 'none';
        $this->rule['conditions'] = $conditions;
        return $this;
    }

    /**
     * AND connector between condition groups.
     *
     * Finalizes the current condition group and prepares for the next one.
     * The next when_all()/when_any()/when_none() call starts a new group.
     * All groups are ANDed together during evaluation.
     *
     * @since 1.1.0
     *
     * @return self
     */
    public function and(): self
    {
        if (! $this->has_groups) {
            // First and() call: wrap existing flat conditions into a group entry.
            $existing_conditions = $this->rule['conditions'] ?? array();
            $existing_match_type = $this->rule['match_type'] ?? 'all';

            $this->rule['conditions'] = array(
                array(
                    'match_type' => $existing_match_type,
                    'conditions' => $existing_conditions,
                ),
            );
            $this->rule['match_type'] = 'all';
            $this->has_groups = true;
        }

        return $this;
    }

    /**
     * Set actions to execute.
     *
     * @since 0.1.0
     *
     * @param array<int, array<string, mixed>>|null $actions Array of action configurations (null for builder).
     * @return ($actions is null ? ActionBuilder : self)
     */
    public function then(?array $actions = null)
    {
        if (null === $actions) {
            return new ActionBuilder($this);
        }

        $this->rule['actions'] = $actions;
        return $this;
    }

    /**
     * Set conditions directly (used by Builder).
     *
     * @since 0.1.0
     *
     * @param array<int, array<string, mixed>> $conditions The condition array.
     * @param string                           $match_type The match type (all/any/none).
     * @return self
     */
    public function set_conditions(array $conditions, string $match_type = 'all'): self
    {
        if ($this->has_groups) {
            // Wrap as a group entry and append to existing conditions.
            $this->rule['conditions'][] = array(
                'match_type' => $match_type,
                'conditions' => $conditions,
            );
        } else {
            $this->rule['match_type'] = $match_type;
            $this->rule['conditions'] = $conditions;
        }

        return $this;
    }

    /**
     * Set actions directly (used by Builder).
     *
     * @since 0.1.0
     *
     * @param array<int, array<string, mixed>> $actions The action array.
     * @return self
     */
    public function set_actions(array $actions): self
    {
        $this->rule['actions'] = $actions;
        return $this;
    }

    /**
     * Handle camelCase method calls by delegating to snake_case equivalents.
     *
     * Allows fluent API methods to be called in either naming convention:
     * - snake_case: ->when_all(), ->set_conditions(), ->when_none()
     * - camelCase:  ->whenAll(),  ->setConditions(),  ->whenNone()
     *
     * @since 0.7.0
     *
     * @param string            $method The method name in camelCase.
     * @param array<int, mixed> $args   The method arguments.
     * @return mixed
     * @throws \BadMethodCallException If no matching snake_case method exists.
     */
    public function __call(string $method, array $args)
    {
        $snake_method = $this->normalize_method_name($method);

        if ($snake_method !== $method && method_exists($this, $snake_method)) {
            return call_user_func_array(array( $this, $snake_method ), $args);
        }

        throw new \BadMethodCallException("Method {$method} does not exist on " . static::class);
    }

    /**
     * Register the rule.
     *
     * Performs auto-detection of required packages and rule type,
     * then registers the rule with PackageManager.
     *
     * Auto-detection includes:
     * - Required packages based on conditions/actions used
     * - Rule type ('php' or 'wp') based on packages and hooks
     *
     * @since 0.1.0
     * @since 0.1.0
     *
     * @return bool True if registration successful, false otherwise.
     */
    public function register(): bool
    {
        $rule = $this->rule;

        // Carry instance-only state into the array form so the canonical
        // entry point sees the same configuration the fluent builder did.
        $rule['hook']     = $this->hook;
        $rule['priority'] = $this->hook_priority;

        return self::register_rule($rule, $this->explicit_type);
    }

    /**
     * Register a rule from an array configuration.
     *
     * Canonical public entry point for rule registration. Produces identical
     * registry state to the equivalent fluent chain — the fluent builder
     * delegates here. Use this when rules are loaded from settings/UI storage
     * where the rule is already an array.
     *
     * Input schema (top-level keys):
     * - id          (string, required)   Rule identifier.
     * - title       (string, optional)   Human-readable title. Default: ''.
     * - order       (int, optional)      Execution order. Default: 10.
     * - enabled     (bool, optional)     Whether the rule runs. Default: true.
     * - locked      (bool, optional)     Lock the rule definition itself.
     * - hook        (string, optional)   WordPress hook to bind to. Default: 'wp'.
     * - priority    (int, optional)      Hook priority. Default: 10.
     * - match_type  (string, optional)   'all' | 'any' | 'none'. Default: 'all'.
     * - conditions  (array, optional)    Conditions array (same shape as fluent).
     * - actions     (array, optional)    Actions array. Per-action `locked: true`
     *                                    is translated to internal `_locked`.
     *
     * The internal `_locked` form is also accepted (passthrough) on both rule
     * and action entries so the fluent builder can delegate here cleanly.
     *
     * Missing keys take the documented defaults — callers migrating from a
     * different convention (e.g. treating missing `enabled` as disabled) must
     * pass explicit values to opt out.
     *
     * @since 1.2.0
     *
     * @param array<string, mixed> $rule Rule configuration array.
     * @param string|null          $type Optional explicit type ('php' or 'wp'). Default: auto-detect.
     * @return bool True on success, false on validation failure.
     */
    public static function register_rule(array $rule, ?string $type = null): bool
    {
        $id = $rule['id'] ?? null;
        if (! is_string($id) || '' === $id) {
            Logger::error('register_rule: rule is missing a valid id');
            return false;
        }

        $normalized = array(
            'id'         => $id,
            'title'      => ( isset($rule['title']) && is_string($rule['title']) ) ? $rule['title'] : '',
            'order'      => array_key_exists('order', $rule) ? (int) $rule['order'] : 10,
            'enabled'    => array_key_exists('enabled', $rule) ? (bool) $rule['enabled'] : true,
            'match_type' => ( isset($rule['match_type']) && is_string($rule['match_type']) ) ? $rule['match_type'] : 'all',
            'conditions' => ( isset($rule['conditions']) && is_array($rule['conditions']) ) ? $rule['conditions'] : array(),
            'actions'    => ( isset($rule['actions']) && is_array($rule['actions']) ) ? self::normalize_action_locks($rule['actions']) : array(),
            '_metadata'  => array(),
        );

        // Rule-level lock — accept public 'locked' (documented) and internal
        // '_locked' (used by the fluent builder when it delegates here).
        if (! empty($rule['locked']) || ! empty($rule['_locked'])) {
            $normalized['_locked'] = true;
        }

        $hook          = ( isset($rule['hook']) && is_string($rule['hook']) ) ? $rule['hook'] : 'wp';
        $hook_priority = array_key_exists('priority', $rule) ? (int) $rule['priority'] : 10;

        if (! self::validate_rule($normalized)) {
            Logger::error('Failed to validate rule: ' . $id);
            return false;
        }

        return self::finalize_registration($normalized, $type, $hook, $hook_priority);
    }

    /**
     * Translate the public `locked` flag on action entries to internal `_locked`.
     *
     * The fluent ActionBuilder writes `_locked` directly; array-form callers use
     * the public `locked` key. Existing `_locked` values are preserved.
     *
     * @since 1.2.0
     *
     * @param array<int, mixed> $actions Raw actions array from the caller.
     * @return array<int, array<string, mixed>> Normalized actions.
     */
    private static function normalize_action_locks(array $actions): array
    {
        $normalized = array();
        foreach ($actions as $action) {
            if (! is_array($action)) {
                continue;
            }
            if (array_key_exists('locked', $action)) {
                if (! empty($action['locked'])) {
                    $action['_locked'] = true;
                }
                unset($action['locked']);
            }
            $normalized[] = $action;
        }
        return $normalized;
    }

    /**
     * Finalize registration of a normalized rule.
     *
     * Shared tail of register_rule(). Runs package detection, type resolution,
     * metadata assembly, and hands the rule to PackageManager. Assumes its
     * input has already been normalized and validated.
     *
     * @since 1.2.0
     *
     * @param array<string, mixed> $rule          Normalized rule array.
     * @param string|null          $explicit_type Type the caller passed, if any.
     * @param string               $hook          Hook the rule binds to.
     * @param int                  $hook_priority Hook priority.
     * @return bool True on success, false on metadata failure.
     */
    private static function finalize_registration(
        array $rule,
        ?string $explicit_type,
        string $hook,
        int $hook_priority
    ): bool {
        // Auto-detect required packages and any unresolved namespaces.
        $detection             = self::detect_packages_for_rule($rule, $explicit_type);
        $required_packages     = $detection['resolved'];
        $unresolved_namespaces = $detection['unresolved'];

        // Determine type (explicit or auto-detected).
        if (null !== $explicit_type) {
            $type = $explicit_type;
        } else {
            $type = self::detect_type($required_packages, $hook, $hook_priority);
        }

        // Validate type conflicts and potentially override type.
        $type = self::validate_type_conflicts(
            $type,
            $required_packages,
            $explicit_type,
            $hook,
            $hook_priority,
            $rule['id']
        );

        // Auto-add WP package if type is 'wp' and not already included.
        // This handles cases where hooks are used (->on()) but no WP conditions/actions are present.
        if ($type === 'wp' && ! in_array('WP', $required_packages, true)) {
            $required_packages[] = 'WP';
        }

        // Build metadata.
        $metadata = array(
            'required_packages'     => $required_packages,
            'unresolved_namespaces' => $unresolved_namespaces,
            'explicit_type'         => $explicit_type,
            'type'                  => $type,
            'order'                 => $rule['order'],
            'enabled'               => $rule['enabled'],
        );

        // Add hook info only for 'wp' type.
        if ($type === 'wp') {
            $metadata['hook']          = $hook;
            $metadata['hook_priority'] = $hook_priority;
        }

        // Store metadata in rule.
        $rule['_metadata'] = $metadata;

        // Remove order and enabled from top-level (now in metadata).
        unset($rule['order']);
        unset($rule['enabled']);

        // Register the rule with new signature (rule + metadata).
        PackageManager::register_rule($rule, $metadata);

        return true;
    }

    /**
     * Validate a rule configuration.
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $rule The rule to validate.
     * @return bool True if valid, false otherwise.
     */
    private static function validate_rule(array $rule): bool
    {
        $required_fields = array( 'id', 'conditions', 'actions' );

        foreach ($required_fields as $field) {
            if (! isset($rule[ $field ])) {
                Logger::error("Missing required field: {$field}");
                return false;
            }
        }

        $conditions = $rule['conditions'] ?? null;
        $actions = $rule['actions'] ?? null;

        if (! is_array($conditions) || ! is_array($actions)) {
            Logger::error('Conditions and actions must be arrays');
            return false;
        }

        return true;
    }

    // ====================================
    // Auto-Detection Helper Methods
    // ====================================

    /**
     * Map a fully qualified class name to its package name.
     *
     * Returns 'Core' for MilliRules\Conditions\ / MilliRules\Actions\ classes,
     * a package name for any registered namespace, or null when unknown so the
     * caller can defer instead of silently labeling it Core.
     *
     * @since 0.1.0
     *
     * @param string $class_name Fully-qualified class name.
     * @return string|null Package name, 'Core' for Core namespaces, or null if unresolved.
     */
    private static function map_class_to_package(string $class_name): ?string
    {
        if (PackageManager::has_packages()) {
            $package_name = PackageManager::map_namespace_to_package($class_name);
            if (null !== $package_name) {
                return $package_name;
            }
        }

        foreach (self::$namespace_package_map as $namespace => $package_name) {
            if (strpos($class_name, $namespace) === 0) {
                return $package_name;
            }
        }

        return null;
    }

    /**
     * Detect required packages and unresolved namespaces for a rule array.
     *
     * Static, so PackageManager::register_pending_rules() can re-run detection
     * against a stored rule array without needing a Rules builder instance.
     *
     * @since 1.2.0
     *
     * @param array<string, mixed> $rule          The rule configuration array.
     * @param string|null          $explicit_type Type passed to Rules::create($type), if any.
     * @return array{resolved: array<int, string>, unresolved: array<int, string>}
     */
    public static function detect_packages_for_rule(array $rule, ?string $explicit_type = null): array
    {
        $packages   = array();
        $unresolved = array();

        $conditions = $rule['conditions'] ?? array();
        if (is_array($conditions)) {
            self::collect_condition_packages($conditions, $packages, $unresolved);
        }

        $actions = $rule['actions'] ?? array();
        if (is_array($actions)) {
            foreach ($actions as $action) {
                if (! is_array($action)) {
                    continue;
                }

                $type = $action['type'] ?? '';
                if (empty($type) || ! is_string($type)) {
                    continue;
                }

                if (self::has_custom_action($type)) {
                    continue;
                }

                $class_name = RuleEngine::type_to_class_name($type, 'Actions');
                $package    = self::resolve_package_for_class($class_name);

                if (null === $package) {
                    $unresolved[] = $class_name;
                } else {
                    $packages[] = $package;
                }
            }
        }

        if (null !== $explicit_type) {
            $matched = false;

            if (PackageManager::has_packages()) {
                foreach (PackageManager::get_all_packages() as $package) {
                    if (strcasecmp($package->get_name(), $explicit_type) === 0) {
                        $packages[] = $package->get_name();
                        $matched    = true;
                        break;
                    }
                }
            }

            // 'php' and 'wp' are detection labels (see detect_type()), not always
            // registered packages — never treat them as unresolved.
            if (! $matched && ! in_array(strtolower($explicit_type), array('php', 'wp'), true)) {
                $unresolved[] = '__explicit_type:' . $explicit_type;
            }
        }

        $packages = array_values(
            array_filter(
                array_unique($packages),
                static function ($package) {
                    return $package !== 'Core';
                }
            )
        );
        sort($packages);

        $unresolved = array_values(array_unique($unresolved));
        sort($unresolved);

        return array(
            'resolved'   => $packages,
            'unresolved' => $unresolved,
        );
    }

    /**
     * Recursively collect packages from conditions, descending into groups.
     *
     * @since 1.2.0
     *
     * @param array<int, mixed>  $conditions The conditions array (may contain groups).
     * @param array<int, string> $packages   Accumulator for resolved package names.
     * @param array<int, string> $unresolved Accumulator for class names with no known package.
     * @return void
     */
    private static function collect_condition_packages(array $conditions, array &$packages, array &$unresolved): void
    {
        foreach ($conditions as $condition) {
            if (! is_array($condition)) {
                continue;
            }

            // Condition group: match_type + conditions, no type.
            if (
                isset($condition['match_type'], $condition['conditions'])
                && ! isset($condition['type'])
            ) {
                $group_conditions = is_array($condition['conditions']) ? $condition['conditions'] : array();
                self::collect_condition_packages($group_conditions, $packages, $unresolved);
                continue;
            }

            $type = $condition['type'] ?? '';
            if (empty($type) || ! is_string($type)) {
                continue;
            }

            if (self::has_custom_condition($type)) {
                continue;
            }

            $class_name = RuleEngine::type_to_class_name($type, 'Conditions');
            $package    = self::resolve_package_for_class($class_name);

            if (null === $package) {
                $unresolved[] = $class_name;
            } else {
                $packages[] = $package;
            }
        }
    }

    /**
     * Resolve a class to its package, treating Core matches with non-existent
     * classes as unresolved.
     *
     * RuleEngine::type_to_class_name() falls back to MilliRules\Actions\* /
     * MilliRules\Conditions\* when no namespace knows the type — that lexically
     * matches Core but the class doesn't actually exist. The class_exists()
     * check distinguishes "real Core class" from "package not loaded yet".
     *
     * @since 1.2.0
     *
     * @param string $class_name Fully-qualified class name to resolve.
     * @return string|null Package name, 'Core', or null if unresolved.
     */
    private static function resolve_package_for_class(string $class_name): ?string
    {
        $package = self::map_class_to_package($class_name);

        if ('Core' === $package && ! class_exists($class_name)) {
            return null;
        }

        return $package;
    }

    /**
     * Detect rule type based on required packages and hook configuration.
     *
     * Detection priority:
     * 1. Explicit hook usage (non-default hook or priority) -> 'wp'
     * 2. WordPress package required -> 'wp'
     * 3. Default -> 'php'
     *
     * Validation rules (see validate_type_conflicts()):
     * - Explicit type='php' with WordPress packages logs warning (may not work)
     * - Explicit type='php' with ->on() usage auto-changes to 'wp' (hook implies WordPress)
     *
     * @since 0.1.0
     *
     * @param array<int, string> $required_packages Array of required package names.
     * @param string             $hook              Hook the rule binds to.
     * @param int                $hook_priority     Hook priority.
     * @return string Rule type: 'php' or 'wp'.
     */
    private static function detect_type(array $required_packages, string $hook, int $hook_priority): string
    {
        // Check if explicit hook was set (non-default values).
        if ($hook !== 'wp' || $hook_priority !== 10) {
            // Explicit hook usage implies WordPress context.
            return 'wp';
        }

        // Check if WordPress package is required.
        if (in_array('WP', $required_packages, true)) {
            return 'wp';
        }

        // Default to PHP (early execution, pure HTTP context).
        // TODO: Add detection for 'laravel', 'symfony', etc. in future.
        return 'php';
    }

    /**
     * Validate type configuration for potential conflicts.
     *
     * Checks for logical inconsistencies between explicit type settings,
     * required packages, and hook configuration. Logs warnings when conflicts
     * are detected and may override the type when necessary.
     *
     * Validation rules:
     * 1. If explicit type='php' but requires WordPress package:
     *    - Logs warning (WordPress conditions/actions require type='wp')
     *    - Respects user intent (no auto-override)
     *    - Rule may not work as expected
     *
     * 2. If explicit type='php' but uses ->on() with non-default hook:
     *    - Logs warning and auto-changes type to 'wp'
     *    - Hook usage definitively requires WordPress context
     *    - Returns 'wp' instead of 'php'
     *
     * @since 0.1.0
     *
     * @param string             $type              The determined rule type.
     * @param array<int, string> $required_packages Array of required package names.
     * @param string|null        $explicit_type     Type passed by the caller, if any.
     * @param string             $hook              Hook the rule binds to.
     * @param int                $hook_priority     Hook priority.
     * @param string             $rule_id           Rule identifier (for log messages).
     * @return string The validated (and potentially corrected) rule type.
     */
    private static function validate_type_conflicts(
        string $type,
        array $required_packages,
        ?string $explicit_type,
        string $hook,
        int $hook_priority,
        string $rule_id
    ): string {
        // Validation 1: Explicit type='php' with WordPress packages.
        if ($explicit_type === 'php' && in_array('WordPress', $required_packages, true)) {
            Logger::warning(
                "Rule '{$rule_id}' has explicit type='php' but requires WordPress package - " .
                'this may not work as expected. Consider using type=\'wp\' or omitting type parameter for auto-detection.'
            );
            // Respect user intent - no override.
        }

        // Validation 2: Explicit type='php' with ->on() usage.
        if ($explicit_type === 'php' && ( $hook !== 'wp' || $hook_priority !== 10 )) {
            Logger::warning(
                "Rule '{$rule_id}' has explicit type='php' but uses WordPress hook '{$hook}' - " .
                'auto-changing type to \'wp\' as hooks require WordPress context.'
            );

            // Override type to 'wp' - hook usage definitively requires WordPress.
            return 'wp';
        }

        return $type;
    }
}
