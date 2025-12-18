---
title: 'Creating Custom Conditions'
post_excerpt: 'Learn how to create custom conditions in MilliRules using callback functions or BaseCondition classes for flexible conditional logic.'
menu_order: 10
---

# Creating Custom Conditions

Custom conditions define the "when" logic that determines if a rule should execute. This guide covers registering and using custom conditions.

## Quick Start

```php

use MilliRules\Rules;
use MilliRules\Context;

// 1. Register reusable condition
Rules::register_condition('is_weekend', function ($args, Context $context) {
    // Reusable logic receiving args + context
    $day = date('N'); // 1 (Monday) to 7 (Sunday)
    return $day >= 6; // Saturday or Sunday
});

// 2. Create Rule
Rules::create('weekend_special')
    ->when()
        // Option A: Inline custom condition without registration
        ->custom('is_business_hours', function (Context $context) {
            $hour = (int) date('H');
            return $hour >= 9 && $hour <= 17;
        })

        // Option B: Call registered condition via magic method
        ->is_weekend()

        // Option C: Call registered condition via custom()
        ->custom('is_weekend')
    ->then()
        ->custom('apply_discount')
    ->register();
```

## Registering Conditions

### Choosing the Right Registration Method

MilliRules offers four ways to define custom conditions. Choose based on your needs:

| Method                       | Best For                          | Reusable?  | Operator Support?              |
|------------------------------|-----------------------------------|------------|--------------------------------|
| **Inline with `->custom()`** | One-off checks                    | ❌ No       | ❌ No                           |
| **Callback Registration**    | Simple boolean checks             | ✅ Yes      | ❌ No                           |
| **Namespace Registration**   | Complex conditions with operators | ✅ Yes      | ✅ Yes (via BaseCondition)      |
| **Manual Wrapper**           | Advanced use cases                | ✅ Yes      | ✅ Yes (if using BaseCondition) |

**Recommendation:** Start with inline `->custom()` for one-off checks. Use callback registration for reusable simple checks. Use namespace registration for complex conditions with operator support.

---

### Method 1: Inline with `->custom()` (Simplest - One-Off Checks)

**Best for:** Quick one-off conditions that are only used in a single rule.

Define the condition directly in the rule using a callback:

```php
use MilliRules\Rules;
use MilliRules\Context;

Rules::create('business_hours_only')
    ->when()
        ->custom('is_business_hours', function(Context $context) {
            // One-off condition logic right here
            $hour = (int) date('H');
            return $hour >= 9 && $hour <= 17;
        })
    ->then()
        ->custom('process_request')
    ->register();
```

**Note:** Inline callbacks receive only the `Context` parameter (not `$args`), since arguments are redundant for inline-defined conditions. To access context data, use `$context->get('key')`.

**Example with context access:**

```php
Rules::create('premium_user_check')
    ->when()
        ->custom('is_premium', function(Context $context) {
            $user = $context->get('user.id');
            $status = get_user_meta($user, 'account_status', true);

            return $status === 'premium';
        })
    ->then()
        ->custom('enable_features')
    ->register();
```

**Pros:**
- ✅ Very simple - no separate registration step
- ✅ Perfect for one-off checks
- ✅ Quick to write and test
- ✅ Clean signature - only receives Context

**Cons:**
- ❌ Not reusable across multiple rules
- ❌ No operator support
- ❌ Harder to test in isolation

---

### Method 2: Callback Registration (Reusable Simple Checks)

**Best for:** Reusable boolean checks across multiple rules, simple logic without operators.

Register once, use everywhere:

```php
use MilliRules\Rules;
use MilliRules\Context;

// Register once at plugin initialization
Rules::register_condition('is_weekend', function($args, Context $context) {
    $day = date('N'); // 1 (Monday) to 7 (Sunday)
    return $day >= 6; // Saturday or Sunday
});

// With configuration
Rules::register_condition('time_in_range', function($args, Context $context) {
    $current_hour = (int) date('H');
    $start = $args[0] ?? 0;
    $end = $args[1] ?? 23;

    return $current_hour >= $start && $current_hour <= $end;
});

// Access context data
Rules::register_condition('user_has_role', function($args, Context $context) {
    $context->load('user');
    $required_role = $args['value'] ?? $args[0] ?? '';
    $user_roles = $context->get('user.roles', []);

    return in_array($required_role, $user_roles, true);
});
```

