---
post_title: 'Core Concepts - Rules, Conditions, and Actions'
post_excerpt: 'Understand the fundamental architecture of MilliRules including rules, conditions, actions, context, and the package system.'
taxonomy:
  category:
    - documentation
    - fundamentals
  post_tag:
    - concepts
    - rules
    - conditions
    - actions
    - context
    - packages
menu_order: 2
---

# Core Concepts - Rules, Conditions, and Actions

Understanding MilliRules' core concepts is essential for building powerful, maintainable rules. This guide explains the fundamental architecture and how all the pieces work together.

## The Rule Engine Architecture

MilliRules follows a simple but powerful pattern: **When conditions are met, then actions execute**.

```
┌─────────────────────────────────────────────┐
│              Rule Definition                │
│                                             │
│  ┌────────────┐         ┌─────────────┐     │
│  │ Conditions │  ───→   │   Actions   │     │
│  │  (When)    │         │   (Then)    │     │
│  └────────────┘         └─────────────┘     │
│        │                       │            │
│        ▼                       ▼            │
│  ┌────────────────────────────────────┐     │
│  │         Context & Packages         │     │
│  └────────────────────────────────────┘     │
└─────────────────────────────────────────────┘
```

## What is a Rule?

A **rule** is a self-contained unit of logic that:
- Has a unique identifier
- Contains one or more conditions
- Contains one or more actions
- Executes when its conditions are satisfied
- Operates on a shared context

### Rule Structure

Every rule consists of:

```php
<?php
use MilliRules\Rules;

Rules::create('rule_id')           // Unique identifier
    ->title('Rule Title')           // Human-readable name
    ->order(10)                     // Execution sequence
    ->enabled(true)                 // Enable/disable flag
    ->when()                        // Condition builder
        ->condition1()
        ->condition2()
    ->then()                        // Action builder
        ->action1()
        ->action2()
    ->register();                   // Register with engine
```

### Rule Properties

| Property | Type | Description | Default |
|----------|------|-------------|---------|
| `id` | string | Unique identifier (required) | - |
| `title` | string | Human-readable name | Empty |
| `order` | int | Execution sequence (lower = first) | 10 |
| `enabled` | bool | Whether rule should execute | true |
| `type` | string | Rule type (`php` or `wp`) | Auto-detected |
| `match_type` | string | Condition logic (`all`, `any`, `none`) | `all` |
| `conditions` | array | Condition configurations | [] |
| `actions` | array | Action configurations | [] |

> [!IMPORTANT]
> Rule IDs must be unique within your application. Using duplicate IDs may cause unexpected behavior. Consider prefixing IDs with your plugin or project name.

## Rule Execution Order

Rules execute in sequence based on their `order` value:

```php
<?php
Rules::create('first_rule')->order(5)->when()->request_url('/test')->then()->register();
Rules::create('second_rule')->order(10)->when()->request_url('/test')->then()->register();
Rules::create('third_rule')->order(20)->when()->request_url('/test')->then()->register();
```

**Execution order**: first_rule → second_rule → third_rule

> [!TIP]
> Use order ranges to organize rules by purpose:
> - **0-9**: Core system rules
> - **10-19**: Default application rules
> - **20-49**: Feature-specific rules
> - **50-99**: Override rules
> - **100+**: Emergency override rules

### Why Order Matters

When multiple rules modify the same value or state:

```php
<?php
// Rule 1 (order: 10) sets cache to 3600 seconds
Rules::create('cache_short')->order(10)
    ->when()->request_url('/api/*')
    ->then()->custom('set_cache', ['value' => '3600'])
    ->register();

// Rule 2 (order: 20) overrides cache to 7200 seconds
Rules::create('cache_long')->order(20)
    ->when()->request_url('/api/stable/*')
    ->then()->custom('set_cache', ['value' => '7200'])
    ->register();
```

For URL `/api/stable/users`:
1. Both rules match
2. Rule 1 executes first (order: 10) → cache set to 3600
3. Rule 2 executes second (order: 20) → cache overridden to 7200
4. **Final value**: 7200 seconds

## Conditions: The "When" Logic

**Conditions** determine whether a rule should execute. They evaluate the current context and return true or false.

### Condition Types

MilliRules provides two categories of conditions:

#### 1. PHP Package Conditions (Framework-Agnostic)

