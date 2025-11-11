---
post_title: 'Dynamic Placeholders'
post_excerpt: 'Use dynamic placeholders to inject runtime values into your MilliRules conditions and actions with flexible context-based resolution.'
taxonomy:
  category:
    - documentation
    - advanced
  post_tag:
    - placeholders
    - dynamic-values
    - context
    - runtime
menu_order: 7
---

# Dynamic Placeholders

Placeholders allow you to inject dynamic runtime values into your rules. Instead of hardcoding values, you can reference contextual data using a simple colon-separated syntax that gets resolved during rule execution.

## What Are Placeholders?

Placeholders are special tokens enclosed in curly braces that get replaced with actual values from the execution context:

```php
<?php
// Static value
'value' => 'Fixed string'

// Dynamic placeholder
'value' => '{request:uri}'         // Current URL
'value' => '{request:method}'      // HTTP method
'value' => '{wp:user:login}'       // Current user's login
'value' => '{cookie:session_id}'   // Session cookie value
```

## Placeholder Syntax

The placeholder syntax uses colon-separated parts to navigate the context hierarchy:

```
{category:subcategory:key}
```

- `category` - Top-level context category (request, wp, cookie, etc.)
- `subcategory` - Nested category (optional, can have multiple levels)
- `key` - Specific value to retrieve

### Examples

```php
<?php
'{request:uri}'              // $context['request']['uri']
'{request:method}'           // $context['request']['method']
'{request:headers:host}'     // $context['request']['headers']['host']
'{wp:user:id}'              // $context['wp']['user']['id']
'{wp:post:post_title}'      // $context['wp']['post']['post_title']
'{cookie:session_id}'        // $context['request']['cookies']['session_id']
```

## Built-in Placeholder Categories

### Request Placeholders

Access HTTP request data from the PHP package context.

#### Available Request Placeholders

| Placeholder | Description | Example Value |
|------------|-------------|---------------|
| `{request:method}` | HTTP method | `GET`, `POST` |
| `{request:uri}` | Full request URI | `/wp-admin/edit.php` |
| `{request:scheme}` | URL scheme | `https` |
| `{request:host}` | Host name | `example.com` |
| `{request:path}` | URL path | `/wp-admin/edit.php` |
| `{request:query}` | Query string | `post_type=page` |
| `{request:referer}` | HTTP referer | `https://example.com/previous` |
| `{request:user_agent}` | User agent string | `Mozilla/5.0...` |
| `{request:ip}` | Client IP address | `192.168.1.1` |

#### Request Headers

```php
<?php
'{request:headers:content-type}'    // Content-Type header
'{request:headers:authorization}'   // Authorization header
'{request:headers:accept}'          // Accept header
'{request:headers:user-agent}'      // User-Agent header
```

> [!NOTE]
> Header names in placeholders are case-insensitive: `{request:headers:Content-Type}` and `{request:headers:content-type}` are equivalent.

#### Examples

```php
<?php
Rules::register_action('log_request', function($context, $config) {
    $message = $config['value'] ?? '';
    error_log($message);
});

Rules::create('log_requests')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('log_request', [
            'value' => 'API request: {request:method} {request:uri} from {request:ip}'
        ])
    ->register();

// Logs: "API request: GET /api/users from 192.168.1.1"
```

---

### Cookie Placeholders

Access cookie values.

```php
<?php
'{cookie:session_id}'        // $_COOKIE['session_id']
'{cookie:user_preference}'   // $_COOKIE['user_preference']
'{cookie:theme}'             // $_COOKIE['theme']
```

#### Examples

```php
<?php
Rules::register_action('personalize', function($context, $config) {
    $theme = $config['theme'] ?? 'default';
    apply_theme($theme);
});

Rules::create('apply_user_theme')
    ->when()->cookie('theme')
    ->then()
        ->custom('personalize', [
            'theme' => '{cookie:theme}'  // Uses cookie value
        ])
    ->register();
```

---

### Parameter Placeholders

Access query and form parameters.

