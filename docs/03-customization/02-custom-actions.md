---
title: 'Creating Custom Actions'
post_excerpt: 'Learn how to create custom actions in MilliRules using callback functions or ActionInterface classes for flexible, reusable functionality.'
menu_order: 20
---

# Creating Custom Actions

Custom actions define the "then" and implement the actual business logic when rules match. This guide covers registering and using custom actions.

## Quick Start

```php

use MilliRules\Rules;
use MilliRules\Context;

// 1. Register reusable action
Rules::register_action('alert_slack', function ($config, Context $context) {
    // Reusable logic receiving config + context
    error_log("Slack Alert [{$config['channel']}]: " . ($config['message'] ?? ''));
});

// 2. Create Rule
Rules::create('monitor_admin')
    ->when()
        ->request_url('/wp-admin/*')
        ->user_role('editor')
    ->then()
        // Option A: Inline custom action without registration
        ->custom('log_ip', function (Context $context) {
            error_log("Access from IP: " . $context->get('request.ip'));
        })

        // Option B: Call registered action via magic method
        ->alert_slack(['channel' => '#security', 'message' => 'Editor in admin area'])
        
        // Option C: Call registered action via custom()
        ->custom('alert_slack', ['channel' => '#security', 'message' => '{user.login} accessed admin area'])
    ->register();
```

## Registering Actions

### Choosing the Right Registration Method

MilliRules offers four ways to define custom actions. Choose based on your needs:

| Method                       | Best For                | Reusable?  |
|------------------------------|-------------------------|------------|
| **Inline with `->custom()`** | One-off actions         | ❌ No       |
| **Callback Registration**    | Reusable simple actions | ✅ Yes      |
| **Namespace Registration**   | Multiple action classes | ✅ Yes      |
| **Manual Wrapper**           | Advanced use cases      | ✅ Yes      |

**Recommendation:** Start with inline `->custom()` for one-off actions. Use callback registration for reusable simple actions. Use namespace registration for complex actions with placeholders.

---

### Method 1: Inline with `->custom()` (Simplest - One-Off Actions)

**Best for:** Quick one-off actions that are only used in a single rule.

Define the action directly in the rule using a callback:

```php
use MilliRules\Rules;
use MilliRules\Context;

Rules::create('log_important_access')
    ->when()->request_url('/important/*')
    ->then()
        ->custom('log_access', function(Context $context) {
            // One-off action logic right here
            error_log('Important page accessed: ' . $context->get('request.url'));
        })
    ->register();
```

**Note:** Inline callbacks receive only the `Context` parameter (not `$args`), since arguments are redundant for inline-defined actions. To access context data, use `$context->get('key')`.

**Example with context access:**

```php
Rules::create('send_notification')
    ->when()->user_role('administrator')
    ->then()
        ->custom('notify', function(Context $context) {
            $user = $context->get('user.login');
            $url = $context->get('request.url');

            wp_mail(
                'admin@example.com',
                'Admin Login',
                "User {$user} accessed {$url}"
            );
        })
    ->register();
```

**Pros:**
- ✅ Very simple - no separate registration step
- ✅ Perfect for one-off actions
- ✅ Quick to write and test
- ✅ Clean signature - only receives Context

**Cons:**
- ❌ Not reusable across multiple rules
- ❌ No placeholder support (e.g. `{user.login}`)
- ❌ Harder to test in isolation

---

### Method 2: Callback Registration (Reusable Simple Actions)

**Best for:** Reusable actions across multiple rules, simple logic without placeholders.

Register once, use everywhere:

```php
use MilliRules\Rules;
use MilliRules\Context;

// Register once at plugin initialization
Rules::register_action('send_email', function($args, Context $context) {
    $to = $args['to'] ?? '';
    $subject = $args['subject'] ?? 'Notification';
    $message = $args['message'] ?? '';

    if (!$to) {
        error_log('send_email: missing recipient');
        return;
    }

    wp_mail($to, $subject, $message);
});

// Simple logging action
Rules::register_action('log_message', function($args, Context $context) {
    $message = $args['message'] ?? $args[0] ?? '';
    error_log($message);
});
```

**Then use in any rule:**

```php
Rules::create('log_important_requests')
    ->when()->request_url('/important/*')
    ->then()
        ->log_message(['message' => 'Important page accessed'])
        ->send_email(['to' => 'admin@example.com', 'subject' => 'Important Access'])
    ->register();
```

**Pros:**
- ✅ Reusable across all rules
- ✅ Simple to register and use
- ✅ Good for basic actions

