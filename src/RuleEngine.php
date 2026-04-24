<?php

/**
 * Rule Engine
 *
 * Executes rules and manages execution context.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer <hello@millicache.com>
 */

namespace MilliRules;

use MilliRules\Logger;
use MilliRules\Conditions\ConditionInterface;
use MilliRules\Actions\ActionInterface;
use MilliRules\Packages\PackageManager;
use MilliRules\Context;

/**
 * Class RuleEngine
 *
 * Handles the execution of rules and manages the execution context.
 *
 * Core Responsibilities:
 * - Evaluates rule conditions against execution context
 * - Executes actions immediately when rules match
 * - Tracks execution statistics (processed, matched, executed)
 * - Validates package requirements for rules
 * - Resolves condition and action class names from types
 *
 * Execution Flow:
 * 1. Process each rule in sequence
 * 2. Check if rule is enabled and packages are available
 * 3. Evaluate conditions based on match_type (all/any/none)
 * 4. If conditions match, execute all actions immediately
 * 5. Continue to next rule (no stopping)
 *
 * Match Types:
 * - 'all'  - All conditions must be true (AND logic)
 * - 'any'  - At least one condition must be true (OR logic)
 * - 'none' - All conditions must be false (NOT logic)
 *
 * @since 0.1.0
 */
class RuleEngine
{
    /**
     * Execution context.
     *
     * @since 0.1.0
     * @var Context
     */
    private Context $context;

    /**
     * Execution statistics.
     *
     * @since 0.1.0
     * @var array<string, int>
     */
    private array $stats = array(
        'rules_processed'   => 0,
        'rules_skipped'     => 0,
        'rules_matched'     => 0,
        'actions_executed'  => 0,
    );

    /**
     * Available packages for the current execution.
     *
     * @since 0.1.0
     * @var array<int, string>
     */
    private array $available_packages = array();

    /**
     * Tracks which actions are locked during execution.
     *
     * Keys are lock keys: either an action type (e.g., 'set_ttl') for unscoped actions,
     * or a scope:value composite (e.g., 'flag:archive:author:1') for scoped actions.
     * Values are the rule ID that set the lock.
     *
     * @since 0.1.0
     * @since 1.1.0 Keys can now be scope:value composites for scoped actions.
     * @var array<string, string>
     */
    private array $locked_actions = array();

    /**
     * Registered namespaces for condition and action resolution.
     *
     * @since 0.1.0
     * @var array<string, array<string>>
     */
    private static array $namespaces = array(
        'Conditions' => array(
            'MilliRules\Conditions',
            'MilliRules\Packages\PHP\Conditions',
            'MilliRules\Packages\WordPress\Conditions',
        ),
        'Actions'    => array( 'MilliRules\Actions' ),
    );

    /**
     * Register a namespace for condition/action resolution.
     *
     * @since 0.1.0
     *
     * @param string $type      The type: 'Conditions' or 'Actions'.
     * @param string $namespace The namespace to search (e.g., 'MyPlugin\Conditions').
     * @return void
     */
    public static function register_namespace(string $type, string $namespace): void
    {
        if (! in_array($type, array( 'Conditions', 'Actions' ), true)) {
            return;
        }

        if (! in_array($namespace, self::$namespaces[ $type ], true)) {
            self::$namespaces[ $type ][] = $namespace;
        }
    }

    /**
     * Get registered namespaces for condition/action resolution.
     *
     * Returns all namespaces registered for a specific type, or all namespaces
     * keyed by type if no type is specified.
     *
     * @since 0.7.1
     *
     * @param string|null $type Optional. 'Conditions' or 'Actions'. If null, returns all.
     * @return array<string, array<string>>|array<string> All namespaces keyed by type, or namespaces for a specific type.
     */
    public static function get_registered_namespaces(?string $type = null): array
    {
        if (null === $type) {
            return self::$namespaces;
        }

        return self::$namespaces[ $type ] ?? array();
    }

