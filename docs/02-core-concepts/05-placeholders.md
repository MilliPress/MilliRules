---
title: 'Dynamic Placeholders'
post_excerpt: 'Use dynamic placeholders to inject runtime values into your MilliRules conditions and actions with flexible context-based resolution.'
menu_order: 50
---

# Dynamic Placeholders

Placeholders allow you to inject dynamic runtime values into your rules. Instead of hardcoding values, you can reference contextual data using a simple colon-separated syntax that gets resolved during rule execution.

## What Are Placeholders?

Placeholders are special tokens enclosed in curly braces that get replaced with actual values from the execution context:

```php
// Static value
'value' => 'Fixed string'

// Dynamic placeholder
'value' => '{request.uri}'         // Current URL
'value' => '{request.method}'      // HTTP method
'value' => '{user.login}'          // Current user's login
'value' => '{cookie.session_id}'   // Session cookie value
```

## Placeholder Syntax

The placeholder syntax uses colon-separated parts to navigate the context hierarchy:

```
{category:subcategory:key}
```

- `category` - Top-level context category (request, user, post, cookie, etc.)
- `subcategory` - Nested category (optional, can have multiple levels)
- `key` - Specific value to retrieve

### Examples

```php
'{request.uri}'              // $context['request']['uri']
'{request.method}'           // $context['request']['method']
'{request:headers:host}'     // $context['request']['headers']['host']
'{user.id}'                  // $context['user']['id']
'{post.title}'               // $context['post']['title']
'{cookie.session_id}'        // $context['cookie']['session_id']
```

## Built-in Placeholder Categories

### Request Placeholders

Access HTTP request data from the PHP package context.

#### Available Request Placeholders

| Placeholder            | Description       | Example Value                  |
|------------------------|-------------------|--------------------------------|
| `{request.method}`     | HTTP method       | `GET`, `POST`                  |
| `{request.uri}`        | Full request URI  | `/wp-admin/edit.php`           |
| `{request.scheme}`     | URL scheme        | `https`                        |
| `{request.host}`       | Host name         | `example.com`                  |
| `{request.path}`       | URL path          | `/wp-admin/edit.php`           |
| `{request.query}`      | Query string      | `post_type=page`               |
| `{request.referer}`    | HTTP referer      | `https://example.com/previous` |
| `{request.user_agent}` | User agent string | `Mozilla/5.0...`               |
| `{request.ip}`         | Client IP address | `192.168.1.1`                  |

#### Request Headers

```php
'{request:headers:content-type}'    // Content-Type header
'{request:headers:authorization}'   // Authorization header
'{request:headers:accept}'          // Accept header
'{request:headers:user-agent}'      // User-Agent header
```

> [!NOTE]
> Header names in placeholders are case-insensitive: `{request:headers:Content-Type}` and `{request:headers:content-type}` are equivalent.

#### Examples

```php
Rules::register_action('log_request', function($args, Context $context) {
    $message = $args['value'] ?? '';
    error_log($message);
});

Rules::create('log_requests')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('log_request', [
            'value' => 'API request: {request.method} {request.uri} from {request.ip}'
        ])
    ->register();

// Logs: "API request: GET /api/users from 192.168.1.1"
```

---

### Cookie Placeholders

Access cookie values.

```php
'{cookie.session_id}'        // $_COOKIE['session_id']
'{cookie.user_preference}'   // $_COOKIE['user_preference']
'{cookie.theme}'             // $_COOKIE['theme']
```

#### Examples

```php
Rules::register_action('personalize', function($args, Context $context) {
    $theme = $args['theme'] ?? 'default';
    apply_theme($theme);
});

Rules::create('apply_user_theme')
    ->when()->cookie('theme')
    ->then()
        ->custom('personalize', [
            'theme' => '{cookie.theme}'  // Uses cookie value
        ])
    ->register();
```

---

### Parameter Placeholders

Access query and form parameters.

```php
'{param.action}'         // $_GET['action'] or $_POST['action']
'{param.id}'            // $_GET['id'] or $_POST['id']
'{param.page}'          // $_GET['page'] or $_POST['page']
```

#### Examples

```php
Rules::register_action('process_action', function($args, Context $context) {
    $action = $args['action'] ?? '';
    $id = $args['id'] ?? 0;
    error_log("Processing action: {$action} for ID: {$id}");
});

Rules::create('process_request')
    ->when()
        ->request_param('action')
        ->request_param('id')
    ->then()
        ->custom('process_action', [
            'action' => '{param.action}',
            'id' => '{param.id}'
        ])
    ->register();
```

---

### WordPress Placeholders

Access WordPress-specific data (available only when WordPress package is loaded).

#### User Placeholders

