---
title: 'Built-in Conditions Reference'
post_excerpt: 'Complete reference guide for all built-in MilliRules conditions including PHP request conditions and WordPress-specific conditions.'
---

# Built-in Conditions Reference

MilliRules comes with a comprehensive set of built-in conditions for both framework-agnostic PHP applications and WordPress-specific scenarios. This reference guide documents every available condition with examples and usage patterns.

## Condition Packages

Conditions are organized into packages:

- **PHP Package** - Framework-agnostic HTTP and request conditions (always available)
- **WordPress Package** - WordPress-specific conditions (available only in WordPress)

## PHP Package Conditions

The PHP package provides framework-agnostic conditions that work in any PHP 7.4+ environment. These conditions handle HTTP requests, headers, cookies, and parameters.

### request_url

Check the request URL or URI path against a pattern or value.

**Namespace**: `MilliRules\Packages\PHP\Conditions\RequestUrl`

**Signature**:
```php
<?php
->request_url($value, $operator = '=')
```

**Parameters**:
- `$value` (string|array): URL pattern or array of patterns
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: All operators (=, !=, LIKE, REGEXP, IN, NOT IN, EXISTS, etc.)

**Context Data Used**: `$context->get('request.uri', '')`

#### Examples

**Exact match**:
```php
<?php
Rules::create('exact_url')
    ->when()
        ->request_url('/wp-admin/edit.php')  // Exact URL match
    ->then()->custom('action')
    ->register();
```

**Wildcard pattern matching**:
```php
<?php
Rules::create('admin_urls')
    ->when()
        ->request_url('/wp-admin/*', 'LIKE')  // Matches any admin URL
    ->then()->custom('action')
    ->register();

// Auto-detected LIKE operator (has wildcard)
Rules::create('api_urls')
    ->when()
        ->request_url('/api/*')  // LIKE operator auto-detected
    ->then()->custom('action')
    ->register();
```

**Multiple URL patterns**:
```php
<?php
Rules::create('protected_areas')
    ->when()
        ->request_url([
            '/wp-admin/*',
            '/wp-login.php',
            '/dashboard/*'
        ], 'IN')
    ->then()->custom('check_authentication')
    ->register();
```

**Regex matching**:
```php
<?php
Rules::create('api_versioned')
    ->when()
        // Matches /api/v1/, /api/v2/, etc.
        ->request_url('/^\\/api\\/v[0-9]+\\//i', 'REGEXP')
    ->then()->custom('route_api_request')
    ->register();
```

**Exclude patterns**:
```php
<?php
Rules::create('non_admin_urls')
    ->when()
        ->request_url('/wp-admin/*', 'NOT LIKE')  // Not admin URLs
    ->then()->custom('public_action')
    ->register();
```

> [!TIP]
> Use wildcards (`*` matches anything, `?` matches single character) for flexible pattern matching without the complexity of regex.

---

### request_method

Check the HTTP request method (GET, POST, PUT, DELETE, etc.).

**Namespace**: `MilliRules\Packages\PHP\Conditions\RequestMethod`

**Signature**:
```php
<?php
->request_method($value, $operator = '=')
```

**Parameters**:
- `$value` (string|array): HTTP method(s) to check
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: =, !=, IN, NOT IN, EXISTS

**Context Data Used**: `$context->get('request.method', '')`

#### Examples

**Single method**:
```php
<?php
Rules::create('post_requests')
    ->when()
        ->request_method('POST')  // Only POST requests
    ->then()->custom('process_form')
    ->register();
```

**Multiple methods** (OR logic):
```php
<?php
Rules::create('safe_methods')
    ->when()
        ->request_method(['GET', 'HEAD'], 'IN')  // GET or HEAD
    ->then()->custom('enable_caching')
    ->register();

// Auto-detected IN operator (array value)
Rules::create('safe_methods_auto')
    ->when()
        ->request_method(['GET', 'HEAD'])  // IN auto-detected
    ->then()->custom('enable_caching')
    ->register();
```

