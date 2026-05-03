<?php

/**
 * Package Manager
 *
 * Central manager for MilliRules packages. Handles package registration, loading,
 * dependency resolution, context building, and namespace mapping.
 *
 * Features:
 * - Package registration and lifecycle management
 * - Automatic dependency resolution with cycle detection
 * - Context aggregation from multiple packages
 * - Placeholder resolver prioritization
 * - Namespace-to-package mapping for fast lookups
 * - Rule delegation to appropriate packages
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 * @since 0.1.0
 */

namespace MilliRules\Packages;

use MilliRules\Logger;
use MilliRules\Rules;

/**
 * Class PackageManager
 *
 * Static manager for all MilliRules packages.
 *
 * @since 0.1.0
 */
class PackageManager
{
    /**
     * Registered package instances.
     *
     * @since 0.1.0
     * @var array<string, PackageInterface>
     */
    private static array $packages = array();

    /**
     * Names of currently loaded packages.
     *
     * @since 0.1.0
     * @var array<int, string>
     */
    private static array $loaded_packages = array();

    /**
     * Maps namespaces to package names for fast lookup.
     *
     * Structure: ['MilliRules\PHP\Conditions' => 'PHP', ...]
     *
     * @since 0.1.0
     * @var array<string, string>
     */
    private static array $namespace_registry = array();

    /**
     * Cache for namespace-to-package lookups.
     *
     * Stores results of map_namespace_to_package() to avoid repeated sorting
     * and string operations. Cache is cleared when packages are registered.
     *
     * Structure: ['MilliRules\PHP\Conditions\RequestUri' => 'PHP', ...]
     *
     * @since 0.1.0
     * @var array<string, string|null>
     */
    private static array $namespace_cache = array();

    /**
     * Queue of rules waiting for their required packages to be loaded.
     *
     * Rules are added here when registered before their packages are loaded.
     * When a package loads, pending rules are checked and registered if all
     * their dependencies are now met.
     *
     * Structure: [
     *   [
     *     'rule' => [...],
     *     'metadata' => [...],
     *     'required_packages' => ['WP', 'PHP']
     *   ],
     *   ...
     * ]
     *
     * @since 0.1.0
     * @var array<int, array{rule: array<string, mixed>, metadata: array<string, mixed>, required_packages: array<int, string>}>
     */
    private static array $pending_rules = array();

    /**
     * Register a package with the manager.
     *
     * Stores package instance and maps its namespaces for fast lookup.
     * Clears the namespace cache to ensure fresh lookups.
     * Does not load the package - call load_available_packages() to load.
     *
     * @since 0.1.0
     *
     * @param PackageInterface $package The package to register.
     * @return void
     */
    public static function register_package(PackageInterface $package): void
    {
        $name                  = $package->get_name();
        self::$packages[ $name ] = $package;

        // Map namespaces to package name.
        foreach ($package->get_namespaces() as $namespace) {
            self::$namespace_registry[ $namespace ] = $name;
        }

        // Clear namespace cache (namespace registry changed).
        self::$namespace_cache = array();
    }

    /**
     * Load available packages with dependency resolution.
     *
     * If $package_names is null, checks all registered packages with is_available()
     * and loads those that are available.
     *
     * If $package_names is provided, loads only those packages if available.
     *
     * Resolves dependencies recursively via get_required_packages().
     * Implements cycle detection for circular dependencies.
     * Calls register_namespaces() for each loaded package.
     *
     * Logs errors for:
     * - Missing dependencies
     * - Circular dependencies
     * - Unavailable packages
     *
     * @since 0.1.0
     *
     * @param array<int, string>|null $package_names Optional array of package names to load.
     * @return array<int, string> Array of successfully loaded package names.
     */
    public static function load_packages(?array $package_names = null): array
    {
        $to_load = array();

        if (null === $package_names) {
            // Auto-detect available packages.
            foreach (self::$packages as $name => $package) {
                if ($package->is_available()) {
                    $to_load[] = $name;
                }
            }
        } else {
            $to_load = $package_names;
        }

        // Load packages with dependency resolution.
        $loading_stack = array();

        foreach ($to_load as $name) {
            self::load_package_recursive($name, $loading_stack);
        }

        return self::$loaded_packages;
    }

