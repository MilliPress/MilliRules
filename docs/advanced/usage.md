---
title: 'Advanced Usage Patterns'
post_excerpt: 'Master advanced MilliRules techniques including early execution, performance optimization, debugging strategies, and complex rule patterns.'
---

# Advanced Usage Patterns

This guide covers advanced techniques for optimizing performance, debugging issues, implementing complex rule patterns, and integrating MilliRules deeply into your applications.

## Early Execution

Early execution runs rules before WordPress fully loads, enabling caching systems, redirects, and performance optimizations.

### MU-Plugin Early Execution

```php
<?php
/**
 * Plugin Name: MilliRules Early Execution
 * Description: Runs MilliRules before WordPress loads
 */

require_once WPMU_PLUGIN_DIR . '/millirules-vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;
use MilliRules\Context;

// Initialize with PHP package only (WordPress not loaded yet)
MilliRules::init(['PHP']);

// Register early execution rules
Rules::create('early_cache_check', 'php')
    ->when()
        ->request_url('/api/*')
        ->request_method('GET')
    ->then()
        ->custom('check_cache')
        ->custom('early_exit_if_cached')
    ->register();

// Execute early rules
$result = MilliRules::execute_rules(['PHP']);
```

### Custom Cache Integration

```php
<?php
Rules::register_action('check_cache', function($args, Context $context) {
    $cache_key = 'page_' . md5($context->get('request.uri', '') ?? '');
    $cached = get_transient($cache_key);

    if ($cached !== false) {
        // Send cached response
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Cache: HIT');
        echo $cached;
        exit;
    }
});

Rules::register_action('save_to_cache', function($args, Context $context) {
    $cache_key = 'page_' . md5($context->get('request.uri', '') ?? '');
    $duration = $args['duration'] ?? 3600;

    ob_start(function($buffer) use ($cache_key, $duration) {
        set_transient($cache_key, $buffer, $duration);
        return $buffer;
    });
});

Rules::create('api_caching')
    ->when()
        ->request_url('/api/*')
        ->request_method('GET')
    ->then()
        ->custom('check_cache') // Check first
        ->custom('save_to_cache', ['duration' => 3600]) // Save if not cached
    ->register();
```

---

## Performance Optimization

### Rule Ordering for Performance

```php
<?php
// Place most restrictive/fastest conditions first
Rules::create('optimized_rule')
    ->order(10)
    ->when()
        // Fast checks first
        ->request_method('POST')                    // Very fast
        ->request_url('/api/specific-endpoint')     // Fast
        ->cookie('session_id')                      // Fast

        // Slower checks last
        ->custom('expensive_validation')            // Slow
    ->then()
        ->custom('process_request')
    ->register();
```

### Lazy Loading

```php
<?php
// Load rules only when needed
add_action('init', function() {
    MilliRules::init();

    // Load admin rules only in admin
    if (is_admin()) {
        require_once __DIR__ . '/rules/admin-rules.php';
    }

    // Load frontend rules only on frontend
    if (!is_admin()) {
        require_once __DIR__ . '/rules/frontend-rules.php';
    }

    // Load API rules only for API requests
    if (str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
        require_once __DIR__ . '/rules/api-rules.php';
    }
}, 1);
```

---

## Debugging Strategies

### Debug Logging

```php
<?php
// Enable comprehensive debugging
define('MILLIRULES_DEBUG', true);

Rules::register_action('debug_log', function($args, Context $context) {
    if (!defined('MILLIRULES_DEBUG') || !MILLIRULES_DEBUG) {
        return;
    }

    $message = $args['message'] ?? '';
    $data = $args['data'] ?? [];

    error_log('=== MilliRules Debug ===');
    error_log('Message: ' . $message);
    error_log('Data: ' . print_r($data, true));
    error_log('======================');
});

// Add debug actions to rules
Rules::create('debuggable_rule')
    ->when()
        ->request_url('/api/*')
    ->then()
        ->custom('debug_log', [
            'message' => 'API request started',
            'data' => ['url' => '{request.uri}']
        ])
        ->custom('process_api')
        ->custom('debug_log', [
            'message' => 'API request completed'
        ])
    ->register();
```

### Execution Statistics