**Exclude methods**:
```php
<?php
Rules::create('non_post_requests')
    ->when()
        ->request_method('POST', '!=')
    ->then()->custom('action')
    ->register();

Rules::create('non_modifying_requests')
    ->when()
        ->request_method(['POST', 'PUT', 'DELETE', 'PATCH'], 'NOT IN')
    ->then()->custom('read_only_action')
    ->register();
```

> [!NOTE]
> HTTP methods are case-insensitive. Both `'POST'` and `'post'` work identically.

---

### request_header

Check request headers against expected values.

**Namespace**: `MilliRules\Packages\PHP\Conditions\RequestHeader`

**Signature**:
```php
<?php
->request_header($header_name, $value = null, $operator = '=')
```

**Parameters**:
- `$header_name` (string): Header name (case-insensitive)
- `$value` (mixed): Expected value (null to check existence)
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: All operators

**Context Data Used**: `$context['request']['headers'][$header_name]`

#### Examples

**Check header existence**:
```php
<?php
Rules::create('has_auth_header')
    ->when()
        ->request_header('Authorization')  // Header exists
    ->then()->custom('process_authenticated')
    ->register();
```

**Check header value**:
```php
<?php
Rules::create('json_requests')
    ->when()
        ->request_header('Content-Type', 'application/json')
    ->then()->custom('parse_json')
    ->register();
```

**Pattern matching headers**:
```php
<?php
Rules::create('bearer_token')
    ->when()
        ->request_header('Authorization', 'Bearer *', 'LIKE')
    ->then()->custom('validate_token')
    ->register();
```

**Multiple accepted values**:
```php
<?php
Rules::create('json_or_xml')
    ->when()
        ->request_header('Content-Type', [
            'application/json',
            'application/xml',
            'text/xml'
        ], 'IN')
    ->then()->custom('parse_structured_data')
    ->register();
```

**Regex for complex matching**:
```php
<?php
Rules::create('api_key_format')
    ->when()
        ->request_header('X-API-Key', '/^[A-Za-z0-9]{32}$/', 'REGEXP')
    ->then()->custom('validate_api_key')
    ->register();
```

> [!IMPORTANT]
> Header names are case-insensitive in HTTP. `'Content-Type'`, `'content-type'`, and `'CONTENT-TYPE'` all reference the same header.

---

### request_param

Check URL query parameters or form POST data.

**Namespace**: `MilliRules\Packages\PHP\Conditions\RequestParam`

**Signature**:
```php
<?php
->request_param($param_name, $value = null, $operator = '=')
```

**Parameters**:
- `$param_name` (string): Parameter name
- `$value` (mixed): Expected value (null to check existence)
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: All operators

**Context Data Used**: `$context['request']['params'][$param_name]` (merges $_GET and $_POST)

#### Examples

**Check parameter existence**:
```php
<?php
Rules::create('has_action_param')
    ->when()
        ->request_param('action')  // Parameter exists
    ->then()->custom('route_action')
    ->register();
```

**Check parameter value**:
```php
<?php
Rules::create('delete_action')
    ->when()
        ->request_param('action', 'delete')
    ->then()->custom('confirm_delete')
    ->register();
```

**Numeric comparison**:
```php
<?php
Rules::create('pagination')
    ->when()
        ->request_param('page', '1', '>')  // Page > 1
    ->then()->custom('show_pagination')
    ->register();
```

**Multiple accepted values**:
```php
<?php
Rules::create('list_actions')
    ->when()
        ->request_param('view', ['list', 'grid', 'table'], 'IN')
    ->then()->custom('render_list_view')
    ->register();
```

**Pattern matching**:
```php
<?php
Rules::create('search_query')
    ->when()
        ->request_param('s', '*product*', 'LIKE')  // Contains "product"
    ->then()->custom('enhance_product_search')
    ->register();
```

> [!NOTE]
> `request_param` checks both GET and POST parameters, with POST taking precedence if the same parameter exists in both.

---

### cookie

Check for cookie existence or value.

**Namespace**: `MilliRules\Packages\PHP\Conditions\Cookie`