**Cons:**
- ❌ No placeholder support (must implement manually)
- ❌ Harder to organize many actions

---

### Method 3: Namespace Registration (Best for Classes)

Register an entire namespace once and all action classes are auto-discovered:

```php
use MilliRules\Rules;

// One-time registration at plugin initialization
Rules::register_namespace('Actions', 'MyPlugin\Actions');
```

**Create your action class:**

```php
namespace MyPlugin\Actions;

use MilliRules\Actions\BaseAction;
use MilliRules\Context;

class NotifyMail extends BaseAction
{
    public function execute($config, Context $context): void
    {
        // Access arguments via $this->args
        $to = $this->args['to'] ?? '';
        $message = $this->args['message'] ?? '';

        // Resolve placeholders like {user.login}
        $to = $this->resolve_value($to);
        $message = $this->resolve_value($message);

        wp_mail($to, 'Notification', $message);
    }

    public function get_type(): string
    {
        return 'notify_mail';  // Used for auto-discovery
    }
}
```

**How it works:**
- The class name `NotifyMail` is converted to `notify_mail`
- MilliRules finds the class automatically via `get_type()`
- No need to manually register each action
- Supports placeholder resolution via `resolve_value()`
- Access arguments via `$this->args` (numeric and named keys)
- Access action type via `$this->type`

**Usage:**
```php
Rules::create('notify_on_login')
    ->when()->is_user_logged_in()
    ->then()
        // Both calling styles work identically
        ->notify_mail(['to' => 'admin@example.com', 'message' => 'User {user.login} logged in'])
        // OR
        ->custom('notify_mail', ['to' => 'admin@example.com', 'message' => 'User {user.login} logged in'])
    ->register();
```

---

## Accessing Action Arguments

When creating custom action classes (using namespace or manual registration), you need to access the arguments passed to the action. MilliRules provides a fluent `get_arg()` API for type-safe argument access with automatic placeholder resolution.

### The `get_arg()` Method

Access action arguments using the `get_arg()` method in your action classes:

```php
namespace MyPlugin\Actions;

use MilliRules\Actions\BaseAction;
use MilliRules\Context;

class SendEmail extends BaseAction
{
    public function execute(Context $context): void
    {
        // Clean type-safe access with automatic placeholder resolution
        $to = $this->get_arg('to', 'admin@example.com')->string();
        $subject = $this->get_arg('subject', 'Notification')->string();
        $html = $this->get_arg('html', false)->bool();
        $priority = $this->get_arg('priority', 10)->int();

        wp_mail($to, $subject, 'Message content');
    }

    public function get_type(): string
    {
        return 'send_email';
    }
}
```

### Type Conversion Methods

The `get_arg()` method returns an `ArgumentValue` object that provides fluent type conversion:

| Method       | Returns  | Default for null    |
|--------------|----------|---------------------|
| `->string()` | `string` | `''` (empty string) |
| `->bool()`   | `bool`   | `false`             |
| `->int()`    | `int`    | `0`                 |
| `->float()`  | `float`  | `0.0`               |
| `->array()`  | `array`  | `[]` (empty array)  |
| `->raw()`    | `mixed`  | `null`              |

### Automatic Placeholder Resolution

Placeholders like `{user.email}` are automatically resolved when you call any type method:

```php
// Rule definition
Rules::create('welcome_email')
    ->when()->is_user_logged_in()
    ->then()
        ->send_email([
            'to' => '{user.email}',
            'subject' => 'Welcome {user.login}!'
        ])
    ->register();

// In your SendEmail action class
$to = $this->get_arg('to')->string();
// Result: 'john@example.com' (placeholder automatically resolved)

$subject = $this->get_arg('subject')->string();
// Result: 'Welcome john!' (placeholder automatically resolved)
```

### Positional Arguments

Works with both named and positional arguments:

```php
class LogMessage extends BaseAction
{
    public function execute(Context $context): void
    {
        // Called via: ->logMessage('ERROR', 'Something broke', 3)
        $level = $this->get_arg(0, 'info')->string();
        $message = $this->get_arg(1, 'No message')->string();
        $priority = $this->get_arg(2, 1)->int();

        error_log("[{$level}] {$message} (priority: {$priority})");
    }

    public function get_type(): string
    {
        return 'log_message';
    }
}
```

---

### Method 4: Manual Wrapper (Advanced - Rarely Needed)