    /**
     * Execute rules with the provided context.
     *
     * Supports optional package filtering to execute only rules that match
     * available packages. Rules requiring unavailable packages are skipped.
     *
     * @since 0.1.0
     *
     * @param array<int, array<string, mixed>> $rules            The rules to execute.
     * @param Context                 $context          The execution context.
     * @param array<int, string>|null          $allowed_packages Optional array of available package names.
     *                                                            If null, uses loaded packages from PackageManager.
     * @return array<string, mixed> Execution result with statistics and context.
     */
    public function execute(array $rules, Context $context, ?array $allowed_packages = null): array
    {
        // Set the execution context.
        $this->context = $context;

        // Reset locked actions for each execution.
        $this->locked_actions = array();

        // Determine available packages for filtering.
        if (null !== $allowed_packages) {
            $this->available_packages = $allowed_packages;
        } else {
            $this->available_packages = PackageManager::get_loaded_package_names();
        }

        // Execute each rule.
        foreach ($rules as $rule) {
            $this->execute_rule($rule);
        }

        // Flush any aggregated errors
        Logger::flush_aggregated();

        return array(
            'rules_processed'  => $this->stats['rules_processed'],
            'rules_skipped'    => $this->stats['rules_skipped'],
            'rules_matched'    => $this->stats['rules_matched'],
            'actions_executed' => $this->stats['actions_executed'],
            'context'          => $this->context->to_array(),
        );
    }

    /**
     * Execute additional rules, preserving locked actions from prior calls.
     *
     * Used by the WP Package to run rules across multiple hook priorities
     * while keeping locks intact. Priority 20 locks carry to priority 9999.
     *
     * @since 1.1.0
     *
     * @param array<int, array<string, mixed>> $rules   The rules to execute.
     * @param Context                          $context The execution context.
     * @return void
     */
    public function execute_continue(array $rules, Context $context): void
    {
        $this->context = $context;

        if (empty($this->available_packages)) {
            $this->available_packages = PackageManager::get_loaded_package_names();
        }

        foreach ($rules as $rule) {
            $this->execute_rule($rule);
        }

        Logger::flush_aggregated();
    }

    /**
     * Execute a single rule.
     *
     * @since 0.1.0
     * @since 0.1.0
     *
     * @param array<string, mixed> $rule The rule to execute.
     * @return void
     */
    private function execute_rule(array $rule): void
    {
        $this->stats['rules_processed']++;

        // Validate package availability.
        if (! $this->validate_rule_packages($rule, $this->available_packages)) {
            $this->stats['rules_skipped']++;
            return;
        }

        // Check if the rule is enabled.
        if (isset($rule['enabled']) && ! $rule['enabled']) {
            return;
        }

        // Check conditions.
        $conditions_value = $rule['conditions'] ?? array();
        $conditions = is_array($conditions_value) ? $conditions_value : array();
        $match_type_value = $rule['match_type'] ?? 'all';
        $match_type = is_string($match_type_value) ? $match_type_value : 'all';
        $rule_id_value = $rule['id'] ?? 'unknown';
        $rule_id = is_string($rule_id_value) ? $rule_id_value : 'unknown';

        if (! $this->check_conditions($conditions, $match_type)) {
            return;
        }

        // Rule matched.
        $this->stats['rules_matched']++;

        // Expose rule metadata on context for actions.
        $metadata = $rule['_metadata'] ?? array();
        $this->context->set('rule', array(
            'id'    => $rule_id,
            'order' => $metadata['order'] ?? 10,
        ));

        // Execute actions.
        $actions_value = $rule['actions'] ?? array();
        $actions = is_array($actions_value) ? $actions_value : array();
        $this->execute_actions($actions, $rule_id);
    }