    /**
     * Recursively load a package and its dependencies with cycle detection.
     *
     * Implements cycle detection by tracking packages currently being loaded in the
     * $loading_stack parameter. If a package is encountered that's already in the stack,
     * a circular dependency is detected and logged.
     *
     * Algorithm:
     * 1. Check if package already loaded (skip if yes)
     * 2. Validate package exists and is available
     * 3. Check if package is in loading_stack (cycle detection)
     * 4. Add package to loading_stack
     * 5. Recursively load all required packages
     * 6. Register package namespaces
     * 7. Mark as loaded
     * 8. Remove from loading_stack
     *
     * Example cycle: A → B → C → A would be detected when loading A the second time.
     *
     * @since 0.1.0
     *
     * @param string               $name          The package name to load.
     * @param array<int, string>   $loading_stack Stack of packages currently being loaded (for cycle detection).
     * @return bool True if loaded successfully, false otherwise.
     */
    private static function load_package_recursive(string $name, array &$loading_stack): bool
    {
        // Already loaded - skip.
        if (in_array($name, self::$loaded_packages, true)) {
            return true;
        }

        // Check if package exists.
        if (! isset(self::$packages[ $name ])) {
            Logger::warning("Package '{$name}' not registered");
            return false;
        }

        $package = self::$packages[ $name ];

        // Check if available.
        if (! $package->is_available()) {
            Logger::warning("Package '{$name}' is not available in this environment");
            return false;
        }

        // Cycle detection.
        if (in_array($name, $loading_stack, true)) {
            $cycle_path = implode(' → ', $loading_stack) . ' → ' . $name;
            Logger::error("Circular dependency detected: {$cycle_path}");
            return false;
        }

        // Add to loading stack for cycle detection.
        $loading_stack[] = $name;

        // Load dependencies first.
        $required_packages = $package->get_required_packages();
        foreach ($required_packages as $required_name) {
            if (! self::load_package_recursive($required_name, $loading_stack)) {
                Logger::error("Failed to load required package '{$required_name}' for '{$name}'");
                // Remove from loading stack.
                array_pop($loading_stack);
                return false;
            }
        }

        // Register namespaces with RuleEngine.
        $package->register_namespaces();

        // Mark as loaded.
        self::$loaded_packages[] = $name;

        // Process pending rules - now that this package is loaded.
        self::register_pending_rules();

        // Remove from loading stack.
        array_pop($loading_stack);

        return true;
    }

    /**
     * Get all loaded package instances.
     *
     * @since 0.1.0
     *
     * @return array<int, PackageInterface> Array of loaded package instances.
     */
    public static function get_loaded_packages(): array
    {
        $packages = array();

        foreach (self::$loaded_packages as $name) {
            if (isset(self::$packages[ $name ])) {
                $packages[] = self::$packages[ $name ];
            }
        }

        return $packages;
    }

    /**
     * Get all registered package instances (loaded or not).
     *
     * Returns all packages that have been registered, regardless of
     * whether they have been loaded. This allows querying package
     * capabilities (like resolve_class_name) without triggering loading.
     *
     * @since 0.1.0
     *
     * @return array<int, PackageInterface> Array of all registered package instances.
     */
    public static function get_all_packages(): array
    {
        return array_values(self::$packages);
    }

    /**
     * Get loaded package names.
     *
     * @since 0.1.0
     *
     * @return array<int, string> Array of loaded package names.
     */
    public static function get_loaded_package_names(): array
    {
        return self::$loaded_packages;
    }

    /**
     * Check if a package is loaded.
     *
     * @since 0.1.0
     *
     * @param string $name The package name.
     * @return bool True if loaded, false otherwise.
     */
    public static function is_package_loaded(string $name): bool
    {
        return in_array($name, self::$loaded_packages, true);
    }

    /**
     * Get a package instance by name.
     *
     * @since 0.1.0
     *
     * @param string $name The package name.
     * @return PackageInterface|null The package instance or null if not registered.
     */
    public static function get_package(string $name): ?PackageInterface
    {
        return self::$packages[ $name ] ?? null;
    }

