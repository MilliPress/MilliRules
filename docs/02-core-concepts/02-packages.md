---
title: 'Understanding the Package System'
post_excerpt: 'Deep dive into MilliRules package architecture, including PHP and WordPress packages, auto-loading, dependencies, and the PackageManager.'
menu_order: 20
---

# Understanding the Package System

The package system is the architectural foundation of MilliRules, providing modularity, extensibility, and environment-specific functionality. This guide explains how packages work, their lifecycle, and how to leverage them effectively.

## What is a Package?

A **package** is a self-contained module that provides:

- **Conditions** - Specific to the package's domain
- **Actions** - Operations relevant to the package
- **Context** - Data available during rule execution
- **Placeholder Resolvers** - Custom placeholder categories
- **Dependencies** - Other packages required for operation

Packages enable MilliRules to work across different environments (vanilla PHP, WordPress, custom frameworks) while maintaining a consistent API.

## Package Architecture

```
┌───────────────────────────────────┐
│          PackageManager           │
│       (Central Coordination)      │
└──────────────┬────────────────────┘
               │
    ┌──────────┴──────────┐
    │                     │
┌───▼─────┐          ┌─────▼───┐
│   PHP   │          │    WP   │
│ Package │◄─────────┤ Package │
│         │ requires │         │
└─────────┘          └─────────┘
```

### PackageManager

The `PackageManager` is a static class that coordinates all packages:

- Registers packages
- Resolves dependencies
- Loads packages in correct order
- Aggregates context from all loaded packages
- Routes rules to appropriate packages

### Package Interface

All packages implement `PackageInterface`:

```php
<?php
namespace MilliRules\Packages;

use MilliRules\Context;

interface PackageInterface {
    public function get_name(): string;
    public function get_namespaces(): array;
    public function is_available(): bool;
    public function get_required_packages(): array;
    public function register_providers(Context $context): void;
    public function get_placeholder_resolver(Context $context);
    public function register_rule(array $rule, array $metadata);
    public function execute_rules(array $rules, Context $context): array;
}
```

---

## Built-in Packages

### PHP Package

The **PHP Package** provides framework-agnostic HTTP and request handling.

**Characteristics**:
- Always available in any PHP 7.4+ environment
- No dependencies
- Provides HTTP request conditions
- Builds request-based context

**Namespace**: `MilliRules\Packages\PHP`

**Conditions Provided**:
- `request_url` - URL/URI matching
- `request_method` - HTTP method checking
- `request_header` - Request header validation
- `request_param` - Query/form parameter checking
- `cookie` - Cookie existence/value checking
- `constant` - PHP constant checking

**Context Providers Registered**:
```php
<?php
// PHP package registers these context providers (loaded on-demand):

'request' => [
    'method' => 'GET',
    'uri' => '/path',
    'scheme' => 'https',
    'host' => 'example.com',
    'path' => '/path',
    'query' => 'key=value',
    'referer' => 'https://example.com/ref',
    'user_agent' => 'Mozilla/5.0...',
    'headers' => [...],
    'ip' => '192.168.1.1',
],

'cookie' => [
    // $_COOKIE data (loaded separately from request)
],

'param' => [
    // array_merge($_GET, $_POST) (loaded separately from request)
],
```

**Availability Check**:
```php
<?php
public function is_available(): bool {
    return true; // Always available
}
```

---

### WordPress Package

The **WordPress Package** provides WordPress-specific functionality.

**Characteristics**:
- Available only in WordPress environments
- Depends on PHP package
- Provides WordPress conditions
- Integrates with WordPress hooks
- Builds WordPress-specific context

**Namespace**: `MilliRules\Packages\WP`

**Conditions Provided**:
- `is_user_logged_in` - User authentication status
- `is_singular` - Singular post/page check
- `is_home` - Home page check
- `is_archive` - Archive page check
- `post_type` - Post type validation

**Context Providers Registered**:
```php
<?php
// WordPress package registers these context providers (loaded on-demand):
// Note: Uses flat structure, no 'wp' namespace

'post' => [
    'id' => 123,
    'title' => 'My Post',
    'type' => 'post',
    'status' => 'publish',
    'author' => 1,
    'parent' => 0,
    'name' => 'my-post',
    // ... normalized post fields
],

'user' => [
    'id' => 1,
    'login' => 'admin',
    'email' => 'admin@example.com',
    'display_name' => 'Administrator',
    'roles' => ['administrator'],
    'logged_in' => true,
],

'query' => [
    'post_type' => 'post',
    'paged' => 1,
    's' => '',
    'm' => '',
    // ... WordPress query variables from $wp_query->query_vars
],

'term' => [
    // Taxonomy term data (when applicable)
],
```