    /**
     * Check if conditions match.
     *
     * @since 0.1.0
     *
     * @param array<int, array<string, mixed>> $conditions The conditions to check.
     * @param string                           $match_type The match type ('all', 'any', 'none').
     * @return bool True if conditions match, false otherwise.
     */
    private function check_conditions(array $conditions, string $match_type): bool
    {
        if (empty($conditions)) {
            // Handle empty conditions based on match type logic.
            switch ($match_type) {
                case 'none':
                    // No conditions exist, so none are true (vacuous truth).
                    return true;

                case 'any':
                    // Need at least one true condition, but none exist.
                    return false;

                case 'all':
                default:
                    // All (zero) conditions are satisfied (vacuous truth).
                    return true;
            }
        }

        $matches = array();

        foreach ($conditions as $condition_config) {
            // Detect inline condition groups (has match_type + conditions, no type).
            if (
                isset($condition_config['match_type'], $condition_config['conditions'])
                && ! isset($condition_config['type'])
            ) {
                $group_conditions = is_array($condition_config['conditions']) ? $condition_config['conditions'] : array();
                $group_match_type = is_string($condition_config['match_type']) ? $condition_config['match_type'] : 'all';
                $matches[] = $this->check_conditions($group_conditions, $group_match_type);
                continue;
            }

            $condition = $this->create_condition($condition_config);
            if (! $condition) {
                $matches[] = false;
                continue;
            }

            try {
                $matches[] = $condition->matches($this->context);
            } catch (\Exception $e) {
                Logger::aggregate('condition_check', 'Error checking condition: ' . $e->getMessage());
                $matches[] = false;
            }
        }

        // Apply match type logic.
        switch ($match_type) {
            case 'all':
                return ! in_array(false, $matches, true);

            case 'none':
                return ! in_array(true, $matches, true);

            case 'any':
            default:
                return in_array(true, $matches, true);
        }
    }

    /**
     * Execute actions.
     *
     * @since 0.1.0
     *
     * @param array<int, array<string, mixed>> $actions The actions to execute.
     * @param string                           $rule_id The rule ID.
     * @return void
     */
    private function execute_actions(array $actions, string $rule_id): void
    {
        foreach ($actions as $action_config) {
            $type_value = $action_config['type'] ?? '';
            $type = is_string($type_value) ? $type_value : '';

            // Build lock key: scoped actions use scope:value, unscoped use type.
            // Scope is resolved statically via Rules::get_action_meta(), so we can
            // check the lock before instantiating the action.
            $lock_key = $this->build_lock_key($type, $action_config);

            // Check if this action is already locked.
            if ('' !== $lock_key && isset($this->locked_actions[ $lock_key ])) {
                Logger::warning(
                    sprintf(
                        "Action '%s' (lock key '%s') locked by rule '%s', skipping execution in rule '%s'",
                        $type,
                        $lock_key,
                        $this->locked_actions[ $lock_key ],
                        $rule_id
                    )
                );
                continue; // Skip this action.
            }

            $action = $this->create_action($action_config);
            if (! $action) {
                continue;
            }

            try {
                $action->execute($this->context);
                $this->stats['actions_executed']++;

                // If this action has the locked flag, lock it.
                if ('' !== $lock_key && ! empty($action_config['_locked'])) {
                    $this->locked_actions[ $lock_key ] = $rule_id;
                }
            } catch (\Exception $e) {
                Logger::aggregate('action_execution', 'Error executing action: ' . $e->getMessage());
            }
        }
    }

