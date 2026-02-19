---
title: 'Creating Custom Packages'
post_excerpt: 'Learn how to create custom MilliRules packages with conditions, actions, context building, and placeholder resolvers for complete extensibility.'
menu_order: 30
---

# Creating Custom Packages

Custom packages are the ultimate way to extend MilliRules. They let you bundle conditions, actions, context data, and placeholder resolvers into reusable, self-contained modules. This guide shows you how to create packages that integrate seamlessly with MilliRules.

## Why Create Custom Packages?

Custom packages enable you to:

- **Bundle related functionality** - Group conditions and actions by domain
- **Provide context data** - Make data available to all rules
- **Add placeholder resolvers** - Enable dynamic values in rules
- **Integrate third-party services** - Connect external APIs and systems
- **Create reusable libraries** - Share packages across projects
- **Maintain clean separation** - Keep code organized by concern

## Package Structure

A complete custom package includes:

```
MyCustomPackage/
├── MyCustomPackage.php      # Package class (implements PackageInterface)
├── Conditions/              # Condition classes
│   ├── CustomCondition1.php
│   └── CustomCondition2.php
├── Actions/                 # Action classes
│   ├── CustomAction1.php
│   └── CustomAction2.php
└── PlaceholderResolver.php  # Optional placeholder resolver
```

---

## Implementing PackageInterface

All packages must implement `PackageInterface`:

```php
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

## Basic Custom Package

### Minimal Package Implementation

```php
namespace MyPlugin\Packages;

use MilliRules\Packages\BasePackage;

class MyCustomPackage extends BasePackage {
    /**
     * Package name (must be unique)
     */
    public function get_name(): string {
        return 'MyCustom';
    }

    /**
     * Namespaces for conditions and actions
     */
    public function get_namespaces(): array {
        return [
            'MyPlugin\Packages\MyCustom\Conditions',
            'MyPlugin\Packages\MyCustom\Actions',
        ];
    }

    /**
     * Check if package can be used in current environment
     */
    public function is_available(): bool {
        // Example: check if required functions exist
        return function_exists('my_required_function');
    }
}
```

### Registering the Package

```php
use MilliRules\MilliRules;
use MyPlugin\Packages\MyCustomPackage;

// Register custom package
$custom_package = new MyCustomPackage();
MilliRules::init(null, [$custom_package]);

// Or let MilliRules auto-discover (if registered globally)
MilliRules::init();
```

---

## Registering Context Providers

Packages register context providers that load data lazily when needed. This improves performance by only loading data that rules actually use.

### Simple Context Provider

```php
use MilliRules\Context;

public function register_providers(Context $context): void {
    // Register a simple provider that loads on-demand
    $context->register_provider('my_custom', function() {
        return [
            'my_custom' => [
                'value1' => get_option('my_option_1'),
                'value2' => get_option('my_option_2'),
                'timestamp' => time(),
            ],
        ];
    });
}
```

**Benefit**: Data is only retrieved when `$context->get('my_custom.value1')` is called.

### Dynamic Context Provider

```php
use MilliRules\Context;

public function register_providers(Context $context): void {
    // Register provider that loads complex data on-demand
    $context->register_provider('my_custom', function() {
        $user_data = [];
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_data = [
                'id' => $user_id,
                'meta' => get_user_meta($user_id),
                'purchases' => $this->get_user_purchases($user_id),
            ];
        }

        return [
            'my_custom' => [
                'user' => $user_data,
                'site' => [
                    'name' => get_bloginfo('name'),
                    'url' => home_url(),
                ],
                'stats' => [
                    'total_posts' => wp_count_posts()->publish,
                    'total_users' => count_users()['total_users'],
                ],
            ],
        ];
    });
}

private function get_user_purchases($user_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}purchases WHERE user_id = %d",
        $user_id
    ));
}
```

**Benefit**: Expensive database queries and WordPress functions only execute when needed.

### Context Provider with External API

```php
use MilliRules\Context;

