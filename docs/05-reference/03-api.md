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
$packages = MilliRules::get_loaded_packages();
// ['PHP', 'WP']
```

---

##### `build_context(): array`

Build context from all loaded packages.

**Returns**: `array` - Aggregated context data

**Example**:
```php
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

> [!NOTE]
> **Naming Convention**: All fluent API methods can be called in either `snake_case` or `camelCase`. For example, `->when_all()` and `->whenAll()` are equivalent, as are `->set_conditions()` and `->setConditions()`. This applies to all builder methods on `Rules`, `ConditionBuilder`, and `ActionBuilder`. The documentation uses `snake_case` throughout, but use whichever style fits your project.

#### Methods

##### `create(string $id, ?string $type = null): Rules`

Create a new rule.

**Parameters**:
- `$id` (string): Unique rule identifier
- `$type` (string|null): Rule type (`'php'` or `'wp'`), auto-detected if null

**Returns**: `Rules` - Rule builder instance

**Example**:
```php
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
Rules::create('my_rule')
    ->enabled(false) // Disable rule
    ->register();
```

---

##### `lock(): Rules`

Lock the rule to prevent overwriting or unregistering.

Locked rules cannot be overwritten by another rule with the same ID, nor can they be unregistered. This guards the entire rule — conditions, actions, and metadata — from replacement.

**Returns**: `Rules` - Fluent interface

**Example**:
```php
// Lock a safety-critical rule
Rules::create('no-cache-post')->lock()->order(0)
    ->when_all()->request_method('POST')
    ->then()->set_cache(false)->lock()
    ->register();

// Attempting to overwrite is silently rejected
Rules::create('no-cache-post')  // Same ID — rejected
    ->when_all()
    ->then()->set_cache(true)
    ->register();

// Attempting to unregister is also rejected
Rules::unregister('no-cache-post');  // Returns false
```

**Key Points**:
- Protects the rule definition (conditions + actions + metadata)
- Separate from `ActionBuilder::lock()` which locks action *execution*
- Use both together for maximum protection on core rules

---

##### `when(): ConditionBuilder`

Start building conditions with match_all logic.

**Returns**: `ConditionBuilder` - Condition builder instance

**Example**:
```php
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
Rules::create('my_rule')
    ->when_none()
        ->condition1()
        ->condition2()
    ->then()->custom('action')
    ->register();
```

---

##### `and(): Rules`

Finalize the current condition group and prepare for the next one. Used to chain multiple condition groups with different match types. All groups are combined with AND logic.

**Returns**: `Rules` - Fluent interface (call `when_all()`, `when_any()`, or `when_none()` next)

**Example**:
```php
Rules::create('my_rule')
    ->when_any()
        ->post_type('page')
        ->post_type('post')
    ->and()->when_none()
        ->user_role('subscriber')
    ->then()
        ->custom('action')
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

##### `register_condition(string $type, callable $callback): ConditionMeta`

Register custom condition callback.

Returns a `ConditionMeta` instance for fluent declaration of metadata (label, description, categories, operators, arguments).

**Parameters**:
- `$type` (string): Condition type identifier
- `$callback` (callable): Callback function `function($args, Context $context): bool`

**Returns**: `ConditionMeta` — fluent metadata declaration for the registered condition

**Example**:
```php
// Simple condition (return value can be ignored)
Rules::register_condition('is_weekend', function($args, Context $context) {
    return date('N') >= 6;
});

// With full metadata for UI introspection
Rules::register_condition('is_weekend', function($args, Context $context) {
    return date('N') >= 6;
})
    ->label('Is Weekend')
    ->description('Matches on Saturdays and Sundays.')
    ->categories('date')
    ->operators('=', '!=');
