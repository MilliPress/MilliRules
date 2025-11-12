---
title: 'WordPress Integration Guide'
post_excerpt: 'Complete guide to integrating MilliRules with WordPress including hooks, queries, plugins, themes, and WordPress-specific best practices.'
---

# WordPress Integration Guide

MilliRules integrates seamlessly with WordPress, providing powerful rule-based logic for plugins, themes, and WordPress applications. This guide covers WordPress-specific features, hooks, patterns, and best practices.

## WordPress Package Overview

The WordPress package extends MilliRules with WordPress-specific functionality:

- **WordPress Conditions** - Post types, user roles, query flags, etc.
- **Hook Integration** - Automatic WordPress hook registration
- **WordPress Context** - Post, user, and query data
- **Template Integration** - Content filtering and modification

##WordPress Initialization

### Basic Initialization

```php
<?php
/**
 * Plugin Name: My MilliRules Plugin
 * Description: Custom rules for WordPress
 */

require_once __DIR__ . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;

add_action('init', function() {
    // Initialize MilliRules (auto-loads WordPress package)
    MilliRules::init();

    // Register your rules here
    register_custom_rules();
}, 1); // Priority 1 for early initialization
```

### Theme Integration

```php
<?php
// functions.php

require_once get_template_directory() . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;

add_action('after_setup_theme', function() {
    MilliRules::init();

    // Theme-specific rules
    require_once get_template_directory() . '/rules/content-rules.php';
    require_once get_template_directory() . '/rules/layout-rules.php';
}, 1);
```

---

## WordPress Hooks

WordPress rules can execute on specific hooks.

### Common Hooks

#### Initialization Hooks

```php
<?php
// plugins_loaded - Very early, plugins just loaded
Rules::create('early_setup')
    ->on('plugins_loaded', 10)
    ->when()->constant('WP_DEBUG', true)
    ->then()->custom('enable_debug_features')
    ->register();

// init - Standard initialization
Rules::create('standard_setup')
    ->on('init', 10)
    ->when()->is_user_logged_in()
    ->then()->custom('setup_user_features')
    ->register();

// wp_loaded - WordPress fully loaded
Rules::create('late_setup')
    ->on('wp_loaded', 10)
    ->when()->constant('DOING_AJAX', true)
    ->then()->custom('setup_ajax_handlers')
    ->register();
```

#### Frontend Hooks

```php
<?php
// wp - Main query has been executed
Rules::create('after_query')
    ->on('wp', 10)
    ->when()->is_singular('post')
    ->then()->custom('track_post_view')
    ->register();

// template_redirect - Before template is loaded
Rules::create('before_template')
    ->on('template_redirect', 10)
    ->when()
        ->request_param('preview', 'true')
        ->is_user_logged_in(false)
    ->then()
        ->custom('redirect_to_login')
    ->register();

// wp_enqueue_scripts - Enqueue frontend assets
Rules::create('conditional_assets')
    ->on('wp_enqueue_scripts', 10)
    ->when()->is_singular(['post', 'page'])
    ->then()->custom('enqueue_reading_mode_scripts')
    ->register();
```

#### Admin Hooks

```php
<?php
// admin_init - Admin initialization
Rules::create('admin_setup')
    ->on('admin_init', 10)
    ->when()->is_user_logged_in()
    ->then()->custom('setup_admin_features')
    ->register();

// admin_menu - Add admin menu items
Rules::create('conditional_menu')
    ->on('admin_menu', 10)
    ->when()
        ->is_user_logged_in()
        ->custom('user_has_permission', ['permission' => 'manage_settings'])
    ->then()
        ->custom('add_settings_menu')
    ->register();

// admin_notices - Display admin notices
Rules::create('warning_notice')
    ->on('admin_notices', 10)
    ->when()
        ->constant('WP_DEBUG', true)
        ->constant('WP_ENVIRONMENT_TYPE', 'production')
    ->then()
        ->custom('show_debug_warning')
    ->register();
```

#### Content Hooks

