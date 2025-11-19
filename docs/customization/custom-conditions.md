---
title: 'Creating Custom Conditions'
post_excerpt: 'Learn how to create custom conditions in MilliRules using callback functions or BaseCondition classes for flexible conditional logic.'
---

# Creating Custom Conditions

Custom conditions define the "when" logic that determines if a rule should execute. This guide covers creating and using custom conditions.

## Quick Start

### Using Registered Conditions

Once registered, conditions can be used via **dynamic method calls**:

```php
<?php
Rules::create('weekend_special')
    ->when()
        ->is_weekend()                      // Dynamic method
        ->time_in_range(9, 17)             // With parameters
        ->user_has_role('administrator')    // Single value
    ->then()->custom('action')
    ->register();
```

**How it works:**
- Method names convert from camelCase to snake_case
- `->isWeekend()` becomes `type='is_weekend'`
- `->userHasRole()` becomes `type='user_has_role'`
- **Name-based conditions** (header, param, cookie, constant): first arg = name, second = value, third = operator
- **Value-based conditions**: first arg = value, second = operator
- Operators auto-detect from value types when not specified

### Using custom() Method

For explicit operator control or complex configurations:

```php
<?php
->when()
    ->custom('user_purchase_count', [
        'value' => 10,
        'operator' => '>='
    ])
```

**When to use which:**
- **Dynamic methods**: Quick checks with auto-detected operators
- **`->custom()`**: Explicit operator control or complex configurations

## Registering Conditions

### Callback-Based Conditions

Quick and simple for straightforward checks:

```php
<?php
use MilliRules\Rules;
use MilliRules\Context;

// Simple boolean check
Rules::register_condition('is_weekend', function(Context $context, $config) {
    $day = date('N'); // 1 (Monday) to 7 (Sunday)
    return $day >= 6; // Saturday or Sunday
});

// With configuration
Rules::register_condition('time_in_range', function(Context $context, $config) {
    $current_hour = (int) date('H');
    $start = $config['start'] ?? $config['value'] ?? 0;
    $end = $config['end'] ?? $config['operator'] ?? 23;

    return $current_hour >= $start && $current_hour <= $end;
});
```

**Callback signature:**
```php
function(Context $context, array $config): bool
```

**Important:** Always return a boolean value.

### Class-Based Conditions

For complex logic or operator support:

```php
<?php
namespace MyPlugin\Conditions;

use MilliRules\Conditions\BaseCondition;
use MilliRules\Context;

class UserPurchaseCount extends BaseCondition
{
    protected function get_actual_value(Context $context): int
    {
        $context->load('user');
        $user_id = $context->get('user.id', 0);

        if (!$user_id) {
            return 0;
        }

        // Get purchase count from database
        return (int) get_user_meta($user_id, 'purchase_count', true);
    }

    protected function get_expected_value(): int
    {
        return (int) ($this->config['value'] ?? 0);
    }

    public function get_type(): string
    {
        return 'user_purchase_count';
    }
}
```

**Register with MilliRules:**
```php
<?php
use MilliRules\Rules;

Rules::register_condition('user_purchase_count', function($context, $config) {
    $condition = new \MyPlugin\Conditions\UserPurchaseCount($config, $context);
    return $condition->matches($context);
});
```

**Usage with operators:**
```php
<?php
Rules::create('vip_customers')
    ->when()
        ->user_purchase_count(10, '>=')  // Dynamic method with operator
        // Or explicit:
        ->custom('user_purchase_count', ['value' => 10, 'operator' => '>='])
    ->then()->custom('apply_discount')
    ->register();
```

## Operator Support

Custom conditions inheriting from `BaseCondition` automatically support all standard operators:

- `=`, `==` - Equality
- `!=`, `<>` - Not equal
- `>`, `>=`, `<`, `<=` - Comparison
- `LIKE`, `NOT LIKE` - Pattern matching
- `IN`, `NOT IN` - Array membership
- `REGEXP` - Regular expression
- `EXISTS`, `NOT EXISTS` - Value existence

See **[Operators Reference](../reference/operators.md)** for complete details.

### Auto-Detection

When using dynamic methods, operators are auto-detected:

```php
->user_age(18, '>=')       // Explicit operator
->user_email('*@gmail.com', 'LIKE')  // Pattern matching
->user_role(['admin', 'editor'], 'IN')  // Array check
```

## Configuration Reference

### Standard Config Keys

```php
[
    'type' => 'condition_type',  // Required: condition identifier
    'value' => 'expected_value', // Common: value to compare against
    'operator' => '=',           // Common: comparison operator (default: '=')
    // ... custom keys as needed
]
```

### Common Patterns

**Boolean check:**
```php
->is_weekend()
// Becomes: ['type' => 'is_weekend']
```

**Single value (equality):**
```php
->user_role('administrator')
// Becomes: ['type' => 'user_role', 'value' => 'administrator', 'operator' => '=']
```

**Value with operator:**
```php
->user_age(18, '>=')
// Becomes: ['type' => 'user_age', 'value' => 18, 'operator' => '>=']
```

**Complex configuration:**
```php
->custom('advanced_check', [
    'value' => 'expected',
    'operator' => 'LIKE',
    'case_sensitive' => false,
    'cache' => true
])
```

## Best Practices

### 1. Always Return Boolean

```php
// ✅ Good - explicit boolean return
Rules::register_condition('is_valid', function($context, $config) {
    $value = $context->get('custom.value');
    return (bool) $value;  // Explicit cast
});

// ❌ Bad - may return non-boolean
Rules::register_condition('is_valid', function($context, $config) {
    return $context->get('custom.value');  // Could be string, int, null...
});
```

### 2. Avoid Side Effects

```php
// ✅ Good - pure check
Rules::register_condition('has_permission', function($context, $config) {
    return current_user_can($config['value'] ?? 'read');
});

// ❌ Bad - modifies state
Rules::register_condition('has_permission', function($context, $config) {
    update_option('last_check', time());  // Don't do this!
    return current_user_can($config['value'] ?? 'read');
});
```

### 3. Handle Missing Data

```php
Rules::register_condition('user_has_meta', function($context, $config) {
    $context->load('user');
    $user_id = $context->get('user.id', 0);

    // Handle case where user is not logged in
    if (!$user_id) {
        return false;
    }

    $meta_key = $config['value'] ?? '';
    return !empty(get_user_meta($user_id, $meta_key, true));
});
```

### 4. Use Type Hints

```php
use MilliRules\Context;

Rules::register_condition('my_check', function(Context $context, array $config): bool {
    // Full IDE autocomplete and type safety
    $value = $context->get('custom.key');
    return $value === ($config['value'] ?? null);
});
```

## Common Pitfalls

### Must Return Boolean

```php
// ❌ Wrong - returns string
Rules::register_condition('check_status', function($context, $config) {
    return get_option('site_status');  // Returns 'active', 'inactive', etc.
});

// ✅ Correct - returns boolean
Rules::register_condition('is_active', function($context, $config) {
    return get_option('site_status') === 'active';
});
```

### Don't Cache Incorrectly

```php
// ❌ Bad - static cache persists across requests
static $cache = null;
Rules::register_condition('expensive_check', function($context, $config) use (&$cache) {
    if ($cache === null) {
        $cache = expensive_calculation();
    }
    return $cache > 10;  // Stale data on subsequent requests!
});

// ✅ Good - use transients or request-scoped caching
Rules::register_condition('expensive_check', function($context, $config) {
    $result = get_transient('expensive_check_result');
    if (false === $result) {
        $result = expensive_calculation();
        set_transient('expensive_check_result', $result, 60);
    }
    return $result > 10;
});
```

### Don't Perform Actions in Conditions

```php
// ❌ Wrong - sends email every time condition is checked
Rules::register_condition('notify_admin', function($context, $config) {
    wp_mail('admin@example.com', 'Check ran', 'Condition checked');
    return true;
});

// ✅ Correct - conditions check, actions do things
Rules::register_condition('should_notify', function($context, $config) {
    return $context->get('user.login') === 'special_user';
});

// Then use an action to send email when condition matches
```

## Next Steps

- **[Custom Actions](custom-actions.md)** - Implement action logic
- **[Built-in Conditions Reference](../reference/conditions.md)** - See available conditions
- **[Operators Reference](../reference/operators.md)** - Complete operator guide
- **[API Reference](../reference/api.md)** - Complete API documentation