    /**
     * Build aggregated context from all loaded packages.
     *
     * Creates an ExecutionContext and registers all lazy providers from loaded packages.
     *
     * @since 0.1.0
     *
     * @return \MilliRules\Context ExecutionContext with all providers registered.
     */
    public static function build_context(): \MilliRules\Context
    {
        $context = new \MilliRules\Context();

        foreach (self::get_loaded_packages() as $package) {
            // Use the new register_providers method from Package
            if (method_exists($package, 'register_context_providers')) {
                $package->register_context_providers($context);
            }
        }

        return $context;
    }

    /**
     * Get placeholder resolver from prioritized package.
     *
     * Prioritizes packages in order: WP > PHP
     * Returns resolver from first available package that provides one.
     *
     * @since 0.1.0
     *
     * @param \MilliRules\Context $context The execution context.
     * @return object|null PlaceholderResolver instance or null.
     */
    public static function get_placeholder_resolver(\MilliRules\Context $context)
    {
        $priority_order = array( 'WP', 'PHP' );

        // Try priority packages first.
        foreach ($priority_order as $name) {
            if (self::is_package_loaded($name)) {
                $package  = self::get_package($name);
                $resolver = $package ? $package->get_placeholder_resolver($context) : null;

                if ($resolver) {
                    return $resolver;
                }
            }
        }

        // Try any loaded package.
        foreach (self::get_loaded_packages() as $package) {
            $resolver = $package->get_placeholder_resolver($context);
            if ($resolver) {
                return $resolver;
            }
        }

        return null;
    }

    /**
     * Map a class name to its package name using longest-match algorithm with caching.
     *
     * Searches namespace registry to find which package provides the namespace
     * for the given class. Uses longest-match algorithm to handle overlapping
     * namespaces correctly.
     *
     * Performance:
     * - Results are cached to avoid repeated sorting and string operations
     * - First lookup performs full algorithm, subsequent lookups use cache
     * - Cache is cleared when packages are registered
     *
     * Algorithm:
     * 1. Check cache for previous result
     * 2. If not cached, sort registered namespaces by length (descending)
     * 3. Find first namespace that matches class name
     * 4. Cache and return associated package name
     *
     * This ensures that when multiple namespaces match, the most specific
     * (longest) namespace is used.
     *
     * Example:
     * - Registered: 'MilliRules\PHP\' → 'PHP'
     * - Registered: 'MilliRules\PHP\Extended\' → 'PHPExtended'
     * - Class: 'MilliRules\PHP\Extended\CustomCondition'
     * - Result: 'PHPExtended' (longest match)
     *
     * @since 0.1.0
     *
     * @param string $class_name Fully-qualified class name.
     * @return string|null Package name or null if not found.
     */
    public static function map_namespace_to_package(string $class_name): ?string
    {
        // Check cache first.
        if (isset(self::$namespace_cache[ $class_name ])) {
            return self::$namespace_cache[ $class_name ];
        }

        // Get all namespaces as keys.
        $namespaces = array_keys(self::$namespace_registry);

        // Sort by length descending (longest first).
        usort(
            $namespaces,
            function ($a, $b) {
                return strlen($b) - strlen($a);
            }
        );

        // Find first (longest) matching namespace.
        foreach ($namespaces as $namespace) {
            if (strpos($class_name, $namespace) === 0) {
                $result = self::$namespace_registry[ $namespace ];
                // Cache the result.
                self::$namespace_cache[ $class_name ] = $result;
                return $result;
            }
        }

        // Cache null result to avoid repeated failed lookups.
        self::$namespace_cache[ $class_name ] = null;
        return null;
    }

    /**
     * Check if any packages are registered.
     *
     * @since 0.1.0
     *
     * @return bool True if at least one package is registered.
     */
    public static function has_packages(): bool
    {
        return ! empty(self::$packages);
    }