**Signature**:
```php
<?php
->cookie($cookie_name, $value = null, $operator = '=')
```

**Parameters**:
- `$cookie_name` (string): Cookie name
- `$value` (mixed): Expected value (null to check existence)
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: All operators

**Context Data Used**: `$context['request']['cookies'][$cookie_name]` (from $_COOKIE)

#### Examples

**Check cookie existence**:
```php
<?php
Rules::create('has_session')
    ->when()
        ->cookie('session_id')  // Cookie exists
    ->then()->custom('load_session')
    ->register();
```

**Check cookie value**:
```php
<?php
Rules::create('theme_preference')
    ->when()
        ->cookie('theme', 'dark')
    ->then()->custom('apply_dark_theme')
    ->register();
```

**Cookie doesn't exist**:
```php
<?php
Rules::create('first_time_visitor')
    ->when()
        ->cookie('visited_before', null, 'NOT EXISTS')
    ->then()->custom('show_welcome_message')
    ->register();
```

**Multiple cookie values**:
```php
<?php
Rules::create('preferred_languages')
    ->when()
        ->cookie('lang', ['en', 'en-US', 'en-GB'], 'IN')
    ->then()->custom('use_english')
    ->register();
```

**Pattern matching cookies**:
```php
<?php
Rules::create('tracking_cookies')
    ->when()
        ->cookie('_ga', 'GA*', 'LIKE')  // Google Analytics cookie
    ->then()->custom('record_analytics')
    ->register();
```

> [!WARNING]
> Cookies are set by the client and can be manipulated. Never trust cookie values for security-critical decisions without additional validation.

---

### constant

Check PHP or WordPress constants.

**Namespace**: `MilliRules\Packages\PHP\Conditions\Constant`

**Signature**:
```php
<?php
->constant($constant_name, $value = null, $operator = '=')
```

**Parameters**:
- `$constant_name` (string): Constant name
- `$value` (mixed): Expected value (null to check existence)
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: All operators

**Context Data Used**: Uses `defined()` and `constant()` PHP functions

#### Examples

**Check constant existence**:
```php
<?php
Rules::create('has_debug_constant')
    ->when()
        ->constant('WP_DEBUG')  // Constant is defined
    ->then()->custom('enable_debug_mode')
    ->register();
```

**Check boolean constants**:
```php
<?php
Rules::create('debug_enabled')
    ->when()
        ->constant('WP_DEBUG', true)  // Debug is ON
    ->then()->custom('show_debug_info')
    ->register();

Rules::create('debug_disabled')
    ->when()
        ->constant('WP_DEBUG', false)  // Debug is OFF
    ->then()->custom('hide_debug_info')
    ->register();
```

**Check string constants**:
```php
<?php
Rules::create('local_environment')
    ->when()
        ->constant('WP_ENVIRONMENT_TYPE', 'local')
    ->then()->custom('enable_local_features')
    ->register();
```

**Multiple environment types**:
```php
<?php
Rules::create('non_production')
    ->when()
        ->constant('WP_ENVIRONMENT_TYPE', ['local', 'development'], 'IN')
    ->then()->custom('enable_dev_tools')
    ->register();
```

**Version checking**:
```php
<?php
Rules::create('php_version_check')
    ->when()
        ->constant('PHP_VERSION', '8.0', '>=')
    ->then()->custom('use_php8_features')
    ->register();
```

> [!TIP]
> Use constant conditions to create environment-specific rules that behave differently in development, staging, and production.

---

## WordPress Package Conditions

WordPress package conditions are available only when WordPress is detected. They provide access to WordPress-specific functionality and query information.

### is_user_logged_in

Check if a user is logged in to WordPress.

**Namespace**: `MilliRules\Packages\WordPress\Conditions\IsUserLoggedIn`

**Signature**:
```php
<?php
->is_user_logged_in($value = true, $operator = '=')
```

**Parameters**:
- `$value` (bool): Expected login state (default: `true`)
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: =, !=, IS, IS NOT

**Context Data Used**: `$context->get('user.id', 0)` (0 = not logged in)