> [!NOTE]
> The 'query' context provides WordPress query variables (like post_type, paged, s).
> WordPress query conditionals (like is_singular, is_home) are accessed via dedicated
> `is_*` conditions rather than context placeholders.

**Availability Check**:
```php
<?php
public function is_available(): bool {
    return function_exists('add_action'); // Detects WordPress
}
```

**Dependencies**:
```php
<?php
public function get_required_packages(): array {
    return ['PHP']; // Requires PHP package
}
```

---

## Package Lifecycle

### 1. Registration

Packages are registered with `PackageManager`:

```php
<?php
use MilliRules\PackageManager;
use MilliRules\Packages\PHP\PHPPackage;
use MilliRules\Packages\WP\WordPressPackage;

// Manual registration
$php_package = new PHPPackage();
$wp_package = new WordPressPackage();

PackageManager::register_package($php_package);
PackageManager::register_package($wp_package);
```

### 2. Loading

Packages are loaded during initialization:

```php
<?php
use MilliRules\MilliRules;

// Auto-loads available packages
MilliRules::init();

// Or specify packages explicitly
MilliRules::init(['PHP', 'WP']);
```

**Loading Process**:
1. Check if package is available (`is_available()`)
2. Resolve dependencies
3. Load required packages first
4. Load the requested package
5. Detect circular dependencies

### 3. Context Provider Registration

Packages register context providers that load data on-demand:

```php
<?php
use MilliRules\Context;

// During initialization, packages register their providers
class PHPPackage extends BasePackage {
    public function register_providers(Context $context): void {
        // Register request provider (loads only when needed)
        $context->register_provider('request', function() {
            return [/* request data */];
        });

        // Register cookie provider (loads only when needed)
        $context->register_provider('cookie', function() {
            return $_COOKIE;
        });

        // Register param provider (loads only when needed)
        $context->register_provider('param', function() {
            return array_merge($_GET, $_POST);
        });
    }
}

// Context sections load lazily when accessed:
// - 'request' loads when $context->get('request.uri') is called
// - 'cookie' loads when $context->get('cookie.session_id') is called
// - 'param' loads when $context->get('param.action') is called
```

### 4. Rule Execution

Rules execute using context from loaded packages:

```php
<?php
$result = MilliRules::execute_rules();

/*
[
    'rules_processed' => 10,
    'rules_skipped' => 2,     // Skipped if package unavailable
    'rules_matched' => 5,
    'actions_executed' => 12,
    'context' => [...],
]
*/
```

---

## Package Dependencies

Packages can depend on other packages using `get_required_packages()`.

### Declaring Dependencies

```php
<?php
namespace MyPlugin\Packages;

use MilliRules\Packages\BasePackage;

class CustomPackage extends BasePackage {
    public function get_name(): string {
        return 'Custom';
    }

    public function get_required_packages(): array {
        return ['PHP', 'WP']; // Requires both PHP and WordPress
    }

    // ... other methods
}
```

### Dependency Resolution

PackageManager automatically resolves dependencies:

```php
<?php
// Request to load Custom package
MilliRules::init(['Custom']);

// Automatic resolution:
// 1. Custom requires ['PHP', 'WP']
// 2. WP requires ['PHP']
// 3. Load order: PHP → WP → Custom
```

### Circular Dependency Detection

```php
<?php
// Package A requires Package B
// Package B requires Package A
// ↓
// Error: Circular dependency detected

try {
    MilliRules::init(['A']);
} catch (Exception $e) {
    error_log('Circular dependency: ' . $e->getMessage());
}
```

> [!WARNING]
> Design your package dependencies carefully to avoid circular dependencies. Each package should have a clear, unidirectional dependency relationship.

---

## Package Namespaces

Packages provide namespaces for conditions and actions.

### Namespace Registration

```php
<?php
public function get_namespaces(): array {
    return [
        'MilliRules\Packages\PHP\Conditions',
        'MilliRules\Packages\PHP\Actions',
    ];
}
```

### Namespace Resolution

When a condition is used, MilliRules finds the appropriate class:

