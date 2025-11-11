---
post_title: 'Creating Custom Actions'
post_excerpt: 'Master creating custom actions in MilliRules using callback functions or ActionInterface classes for flexible, reusable functionality.'
taxonomy:
  category:
    - documentation
    - guides
    - advanced
  post_tag:
    - custom-actions
    - extending
    - callbacks
    - actioninterface
    - advanced
menu_order: 10
---

# Creating Custom Actions

Custom actions are the heart of what makes your rules useful. While MilliRules provides the framework, your custom actions implement the actual business logic. This guide covers everything from simple callback actions to sophisticated class-based implementations.

## Why Create Custom Actions?

Custom actions enable you to:

- Implement business-specific operations
- Integrate with third-party services
- Modify WordPress behavior dynamically
- Perform database operations
- Send notifications and emails
- Log custom analytics
- Transform data

## Callback-Based Custom Actions

The quickest way to create actions is using callback functions.

### Basic Callback Action

```php
<?php
use MilliRules\Rules;

// Register simple action
Rules::register_action('log_message', function($context, $config) {
    $message = $config['value'] ?? 'No message';
    error_log('MilliRules: ' . $message);
});

// Use in rule
Rules::create('log_admin_access')
    ->when()->request_url('/wp-admin/*')
    ->then()->custom('log_message', ['value' => 'Admin accessed'])
    ->register();
```

### Action with Context Access

```php
<?php
Rules::register_action('log_user_action', function($context, $config) {
    $user = $context['wp']['user']['login'] ?? 'guest';
    $url = $context['request']['uri'] ?? 'unknown';
    $ip = $context['request']['ip'] ?? '0.0.0.0';

    error_log("User {$user} accessed {$url} from {$ip}");
});

// Usage
Rules::create('track_user_activity')
    ->when()->request_url('/important/*')
    ->then()->custom('log_user_action')
    ->register();
```

### Action with Configuration

```php
<?php
Rules::register_action('send_email', function($context, $config) {
    $to = $config['to'] ?? '';
    $subject = $config['subject'] ?? 'Notification';
    $message = $config['message'] ?? '';

    if (!$to) {
        error_log('send_email: missing recipient');
        return;
    }

    wp_mail($to, $subject, $message);
});

// Usage
Rules::create('notify_admin')
    ->when()->request_param('action', 'user_registered')
    ->then()
        ->custom('send_email', [
            'to' => 'admin@example.com',
            'subject' => 'New User Registration',
            'message' => 'A new user has registered on your site.'
        ])
    ->register();
```

---

## Class-Based Custom Actions

For complex operations or reusable actions, create classes implementing `ActionInterface`.

### ActionInterface

```php
<?php
namespace MilliRules\Interfaces;

interface ActionInterface {
    public function execute(array $context): void;
    public function get_type(): string;
}
```

### Basic Custom Action Class

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

        if (!$to) {
            error_log('SendEmailAction: missing recipient');
            return;
        }

        // Use context for dynamic data
        $user_email = $context['wp']['user']['email'] ?? $to;

        wp_mail($user_email, $subject, $message);
    }

    public function get_type(): string {
        return 'send_email';
    }
}
```

**Register namespace**:
```php
<?php
use MilliRules\RuleEngine;

RuleEngine::register_namespace('Actions', 'MyPlugin\Actions');
```

**Usage**:
```php
<?php
Rules::create('email_notification')
    ->when()->request_url('/form-submitted')
    ->then()
        ->custom('send_email', [
            'to' => 'admin@example.com',
            'subject' => 'Form Submission',
            'message' => 'New form submitted'
        ])
    ->register();
```

---

## Using BaseAction for Placeholder Support

`BaseAction` provides automatic placeholder resolution.

### BaseAction Overview

```php
<?php
namespace MilliRules\Actions;

abstract class BaseAction implements ActionInterface {
    protected $config;
    protected $context;

    public function __construct(array $config, array $context);
    protected function resolve_value(string $value): string;
    abstract public function execute(array $context): void;
    abstract public function get_type(): string;
}
```

### Action with Placeholder Resolution

```php
<?php
namespace MyPlugin\Actions;

use MilliRules\Actions\BaseAction;

