---
post_title: 'Getting Started with MilliRules'
post_excerpt: 'Learn how to install MilliRules and create your first rule with this step-by-step guide for WordPress and PHP developers.'
---

# Getting Started with MilliRules

MilliRules is a powerful, flexible rule engine for PHP and WordPress that lets you create conditional logic using an elegant fluent API. Whether you're building a WordPress plugin or a framework-agnostic PHP application, MilliRules makes it easy to implement complex business rules without tangling your code with if-else statements.

## What is MilliRules?

MilliRules allows you to define rules that automatically execute actions when specific conditions are met. Think of it as a sophisticated "if-then" system that:

- **Separates logic from code** - Define rules independently of your application logic
- **Works everywhere** - Use in WordPress, Laravel, Symfony, or any PHP 7.4+ project
- **Provides a fluent API** - Write readable, chainable code that's easy to understand
- **Extends easily** - Add custom conditions, actions, and packages for your needs

## Prerequisites

Before installing MilliRules, ensure you have:

- **PHP 7.4 or higher** - MilliRules requires PHP 7.4+
- **Composer** - For dependency management
- **(Optional) WordPress 5.0+** - If using WordPress-specific features

## Installation via Composer

MilliRules is installed via Composer. Run this command in your project directory:

```bash
composer require MilliPress/MilliRules
```

### Autoloading

After installation, include Composer's autoloader in your project:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';
```

## Initializing MilliRules

Before creating rules, you need to initialize MilliRules. This registers the available packages (PHP and WordPress) and prepares the rule engine.

### Basic Initialization

For most WordPress installations, use the simple initialization:

```php
<?php
use MilliRules\MilliRules;

// Initialize with auto-detected packages
MilliRules::init();
```

This automatically:
- Registers the **PHP package** (always available)
- Registers the **WordPress package** (if WordPress is detected)
- Loads both packages if they're available

### Framework-Agnostic Usage

If you're using MilliRules outside WordPress, it will automatically use only the PHP package:

```php
<?php
use MilliRules\MilliRules;

// In a non-WordPress environment, only PHP package loads
MilliRules::init();
```

### Custom Package Selection

You can explicitly specify which packages to load:

```php
<?php
use MilliRules\MilliRules;

// Load only the PHP package (useful for early execution)
MilliRules::init(['PHP']);

// Or load specific packages with custom instances
$custom_package = new MyCustomPackage();
MilliRules::init(null, [$custom_package]);
```

> [!NOTE]
> The first parameter accepts package names as strings to load specific packages. The second parameter accepts PackageInterface instances to register. Using `null` for both parameters tells MilliRules to register and auto-load default packages.

## Your First Rule

Let's create a simple rule that logs information when someone accesses your WordPress admin dashboard.

### Step 1: Initialize MilliRules

Add this to your plugin's main file or `functions.php`:

```php
<?php
use MilliRules\MilliRules;
use MilliRules\Rules;

// Initialize the rule engine
add_action('init', function() {
    MilliRules::init();
}, 1); // Priority 1 ensures rules are registered early
```

### Step 2: Create Your First Rule

```php
<?php
use MilliRules\Rules;

// Create a rule that runs on WordPress 'init' hook
Rules::create('log_admin_access', 'wp')
    ->title('Log Admin Dashboard Access')
    ->order(10)
    ->when()
        ->request_url('/wp-admin/*')  // Matches any admin URL
        ->is_user_logged_in()          // User must be logged in
    ->then()
        ->custom('log_message', ['value' => 'Admin dashboard accessed'])
    ->register();
```

### Step 3: Register the Custom Action

Since `log_message` is a custom action, let's register it:

```php
<?php
use MilliRules\Rules;

Rules::register_action('log_message', function($context, $config) {
    $message = $config['value'] ?? 'No message';
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

// Initialize MilliRules early
add_action('init', function() {
    MilliRules::init();

    // Register custom log action
    Rules::register_action('log_message', function($context, $config) {
        $message = $config['value'] ?? 'No message';
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
}, 1);
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

## Rule Execution

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

## Next Steps

Now that you've created your first rule, you're ready to explore more advanced features:

- **[Core Concepts](../core/concepts.md)** - Understand rules, conditions, actions, and the package system
- **[Building Rules](../core/building-rules.md)** - Master the fluent API and rule builder
- **[Built-in Conditions](../reference/conditions.md)** - Discover all available conditions
- **[Operators](../reference/operators.md)** - Learn about pattern matching and comparison operators

## Common Pitfalls

### 1. Forgetting to Initialize

```php
<?php
// ❌ Wrong - rules created before initialization
Rules::create('my_rule')->when()->request_url('/test')->then()->register();
MilliRules::init();

// ✅ Correct - initialize first
MilliRules::init();
Rules::create('my_rule')->when()->request_url('/test')->then()->register();
```

### 2. Incorrect WordPress Hook Priority

```php
<?php
// ❌ Wrong - initializing too late
add_action('init', function() {
    MilliRules::init();
}, 999); // Rules may miss early hooks

// ✅ Correct - initialize early
add_action('init', function() {
    MilliRules::init();
}, 1); // Priority 1 ensures early initialization
```

### 3. Missing Autoloader

```php
<?php
// ❌ Wrong - missing autoloader
use MilliRules\MilliRules;
MilliRules::init(); // Fatal error: Class not found

// ✅ Correct - include autoloader first
require_once __DIR__ . '/vendor/autoload.php';
use MilliRules\MilliRules;
MilliRules::init();
```

## Troubleshooting

### Rules Not Executing

1. **Check if MilliRules is initialized**: Make sure `MilliRules::init()` is called before creating rules
2. **Verify rule is registered**: Add `error_log()` calls to confirm your rule registration code runs
3. **Check WordPress hook timing**: Ensure your rules are created before the hooks they target fire
4. **Enable debug logging**: Check your error log for MilliRules-related messages

### Package Not Available Error

If you see "Package not available" errors:

1. **WordPress package**: Ensure WordPress is fully loaded before initializing MilliRules
2. **Custom packages**: Verify your custom package's `is_available()` method returns `true`
3. **Check dependencies**: Ensure required packages are loaded first

### Getting Help

- Review the [API Reference](../reference/api.md) for detailed method documentation
- Check [Real-World Examples](../advanced/examples.md) for complete working code
- Examine your error logs for detailed error messages

## Best Practices

1. **Initialize early** - Call `MilliRules::init()` as early as possible in your application
2. **Use descriptive IDs** - Give your rules meaningful, unique identifiers
3. **Add titles** - Always add titles to make debugging easier
4. **Order matters** - Use the `->order()` method to control execution sequence
5. **Test incrementally** - Start with simple rules and add complexity gradually
6. **Check logs** - Monitor your error logs to catch issues early

---

**Ready to dive deeper?** Continue to [Core Concepts](../core/concepts.md) to understand the fundamental architecture of MilliRules.