**Only use when:** Namespace registration isn't suitable (dynamic class names, runtime actions, etc.)

```php
use MilliRules\Rules;

// ⚠️ Avoid this if possible - creates type duplication
Rules::register_action('notify', function($args, Context $context) {
    $action = new \MyPlugin\Actions\NotifyMail($args, $context);
    $action->execute($context);
});
```

**Why to avoid:**
- Type specified twice (in registration AND in `get_type()`)
- More verbose than namespace registration
- Harder to maintain

**When it's necessary:**
- You can't register the entire namespace
- You need to pass custom dependencies to the constructor
- You need conditional registration logic

## Using Registered Actions

Once registered, actions can be used via **dynamic method calls**:

```php
Rules::create('notify_admin')
    ->when()->request_url('/important/*')
    ->then()
        ->send_email(['to' => 'admin@example.com', 'subject' => 'Alert'])   // Dynamic method
        ->log_message(['message' => 'Important page accessed'])             // Dynamic method
    ->register();
```

### Argument Patterns

Both `->action_name()` and `->custom()` accept identical argument formats:

**Named parameters (recommended):**
```php
->send_email(['to' => 'admin@example.com', 'subject' => 'Alert'])
->custom('send_email', ['to' => 'admin@example.com', 'subject' => 'Alert'])
// Both result in: $this->args['to'], $this->args['subject']
```

**Positional array:**
```php
->send_email(['admin@example.com', 'Alert', 'Body'])
->custom('send_email', ['admin@example.com', 'Alert', 'Body'])
// Both result in: $this->args[0], $this->args[1], $this->args[2]
```

**Single value:**
```php
->log_message('Important event occurred')
->custom('log_message', 'Important event occurred')
// Both result in: $this->args[0]
```

**When to use which:**
- **Dynamic methods**: Shorter syntax when method name is the action type
- **`->custom()`**: When action type needs to be dynamic or passed as variable

## Best Practices

### 1. Keep Actions Focused

```php
// ✅ Good - single responsibility
Rules::register_action('log_event', function($args, Context $context) {
    error_log($args[0] ?? '');
});

// ❌ Bad - too many responsibilities
Rules::register_action('do_everything', function($args, Context $context) {
    // Logs, sends email, updates database, clears cache...
});
```

### 2. Validate Configuration

```php
Rules::register_action('send_email', function($args, Context $context) {
    if (!isset($args['to']) || !is_email($args['to'])) {
        error_log('send_email: invalid recipient');
        return;
    }

    wp_mail($args['to'], $args['subject'] ?? '', $args['message'] ?? '');
});
```

### 3. Handle Errors Gracefully

```php
Rules::register_action('api_call', function($args, Context $context) {
    try {
        $response = wp_remote_post($args['url'] ?? '', [
            'body' => $args['data'] ?? []
        ]);

        if (is_wp_error($response)) {
            error_log('API call failed: ' . $response->get_error_message());
            return;
        }
    } catch (\Exception $e) {
        error_log('API call exception: ' . $e->getMessage());
    }
});
```

### 4. Use Type Hints

```php
use MilliRules\Context;

Rules::register_action('my_action', function(array $args, Context $context): void {
    // Full IDE autocomplete and type safety
    $user = $context->get('user.login');
});
```

## Declaring Action Metadata

`Rules::register_action()` returns an `ActionMeta` instance that lets you declare metadata about the action type. The metadata is used for both engine behavior (e.g., scoped locking) and consumer introspection (e.g., UI builders reading labels and descriptions).

### Callback-Based Metadata

Chain metadata methods directly after registration. The returned `ActionMeta` is the same instance stored in the registry, so all chained calls persist:

```php
Rules::register_action('add_flag', $addCallback)
    ->scope('flag')
    ->label('Add Flag')
    ->description('Tag the response with a flag for bulk invalidation.')
    ->categories('flags')
    ->args()
        ->string(0)->label('Flag')->required();
```

### Class-Based Metadata via `set_meta()`

For class-based actions extending `BaseAction`, override two static methods:

- `get_scope()` — returns the lock scope. Called by the engine during rule execution, which may happen during early bootstrap. Must return a plain string — no framework-specific function calls.
- `set_meta()` — configures consumer-facing metadata. Called only when consumers request full metadata, after the framework has initialized.

```php
use MilliRules\Actions\ActionMeta;
use MilliRules\Actions\BaseAction;
use MilliRules\Context;

class AddFlag extends BaseAction
{
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

    public function execute(Context $context): void
    {
        $flag = $this->get_arg(0, '')->string();
        // ... add the flag ...
    }

    public function get_type(): string
    {
        return 'add_flag';
    }
}
```