```php
<?php
// the_content - Filter post content
Rules::create('add_content_disclaimer')
    ->on('the_content', 10)
    ->when()
        ->is_singular('post')
        ->post_type('product')
    ->then()
        ->custom('prepend_disclaimer')
    ->register();

// the_title - Filter post title
Rules::create('modify_title')
    ->on('the_title', 10)
    ->when()
        ->is_singular('post')
        ->custom('is_featured_post')
    ->then()
        ->custom('add_featured_badge_to_title')
    ->register();
```

#### Save Hooks

```php
<?php
// save_post - After post is saved
Rules::create('post_save_notification')
    ->on('save_post', 10)
    ->when()
        ->post_type('post')
        ->custom('post_status_changed_to_published')
    ->then()
        ->custom('send_publication_notification')
    ->register();

// wp_insert_post - When post is created/updated
Rules::create('track_post_creation')
    ->on('wp_insert_post', 10)
    ->when()->post_type(['post', 'page'])
    ->then()->custom('log_post_creation')
    ->register();
```

---

## WordPress Conditions

The WordPress package provides conditions for WordPress-specific scenarios.

### User Conditions

```php
<?php
// Check if user is logged in
Rules::create('authenticated_only')
    ->when()->is_user_logged_in()
    ->then()->custom('show_dashboard')
    ->register();

// Check user roles (custom condition)
Rules::register_condition('user_has_role', function($context, $config) {
    $required_role = $config['role'] ?? '';
    $user_roles = $context['wp']['user']['roles'] ?? [];

    return in_array($required_role, $user_roles);
});

Rules::create('admin_only')
    ->when()
        ->is_user_logged_in()
        ->custom('user_has_role', ['role' => 'administrator'])
    ->then()
        ->custom('show_admin_tools')
    ->register();
```

### Query Conditions

```php
<?php
// Singular posts/pages
Rules::create('single_post_layout')
    ->when()->is_singular('post')
    ->then()->custom('apply_single_post_layout')
    ->register();

// Home page
Rules::create('homepage_features')
    ->when()->is_home()
    ->then()->custom('load_homepage_features')
    ->register();

// Archives
Rules::create('archive_sidebar')
    ->when()->is_archive()
    ->then()->custom('show_archive_sidebar')
    ->register();

// Multiple post types
Rules::create('content_enhancement')
    ->when()->is_singular(['post', 'page', 'article'], 'IN')
    ->then()->custom('enhance_content_display')
    ->register();
```

### Post Conditions

```php
<?php
// Check post type
Rules::create('product_features')
    ->when()->post_type('product')
    ->then()->custom('enable_product_features')
    ->register();

// Custom post status check
Rules::register_condition('post_status', function($context, $config) {
    $expected = $config['value'] ?? '';
    $actual = $context['wp']['post']['post_status'] ?? '';

    return $actual === $expected;
});

Rules::create('draft_warning')
    ->when()
        ->post_type('post')
        ->custom('post_status', ['value' => 'draft'])
    ->then()
        ->custom('show_draft_warning')
    ->register();
```

---

## WordPress Actions

Create WordPress-specific actions for common operations.

### Content Modification

```php
<?php
Rules::register_action('prepend_to_content', function($context, $config) {
    $text = $config['text'] ?? '';
    $priority = $config['priority'] ?? 10;

    add_filter('the_content', function($content) use ($text) {
        return $text . $content;
    }, $priority);
});

Rules::create('add_reading_time')
    ->when()->is_singular('post')
    ->then()
        ->custom('prepend_to_content', [
            'text' => '<div class="reading-time">5 min read</div>',
            'priority' => 10
        ])
    ->register();
```

### Navigation Menu Modification

```php
<?php
Rules::register_action('add_menu_item', function($context, $config) {
    $menu_slug = $config['menu_slug'] ?? '';
    $title = $config['title'] ?? '';
    $capability = $config['capability'] ?? 'read';
    $url = $config['url'] ?? '#';

    add_menu_page($title, $title, $capability, $menu_slug, function() use ($url) {
        wp_redirect($url);
        exit;
    });
});

Rules::create('add_tools_menu')
    ->on('admin_menu', 20)
    ->when()->is_user_logged_in()
    ->then()
        ->custom('add_menu_item', [
            'menu_slug' => 'custom-tools',
            'title' => 'Custom Tools',
            'capability' => 'manage_options',
            'url' => admin_url('admin.php?page=custom-tools')
        ])
    ->register();
```