```php
<?php
// Track execution statistics
$result = MilliRules::execute_rules();

error_log('=== Execution Statistics ===');
error_log('Rules processed: ' . $result['rules_processed']);
error_log('Rules skipped: ' . $result['rules_skipped']);
error_log('Rules matched: ' . $result['rules_matched']);
error_log('Actions executed: ' . $result['actions_executed']);
error_log('===========================');

// Performance tracking
$start_time = microtime(true);
$start_memory = memory_get_usage();

$result = MilliRules::execute_rules();

$execution_time = microtime(true) - $start_time;
$memory_used = memory_get_usage() - $start_memory;

error_log("Execution time: {$execution_time}s");
error_log("Memory used: " . size_format($memory_used));
```

### Debug Conditions

```php
<?php
Rules::register_condition('debug_context', function($args, Context $context) {
    error_log('=== Context Debug ===');
    error_log('Full context: ' . print_r($context, true));
    error_log('===================');
    return true; // Always matches
});

Rules::create('debug_rule')
    ->when()
        ->custom('debug_context')
        ->your_actual_conditions()
    ->then()
        ->your_actions()
    ->register();
```

---

## Complex Rule Patterns

### Conditional Rule Groups

```php
<?php
// Environment-specific rules
$environment = wp_get_environment_type();

if ($environment === 'local') {
    // Local development rules
    Rules::create('local_debug')
        ->when()->constant('WP_DEBUG', true, '=')
        ->then()->custom('enable_verbose_logging')
        ->register();

    Rules::create('local_logging')
        ->when()->request_url('*')
        ->then()->custom('log_all_requests')
        ->register();

} elseif ($environment === 'staging') {
    // Staging environment rules
    Rules::create('staging_monitoring')
        ->when()->request_url('*')
        ->then()->custom('track_staging_metrics')
        ->register();

} elseif ($environment === 'production') {
    // Production rules
    Rules::create('prod_caching')
        ->when()->request_url('/api/*')
        ->then()->custom('enable_aggressive_cache')
        ->register();

    Rules::create('prod_security')
        ->when()->request_method('POST')
        ->then()->custom('enhanced_security_check')
        ->register();
}
```

### Dynamic Rule Generation

```php
<?php
// Generate rules from configuration
$protected_endpoints = [
    '/api/users' => ['GET', 'POST'],
    '/api/posts' => ['GET', 'POST', 'PUT', 'DELETE'],
    '/api/settings' => ['GET', 'PUT'],
];

foreach ($protected_endpoints as $endpoint => $methods) {
    $rule_id = 'protect_' . sanitize_title($endpoint);

    Rules::create($rule_id)
        ->when()
            ->request_url($endpoint)
            ->request_method($methods, 'IN')
            ->is_user_logged_in(false) // Not logged in
        ->then()
            ->custom('send_401_unauthorized')
        ->register();
}
```

---

## Package Filtering

Execute rules with specific packages only.

### Selective Package Execution

```php
<?php
// Execute only PHP rules (before WordPress loads)
$php_result = MilliRules::execute_rules(['PHP']);

// Execute only WordPress rules
$wp_result = MilliRules::execute_rules(['WP']);

// Execute with custom packages
$custom_result = MilliRules::execute_rules(['PHP', 'Custom']);
```

### Context-Aware Package Selection

```php
<?php
add_action('init', function() {
    MilliRules::init();

    // Determine which packages to use
    $packages = ['PHP'];

    if (function_exists('add_action')) {
        $packages[] = 'WP';
    }

    if (class_exists('WooCommerce')) {
        $packages[] = 'WooCommerce';
    }

    // Execute with selected packages
    $result = MilliRules::execute_rules($packages);
}, 5);
```

---

## Context Manipulation

### Extending Context

```php
<?php
// Add custom data to context before execution
add_filter('millirules_context', function(Context $context) {
    $context['custom'] = [
        'api_key' => get_option('my_api_key'),
        'feature_flags' => get_option('feature_flags', []),
        'site_config' => get_site_config(),
    ];

    return $context;
});
```

### Context Transformation

```php
<?php
// Transform context for specific rules
Rules::register_action('with_transformed_context', function($args, Context $context) {
    // Add computed values
    $context['computed'] = [
        'is_business_hours' => check_business_hours(),
        'user_tier' => calculate_user_tier($context),
        'request_complexity' => analyze_request($context),
    ];

    // Execute sub-action with transformed context
    $callback = $args['callback'] ?? null;
    if (is_callable($callback)) {
        $callback($context);
    }
});
```