public function register_providers(Context $context): void {
    // Register provider that loads API data on-demand
    $context->register_provider('my_custom', function() {
        // Cache expensive API calls
        $api_data = get_transient('my_custom_api_data');

        if ($api_data === false) {
            $response = wp_remote_get('https://api.example.com/data', [
                'timeout' => 10,
                'headers' => ['Authorization' => 'Bearer ' . $this->get_api_key()],
            ]);

            if (!is_wp_error($response)) {
                $api_data = json_decode(wp_remote_retrieve_body($response), true);
                set_transient('my_custom_api_data', $api_data, 300); // Cache 5 minutes
            } else {
                $api_data = [];
            }
        }

        return [
            'my_custom' => [
                'api' => $api_data,
                'cached_at' => get_transient('my_custom_api_data_time') ?: time(),
            ],
        ];
    });
}
```

**Benefit**: API calls only execute if a rule actually needs the API data.

---

## Creating Package Conditions

Package conditions extend the available condition types.

### Simple Package Condition

```php
namespace MyPlugin\Packages\MyCustom\Conditions;

use MilliRules\Conditions\BaseCondition;
use MilliRules\Context;

class UserLevelCondition extends BaseCondition {
    protected function get_actual_value(Context $context) {
        $context->load('user');
        $user_id = $context->get('user.id', 0);

        if (!$user_id) {
            return 0;
        }

        // Get custom user level
        return (int) get_user_meta($user_id, 'user_level', true);
    }

    public function get_type(): string {
        return 'user_level';
    }
}
```

**Usage**:
```php
Rules::create('premium_users')
    ->when()
        ->custom('user_level', ['value' => 5, 'operator' => '>='])
    ->then()
        ->custom('show_premium_content')
    ->register();
```

### Condition Using Package Context

```php
namespace MyPlugin\Packages\MyCustom\Conditions;

use MilliRules\Conditions\BaseCondition;
use MilliRules\Context;

class PurchaseCountCondition extends BaseCondition {
    protected function get_actual_value(Context $context) {
        // Load package context data
        $context->load('my_custom');

        // Use data from package context
        $purchases = $context->get('my_custom.user.purchases', []);
        return count($purchases);
    }

    public function get_type(): string {
        return 'purchase_count';
    }
}
```

---

## Creating Package Actions

Package actions provide functionality specific to your package's domain.

### Simple Package Action

```php
namespace MyPlugin\Packages\MyCustom\Actions;

use MilliRules\Actions\BaseAction;
use MilliRules\Context;

class UpdateUserLevelAction extends BaseAction {
    public function execute(Context $context): void {
        $context->load('user');
        $user_id = $context->get('user.id', 0);
        $level = $this->config['level'] ?? 1;

        if (!$user_id) {
            error_log('UpdateUserLevelAction: No user logged in');
            return;
        }

        update_user_meta($user_id, 'user_level', $level);
        error_log("Updated user {$user_id} to level {$level}");
    }

    public function get_type(): string {
        return 'update_user_level';
    }
}
```

### Action with Placeholder Support

```php
namespace MyPlugin\Packages\MyCustom\Actions;

use MilliRules\Actions\BaseAction;
use MilliRules\Context;

class SendNotificationAction extends BaseAction {
    public function execute(Context $context): void {
        // Resolve placeholders
        $message = $this->resolve_value($this->config['message'] ?? '');
        $recipient = $this->resolve_value($this->config['to'] ?? '');

        // Send notification
        $this->send_notification($recipient, $message);
    }

    private function send_notification($to, $message) {
        // Implementation...
        wp_mail($to, 'Notification', $message);
    }

    public function get_type(): string {
        return 'send_notification';
    }
}
```

---

## Adding Placeholder Resolvers

Placeholder resolvers enable dynamic values in rules.

### Basic Placeholder Resolver

```php
use MilliRules\Context;