    /**
     * Build the lock key for an action.
     *
     * Resolves scope via Rules::get_action_scope() — the engine hot path
     * that never calls set_meta(). This is critical because rules may
     * execute during early bootstrap, before framework-specific functions
     * are available.
     *
     * If the action is scoped, the lock key is "scope:value" using the
     * first positional arg as the value. Otherwise it's just the action type.
     *
     * Non-scalar first arguments on scoped actions can't be serialized
     * into a stable lock key, so they return an empty string — the action
     * executes but is not lockable.
     *
     * @since 1.1.0
     *
     * @param string                   $type          The action type.
     * @param array<int|string, mixed> $action_config The action configuration.
     * @return string The lock key, or empty string if the action cannot be locked.
     */
    private function build_lock_key(string $type, array $action_config): string
    {
        if ('' === $type) {
            return '';
        }

        // Fast path: never calls set_meta(). Safe during early bootstrap.
        $scope = Rules::get_action_scope($type);

        if ('' === $scope) {
            return $type;
        }

        // Scoped: build "scope:value" key from the first argument.
        // Builder-created rules use positional keys (0 => 'val'), while
        // data-stored rules use named keys ('flag' => 'val'). Try
        // positional first, then fall back to the first non-internal
        // named key.
        $value = $action_config[0] ?? null;
        if (null === $value && ! array_key_exists(0, $action_config)) {
            foreach ($action_config as $key => $val) {
                if (! is_string($key) || 'type' === $key || ('' !== $key && '_' === $key[0])) {
                    continue;
                }
                $value = $val;
                break;
            }
            if (null === $value) {
                $value = '';
            }
        }

        if (! is_scalar($value)) {
            Logger::warning(
                sprintf(
                    "Scoped action '%s' has non-scalar first argument — action will not be locked",
                    $type
                )
            );
            return ''; // Not lockable; skip the lock check and set.
        }

        $value_str = (string) $value;
        if ('' === $value_str) {
            // No value provided — lock only the scope bucket.
            return $scope;
        }

        return $scope . ':' . $value_str;
    }

    /**
     * Get a value from the execution context.
     *
     * @since 0.1.0
     *
     * @param string $key The context key (supports dot notation, e.g., 'post.type').
     * @return mixed The context value or null if not found.
     */
    public function get_context(string $key)
    {
        return $this->context->get($key);
    }

    /**
     * Validate that a rule's required packages are available.
     *
     * Checks if all packages required by the rule are present in the available packages list.
     * Logs detailed errors for any missing packages.
     *
     * Rules without metadata or without required_packages are considered valid (backward compatible).
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $rule               The rule configuration.
     * @param array<int, string>   $available_packages Array of available package names.
     * @return bool True if all required packages are available, false otherwise.
     */
    private function validate_rule_packages(array $rule, array $available_packages): bool
    {
        // Extract metadata.
        if (! isset($rule['_metadata']) || ! is_array($rule['_metadata'])) {
            // No metadata - rule is valid (backward compatible).
            return true;
        }

        $metadata = $rule['_metadata'];

        // Extract required packages.
        $required_packages = $metadata['required_packages'] ?? array();

        if (empty($required_packages) || ! is_array($required_packages)) {
            // No package requirements - rule is valid.
            return true;
        }

        // Check each required package.
        $missing_packages = array();

        foreach ($required_packages as $required_package) {
            if (! in_array($required_package, $available_packages, true)) {
                $missing_packages[] = $required_package;
            }
        }

        // If any packages are missing, log error and return false.
        if (! empty($missing_packages)) {
            $rule_id_value = $rule['id'] ?? 'unknown';
            $rule_id       = is_string($rule_id_value) ? $rule_id_value : 'unknown';

            $required_list  = implode(', ', $required_packages);
            $available_list = empty($available_packages) ? 'none' : implode(', ', $available_packages);
            $missing_list   = implode(', ', $missing_packages);

            Logger::warning(
                sprintf(
                    "Rule '%s' requires packages [%s] but only [%s] are available. Missing: [%s]",
                    $rule_id,
                    $required_list,
                    $available_list,
                    $missing_list
                )
            );

            return false;
        }

        return true;
    }