    /**
     * Register a rule by delegating to appropriate packages.
     *
     * Defers when unresolved_namespaces is non-empty or a required package
     * isn't loaded yet. Deferred rules are re-evaluated by register_pending_rules().
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $rule     The rule configuration.
     * @param array<string, mixed> $metadata Includes 'required_packages' and 'unresolved_namespaces'.
     * @return void
     */
    public static function register_rule(array $rule, array $metadata): void
    {
        $required_packages     = $metadata['required_packages'] ?? array();
        $unresolved_namespaces = $metadata['unresolved_namespaces'] ?? array();
        $rule_id               = $rule['id'] ?? 'unknown';

        if (! empty($unresolved_namespaces)) {
            self::$pending_rules[] = array(
                'rule'              => $rule,
                'metadata'          => $metadata,
                'required_packages' => $required_packages,
            );
            return;
        }

        foreach ($required_packages as $package_name) {
            if (! self::is_package_loaded($package_name)) {
                self::$pending_rules[] = array(
                    'rule'              => $rule,
                    'metadata'          => $metadata,
                    'required_packages' => $required_packages,
                );
                return;
            }
        }

        // Remove rule from packages it's no longer targeting.
        // This handles the case where a rule override changes required_packages
        // (e.g., from WP to PHP), preventing duplicates across packages.
        if ('unknown' !== $rule_id) {
            foreach (self::$packages as $pkg_name => $pkg) {
                if (! in_array($pkg_name, $required_packages, true)) {
                    $pkg->unregister_rule($rule_id);
                }
            }
        }

        // Register in target packages.
        foreach ($required_packages as $package_name) {
            if (isset(self::$packages[ $package_name ])) {
                self::$packages[ $package_name ]->register_rule($rule, $metadata);
            } else {
                Logger::warning("Cannot register rule '{$rule_id}' - package '{$package_name}' not registered");
            }
        }
    }

    /**
     * Unregister a rule by its ID from all packages.
     *
     * Iterates through all registered packages and removes the rule with the
     * given ID. Also removes the rule from the pending rules queue if present.
     *
     * @since 0.5.0
     *
     * @param string $rule_id The ID of the rule to unregister.
     * @return bool True if a rule was found and removed from at least one package, false otherwise.
     */
    public static function unregister_rule(string $rule_id): bool
    {
        $found = false;

        // Remove from all packages.
        foreach (self::$packages as $package) {
            if ($package->unregister_rule($rule_id)) {
                $found = true;
            }
        }

        // Also remove from pending rules queue.
        foreach (self::$pending_rules as $index => $pending) {
            if (($pending['rule']['id'] ?? null) === $rule_id) {
                array_splice(self::$pending_rules, $index, 1);
                $found = true;
                break;
            }
        }

        return $found;
    }

    /**
     * Re-evaluate pending rules and register any that can now be resolved.
     *
     * Called automatically after each package loads. Re-runs detection against
     * the current namespace registry so previously-unknown classes can resolve.
     *
     * @since 0.1.0
     *
     * @return int Number of rules successfully registered.
     */
    public static function register_pending_rules(): int
    {
        $registered_count = 0;
        $remaining_rules  = array();

        foreach (self::$pending_rules as $pending) {
            $rule          = $pending['rule'];
            $metadata      = $pending['metadata'];
            $rule_id       = $rule['id'] ?? 'unknown';
            $explicit_type = $metadata['explicit_type'] ?? null;

            $detection             = Rules::detect_packages_for_rule($rule, $explicit_type);
            $required_packages     = $detection['resolved'];
            $unresolved_namespaces = $detection['unresolved'];

            // Re-detection only sees conditions/actions/explicit_type — it can't
            // see the 'WP' that Rules::register() appends from rule type detection
            // (e.g. ->on() without WP conditions). Preserve original packages.
            foreach ($pending['required_packages'] as $original_pkg) {
                if (! in_array($original_pkg, $required_packages, true)) {
                    $required_packages[] = $original_pkg;
                }
            }
            sort($required_packages);

            $metadata['required_packages']     = $required_packages;
            $metadata['unresolved_namespaces'] = $unresolved_namespaces;

            if (! empty($unresolved_namespaces)) {
                $remaining_rules[] = array(
                    'rule'              => $rule,
                    'metadata'          => $metadata,
                    'required_packages' => $required_packages,
                );
                continue;
            }

            $all_loaded = true;
            foreach ($required_packages as $package_name) {
                if (! self::is_package_loaded($package_name)) {
                    $all_loaded = false;
                    break;
                }
            }

            if (! $all_loaded) {
                $remaining_rules[] = array(
                    'rule'              => $rule,
                    'metadata'          => $metadata,
                    'required_packages' => $required_packages,
                );
                continue;
            }

            if ('unknown' !== $rule_id) {
                foreach (self::$packages as $pkg_name => $pkg) {
                    if (! in_array($pkg_name, $required_packages, true)) {
                        $pkg->unregister_rule($rule_id);
                    }
                }
            }

            foreach ($required_packages as $package_name) {
                if (isset(self::$packages[ $package_name ])) {
                    self::$packages[ $package_name ]->register_rule($rule, $metadata);
                } else {
                    Logger::warning("Cannot register rule '{$rule_id}' - package '{$package_name}' not registered");
                }
            }
            $registered_count++;
        }

        self::$pending_rules = $remaining_rules;
        return $registered_count;
    }