public function get_placeholder_resolver(Context $context) {
    return function($placeholder_parts) use ($context) {
        // $placeholder_parts = ['my_custom', 'category', 'key']
        // From placeholder: {my_custom:category:key}

        if ($placeholder_parts[0] !== 'my_custom') {
            return null; // Not for this package
        }

        // Load context data if not already loaded
        $context->load('my_custom');

        // Convert parts to dot notation path
        $path = implode('.', $placeholder_parts);

        // Get value from context
        $value = $context->get($path, '');

        return is_scalar($value) ? (string) $value : '';
    };
}
```

**Usage**:
```php
Rules::create('use_custom_placeholder')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('log', [
            'message' => 'Site: {my_custom:site:name}, Users: {my_custom:stats:total_users}'
        ])
    ->register();
```

### Advanced Placeholder Resolver

```php
use MilliRules\Context;

public function get_placeholder_resolver(Context $context) {
    return function($placeholder_parts) use ($context) {
        if ($placeholder_parts[0] !== 'my_custom') {
            return null;
        }

        $category = $placeholder_parts[1] ?? '';
        $key = $placeholder_parts[2] ?? '';

        // Load context data once
        $context->load('my_custom');

        switch ($category) {
            case 'user':
                return $this->resolve_user_placeholder($context, $key);

            case 'product':
                return $this->resolve_product_placeholder($context, $key);

            case 'setting':
                return $this->resolve_setting_placeholder($context, $key);

            default:
                return '';
        }
    };
}

private function resolve_user_placeholder(Context $context, $key) {
    switch ($key) {
        case 'level':
            return $context->get('my_custom.user.level', '0');
        case 'points':
            return $context->get('my_custom.user.points', '0');
        default:
            return $context->get("my_custom.user.{$key}", '');
    }
}
```

---

## Declaring Package Dependencies

Packages can require other packages.

### Simple Dependency

```php
public function get_required_packages(): array {
    return ['PHP']; // Requires PHP package
}
```

### Multiple Dependencies

```php
public function get_required_packages(): array {
    return ['PHP', 'WP']; // Requires both PHP and WordPress packages
}
```

> [!WARNING]
> Avoid circular dependencies. Package A should not require Package B if Package B requires Package A. MilliRules will detect this and throw an error.

---

## Complete Custom Package Example

Here's a complete example of a custom package for a membership system:

```php
namespace MyPlugin\Packages;

use MilliRules\Packages\BasePackage;

class MembershipPackage extends BasePackage {
    public function get_name(): string {
        return 'Membership';
    }

    public function get_namespaces(): array {
        return [
            'MyPlugin\Packages\Membership\Conditions',
            'MyPlugin\Packages\Membership\Actions',
        ];
    }

    public function is_available(): bool {
        // Check if membership system is active
        return class_exists('My_Membership_System');
    }

    public function get_required_packages(): array {
        return ['PHP', 'WP']; // Requires both PHP and WordPress
    }

    public function register_providers(Context $context): void {
        // Register membership provider (loads on-demand)
        $context->register_provider('membership', function() {
            $user_id = get_current_user_id();

            $membership_data = [];
            if ($user_id) {
                $membership_data = [
                    'level' => get_user_meta($user_id, 'membership_level', true) ?: 'free',
                    'status' => get_user_meta($user_id, 'membership_status', true) ?: 'inactive',
                    'expiry' => get_user_meta($user_id, 'membership_expiry', true) ?: 0,
                    'features' => $this->get_user_features($user_id),
                ];
            }

            return [
                'membership' => [
                    'user' => $membership_data,
                    'levels' => $this->get_available_levels(),
                    'features' => $this->get_all_features(),
                ],
            ];
        });
    }

    public function get_placeholder_resolver(Context $context) {
        return function($parts) use ($context) {
            if ($parts[0] !== 'membership') {
                return null;
            }

            // Load membership context if not already loaded
            $context->load('membership');

            $category = $parts[1] ?? '';
            $key = $parts[2] ?? '';

            if ($category === 'user') {
                return $context->get("membership.user.{$key}", '');
            }

            return '';
        };
    }

    private function get_user_features($user_id) {
        // Get features available to user
        return ['feature1', 'feature2'];
    }

    private function get_available_levels() {
        return ['free', 'basic', 'premium', 'enterprise'];
    }

