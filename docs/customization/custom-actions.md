---
title: 'Creating Custom Actions'
post_excerpt: 'Learn how to create custom actions in MilliRules using callback functions or ActionInterface classes for flexible, reusable functionality.'
---

# Creating Custom Actions

Custom actions implement the actual business logic when rules match. This guide covers registering and using custom actions.

## Quick Start

### Using Registered Actions

Once registered, actions can be used via **dynamic method calls**:

```php
<?php
Rules::create('notify_admin')
    ->when()->request_url('/important/*')
    ->then()
        ->send_email('admin@example.com')        // Dynamic method
        ->log_event('Important page accessed')   // Dynamic method
    ->register();
```

**How it works:**
- Method names convert from camelCase to snake_case
- `->sendEmail()` becomes `type='send_email'`
- `->logToDatabase()` becomes `type='log_to_database'`
- First argument becomes `'value'` in config
- Second argument becomes `'expire'` in config

### Using custom() Method

For complex configurations, use `->custom()`:

```php
<?php
->then()
    ->custom('send_email', [
        'to' => 'admin@example.com',
        'subject' => 'Notification',
        'message' => 'Event occurred',
        'priority' => 'high'
    ])
```

**When to use which:**
- **Dynamic methods**: Simple actions with 1-2 parameters
- **`->custom()`**: Complex configurations with multiple named parameters

## Registering Actions

### Callback-Based Actions

Quick and simple for straightforward logic:

```php
<?php
use MilliRules\Rules;
use MilliRules\Context;

Rules::register_action('send_email', function(Context $context, $config) {
    $to = $config['to'] ?? $config['value'] ?? '';
    $subject = $config['subject'] ?? 'Notification';
    $message = $config['message'] ?? '';

    if (!$to) {
        error_log('send_email: missing recipient');
        return;
    }

    wp_mail($to, $subject, $message);
});
```

**Callback signature:**
```php
function(Context $context, array $config): void
```

**Config structure:**
- `$config['value']` - Primary value (from dynamic method first argument)
- `$config['expire']` - Expiration (from dynamic method second argument)
- `$config['...']` - Any custom keys when using `->custom()`

### Class-Based Actions

For complex logic, testability, or state management:

```php
<?php
namespace MyPlugin\Actions;

use MilliRules\Actions\ActionInterface;
use MilliRules\Context;

class SendEmailAction implements ActionInterface
{
    private array $config;
    private Context $context;

    public function __construct(array $config, Context $context)
    {
        $this->config = $config;
        $this->context = $context;
    }

    public function execute(Context $context): void
    {
        $to = $this->config['to'] ?? '';
        $subject = $this->config['subject'] ?? 'Notification';

        // Access context data
        $user = $context->get('user.login', 'guest');
        $message = "Hello {$user}, " . ($this->config['message'] ?? '');

        wp_mail($to, $subject, $message);
    }

    public function get_type(): string
    {
        return 'send_email';
    }
}

// Register the class
Rules::register_action('send_email', function($context, $config) {
    return new \MyPlugin\Actions\SendEmailAction($config, $context);
});
```

### Using BaseAction for Placeholders

For actions that need placeholder resolution:

```php
<?php
namespace MyPlugin\Actions;

use MilliRules\Actions\BaseAction;
use MilliRules\Context;

class NotifyAction extends BaseAction
{
    public function execute(Context $context): void
    {
        // Resolve placeholders in config
        $message = $this->resolve_value($this->config['message'] ?? '');
        $to = $this->resolve_value($this->config['to'] ?? '');

        wp_mail($to, 'Notification', $message);
    }

    public function get_type(): string
    {
        return 'notify';
    }
}
```

**Usage with placeholders:**
```php
<?php
Rules::create('notify_on_login')
    ->when()->is_user_logged_in()
    ->then()
        ->custom('notify', [
            'to' => 'admin@example.com',
            'message' => 'User {user:login} logged in from {request:ip}'
        ])
    ->register();
```

## Configuration Reference

### Standard Config Keys

```php
[
    'type' => 'action_type',    // Required: action identifier
    'value' => 'primary_value', // Common: main value (from first dynamic arg)
    'expire' => 3600,           // Common: expiration/duration (from second dynamic arg)
    // ... custom keys as needed
]
```

### Common Patterns

**Simple value:**
```php
->log_message('Event occurred')
// Becomes: ['type' => 'log_message', 'value' => 'Event occurred']
```

**Value with expiration:**
```php
->set_cache('data_key', 3600)
// Becomes: ['type' => 'set_cache', 'value' => 'data_key', 'expire' => 3600]
```

**Complex configuration:**
```php
->custom('send_notification', [
    'to' => ['admin@example.com', 'team@example.com'],
    'template' => 'alert',
    'data' => ['event' => 'login', 'time' => time()]
])
```

## Best Practices

### 1. Keep Actions Focused

```php
// ✅ Good - single responsibility
Rules::register_action('log_event', function($context, $config) {
    error_log($config['value'] ?? '');
});

// ❌ Bad - too many responsibilities
Rules::register_action('do_everything', function($context, $config) {
    // Logs, sends email, updates database, clears cache...
});
```

### 2. Validate Configuration

```php
Rules::register_action('send_email', function($context, $config) {
    if (!isset($config['to']) || !is_email($config['to'])) {
        error_log('send_email: invalid recipient');
        return;
    }

    wp_mail($config['to'], $config['subject'] ?? '', $config['message'] ?? '');
});
```

### 3. Handle Errors Gracefully

```php
Rules::register_action('api_call', function($context, $config) {
    try {
        $response = wp_remote_post($config['url'] ?? '', [
            'body' => $config['data'] ?? []
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

Rules::register_action('my_action', function(Context $context, array $config): void {
    // Full IDE autocomplete and type safety
    $user = $context->get('user.login');
});
```

## Common Pitfalls

### Don't Modify Context Expecting Persistence

```php
// ❌ Wrong - context changes don't persist between rules
Rules::register_action('bad_action', function($context, $config) {
    $context->set('custom_value', 'modified');
    // This change is lost after the action completes!
});

// ✅ Correct - use external state
Rules::register_action('good_action', function($context, $config) {
    update_option('custom_value', 'modified');
    // Or use globals, database, cache, etc.
});
```

### Don't Perform Heavy Operations Without Caching

```php
// ❌ Bad - runs on every execution
Rules::register_action('slow_action', function($context, $config) {
    $data = expensive_api_call();
    process_data($data);
});

// ✅ Good - cache expensive operations
Rules::register_action('cached_action', function($context, $config) {
    $data = get_transient('cached_data');
    if (false === $data) {
        $data = expensive_api_call();
        set_transient('cached_data', $data, HOUR_IN_SECONDS);
    }
    process_data($data);
});
```

## Next Steps

- **[Custom Conditions](custom-conditions.md)** - Create conditional logic
- **[Built-in Actions Reference](../reference/actions.md)** - See available actions
- **[Placeholder System](../reference/placeholders.md)** - Dynamic value resolution
- **[API Reference](../reference/api.md)** - Complete API documentation
