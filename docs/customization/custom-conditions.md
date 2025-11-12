---
title: 'Creating Custom Conditions'
post_excerpt: 'Learn how to extend MilliRules with custom conditions using callback functions or BaseCondition classes for powerful, reusable logic.'
---

# Creating Custom Conditions

While MilliRules provides comprehensive built-in conditions, you'll often need domain-specific logic. This guide shows you how to create custom conditions using callback functions or class-based implementations.

## Why Create Custom Conditions?

Custom conditions enable you to:

- Implement business-specific logic
- Check external APIs or services
- Validate complex data structures
- Integrate with third-party systems
- Create reusable condition libraries

## Callback-Based Custom Conditions

The simplest way to create custom conditions is using callback functions.

### Basic Callback Condition

```php
<?php
use MilliRules\Rules;

// Register custom condition
Rules::register_condition('is_weekend', function($context) {
    $day = date('N'); // 1 (Monday) to 7 (Sunday)
    return $day >= 6; // Saturday or Sunday
});

// Use in rule
Rules::create('weekend_special')
    ->when()
        ->custom('is_weekend')
        ->request_url('/shop/*')
    ->then()
        ->custom('apply_weekend_discount')
    ->register();
```

### Condition with Configuration

```php
<?php
// Register parameterized condition
Rules::register_condition('time_range', function($context, $config) {
    $current_hour = (int) date('H');
    $start = $config['start'] ?? 0;
    $end = $config['end'] ?? 23;

    return $current_hour >= $start && $current_hour <= $end;
});

// Use with configuration
Rules::create('business_hours')
    ->when()
        ->custom('time_range', ['start' => 9, 'end' => 17])
    ->then()
        ->custom('show_business_hours_message')
    ->register();
```

### Accessing Context

```php
<?php
Rules::register_condition('user_has_spent_minimum', function($context, $config) {
    $minimum = $config['minimum'] ?? 100;
    $user_id = $context['wp']['user']['id'] ?? 0;

    if (!$user_id) {
        return false;
    }

    // Check user's total spending (example)
    $total_spent = get_user_meta($user_id, 'total_spent', true) ?: 0;

    return $total_spent >= $minimum;
});

// Use in rule
Rules::create('vip_customer')
    ->when()
        ->is_user_logged_in()
        ->custom('user_has_spent_minimum', ['minimum' => 500])
    ->then()
        ->custom('show_vip_benefits')
    ->register();
```

---

## Class-Based Custom Conditions

For complex or reusable conditions, create classes extending `BaseCondition`.

### BaseCondition Overview

`BaseCondition` provides:
- Operator support (all 13 operators)
- Value comparison logic
- Consistent error handling
- Context access

```php
<?php
namespace MilliRules\Conditions;

abstract class BaseCondition implements ConditionInterface {
    protected $config;
    protected $context;

    public function __construct(array $config, array $context);
    abstract protected function get_actual_value(array $context);
    public function matches(array $context): bool;
    public static function compare_values($actual, $expected, string $operator = '='): bool;
}
```

### Basic Custom Condition Class

```php
<?php
namespace MyPlugin\Conditions;

use MilliRules\Conditions\BaseCondition;

class TimeRangeCondition extends BaseCondition {
    protected function get_actual_value(array $context) {
        return (int) date('H'); // Current hour (0-23)
    }

    public function get_type(): string {
        return 'time_range';
    }
}
```

**Usage**:
```php
<?php
// Register namespace
RuleEngine::register_namespace('Conditions', 'MyPlugin\Conditions');

// Use in rule (operators supported automatically)
Rules::create('business_hours')
    ->when()
        ->custom('time_range', ['value' => 9, 'operator' => '>='])
        ->custom('time_range', ['value' => 17, 'operator' => '<='])
    ->then()
        ->custom('show_business_message')
    ->register();
```

### Condition with Complex Logic

```php
<?php
namespace MyPlugin\Conditions;

use MilliRules\Conditions\BaseCondition;

class UserPurchaseCountCondition extends BaseCondition {
    protected function get_actual_value(array $context) {
        $user_id = $context['wp']['user']['id'] ?? 0;

        if (!$user_id) {
            return 0;
        }

        // Get user's purchase count
        return (int) get_user_meta($user_id, 'purchase_count', true);
    }

    public function get_type(): string {
        return 'user_purchase_count';
    }
}
```

**Usage with operators**:
```php
<?php
Rules::create('frequent_buyer')
    ->when()
        ->is_user_logged_in()
        ->custom('user_purchase_count', ['value' => 10, 'operator' => '>='])
    ->then()
        ->custom('show_loyalty_discount')
    ->register();
```

### Condition with Multiple Values