    private function get_all_features() {
        return ['feature1', 'feature2', 'feature3'];
    }
}
```

**Membership Condition Example**:

```php
namespace MyPlugin\Packages\Membership\Conditions;

use MilliRules\Conditions\BaseCondition;
use MilliRules\Context;

class MembershipLevelCondition extends BaseCondition {
    protected function get_actual_value(Context $context) {
        $context->load('membership');
        return $context->get('membership.user.level', 'free');
    }

    public function get_type(): string {
        return 'membership_level';
    }
}
```

**Membership Action Example**:

```php
namespace MyPlugin\Packages\Membership\Actions;

use MilliRules\Actions\BaseAction;
use MilliRules\Context;

class UpgradeMembershipAction extends BaseAction {
    public function execute(Context $context): void {
        $context->load('user');
        $user_id = $context->get('user.id', 0);
        $new_level = $this->config['level'] ?? 'basic';

        if (!$user_id) {
            return;
        }

        update_user_meta($user_id, 'membership_level', $new_level);
        update_user_meta($user_id, 'membership_status', 'active');

        // Resolve message with placeholders
        $message = $this->resolve_value(
            $this->config['message'] ?? 'Upgraded to {membership:user:level}'
        );

        error_log($message);
    }

    public function get_type(): string {
        return 'upgrade_membership';
    }
}
```

**Using the Custom Package**:

```php
use MilliRules\MilliRules;
use MyPlugin\Packages\MembershipPackage;

// Initialize with custom package
$membership_package = new MembershipPackage();
MilliRules::init(null, [$membership_package]);

// Create rule using package conditions and actions
Rules::create('auto_upgrade_frequent_buyers')
    ->when()
        ->is_user_logged_in()                                    // WP condition
        ->custom('membership_level', ['value' => 'free'])        // Membership condition
        ->custom('purchase_count', ['value' => 10, 'operator' => '>='])
    ->then()
        ->custom('upgrade_membership', [
            'level' => 'premium',
            'message' => 'Congratulations! Upgraded to premium membership.'
        ])
    ->register();
```

---

## Real-World Example: Acorn MilliRules

The [Acorn MilliRules](https://github.com/MilliPress/Acorn-MilliRules) package is a real-world custom package that extends MilliRules for the [Roots Acorn](https://roots.io/acorn/) framework. It's a good reference for how to structure a production package.

### Package Class

The Acorn package registers route-aware conditions, HTTP response actions, and a route context provider:

```php
namespace MilliRules\Acorn\Packages\Acorn;

use MilliRules\Acorn\Packages\Acorn\Contexts\Route;
use MilliRules\Packages\BasePackage;

class Package extends BasePackage
{
    public function get_name(): string
    {
        return 'Acorn';
    }

    public function get_namespaces(): array
    {
        return [
            'MilliRules\\Acorn\\Packages\\Acorn\\Actions',
            'MilliRules\\Acorn\\Packages\\Acorn\\Conditions',
            'MilliRules\\Acorn\\Packages\\Acorn\\Contexts',
        ];
    }

    public function is_available(): bool
    {
        return function_exists('app');
    }

    public function get_required_packages(): array
    {
        return ['PHP'];
    }
}
```

### What It Provides

| Component              | Description                                                     |
|------------------------|-----------------------------------------------------------------|
| **Conditions**         | `RouteName`, `RouteParameter`, `RouteController`                |
| **Actions**            | `Redirect`, `SetHeader`                                         |
| **Context**            | Route metadata (name, parameters, controller, URI, middleware)   |
| **Auto-discovery**     | Rule classes in `app/Rules/` are registered automatically        |
| **Artisan commands**   | 8 CLI commands to list, inspect, and scaffold rules              |

### Usage Example

```php
// app/Rules/RedirectLegacyDocs.php
namespace App\Rules;

use MilliRules\Rules;

class RedirectLegacyDocs
{
    public function register(): void
    {
        Rules::create('redirect_legacy_docs', 'Acorn')
            ->when()
                ->routeName('docs.*')
                ->routeParameter('product', ['value' => 'old-product'])
            ->then()
                ->redirect('/docs/new-product/', ['status' => 301])
            ->register();
    }
}
```

For full documentation, see the [Acorn MilliRules docs](https://millipress.com/docs/acorn-millirules/).

---

## Best Practices

### 1. Use Descriptive Package Names

```php
// ✅ Good - clear and specific
public function get_name(): string {
    return 'WooCommerce';
}

