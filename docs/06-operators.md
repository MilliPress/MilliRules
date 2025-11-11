---
post_title: 'Operators and Pattern Matching'
post_excerpt: 'Complete guide to MilliRules operators including equality, comparison, pattern matching with wildcards and regex, and boolean logic.'
taxonomy:
  category:
    - documentation
    - reference
  post_tag:
    - operators
    - pattern-matching
    - like
    - regexp
    - comparison
    - wildcards
menu_order: 6
---

# Operators and Pattern Matching

Operators are the backbone of condition evaluation in MilliRules. They determine how actual values are compared against expected values. This comprehensive guide covers all 13 operators with examples and best practices.

## Operator Overview

MilliRules supports 13 operators organized into five categories:

| Category | Operators | Description |
|----------|-----------|-------------|
| **Equality** | `=`, `!=` | Exact matching and inequality |
| **Comparison** | `>`, `>=`, `<`, `<=` | Numeric comparisons |
| **Pattern** | `LIKE`, `NOT LIKE`, `REGEXP` | Wildcard and regex matching |
| **Membership** | `IN`, `NOT IN` | Array membership testing |
| **Existence** | `EXISTS`, `NOT EXISTS` | Value existence checking |
| **Boolean** | `IS`, `IS NOT` | Boolean value comparison |

> [!NOTE]
> All operators are **case-insensitive**. `'LIKE'`, `'like'`, and `'Like'` are treated identically. MilliRules normalizes operators to uppercase internally.

## Auto-Detection

MilliRules intelligently detects the appropriate operator based on the value type:

```php
<?php
// String value → '=' operator
->request_method('GET')

// Array value → 'IN' operator
->request_method(['GET', 'HEAD'])

// Boolean value → 'IS' operator
->constant('WP_DEBUG', true)

// Null value → 'EXISTS' operator
->cookie('session_id')

// String with wildcards (* or ?) → 'LIKE' operator
->request_url('/admin/*')

// String starting with '/' → 'REGEXP' operator
->request_url('/^\\/api\\//i')
```

> [!TIP]
> Auto-detection makes your code cleaner and more readable. Only specify operators explicitly when you need precise control.

---

## Equality Operators

### = (Equals)

**Exact match comparison**. This is the default operator.

#### Syntax
```php
<?php
->condition($value)           // Auto-detected
->condition($value, '=')      // Explicit
```

#### Examples

**String comparison**:
```php
<?php
Rules::create('exact_match')
    ->when()
        ->request_method('POST')              // Exact match
        ->request_url('/api/users')           // Exact URL
        ->cookie('theme', 'dark')             // Exact value
    ->then()->custom('action')
    ->register();
```

**Numeric comparison**:
```php
<?php
Rules::create('exact_number')
    ->when()
        ->request_param('page', '1', '=')     // Page equals 1
        ->constant('PHP_VERSION', '8.0', '=') // Version equals 8.0
    ->then()->custom('action')
    ->register();
```

**Boolean comparison** (prefer `IS` operator):
```php
<?php
// Works but not recommended
->constant('WP_DEBUG', true, '=')

// Better - use IS operator
->constant('WP_DEBUG', true, 'IS')
```

> [!IMPORTANT]
> String comparisons are **case-sensitive**: `'POST'` does not equal `'post'`. Use exact casing or normalize values before comparison.

---

### != (Not Equals)

**Inequality comparison**. Matches when values are not equal.

#### Syntax
```php
<?php
->condition($value, '!=')
```

#### Examples

**Exclude values**:
```php
<?php
Rules::create('not_post_method')
    ->when()
        ->request_method('POST', '!=')        // Not POST
        ->request_url('/wp-login.php', '!=')  // Not login page
    ->then()->custom('action')
    ->register();
```

**Exclude status**:
```php
<?php
Rules::create('not_debug_mode')
    ->when()
        ->constant('WP_DEBUG', true, '!=')    // Debug not enabled
    ->then()->custom('production_action')
    ->register();
```

---

## Comparison Operators