```

For class-based conditions, override `set_meta()` on your `BaseCondition` subclass:

```php
class RequestUrl extends BaseCondition {
    public static function set_meta(ConditionMeta $meta): void
    {
        $meta
            ->label('Request URL')
            ->description('Match the current request URL.')
            ->categories('request')
            ->operators('=', '!=', 'LIKE', 'REGEXP', 'IN', 'NOT IN')
            ->args()
                ->string('value')->label('URL Pattern')->required();
    }
}
```

---

##### `get_condition_meta(string $type): ?ConditionMeta`

Get the full metadata for a registered condition type.

Resolves metadata from either the callback-based registry (populated by `register_condition()`) or the class-based `BaseCondition::set_meta()` method. For class-based conditions, the argument mapping from `BaseCondition::get_argument_mapping()` is automatically included.

Results are cached per type.

**Parameters**:
- `$type` (string): Condition type identifier

**Returns**: `ConditionMeta|null` — metadata for the condition, or `null` if not found

**Example**:
```php
$meta = Rules::get_condition_meta('request_url');
if ($meta) {
    $label     = $meta->get_label();            // 'Request URL'
    $operators = $meta->get_operators();         // ['=', '!=', 'LIKE', ...]
    $mapping   = $meta->get_argument_mapping();  // ['value']
    $args      = $meta->get_arguments();         // array<ArgumentSchema>
    $data      = $meta->to_array();              // For REST/JSON serialization
}
```

---

#### `ConditionMeta` — fluent condition metadata

`ConditionMeta` is the metadata container for condition types. Parallel to `ActionMeta` but with operators instead of scope.

##### Core fields

- `->label(string $label)` — human-readable name
- `->description(string $description)` — help text
- `->categories(string ...$categories)` — one or more UI grouping categories
- `->operators(string ...$operators)` — supported comparison operators (e.g., `'='`, `'!='`, `'LIKE'`, `'IN'`)
- `->argument_mapping(array $mapping)` — how builder args map to config keys (auto-set for class-based conditions)
- `->args()` → `ArgumentsBuilder` — same walking-builder pattern as ActionMeta
- `->extend(string $key, $value)` — plugin-specific metadata bag

##### `->to_array(): array` — wire format

```php
[
    'type'             => string,
    'label'            => string,
    'description'      => string,
    'categories'       => array<int, string>,
    'operators'        => array<int, string>,
    'argument_mapping' => array<int, string>,
    'arguments'        => array<int, array>,
    'extensions'       => array<string, mixed>,
]
```

---

##### `register_action(string $type, callable $callback): ActionMeta`

Register custom action callback.

Returns an `ActionMeta` instance for fluent declaration of action metadata (scope, label, description, category).

**Parameters**:
- `$type` (string): Action type identifier
- `$callback` (callable): Callback function `function($args, Context $context): void`

**Returns**: `ActionMeta` — fluent metadata declaration for the registered action

**Example**:
```php
// Simple action (return value can be ignored)
Rules::register_action('log', function($args, Context $context) {
    error_log($args['value'] ?? '');
});

// Paired actions with shared scope (value-level locking when locked)
Rules::register_action('add_flag', $addCallback)->scope('flag');
Rules::register_action('remove_flag', $removeCallback)->scope('flag');

// With full metadata for UI introspection
Rules::register_action('add_flag', $addCallback)
    ->scope('flag')
    ->label('Add Flag')
    ->description('Tag the response with a flag for bulk invalidation.')
    ->categories('flags')
    ->args()
        ->string(0)->label('Flag')->required();
```

For class-based actions, override two static methods on your `BaseAction` subclass:

- `get_scope()` — returns the lock scope as a plain string. **Must not use framework-specific functions** (e.g., translation) because the engine calls it during rule execution, which may happen during early bootstrap.
- `set_meta(ActionMeta $meta)` — configures consumer-facing metadata (label, description, categories, args). Called only by consumer code like UIs or REST endpoints, which always run after the framework has initialized.

```php
class AddFlag extends BaseAction {
    // Engine-relevant. Called during early bootstrap — plain strings only.
    public static function get_scope(): string
    {
        return 'flag';
    }

    // Consumer-relevant. Called after framework initialization.
    public static function set_meta(ActionMeta $meta): void
    {
        $meta
            ->label('Add Flag')
            ->description('Tag the response with a flag.')
            ->categories('flags');
    }

