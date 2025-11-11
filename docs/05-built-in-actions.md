---
post_title: 'Built-in Actions Reference'
post_excerpt: 'Learn about MilliRules action system, including custom callback actions, class-based actions, and creating reusable action patterns.'
taxonomy:
  category:
    - documentation
    - reference
  post_tag:
    - actions
    - callbacks
    - triggers
    - custom-actions
menu_order: 5
---

# Built-in Actions Reference

Actions are the "then" part of your rules—they define what happens when conditions are met. Unlike conditions, MilliRules' action system is primarily designed around **custom actions** that you define for your specific needs.

## Understanding the Action System

MilliRules provides a flexible action framework that allows you to:

- **Register custom callback actions** for quick, inline functionality
- **Create reusable action classes** for complex operations
- **Execute actions sequentially** in the order they're defined
- **Access full context** within actions for data-driven decisions

## Action Execution Flow

When a rule's conditions match:

```
1. Rule conditions evaluate to true
   ↓
2. Rule engine triggers action execution
   ↓
3. For each action in the rule:
   a. Instantiate action with config and context
   b. Execute action's execute() method
   c. Continue to next action
   ↓
4. Return execution statistics
```

Actions execute **immediately and sequentially**. There's no action queue or deferred execution.

> [!IMPORTANT]
> Actions execute in the exact order they're defined in your rule. If one action fails, MilliRules logs the error and continues to the next action.

## Action Types

### 1. Custom Callback Actions

The simplest way to create actions is using callback functions.

#### Registering Callback Actions

```php
<?php
use MilliRules\Rules;

// Simple action
Rules::register_action('log_message', function($context, $config) {
    $message = $config['value'] ?? 'No message';
    error_log('MilliRules: ' . $message);
});

// Action with context access
Rules::register_action('log_user_action', function($context, $config) {
    $user = $context['wp']['user']['login'] ?? 'guest';
    $url = $context['request']['uri'] ?? 'unknown';
    error_log("User {$user} accessed {$url}");
});
```

#### Using Callback Actions in Rules

```php
<?php
Rules::create('log_admin_access')
    ->when()
        ->request_url('/wp-admin/*')
        ->is_user_logged_in()
    ->then()
        ->custom('log_message', ['value' => 'Admin area accessed'])
        ->custom('log_user_action')
    ->register();
```

**Callback Parameters**:
- `$context` (array): Full execution context with request, WP data, etc.
- `$config` (array): Action configuration passed from the rule

> [!TIP]
> Use callback actions for simple operations that don't require state management or extensive configuration.

---

### 2. Class-Based Actions

For complex operations, create action classes implementing `ActionInterface`.

#### Action Interface

```php
<?php
namespace MilliRules\Interfaces;

interface ActionInterface {
    public function execute(array $context): void;
    public function get_type(): string;
}
```

#### Creating an Action Class

```php
<?php
namespace MyPlugin\Actions;

use MilliRules\Interfaces\ActionInterface;

class SendEmailAction implements ActionInterface {
    private $config;
    private $context;

    public function __construct(array $config, array $context) {
        $this->config = $config;
        $this->context = $context;
    }

    public function execute(array $context): void {
        $to = $this->config['to'] ?? '';
        $subject = $this->config['subject'] ?? 'Notification';
        $message = $this->config['message'] ?? '';

        // Access context for dynamic data
        $user_email = $context['wp']['user']['email'] ?? $to;

        wp_mail($to, $subject, $message);
    }

    public function get_type(): string {
        return 'send_email';
    }
}
```

#### Registering the Namespace

```php
<?php
use MilliRules\RuleEngine;

// Register action namespace so MilliRules can find your action classes
RuleEngine::register_namespace('Actions', 'MyPlugin\Actions');
```

#### Using Class-Based Actions

```php
<?php
Rules::create('user_registration_notification')
    ->when()
        ->request_url('/wp-admin/user-new.php')
        ->request_param('action', 'createuser')
    ->then()
        ->custom('send_email', [
            'to' => 'admin@example.com',
            'subject' => 'New User Registration',
            'message' => 'A new user has registered.'
        ])
    ->register();
```

> [!NOTE]
> Class-based actions provide better organization, testability, and reusability for complex operations.

---

### 3. BaseAction Helper Class

MilliRules provides a `BaseAction` abstract class that includes placeholder resolution.