Comparison operators perform **numeric comparisons**. Non-numeric strings are cast to numbers (often 0).

### > (Greater Than)

Matches when actual value is greater than expected value.

#### Syntax
```php
<?php
->condition($value, '>')
```

#### Examples

```php
<?php
Rules::create('pagination')
    ->when()
        ->request_param('page', '1', '>')     // Page > 1
        ->request_param('limit', '10', '>')   // Limit > 10
    ->then()->custom('paginated_action')
    ->register();
```

---

### >= (Greater Than or Equal)

Matches when actual value is greater than or equal to expected value.

#### Syntax
```php
<?php
->condition($value, '>=')
```

#### Examples

```php
<?php
Rules::create('php_version_check')
    ->when()
        ->constant('PHP_VERSION', '7.4', '>=')  // PHP 7.4+
    ->then()->custom('use_modern_features')
    ->register();
```

---

### < (Less Than)

Matches when actual value is less than expected value.

#### Syntax
```php
<?php
->condition($value, '<')
```

#### Examples

```php
<?php
Rules::create('early_pagination')
    ->when()
        ->request_param('page', '5', '<')     // First 4 pages
    ->then()->custom('show_getting_started')
    ->register();
```

---

### <= (Less Than or Equal)

Matches when actual value is less than or equal to expected value.

#### Syntax
```php
<?php
->condition($value, '<=')
```

#### Examples

```php
<?php
Rules::create('legacy_php')
    ->when()
        ->constant('PHP_VERSION', '7.3', '<=')  // PHP 7.3 or older
    ->then()->custom('use_legacy_code')
    ->register();
```

> [!WARNING]
> Comparison operators cast values to numbers. String `'abc'` becomes `0` which may cause unexpected results. Ensure you're comparing numeric values.

---

## Pattern Matching Operators

Pattern matching operators allow flexible string matching using wildcards or regular expressions.

### LIKE (Wildcard Pattern)

**SQL-style wildcard matching** using `*` (matches any characters) and `?` (matches single character).

#### Syntax
```php
<?php
->condition($pattern, 'LIKE')   // Explicit
->condition($pattern)            // Auto-detected if pattern contains * or ?
```

#### Wildcards

| Wildcard | Description | Example | Matches | Doesn't Match |
|----------|-------------|---------|---------|---------------|
| `*` | Any characters (0 or more) | `/admin/*` | `/admin/`, `/admin/posts`, `/admin/a/b/c` | `/administrator/` |
| `?` | Single character | `/page-?` | `/page-1`, `/page-a` | `/page-10`, `/page-` |

#### Examples

**Prefix matching**:
```php
<?php
Rules::create('admin_urls')
    ->when()
        ->request_url('/wp-admin/*')  // Matches /wp-admin/anything
    ->then()->custom('admin_action')
    ->register();
```

**Suffix matching**:
```php
<?php
Rules::create('api_endpoints')
    ->when()
        ->request_url('*/api')        // Matches anything/api
    ->then()->custom('api_action')
    ->register();
```

**Contains matching**:
```php
<?php
Rules::create('search_pages')
    ->when()
        ->request_url('*search*')     // Contains "search" anywhere
    ->then()->custom('search_action')
    ->register();
```

**Single character wildcard**:
```php
<?php
Rules::create('version_urls')
    ->when()
        ->request_url('/v?/*')        // Matches /v1/, /v2/, /va/, etc.
    ->then()->custom('version_action')
    ->register();
```

**Header patterns**:
```php
<?php
Rules::create('bearer_tokens')
    ->when()
        ->request_header('Authorization', 'Bearer *', 'LIKE')
    ->then()->custom('validate_token')
    ->register();
```

**Complex patterns**:
```php
<?php
Rules::create('complex_pattern')
    ->when()
        ->request_url('/api/v?/users/*')  // /api/v1/users/123, /api/v2/users/abc
    ->then()->custom('api_action')
    ->register();
```

> [!TIP]
> LIKE patterns are case-sensitive. Use REGEXP with the `i` flag for case-insensitive matching.