class LogDetailedAction extends BaseAction {
    public function execute(array $context): void {
        // Resolve placeholders in message
        $message = $this->resolve_value($this->config['message'] ?? '');

        error_log('Detailed Log: ' . $message);
    }

    public function get_type(): string {
        return 'log_detailed';
    }
}
```

**Usage with placeholders**:
```php
<?php
Rules::create('detailed_logging')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('log_detailed', [
            'message' => '{request:method} request to {request:uri} from {request:ip} by user {wp:user:login}'
        ])
    ->register();

// Logs: "GET request to /api/users from 192.168.1.1 by user john_doe"
```

---

## Advanced Custom Actions

### Action with Database Operations

```php
<?php
namespace MyPlugin\Actions;

use MilliRules\Actions\BaseAction;

class LogToDatabaseAction extends BaseAction {
    public function execute(array $context): void {
        global $wpdb;

        $table = $wpdb->prefix . 'access_log';
        $user_id = $context['wp']['user']['id'] ?? 0;
        $url = $context['request']['uri'] ?? '';
        $ip = $context['request']['ip'] ?? '';

        $wpdb->insert($table, [
            'user_id' => $user_id,
            'url' => $url,
            'ip' => $ip,
            'timestamp' => current_time('mysql'),
        ]);
    }