| Placeholder           | Description        | Example Value     |
|-----------------------|--------------------|-------------------|
| `{user.id}`           | User ID            | `123`             |
| `{user.login}`        | User login name    | `john_doe`        |
| `{user.email}`        | User email         | `john@example.com`|
| `{user.display_name}` | Display name       | `John Doe`        |
| `{user.roles}`        | User roles (array) | `administrator`   |

#### Post Placeholders

| Placeholder     | Description  | Example Value      |
|-----------------|--------------|--------------------|
| `{post.id}`     | Post ID      | `456`              |
| `{post.title}`  | Post title   | `My Blog Post`     |
| `{post.type}`   | Post type    | `post`, `page`     |
| `{post.status}` | Post status  | `publish`, `draft` |
| `{post.author}` | Author ID    | `123`              |

#### Query Variable Placeholders

Access WordPress query variables from `$wp_query->query_vars`:

| Placeholder         | Description           | Example Value      |
|---------------------|-----------------------|--------------------|
| `{query.post_type}` | Current post type     | `'post'`, `'page'` |
| `{query.paged}`     | Current page number   | `1`, `2`, `3`      |
| `{query.s}`         | Search query          | `'search term'`    |
| `{query.m}`         | Month/year archive    | `'202312'`         |
| `{query.cat}`       | Category ID           | `'5'`              |
| `{query.tag}`       | Tag slug              | `'news'`           |
| `{query.author}`    | Author ID or name     | `'1'`              |

#### Examples

```php
use MilliRules\Context;

Rules::register_action('log_user_action', function($args, Context $context) {
    error_log($args['message'] ?? '');
});

Rules::create('log_search')
    ->when()
        ->request_url('/search')
        ->is_search()
    ->then()
        ->custom('log_user_action', [
            'message' => 'User {user.login} searched for "{query.s}" on {request.uri}'
        ])
    ->register();

// Logs: "User john_doe searched for "wordpress plugins" on /somewhere"
```

---

## Using Placeholders in Actions

Placeholders are primarily used in action configurations to inject dynamic values.

### Basic Usage

```php
Rules::register_action('send_notification', function($args, Context $context) {
    $to = $args['to'] ?? '';
    $subject = $args['subject'] ?? '';
    $message = $args['message'] ?? '';

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
            'message' => 'User {user.login} logged in from {request.ip}'
        ])
    ->register();
```

### Multiple Placeholders

```php
Rules::register_action('log_detailed', function($args, Context $context) {
    error_log($args['message'] ?? '');
});

Rules::create('detailed_logging')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('log_detailed', [
            'message' => '{request.method} request to {request.uri} from {request.ip} '
                       . 'at {request.timestamp} by user {user.login}'
        ])
    ->register();
```

### Nested Placeholders

```php
Rules::register_action('set_header', function($args, Context $context) {
    $name = $args['name'] ?? '';
    $value = $args['value'] ?? '';

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
use MilliRules\PlaceholderResolver;

Rules::register_action('manual_resolution', function($args, Context $context) {
    $resolver = new PlaceholderResolver($context);

    // Resolve individual value
    $message = $resolver->resolve($args['message'] ?? '');

    // Use resolved value
    error_log($message);
});

Rules::create('use_manual_resolution')
    ->when()->request_url('/test')
    ->then()
        ->custom('manual_resolution', [
            'message' => 'Testing from {request.ip}'
        ])
    ->register();
```

---

## Creating Custom Placeholder Resolvers

Register custom placeholder categories for your own data sources.

### Registering Custom Resolvers

```php
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
Rules::register_action('log_custom', function($args, Context $context) {
    error_log($args['message'] ?? '');
});

Rules::create('use_custom_placeholders')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('log_custom', [
            'message' => 'Request to {custom.site_name} at {custom.current_time}'
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
// {env.name}           → 'production'
// {env.debug}          → true/false
// {env:var:API_KEY}    → getenv('API_KEY')
// {env:server:http_host} → $_SERVER['HTTP_HOST']
```

---

## Advanced Placeholder Patterns

### Conditional Placeholders

Use placeholders with fallback values:

```php
Rules::register_action('log_with_fallback', function($args, Context $context) {
    $resolver = new PlaceholderResolver($context);

    // Resolve with fallback
    $user = $resolver->resolve($args['user'] ?? '') ?: 'guest';
    $message = "User: {$user}";

    error_log($message);
});

Rules::create('log_with_defaults')
    ->when()->request_url('*')
    ->then()
        ->custom('log_with_fallback', [
            'user' => '{user.login}'  // Falls back to 'guest' if empty
        ])
    ->register();
```

### Placeholder Transformation

Transform placeholder values:

```php
Rules::register_action('transform_placeholder', function($args, Context $context) {
    $resolver = new PlaceholderResolver($context);
    $value = $resolver->resolve($args['value'] ?? '');

    // Transform resolved value
    $transformed = strtoupper($value);
    $transformed = sanitize_text_field($transformed);

    error_log($transformed);
});
```