    /**
     * Create a condition instance from configuration.
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $config The condition configuration.
     * @return ConditionInterface|null The condition instance or null on error.
     */
    private function create_condition(array $config): ?ConditionInterface
    {
        $type_value = $config['type'] ?? '';
        $type = is_string($type_value) ? $type_value : '';

        if (empty($type)) {
            Logger::error('Condition type not specified');
            return null;
        }

        // Check for custom callback-based condition first.
        if (Rules::has_custom_condition($type)) {
            $callback = Rules::get_custom_condition($type);

            if ($callback) {
                try {
                    return new Conditions\Callback($type, $callback, $config, $this->context);
                } catch (\Exception $e) {
                    Logger::error('Error creating callback condition: ' . $e->getMessage());
                    return null;
                }
            }
        }

        // Auto-resolve class name from type using convention.
        // Convert: is_user_logged_in → IsUserLoggedIn.
        $class_name = self::type_to_class_name($type, 'Conditions');

        // Check if the class exists.
        if (! class_exists($class_name)) {
            Logger::error('Unknown condition type: ' . $type);
            return null;
        }

        try {
            $instance = new $class_name($config, $this->context);
            return $instance instanceof ConditionInterface ? $instance : null;
        } catch (\Exception $e) {
            Logger::error('Error creating condition: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create an action instance from the configuration.
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $config The action configuration.
     * @return ActionInterface|null The action instance or null on error.
     */
    private function create_action(array $config): ?ActionInterface
    {
        $type_value = $config['type'] ?? '';
        $type = is_string($type_value) ? $type_value : '';

        if (empty($type)) {
            Logger::error('Action type not specified');
            return null;
        }

        // Check for custom callback-based action first.
        if (Rules::has_custom_action($type)) {
            $callback = Rules::get_custom_action($type);

            if ($callback) {
                try {
                    return new Actions\Callback($type, $callback, $config, $this->context);
                } catch (\Exception $e) {
                    Logger::error('Error creating callback action: ' . $e->getMessage());
                    return null;
                }
            }
        }

        // Auto-resolve class name from type using convention.
        // Convert: add_flag → AddFlag.
        $class_name = self::type_to_class_name($type, 'Actions');

        // Check if the class exists.
        if (! class_exists($class_name)) {
            Logger::error('Unknown action type: ' . $type);
            return null;
        }

        try {
            $instance = new $class_name($config, $this->context);
            return $instance instanceof ActionInterface ? $instance : null;
        } catch (\Exception $e) {
            Logger::error('Error creating action: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Convert a PascalCase class base name to a snake_case type string.
     *
     * Inverse of the conversion in type_to_class_name():
     *   'IsUserLoggedIn' → 'is_user_logged_in'
     *   'PostType'       → 'post_type'
     *
     * @internal
     * @since 1.1.0
     *
     * @param string $class_base The unqualified class name (e.g., 'PostType').
     * @return string The snake_case type string.
     */
    public static function class_name_to_type(string $class_base): string
    {
        $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $class_base);

        return strtolower(is_string($replaced) ? $replaced : $class_base);
    }

    /**
     * Discover all class-based type strings for a given namespace type.
     *
     * Scans all registered namespace directories for PHP files whose classes
     * extend the appropriate base class (BaseCondition or BaseAction).
     *
     * Uses the Composer autoloader (PSR-4) to resolve namespace prefixes to
     * directories. Falls back to the MilliRules src/ directory for the
     * project's own namespaces.
     *
     * @internal
     * @since 1.1.0
     *
     * @param string $type 'Conditions' or 'Actions'.
     * @return array<string, string> Map of type string => fully-qualified class name.
     */
    public static function scan_namespace_types(string $type): array
    {
        $base_class = 'Conditions' === $type
            ? Conditions\BaseCondition::class
            : Actions\BaseAction::class;

        $skip = array(
            Conditions\BaseCondition::class,
            Actions\BaseAction::class,
            Conditions\Callback::class,
            Actions\Callback::class,
            'MilliRules\Conditions\ConditionInterface',
            'MilliRules\Actions\ActionInterface',
        );

        $results = array();

        foreach (self::$namespaces[ $type ] ?? array() as $namespace) {
            $dir = self::namespace_to_directory($namespace);
            if (null === $dir || ! is_dir($dir)) {
                continue;
            }

            $files = scandir($dir);
            if (false === $files) {
                continue;
            }

            foreach ($files as $file) {
                if ('.' === $file[0] || 'php' !== pathinfo($file, PATHINFO_EXTENSION)) {
                    continue;
                }

                $class_base = pathinfo($file, PATHINFO_FILENAME);
                $fqn        = $namespace . '\\' . $class_base;

                if (in_array($fqn, $skip, true)) {
                    continue;
                }

                if (! class_exists($fqn) || ! is_subclass_of($fqn, $base_class)) {
                    continue;
                }

                // Let classes opt out of discovery (e.g., handler base classes).
                if (method_exists($fqn, 'is_discoverable') && ! $fqn::is_discoverable()) {
                    continue;
                }

                $type_string = self::class_name_to_type($class_base);
                $results[ $type_string ] = $fqn;
            }
        }

        return $results;
    }

    /**
     * Resolve a namespace to a filesystem directory.
     *
     * Tries Composer's ClassLoader PSR-4 prefix map first (validating that
     * the resolved directory exists, so Strauss-rewritten prefixes don't
     * short-circuit to stale paths), then its classmap, then derives the
     * path from the MilliRules src/ root.
     *
     * @internal
     * @since 1.1.0
     *
     * @param string $namespace The fully-qualified namespace.
     * @return string|null The directory path, or null if not resolvable.
     */
    private static function namespace_to_directory(string $namespace): ?string
    {
        foreach (spl_autoload_functions() as $autoloader) {
            if (
                ! is_array($autoloader)
                || ! ($autoloader[0] instanceof \Composer\Autoload\ClassLoader)
            ) {
                continue;
            }

            /** @var \Composer\Autoload\ClassLoader $loader */
            $loader = $autoloader[0];

            // 1. PSR-4 resolution — validate the directory exists so overly
            //    generic host-plugin prefixes don't short-circuit the lookup.
            foreach ($loader->getPrefixesPsr4() as $prefix => $dirs) {
                if (strpos($namespace . '\\', $prefix) !== 0) {
                    continue;
                }
                $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($namespace, strlen($prefix)));
                foreach ($dirs as $dir) {
                    $candidate = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative;
                    if (is_dir($candidate)) {
                        return $candidate;
                    }
                }
            }

            // 2. Classmap fallback — first matching entry under the namespace
            //    prefix; dirname() gives the enclosing directory.
            $prefix = $namespace . '\\';
            foreach ($loader->getClassMap() as $class => $file) {
                if (strpos($class, $prefix) === 0) {
                    return dirname($file);
                }
            }
        }

        // 3. Direct src/ fallback for unscoped MilliRules\ usage.
        if (strpos($namespace, 'MilliRules\\') === 0) {
            $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($namespace, strlen('MilliRules\\')));
            return dirname(__DIR__) . DIRECTORY_SEPARATOR . $relative;
        }

        return null;
    }

    /**
     * Convert a type string to a class name using convention.
     *
     * Converts snake_case type to PascalCase class name.
     * Searches through registered namespaces to find the class.
     *
     * @internal This is an internal implementation detail used by Rules and RuleEngine.
     *           Do not call this method directly - it is not part of the public API.
     *
     * @since 0.1.0
     * @since 0.1.0
     *
     * @param string $type      The type string (e.g., 'is_user_logged_in').
     * @param string $namespace The namespace suffix (Conditions or Actions).
     * @return string The fully qualified class name.
     */
    public static function type_to_class_name(string $type, string $namespace): string
    {
        // Convert snake_case to PascalCase.
        $class_base = str_replace('_', '', ucwords($type, '_'));

        // Try each registered namespace for this type.
        $namespaces = self::$namespaces[ $namespace ] ?? array();

        foreach ($namespaces as $ns) {
            $class_name = $ns . '\\' . $class_base;
            if (class_exists($class_name)) {
                return $class_name;
            }
        }

        // Ask registered packages if they can resolve this type.
        if (PackageManager::has_packages()) {
            foreach (PackageManager::get_all_packages() as $package) {
                $resolved = $package->resolve_class_name($type, $namespace);
                if (null !== $resolved) {
                    return $resolved;
                }
            }
        }

        // Default to first registered namespace.
        $default_namespace = $namespaces[0] ?? 'MilliRules\\' . $namespace;
        return $default_namespace . '\\' . $class_base;
    }
}