```php
<?php
'{param:action}'         // $_GET['action'] or $_POST['action']
'{param:id}'            // $_GET['id'] or $_POST['id']
'{param:page}'          // $_GET['page'] or $_POST['page']
```

#### Examples

```php
<?php
Rules::register_action('process_action', function($context, $config) {
    $action = $config['action'] ?? '';
    $id = $config['id'] ?? 0;
    error_log("Processing action: {$action} for ID: {$id}");
});

Rules::create('process_request')
    ->when()
        ->request_param('action')
        ->request_param('id')
    ->then()
        ->custom('process_action', [
            'action' => '{param:action}',
            'id' => '{param:id}'
        ])
    ->register();
```

---

### WordPress Placeholders

Access WordPress-specific data (available only when WordPress package is loaded).

#### User Placeholders

| Placeholder | Description | Example Value |
|------------|-------------|---------------|
| `{wp:user:id}` | User ID | `123` |
| `{wp:user:login}` | User login name | `john_doe` |
| `{wp:user:email}` | User email | `john@example.com` |
| `{wp:user:display_name}` | Display name | `John Doe` |
| `{wp:user:roles}` | User roles (array) | `administrator` |

#### Post Placeholders

| Placeholder | Description | Example Value |
|------------|-------------|---------------|
| `{wp:post:id}` | Post ID | `456` |
| `{wp:post:post_title}` | Post title | `My Blog Post` |
| `{wp:post:post_type}` | Post type | `post`, `page` |
| `{wp:post:post_status}` | Post status | `publish`, `draft` |
| `{wp:post:post_author}` | Author ID | `123` |

#### Query Placeholders

| Placeholder | Description | Example Value |
|------------|-------------|---------------|
| `{wp:query:is_singular}` | Is singular post | `true`, `false` |
| `{wp:query:is_home}` | Is home page | `true`, `false` |
| `{wp:query:is_archive}` | Is archive page | `true`, `false` |
| `{wp:query:is_admin}` | Is admin area | `true`, `false` |

#### Examples

```php
<?php
Rules::register_action('log_user_action', function($context, $config) {
    error_log($config['message'] ?? '');
});

Rules::create('log_post_edit')
    ->when()
        ->request_url('/wp-admin/post.php')
        ->is_user_logged_in()
    ->then()
        ->custom('log_user_action', [
            'message' => 'User {wp:user:login} (ID: {wp:user:id}) editing post {wp:post:id}'
        ])
    ->register();

// Logs: "User john_doe (ID: 123) editing post 456"
```

---

## Using Placeholders in Actions

Placeholders are primarily used in action configurations to inject dynamic values.

### Basic Usage

```php
<?php
Rules::register_action('send_notification', function($context, $config) {
    $to = $config['to'] ?? '';
    $subject = $config['subject'] ?? '';
    $message = $config['message'] ?? '';

    // Placeholders already resolved by BaseAction
    wp_mail($to, $subject, $message);
});

Rules::create('notify_on_login')
    ->when()
        ->is_user_logged_in()
        ->request_url('/wp-admin/*')
    ->then()
        ->custom('send_notification', [
            'to' => 'admin@example.com',
            'subject' => 'User Login Alert',
            'message' => 'User {wp:user:login} logged in from {request:ip}'
        ])
    ->register();
```

### Multiple Placeholders

```php
<?php
Rules::register_action('log_detailed', function($context, $config) {
    error_log($config['message'] ?? '');
});

Rules::create('detailed_logging')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('log_detailed', [
            'message' => '{request:method} request to {request:uri} from {request:ip} '
                       . 'at {request:timestamp} by user {wp:user:login}'
        ])
    ->register();
```

### Nested Placeholders

```php
<?php
Rules::register_action('set_header', function($context, $config) {
    $name = $config['name'] ?? '';
    $value = $config['value'] ?? '';

    if (!headers_sent()) {
        header("{$name}: {$value}");
    }
});

Rules::create('custom_header')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('set_header', [
            'name' => 'X-Request-ID',
            'value' => '{request:headers:x-request-id}'  // Forward header value
        ])
    ->register();
```

---

## Implementing Placeholder Resolution

### In BaseAction Subclasses