```php
<?php
// User writes:
->request_url('/api/*')

// MilliRules resolves:
// 1. Converts 'request_url' to 'RequestUrl'
// 2. Searches registered namespaces
// 3. Finds: MilliRules\Packages\PHP\Conditions\RequestUrlCondition
// 4. Instantiates class with config and context
```

**Longest Match Algorithm**:

Multiple packages can provide overlapping namespaces. MilliRules uses the longest matching namespace:

```php
<?php
// Registered namespaces:
// - 'MyPlugin\Conditions'
// - 'MyPlugin\Conditions\Advanced'

// Looking for: MyPlugin\Conditions\Advanced\CustomCondition
// ↓
// Uses: 'MyPlugin\Conditions\Advanced' (longest match)
```

---

## Package Filtering

You can filter which packages are used during execution.

### Filter by Package Name

```php
<?php
// Execute only with PHP package (skip WordPress)
$result = MilliRules::execute_rules(['PHP']);

// Execute only with WordPress package
$result = MilliRules::execute_rules(['WP']);

// Execute with specific packages
$result = MilliRules::execute_rules(['PHP', 'Custom']);
```

### Use Cases

**Early execution** (before WordPress loads):

```php
<?php
// In mu-plugins or early hook
MilliRules::init(['PHP']);

// Execute only PHP rules
$result = MilliRules::execute_rules(['PHP']);
```

**Testing specific packages**:

```php
<?php
// Test only PHP-related rules
$php_result = MilliRules::execute_rules(['PHP']);

// Test only WordPress-related rules
$wp_result = MilliRules::execute_rules(['WP']);
```

---

## Package Context

### Accessing Package Context

```php
<?php
use MilliRules\Context;

Rules::register_action('context_aware', function($args, Context $context) {
    // Check which providers are available
    // Note: Providers are loaded on-demand, not preloaded

    // Access request data (triggers lazy loading if not already loaded)
    if ($context->has('request.uri')) {
        $url = $context->get('request.uri', '');
        error_log("Request URL: {$url}");
    }

    // Access WordPress user data (triggers lazy loading if not already loaded)
    $context->load('user');
    if ($context->has('user.id')) {
        $user_id = $context->get('user.id', 0);
        error_log("WordPress user: {$user_id}");
    }
});
```

### Conditional Package Features

```php
<?php
Rules::create('flexible_rule')
    ->when_any()  // Use OR logic
        // PHP condition (always works)
        ->request_url('/api/*')

        // WordPress condition (works if WP package loaded)
        ->is_user_logged_in()
    ->then()
        ->custom('context_aware')  // Action adapts to available packages
    ->register();
```

---

## Best Practices

### 1. Check Package Availability

```php
<?php
use MilliRules\Context;

// ✅ Good - check before using package-specific features
Rules::register_action('safe_wp_action', function($args, Context $context) {
    $context->load('user');

    if (!$context->has('user.id')) {
        error_log('WordPress user context not available');
        return;
    }

    $user_id = $context->get('user.id', 0);
    // Use WordPress features
});

// ❌ Bad - assumes WordPress context is always available
Rules::register_action('unsafe_action', function($args, Context $context) {
    $context->load('user');
    $user_id = $context->get('user.id'); // May return null if not available!
});
```

### 2. Declare Dependencies Explicitly

```php
<?php
// ✅ Good - explicit dependencies
class MyPackage extends BasePackage {
    public function get_required_packages(): array {
        return ['PHP', 'WP'];
    }
}

// ❌ Bad - undeclared dependencies
class MyPackage extends BasePackage {
    public function register_providers(Context $context): void {
        // Uses WordPress functions without declaring dependency!
        $context->register_provider('data', function() {
            return ['option' => get_option('my_option')];
        });
    }
}
```

### 3. Use Appropriate Package for Rules

```php
<?php
// ✅ Good - PHP rule for PHP conditions
Rules::create('cache_check', 'php')
    ->when()->request_url('/api/*')
    ->then()->custom('check_cache')
    ->register();

// ✅ Good - WordPress rule for WordPress conditions
Rules::create('admin_notice', 'wp')
    ->when()->is_user_logged_in()
    ->then()->custom('show_notice')
    ->register();

// ❌ Unclear - mixed without explicit type
Rules::create('mixed_rule')  // Type will be auto-detected
    ->when()
        ->request_url('/api/*')
        ->is_user_logged_in()
    ->then()->custom('action')
    ->register();
```

### 4. Handle Package Unavailability Gracefully