```php
<?php
namespace MyPlugin\Actions;

use MilliRules\Actions\BaseAction;

class CustomAction extends BaseAction {
    public function execute(array $context): void {
        // Resolve placeholders in config values
        $message = $this->resolve_value($this->config['value'] ?? '');

        // Use resolved value
        error_log($message);
    }

    public function get_type(): string {
        return 'custom_action';
    }
}
```

**Using placeholder resolution**:

```php
<?php
Rules::create('dynamic_logging')
    ->when()
        ->request_url('/api/*')
    ->then()
        ->custom('custom_action', [
            'value' => 'API request to {request:uri} from {request:ip}'
        ])
    ->register();

// Logs: "API request to /api/users from 192.168.1.1"
```

See [Dynamic Placeholders](07-placeholders.md) for complete placeholder syntax.

---

## Common Action Patterns

### 1. Logging Actions

```php
<?php
// Simple logging
Rules::register_action('log', function($context, $config) {
    error_log($config['value'] ?? '');
});

// Structured logging
Rules::register_action('log_structured', function($context, $config) {
    $data = [
        'timestamp' => time(),
        'user' => $context['wp']['user']['login'] ?? 'guest',
        'ip' => $context['request']['ip'] ?? 'unknown',
        'message' => $config['value'] ?? '',
    ];
    error_log(json_encode($data));
});

// Usage
Rules::create('log_actions')
    ->when()->request_url('/important/*')
    ->then()
        ->custom('log', ['value' => 'Important URL accessed'])
        ->custom('log_structured', ['value' => 'Security alert'])
    ->register();
```

---

### 2. Redirect Actions

```php
<?php
Rules::register_action('redirect', function($context, $config) {
    $url = $config['url'] ?? home_url();
    $status = $config['status'] ?? 302;

    if (!headers_sent()) {
        wp_redirect($url, $status);
        exit;
    }
});

// Usage
Rules::create('redirect_logged_out_users')
    ->when()
        ->request_url('/members/*')
        ->is_user_logged_in(false)
    ->then()
        ->custom('redirect', [
            'url' => wp_login_url(),
            'status' => 302
        ])
    ->register();
```

> [!WARNING]
> Redirect actions should typically be the last action in a rule, as they terminate execution with `exit`.

---

### 3. Cache Control Actions

```php
<?php
Rules::register_action('set_cache_headers', function($context, $config) {
    $duration = $config['duration'] ?? 3600;

    if (!headers_sent()) {
        header("Cache-Control: public, max-age={$duration}");
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $duration) . ' GMT');
    }
});

// Usage
Rules::create('cache_api_responses')
    ->when()
        ->request_url('/api/*')
        ->request_method(['GET', 'HEAD'], 'IN')
    ->then()
        ->custom('set_cache_headers', ['duration' => 7200])
    ->register();
```

---

### 4. Database Operations

```php
<?php
Rules::register_action('log_to_database', function($context, $config) {
    global $wpdb;

    $table = $wpdb->prefix . 'access_log';
    $wpdb->insert($table, [
        'user_id' => $context['wp']['user']['id'] ?? 0,
        'url' => $context['request']['uri'] ?? '',
        'timestamp' => current_time('mysql'),
    ]);
});

// Usage
Rules::create('track_premium_access')
    ->when()
        ->request_url('/premium/*')
        ->is_user_logged_in()
    ->then()
        ->custom('log_to_database')
    ->register();
```

---

### 5. WordPress Hook Triggers

```php
<?php
// Trigger WordPress actions
Rules::register_action('do_action', function($context, $config) {
    $hook = $config['value'] ?? '';
    $args = $config['args'] ?? [];

    if ($hook) {
        do_action($hook, ...$args);
    }
});

// Trigger WordPress filters
Rules::register_action('apply_filters', function($context, $config) {
    $hook = $config['value'] ?? '';
    $value = $config['filter_value'] ?? '';
    $args = $config['args'] ?? [];

    if ($hook) {
        return apply_filters($hook, $value, ...$args);
    }
});

// Usage
Rules::create('trigger_custom_hooks')
    ->when()->request_url('/checkout/*')
    ->then()
        ->custom('do_action', [
            'value' => 'my_checkout_started',
            'args' => ['checkout_page']
        ])
    ->register();
```

---

### 6. Content Modification

```php
<?php
Rules::register_action('modify_content', function($context, $config) {
    add_filter('the_content', function($content) use ($config) {
        $prepend = $config['prepend'] ?? '';
        $append = $config['append'] ?? '';

        return $prepend . $content . $append;
    }, $config['priority'] ?? 10);
});

// Usage
Rules::create('add_disclaimer')
    ->when()
        ->is_singular('post')
        ->post_type('product')
    ->then()
        ->custom('modify_content', [
            'prepend' => '<div class="disclaimer">Product information may vary.</div>',
            'priority' => 10
        ])
    ->register();
```

