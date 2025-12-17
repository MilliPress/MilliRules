---
title: 'Complete API Reference'
post_excerpt: 'Comprehensive API reference for all MilliRules classes, methods, interfaces, and functions with parameters, return types, and usage examples.'
menu_order: 30
---

# Complete API Reference

This comprehensive API reference documents all public classes, methods, interfaces, and functions in MilliRules.

## Table of Contents

- [Core Classes](#core-classes)
- [Builders](#builders)
- [Interfaces](#interfaces)
- [Base Classes](#base-classes)

---

## Core Classes

### MilliRules

Main entry point for initializing and executing rules.

**Namespace**: `MilliRules`

#### Methods

##### `init(?array $package_names = null, ?array $packages = null): array`

Initialize MilliRules and load packages.

**Parameters**:
- `$package_names` (array|null): Array of package names to load (null = auto-load all available)
- `$packages` (array|null): Array of PackageInterface instances to register (null = register defaults)

**Returns**: `array` - Array of loaded package names

**Example**:
```php
<?php
// Auto-load available packages
MilliRules::init();

// Load specific packages by name
MilliRules::init(['PHP', 'WP']);

// Load with custom package instances
$custom = new CustomPackage();
MilliRules::init(null, [$custom]);
```

---

##### `execute_rules(?array $allowed_packages = null, array $context = []): array`

Execute all registered rules.

**Parameters**:
- `$allowed_packages` (array|null): Array of package names to use (null = all loaded)
- `$context` (array): Additional context data (merges with auto-built context)

**Returns**: `array` - Execution result with statistics

```php
[
    'rules_processed' => int,   // Total rules evaluated
    'rules_skipped' => int,     // Rules skipped
    'rules_matched' => int,     // Rules with matching conditions
    'actions_executed' => int,  // Actions executed
    'context' => array,         // Execution context
]
```

**Example**:
```php
<?php
// Execute all rules
$result = MilliRules::execute_rules();

// Execute only PHP rules
$result = MilliRules::execute_rules(['PHP']);

// Execute with custom context
$result = MilliRules::execute_rules(null, ['custom_data' => 'value']);
```

---

##### `get_loaded_packages(): array`

Get names of all loaded packages.

**Returns**: `array` - Array of package names

**Example**:
```php
<?php
$packages = MilliRules::get_loaded_packages();
// ['PHP', 'WP']
```

---

##### `build_context(): array`

Build context from all loaded packages.

**Returns**: `array` - Aggregated context data

**Example**:
```php
<?php
$context = MilliRules::build_context();
/*
[
    'request' => [...],
    'wp' => [...],
]
*/
```

---

### Rules

Fluent interface for creating and registering rules.

**Namespace**: `MilliRules`

#### Methods

##### `create(string $id, ?string $type = null): Rules`

Create a new rule.

**Parameters**:
- `$id` (string): Unique rule identifier
- `$type` (string|null): Rule type (`'php'` or `'wp'`), auto-detected if null

**Returns**: `Rules` - Rule builder instance

**Example**:
```php
<?php
$rule = Rules::create('my_rule');
$rule = Rules::create('wp_rule', 'wp');
```

---

##### `title(string $title): Rules`

Set rule title.

**Parameters**:
- `$title` (string): Human-readable title

**Returns**: `Rules` - Fluent interface

**Example**:
```php
<?php
Rules::create('my_rule')
    ->title('My Custom Rule')
    ->register();
```

---

##### `order(int $order): Rules`

Set execution order.

**Parameters**:
- `$order` (int): Order value (lower = executes first)

**Returns**: `Rules` - Fluent interface

**Example**:
```php
<?php
Rules::create('my_rule')
    ->order(10) // Execute at priority 10
    ->register();
```

---

##### `enabled(bool $enabled): Rules`

Enable or disable rule.

**Parameters**:
- `$enabled` (bool): Whether rule is enabled

**Returns**: `Rules` - Fluent interface

**Example**:
```php
<?php
Rules::create('my_rule')
    ->enabled(false) // Disable rule
    ->register();
```

---

##### `when(): ConditionBuilder`

Start building conditions with match_all logic.

**Returns**: `ConditionBuilder` - Condition builder instance

**Example**:
```php
<?php
Rules::create('my_rule')
    ->when()
        ->request_url('/api/*')
        ->request_method('GET')
    ->then()
        ->custom('action')
    ->register();
```

---

##### `when_all(): ConditionBuilder`

Start building conditions with ALL logic (AND).

**Returns**: `ConditionBuilder`

**Example**:
```php
<?php
Rules::create('my_rule')
    ->when_all()
        ->condition1()
        ->condition2()
    ->then()->custom('action')
    ->register();
```

---

##### `when_any(): ConditionBuilder`

Start building conditions with ANY logic (OR).

**Returns**: `ConditionBuilder`

**Example**:
```php
<?php
Rules::create('my_rule')
    ->when_any()
        ->condition1()
        ->condition2()
    ->then()->custom('action')
    ->register();
```

---

##### `when_none(): ConditionBuilder`

Start building conditions with NONE logic (NOT).

**Returns**: `ConditionBuilder`

**Example**:
```php
<?php
Rules::create('my_rule')
    ->when_none()
        ->condition1()
        ->condition2()
    ->then()->custom('action')
    ->register();
```

---

##### `then(?array $actions = null): ActionBuilder`

Start building actions.

**Parameters**:
- `$actions` (array|null): Array of action configurations (optional)

**Returns**: `ActionBuilder` - Action builder instance

**Example**:
```php
<?php
Rules::create('my_rule')
    ->when()->request_url('*')
    ->then()
        ->custom('action1')
        ->custom('action2')
    ->register();
```

---

##### `on(string $hook, int $priority = 10): Rules`

Register rule on WordPress hook.

**Parameters**:
- `$hook` (string): WordPress hook name
- `$priority` (int): Hook priority

**Returns**: `Rules` - Fluent interface

**Example**:
```php
<?php
Rules::create('my_rule', 'wp')
    ->on('init', 10)
    ->when()->is_user_logged_in()
    ->then()->custom('action')
    ->register();
```

---

##### `register(): void`

Register rule with MilliRules.

If a rule with the same ID already exists, it will be **replaced**.

**Returns**: `void`

**Example**:
```php
<?php
Rules::create('my_rule')
    ->when()->request_url('*')
    ->then()->custom('action')
    ->register(); // Must call to activate rule

// Registering again with same ID replaces the rule
Rules::create('my_rule')
    ->when()->request_url('/api/*')
    ->then()->custom('different_action')
    ->register(); // Replaces previous 'my_rule'
```

---

##### `unregister(string $rule_id): bool`

Remove a rule by its ID.

**Parameters**:
- `$rule_id` (string): The ID of the rule to remove

**Returns**: `bool` - True if rule was found and removed, false otherwise

**Example**:
```php
<?php
// Remove a rule
$removed = Rules::unregister('my_rule');

// Check if removal was successful
if ($removed) {
    error_log('Rule was removed');
} else {
    error_log('Rule not found');
}

// Use case: Child theme disabling parent rule
Rules::unregister('parent_theme_cache_rule');

// Use case: Environment-specific disabling
if (wp_get_environment_type() === 'production') {
    Rules::unregister('debug_logging_rule');
}
```

---

##### `register_condition(string $type, callable $callback): void`

Register custom condition callback.

**Parameters**:
- `$type` (string): Condition type identifier
- `$callback` (callable): Callback function `function($args, Context $context): bool`

**Returns**: `void`

**Example**:
```php
<?php
Rules::register_condition('is_weekend', function(Context $context) {
    return date('N') >= 6;
});
```

---

##### `register_action(string $type, callable $callback): void`

Register custom action callback.

**Parameters**:
- `$type` (string): Action type identifier
- `$callback` (callable): Callback function `function($args, Context $context): void`

**Returns**: `void`

**Example**:
```php
<?php
Rules::register_action('log', function($args, Context $context) {
    error_log($args['value'] ?? '');
});
```

---

##### `register_placeholder(string $category, callable $resolver): void`

Register custom placeholder resolver.

**Parameters**:
- `$category` (string): Placeholder category
- `$resolver` (callable): Resolver function `function($context, $parts): string`

**Returns**: `void`

**Example**:
```php
<?php
Rules::register_placeholder('custom', function($context, $parts) {
    return $context['custom'][$parts[0]] ?? '';
});
```

---

## Builders

### ConditionBuilder

Fluent builder for rule conditions.

**Namespace**: `MilliRules`

#### Methods

##### `match_all(): ConditionBuilder`

Use AND logic for conditions.

**Returns**: `ConditionBuilder`

---

##### `match_any(): ConditionBuilder`

Use OR logic for conditions.

**Returns**: `ConditionBuilder`

---

##### `match_none(): ConditionBuilder`

Use NOT logic for conditions.

**Returns**: `ConditionBuilder`

---

##### `custom(string $type, $arg = null): ConditionBuilder`

Add custom condition.

**Parameters**:
- `$type` (string): Condition type
- `$arg` (mixed): Condition argument (value, config array, etc.)

**Returns**: `ConditionBuilder`

**Example**:
```php
<?php
->when()
    ->custom('is_weekend')
    ->custom('time_range', ['start' => 9, 'end' => 17])
```

---

##### `add_namespace(string $namespace): ConditionBuilder`

Add condition namespace for class resolution.

**Parameters**:
- `$namespace` (string): Fully qualified namespace

**Returns**: `ConditionBuilder`

---

##### `__call(string $method, array $args): mixed`

Magic method for dynamic condition creation.

Converts method calls to condition types:
- `request_url()` → `RequestUrlCondition`
- `is_user_logged_in()` → `IsUserLoggedInCondition`

---

### ActionBuilder

Fluent builder for rule actions.

**Namespace**: `MilliRules`

#### Methods

##### `custom(string $type, $arg = null): ActionBuilder`

Add custom action.

**Parameters**:
- `$type` (string): Action type
- `$arg` (mixed): Action argument (config array, value, etc.)

**Returns**: `ActionBuilder`

**Example**:
```php
<?php
->then()
    ->custom('log', ['value' => 'message'])
    ->custom('send_email', ['to' => 'admin@example.com'])
```

---

##### `lock(): ActionBuilder`

Mark the last action as locked.

Locked actions prevent subsequent actions **of the same type** from executing in later rules. The first matching locked action wins. This is useful for preventing cache TTL values or security settings from being overridden by lower-priority rules.

**Returns**: `ActionBuilder`

**Example**:
```php
<?php
// Rule 1 (order: 10) - Disable cache for logged-in users
Rules::create('no-cache-logged-in')->order(10)
    ->when()->is_user_logged_in()
    ->then()->do_cache(false)->lock()  // Lock the cache setting
    ->register();

// Rule 2 (order: 20) - This cache action will be IGNORED
Rules::create('cache-api')->order(20)
    ->when()->request_url('/api/*')
    ->then()->do_cache(true)  // Blocked - cache already locked
    ->register();

// Lock multiple actions independently
->then()
    ->do_cache(false)->lock()           // Locks do_cache
    ->do_log('User logged in')          // Not locked, still executes
    ->do_set_header('X-Cache', 'MISS')  // Not locked, still executes
```

**Key Points**:
- Locks are per action type, not per rule
- Only affects actions of the same type
- Different action types can still execute
- Lock only applies if the rule's conditions match
- Locks reset on each rule execution

---

##### `add_namespace(string $namespace): ActionBuilder`

Add action namespace for class resolution.

**Parameters**:
- `$namespace` (string): Fully qualified namespace

**Returns**: `ActionBuilder`

---

##### `__call(string $method, array $args): mixed`

Magic method for dynamic action creation.

---

## Interfaces

### PackageInterface

Interface for all packages.

**Namespace**: `MilliRules\Interfaces`

#### Methods

##### `get_name(): string`

Get unique package name.

**Returns**: `string` - Package name

---

##### `get_namespaces(): array`

Get condition and action namespaces.

**Returns**: `array` - Array of namespace strings

---

##### `is_available(): bool`

Check if package is available in current environment.

**Returns**: `bool` - True if available

---

##### `get_required_packages(): array`

Get required package names.

**Returns**: `array` - Array of package names

---

##### `build_context(): array`

Build context data for this package.

**Returns**: `array` - Context data

---

##### `get_placeholder_resolver(array $context)`

Get placeholder resolver for this package.

**Parameters**:
- `$context` (array): Execution context

**Returns**: `callable|null` - Resolver function or null

---

##### `register_rule(array $rule, array $metadata): void`

Register rule with package.

**Parameters**:
- `$rule` (array): Rule configuration
- `$metadata` (array): Rule metadata

**Returns**: `void`

---

##### `execute_rules(array $rules, array $context): array`

Execute rules for this package.

**Parameters**:
- `$rules` (array): Rules to execute
- `$context` (array): Execution context

**Returns**: `array` - Execution result

---

### ConditionInterface

Interface for all conditions.

**Namespace**: `MilliRules\Interfaces`

#### Methods

##### `matches(array $context): bool`

Check if condition matches.

**Parameters**:
- `$context` (array): Execution context

**Returns**: `bool` - True if matches

---

##### `get_type(): string`

Get condition type identifier.

**Returns**: `string` - Condition type

---

### ActionInterface

Interface for all actions.

**Namespace**: `MilliRules\Interfaces`

#### Methods

##### `execute(Context $context): void`

Execute action.

**Parameters**:
- `$context` (array): Execution context

**Returns**: `void`

---

##### `get_type(): string`

Get action type identifier.

**Returns**: `string` - Action type

---

## Base Classes

### BasePackage

Abstract base class for packages.

**Namespace**: `MilliRules\Packages`

Provides default implementations for most `PackageInterface` methods.

**Must Override**:
- `get_name(): string`
- `get_namespaces(): array`
- `is_available(): bool`

**Can Override**:
- `get_required_packages(): array` - Defaults to `[]`
- `build_context(): array` - Defaults to `[]`
- `get_placeholder_resolver()` - Defaults to `null`

---

### BaseCondition

Abstract base class for conditions.

**Namespace**: `MilliRules\Conditions`

Provides operator support and comparison logic.

#### Methods

##### `__construct(array $config, Context $context)`

Constructor.

**Parameters**:
- `$config` (array): Condition configuration
- `$context` (array): Execution context

---

##### `matches(array $context): bool`

Check if condition matches (implemented).

**Parameters**:
- `$context` (array): Execution context

**Returns**: `bool`

---

##### `abstract protected function get_actual_value(Context $context)`

Get actual value to compare (must implement).

**Parameters**:
- `$context` (array): Execution context

**Returns**: `mixed` - Actual value

---

##### `static public function compare_values($actual, $expected, string $operator = '='): bool`

Compare values using operator.

**Parameters**:
- `$actual` (mixed): Actual value
- `$expected` (mixed): Expected value
- `$operator` (string): Comparison operator

**Returns**: `bool` - True if comparison matches

---

### BaseAction

Abstract base class for actions.

**Namespace**: `MilliRules\Actions`

Provides placeholder resolution.

#### Methods

##### `__construct(array $config, Context $context)`

Constructor.

**Parameters**:
- `$config` (array): Action configuration
- `$context` (array): Execution context

---

##### `protected function resolve_value(string $value): string`

Resolve placeholders in value.

**Parameters**:
- `$value` (string): Value with placeholders

**Returns**: `string` - Resolved value

---

##### `abstract public function execute(Context $context): void`

Execute action (must implement).

**Parameters**:
- `$context` (array): Execution context

**Returns**: `void`

---

## PackageManager

Static manager for packages.

**Namespace**: `MilliRules`

#### Methods

##### `static register_package(PackageInterface $package): void`

Register package.

**Parameters**:
- `$package` (PackageInterface): Package instance

**Returns**: `void`

---

##### `static load_packages(?array $package_names = null): array`

Load packages by name.

**Parameters**:
- `$package_names` (array|null): Package names (null = load all available)

**Returns**: `array` - Loaded package names

---

##### `static get_loaded_packages(): array`

Get loaded package instances.

**Returns**: `array` - Array of PackageInterface instances

---

##### `static get_loaded_package_names(): array`

Get loaded package names.

**Returns**: `array` - Array of package names

---

##### `static get_package(string $name): ?PackageInterface`

Get package by name.

**Parameters**:
- `$name` (string): Package name

**Returns**: `PackageInterface|null` - Package instance or null

---

##### `static is_package_loaded(string $name): bool`

Check if package is loaded.

**Parameters**:
- `$name` (string): Package name

**Returns**: `bool` - True if loaded

---

##### `static has_packages(): bool`

Check if any packages are loaded.

**Returns**: `bool` - True if packages exist

---

##### `static build_context(): array`

Build context from all loaded packages.

**Returns**: `array` - Aggregated context

---

## RuleEngine

Rule execution engine.

**Namespace**: `MilliRules`

#### Methods

##### `execute(array $rules, array $context, ?array $allowed_packages = null): array`

Execute rules.

**Parameters**:
- `$rules` (array): Rules to execute
- `$context` (array): Execution context
- `$allowed_packages` (array|null): Allowed package names

**Returns**: `array` - Execution result

---

##### `static register_namespace(string $type, string $namespace): void`

Register namespace for class resolution.

**Parameters**:
- `$type` (string): Type (`'condition'` or `'action'`)
- `$namespace` (string): Namespace string

**Returns**: `void`

---

## Next Steps

- **[Real-World Examples](../04-advanced/01-examples.md)** - See API usage in complete examples
- **[Getting Started](../01-getting-started/01-introduction.md)** - Basic usage guide
- **[Building Rules](../02-core-concepts/03-building-rules.md)** - Fluent API guide

---

**Ready for complete examples?** Continue to [Real-World Examples](../04-advanced/01-examples.md) to see the API in action with full working code.