```php
<?php
namespace MyPlugin\Conditions;

use MilliRules\Conditions\BaseCondition;

class UserRoleCondition extends BaseCondition {
    protected function get_actual_value(array $context) {
        $user_id = $context['wp']['user']['id'] ?? 0;

        if (!$user_id) {
            return [];
        }

        $user = get_userdata($user_id);
        return $user ? $user->roles : [];
    }

    public function get_type(): string {
        return 'user_role';
    }
}
```

**Usage**:
```php
<?php
// Single role check
Rules::create('admin_only')
    ->when()
        ->custom('user_role', ['value' => 'administrator'])
    ->then()
        ->custom('show_admin_tools')
    ->register();

// Multiple roles (IN operator)
Rules::create('staff_members')
    ->when()
        ->custom('user_role', [
            'value' => ['administrator', 'editor', 'author'],
            'operator' => 'IN'
        ])
    ->then()
        ->custom('show_staff_area')
    ->register();
```

---

## Advanced Custom Conditions

### Condition with External API

```php
<?php
Rules::register_condition('api_status_check', function($context, $config) {
    $api_url = $config['url'] ?? '';
    $expected_status = $config['status'] ?? 200;

    if (!$api_url) {
        return false;
    }

    // Make API request (cache result to avoid repeated calls)
    $cache_key = 'api_status_' . md5($api_url);
    $status = get_transient($cache_key);

    if ($status === false) {
        $response = wp_remote_get($api_url, ['timeout' => 5]);

        if (is_wp_error($response)) {
            return false;
        }

        $status = wp_remote_retrieve_response_code($response);
        set_transient($cache_key, $status, 60); // Cache for 1 minute
    }

    return $status === $expected_status;
});

// Usage
Rules::create('check_service_availability')
    ->when()
        ->custom('api_status_check', [
            'url' => 'https://api.example.com/status',
            'status' => 200
        ])
    ->then()
        ->custom('enable_feature')
    ->register();
```

### Condition with Database Query

```php
<?php
Rules::register_condition('product_stock_level', function($context, $config) {
    global $wpdb;

    $product_id = $config['product_id'] ?? 0;
    $minimum = $config['minimum'] ?? 10;

    if (!$product_id) {
        return false;
    }

    $stock = $wpdb->get_var($wpdb->prepare(
        "SELECT stock_quantity FROM {$wpdb->prefix}products WHERE id = %d",
        $product_id
    ));

    return (int) $stock >= $minimum;
});

// Usage
Rules::create('low_stock_alert')
    ->when()
        ->custom('product_stock_level', [
            'product_id' => 123,
            'minimum' => 5
        ])
        ->match_none()  // Inverse logic
    ->then()
        ->custom('send_low_stock_notification')
    ->register();
```

### Condition with Caching

```php
<?php
Rules::register_condition('cached_condition', function($context, $config) {
    $cache_key = 'condition_' . md5(serialize($config));
    $cached_result = wp_cache_get($cache_key, 'millirules');

    if ($cached_result !== false) {
        return $cached_result;
    }

    // Expensive operation
    $result = perform_expensive_check($config);

    // Cache for 5 minutes
    wp_cache_set($cache_key, $result, 'millirules', 300);

    return $result;
});
```

---

## Condition Registration Patterns

### Global Registration

Register conditions globally during initialization:

```php
<?php
add_action('init', function() {
    MilliRules::init();

    // Register all custom conditions
    Rules::register_condition('is_weekend', 'check_weekend_callback');
    Rules::register_condition('time_range', 'check_time_range_callback');
    Rules::register_condition('user_level', 'check_user_level_callback');
}, 1);
```

### Conditional Registration

Register conditions only when needed:

```php
<?php
if (class_exists('WooCommerce')) {
    Rules::register_condition('cart_total', function($context, $config) {
        $minimum = $config['minimum'] ?? 0;
        return WC()->cart->get_total('') >= $minimum;
    });
}

if (function_exists('pll_current_language')) {
    Rules::register_condition('polylang_language', function($context, $config) {
        $language = $config['value'] ?? '';
        return pll_current_language() === $language;
    });
}
```

### Namespaced Registration

Organize conditions by namespace:

```php
<?php
// Register namespace for conditions
RuleEngine::register_namespace('Conditions', 'MyPlugin\Conditions');

// All condition classes in MyPlugin\Conditions namespace are now available
// MyPlugin\Conditions\UserLevelCondition → user_level
// MyPlugin\Conditions\ProductStockCondition → product_stock
```

---

## Best Practices

### 1. Return Boolean Values

```php
<?php
// ✅ Good - returns boolean
Rules::register_condition('is_valid', function($context, $config) {
    return some_check() === true;
});

// ❌ Bad - returns non-boolean
Rules::register_condition('is_valid', function($context, $config) {
    return some_check(); // May return string, int, etc.
});
```

### 2. Handle Missing Context