// ❌ Bad - vague or generic
public function get_name(): string {
    return 'Custom';
}
```

### 2. Validate Environment in is_available()

```php
// ✅ Good - comprehensive checks
public function is_available(): bool {
    return class_exists('WooCommerce')
        && function_exists('wc_get_product')
        && defined('WC_VERSION');
}

// ❌ Bad - minimal checking
public function is_available(): bool {
    return true;
}
```

### 3. Cache Expensive Context Data

```php
use MilliRules\Context;

// ✅ Good - caches API calls in lazy provider
public function register_providers(Context $context): void {
    $context->register_provider('my_package', function() {
        $data = get_transient('my_package_context');

        if ($data === false) {
            $data = $this->fetch_expensive_data();
            set_transient('my_package_context', $data, 300);
        }

        return ['my_package' => $data];
    });
}
```

**Note**: With lazy loading, this expensive data is only fetched when a rule actually needs it!

### 4. Document Your Package

```php
/**
 * Membership Package
 *
 * Provides membership-related conditions and actions.
 *
 * Conditions:
 * - membership_level: Check user's membership level
 * - membership_status: Check membership status
 * - has_feature: Check if user has access to feature
 *
 * Actions:
 * - upgrade_membership: Upgrade user to new level
 * - grant_feature: Grant feature access
 * - send_membership_email: Send membership-related email
 *
 * Context:
 * - membership.user.level: User's membership level
 * - membership.user.status: Membership status
 * - membership.user.features: Available features
 *
 * Placeholders:
 * - {membership:user:level}: User's membership level
 * - {membership:user:status}: Membership status
 */
class MembershipPackage extends BasePackage {
    // ...
}
```

---

## Common Pitfalls

### 1. Circular Dependencies

```php
// ❌ Wrong - circular dependency
class PackageA extends BasePackage {
    public function get_required_packages(): array {
        return ['PackageB']; // A requires B
    }
}

class PackageB extends BasePackage {
    public function get_required_packages(): array {
        return ['PackageA']; // B requires A - CIRCULAR!
    }
}
```

### 2. Accessing Unavailable Context

```php
use MilliRules\Context;

// ❌ Wrong - doesn't check availability
protected function get_actual_value(Context $context) {
    $context->load('user');
    return $context->get('user.id'); // May return null if not available!
}

// ✅ Correct - provides default value
protected function get_actual_value(Context $context) {
    $context->load('user');
    return $context->get('user.id', 0);
}
```

### 3. Expensive Provider Registration

```php
use MilliRules\Context;

// ❌ Wrong - executes expensive operation during registration
public function register_providers(Context $context): void {
    $data = expensive_api_call(); // Runs on every request!
    $context->register_provider('my_package', function() use ($data) {
        return ['my_package' => $data];
    });
}

// ✅ Correct - expensive operation runs only when provider loads
public function register_providers(Context $context): void {
    $context->register_provider('my_package', function() {
        $data = wp_cache_get('my_package_data', 'my_group');

        if ($data === false) {
            $data = expensive_api_call(); // Only runs when needed!
            wp_cache_set('my_package_data', $data, 'my_group', 300);
        }

        return ['my_package' => $data];
    });
}
```

---

## Next Steps

- **[Advanced Patterns](../04-advanced/02-advanced-patterns.md)** - Advanced package techniques
- **[WordPress Integration](../04-advanced/03-wordpress-integration.md)** - WordPress-specific patterns
- **[API Reference](../05-reference/03-api.md)** - Complete API documentation
- **[Real-World Examples](../04-advanced/01-examples.md)** - See complete package implementations

---

**Ready for advanced techniques?** Continue to [Advanced Patterns](../04-advanced/02-advanced-patterns.md) to learn optimization strategies and advanced rule patterns.