---

### 7. Conditional Execution

```php
<?php
Rules::register_action('execute_if', function($context, $config) {
    $condition = $config['condition'] ?? null;
    $callback = $config['callback'] ?? null;

    if (is_callable($condition) && is_callable($callback)) {
        if ($condition($context)) {
            $callback($context);
        }
    }
});

// Usage
Rules::create('conditional_action')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('execute_if', [
            'condition' => function($context) {
                return date('H') >= 9 && date('H') <= 17; // Business hours
            },
            'callback' => function($context) {
                error_log('API accessed during business hours');
            }
        ])
    ->register();
```

---

## Action Configuration

Actions receive configuration through the `$config` array:

```php
<?php
Rules::register_action('flexible_action', function($context, $config) {
    // Common configuration keys
    $value = $config['value'] ?? '';           // Primary value
    $enabled = $config['enabled'] ?? true;     // Enable flag
    $priority = $config['priority'] ?? 10;     // Priority/order
    $options = $config['options'] ?? [];       // Additional options

    // Custom configuration
    $custom_param = $config['custom_param'] ?? 'default';
});

// Usage with full configuration
Rules::create('configured_action')
    ->when()->request_url('/test')
    ->then()
        ->custom('flexible_action', [
            'value' => 'test value',
            'enabled' => true,
            'priority' => 20,
            'options' => ['key' => 'value'],
            'custom_param' => 'custom value'
        ])
    ->register();
```

> [!TIP]
> Use consistent configuration key names across your actions:
> - `'value'` for the primary action value
> - `'enabled'` for enable/disable flags
> - `'priority'` for ordering within the action
> - `'options'` for nested configuration

---

## Accessing Context in Actions

The context provides access to all available data:

```php
<?php
Rules::register_action('context_aware_action', function($context, $config) {
    // Request data
    $url = $context['request']['uri'] ?? '';
    $method = $context['request']['method'] ?? '';
    $ip = $context['request']['ip'] ?? '';

    // Cookies
    $session = $context['request']['cookies']['session_id'] ?? '';

    // WordPress data (if available)
    $user_id = $context['wp']['user']['id'] ?? 0;
    $user_login = $context['wp']['user']['login'] ?? 'guest';
    $post_id = $context['wp']['post']['id'] ?? 0;

    // Query flags
    $is_singular = $context['wp']['query']['is_singular'] ?? false;
    $is_admin = $context['wp']['query']['is_admin'] ?? false;

    // Use context data
    error_log("User {$user_login} accessed {$url} from {$ip}");
});
```

**Full context structure**:
```php
<?php
[
    'request' => [
        'method' => 'GET',
        'uri' => '/path',
        'scheme' => 'https',
        'host' => 'example.com',
        'path' => '/path',
        'query' => 'key=value',
        'referer' => 'https://example.com',
        'user_agent' => 'Mozilla/5.0...',
        'headers' => [...],
        'ip' => '192.168.1.1',
        'cookies' => [...],
        'params' => [...],
    ],
    'wp' => [  // WordPress only
        'post' => [...],
        'user' => [...],
        'query' => [...],
        'constants' => [...],
    ],
]
```

---

## Error Handling in Actions

MilliRules catches action exceptions and continues execution:

```php
<?php
Rules::register_action('safe_action', function($context, $config) {
    try {
        // Risky operation
        $result = risky_operation();

        if (!$result) {
            throw new Exception('Operation failed');
        }
    } catch (Exception $e) {
        error_log('Action error: ' . $e->getMessage());
        // Execution continues to next action
    }
});
```

> [!IMPORTANT]
> If an action throws an uncaught exception, MilliRules logs the error and continues to the next action. The rule is marked as executed even if actions fail.

---

## Multiple Actions in Sequence

Actions execute sequentially in definition order:

```php
<?php
Rules::create('multi_step_process')
    ->when()->request_url('/process')
    ->then()
        ->custom('log', ['value' => '1. Starting process'])
        ->custom('validate_data')
        ->custom('log', ['value' => '2. Data validated'])
        ->custom('process_data')
        ->custom('log', ['value' => '3. Data processed'])
        ->custom('send_response')
        ->custom('log', ['value' => '4. Response sent'])
    ->register();
```