---

## Error Handling

### Graceful Degradation

```php
<?php
Rules::register_action('safe_api_call', function($args, Context $context) {
    try {
        $response = wp_remote_post($args['url'], [
            'body' => json_encode($args['data']),
            'headers' => ['Content-Type' => 'application/json'],
            'timeout' => 10,
        ]);

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        if ($status >= 400) {
            throw new Exception("API returned status {$status}");
        }

        // Success
        return json_decode(wp_remote_retrieve_body($response), true);

    } catch (Exception $e) {
        error_log('API Error: ' . $e->getMessage());

        // Fallback behavior
        $fallback = $args['fallback'] ?? null;
        if (is_callable($fallback)) {
            return $fallback($context);
        }

        return null;
    }
});
```

### Error Notification

```php
<?php
Rules::register_action('notify_on_error', function($args, Context $context) {
    try {
        // Risky operation
        perform_critical_operation($config);

    } catch (Exception $e) {
        // Log error
        error_log('Critical error: ' . $e->getMessage());

        // Notify admin
        wp_mail(
            get_option('admin_email'),
            'MilliRules Critical Error',
            "Error: {$e->getMessage()}\n\nContext: " . print_r($context, true)
        );

        // Store error for admin dashboard
        update_option('millirules_last_error', [
            'message' => $e->getMessage(),
            'time' => time(),
            'context' => $context,
        ]);
    }
});
```

---

## Testing Strategies

### Unit Testing Rules

```php
<?php
class MilliRulesTest extends WP_UnitTestCase {
    public function setUp(): void {
        parent::setUp();
        MilliRules::init();
    }

    public function test_api_cache_rule() {
        // Register test action
        Rules::register_action('test_cache', function($args, Context $context) {
            update_option('test_cache_called', true);
        });

        // Create rule
        Rules::create('test_api_cache')
            ->when()
                ->request_url('/api/test')
                ->request_method('GET')
            ->then()
                ->custom('test_cache')
            ->register();

        // Simulate request
        $_SERVER['REQUEST_URI'] = '/api/test';
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Execute
        $result = MilliRules::execute_rules();

        // Assert
        $this->assertEquals(1, $result['rules_matched']);
        $this->assertTrue(get_option('test_cache_called'));
    }
}
```

### Integration Testing

```php
<?php
function test_complete_workflow() {
    // Setup
    MilliRules::init();

    $executed_actions = [];

    Rules::register_action('track_execution', function($args, Context $context) use (&$executed_actions) {
        $executed_actions[] = $args['step'];
    });

    // Create multi-step rule
    Rules::create('workflow_test')
        ->when()->request_url('/test-workflow')
        ->then()
            ->custom('track_execution', ['step' => 'validate'])
            ->custom('track_execution', ['step' => 'process'])
            ->custom('track_execution', ['step' => 'complete'])
        ->register();

    // Execute
    $_SERVER['REQUEST_URI'] = '/test-workflow';
    MilliRules::execute_rules();

    // Verify execution order
    assert($executed_actions === ['validate', 'process', 'complete']);

    echo "Workflow test passed!\n";
}
```

---

## Best Practices Summary

### 1. Performance

- Order conditions from fastest to slowest
- Cache expensive operations
- Use early execution for caching/redirects
- Load rules only when needed

### 2. Debugging

- Enable debug logging in development
- Track execution statistics
- Use debug conditions to inspect context
- Monitor memory and execution time

### 3. Maintainability

- Use descriptive rule IDs
- Group related rules by feature
- Document complex logic
- Use consistent naming conventions

### 4. Error Handling

- Always validate input
- Provide fallback behaviors
- Log errors appropriately
- Notify admins of critical issues

### 5. Testing

- Write unit tests for custom conditions/actions
- Test complete rule workflows
- Test with different package combinations
- Simulate various environments

---

## Next Steps

- **[WordPress Integration Guide](wordpress-integration.md)** - WordPress-specific patterns
- **[API Reference](../reference/api.md)** - Complete method documentation
- **[Real-World Examples](examples.md)** - Complete working examples

---

**Ready to explore WordPress integration?** Continue to [WordPress Integration Guide](wordpress-integration.md) for WordPress-specific techniques and patterns.
