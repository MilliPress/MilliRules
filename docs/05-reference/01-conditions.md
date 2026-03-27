---
title: 'Built-in Conditions Reference'
post_excerpt: 'Complete reference guide for all built-in MilliRules conditions including PHP request conditions and WordPress-specific conditions.'
menu_order: 10
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
Rules::create('exact_url')
    ->when()
        ->request_url('/wp-admin/edit.php')  // Exact URL match
    ->then()->custom('action')
    ->register();
```

**Wildcard pattern matching**:
```php
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
Rules::create('api_versioned')
    ->when()
        // Matches /api/v1/, /api/v2/, etc.
        ->request_url('/^\\/api\\/v[0-9]+\\//i', 'REGEXP')
    ->then()->custom('route_api_request')
    ->register();
```

**Exclude patterns**:
```php
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
Rules::create('post_requests')
    ->when()
        ->request_method('POST')  // Only POST requests
    ->then()->custom('process_form')
    ->register();
```

**Multiple methods** (OR logic):
```php
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
Rules::create('has_auth_header')
    ->when()
        ->request_header('Authorization')  // Header exists
    ->then()->custom('process_authenticated')
    ->register();
```

**Check header value**:
```php
Rules::create('json_requests')
    ->when()
        ->request_header('Content-Type', 'application/json')
    ->then()->custom('parse_json')
    ->register();
```

**Pattern matching headers**:
```php
Rules::create('bearer_token')
    ->when()
        ->request_header('Authorization', 'Bearer *', 'LIKE')
    ->then()->custom('validate_token')
    ->register();
```

**Multiple accepted values**:
```php
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
Rules::create('has_action_param')
    ->when()
        ->request_param('action')  // Parameter exists
    ->then()->custom('route_action')
    ->register();
```

**Check parameter value**:
```php
Rules::create('delete_action')
    ->when()
        ->request_param('action', 'delete')
    ->then()->custom('confirm_delete')
    ->register();
```

**Numeric comparison**:
```php
Rules::create('pagination')
    ->when()
        ->request_param('page', '1', '>')  // Page > 1
    ->then()->custom('show_pagination')
    ->register();
```

**Multiple accepted values**:
```php
Rules::create('list_actions')
    ->when()
        ->request_param('view', ['list', 'grid', 'table'], 'IN')
    ->then()->custom('render_list_view')
    ->register();
```

**Pattern matching**:
```php
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
Rules::create('has_session')
    ->when()
        ->cookie('session_id')  // Cookie exists
    ->then()->custom('load_session')
    ->register();
```

**Check cookie value**:
```php
Rules::create('theme_preference')
    ->when()
        ->cookie('theme', 'dark')
    ->then()->custom('apply_dark_theme')
    ->register();
```

**Cookie doesn't exist**:
```php
Rules::create('first_time_visitor')
    ->when()
        ->cookie('visited_before', null, 'NOT EXISTS')
    ->then()->custom('show_welcome_message')
    ->register();
```

**Multiple cookie values**:
```php
Rules::create('preferred_languages')
    ->when()
        ->cookie('lang', ['en', 'en-US', 'en-GB'], 'IN')
    ->then()->custom('use_english')
    ->register();
```

**Pattern matching cookies**:
```php
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
Rules::create('has_debug_constant')
    ->when()
        ->constant('WP_DEBUG')  // Constant is defined
    ->then()->custom('enable_debug_mode')
    ->register();
```

**Check boolean constants**:
```php
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
Rules::create('local_environment')
    ->when()
        ->constant('WP_ENVIRONMENT_TYPE', 'local')
    ->then()->custom('enable_local_features')
    ->register();
```

**Multiple environment types**:
```php
Rules::create('non_production')
    ->when()
        ->constant('WP_ENVIRONMENT_TYPE', ['local', 'development'], 'IN')
    ->then()->custom('enable_dev_tools')
    ->register();
```

**Version checking**:
```php
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

### Generic WordPress is_* Conditions

MilliRules supports any WordPress conditional tag function through the `IsConditional` class. Any function starting with `is_` (like `is_singular()`, `is_home()`, `is_archive()`, `is_category()`, etc.) can be used as a condition.