**Why `set_meta()` takes an `ActionMeta` parameter instead of returning one**: the engine owns the action type string (it knows what to look up), so it constructs the `ActionMeta` with the correct type and passes it in. Subclasses can't forget to call a parent method — there's no parent call to make — and they can't set the wrong type.

**Why scope lives in `get_scope()` instead of `set_meta()`**: the engine reads scope during rule execution (to build lock keys), which may happen during early bootstrap before the application framework has fully initialized. If scope were set inside `set_meta()` alongside framework-dependent calls, the engine couldn't read it safely. Splitting scope into a separate, string-only static method keeps the engine hot path runtime-safe.

### Available Metadata Fields

| Method / Override | Purpose             | Read by         | Where to declare |
| ----------------- | ------------------- | --------------- | ---------------- |
| `get_scope()`     | Lock grouping       | RuleEngine (hot path) | Static method on class |
| `label()`         | Human-readable name | Consumers (UIs) | Inside `set_meta()` |
| `description()`   | Help text           | Consumers (UIs) | Inside `set_meta()` |
| `categories()`    | UI grouping (one or more) | Consumers (UIs) | Inside `set_meta()` |
| `args()`          | Enter arguments context | Consumers (UIs) | Inside `set_meta()` |
| `extend()`        | Plugin-specific bag | Consumers       | Inside `set_meta()` |

- **`get_scope()`** is engine-relevant: the `RuleEngine` calls it directly (not via `set_meta()`) to build value-level lock keys for paired actions (e.g., `add_flag`/`remove_flag`). It must be runtime-safe — no framework-specific function calls — because rules may execute during early bootstrap.
- **`label`, `description`, `categories`, `args`** are stored but never interpreted by MilliRules itself. They live inside `set_meta()`, which is called only when consumers (UI builders, CLIs, docs generators) introspect via `Rules::get_action_meta($type)`.
- **`extend`** is the catch-all for anything plugin-specific that doesn't belong in MilliRules core.

> **Note**: `set_meta()` is called after the application framework has fully initialized. If your framework provides translation functions (e.g., `__()` in WordPress), you can safely use them for labels, descriptions, and argument labels inside `set_meta()`. In contrast, `get_scope()` runs during early bootstrap and must return plain strings only.

### Declaring Arguments

Enter the arguments declaration context with `$meta->args()`. Inside, use type factories (`->integer($key)`, `->string($key)`, etc.) to declare each argument, then chain config setters (`->label()`, `->default()`, etc.) directly on it. To declare another argument, just call another type factory — it "walks" back to the builder and starts a new one.

This mirrors the `->when()`/`->then()` context pattern from rule building.

MilliRules ships a small set of engine-level types (`string`, `integer`, `number`, `boolean`, `choice`, `choices`) plus an open `format` field for consumer-defined UI hints like `'url'`, `'seconds'`, or `'regex'`.

```php
use MilliRules\Actions\ActionMeta;
use MilliRules\Actions\BaseAction;

class SetTtl extends BaseAction
{
    public static function describe(ActionMeta $meta): void
    {
        $meta
            ->label('Set TTL')
            ->description('Set cache time-to-live.')
            ->categories('caching')
            ->args()
                ->integer('ttl')
                    ->format('seconds')  // UI hint: render as duration picker
                    ->label('TTL')
                    ->description('Duration in seconds')
                    ->default(3600)
                    ->min(0)
                    ->max(86400)
                ->string('reason')
                    ->label('Reason')
                    ->default('');
    }

    public function execute(Context $context): void
    {
        $ttl    = $this->get_arg('ttl', 3600)->int();
        $reason = $this->get_arg('reason', '')->string();
        // ...
    }

    public function get_type(): string { return 'set_ttl'; }
}
```

Key points:

- **No class name imports for arguments**. You never write `ArgumentSchema` or `ArgumentsBuilder` in your own code — they're internal. You only chain methods starting from `$meta->args()`.
- **Type is chosen by the factory method name** (`->integer($key)`, `->string($key)`, etc.). It's fixed once the argument is created.
- **Runtime guards catch misuse immediately**. Calling `->min()` on a string schema is fine (it's a length bound), but calling `->min()` on a boolean throws `InvalidArgumentException` at class-load time.
- **`default()` rejects closures** because schemas must be JSON-serializable. Pass scalars or arrays.
- **Declaration order is preserved** — the order you declare arguments is the order consumers receive them.
- **`options()` for choice/choices** — use `->options([...])` to declare the allowed values for `->choice($key)` or `->choices($key)` arguments. Accepts either simple form `['a', 'b']` or structured `[['value' => 'a', 'label' => 'A']]`.
- **Consumer utilities are available**: `$schema->validate($value)` returns a plain English error or null; `$schema->sanitize($value)` coerces raw input to the declared type. MilliRules' `RuleEngine` does not call these — they're opt-in for consumers (validators, UIs, CLIs) that want to share coercion logic.
- **Meta methods called after `->args()` are auto-forwarded** — you can continue chaining `->extend()`, `->categories()`, or any other `ActionMeta` method after declaring arguments. The chain routes through the argument schema's `__call()` back to the parent meta. No `->end()` or "put args() last" ceremony needed.

#### Choice and choices example

```php
$meta
    ->label('Cache Mode')
    ->args()
        ->choice('strategy')
            ->options(['eager', 'lazy', 'off'])
            ->default('lazy')
            ->label('Caching Strategy')
        ->choices('vary_by')
            ->options(['user', 'locale', 'device'])
            ->default(['user'])
            ->label('Vary By');
```

See the [ArgumentSchema API reference](../05-reference/03-api.md#argumentschema--argument-metadata) for the full API.

### Plugin-Specific Metadata via `extend()`

Anything that doesn't belong in MilliRules core — icons, conditional visibility rules, documentation URLs, plugin-defined widgets — can be attached to `ActionMeta` via the extension bag:

```php
public static function describe(ActionMeta $meta): void
{
    $meta
        ->label('Set TTL')
        ->categories('caching')
        // Plugin-specific metadata: MilliRules stores these but never reads them.
        ->extend('my-plugin:icon', 'clock')
        ->extend('my-plugin:docs_url', 'https://example.com/actions/set-ttl')
        ->extend('my-plugin:requires_addon', 'pro');
}
```

**Namespacing convention**: prefix your keys with your plugin slug and a colon (`my-plugin:field-name`) to avoid collisions with other consumers. MilliRules does not enforce this — the convention is the contract between consumers.

Consumers read extensions via:

```php
$meta = Rules::get_action_meta('set_ttl');
$icon = $meta?->get_extension('my-plugin:icon');         // 'clock'
$has  = $meta?->has_extension('my-plugin:icon');         // true
$all  = $meta?->get_extensions();                        // full keyed bag
```

Use `arguments()` for structured data that every consumer needs (argument metadata), and `extend()` for data that only specific consumers care about.

### Introspecting Action Metadata

Consumers can query metadata for any registered action type:

```php
$meta = Rules::get_action_meta('add_flag');
if ($meta) {
    echo $meta->get_label();              // 'Add Flag'
    $cats = $meta->get_categories();      // ['flags']
    $args = $meta->get_arguments();       // array<ArgumentSchema>
    $icon = $meta->get_extension('my-plugin:icon');
    $data = $meta->to_array();            // Serializable array for REST
}
```

This works for both callback-based and class-based actions uniformly.

## Common Pitfalls

### Don't Modify Context Expecting Persistence

```php
// ❌ Wrong - context changes don't persist between rules
Rules::register_action('bad_action', function($args, Context $context) {
    $context->set('custom_value', 'modified');
    // This change is lost after the action completes!
});

// ✅ Correct - use external state
Rules::register_action('good_action', function($args, Context $context) {
    update_option('custom_value', 'modified');
    // Or use globals, database, cache, etc.
});
```

### Don't Perform Heavy Operations Without Caching

```php
// ❌ Bad - runs on every execution
Rules::register_action('slow_action', function($args, Context $context) {
    $data = expensive_api_call();
    process_data($data);
});

// ✅ Good - cache expensive operations
Rules::register_action('cached_action', function($args, Context $context) {
    $data = get_transient('cached_data');
    if (false === $data) {
        $data = expensive_api_call();
        set_transient('cached_data', $data, HOUR_IN_SECONDS);
    }
    process_data($data);
});
```

## Next Steps

- **[Custom Conditions](./01-custom-conditions.md)** - Create conditional logic
- **[Built-in Actions Reference](../05-reference/02-actions.md)** - See available actions
- **[Placeholder System](../02-core-concepts/05-placeholders.md)** - Dynamic value resolution
- **[API Reference](../05-reference/03-api.md)** - Complete API documentation