### Widget Registration

```php
<?php
Rules::register_action('register_sidebar', function($context, $config) {
    $sidebar_config = wp_parse_args($config, [
        'name' => 'Custom Sidebar',
        'id' => 'custom-sidebar',
        'description' => 'A custom sidebar',
        'before_widget' => '<div class="widget">',
        'after_widget' => '</div>',
        'before_title' => '<h3>',
        'after_title' => '</h3>',
    ]);

    register_sidebar($sidebar_config);
});

Rules::create('conditional_sidebar')
    ->on('widgets_init', 10)
    ->when()->constant('ENABLE_CUSTOM_SIDEBAR', true)
    ->then()
        ->custom('register_sidebar', [
            'name' => 'Product Sidebar',
            'id' => 'product-sidebar'
        ])
    ->register();
```

### User Meta Updates

```php
<?php
Rules::register_action('update_user_meta', function($context, $config) {
    $user_id = $context['wp']['user']['id'] ?? 0;
    $meta_key = $config['key'] ?? '';
    $meta_value = $config['value'] ?? '';

    if (!$user_id || !$meta_key) {
        return;
    }

    update_user_meta($user_id, $meta_key, $meta_value);
});

Rules::create('track_login_time')
    ->on('wp_login', 10)
    ->when()->is_user_logged_in()
    ->then()
        ->custom('update_user_meta', [
            'key' => 'last_login',
            'value' => time()
        ])
    ->register();
```

---

## WooCommerce Integration

### WooCommerce Conditions

```php
<?php
Rules::register_condition('cart_total', function($context, $config) {
    if (!function_exists('WC')) {
        return false;
    }

    $minimum = $config['minimum'] ?? 0;
    $cart_total = WC()->cart->get_total('');

    return $cart_total >= $minimum;
});

Rules::register_condition('has_product_in_cart', function($context, $config) {
    if (!function_exists('WC')) {
        return false;
    }

    $product_id = $config['product_id'] ?? 0;

    foreach (WC()->cart->get_cart() as $cart_item) {
        if ($cart_item['product_id'] == $product_id) {
            return true;
        }
    }

    return false;
});
```

### WooCommerce Actions

```php
<?php
Rules::register_action('apply_coupon', function($context, $config) {
    if (!function_exists('WC')) {
        return;
    }

    $coupon_code = $config['coupon'] ?? '';

    if ($coupon_code && !WC()->cart->has_discount($coupon_code)) {
        WC()->cart->apply_coupon($coupon_code);
    }
});

Rules::create('auto_apply_coupon')
    ->when()
        ->custom('cart_total', ['minimum' => 100])
        ->is_user_logged_in()
    ->then()
        ->custom('apply_coupon', ['coupon' => 'LOYALTYDISCOUNT'])
    ->register();
```

---

## Plugin Integration Patterns

### Feature Flags

```php
<?php
// Enable/disable features based on rules
Rules::register_action('enable_feature', function($context, $config) {
    $feature = $config['feature'] ?? '';

    if ($feature) {
        update_option("feature_enabled_{$feature}", true);
    }
});

Rules::create('enable_beta_features')
    ->when()
        ->is_user_logged_in()
        ->custom('user_has_role', ['role' => 'administrator'])
        ->constant('WP_ENVIRONMENT_TYPE', ['local', 'development'], 'IN')
    ->then()
        ->custom('enable_feature', ['feature' => 'beta_dashboard'])
        ->custom('enable_feature', ['feature' => 'advanced_editor'])
    ->register();
```

### Access Control

