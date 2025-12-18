---
title: 'Quick Start Guide'
post_excerpt: 'Get MilliRules up and running in minutes with this step-by-step installation and initialization guide.'
menu_order: 20
---

# Quick Start Guide

This guide will help you install and initialize MilliRules in just a few minutes. Whether you're using WordPress or a standalone PHP application, you'll be ready to create your first rule quickly.

## Prerequisites

Before installing MilliRules, ensure you have:

- **PHP 7.4 or higher**
- **Composer** - For dependency management
- **(Optional) WordPress 5.0+** - If using WordPress-specific features

## Installation via Composer

MilliRules is installed via Composer. Run this command in your project directory:

```bash
composer require MilliPress/MilliRules
```

This will download MilliRules and all its dependencies into your `vendor/` directory.

## Initializing MilliRules

Before creating rules, you need to initialize MilliRules. This registers the available packages (PHP and WordPress) and prepares the rule engine.

### Basic Initialization

For most installations, whether you are using WordPress or a standalone PHP application, use the simple initialization:

```php
use MilliRules\MilliRules;

// Initialize with auto-detected packages
MilliRules::init();
```

This automatically detects your environment:
- In **WordPress**, it registers and loads both the PHP and WordPress packages.
- In **Framework-agnostic** environments, it automatically loads only the PHP package.

### Custom Package Selection

You can explicitly specify which packages to load:

```php
use MilliRules\MilliRules;

// Load only the PHP package (useful for early execution)
MilliRules::init(['PHP']);

// Or load specific packages with custom instances
$custom_package = new MyCustomPackage();
MilliRules::init(null, [$custom_package]);
```

> [!NOTE]
> The first parameter accepts package names as strings to load specific packages. The second parameter accepts PackageInterface instances to register. Using `null` for both parameters tells MilliRules to register and auto-load default packages.

## Verifying Installation

To verify that MilliRules is properly installed and initialized, you can check the loaded packages:

```php
use MilliRules\MilliRules;

// Initialize MilliRules
MilliRules::init();

// Check loaded packages
$packages = MilliRules::get_loaded_packages();
error_log('Loaded packages: ' . print_r($packages, true));
```

If everything is working correctly, you should see the PHP package (and WordPress package if in WordPress environment) in your error log.

## Common Pitfalls

### 1. Forgetting to Initialize

```php
// ❌ Wrong - rules created before initialization
Rules::create('my_rule')->when()->request_url('/test')->then()->register();
MilliRules::init();

// ✅ Correct - initialize first
MilliRules::init();
Rules::create('my_rule')->when()->request_url('/test')->then()->register();
```

### 2. Incorrect WordPress Hook Priority

```php
// ❌ Wrong - initializing too late
add_action('init', function() {
    MilliRules::init();
}, 999); // Rules may miss early hooks

// ✅ Correct - initialize early or at top level
MilliRules::init();
```

### 3. Missing Autoloader

```php
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

- Review the [API Reference](../05-reference/03-api.md) for detailed method documentation
- Check [Real-World Examples](../04-advanced/01-examples.md) for complete working code
- Examine your error logs for detailed error messages

## Best Practices

1. **Initialize early** - Call `MilliRules::init()` as early as possible in your application
2. **Check your environment** - Verify PHP version and Composer are properly configured
3. **Enable error logging** - Turn on error logging during development to catch issues quickly
4. **Test in isolation** - Create a simple test rule to verify MilliRules is working before building complex logic

## Next Steps

Now that MilliRules is installed and initialized, you're ready to create your first rule!

Continue to [Creating Your First Rule](03-first-rule.md) to start building with MilliRules.