    /**
     * Get pending rules waiting for packages to load.
     *
     * Returns the queue of rules that have been registered but are waiting
     * for their required packages to be loaded. Useful for debugging and
     * monitoring the rule registration process.
     *
     * @since 0.1.0
     *
     * @return array<int, array{rule: array<string, mixed>, metadata: array<string, mixed>, required_packages: array<int, string>}> Array of pending rule data.
     */
    public static function get_pending_rules(): array
    {
        return self::$pending_rules;
    }

    /**
     * Get pending rules deferred specifically due to unknown class namespaces
     * (as opposed to known packages that simply aren't loaded yet).
     *
     * @since 1.2.0
     *
     * @return array<int, array{rule: array<string, mixed>, metadata: array<string, mixed>, required_packages: array<int, string>}>
     */
    public static function get_unresolved_pending(): array
    {
        $unresolved = array();

        foreach (self::$pending_rules as $pending) {
            if (! empty($pending['metadata']['unresolved_namespaces'] ?? array())) {
                $unresolved[] = $pending;
            }
        }

        return $unresolved;
    }

    /**
     * Final-pass pending rule registration with warnings for anything stuck.
     *
     * Call once the package system is fully initialized (e.g. on 'wp_loaded' or
     * at the end of CLI bootstrap). Deferral during boot is silent; this is the
     * point where unresolved rules become loud.
     *
     * @since 1.2.0
     *
     * @return int Number of rules still pending after the final pass.
     */
    public static function finalize_pending_rules(): int
    {
        self::register_pending_rules();

        $still_pending = count(self::$pending_rules);

        foreach (self::$pending_rules as $pending) {
            $rule_id               = $pending['rule']['id'] ?? 'unknown';
            $unresolved_namespaces = $pending['metadata']['unresolved_namespaces'] ?? array();
            $required_packages     = $pending['required_packages'] ?? array();

            if (! empty($unresolved_namespaces)) {
                Logger::warning(
                    "Rule '{$rule_id}' could not be registered - unresolved class namespaces: " .
                    implode(', ', $unresolved_namespaces) .
                    '. Check for typo\'d action/condition types or missing package registrations.'
                );
            } else {
                $missing = array();
                foreach ($required_packages as $package_name) {
                    if (! self::is_package_loaded($package_name)) {
                        $missing[] = $package_name;
                    }
                }
                Logger::warning(
                    "Rule '{$rule_id}' could not be registered - required packages not loaded: " .
                    implode(', ', $missing)
                );
            }
        }

        return $still_pending;
    }

    /**
     * Get all rules from all loaded packages, tagged with their package name.
     *
     * Aggregates get_rules() across all loaded packages and adds a '_package'
     * key to each rule identifying which package it belongs to. Handles both
     * flat rule arrays (BasePackage) and grouped rule arrays (WordPress groups
     * by hook name) by normalizing them into a single flat collection.
     *
     * @since 0.6.1
     *
     * @return array<int, array<string, mixed>> Flat array of rules, each with '_package' key added.
     */
    public static function get_all_rules(): array
    {
        $all_rules = array();

        foreach (self::get_loaded_packages() as $package) {
            $package_name = $package->get_name();

            foreach (self::flatten_rules($package->get_rules()) as $rule) {
                $rule['_package'] = $package_name;
                $rule_id          = $rule['id'] ?? null;

                if (null !== $rule_id) {
                    // Deduplicate by ID — later packages override earlier ones.
                    $all_rules[ $rule_id ] = $rule;
                } else {
                    $all_rules[] = $rule;
                }
            }
        }

        return array_values($all_rules);
    }