```php
<?php
use MilliRules\PackageManager;

// Check if package is loaded
if (PackageManager::is_package_loaded('WP')) {
    // Create WordPress-specific rules
    Rules::create('wp_rule')
        ->when()->is_user_logged_in()
        ->then()->custom('wp_action')
        ->register();
}
```

---

## Package Information

### Get Loaded Packages

```php
<?php
use MilliRules\MilliRules;

// Get package names
$package_names = MilliRules::get_loaded_packages();
// ['PHP', 'WP']

error_log('Loaded packages: ' . implode(', ', $package_names));
```

### Check Specific Package

```php
<?php
use MilliRules\PackageManager;

if (PackageManager::is_package_loaded('WP')) {
    error_log('WordPress package is loaded');
}

if (PackageManager::has_packages()) {
    error_log('At least one package is loaded');
}
```

### Get Package Instance

```php
<?php
use MilliRules\PackageManager;

$php_package = PackageManager::get_package('PHP');

if ($php_package) {
    $namespaces = $php_package->get_namespaces();
    error_log('PHP package namespaces: ' . print_r($namespaces, true));
}
```

---

## Common Patterns

### 1. Environment-Specific Loading

```php
<?php
// Load different packages based on environment
if (defined('WP_CLI') && WP_CLI) {
    // CLI environment - PHP only
    MilliRules::init(['PHP']);
} elseif (defined('DOING_CRON') && DOING_CRON) {
    // Cron environment - PHP + WordPress
    MilliRules::init(['PHP', 'WP']);
} else {
    // Normal request - all packages
    MilliRules::init();
}
```

### 2. Progressive Enhancement

```php
<?php
// Base rules with PHP package
MilliRules::init(['PHP']);

Rules::create('base_security')
    ->when()->request_url('*')
    ->then()->custom('basic_security')
    ->register();

// Enhance with WordPress if available
if (PackageManager::is_package_loaded('WP')) {
    Rules::create('wp_security')
        ->when()->is_user_logged_in()
        ->then()->custom('enhanced_security')
        ->register();
}
```

### 3. Package-Specific Rules

```php
<?php
// Group rules by package
$php_rules = [
    'api_cache', 'request_logging', 'header_security'
];

$wp_rules = [
    'admin_notices', 'user_redirects', 'content_filtering'
];

// Register PHP rules
foreach ($php_rules as $rule_id) {
    Rules::create($rule_id, 'php')
        ->when()->request_url('*')
        ->then()->custom($rule_id . '_action')
        ->register();
}

// Register WordPress rules (if available)
if (PackageManager::is_package_loaded('WP')) {
    foreach ($wp_rules as $rule_id) {
        Rules::create($rule_id, 'wp')
            ->when()->is_user_logged_in()
            ->then()->custom($rule_id . '_action')
            ->register();
    }
}
```

---

## Troubleshooting

### Package Not Loading

**Check availability**:
```php
<?php
$package = PackageManager::get_package('WP');
if (!$package) {
    error_log('Package not registered');
} elseif (!$package->is_available()) {
    error_log('Package not available in this environment');
}
```

### Dependency Issues

**Check dependencies**:
```php
<?php
$package = PackageManager::get_package('Custom');
$required = $package->get_required_packages();

foreach ($required as $dep) {
    if (!PackageManager::is_package_loaded($dep)) {
        error_log("Missing dependency: {$dep}");
    }
}
```

### Context Missing Data

**Verify providers are registered**:
```php
<?php
use MilliRules\Context;

$context = new Context();
MilliRules::init(); // Registers all providers

// Check if a specific provider is available
$context->load('user');
if (!$context->has('user.id')) {
    error_log('WordPress user context not available');
}

// Export context to see all loaded sections
$array = $context->to_array();
error_log('Available context keys: ' . implode(', ', array_keys($array)));
```

---

## Next Steps

- **[Creating Custom Conditions](../03-customization/01-custom-conditions.md)** - Extend package conditions
- **[Creating Custom Packages](../03-customization/03-custom-packages.md)** - Build your own packages
- **[Advanced Patterns](../04-advanced/02-advanced-patterns.md)** - Advanced package techniques
- **[WordPress Integration](../04-advanced/03-wordpress-integration.md)** - WordPress package details

---

**Ready to create your own package?** Continue to [Creating Custom Packages](../03-customization/03-custom-packages.md) to learn how to extend MilliRules with your own functionality.