Actions extending `BaseAction` automatically get placeholder resolution:

```php
<?php
namespace MyPlugin\Actions;

use MilliRules\Actions\BaseAction;

class CustomNotificationAction extends BaseAction {
    public function execute(array $context): void {
        // Resolve placeholders in config values
        $message = $this->resolve_value($this->config['message'] ?? '');
        $recipient = $this->resolve_value($this->config['to'] ?? '');

        // Use resolved values
        wp_mail($recipient, 'Notification', $message);
    }

    public function get_type(): string {
        return 'custom_notification';
    }
}
```

### In Callback Actions

Callback actions need to manually resolve placeholders:

```php
<?php
use MilliRules\PlaceholderResolver;

Rules::register_action('manual_resolution', function($context, $config) {
    $resolver = new PlaceholderResolver($context);

    // Resolve individual value
    $message = $resolver->resolve($config['message'] ?? '');

    // Use resolved value
    error_log($message);
});

Rules::create('use_manual_resolution')
    ->when()->request_url('/test')
    ->then()
        ->custom('manual_resolution', [
            'message' => 'Testing from {request:ip}'
        ])
    ->register();
```

---

## Creating Custom Placeholder Resolvers

Register custom placeholder categories for your own data sources.

### Registering Custom Resolvers

```php
<?php
use MilliRules\Rules;

// Register custom placeholder category
Rules::register_placeholder('custom', function($context, $parts) {
    // $parts[0] is the first key after 'custom:'
    // $parts[1] is the second key, etc.

    switch ($parts[0] ?? '') {
        case 'site_name':
            return get_bloginfo('name');

        case 'site_url':
            return home_url();

        case 'current_time':
            return date('Y-m-d H:i:s');

        case 'option':
            return get_option($parts[1] ?? '');

        default:
            return '';
    }
});
```

### Using Custom Placeholders

```php
<?php
Rules::register_action('log_custom', function($context, $config) {
    error_log($config['message'] ?? '');
});

Rules::create('use_custom_placeholders')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('log_custom', [
            'message' => 'Request to {custom:site_name} at {custom:current_time}'
        ])
    ->register();

// Access WordPress options
Rules::create('use_option_placeholder')
    ->when()->request_url('/test')
    ->then()
        ->custom('log_custom', [
            'message' => 'Site tagline: {custom:option:blogdescription}'
        ])
    ->register();
```

### Complex Custom Resolvers

```php
<?php
Rules::register_placeholder('env', function($context, $parts) {
    $key = $parts[0] ?? '';

    // Environment variables
    if ($key === 'var') {
        return getenv($parts[1] ?? '');
    }

    // Server information
    if ($key === 'server') {
        return $_SERVER[strtoupper($parts[1] ?? '')] ?? '';
    }

    // Custom environment data
    $env_data = [
        'name' => WP_ENVIRONMENT_TYPE ?? 'production',
        'debug' => WP_DEBUG ?? false,
        'version' => get_bloginfo('version'),
    ];

    return $env_data[$key] ?? '';
});

// Usage:
// {env:name}           → 'production'
// {env:debug}          → true/false
// {env:var:API_KEY}    → getenv('API_KEY')
// {env:server:http_host} → $_SERVER['HTTP_HOST']
```

---

## Advanced Placeholder Patterns

### Conditional Placeholders

Use placeholders with fallback values:

```php
<?php
Rules::register_action('log_with_fallback', function($context, $config) {
    $resolver = new PlaceholderResolver($context);

    // Resolve with fallback
    $user = $resolver->resolve($config['user'] ?? '') ?: 'guest';
    $message = "User: {$user}";

    error_log($message);
});

Rules::create('log_with_defaults')
    ->when()->request_url('*')
    ->then()
        ->custom('log_with_fallback', [
            'user' => '{wp:user:login}'  // Falls back to 'guest' if empty
        ])
    ->register();
```

### Placeholder Transformation

Transform placeholder values:

```php
<?php
Rules::register_action('transform_placeholder', function($context, $config) {
    $resolver = new PlaceholderResolver($context);
    $value = $resolver->resolve($config['value'] ?? '');

    // Transform resolved value
    $transformed = strtoupper($value);
    $transformed = sanitize_text_field($transformed);

    error_log($transformed);
});
```