#### Examples

**Check if logged in**:
```php
<?php
Rules::create('authenticated_users')
    ->when()
        ->is_user_logged_in()  // User is logged in
    ->then()->custom('show_dashboard')
    ->register();
```

**Check if NOT logged in**:
```php
<?php
Rules::create('guest_users')
    ->when()
        ->is_user_logged_in(false)  // User is NOT logged in
    ->then()->custom('show_login_prompt')
    ->register();
```

---

### is_singular

Check if the current page is a singular post, page, or custom post type.

**Namespace**: `MilliRules\Packages\WordPress\Conditions\IsSingular`

**Signature**:
```php
<?php
->is_singular($post_types = null, $operator = '=')
```

**Parameters**:
- `$post_types` (string|array|null): Post type(s) to check (null = any singular)
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: =, !=, IN, NOT IN, EXISTS

**Context Data Used**: `$context['wp']['query']['is_singular']`

#### Examples

**Any singular post**:
```php
<?php
Rules::create('any_singular')
    ->when()
        ->is_singular()  // Any single post/page
    ->then()->custom('show_related_content')
    ->register();
```

**Specific post type**:
```php
<?php
Rules::create('single_post')
    ->when()
        ->is_singular('post')  // Only blog posts
    ->then()->custom('show_post_metadata')
    ->register();
```

**Multiple post types**:
```php
<?php
Rules::create('content_pages')
    ->when()
        ->is_singular(['post', 'page'], 'IN')
    ->then()->custom('enable_comments')
    ->register();

// Auto-detected IN operator
Rules::create('content_pages_auto')
    ->when()
        ->is_singular(['post', 'page'])  // IN auto-detected
    ->then()->custom('enable_comments')
    ->register();
```

**Exclude post types**:
```php
<?php
Rules::create('non_page_singular')
    ->when()
        ->is_singular('page', '!=')  // Not a page
    ->then()->custom('show_blog_sidebar')
    ->register();
```

---

### is_home

Check if the current page is the blog home/posts page.

**Namespace**: `MilliRules\Packages\WordPress\Conditions\IsHome`

**Signature**:
```php
<?php
->is_home($value = true, $operator = '=')
```

**Parameters**:
- `$value` (bool): Expected state (default: `true`)
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: =, !=, IS, IS NOT

**Context Data Used**: `$context['wp']['query']['is_home']`

#### Examples

**Is home page**:
```php
<?php
Rules::create('blog_home')
    ->when()
        ->is_home()  // Is blog home page
    ->then()->custom('show_featured_posts')
    ->register();
```

**Not home page**:
```php
<?php
Rules::create('not_home')
    ->when()
        ->is_home(false)  // Not blog home
    ->then()->custom('show_breadcrumbs')
    ->register();
```

> [!NOTE]
> `is_home()` checks for the blog posts index page, which may be different from the site's front page if a static page is set as the homepage.

---

### is_archive

Check if the current page is an archive page (category, tag, date, author, etc.).

**Namespace**: `MilliRules\Packages\WordPress\Conditions\IsArchive`

**Signature**:
```php
<?php
->is_archive($archive_type = null, $operator = '=')
```

**Parameters**:
- `$archive_type` (string|array|null): Archive type(s) to check (null = any archive)
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: =, !=, IN, NOT IN, EXISTS

**Context Data Used**: `$context['wp']['query']['is_archive']`

#### Examples

**Any archive**:
```php
<?php
Rules::create('any_archive')
    ->when()
        ->is_archive()  // Any archive page
    ->then()->custom('show_archive_sidebar')
    ->register();
```

**Specific archive type**:
```php
<?php
Rules::create('category_archives')
    ->when()
        ->is_archive('category')  // Category archives only
    ->then()->custom('show_category_description')
    ->register();
```

**Multiple archive types**:
```php
<?php
Rules::create('taxonomy_archives')
    ->when()
        ->is_archive(['category', 'tag'], 'IN')
    ->then()->custom('show_taxonomy_cloud')
    ->register();
```

---

### post_type