**Then use in any rule:**

```php
Rules::create('weekend_special')
    ->when()
        ->is_weekend()
        ->time_in_range(9, 17)
        ->user_has_role('customer')
    ->then()
        ->custom('apply_discount')
    ->register();
```

**Pros:**
- ✅ Reusable across all rules
- ✅ Simple to register and use
- ✅ Good for boolean checks

**Cons:**
- ❌ No operator support (must implement manually)
- ❌ Harder to organize many conditions

---

### Method 3: Namespace Registration (Best for Classes)

**Best for:** Complex conditions with operator support, reusable logic, testable code.

Register an entire namespace once and all condition classes are auto-discovered:

```php
use MilliRules\Rules;

// One-time registration at plugin initialization
Rules::register_namespace('Conditions', 'MyPlugin\Conditions');
```

**Create your condition class:**

```php
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
        return 'user_purchase_count';  // Used for auto-discovery
    }
}
```

**How it works:**
- The class name `UserPurchaseCount` is converted to `user_purchase_count`
- MilliRules finds the class automatically via `get_type()`
- No need to manually register each condition
- Supports all operators (=, !=, >, >=, <, <=, LIKE, IN, REGEXP, EXISTS, IS)
- Access configuration via `$this->config`

**Usage:**
```php
Rules::create('vip_customers')
    ->when()
        // Both calling styles work identically
        ->user_purchase_count(10, '>=')  // Auto-discovered, operator supported
        // OR
        ->custom('user_purchase_count', ['value' => 10, 'operator' => '>='])
    ->then()
        ->custom('apply_vip_discount')
    ->register();
```

---

### Method 4: Manual Wrapper (Advanced - Rarely Needed)

**Only use when:** Namespace registration isn't suitable (dynamic class names, runtime conditions, etc.)

```php
use MilliRules\Rules;

// ⚠️ Avoid this if possible - creates type duplication
Rules::register_condition('user_purchase_count', function($args, Context $context) {
    $condition = new \MyPlugin\Conditions\UserPurchaseCount($args, $context);
    return $condition->matches($context);
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

## Using Registered Conditions

Once registered, conditions can be used via **dynamic method calls**:

```php
Rules::create('special_offer')
    ->when()
        ->is_weekend()                      // Dynamic method
        ->time_in_range(9, 17)              // With parameters
        ->user_has_role('customer')         // Single value
    ->then()
        ->custom('send_offer')
    ->register();
```

### Argument Patterns

Both `->condition_name()` and `->custom()` accept identical argument formats:

**No parameters (boolean check):**
```php
->is_weekend()
->custom('is_weekend')
// Both result in: ['type' => 'is_weekend']
```

**Single value (equality check):**
```php
->user_role('administrator')
->custom('user_role', 'administrator')
// Both result in: ['type' => 'user_role', 'value' => 'administrator', 'operator' => '=']
```

**Value with operator:**
```php
->user_age(18, '>=')
->custom('user_age', ['value' => 18, 'operator' => '>='])
// Both result in: ['type' => 'user_age', 'value' => 18, 'operator' => '>=']
```

**Multiple positional arguments:**
```php
->time_in_range(9, 17)
// Result in: ['type' => 'time_in_range', 0 => 9, 1 => 17]
// Access via: $args[0], $args[1]
```

**When to use which:**
- **Dynamic methods**: Shorter syntax when method name is the condition type
- **`->custom()`**: When condition type needs to be dynamic or passed as variable

## Operator Support

Custom conditions inheriting from `BaseCondition` automatically support all standard operators:

- `=`, `==` - Equality
- `!=`, `<>` - Not equal
- `>`, `>=`, `<`, `<=` - Comparison
- `LIKE`, `NOT LIKE` - Pattern matching
- `IN`, `NOT IN` - Array membership
- `REGEXP` - Regular expression
- `EXISTS`, `NOT EXISTS` - Value existence
- `IS` - Boolean strict comparison

See **[Operators Reference](../02-core-concepts/04-operators.md)** for complete details.

### Auto-Detection

When using dynamic methods, operators are auto-detected from value types:

```php
->user_age(18)                           // Auto: '=' for scalar
->user_age(18, '>=')                     // Explicit: '>='
->user_email('*@gmail.com')              // Auto: 'LIKE' for wildcard
->user_role(['admin', 'editor'])         // Auto: 'IN' for array
->is_logged_in(true)                     // Auto: 'IS' for boolean
```

## Configuration Reference

### Standard Config Keys

```php
[
    'type' => 'condition_type',  // Required: condition identifier
    'value' => 'expected_value', // Common: value to compare against
    'operator' => '=',           // Common: comparison operator (default: '=')
    'name' => 'field_name',      // For name-based conditions (header, param, etc.)
    // ... custom keys as needed
]
```

### Common Patterns

**Boolean check:**
```php
->is_weekend()
// Becomes: ['type' => 'is_weekend', 'operator' => 'IS']
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