    public function execute(Context $context): void { /* ... */ }
    public function get_type(): string { return 'add_flag'; }
}
```

**Why this signature**: the engine owns the action type string (from the registration lookup), so it constructs the `ActionMeta` and passes it in. Subclasses can't accidentally set the wrong type or forget to call `parent::set_meta()` — there's no boilerplate to forget.

**Why scope is split from `set_meta()`**: the engine reads scope during rule execution, which may happen during early bootstrap before the application framework has fully initialized. If scope were set inside `set_meta()` alongside framework-dependent calls (e.g., translation functions), the engine couldn't read it safely. The split keeps the hot path runtime-safe.

---

##### `get_action_scope(string $type): string`

Get the lock scope for an action type — **engine hot path, runtime-safe**.

Fast-path accessor that never calls `set_meta()`. Used internally by `RuleEngine::build_lock_key()`. Safe to call during early bootstrap before the application framework has initialized.

**Parameters**:
- `$type` (string): Action type identifier

**Returns**: `string` — the scope identifier, or `''` for unknown or unscoped actions

**Example**:
```php
$scope = Rules::get_action_scope('add_flag');  // 'flag'
$scope = Rules::get_action_scope('set_ttl');   // '' (unscoped)
```

Resolution order:
1. Callback-based: reads from the meta set at registration time (`register_action()->scope()`).
2. Class-based: calls `$class::get_scope()` directly.

Results are cached per type.

---

##### `get_action_meta(string $type): ?ActionMeta`

Get the full metadata for a registered action type.

Resolves metadata from either the callback-based registry (populated by `register_action()`) or the class-based `BaseAction::set_meta()` method. Results are cached per type.

**May require framework functions**: for class-based actions, this calls `set_meta()`, which may use framework-specific functions (e.g., translation). Do NOT call this during early bootstrap before the framework has initialized — use `get_action_scope()` instead if you only need the scope.

**Parameters**:
- `$type` (string): Action type identifier

**Returns**: `ActionMeta|null` — metadata for the action, or `null` if not found

**Example**:
```php
$meta = Rules::get_action_meta('add_flag');
if ($meta) {
    $label       = $meta->get_label();        // 'Add Flag'
    $scope       = $meta->get_scope();        // 'flag' (synced from $class::get_scope())
    $categories  = $meta->get_categories();    // ['flags']
    $arguments   = $meta->get_arguments();    // array<ArgumentSchema>
    $icon        = $meta->get_extension('millicache:icon'); // plugin-specific
    $data        = $meta->to_array();         // For REST/JSON serialization
}
```

---

#### `ActionMeta` — fluent action metadata

`ActionMeta` is the declarative metadata container for an action type. Obtain it from `Rules::register_action()` (for callback-based actions) or override `BaseAction::set_meta()` (for class-based actions).

##### Core fields

- `->scope(string $scope)` — lock scope (engine-relevant; see [Scoped Locking](../02-core-concepts/01-concepts.md#scoped-locking-for-paired-actions))
- `->label(string $label)` — human-readable name
- `->description(string $description)` — help text
- `->categories(string ...$categories)` — one or more UI grouping categories

##### `->args(): ArgumentsBuilder`

Enter the arguments declaration context. Returns an internal `ArgumentsBuilder` instance that collects argument schemas via type factories. The builder is cached — calling `args()` multiple times returns the same instance.

```php
$meta->args()
    ->integer('ttl')->format('seconds')->default(3600)->min(0)
    ->string('reason')->default('');
```

Inside the builder, each type factory (`->integer($key)`, `->string($key)`, etc.) creates a new `ArgumentSchema` and returns it for continued configuration. To declare another argument, just call another type factory — it "walks" back to the builder and starts a new one.

Preserves declaration order (no auto-sorting). Any meta-level methods called after `->args()` (like `->extend()` or `->categories()`) are automatically forwarded back to the `ActionMeta` via `__call()`, so the chain can continue seamlessly:

```php
$meta
    ->label(__('Set TTL'))
    ->args()
        ->integer('ttl')->default(3600)
        ->string('reason')->default('')
    ->extend('millicache:icon', 'clock');  // forwarded to $meta
