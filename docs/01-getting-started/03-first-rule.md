---
title: 'Creating Your First Rule'
post_excerpt: 'Learn how to create your first MilliRules rule with this hands-on tutorial featuring a complete WordPress example.'
---

# Creating Your First Rule

Now that you have MilliRules installed and initialized, let's create your first rule. This hands-on tutorial will walk you through building a simple but functional rule that logs admin dashboard access in WordPress.

## Anatomy of a Rule

Every MilliRules rule consists of three main parts:

1. **Rule Creation** - Define the rule with an ID and type
2. **Conditions** (when) - Specify when the rule should execute
3. **Actions** (then) - Define what happens when conditions are met

Here's the basic structure:

```php
<?php
use MilliRules\Rules;

Rules::create('rule_id')
    ->when()
        // Add conditions here
    ->then()
        // Add actions here
    ->register();
```

## Your First Rule: Log Admin Access

Let's create a rule that logs a message whenever someone accesses the WordPress admin dashboard.

### Step 1: Initialize MilliRules

Add this to your plugin's main file or `functions.php`:

```php
<?php
use MilliRules\MilliRules;
use MilliRules\Rules;

// Initialize the rule engine
MilliRules::init();
```

### Step 2: Create Your First Rule

```php
<?php
use MilliRules\Rules;

// Create a rule that runs on WordPress 'init' hook
Rules::create('log_admin_access', 'wp')
    ->title('Log Admin Dashboard Access') // Optional
    ->order(10) // Optional
    ->when()
        ->request_url('/wp-admin/*')  // Matches any admin URL
        ->is_user_logged_in()         // User must be logged in
    ->then()
        ->custom('log_message', ['value' => 'Admin dashboard accessed'])
    ->register();
```

### Step 3: Register the Custom Action

Since `log_message` is a custom action, let's register it:

```php
<?php
use MilliRules\Rules;
use MilliRules\Context;

Rules::register_action('log_message', function($args, Context $context) {
    $message = $args['message'] ?? $args[0] ?? 'No message';
    error_log('MilliRules: ' . $message);
});
```

### Complete Example

Here's everything together in a WordPress plugin context:

```php
<?php
/**
 * Plugin Name: My First MilliRules Plugin
 * Description: Logs admin dashboard access using MilliRules
 * Version: 1.0.0
 */

require_once __DIR__ . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;
use MilliRules\Context;

// Initialize MilliRules
MilliRules::init();

// Register custom log action
Rules::register_action('log_message', function($args, Context $context) {
    $message = $args['message'] ?? $args[0] ?? 'No message';
    error_log('MilliRules: ' . $message);
});

// Create the rule
Rules::create('log_admin_access', 'wp')
    ->title('Log Admin Dashboard Access')
    ->order(10)
    ->when()
        ->request_url('/wp-admin/*')
        ->is_user_logged_in()
    ->then()
        ->custom('log_message', ['value' => 'Admin dashboard accessed'])
    ->register();
```

> [!TIP]
> Check your error log (usually in `wp-content/debug.log` if `WP_DEBUG_LOG` is enabled) to see the logged messages when you access the WordPress admin dashboard.

## Understanding What Just Happened

Let's break down what this rule does:

1. **Rule Creation**: `Rules::create('log_admin_access', 'wp')` creates a new rule with ID `log_admin_access` and type `wp` (WordPress)

2. **Metadata**: `->title()` and `->order()` add descriptive information and control execution sequence

3. **Conditions**: The `->when()` builder defines conditions that must be met:
   - Request URL matches `/wp-admin/*` (wildcard pattern)
   - User is logged in

4. **Actions**: The `->then()` builder defines what happens when conditions match:
   - Log a message via the custom `log_message` action

5. **Registration**: `->register()` registers the rule with MilliRules

## Simple Condition Examples

Here are some common conditions you can use:

### URL Matching

```php
<?php
// Exact match
->when()->request_url('/contact')

// Wildcard pattern
->when()->request_url('/blog/*')

// Multiple URLs (OR logic)
->when_any()
    ->request_url('/about')
    ->request_url('/contact')
```

### HTTP Method Checking

```php
<?php
// Check for POST requests
->when()->request_method('POST')

// Check for GET or HEAD (using array)
->when()->request_method(['GET', 'HEAD'])
```

### User Status (WordPress)

```php
<?php
// User must be logged in
->when()->is_user_logged_in()

// User must NOT be logged in
->when()->is_user_logged_in(false)
```

