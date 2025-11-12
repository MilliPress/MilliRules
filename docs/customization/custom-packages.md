---
title: 'Creating Custom Packages'
post_excerpt: 'Learn how to create custom MilliRules packages with conditions, actions, context building, and placeholder resolvers for complete extensibility.'
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

##Package Structure

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
<?php
namespace MilliRules\Interfaces;

interface PackageInterface {
    public function get_name(): string;
    public function get_namespaces(): array;
    public function is_available(): bool;
    public function get_required_packages(): array;
    public function build_context(): array;
    public function get_placeholder_resolver(array $context);
    public function register_rule(array $rule, array $metadata);
    public function execute_rules(array $rules, array $context): array;
}
```

---

## Basic Custom Package

### Minimal Package Implementation

```php
<?php
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
<?php
use MilliRules\MilliRules;
use MyPlugin\Packages\MyCustomPackage;

// Register custom package
$custom_package = new MyCustomPackage();
MilliRules::init(null, [$custom_package]);

// Or let MilliRules auto-discover (if registered globally)
MilliRules::init();
```

---

## Building Context

Context provides data to all conditions and actions.

### Simple Context

```php
<?php
public function build_context(): array {
    return [
        'my_custom' => [
            'value1' => get_option('my_option_1'),
            'value2' => get_option('my_option_2'),
            'timestamp' => time(),
        ],
    ];
}
```

### Dynamic Context

```php
<?php
public function build_context(): array {
    global $wpdb;

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
}

private function get_user_purchases($user_id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}purchases WHERE user_id = %d",
        $user_id
    ));
}
```

### Context with External API

```php
<?php
public function build_context(): array {
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
}
```

---

## Creating Package Conditions

Package conditions extend the available condition types.

### Simple Package Condition

```php
<?php
namespace MyPlugin\Packages\MyCustom\Conditions;

use MilliRules\Conditions\BaseCondition;

class UserLevelCondition extends BaseCondition {
    protected function get_actual_value(array $context) {
        $user_id = $context['wp']['user']['id'] ?? 0;

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
<?php
Rules::create('premium_users')
    ->when()
        ->custom('user_level', ['value' => 5, 'operator' => '>='])
    ->then()
        ->custom('show_premium_content')
    ->register();
```

### Condition Using Package Context

```php
<?php
namespace MyPlugin\Packages\MyCustom\Conditions;

use MilliRules\Conditions\BaseCondition;

class PurchaseCountCondition extends BaseCondition {
    protected function get_actual_value(array $context) {
        // Use data from package context
        $purchases = $context['my_custom']['user']['purchases'] ?? [];
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
<?php
namespace MyPlugin\Packages\MyCustom\Actions;

use MilliRules\Actions\BaseAction;

class UpdateUserLevelAction extends BaseAction {
    public function execute(array $context): void {
        $user_id = $context['wp']['user']['id'] ?? 0;
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
<?php
namespace MyPlugin\Packages\MyCustom\Actions;

use MilliRules\Actions\BaseAction;

class SendNotificationAction extends BaseAction {
    public function execute(array $context): void {
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
<?php
public function get_placeholder_resolver(array $context) {
    return function($placeholder_parts) use ($context) {
        // $placeholder_parts = ['my_custom', 'category', 'key']
        // From placeholder: {my_custom:category:key}

        if ($placeholder_parts[0] !== 'my_custom') {
            return null; // Not for this package
        }

        // Remove package name
        array_shift($placeholder_parts);

        // Navigate context
        $value = $context['my_custom'] ?? [];
        foreach ($placeholder_parts as $part) {
            if (!isset($value[$part])) {
                return '';
            }
            $value = $value[$part];
        }

        return is_scalar($value) ? (string) $value : '';
    };
}
```

**Usage**:
```php
<?php
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
<?php
public function get_placeholder_resolver(array $context) {
    return function($placeholder_parts) use ($context) {
        if ($placeholder_parts[0] !== 'my_custom') {
            return null;
        }

        $category = $placeholder_parts[1] ?? '';
        $key = $placeholder_parts[2] ?? '';

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

private function resolve_user_placeholder($context, $key) {
    $user_data = $context['my_custom']['user'] ?? [];

    switch ($key) {
        case 'level':
            return $user_data['level'] ?? '0';
        case 'points':
            return $user_data['points'] ?? '0';
        default:
            return $user_data[$key] ?? '';
    }
}
```

---

## Declaring Package Dependencies

Packages can require other packages.

### Simple Dependency

```php
<?php
public function get_required_packages(): array {
    return ['PHP']; // Requires PHP package
}
```

### Multiple Dependencies

```php
<?php
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
<?php
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

    public function build_context(): array {
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
    }

    public function get_placeholder_resolver(array $context) {
        return function($parts) use ($context) {
            if ($parts[0] !== 'membership') {
                return null;
            }

            $category = $parts[1] ?? '';
            $key = $parts[2] ?? '';

            if ($category === 'user') {
                $user_data = $context['membership']['user'] ?? [];
                return $user_data[$key] ?? '';
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
<?php
namespace MyPlugin\Packages\Membership\Conditions;

use MilliRules\Conditions\BaseCondition;

class MembershipLevelCondition extends BaseCondition {
    protected function get_actual_value(array $context) {
        return $context['membership']['user']['level'] ?? 'free';
    }

    public function get_type(): string {
        return 'membership_level';
    }
}
```

**Membership Action Example**:

```php
<?php
namespace MyPlugin\Packages\Membership\Actions;

use MilliRules\Actions\BaseAction;

class UpgradeMembershipAction extends BaseAction {
    public function execute(array $context): void {
        $user_id = $context['wp']['user']['id'] ?? 0;
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
<?php
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

## Best Practices

### 1. Use Descriptive Package Names

```php
<?php
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
<?php
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
<?php
// ✅ Good - caches API calls
public function build_context(): array {
    $data = get_transient('my_package_context');

    if ($data === false) {
        $data = $this->fetch_expensive_data();
        set_transient('my_package_context', $data, 300);
    }

    return ['my_package' => $data];
}
```

### 4. Document Your Package

```php
<?php
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
<?php
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
<?php
// ❌ Wrong - doesn't check availability
protected function get_actual_value(array $context) {
    return $context['wp']['user']['id']; // May not exist!
}

// ✅ Correct - checks before accessing
protected function get_actual_value(array $context) {
    return $context['wp']['user']['id'] ?? 0;
}
```

### 3. Expensive Context Building

```php
<?php
// ❌ Wrong - expensive operation on every request
public function build_context(): array {
    $data = expensive_api_call(); // Slows down every request!
    return ['my_package' => $data];
}

// ✅ Correct - caches expensive operations
public function build_context(): array {
    $data = wp_cache_get('my_package_data', 'my_group');

    if ($data === false) {
        $data = expensive_api_call();
        wp_cache_set('my_package_data', $data, 'my_group', 300);
    }

    return ['my_package' => $data];
}
```

---

## Next Steps

- **[Advanced Usage Patterns](../advanced/usage.md)** - Advanced package techniques
- **[WordPress Integration](../advanced/wordpress-integration.md)** - WordPress-specific patterns
- **[API Reference](../reference/api.md)** - Complete API documentation
- **[Real-World Examples](../advanced/examples.md)** - See complete package implementations

---

**Ready for advanced techniques?** Continue to [Advanced Usage Patterns](../advanced/usage.md) to learn optimization strategies and advanced rule patterns.