**Execution flow**:
1. Log: "1. Starting process"
2. Validate data
3. Log: "2. Data validated"
4. Process data
5. Log: "3. Data processed"
6. Send response
7. Log: "4. Response sent"

---

## Best Practices

### 1. Keep Actions Focused

```php
<?php
// ✅ Good - single responsibility
Rules::register_action('log_access', function($context, $config) {
    error_log('Access logged');
});

Rules::register_action('update_counter', function($context, $config) {
    update_option('access_count', get_option('access_count', 0) + 1);
});

// ❌ Bad - multiple responsibilities
Rules::register_action('do_everything', function($context, $config) {
    error_log('Access logged');
    update_option('access_count', get_option('access_count', 0) + 1);
    send_email('admin@example.com', 'Access', 'Someone accessed');
    update_database();
    // Too much in one action!
});
```

### 2. Use Descriptive Action Names

```php
<?php
// ✅ Good
Rules::register_action('send_admin_notification_email', ...);
Rules::register_action('log_security_event', ...);
Rules::register_action('update_user_last_login_timestamp', ...);

// ❌ Bad
Rules::register_action('send', ...);
Rules::register_action('log', ...);
Rules::register_action('update', ...);
```

### 3. Validate Configuration

```php
<?php
Rules::register_action('safe_action', function($context, $config) {
    // Validate required configuration
    if (empty($config['required_value'])) {
        error_log('Action error: missing required_value');
        return;
    }

    // Validate data types
    $count = absint($config['count'] ?? 0);
    $enabled = (bool) ($config['enabled'] ?? true);

    // Proceed with validated data
    // ...
});
```

### 4. Check Prerequisites

```php
<?php
Rules::register_action('wordpress_dependent', function($context, $config) {
    // Check if WordPress functions are available
    if (!function_exists('wp_mail')) {
        error_log('WordPress not available');
        return;
    }

    wp_mail($config['to'], $config['subject'], $config['message']);
});
```

### 5. Use Constants for Configuration

```php
<?php
// Define action configuration constants
define('DEFAULT_EMAIL_RECIPIENT', 'admin@example.com');
define('DEFAULT_LOG_LEVEL', 'info');

Rules::register_action('send_notification', function($context, $config) {
    $to = $config['to'] ?? DEFAULT_EMAIL_RECIPIENT;
    $level = $config['level'] ?? DEFAULT_LOG_LEVEL;

    // Use constants for consistent configuration
});
```

---

## Common Pitfalls

### 1. Modifying Context

```php
<?php
// ❌ Wrong - context modifications don't persist
Rules::register_action('modify_context', function($context, $config) {
    $context['custom_value'] = 'modified';
    // This change is lost after the action completes
});

// ✅ Correct - use external state or return values
Rules::register_action('store_value', function($context, $config) {
    update_option('custom_value', 'modified');
});
```

> [!WARNING]
> Context is passed by value to actions. Modifications to `$context` within an action do not persist to subsequent actions.

### 2. Assuming Action Order Across Rules

```php
<?php
// ❌ Wrong - different rules, no guaranteed order
Rules::create('rule1')->order(10)->when()->then()->custom('action1')->register();
Rules::create('rule2')->order(20)->when()->then()->custom('action2')->register();
// action1 and action2 only execute if their respective rule conditions match

// ✅ Correct - actions in same rule execute in order
Rules::create('rule')->order(10)
    ->when()->request_url('*')
    ->then()
        ->custom('action1')  // Executes first
        ->custom('action2')  // Executes second
    ->register();
```

### 3. Using Exit/Die in Actions

```php
<?php
// ❌ Wrong - prevents subsequent actions
Rules::register_action('early_exit', function($context, $config) {
    echo 'Response';
    exit; // Stops all subsequent actions!
});

// ✅ Correct - use flags or return early
Rules::register_action('conditional_processing', function($context, $config) {
    if (!some_condition()) {
        return; // Skip this action, continue to next
    }

    // Process normally
});
```

---

## Next Steps

- **[Operators and Pattern Matching](06-operators.md)** - Master condition operators
- **[Dynamic Placeholders](07-placeholders.md)** - Use dynamic values in actions
- **[Creating Custom Actions](10-custom-actions.md)** - Advanced action development
- **[Real-World Examples](15-examples.md)** - See actions in complete examples

---

**Ready to create custom actions?** Continue to [Creating Custom Actions](10-custom-actions.md) for advanced techniques and patterns.