### Cookie Checking

```php
<?php
// Check if cookie exists
->when()->cookie('session_id')

// Check cookie value
->when()->cookie('user_preference', 'dark_mode')
```

## Simple Action Examples

### Logging

```php
<?php
// Register a logging action
Rules::register_action('log', function($args, Context $context) {
    error_log('MilliRules: ' . ($args['value'] ?? ''));
});

// Use it in a rule
->then()->custom('log', ['value' => 'Something happened'])
```

### Redirects

```php
<?php
// Register a redirect action
Rules::register_action('redirect', function($args, Context $context) {
    $url = $args['url'] ?? '/';
    wp_redirect($url);
    exit;
});

// Use it in a rule
->then()->custom('redirect', ['url' => '/login'])
```

### Setting Headers

```php
<?php
// Register a cache header action
Rules::register_action('set_cache', function($args, Context $context) {
    $duration = $args['duration'] ?? 3600;
    header('Cache-Control: max-age=' . $duration);
});

// Use it in a rule
->then()->custom('set_cache', ['duration' => 7200])
```

## Running Rules

By default, WordPress rules execute automatically on their specified hook. You don't need to manually trigger execution.

However, you can also execute rules manually:

```php
<?php
use MilliRules\MilliRules;

// Execute all registered rules
$result = MilliRules::execute_rules();

// Check execution statistics
echo 'Rules processed: ' . $result['rules_processed'] . "\n";
echo 'Rules matched: ' . $result['rules_matched'] . "\n";
echo 'Actions executed: ' . $result['actions_executed'] . "\n";
```

> [!IMPORTANT]
> WordPress rules registered with `->on('hook_name')` execute automatically when that hook fires. You only need manual execution for PHP-only rules or when testing.

## Common Beginner Mistakes

### 1. Forgetting to Call `register()`

```php
<?php
// ❌ Wrong - rule never registered
Rules::create('my_rule')
    ->when()->request_url('/test')
    ->then()->custom('action');
// Missing ->register()

// ✅ Correct
Rules::create('my_rule')
    ->when()->request_url('/test')
    ->then()->custom('action')
    ->register();
```

### 2. Using Undefined Custom Actions

```php
<?php
// ❌ Wrong - 'send_email' not registered
Rules::create('notify')
    ->when()->request_url('/contact')
    ->then()->custom('send_email')  // Not defined!
    ->register();

// ✅ Correct - register action first
Rules::register_action('send_email', function($args, Context $context) {
    // Email sending logic here
});

Rules::create('notify')
    ->when()->request_url('/contact')
    ->then()->custom('send_email')
    ->register();
```

### 3. Incorrect Condition Logic

```php
<?php
// ❌ Wrong - using when() with single condition that should be OR
Rules::create('public_access')
    ->when()  // This uses AND logic by default
        ->request_url('/public')
        ->request_url('/open')  // Can't match both!
    ->then()->custom('grant_access')
    ->register();

// ✅ Correct - use when_any() for OR logic
Rules::create('public_access')
    ->when_any()  // Use OR logic
        ->request_url('/public')
        ->request_url('/open')
    ->then()->custom('grant_access')
    ->register();
```

## Next Steps

Congratulations! You've created your first MilliRules rule. Now you're ready to explore more advanced features:

### Learn Core Concepts
- **[Core Concepts](../02-core-concepts/01-concepts.md)** - Understand the architecture and how rules work internally
- **[Packages System](../02-core-concepts/02-packages.md)** - Learn about the package system and how to use it
- **[Building Rules](../02-core-concepts/03-building-rules.md)** - Master the fluent API and advanced rule patterns

### Explore Available Features
- **[Operators](../02-core-concepts/04-operators.md)** - Learn about pattern matching and comparison operators
- **[Placeholders](../02-core-concepts/05-placeholders.md)** - Use dynamic values in your actions
- **[Built-in Conditions](../05-reference/01-conditions.md)** - Discover all available conditions

### Build Custom Components
- **[Custom Conditions](../03-customization/01-custom-conditions.md)** - Create your own condition types
- **[Custom Actions](../03-customization/02-custom-actions.md)** - Build custom action handlers
- **[Custom Packages](../03-customization/03-custom-packages.md)** - Extend MilliRules with custom packages

---

**Ready to dive deeper?** Continue to [Core Concepts](../02-core-concepts/01-concepts.md) to understand the fundamental architecture of MilliRules.