**How It Works**:
- The `IsConditional` class acts as a bridge between MilliRules and WordPress conditional tags
- Supports all WordPress conditional tags: [WordPress Conditional Tags](https://developer.wordpress.org/themes/basics/conditional-tags/)
- Arguments passed to the condition are forwarded to the WordPress function
- Operates in two modes: Boolean Mode (no arguments) or Function Call Mode (with arguments)

#### Basic Usage (Boolean Mode)

When used without arguments, `is_*` conditions behave as simple boolean checks:

```php
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
- The underlying WordPress function is called with **no arguments**
- The boolean result is compared to the configured `value` (default: `true`) using the configured `operator` (default: `IS`)

Examples:
- `->is_404()` → `is_404() IS true`
- `->is_user_logged_in(false)` → `is_user_logged_in() IS false`

**Basic conditionals**:
```php
// Check if any archive page
Rules::create('archive_pages')
    ->when()
        ->is_archive()
    ->then()->custom('show_archive_sidebar')
    ->register();
```

#### Function Call Mode (With Arguments)

For conditionals that accept arguments, you can pass them directly to the builder. `IsConditional` will call the underlying `is_*` function with those arguments and compare the result to `true`.

In this mode:
- All non-boolean arguments are treated as **function arguments** for the underlying `is_*` function
- The condition always checks whether the function result is `true` (using `value = true` internally)

**With arguments**:
```php
// Single-argument conditional
->is_singular('page')           // is_singular('page') IS TRUE

// Multi-argument conditional
->is_tax('genre', 'sci-fi')     // is_tax('genre', 'sci-fi') IS TRUE
```

**Combining conditions**:
```php
// Check if user is logged in and viewing a product archive
Rules::create('archive_list_user_orders')
    ->when()
        ->is_user_logged_in()
        ->is_post_type_archive('product')
    ->then()->custom('show_orders')
    ->register();
```

#### Using Operators with WordPress Conditionals

You can optionally pass a comparison operator as the **last argument** when using function call mode. This operator controls how the boolean result of the `is_*` function is compared to `true`.

Supported operators:
- `=`
- `!=`
- `IS`
- `IS NOT`

```php
// Calls is_tax('genre', 'sci-fi') and compares result != TRUE
// Check for multiple taxonomy terms with IN operator
Rules::create('action_or_drama')
    ->when()
        ->is_tax('genre', 'sci-fi', '!=');
    ->then()->custom('show_newsletter_cta')
    ->register();

// Check for multiple taxonomy terms with IN operator
Rules::create('action_or_drama')
    ->when()
        ->is_tax('genre', ['action', 'drama'], 'IN')
    ->then()->custom('show_intense_content_warning')
    ->register();
```

#### Implementation Notes

- The builder records all raw method arguments in a generic `args` key in the condition config
- The WordPress `IsConditional` class interprets `args` to determine whether to operate in boolean mode or function-call mode
- Other packages can reuse the `args` convention in their own condition classes without any changes to core engine or base condition logic

---

### Generic WordPress has_* Conditions

MilliRules supports any WordPress conditional tag function starting with `has_` through the `HasConditional` class. Functions like `has_post_thumbnail()`, `has_block()`, `has_term()`, `has_excerpt()`, etc. can all be used as conditions.

**How It Works**:
- The `HasConditional` class acts as a bridge between MilliRules and WordPress `has_*` conditional functions
- Arguments passed to the condition are forwarded to the WordPress function
- Operates in two modes: Boolean Mode (no arguments) or Function Call Mode (with arguments)

#### Basic Usage (Boolean Mode)

When used without arguments, `has_*` conditions behave as simple boolean checks:

```php
// Fluent builder
Rules::create('rule-1')
    ->when()->has_post_thumbnail()->then()->register();

// Array configuration
[
    'id'         => 'rule-1',
    'conditions' => [
        [ 'type' => 'has_post_thumbnail' ], // has_post_thumbnail() IS TRUE
    ],
    'actions'    => [],
];
```

In this mode:
- The underlying WordPress function is called with **no arguments**
- The boolean result is compared to the configured `value` (default: `true`) using the configured `operator` (default: `IS`)

Examples:
- `->has_post_thumbnail()` → `has_post_thumbnail() IS true`
- `->has_post_thumbnail(false)` → `has_post_thumbnail() IS false`

**Basic conditionals**:
```php
// Check if the post has an excerpt
Rules::create('has_excerpt')
    ->when()
        ->has_excerpt()
    ->then()->custom('show_custom_excerpt')
    ->register();
```

#### Function Call Mode (With Arguments)

For conditionals that accept arguments, you can pass them directly to the builder. `HasConditional` will call the underlying `has_*` function with those arguments and compare the result to `true`.

In this mode:
- All non-boolean arguments are treated as **function arguments** for the underlying `has_*` function
- The condition always checks whether the function result is `true` (using `value = true` internally)

**With arguments**:
```php
// Single-argument conditional
->has_block('core/paragraph')       // has_block('core/paragraph') IS TRUE

// Multi-argument conditional
->has_term('news', 'category')      // has_term('news', 'category') IS TRUE
```

**Combining conditions**:
```php
// Check if a post has a featured image and a specific block
Rules::create('rich_content')
    ->when()
        ->has_post_thumbnail()
        ->has_block('core/gallery')
    ->then()->custom('show_rich_layout')
    ->register();
```

#### Using Operators with WordPress has_* Conditionals

You can optionally pass a comparison operator as the **last argument** when using function call mode. This operator controls how the boolean result of the `has_*` function is compared to `true`.

Supported operators:
- `=`
- `!=`
- `IS`
- `IS NOT`

```php
// Check that a post does NOT have a specific term
Rules::create('not_in_news')
    ->when()
        ->has_term('news', 'category', '!=')
    ->then()->custom('show_generic_sidebar')
    ->register();

// Check that a post does NOT have a featured image
Rules::create('no_thumbnail')
    ->when()
        ->has_post_thumbnail(false)
    ->then()->custom('show_placeholder_image')
    ->register();
```

#### Implementation Notes

- The builder records all raw method arguments in a generic `args` key in the condition config
- The WordPress `HasConditional` class interprets `args` to determine whether to operate in boolean mode or function-call mode
- Other packages can reuse the `args` convention in their own condition classes without any changes to core engine or base condition logic

---

### post_type

Check the current post type.

**Namespace**: `MilliRules\Packages\WordPress\Conditions\PostType`

**Signature**:
```php
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
Rules::create('product_pages')
    ->when()
        ->post_type('product')
    ->then()->custom('show_product_gallery')
    ->register();
```

**Multiple post types**:
```php
Rules::create('content_types')
    ->when()
        ->post_type(['post', 'page', 'article'], 'IN')
    ->then()->custom('enable_reading_time')
    ->register();
```

**Exclude post type**:
```php
Rules::create('non_page_content')
    ->when()
        ->post_type('page', '!=')
    ->then()->custom('show_author_bio')
    ->register();
```

---

### post_status

Check the current post status.

**Namespace**: `MilliRules\Packages\WordPress\Conditions\PostStatus`

**Signature**:
```php
->post_status($status, $operator = '=')
```

**Parameters**:
- `$status` (string|array): Post status(es) to check (`publish`, `draft`, `pending`, `private`, `future`, `trash`, etc.)
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: =, !=, IN, NOT IN

**How it resolves**: Reads `post_status` from the queried object or global `$post`.

#### Examples

**Single status**:
```php
Rules::create('published_only')
    ->when()
        ->post_status('publish')
    ->then()->custom('show_share_buttons')
    ->register();
```

**Multiple statuses**:
```php
Rules::create('visible_content')
    ->when()
        ->post_status(['publish', 'private'], 'IN')
    ->then()->custom('enable_comments')
    ->register();
```

**Exclude status**:
```php
Rules::create('not_draft')
    ->when()
        ->post_status('draft', '!=')
    ->then()->custom('index_content')
    ->register();
```

> [!NOTE]
> This condition reads the actual `post_status` property — there is no WordPress `is_post_status()` conditional tag, which is why this dedicated condition exists.

---

### post_parent

Check the parent post ID of the current post.

**Namespace**: `MilliRules\Packages\WordPress\Conditions\PostParent`

**Signature**:
```php
->post_parent($parent_id, $operator = '=')
```

**Parameters**:
- `$parent_id` (int|array): Parent post ID(s) to check
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: =, !=, IN, NOT IN, >, <

**How it resolves**: Reads `post_parent` from the queried object or global `$post`. Returns `0` if no post is found.

#### Examples

**Exact parent**:
```php
Rules::create('child_of_about')
    ->when()
        ->post_parent(10)
    ->then()->custom('show_about_subnav')
    ->register();
```

**Has any parent** (hierarchical post):
```php
Rules::create('is_child_page')
    ->when()
        ->post_parent(0, '>')
    ->then()->custom('show_breadcrumbs')
    ->register();
```

**Is top-level page** (no parent):
```php
Rules::create('top_level_page')
    ->when()
        ->post_parent(0)
    ->then()->custom('show_child_pages_menu')
    ->register();
```

> [!TIP]
> Use `->post_parent(0, '>')` as an efficient way to check if a page is a child of any parent, regardless of which parent.

---

### user_role

Check the roles of the current logged-in user.

**Namespace**: `MilliRules\Packages\WordPress\Conditions\UserRole`

**Signature**:
```php
->user_role($role, $operator = 'IN')
```

**Parameters**:
- `$role` (string|array): Role(s) to check (`administrator`, `editor`, `author`, `contributor`, `subscriber`, or custom roles)
- `$operator` (string): Comparison operator (default: `'IN'`)

**Supported Operators**: =, !=, IN, NOT IN

**How it resolves**: Loads user data from context and checks the `roles` array. Uses intersection logic — a user with multiple roles matches if *any* of their roles match any of the expected roles.

#### Examples

**Single role**:
```php
Rules::create('admin_features')
    ->when()
        ->user_role('administrator')
    ->then()->custom('show_admin_toolbar')
    ->register();
```

**Multiple roles**:
```php
Rules::create('editorial_features')
    ->when()
        ->user_role(['editor', 'administrator'], 'IN')
    ->then()->custom('show_editorial_tools')
    ->register();
```

**Exclude role**:
```php
Rules::create('non_subscribers')
    ->when()
        ->user_role('subscriber', '!=')
    ->then()->custom('show_premium_content')
    ->register();
```

> [!NOTE]
> WordPress users can have multiple roles. This condition uses array intersection, so `->user_role('editor')` will match a user who has both `editor` and `administrator` roles.

---

### wp_environment

Check the WordPress environment type.

**Namespace**: `MilliRules\Packages\WordPress\Conditions\WpEnvironment`

**Signature**:
```php
->wp_environment($environment, $operator = '=')
```

**Parameters**:
- `$environment` (string|array): Environment type(s) to check (`production`, `staging`, `development`, `local`)
- `$operator` (string): Comparison operator (default: `'='`)

**Supported Operators**: =, !=, IN, NOT IN

**How it resolves**: Uses `wp_get_environment_type()` (WordPress 5.5+). Falls back to `'production'` if the function is unavailable.

#### Examples

**Production check**:
```php
Rules::create('production_only')
    ->when()
        ->wp_environment('production')
    ->then()->custom('enable_caching')
    ->register();
```

**Non-production environments**:
```php
Rules::create('dev_tools')
    ->when()
        ->wp_environment(['development', 'local'], 'IN')
    ->then()->custom('enable_debug_bar')
    ->register();
```

**Exclude production**:
```php
Rules::create('not_production')
    ->when()
        ->wp_environment('production', '!=')
    ->then()->custom('show_environment_banner')
    ->register();
```

> [!TIP]
> You can also use `->constant('WP_ENVIRONMENT_TYPE', 'local')` from the PHP package for the same effect. The `wp_environment` condition is a convenience wrapper that handles the function availability check.

---

### query_var

Check WordPress query variables.

**Namespace**: `MilliRules\Packages\WordPress\Conditions\QueryVar`

**Signature**:
```php
->query_var($name, $value = null, $operator = '=')
```

**Parameters**:
- `$name` (string): Query variable name (e.g., `paged`, `post_type`, `s`, `m`, `author`, etc.)
- `$value` (mixed): Expected value (null defaults to `EXISTS` operator)
- `$operator` (string): Comparison operator (default: `'='`, or `'EXISTS'` when no value)

**Supported Operators**: =, !=, IN, NOT IN, LIKE, EXISTS, NOT EXISTS

**How it resolves**: First checks the execution context, then falls back to `get_query_var()`. When no value is provided, automatically uses the `EXISTS` operator.

#### Examples

**Check existence**:
```php
Rules::create('is_search')
    ->when()
        ->query_var('s')  // Defaults to EXISTS
    ->then()->custom('enhance_search')
    ->register();
```

**Check value**:
```php
Rules::create('page_two')
    ->when()
        ->query_var('paged', 2)
    ->then()->custom('show_pagination_notice')
    ->register();
```

**Check post type query var**:
```php
Rules::create('product_archive_query')
    ->when()
        ->query_var('post_type', 'product')
    ->then()->custom('show_product_filters')
    ->register();
```

**Check non-existence**:
```php
Rules::create('no_search')
    ->when()
        ->query_var('s', null, 'NOT EXISTS')
    ->then()->custom('show_default_content')
    ->register();
```

> [!NOTE]
> `query_var` is a name-based condition — the first argument is always the query variable name, not a value. This makes it unique among WordPress conditions.

---

## Combining Conditions

### PHP Conditions Only

```php
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

See [Creating Custom Conditions](../03-customization/01-custom-conditions.md) for detailed information.

## Best Practices

### 1. Use Specific Conditions

```php
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

- **[Built-in Actions](./02-actions.md)** - Learn about available actions
- **[Operators](../02-core-concepts/04-operators.md)** - Master comparison and pattern matching
- **[Custom Conditions](../03-customization/01-custom-conditions.md)** - Create your own conditions
- **[Examples](../04-advanced/01-examples.md)** - See conditions in real-world scenarios

---

**Need more details?** Check the [API Reference](./03-api.md) for complete method signatures and parameters.