Available in any PHP environment:

```php
<?php
->when()
    ->request_url('/api/*')           // URL pattern matching
    ->request_method('POST')          // HTTP method
    ->request_header('Content-Type', 'application/json')  // Headers
    ->cookie('session_id')            // Cookie existence/value
    ->request_param('action', 'save') // Query/form parameters
    ->constant('WP_DEBUG', true)      // PHP/WordPress constants
```

#### 2. WordPress Package Conditions

Available only in WordPress environments:

```php
<?php
->when()
    ->is_user_logged_in()             // User authentication
    ->is_singular('post')             // Singular post/page
    ->is_archive('category')          // Archive pages
    ->post_type('product')            // Post type
    ->is_home()                       // Home page
```

> [!NOTE]
> WordPress conditions require the WordPress package to be loaded. MilliRules automatically detects WordPress and loads the appropriate package.

### Condition Evaluation Logic

MilliRules supports three evaluation strategies:

#### Match All (AND Logic)

**All conditions must be true** for the rule to execute. This is the default behavior.

```php
<?php
Rules::create('secure_api_access')
    ->when()  // Implicitly uses match_all()
        ->request_url('/api/*')
        ->request_method('POST')
        ->request_header('Authorization', 'Bearer *', 'LIKE')
    ->then()
        ->custom('process_request')
    ->register();
```

**Evaluates to**: `condition1 AND condition2 AND condition3`

#### Match Any (OR Logic)

**At least one condition must be true** for the rule to execute.

```php
<?php
Rules::create('development_environments')
    ->when()
        ->match_any()  // Explicit OR logic
        ->constant('WP_DEBUG', true)
        ->constant('WP_ENVIRONMENT_TYPE', 'local')
        ->constant('WP_ENVIRONMENT_TYPE', 'development')
    ->then()
        ->custom('enable_debug_bar')
    ->register();
```

**Evaluates to**: `condition1 OR condition2 OR condition3`

#### Match None (NOT Logic)

**All conditions must be false** for the rule to execute.

```php
<?php
Rules::create('production_only')
    ->when()
        ->match_none()  // Explicit NOT logic
        ->constant('WP_DEBUG', true)
        ->constant('WP_ENVIRONMENT_TYPE', 'local')
    ->then()
        ->custom('enable_caching')
    ->register();
```

**Evaluates to**: `NOT condition1 AND NOT condition2`

> [!WARNING]
> You cannot mix match types within a single `->when()` block. If you need complex logic like `(A AND B) OR (C AND D)`, create separate rules or use custom condition callbacks.

## Actions: The "Then" Behavior

**Actions** are what happens when conditions are satisfied. They can modify data, trigger side effects, log information, or perform any operation.

### Action Execution

Actions execute **immediately and sequentially** when their rule's conditions match:

```php
<?php
Rules::create('api_request_handler')
    ->when()
        ->request_url('/api/process')
    ->then()
        ->custom('log_request')      // Executes first
        ->custom('validate_data')    // Executes second
        ->custom('process_request')  // Executes third
        ->custom('send_response')    // Executes fourth
    ->register();
```

### Action Types

MilliRules supports various action types:

#### 1. Custom Callback Actions

Define actions inline using callbacks:

```php
<?php
Rules::register_action('send_email', function($context, $config) {
    $to = $config['value'] ?? '';
    $subject = $config['subject'] ?? 'Notification';
    wp_mail($to, $subject, 'Your message');
});

// Use in rules:
->then()
    ->custom('send_email', [
        'value' => 'admin@example.com',
        'subject' => 'New User Registration'
    ])
```

#### 2. Class-Based Actions

Create reusable action classes:

```php
<?php
use MilliRules\Interfaces\ActionInterface;

class SendEmailAction implements ActionInterface {
    private $config;
    private $context;

    public function __construct(array $config, array $context) {
        $this->config = $config;
        $this->context = $context;
    }

    public function execute(array $context): void {
        $to = $this->config['value'] ?? '';
        wp_mail($to, 'Subject', 'Message');
    }

    public function get_type(): string {
        return 'send_email';
    }
}
```

#### 3. WordPress Hook Actions

Trigger WordPress actions or filters:

```php
<?php
->then()
    ->custom('do_action', ['value' => 'my_custom_hook'])
    ->custom('apply_filters', ['value' => 'my_custom_filter'])
```