### Array Placeholders

Access array values:

```php
// Access first role
'{user:roles:0}'        // First role

// Access header values
'{request:headers:accept}' // Accept header
```

### Object Property Access

Access public properties and magic properties on objects using dot notation:

```php
// Access public object properties
'{hook:args:0:ID}'           // WP_Post object's ID property
'{hook:args:0:post_title}'   // WP_Post object's post_title property
'{hook:args:0:post_author}'  // WP_Post object's post_author property

// Access magic properties (via __get() method)
'{hook:args:2:permalink}'    // WP_Post object's permalink (magic property)

// Mixed array and object access
'{hook:args:2:ID}'          // Third argument (index 2) → object's ID property
```

#### WordPress Hook Examples

WordPress hooks often pass objects as arguments. You can now access their properties directly:

```php
use MilliRules\Context;

// Example: transition_post_status hook passes (new_status, old_status, $post)
Rules::register_action('clear_post_cache', function($args, Context $context) {
    $url = $args['url'] ?? '';
    // Clear cache for the URL
    wp_cache_delete($url);
});

Rules::create('clear_on_publish')
    ->when()
        ->hook_is('transition_post_status')
        ->hook_arg(0, '==', 'publish')  // New status is 'publish'
    ->then()
        ->custom('clear_post_cache', [
            'url' => '{hook:args:2:permalink}'  // Access WP_Post's permalink property
        ])
    ->register();
```

#### Nested Objects and Arrays

Combine array and object access for complex data structures:

```php
// WordPress comment object in an array
'{comments:0:comment_author}'       // First comment's author
'{comments:0:comment_content}'      // First comment's content

// API response with nested objects
'{api:response:data:items:0:id}'    // First item's ID from API response

// Custom data structures
'{data:user:profile:settings}'      // Access nested object properties
```

#### How It Works

When resolving placeholders, MilliRules automatically detects whether each segment is:
- **Array access**: Uses `isset()` and `$array[$key]`
- **Object property access**: Checks `property_exists()` for public properties, or `__get()` for magic properties

This allows seamless access to mixed array/object structures without special syntax.

---

## Placeholder Resolution Flow

Understanding how placeholders are resolved:

```
1. Action configuration contains placeholder: "{request.uri}"
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
// ✅ Good - clear what data is being used
'message' => 'User {user.login} accessed {request.uri}'

// ❌ Bad - unclear placeholders
'message' => 'User {u} accessed {r}'
```

### 2. Provide Fallback Values

```php
Rules::register_action('safe_action', function($args, Context $context) {
    $resolver = new PlaceholderResolver($context);

    // Resolve with fallback
    $user = $resolver->resolve($args['user'] ?? '') ?: 'Unknown User';
    $ip = $resolver->resolve($args['ip'] ?? '') ?: '0.0.0.0';

    error_log("User: {$user}, IP: {$ip}");
});
```

### 3. Validate Resolved Values

```php
Rules::register_action('validated_action', function($args, Context $context) {
    $resolver = new PlaceholderResolver($context);
    $email = $resolver->resolve($args['email'] ?? '');

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
/**
 * Custom Placeholder: {payment.gateway}
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
// ❌ Wrong - WordPress placeholders in PHP-only context
Rules::create('php_rule', 'php')
    ->when()->request_url('/api/*')
    ->then()
        ->custom('action', [
            'value' => '{user.login}'  // Empty! WordPress not available
        ])
    ->register();

// ✅ Correct - check context availability
Rules::register_action('safe_wp_action', function($args, Context $context) {
    if (!isset($context['wp'])) {
        error_log('WordPress context not available');
        return;
    }

    $resolver = new PlaceholderResolver($context);
    $user = $resolver->resolve('{user.login}');
    // ...
});
```

### 2. Incorrect Placeholder Syntax

```php
// ❌ Wrong - missing braces
'value' => 'request:uri'

// ❌ Wrong - incorrect separator
'value' => '{request.uri}'

// ✅ Correct - proper syntax
'value' => '{request.uri}'
```

### 3. Case Sensitivity

```php
// Context keys are case-sensitive
// ✅ Correct
'{request.uri}'

// ❌ Wrong
'{Request:URI}'
'{REQUEST:URI}'
```

---

## Next Steps

- **[Understanding the Package System](02-packages.md)** - Learn about package architecture
- **[Creating Custom Actions](../03-customization/02-custom-actions.md)** - Implement actions with placeholders
- **[Advanced Patterns](../04-advanced/02-advanced-patterns.md)** - Advanced placeholder techniques
- **[Real-World Examples](../04-advanced/01-examples.md)** - See placeholders in action

---

**Ready to extend MilliRules?** Continue to [Creating Custom Packages](../03-customization/03-custom-packages.md) to learn how to add your own context data and placeholders.