---

### NOT LIKE (Inverse Wildcard)

**Inverse of LIKE**. Matches when pattern does NOT match.

#### Syntax
```php
<?php
->condition($pattern, 'NOT LIKE')
```

#### Examples

**Exclude admin areas**:
```php
<?php
Rules::create('non_admin')
    ->when()
        ->request_url('/wp-admin/*', 'NOT LIKE')
        ->request_url('/wp-login.php', '!=')
    ->then()->custom('public_action')
    ->register();
```

**Exclude API endpoints**:
```php
<?php
Rules::create('non_api')
    ->when()
        ->request_url('/api/*', 'NOT LIKE')
    ->then()->custom('web_action')
    ->register();
```

---

### REGEXP (Regular Expression)

**Full regular expression matching** using PHP's `preg_match()`.

#### Syntax
```php
<?php
->condition($regex_pattern, 'REGEXP')  // Explicit
->condition('/pattern/')                // Auto-detected (starts with /)
```

#### Pattern Format

Regex patterns must be valid PHP regex with delimiters:

```php
<?php
'/pattern/'           // Basic pattern
'/pattern/i'          // Case-insensitive
'/pattern/u'          // UTF-8
'/^\\/api\\//'        // Must escape forward slashes
```

> [!IMPORTANT]
> Always use delimiters (`/pattern/`) and escape forward slashes in the pattern itself (`\\/`).

#### Examples

**API version matching**:
```php
<?php
Rules::create('api_versions')
    ->when()
        // Matches /api/v1/, /api/v2/, /api/v123/
        ->request_url('/^\\/api\\/v[0-9]+\\//i', 'REGEXP')
    ->then()->custom('api_action')
    ->register();
```

**Email validation**:
```php
<?php
Rules::create('email_param')
    ->when()
        ->request_param('email', '/^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$/i', 'REGEXP')
    ->then()->custom('process_email')
    ->register();
```

**Complex URL patterns**:
```php
<?php
Rules::create('product_urls')
    ->when()
        // Matches /product/abc-123, /product/xyz-456
        ->request_url('/^\\/product\\/[a-z]+-[0-9]+$/i', 'REGEXP')
    ->then()->custom('show_product')
    ->register();
```

**Date patterns**:
```php
<?php
Rules::create('date_urls')
    ->when()
        // Matches /2024/01/15, /2023/12/31
        ->request_url('/^\\/[0-9]{4}\\/[0-9]{2}\\/[0-9]{2}$/', 'REGEXP')
    ->then()->custom('date_archive')
    ->register();
```

**Case-insensitive matching**:
```php
<?php
Rules::create('case_insensitive')
    ->when()
        ->request_url('/\\/admin/i', 'REGEXP')  // Matches /admin, /ADMIN, /Admin
    ->then()->custom('action')
    ->register();
```

#### Common Regex Patterns

| Pattern | Regex | Example |
|---------|-------|---------|
| Numeric ID | `/^\\/post\\/[0-9]+$/` | `/post/123` |
| Alphanumeric slug | `/^\\/page\\/[a-z0-9-]+$/i` | `/page/my-slug` |
| UUID | `/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i` | `550e8400-e29b-41d4-a716-446655440000` |
| API versioning | `/^\\/api\\/v[0-9]+\\//` | `/api/v2/` |
| Date (YYYY-MM-DD) | `/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/` | `2024-01-15` |
| Email | `/^[a-z0-9._%+-]+@[a-z0-9.-]+\\.[a-z]{2,}$/i` | `user@example.com` |

> [!WARNING]
> Complex regex can impact performance. Use LIKE patterns when possible for better performance.

---

## Membership Operators

Membership operators check if a value exists in an array of possibilities.

### IN (In Array)

Matches when actual value is **in** the array of expected values.

#### Syntax
```php
<?php
->condition([$val1, $val2, ...], 'IN')  // Explicit
->condition([$val1, $val2, ...])         // Auto-detected
```