```

See [ArgumentSchema](#argumentschema--argument-metadata) below for the per-argument API.

##### `->extend(string $key, mixed $value): self`

Attach plugin-specific metadata under a namespaced key. MilliRules stores the value but never interprets it. Use this for anything that isn't part of MilliRules core: icons, conditional visibility rules, documentation URLs, plugin-defined widgets.

```php
->extend('millicache:icon', 'clock')
->extend('seo-redirects:default_status', 301)
->extend('docs:url', 'https://example.com/actions/set-ttl')
```

**Namespacing convention**: use `plugin-slug:field-name` to avoid collisions. MilliRules does not enforce this — the convention is the contract.

##### Extension bag getters

- `->get_extension(string $key): mixed|null` — returns the value, or `null` if not set
- `->has_extension(string $key): bool` — distinguishes "set to null" from "not set"
- `->get_extensions(): array<string, mixed>` — returns the full keyed bag

##### `->to_array(): array` — wire format

```php
[
    'type'        => string,
    'scope'       => string,
    'label'       => string,
    'description' => string,
    'categories'  => array<int, string>,
    'arguments'   => array<int, array>,    // each via ArgumentSchema::to_array()
    'extensions'  => array<string, mixed>, // plugin-specific bag
]
```

This is the stable, REST-serializable format for transmitting action metadata to consumers.

---

#### `ArgumentSchema` — argument metadata

`ArgumentSchema` is the declarative format for action arguments. Consumer code never references this class directly — schemas are obtained via `$meta->args()->type($key)`. The class is internal but documented here so you understand what your `->args()` chain is producing.

Every consumer that introspects actions (UIs, CLIs, docs generators, validators) reads the same schema. MilliRules' `RuleEngine` does **not** use `ArgumentSchema` at runtime — it's purely metadata.

##### Type system

Six core types cover all engine-level data shapes:

| Type       | Coercion          | min/max semantics | Default    |
|------------|-------------------|-------------------|------------|
| `string`   | `(string)` cast   | length bounds     | `''`       |
| `integer`  | `(int)` cast      | value bounds      | `0`        |
| `number`   | `(float)` cast    | value bounds      | `0.0`      |
| `boolean`  | truthy check      | —                 | `false`    |
| `choice`   | pass-through      | —                 | first option or `null` |
| `choices`  | `(array)` + filter to valid options | — | `[]`       |

Everything else (`url`, `email`, `seconds`, `regex`, `date`, etc.) is expressible as a core type + `format`:

```php
$meta->args()
    ->integer('ttl')->format('seconds')     // TTL input
    ->string('homepage')->format('url')     // URL field
    ->string('contact')->format('email')    // email field
    ->string('pattern')->format('regex');   // regex pattern
```

MilliRules stores `format` but never interprets it. Consumers pick their own vocabulary and handle format-specific rendering/validation.

##### Creating schemas

Schemas are created exclusively via the builder's type factories, obtained from `$meta->args()`:

```php
$meta->args()
    ->string($key)      // $key is int|string
    ->integer($key)
    ->number($key)
    ->boolean($key)
    ->choice($key)
    ->choices($key);
```

You never write `new ArgumentSchema(...)` yourself.

##### Walking: chain to the next argument

Type factories are also available **on an existing schema** and delegate back to the builder to start a new argument:

```php
$meta->args()
    ->integer('ttl')->default(3600)      // schema for 'ttl'
    ->string('reason')->default('');     // ->string() walks back; new schema for 'reason'
```

This is why you can chain multiple arguments in a single fluent expression without restarting from `$meta->args()`.

##### Fluent setters (config)

- `->format(string $format)` — consumer-defined format hint
- `->label(string $label)` — human-readable name
- `->description(string $description)` — help text
- `->required(bool $required = true)` — mark as mandatory
- `->default(mixed $value)` — default value (rejects closures)
- `->min(int $min)` / `->max(int $max)` — length (string) or value (integer/number) bounds; throws on other types
- `->options(array $options)` — allowed values for `choice`/`choices` types; throws on other types

##### `options()` format

Accepts either simple or structured form:

```php
// Simple — value == label
->options(['GET', 'POST', 'PUT'])