```php
<?php
Rules::register_action('restrict_access', function($context, $config) {
    $message = $config['message'] ?? 'Access denied';
    $redirect = $config['redirect'] ?? home_url();

    wp_die($message, 'Access Denied', [
        'link_url' => $redirect,
        'link_text' => 'Go back',
    ]);
});

Rules::create('protect_admin_pages')
    ->when()
        ->request_url('/wp-admin/options-*.php')
        ->is_user_logged_in()
        ->custom('user_has_role', ['role' => 'administrator'])
        ->match_none() // NOT administrator
    ->then()
        ->custom('restrict_access', [
            'message' => 'Only administrators can access this page',
            'redirect' => admin_url()
        ])
    ->register();
```

### Conditional Plugin Loading

```php
<?php
// Conditionally load plugin features
add_action('plugins_loaded', function() {
    MilliRules::init();

    Rules::register_action('load_plugin_module', function($context, $config) {
        $module = $config['module'] ?? '';
        $file = plugin_dir_path(__FILE__) . "modules/{$module}.php";

        if (file_exists($file)) {
            require_once $file;
        }
    });

    Rules::create('load_api_module')
        ->when()->request_url('/wp-json/myplugin/*')
        ->then()->custom('load_plugin_module', ['module' => 'api'])
        ->register();

    Rules::create('load_admin_module')
        ->when()->constant('WP_ADMIN', true)
        ->then()->custom('load_plugin_module', ['module' => 'admin'])
        ->register();
}, 5);
```

---

## Best Practices

### 1. Hook Timing

```php
<?php
// ✅ Good - initialize early
add_action('init', function() {
    MilliRules::init();
    register_rules();
}, 1); // Early priority

// ❌ Bad - too late, hooks may have fired
add_action('wp_footer', function() {
    MilliRules::init(); // Too late!
    register_rules();
});
```

### 2. WordPress Function Availability

```php
<?php
// ✅ Good - checks function availability
Rules::register_condition('wp_safe_condition', function($context, $config) {
    if (!function_exists('get_current_user_id')) {
        return false;
    }

    $user_id = get_current_user_id();
    return $user_id > 0;
});

// ❌ Bad - assumes WordPress is loaded
Rules::register_condition('unsafe_condition', function($context, $config) {
    $user_id = get_current_user_id(); // May not exist!
    return $user_id > 0;
});
```

### 3. Multisite Compatibility

```php
<?php
Rules::register_condition('is_main_site', function($context, $config) {
    if (!is_multisite()) {
        return true; // Not multisite, always main site
    }

    return is_main_site();
});

Rules::create('main_site_only_feature')
    ->when()->custom('is_main_site')
    ->then()->custom('enable_network_feature')
    ->register();
```

### 4. Translation Ready

```php
<?php
Rules::register_action('show_message', function($context, $config) {
    $message = $config['message'] ?? '';

    // Make translatable
    $translated = __($message, 'my-text-domain');

    echo '<div class="notice">' . esc_html($translated) . '</div>';
});
```

---

## Troubleshooting

### Rules Not Executing in WordPress

**Check initialization timing**:
```php
<?php
// Verify MilliRules is initialized
add_action('init', function() {
    if (!class_exists('MilliRules\MilliRules')) {
        error_log('MilliRules not loaded!');
        return;
    }

    MilliRules::init();
    error_log('MilliRules initialized');
}, 1);
```

**Verify package loading**:
```php
<?php
$packages = MilliRules::get_loaded_packages();
error_log('Loaded packages: ' . implode(', ', $packages));

if (!in_array('WP', $packages)) {
    error_log('WordPress package not loaded!');
}
```

### Hook Conflicts

```php
<?php
// Check if hook has fired
add_action('init', function() {
    error_log('Init hook fired');
    MilliRules::init();

    Rules::create('test_rule')
        ->on('template_redirect', 10)
        ->when()->request_url('*')
        ->then()->custom('log', ['value' => 'Template redirect fired'])
        ->register();
}, 1);

add_action('template_redirect', function() {
    error_log('template_redirect fired directly');
}, 1);
```

---

## Next Steps

- **[API Reference](../reference/api.md)** - Complete method documentation
- **[Real-World Examples](examples.md)** - WordPress integration examples
- **[Advanced Usage](usage.md)** - Advanced WordPress patterns

---

**Ready for complete examples?** Continue to [Real-World Examples](examples.md) to see full WordPress implementations and use cases.