#### Examples

**Multiple HTTP methods**:
```php
<?php
Rules::create('safe_methods')
    ->when()
        ->request_method(['GET', 'HEAD', 'OPTIONS'], 'IN')
    ->then()->custom('cacheable_action')
    ->register();

// Auto-detected IN operator
Rules::create('safe_methods_auto')
    ->when()
        ->request_method(['GET', 'HEAD', 'OPTIONS'])
    ->then()->custom('cacheable_action')
    ->register();
```

**Multiple URLs**:
```php
<?php
Rules::create('protected_pages')
    ->when()
        ->request_url([
            '/dashboard',
            '/profile',
            '/settings'
        ], 'IN')
    ->then()->custom('require_auth')
    ->register();
```

**Environment types**:
```php
<?php
Rules::create('non_production')
    ->when()
        ->constant('WP_ENVIRONMENT_TYPE', ['local', 'development', 'staging'], 'IN')
    ->then()->custom('enable_debug')
    ->register();
```

**Post types**:
```php
<?php
Rules::create('content_types')
    ->when()
        ->post_type(['post', 'page', 'article'], 'IN')
    ->then()->custom('show_reading_time')
    ->register();
```

---

### NOT IN (Not In Array)

Matches when actual value is **not in** the array of expected values.

#### Syntax
```php
<?php
->condition([$val1, $val2, ...], 'NOT IN')
```

#### Examples

**Exclude methods**:
```php
<?php
Rules::create('non_modifying')
    ->when()
        ->request_method(['POST', 'PUT', 'DELETE', 'PATCH'], 'NOT IN')
    ->then()->custom('read_only_action')
    ->register();
```

**Exclude URLs**:
```php
<?php
Rules::create('non_admin_urls')
    ->when()
        ->request_url([
            '/wp-admin/*',
            '/wp-login.php'
        ], 'NOT IN')
    ->then()->custom('public_action')
    ->register();
```

---

## Existence Operators

Existence operators check whether a value exists, regardless of its actual value.

### EXISTS

Matches when a value **exists and is not empty**.

#### Syntax
```php
<?php
->condition(null, 'EXISTS')   // Explicit
->condition()                 // Auto-detected (no value parameter)
```

#### Empty Values

These values are considered "non-existent":
- `null`
- `''` (empty string)
- `[]` (empty array)
- `'0'` is **NOT** empty (it exists)

#### Examples

**Cookie existence**:
```php
<?php
Rules::create('has_session')
    ->when()
        ->cookie('session_id', null, 'EXISTS')  // Cookie exists
    ->then()->custom('load_session')
    ->register();

// Shorthand
Rules::create('has_session_short')
    ->when()
        ->cookie('session_id')  // EXISTS auto-detected
    ->then()->custom('load_session')
    ->register();
```

**Parameter existence**:
```php
<?php
Rules::create('has_action_param')
    ->when()
        ->request_param('action')  // Parameter exists with any value
    ->then()->custom('route_action')
    ->register();
```

**Header existence**:
```php
<?php
Rules::create('has_auth_header')
    ->when()
        ->request_header('Authorization')  // Header exists
    ->then()->custom('validate_auth')
    ->register();
```

---

### NOT EXISTS

Matches when a value **does not exist or is empty**.

#### Syntax
```php
<?php
->condition(null, 'NOT EXISTS')
```

#### Examples

**New visitor detection**:
```php
<?php
Rules::create('first_visit')
    ->when()
        ->cookie('visited_before', null, 'NOT EXISTS')
    ->then()->custom('show_welcome')
    ->register();
```

**Missing parameters**:
```php
<?php
Rules::create('no_page_param')
    ->when()
        ->request_param('page', null, 'NOT EXISTS')
    ->then()->custom('show_first_page')
    ->register();
```

**Opt-out detection**:
```php
<?php
Rules::create('analytics_allowed')
    ->when()
        ->cookie('analytics_opt_out', null, 'NOT EXISTS')
    ->then()->custom('track_analytics')
    ->register();
```