**Name-based condition:**
```php
->request_header('User-Agent', '*Chrome*')
// Becomes: ['type' => 'request_header', 'name' => 'User-Agent', 'value' => '*Chrome*', 'operator' => 'LIKE']
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
Rules::register_condition('is_valid', function($args, Context $context) {
    $value = $context->get('custom.value');
    return (bool) $value;  // Explicit cast
});

// ❌ Bad - may return non-boolean
Rules::register_condition('is_valid', function($args, Context $context) {
    return $context->get('custom.value');  // Could be string, int, null...
});
```

### 2. Avoid Side Effects

```php
// ✅ Good - pure check
Rules::register_condition('has_permission', function($args, Context $context) {
    return current_user_can($args['value'] ?? 'read');
});

// ❌ Bad - modifies state
Rules::register_condition('has_permission', function($args, Context $context) {
    update_option('last_check', time());  // Don't do this!
    return current_user_can($args['value'] ?? 'read');
});
```

### 3. Handle Missing Data Gracefully

```php
Rules::register_condition('user_has_meta', function($args, Context $context) {
    $context->load('user');
    $user_id = $context->get('user.id', 0);

    // Handle case where user is not logged in
    if (!$user_id) {
        return false;
    }

    $meta_key = $args['value'] ?? $args[0] ?? '';
    return !empty(get_user_meta($user_id, $meta_key, true));
});
```

### 4. Use Type Hints

```php
use MilliRules\Context;

Rules::register_condition('my_check', function(array $args, Context $context): bool {
    // Full IDE autocomplete and type safety
    $value = $context->get('custom.key');
    return $value === ($args['value'] ?? null);
});
```

## Common Pitfalls

### Must Return Boolean

```php
// ❌ Wrong - returns string
Rules::register_condition('check_status', function($args, Context $context) {
    return get_option('site_status');  // Returns 'active', 'inactive', etc.
});

// ✅ Correct - returns boolean
Rules::register_condition('is_active', function($args, Context $context) {
    return get_option('site_status') === 'active';
});
```

### Don't Cache Incorrectly

```php
// ❌ Bad - static cache persists across requests
static $cache = null;
Rules::register_condition('expensive_check', function($args, Context $context) use (&$cache) {
    if ($cache === null) {
        $cache = expensive_calculation();
    }
    return $cache > 10;  // Stale data on subsequent requests!
});

// ✅ Good - use transients or request-scoped caching
Rules::register_condition('expensive_check', function($args, Context $context) {
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
Rules::register_condition('notify_admin', function($args, Context $context) {
    wp_mail('admin@example.com', 'Check ran', 'Condition checked');
    return true;
});

// ✅ Correct - conditions check, actions do things
Rules::register_condition('should_notify', function($args, Context $context) {
    return $context->get('user.login') === 'special_user';
});

// Then use an action to send email when condition matches
```

## Next Steps

- **[Custom Actions](02-custom-actions.md)** - Implement action logic
- **[Built-in Conditions Reference](../05-reference/01-conditions.md)** - See available conditions
- **[Operators Reference](../02-core-concepts/04-operators.md)** - Complete operator guide
- **[API Reference](../05-reference/03-api.md)** - Complete API documentation