### Array Placeholders

Access array values:

```php
<?php
// Access first role
'{wp:user:roles:0}'        // First role

// Access header values
'{request:headers:accept}' // Accept header
```

---

## Placeholder Resolution Flow

Understanding how placeholders are resolved:

```
1. Action configuration contains placeholder: "{request:uri}"
   ↓
2. BaseAction::resolve_value() detects placeholder
   ↓
3. PlaceholderResolver splits by colons: ['request', 'uri']
   ↓
4. Looks up category 'request' in registered resolvers
   ↓
5. PHP package resolver handles 'request' category
   ↓
6. Returns $context['request']['uri']
   ↓
7. Placeholder replaced with actual value: "/api/users"
   ↓
8. Action executes with resolved value
```

---

## Best Practices

### 1. Use Descriptive Placeholder Names

```php
<?php
// ✅ Good - clear what data is being used
'message' => 'User {wp:user:login} accessed {request:uri}'

// ❌ Bad - unclear placeholders
'message' => 'User {u} accessed {r}'
```

### 2. Provide Fallback Values

```php
<?php
Rules::register_action('safe_action', function($context, $config) {
    $resolver = new PlaceholderResolver($context);

    // Resolve with fallback
    $user = $resolver->resolve($config['user'] ?? '') ?: 'Unknown User';
    $ip = $resolver->resolve($config['ip'] ?? '') ?: '0.0.0.0';

    error_log("User: {$user}, IP: {$ip}");
});
```

### 3. Validate Resolved Values

```php
<?php
Rules::register_action('validated_action', function($context, $config) {
    $resolver = new PlaceholderResolver($context);
    $email = $resolver->resolve($config['email'] ?? '');

    // Validate resolved value
    if (!is_email($email)) {
        error_log('Invalid email from placeholder');
        return;
    }

    // Use validated value
    wp_mail($email, 'Subject', 'Message');
});
```

### 4. Document Custom Placeholders

```php
<?php
/**
 * Custom Placeholder: {payment:gateway}
 * Returns the active payment gateway name
 *
 * Custom Placeholder: {payment:status:order_id}
 * Returns the payment status for a given order ID
 *
 * Example: {payment:status:123} → 'completed'
 */
Rules::register_placeholder('payment', function($context, $parts) {
    // Implementation...
});
```

---

## Common Pitfalls

### 1. Missing Context Data

```php
<?php
// ❌ Wrong - WordPress placeholders in PHP-only context
Rules::create('php_rule', 'php')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('action', [
            'value' => '{wp:user:login}'  // Empty! WordPress not available
        ])
    ->register();

// ✅ Correct - check context availability
Rules::register_action('safe_wp_action', function($context, $config) {
    if (!isset($context['wp'])) {
        error_log('WordPress context not available');
        return;
    }

    $resolver = new PlaceholderResolver($context);
    $user = $resolver->resolve('{wp:user:login}');
    // ...
});
```

### 2. Incorrect Placeholder Syntax

```php
<?php
// ❌ Wrong - missing braces
'value' => 'request:uri'

// ❌ Wrong - incorrect separator
'value' => '{request.uri}'

// ✅ Correct - proper syntax
'value' => '{request:uri}'
```

### 3. Case Sensitivity

```php
<?php
// Context keys are case-sensitive
// ✅ Correct
'{request:uri}'

// ❌ Wrong
'{Request:URI}'
'{REQUEST:URI}'
```

---

## Next Steps

- **[Understanding the Package System](08-packages.md)** - Learn about package architecture
- **[Creating Custom Actions](10-custom-actions.md)** - Implement actions with placeholders
- **[Advanced Usage Patterns](12-advanced-usage.md)** - Advanced placeholder techniques
- **[Real-World Examples](15-examples.md)** - See placeholders in action

---

**Ready to extend MilliRules?** Continue to [Creating Custom Packages](11-custom-packages.md) to learn how to add your own context data and placeholders.