---

## Boolean Operators

Boolean operators provide strict boolean value comparison.

### IS (Is True/False)

Matches when value **strictly equals** true or false.

#### Syntax
```php
<?php
->condition(true, 'IS')    // Explicit
->condition(true)           // Auto-detected
```

#### Examples

**Debug mode**:
```php
<?php
Rules::create('debug_enabled')
    ->when()
        ->constant('WP_DEBUG', true, 'IS')
    ->then()->custom('show_debug_bar')
    ->register();

// Auto-detected
Rules::create('debug_enabled_auto')
    ->when()
        ->constant('WP_DEBUG', true)  // IS auto-detected
    ->then()->custom('show_debug_bar')
    ->register();
```

**Feature flags**:
```php
<?php
Rules::create('feature_enabled')
    ->when()
        ->constant('FEATURE_ENABLED', true)
    ->then()->custom('use_new_feature')
    ->register();
```

**Boolean states**:
```php
<?php
Rules::create('user_logged_in')
    ->when()
        ->is_user_logged_in()  // Returns boolean, uses IS
    ->then()->custom('show_dashboard')
    ->register();
```

---

### IS NOT (Is Not True/False)

Matches when value **does not strictly equal** true or false.

#### Syntax
```php
<?php
->condition(true, 'IS NOT')
->condition(false, 'IS NOT')
```

#### Examples

**Debug disabled**:
```php
<?php
Rules::create('production_mode')
    ->when()
        ->constant('WP_DEBUG', true, 'IS NOT')  // Not true (false or undefined)
    ->then()->custom('production_features')
    ->register();
```

**Feature disabled**:
```php
<?php
Rules::create('legacy_mode')
    ->when()
        ->constant('NEW_FEATURE', true, 'IS NOT')
    ->then()->custom('use_legacy_code')
    ->register();
```

> [!NOTE]
> `IS` and `IS NOT` perform **strict boolean comparison**. Use `=` and `!=` for truthy/falsy comparisons.

---

## Operator Precedence

When using multiple conditions with different operators, all conditions are evaluated independently. There's no operator precedence since conditions are combined using match types (all/any/none).

```php
<?php
Rules::create('multiple_operators')
    ->when()  // match_all by default
        ->request_url('/api/*', 'LIKE')           // Pattern
        ->request_method(['GET', 'HEAD'], 'IN')   // Membership
        ->cookie('session_id', null, 'EXISTS')    // Existence
    ->then()->custom('action')
    ->register();

// Evaluates as: (URL LIKE /api/*) AND (method IN [GET, HEAD]) AND (cookie EXISTS)
```

---

## Operator Auto-Detection Rules

MilliRules uses these rules for operator auto-detection:

| Value Type | Pattern | Auto-Detected Operator | Example |
|------------|---------|----------------------|---------|
| Array | Any array | `IN` | `['GET', 'POST']` → `IN` |
| Boolean | `true` or `false` | `IS` | `true` → `IS` |
| Null | `null` | `EXISTS` | `null` → `EXISTS` |
| String with wildcard | Contains `*` or `?` | `LIKE` | `'/admin/*'` → `LIKE` |
| String (regex) | Starts with `/` | `REGEXP` | `'/^abc/'` → `REGEXP` |
| Other | Any other value | `=` | `'POST'` → `=` |

```php
<?php
// Auto-detection examples
->request_method('GET')                    // = (string, no wildcard)
->request_method(['GET', 'HEAD'])          // IN (array)
->constant('WP_DEBUG', true)               // IS (boolean)
->cookie('session_id')                     // EXISTS (no value parameter)
->request_url('/admin/*')                  // LIKE (has wildcard)
->request_url('/^\\/api\\//i')             // REGEXP (starts with /)
```

---

## Best Practices

### 1. Let Auto-Detection Work