    /**
     * Flatten a rules array that may be grouped (e.g., by hook name).
     *
     * Packages may return rules as either:
     * - Flat array: [rule1, rule2, ...] (numeric keys)
     * - Grouped array: ['hook_name' => [rule1, rule2, ...], ...] (string keys)
     *
     * This method normalizes both formats into a flat array by detecting
     * grouped entries (string key, array value without 'id' key).
     *
     * @since 0.6.1
     *
     * @param array<int|string, mixed> $rules The rules array to flatten.
     * @return array<int, array<string, mixed>> Flat array of rules.
     */
    private static function flatten_rules(array $rules): array
    {
        $flat = array();

        foreach ($rules as $key => $value) {
            // Grouped structure: string key (hook name) → array of rules.
            if (is_string($key) && is_array($value) && ! isset($value['id'])) {
                foreach ($value as $rule) {
                    $flat[] = $rule;
                }
            } else {
                // Already flat: numeric key → rule array.
                $flat[] = $value;
            }
        }

        return $flat;
    }

    /**
     * Clear all registered rules from packages and reset loaded state.
     *
     * This method:
     * - Calls clear() on all registered packages to remove their rules
     * - Clears the loaded packages list
     * - Clears the pending rules queue
     * - Preserves package registrations ($packages array)
     * - Preserves namespace mappings ($namespace_registry)
     *
     * This allows rules to be cleared and re-registered without re-registering
     * packages or losing namespace mappings.
     *
     * Useful for testing scenarios where you want to reset rules between test cases
     * while keeping the package system initialized.
     *
     * Use reset() instead if you need a complete fresh start including package
     * de-registration.
     *
     * @since 0.1.0
     *
     * @return void
     */
    public static function clear(): void
    {
        // Clear all rules from registered packages.
        foreach (self::$packages as $package) {
            if (method_exists($package, 'clear')) {
                $package->clear();
            }
        }

        // Clear loaded packages list (packages remain registered).
        self::$loaded_packages = array();

        // Clear pending rules queue.
        self::$pending_rules = array();

        // Note: We keep $packages and $namespace_registry intact.
        // This allows packages to be re-loaded without re-registration.
    }

    /**
     * Perform complete reset of PackageManager state.
     *
     * This method clears EVERYTHING:
     * - All registered rules (via clear() on packages)
     * - All loaded packages
     * - All registered packages
     * - All namespace mappings
     * - Namespace lookup cache
     * - Pending rules queue
     *
     * After calling reset(), the PackageManager is in a completely fresh state
     * as if it was never initialized. You'll need to register and load packages again.
     *
     * Difference from clear():
     * - clear(): Keeps packages registered, only clears rules and loaded state
     * - reset(): Removes everything, complete fresh start
     *
     * Use this for tests that need complete isolation or when you want to
     * reinitialize the entire package system from scratch.
     *
     * @since 0.1.0
     *
     * @return void
     */
    public static function reset(): void
    {
        // First, clear all rules from packages.
        foreach (self::$packages as $package) {
            if (method_exists($package, 'clear')) {
                $package->clear();
            }
        }

        // Now clear all static arrays.
        self::$packages           = array();
        self::$loaded_packages    = array();
        self::$namespace_registry = array();
        self::$namespace_cache    = array();
        self::$pending_rules      = array();
    }

    /**
     * Check if PackageManager has been initialized with packages.
     *
     * Returns true if at least one package has been registered.
     * Useful for debugging and conditional initialization logic.
     *
     * @since 0.1.0
     *
     * @return bool True if packages are registered, false otherwise.
     */
    public static function is_initialized(): bool
    {
        return ! empty(self::$packages);
    }
}