> [!TIP]
> Use class-based actions for complex logic that requires state management or extensive configuration. Use callback actions for simple, one-off operations.

## Context: Shared Data Pool

The **context** is a shared associative array that contains all the data available to conditions and actions.

### Context Structure

```php
<?php
[
    'request' => [
        'method' => 'GET',
        'uri' => '/wp-admin/edit.php',
        'scheme' => 'https',
        'host' => 'example.com',
        'path' => '/wp-admin/edit.php',
        'query' => 'post_type=page',
        'referer' => 'https://example.com',
        'user_agent' => 'Mozilla/5.0...',
        'headers' => [...],
        'ip' => '192.168.1.1',
        'cookies' => [...],
        'params' => [...],
    ],
    'wp' => [
        'post' => [...],
        'user' => [...],
        'query' => [...],
        'constants' => [...],
    ],
    // Custom package data...
]
```

### Context Building

Context is automatically built from loaded packages:

```php
<?php
use MilliRules\MilliRules;

// Initialize packages (builds context automatically)
MilliRules::init();

// Get current context
$context = MilliRules::build_context();
print_r($context);
```

### Accessing Context in Custom Code

```php
<?php
Rules::register_action('log_context', function($context, $config) {
    // Access request data
    $method = $context['request']['method'] ?? 'UNKNOWN';

    // Access WordPress data (if available)
    $user_id = $context['wp']['user']['id'] ?? 0;

    error_log("Request: $method, User: $user_id");
});
```

> [!IMPORTANT]
> Context is built once at the beginning of rule execution. It's a snapshot of the current state and won't reflect changes made during execution unless you explicitly modify it.

## Packages: Modular Functionality

**Packages** are self-contained modules that provide:
- Conditions
- Actions
- Context data
- Placeholder resolvers

### Built-in Packages

#### PHP Package

**Always available** in any PHP environment:
- Framework-agnostic HTTP conditions
- Request/response handling
- Cookie and header management

```php
<?php
// PHP package is always loaded
MilliRules::init();
```

#### WordPress Package

**Available only in WordPress**:
- WordPress-specific conditions
- Hook-based execution
- WordPress data in context

```php
<?php
// Automatically loads WordPress package if WordPress is detected
MilliRules::init();
```

### Package Dependencies

Packages can depend on other packages:

```php
<?php
// WordPress package requires PHP package
class WordPressPackage extends BasePackage {
    public function get_required_packages(): array {
        return ['PHP'];  // PHP must be loaded first
    }
}
```

MilliRules automatically resolves dependencies:
1. Detects required packages
2. Loads dependencies first
3. Prevents circular dependencies

> [!WARNING]
> Circular dependencies (Package A requires Package B, Package B requires Package A) will cause an error. Design your packages carefully to avoid this.

## Rule Types: PHP vs WordPress

MilliRules supports two rule types that determine execution strategy:

### PHP Rules (`type: 'php'`)

- Execute immediately when `execute_rules()` is called
- No WordPress hook integration
- Suitable for early execution (caching, redirects)
- Framework-agnostic

```php
<?php
Rules::create('cache_check', 'php')
    ->when()->request_url('/api/*')
    ->then()->custom('check_cache')
    ->register();

// Manual execution required
MilliRules::execute_rules(['PHP']);
```

### WordPress Rules (`type: 'wp'`)

- Execute automatically on WordPress hooks
- Integrated with WordPress lifecycle
- Access to WordPress data and functions
- Default type when WordPress is detected

```php
<?php
Rules::create('admin_notice', 'wp')
    ->on('admin_notices', 10)  // Registers with WordPress hook
    ->when()->is_user_logged_in()
    ->then()->custom('show_notice')
    ->register();

// Executes automatically when 'admin_notices' hook fires
```

### Auto-Detection

MilliRules auto-detects rule type based on:
1. Explicit `type` parameter
2. Used conditions (WordPress conditions → `wp` type)
3. Hook registration (`.on()` → `wp` type)
4. Default to `php` if ambiguous

```php
<?php
// Auto-detected as 'wp' due to WordPress condition
Rules::create('wp_rule_auto')
    ->when()->is_user_logged_in()
    ->then()->custom('action')
    ->register();
```