Check the current post type.

**Namespace**: `MilliRules\Packages\WordPress\Conditions\PostType`

**Signature**:
```php
<?php
->post_type($post_types, $operator = '=')
```

**Parameters**:
- `$post_types` (string|array): Post type(s) to check
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: =, !=, IN, NOT IN, EXISTS

**Context Data Used**: `$context['wp']['post']['post_type']`

#### Examples

**Single post type**:
```php
<?php
Rules::create('product_pages')
    ->when()
        ->post_type('product')
    ->then()->custom('show_product_gallery')
    ->register();
```

**Multiple post types**:
```php
<?php
Rules::create('content_types')
    ->when()
        ->post_type(['post', 'page', 'article'], 'IN')
    ->then()->custom('enable_reading_time')
    ->register();
```

**Exclude post type**:
```php
<?php
Rules::create('non_page_content')
    ->when()
        ->post_type('page', '!=')
    ->then()->custom('show_author_bio')
    ->register();
```

---

### Generic WordPress `is_*` Conditions

MilliRules provides a generic WordPress condition class `IsConditional` that acts as a fallback for all WordPress `is_*` conditional functions when no more specific condition class exists.

#### Basic Usage (Boolean Mode)

When used without arguments, `is_*` conditions behave as simple boolean checks:

```php
<?php
// Fluent builder
Rules::create('rule-1')
    ->when()->is_404()->then()->register();

// Array configuration
[
    'id'         => 'rule-1',
    'conditions' => [
        [ 'type' => 'is_404' ], // is_404() IS TRUE
    ],
    'actions'    => [],
];
```

In this mode:

- The underlying WordPress function is called with **no arguments**.
- The boolean result is compared to the configured `value` (default: `true`) using the configured `operator` (default: `IS`).

Examples:

- `->is_404()` → `is_404() IS true`
- `->is_user_logged_in(false)` → `is_user_logged_in() IS false`

#### Function Call Mode (Arguments)

For conditionals that accept arguments, you can pass them directly to the builder. `IsConditional` will call the underlying `is_*` function with those arguments and compare the result to `true`.

Examples:

```php
<?php
// Single-argument conditional
->is_singular('page')           // is_singular('page') IS TRUE

// Multi-argument conditional
->is_tax('genre', 'sci-fi')     // is_tax('genre', 'sci-fi') IS TRUE
```

In this mode:

- All non-boolean arguments are treated as **function arguments** for the underlying `is_*` function.
- The condition always checks whether the function result is `true` (using `value = true` internally).

#### Optional Operator from the Last Argument

You can optionally pass a comparison operator as the **last argument** when using function call mode. This operator controls how the boolean result of the `is_*` function is compared to `true`.

Supported operators:

- `=`
- `!=`
- `IS`
- `IS NOT`

Example:

```php
<?php
// Calls is_tax('genre', 'sci-fi') and compares result != TRUE
->is_tax('genre', 'sci-fi', '!=');
```

This is useful to express "NOT this conditional" while still using the function-call form.

#### Implementation Notes

- The builder records all raw method arguments in a generic `args` key in the condition config.
- The WordPress `IsConditional` class interprets `args` to determine whether to operate in boolean mode or function-call mode.
- Other packages can reuse the `args` convention in their own condition classes without any changes to core engine or base condition logic.

---

## Combining Conditions

### PHP Conditions Only

```php
<?php
Rules::create('api_authentication', 'php')
    ->when()
        ->request_url('/api/*')
        ->request_method('POST')
        ->request_header('Authorization', 'Bearer *', 'LIKE')
        ->cookie('session_id')
    ->then()->custom('process_api_request')
    ->register();
```

### WordPress Conditions Only

```php
<?php
Rules::create('admin_users_posts', 'wp')
    ->when()
        ->is_user_logged_in()
        ->is_singular('post')
        ->post_type('post')
    ->then()->custom('show_admin_tools')
    ->register();
```

### Mixed PHP and WordPress Conditions