    public function get_type(): string {
        return 'log_to_database';
    }
}
```

### Action with External API Call

```php
<?php
Rules::register_action('send_to_api', function($context, $config) {
    $api_url = $config['url'] ?? '';
    $data = $config['data'] ?? [];

    if (!$api_url) {
        error_log('send_to_api: missing URL');
        return;
    }

    // Add context data to payload
    $data['user_id'] = $context['wp']['user']['id'] ?? 0;
    $data['request_url'] = $context['request']['uri'] ?? '';
    $data['timestamp'] = time();

    $response = wp_remote_post($api_url, [
        'body' => json_encode($data),
        'headers' => ['Content-Type' => 'application/json'],
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        error_log('API Error: ' . $response->get_error_message());
        return;
    }

    $status = wp_remote_retrieve_response_code($response);
    error_log("API Response Status: {$status}");
});

// Usage
Rules::create('sync_to_external_system')
    ->when()->request_param('action', 'user_registered')
    ->then()
        ->custom('send_to_api', [
            'url' => 'https://api.example.com/users',
            'data' => ['event' => 'registration']
        ])
    ->register();
```

### Action with Caching

```php
<?php
Rules::register_action('set_cache', function($context, $config) {
    $key = $config['key'] ?? '';
    $value = $config['value'] ?? '';
    $duration = $config['duration'] ?? 3600;

    if (!$key) {
        error_log('set_cache: missing cache key');
        return;
    }

    // Add context data to cache value
    $cache_data = [
        'value' => $value,
        'user_id' => $context['wp']['user']['id'] ?? 0,
        'timestamp' => time(),
    ];

    set_transient($key, $cache_data, $duration);
});

// Usage
Rules::create('cache_api_response')
    ->when()
        ->request_url('/api/expensive-operation')
        ->request_method('GET')
    ->then()
        ->custom('set_cache', [
            'key' => 'api_result_expensive_operation',
            'value' => 'result_data',
            'duration' => 7200
        ])
    ->register();
```

### Action with WordPress Hooks

```php
<?php
Rules::register_action('trigger_wp_action', function($context, $config) {
    $hook_name = $config['hook'] ?? '';
    $args = $config['args'] ?? [];

    if (!$hook_name) {
        error_log('trigger_wp_action: missing hook name');
        return;
    }

    // Add context to args
    $args['context'] = $context;

    do_action($hook_name, ...$args);
});

// Usage
Rules::create('trigger_custom_hook')
    ->when()->request_url('/checkout/complete')
    ->then()
        ->custom('trigger_wp_action', [
            'hook' => 'my_plugin_checkout_complete',
            'args' => ['order_id' => 123]
        ])
    ->register();
```

### Action with Content Modification

```php
<?php
Rules::register_action('modify_content', function($context, $config) {
    $prepend = $config['prepend'] ?? '';
    $append = $config['append'] ?? '';
    $priority = $config['priority'] ?? 10;

    add_filter('the_content', function($content) use ($prepend, $append) {
        return $prepend . $content . $append;
    }, $priority);
});

// Usage
Rules::create('add_disclaimer_to_posts')
    ->when()
        ->is_singular('post')
        ->post_type('product')
    ->then()
        ->custom('modify_content', [
            'prepend' => '<div class="product-disclaimer">Prices subject to change.</div>',
            'priority' => 10
        ])
    ->register();
```

---

## Action Configuration Patterns

### Validated Configuration

```php
<?php
Rules::register_action('validated_action', function($context, $config) {
    // Validate required fields
    if (!isset($config['required_field'])) {
        error_log('validated_action: missing required_field');
        return;
    }

    // Validate data types
    $number = absint($config['number'] ?? 0);
    $string = sanitize_text_field($config['string'] ?? '');
    $email = sanitize_email($config['email'] ?? '');

    if (!is_email($email)) {
        error_log('validated_action: invalid email');
        return;
    }

    // Proceed with validated data
    perform_action($config['required_field'], $number, $string, $email);
});
```

### Configuration with Defaults

```php
<?php
Rules::register_action('configurable_action', function($context, $config) {
    // Define defaults
    $defaults = [
        'enabled' => true,
        'priority' => 10,
        'timeout' => 30,
        'retry_count' => 3,
    ];

    // Merge with provided config
    $config = wp_parse_args($config, $defaults);

    if (!$config['enabled']) {
        return; // Action disabled
    }

    // Use configuration
    perform_operation($config);
});
```

### Conditional Configuration

```php
<?php
Rules::register_action('conditional_action', function($context, $config) {
    $mode = $config['mode'] ?? 'default';

    switch ($mode) {
        case 'debug':
            error_log('Debug mode: ' . print_r($context, true));
            break;

        case 'production':
            // Production logic
            send_to_external_service($context);
            break;

        case 'test':
            // Test logic
            store_for_testing($context);
            break;

        default:
            // Default logic
            standard_operation($context);
            break;
    }
});
```

---

## Action Error Handling

### Graceful Error Handling

```php
<?php
Rules::register_action('safe_action', function($context, $config) {
    try {
        // Risky operation
        $result = risky_operation($config);

        if (!$result) {
            throw new Exception('Operation failed');
        }

        error_log('Operation successful');

    } catch (Exception $e) {
        error_log('Action error: ' . $e->getMessage());

        // Optional: notify admin
        if (defined('WP_DEBUG') && WP_DEBUG) {
            wp_mail(get_option('admin_email'), 'Action Error', $e->getMessage());
        }
    }
});
```

### Validation Before Execution

```php
<?php
Rules::register_action('validated_execution', function($context, $config) {
    // Pre-execution validation
    if (!validate_context($context)) {
        error_log('Invalid context for action');
        return;
    }

    if (!validate_config($config)) {
        error_log('Invalid configuration for action');
        return;
    }

    // Safe to execute
    execute_operation($context, $config);
});
```

---

## Best Practices

### 1. Validate Input

```php
<?php
// ✅ Good - validates all input
Rules::register_action('safe_email', function($context, $config) {
    $to = sanitize_email($config['to'] ?? '');
    $subject = sanitize_text_field($config['subject'] ?? '');
    $message = wp_kses_post($config['message'] ?? '');

    if (!is_email($to)) {
        error_log('Invalid email address');
        return;
    }

    wp_mail($to, $subject, $message);
});

// ❌ Bad - no validation
Rules::register_action('unsafe_email', function($context, $config) {
    wp_mail($config['to'], $config['subject'], $config['message']);
});
```

### 2. Use Descriptive Names

```php
<?php
// ✅ Good - clear purpose
Rules::register_action('send_admin_notification_email', ...);
Rules::register_action('log_security_event_to_database', ...);
Rules::register_action('update_user_last_login_timestamp', ...);

// ❌ Bad - unclear purpose
Rules::register_action('send', ...);
Rules::register_action('log', ...);
Rules::register_action('update', ...);
```

### 3. Handle Missing Context

```php
<?php
// ✅ Good - checks context availability
Rules::register_action('wp_aware_action', function($context, $config) {
    if (!isset($context['wp'])) {
        error_log('WordPress context not available');
        return;
    }

    $user_id = $context['wp']['user']['id'] ?? 0;
    // Proceed with WordPress operations
});
```

### 4. Avoid Side Effects in Dry Runs

```php
<?php
// ✅ Good - respects dry-run mode
Rules::register_action('careful_action', function($context, $config) {
    $dry_run = $config['dry_run'] ?? false;

    if ($dry_run) {
        error_log('DRY RUN: Would perform action');
        return;
    }

    // Actual operation
    perform_action();
});
```

### 5. Log Important Actions

```php
<?php
// ✅ Good - logs important operations
Rules::register_action('critical_operation', function($context, $config) {
    error_log('Starting critical operation');

    try {
        $result = perform_critical_operation($config);
        error_log('Critical operation completed successfully');
        return $result;

    } catch (Exception $e) {
        error_log('Critical operation failed: ' . $e->getMessage());
        throw $e;
    }
});
```

---

## Testing Custom Actions

### Unit Testing

```php
<?php
class CustomActionTest extends WP_UnitTestCase {
    public function test_send_email_action() {
        // Register action
        Rules::register_action('test_email', function($context, $config) {
            // Mock email sending
            update_option('test_email_sent', $config);
            return true;
        });

        // Create rule
        Rules::create('test_rule')
            ->when()->request_url('*')
            ->then()->custom('test_email', [
                'to' => 'test@example.com',
                'subject' => 'Test'
            ])
            ->register();

        // Execute
        MilliRules::execute_rules();

        // Assert
        $sent = get_option('test_email_sent');
        $this->assertEquals('test@example.com', $sent['to']);
        $this->assertEquals('Test', $sent['subject']);
    }
}
```

### Manual Testing

```php
<?php
Rules::register_action('debug_action', function($context, $config) {
    error_log('=== ACTION DEBUG ===');
    error_log('Config: ' . print_r($config, true));
    error_log('Context keys: ' . implode(', ', array_keys($context)));
    error_log('User: ' . ($context['wp']['user']['login'] ?? 'guest'));
    error_log('URL: ' . ($context['request']['uri'] ?? 'unknown'));
    error_log('===================');
});
```

---

## Common Pitfalls

### 1. Forgetting to Return Early

```php
<?php
// ❌ Wrong - continues after error
Rules::register_action('bad_action', function($context, $config) {
    if (!isset($config['required'])) {
        error_log('Missing required config');
        // Should return here!
    }

    // This still executes!
    perform_operation($config['required']);
});

// ✅ Correct - returns early
Rules::register_action('good_action', function($context, $config) {
    if (!isset($config['required'])) {
        error_log('Missing required config');
        return; // Early return
    }

    perform_operation($config['required']);
});
```

### 2. Not Handling Exceptions

```php
<?php
// ❌ Wrong - unhandled exception stops execution
Rules::register_action('risky_action', function($context, $config) {
    risky_operation(); // May throw exception
});

// ✅ Correct - handles exceptions
Rules::register_action('safe_action', function($context, $config) {
    try {
        risky_operation();
    } catch (Exception $e) {
        error_log('Action error: ' . $e->getMessage());
    }
});
```

### 3. Modifying Context Expecting Persistence

```php
<?php
// ❌ Wrong - context changes don't persist
Rules::register_action('bad_context_modification', function($context, $config) {
    $context['custom_value'] = 'modified';
    // This change is lost after action completes!
});

// ✅ Correct - use external state
Rules::register_action('good_state_management', function($context, $config) {
    update_option('custom_value', 'modified');
    // Or use global variable, cache, etc.
});
```

---

## Next Steps

- **[Creating Custom Packages](11-custom-packages.md)** - Package your actions
- **[Advanced Usage](12-advanced-usage.md)** - Advanced action techniques
- **[WordPress Integration](13-wordpress-integration.md)** - WordPress-specific actions
- **[Real-World Examples](15-examples.md)** - Complete action implementations

---

**Ready to package your code?** Continue to [Creating Custom Packages](11-custom-packages.md) to learn how to bundle conditions, actions, and context into reusable packages.