> [!TIP]
> Always explicitly specify the rule type when creating rules to avoid ambiguity: `Rules::create('rule_id', 'wp')` or `Rules::create('rule_id', 'php')`.

## Execution Flow

Understanding the execution flow helps debug issues and optimize performance:

```
1. Initialize MilliRules
   ↓
2. Register packages
   ↓
3. Load available packages
   ↓
4. Build context from packages
   ↓
5. Register rules
   ↓
6. Trigger execution (manual or hook-based)
   ↓
7. For each rule:
   a. Check if enabled
   b. Validate package availability
   c. Evaluate conditions
   d. If conditions match → execute actions
   ↓
8. Return execution statistics
```

### Execution Statistics

Every execution returns detailed statistics:

```php
<?php
$result = MilliRules::execute_rules();

/*
[
    'rules_processed' => 10,   // Total rules evaluated
    'rules_skipped' => 2,      // Rules skipped (disabled/missing packages)
    'rules_matched' => 5,      // Rules where conditions matched
    'actions_executed' => 12,  // Total actions executed
    'context' => [...],        // Execution context
]
*/
```

> [!TIP]
> Use execution statistics for debugging and performance monitoring. Log them in development to understand rule behavior.

## Best Practices

### 1. Keep Rules Focused

```php
<?php
// ✅ Good - focused, single purpose
Rules::create('cache_api_responses')
    ->when()->request_url('/api/*')
    ->then()->custom('set_cache_headers')
    ->register();

// ❌ Bad - too many responsibilities
Rules::create('do_everything')
    ->when()->request_url('*')
    ->then()
        ->custom('check_cache')
        ->custom('validate_user')
        ->custom('process_request')
        ->custom('send_email')
        ->custom('update_database')
    ->register();
```

### 2. Use Descriptive Names

```php
<?php
// ✅ Good - clear purpose
Rules::create('block_non_authenticated_api_access')
    ->title('Block API Access for Non-Authenticated Users')

// ❌ Bad - unclear purpose
Rules::create('rule1')
    ->title('Check stuff')
```

### 3. Order Rules Logically

```php
<?php
// ✅ Good - logical ordering
Rules::create('set_default_cache')->order(10)  // Set defaults first
Rules::create('override_api_cache')->order(20) // Override for specific cases
Rules::create('disable_cache_dev')->order(30)  // Development override last
```

### 4. Leverage Context

```php
<?php
// ✅ Good - uses context effectively
Rules::register_action('log_user_action', function($context, $config) {
    $user = $context['wp']['user']['login'] ?? 'guest';
    $url = $context['request']['uri'] ?? 'unknown';
    error_log("User $user accessed $url");
});
```

## Common Patterns

### 1. Early Exit Pattern

Stop processing based on conditions:

```php
<?php
Rules::create('maintenance_mode')
    ->order(1)  // Execute first
    ->when()->constant('MAINTENANCE_MODE', true)
    ->then()->custom('show_maintenance_page')->custom('exit')
    ->register();
```

### 2. Progressive Enhancement Pattern

Layer features based on availability:

```php
<?php
// Base rule for all environments
Rules::create('base_security')->order(10)
    ->when()->request_url('*')
    ->then()->custom('basic_security_headers')
    ->register();

// Enhanced rule for WordPress
Rules::create('wp_security')->order(20)
    ->when()->is_user_logged_in()
    ->then()->custom('additional_security_headers')
    ->register();
```

### 3. Override Pattern

Allow specific rules to override general rules:

```php
<?php
// General rule
Rules::create('default_cache')->order(10)
    ->when()->request_url('*')
    ->then()->custom('set_cache', ['value' => '3600'])
    ->register();

// Specific override
Rules::create('api_no_cache')->order(20)
    ->when()->request_url('/api/dynamic/*')
    ->then()->custom('set_cache', ['value' => '0'])
    ->register();
```

## Next Steps

Now that you understand core concepts, explore these topics:

- **[Building Rules](03-building-rules.md)** - Master the fluent API in depth
- **[Built-in Conditions](04-built-in-conditions.md)** - Complete condition reference
- **[Operators](06-operators.md)** - Pattern matching and comparisons
- **[Packages](08-packages.md)** - Deep dive into the package system

---

**Questions about core concepts?** Check the [API Reference](14-api-reference.md) or explore [Real-World Examples](15-examples.md) to see these concepts in action.