```php
<?php
Rules::create('secure_admin_area', 'wp')
    ->when()
        ->request_url('/wp-admin/*')      // PHP condition
        ->is_user_logged_in()              // WordPress condition
        ->cookie('admin_preference')       // PHP condition
    ->then()->custom('customize_admin')
    ->register();
```

> [!TIP]
> When mixing PHP and WordPress conditions, ensure the WordPress package is available. Auto-detection will set the rule type to `'wp'` when WordPress conditions are used.

## Condition Evaluation Order

Conditions are evaluated in the order they're defined:

```php
<?php
Rules::create('sequential_checks')
    ->when()
        ->request_url('/api/*')        // Checked first
        ->request_method('POST')        // Checked second
        ->cookie('auth_token')          // Checked third
    ->then()->custom('action')
    ->register();
```

For performance, place the **most restrictive or fastest conditions first**:

```php
<?php
// ✅ Good - quick checks first
Rules::create('optimized')
    ->when()
        ->request_method('POST')        // Fast check
        ->request_url('/specific/path') // Fast check
        ->custom('complex_validation')  // Slow custom check last
    ->then()->custom('action')
    ->register();

// ❌ Bad - slow check first
Rules::create('unoptimized')
    ->when()
        ->custom('complex_validation')  // Slow check first!
        ->request_method('POST')
        ->request_url('/specific/path')
    ->then()->custom('action')
    ->register();
```

> [!IMPORTANT]
> With match_all() (default), if any condition fails, subsequent conditions are not evaluated. Place restrictive conditions first to short-circuit evaluation early.

## Custom Conditions

When built-in conditions aren't sufficient, create custom conditions:

```php
<?php
// Register custom condition
Rules::register_condition('is_weekend', function(Context $context) {
    return date('N') >= 6; // Saturday or Sunday
});

// Use in rule
Rules::create('weekend_special')
    ->when()
        ->custom('is_weekend')
        ->request_url('/shop/*')
    ->then()->custom('apply_weekend_discount')
    ->register();
```

See [Creating Custom Conditions](../customization/custom-conditions.md) for detailed information.

## Best Practices

### 1. Use Specific Conditions

```php
<?php
// ✅ Good - specific conditions
->when()
    ->request_url('/api/users')
    ->request_method('GET')

// ❌ Bad - too broad
->when()
    ->request_url('*')
```

### 2. Leverage Auto-Detection

```php
<?php
// ✅ Good - let MilliRules detect operators
->request_url('/admin/*')           // LIKE auto-detected
->request_method(['GET', 'HEAD'])   // IN auto-detected
->constant('WP_DEBUG', true)        // IS auto-detected

// ❌ Unnecessary - explicit when auto-detected works
->request_url('/admin/*', 'LIKE')
->request_method(['GET', 'HEAD'], 'IN')
->constant('WP_DEBUG', true, 'IS')
```

### 3. Group Related Conditions

```php
<?php
// ✅ Good - logical grouping
Rules::create('api_security')
    ->when()
        // API context
        ->request_url('/api/*')
        ->request_method('POST')

        // Authentication
        ->cookie('session_id')
        ->request_header('Authorization')
    ->then()->custom('process_secure_api')
    ->register();
```

### 4. Use Comments for Complex Logic

```php
<?php
Rules::create('complex_caching')
    ->when()
        // Cacheable request types
        ->request_method(['GET', 'HEAD'], 'IN')

        // Not in admin or login areas
        ->request_url('/wp-admin/*', 'NOT LIKE')
        ->request_url('/wp-login.php', '!=')

        // User hasn't disabled caching
        ->cookie('disable_cache', null, 'NOT EXISTS')
    ->then()->custom('apply_cache')
    ->register();
```

## Next Steps

- **[Built-in Actions](actions.md)** - Learn about available actions
- **[Operators](operators.md)** - Master comparison and pattern matching
- **[Custom Conditions](../customization/custom-conditions.md)** - Create your own conditions
- **[Examples](../advanced/examples.md)** - See conditions in real-world scenarios

---

**Need more details?** Check the [API Reference](api.md) for complete method signatures and parameters.