// Structured — separate value and label
->options([
    ['value' => 'get',  'label' => 'GET Request'],
    ['value' => 'post', 'label' => 'POST Request'],
])
```

Stored internally as the structured form. `to_array()` always emits the structured form.

##### Runtime guards

Calling incompatible setters throws `InvalidArgumentException` at declaration time (i.e., at class-load time for class-based actions):

```php
$meta->args()->string('k')->min(5);              // OK: length bound
$meta->args()->boolean('k')->min(5);             // ✗ throws
$meta->args()->integer('k')->options(['a', 'b']); // ✗ throws
```

##### Getters

- `->get_key(): int|string`
- `->get_type(): string`
- `->get_format(): string`
- `->get_label(): string`
- `->get_description(): string`
- `->get_default(): mixed`
- `->has_default(): bool` — distinguishes "default is null" from "no default set"
- `->is_required(): bool`
- `->get_min(): ?int`
- `->get_max(): ?int`
- `->get_options(): array`

##### `validate(mixed $value): ?string`

Consumer utility. Returns `null` if the value is valid, or a plain English error message string if invalid. MilliRules ships no translation layer — consumers wrap the returned string in their own i18n if needed.

```php
// Retrieve schemas via the meta's get_arguments():
$schemas = Rules::get_action_meta('set_ttl')->get_arguments();
$schema  = $schemas[0];

$schema->validate(50);        // null (valid)
$schema->validate(150);       // "Argument 'ttl' must be at most 100"
$schema->validate('abc');     // "Argument 'ttl' must be an integer"
```

Note: `RuleEngine` does not call `validate()`. It's an opt-in utility for consumers (validators, UIs, CLIs).

##### `sanitize(mixed $value): mixed`

Consumer utility. Coerces a raw value to the declared type.

```php
$integer_schema->sanitize('3600');   // 3600
$boolean_schema->sanitize('yes');    // true
$choices_schema->sanitize(['a', 'invalid', 'b']);  // ['a', 'b']
```

Null values are replaced with the default if set, otherwise the type's zero value (`''`, `0`, `0.0`, `false`, first option, or `[]`).

##### `to_array(): array` — wire format

```php
[
    'key'         => int|string,
    'type'        => string,
    'format'      => string,
    'label'       => string,
    'description' => string,
    'default'     => mixed,
    'has_default' => bool,
    'required'    => bool,
    'min'         => int|null,
    'max'         => int|null,
    'options'     => array<int, array{value: mixed, label: string}>,
]
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
->then()
    ->custom('log', ['value' => 'message'])
    ->custom('send_email', ['to' => 'admin@example.com'])
```

---

##### `lock(): ActionBuilder`

Mark the last action as locked.

Locked actions prevent subsequent actions from changing the same setting. How locking works depends on whether the action was registered with a **scope**:

- **Unscoped actions** (default): locks by action type — `set_ttl(300)->lock()` blocks all `set_ttl` calls
- **Scoped actions**: locks by scope + value — `add_flag('x')->lock()` only blocks operations on `'x'` within the same scope

**Returns**: `ActionBuilder`

**Example — unscoped (type-level locking)**:
```php
// Rule 1 (order: 10) - Disable cache for logged-in users
Rules::create('no-cache-logged-in')->order(10)
    ->when()->is_user_logged_in()
    ->then()->do_cache(false)->lock()  // Lock the cache setting
    ->register();

// Rule 2 (order: 20) - This cache action will be IGNORED
Rules::create('cache-api')->order(20)
    ->when()->request_url('/api/*')
    ->then()->do_cache(true)  // Blocked - do_cache is locked
    ->register();
```

**Example — scoped (value-level locking)**:
```php
// Consumer registers paired actions with shared scope
Rules::register_action('add_flag', $callback)->scope('flag');
Rules::register_action('remove_flag', $callback)->scope('flag');

// Lock a specific flag value
->then()->add_flag('system-flag')->lock()  // Locks 'flag:system-flag'

// Later rules:
->then()->add_flag('custom-flag')          // Allowed — different lock key
->then()->remove_flag('system-flag')       // Blocked — same lock key
```

**Key Points**:
- Unscoped: locks are per action type
- Scoped: locks are per scope + value (cross-type within the same scope)
- Different action types/scopes can still execute
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