```php
<?php
// ✅ Good - checks context availability
Rules::register_condition('wp_condition', function($context, $config) {
    if (!isset($context['wp'])) {
        return false; // WordPress not available
    }

    $user_id = $context['wp']['user']['id'] ?? 0;
    return $user_id > 0;
});

// ❌ Bad - assumes context exists
Rules::register_condition('wp_condition', function($context, $config) {
    $user_id = $context['wp']['user']['id']; // May not exist!
    return $user_id > 0;
});
```

### 3. Validate Configuration

```php
<?php
// ✅ Good - validates configuration
Rules::register_condition('validated_condition', function($context, $config) {
    // Validate required config
    if (!isset($config['required_param'])) {
        error_log('Missing required_param in condition config');
        return false;
    }

    // Validate data types
    $minimum = absint($config['minimum'] ?? 0);

    return perform_check($config['required_param'], $minimum);
});
```

### 4. Optimize Performance

```php
<?php
// ✅ Good - caches expensive operations
Rules::register_condition('expensive_check', function($context, $config) {
    static $cache = [];
    $cache_key = md5(serialize($config));

    if (isset($cache[$cache_key])) {
        return $cache[$cache_key];
    }

    $result = expensive_operation($config);
    $cache[$cache_key] = $result;

    return $result;
});

// ✅ Good - early returns
Rules::register_condition('optimized_check', function($context, $config) {
    // Quick checks first
    if (!isset($config['value'])) {
        return false;
    }

    // Expensive checks last
    return expensive_operation($config['value']);
});
```

### 5. Use Descriptive Names

```php
<?php
// ✅ Good - clear, descriptive names
Rules::register_condition('user_has_active_subscription', ...);
Rules::register_condition('product_is_in_stock', ...);
Rules::register_condition('cart_contains_downloadable_items', ...);

// ❌ Bad - unclear names
Rules::register_condition('check', ...);
Rules::register_condition('validate', ...);
Rules::register_condition('test', ...);
```

---

## Testing Custom Conditions

### Unit Testing

```php
<?php
class CustomConditionTest extends WP_UnitTestCase {
    public function test_is_weekend_condition() {
        Rules::register_condition('is_weekend', function($context) {
            return date('N') >= 6;
        });

        // Create rule using condition
        Rules::create('test_rule')
            ->when()->custom('is_weekend')
            ->then()->custom('test_action')
            ->register();

        // Execute and check result
        $result = MilliRules::execute_rules();

        // Assert based on current day
        if (date('N') >= 6) {
            $this->assertGreaterThan(0, $result['rules_matched']);
        } else {
            $this->assertEquals(0, $result['rules_matched']);
        }
    }
}
```

### Manual Testing

```php
<?php
// Debug condition
Rules::register_condition('debug_condition', function($context, $config) {
    error_log('Condition config: ' . print_r($config, true));
    error_log('Context: ' . print_r($context, true));

    $result = your_condition_logic($context, $config);

    error_log('Condition result: ' . ($result ? 'true' : 'false'));

    return $result;
});
```

---

## Common Pitfalls

### 1. Not Returning Boolean

```php
<?php
// ❌ Wrong - returns string
Rules::register_condition('bad_condition', function($context, $config) {
    return 'yes'; // Should return boolean!
});

// ✅ Correct - returns boolean
Rules::register_condition('good_condition', function($context, $config) {
    return true;
});
```

### 2. Expensive Operations Without Caching

```php
<?php
// ❌ Wrong - expensive API call on every evaluation
Rules::register_condition('api_check', function($context, $config) {
    $response = wp_remote_get('https://api.example.com/check');
    return wp_remote_retrieve_response_code($response) === 200;
});

// ✅ Correct - caches API result
Rules::register_condition('api_check', function($context, $config) {
    $cached = get_transient('api_check_result');

    if ($cached !== false) {
        return $cached;
    }

    $response = wp_remote_get('https://api.example.com/check');
    $result = wp_remote_retrieve_response_code($response) === 200;

    set_transient('api_check_result', $result, 60);

    return $result;
});
```

### 3. Side Effects in Conditions

```php
<?php
// ❌ Wrong - modifies state in condition
Rules::register_condition('bad_condition', function($context, $config) {
    update_option('some_option', 'value'); // Side effect!
    return true;
});

// ✅ Correct - conditions are read-only
Rules::register_condition('good_condition', function($context, $config) {
    return get_option('some_option') === 'value'; // Read-only check
});
```

---

## Next Steps

- **[Creating Custom Actions](custom-actions.md)** - Build custom actions
- **[Creating Custom Packages](custom-packages.md)** - Package your conditions
- **[Advanced Usage](../advanced/usage.md)** - Advanced techniques
- **[Real-World Examples](../advanced/examples.md)** - See complete implementations

---

**Ready to create custom actions?** Continue to [Creating Custom Actions](custom-actions.md) to learn how to build powerful actions for your rules.