```php
<?php
// ✅ Good - clean and readable
->request_url('/api/*')
->request_method(['GET', 'HEAD'])
->constant('WP_DEBUG', true)

// ❌ Unnecessary - auto-detection works fine
->request_url('/api/*', 'LIKE')
->request_method(['GET', 'HEAD'], 'IN')
->constant('WP_DEBUG', true, 'IS')
```

### 2. Use LIKE for Simple Patterns

```php
<?php
// ✅ Good - simple and fast
->request_url('/api/*')
->request_url('/product-*')

// ❌ Overkill - regex is slower
->request_url('/^\\/api\\//i', 'REGEXP')
->request_url('/^\\/product-/i', 'REGEXP')
```

### 3. Validate Regex Patterns

```php
<?php
// ✅ Good - valid regex with delimiters
->request_url('/^\\/api\\/v[0-9]+\\//i', 'REGEXP')

// ❌ Wrong - missing delimiters
->request_url('^/api/v[0-9]+/', 'REGEXP')

// ❌ Wrong - forward slashes not escaped
->request_url('/^/api/v[0-9]+/', 'REGEXP')
```

### 4. Use Appropriate Operators

```php
<?php
// ✅ Good - right operator for the job
->request_method(['GET', 'HEAD'], 'IN')      // Multiple values
->request_url('/admin/*', 'LIKE')            // Pattern match
->constant('WP_DEBUG', true, 'IS')           // Boolean
->cookie('session_id', null, 'EXISTS')       // Existence

// ❌ Wrong - inefficient or incorrect
->request_method('GET', '=')                 // Use IN for multiple
->request_method('POST', '=')
->request_url('/admin/edit.php', 'LIKE')     // Use = for exact match
```

### 5. Consider Performance

**Operator performance** (fastest to slowest):
1. `=`, `!=` (equality)
2. `IS`, `IS NOT` (boolean)
3. `EXISTS`, `NOT EXISTS` (existence)
4. `>`, `>=`, `<`, `<=` (numeric)
5. `IN`, `NOT IN` (membership)
6. `LIKE`, `NOT LIKE` (wildcard)
7. `REGEXP` (regex - slowest)

```php
<?php
// ✅ Good - fast checks first
->when()
    ->request_method('POST')              // Fast equality
    ->request_url('/api/users')           // Fast equality
    ->request_header('Authorization')     // Fast existence
    ->custom('complex_validation')        // Slow custom check last

// ❌ Bad - slow check first
->when()
    ->request_url('/complex-.*-pattern/i', 'REGEXP')  // Slow regex first!
    ->request_method('POST')
```

---

## Common Pitfalls

### 1. Case Sensitivity

```php
<?php
// ❌ Wrong - case mismatch
->request_method('post')  // Won't match 'POST'

// ✅ Correct - exact case
->request_method('POST')

// ✅ Alternative - case-insensitive regex
->request_method('/^post$/i', 'REGEXP')
```

### 2. Wildcard Escaping

```php
<?php
// ❌ Wrong - literal asterisk not treated as wildcard
->request_url('\\*')

// ✅ Correct - asterisk is wildcard
->request_url('*')

// ✅ If you need literal asterisk, use regex
->request_url('/\\*/','REGEXP')
```

### 3. Regex Delimiters

```php
<?php
// ❌ Wrong - no delimiters
->request_url('^/api/', 'REGEXP')

// ✅ Correct - with delimiters
->request_url('/^\\/api\\//i', 'REGEXP')
```

### 4. Empty Arrays

```php
<?php
// ❌ Wrong - empty array always fails
->request_method([], 'IN')

// ✅ Correct - non-empty array
->request_method(['GET', 'POST'], 'IN')
```

---

## Next Steps

- **[Dynamic Placeholders](07-placeholders.md)** - Use dynamic values in conditions
- **[Built-in Conditions](04-built-in-conditions.md)** - See operators in action
- **[Custom Conditions](09-custom-conditions.md)** - Implement custom operators
- **[Real-World Examples](15-examples.md)** - Complete working examples

---

**Ready for advanced features?** Continue to [Dynamic Placeholders](07-placeholders.md) to learn about using dynamic values in your rules.
