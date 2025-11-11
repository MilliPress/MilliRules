---
post_title: 'Real-World Examples'
post_excerpt: 'Complete, working examples of MilliRules implementations including caching, access control, redirects, content manipulation, and more.'
---

# Real-World Examples

This guide provides complete, working examples of MilliRules implementations for common use cases. Each example includes full code with explanations.

## Table of Contents

- [Page Caching System](#page-caching-system)
- [Access Control and Redirects](#access-control-and-redirects)
- [Content Modification](#content-modification)
- [User Tracking and Analytics](#user-tracking-and-analytics)
- [API Rate Limiting](#api-rate-limiting)
- [Feature Flags](#feature-flags)
- [WooCommerce Integration](#woocommerce-integration)
- [Membership System](#membership-system)

---

## Page Caching System

A complete page caching implementation using early execution.

```php
<?php
/**
 * Plugin Name: MilliRules Page Cache
 * Description: Intelligent page caching with MilliRules
 */

require_once __DIR__ . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;

// Initialize early (in mu-plugins or early hook)
add_action('plugins_loaded', function() {
    MilliRules::init();

    // Register cache check action
    Rules::register_action('check_page_cache', function($context, $config) {
        $uri = $context['request']['uri'] ?? '';
        $cache_key = 'page_cache_' . md5($uri);

        $cached = get_transient($cache_key);

        if ($cached !== false) {
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Cache: HIT');
            header('X-Cache-Key: ' . $cache_key);
            echo $cached;
            exit;
        }
    });

    // Register cache save action
    Rules::register_action('save_page_cache', function($context, $config) {
        $uri = $context['request']['uri'] ?? '';
        $cache_key = 'page_cache_' . md5($uri);
        $duration = $config['duration'] ?? 3600;

        ob_start(function($buffer) use ($cache_key, $duration) {
            // Save to cache
            set_transient($cache_key, $buffer, $duration);

            // Add cache header
            header('X-Cache: MISS');
            header('X-Cache-Key: ' . $cache_key);

            return $buffer;
        });
    });

    // Rule 1: Check cache for cacheable requests
    Rules::create('check_cache', 'php')
        ->order(5)
        ->when()
            ->request_method(['GET', 'HEAD'], 'IN')
            ->request_url('/wp-admin/*', 'NOT LIKE')
            ->request_url('/wp-login.php', '!=')
            ->cookie('wordpress_logged_in_*', null, 'NOT EXISTS')
        ->then()
            ->custom('check_page_cache')
        ->register();

    // Rule 2: Save to cache after response
    Rules::create('save_cache', 'php')
        ->order(10)
        ->when()
            ->request_method(['GET', 'HEAD'], 'IN')
            ->request_url('/wp-admin/*', 'NOT LIKE')
            ->request_url('/wp-login.php', '!=')
        ->then()
            ->custom('save_page_cache', ['duration' => 3600])
        ->register();

    // Execute early rules
    MilliRules::execute_rules(['PHP']);
}, 1);

// Clear cache on post update
add_action('save_post', function($post_id) {
    // Clear all page cache
    global $wpdb;
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_page_cache_%'");
});
```

---

## Access Control and Redirects

Protect pages and redirect unauthorized users.

```php
<?php
/**
 * Plugin Name: MilliRules Access Control
 * Description: Rule-based access control
 */

require_once __DIR__ . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;

add_action('init', function() {
    MilliRules::init();

    // Register custom conditions
    Rules::register_condition('user_has_role', function($context, $config) {
        $required_role = $config['role'] ?? '';
        $user_roles = $context['wp']['user']['roles'] ?? [];
        return in_array($required_role, $user_roles);
    });

    Rules::register_condition('user_can', function($context, $config) {
        $capability = $config['capability'] ?? '';
        $user_id = $context['wp']['user']['id'] ?? 0;
        return $user_id && user_can($user_id, $capability);
    });

    // Register redirect action
    Rules::register_action('redirect_to', function($context, $config) {
        $url = $config['url'] ?? home_url();
        $status = $config['status'] ?? 302;
        $message = $config['message'] ?? '';

        if ($message) {
            set_transient('redirect_message_' . get_current_user_id(), $message, 30);
        }

        wp_redirect($url, $status);
        exit;
    });

    // Register message display action
    Rules::register_action('show_redirect_message', function($context, $config) {
        $user_id = get_current_user_id();
        $message = get_transient('redirect_message_' . $user_id);

        if ($message) {
            delete_transient('redirect_message_' . $user_id);
            add_action('admin_notices', function() use ($message) {
                echo '<div class="notice notice-warning"><p>' . esc_html($message) . '</p></div>';
            });
        }
    });

    // Rule 1: Protect admin area
    Rules::create('protect_admin', 'wp')
        ->on('admin_init', 5)
        ->when()
            ->is_user_logged_in()
            ->custom('user_can', ['capability' => 'edit_posts'])
            ->match_none()
        ->then()
            ->custom('redirect_to', [
                'url' => home_url(),
                'message' => 'You do not have permission to access the admin area.'
            ])
        ->register();

    // Rule 2: Protect specific pages
    Rules::create('protect_membership_pages', 'wp')
        ->on('template_redirect', 10)
        ->when()
            ->request_url('/members/*', 'LIKE')
            ->is_user_logged_in(false)
        ->then()
            ->custom('redirect_to', [
                'url' => wp_login_url($_SERVER['REQUEST_URI'] ?? ''),
                'message' => 'Please log in to access member content.',
                'status' => 302
            ])
        ->register();

    // Rule 3: Role-based page protection
    Rules::create('protect_premium_content', 'wp')
        ->on('template_redirect', 10)
        ->when()
            ->request_url('/premium/*', 'LIKE')
            ->is_user_logged_in()
            ->custom('user_has_role', ['role' => 'subscriber'])
            ->match_none()
        ->then()
            ->custom('redirect_to', [
                'url' => home_url('/upgrade'),
                'message' => 'Upgrade to premium to access this content.'
            ])
        ->register();

    // Rule 4: Display redirect messages
    Rules::create('display_messages', 'wp')
        ->on('admin_notices', 10)
        ->when()->is_user_logged_in()
        ->then()->custom('show_redirect_message')
        ->register();

}, 1);
```

---

## Content Modification

Dynamically modify WordPress content based on conditions.

```php
<?php
/**
 * Plugin Name: MilliRules Content Modifier
 * Description: Conditional content modification
 */

require_once __DIR__ . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;

add_action('init', function() {
    MilliRules::init();

    // Register content modification actions
    Rules::register_action('prepend_content', function($context, $config) {
        $text = $config['text'] ?? '';
        $priority = $config['priority'] ?? 10;

        add_filter('the_content', function($content) use ($text) {
            return $text . $content;
        }, $priority);
    });

    Rules::register_action('append_content', function($context, $config) {
        $text = $config['text'] ?? '';
        $priority = $config['priority'] ?? 10;

        add_filter('the_content', function($content) use ($text) {
            return $content . $text;
        }, $priority);
    });

    Rules::register_action('add_reading_time', function($context, $config) {
        add_filter('the_content', function($content) {
            $word_count = str_word_count(strip_tags($content));
            $reading_time = ceil($word_count / 200); // 200 words per minute

            $badge = '<div class="reading-time" style="background: #f0f0f0; padding: 10px; margin-bottom: 20px; border-radius: 5px;">';
            $badge .= '<strong>⏱ Reading time:</strong> ' . $reading_time . ' min';
            $badge .= '</div>';

            return $badge . $content;
        }, 10);
    });

    // Rule 1: Add disclaimer to product posts
    Rules::create('product_disclaimer', 'wp')
        ->on('the_content', 10)
        ->when()
            ->is_singular('post')
            ->post_type('product')
        ->then()
            ->custom('prepend_content', [
                'text' => '<div class="disclaimer" style="background: #fff3cd; padding: 15px; margin-bottom: 20px; border-left: 4px solid #ffc107;">' .
                         '<strong>⚠ Disclaimer:</strong> Product specifications and prices are subject to change without notice.' .
                         '</div>',
                'priority' => 10
            ])
        ->register();

    // Rule 2: Add reading time to blog posts
    Rules::create('add_blog_reading_time', 'wp')
        ->on('the_content', 10)
        ->when()
            ->is_singular('post')
            ->post_type('post')
        ->then()
            ->custom('add_reading_time')
        ->register();

    // Rule 3: Add CTA to pages for non-members
    Rules::create('membership_cta', 'wp')
        ->on('the_content', 10)
        ->when()
            ->is_singular('page')
            ->is_user_logged_in(false)
        ->then()
            ->custom('append_content', [
                'text' => '<div class="membership-cta" style="background: #007cba; color: white; padding: 30px; margin-top: 30px; text-align: center; border-radius: 5px;">' .
                         '<h3 style="color: white; margin-top: 0;">Enjoying this content?</h3>' .
                         '<p>Join our community to access exclusive content and features!</p>' .
                         '<a href="/register" style="background: white; color: #007cba; padding: 12px 30px; text-decoration: none; border-radius: 3px; display: inline-block; font-weight: bold;">Join Now</a>' .
                         '</div>',
                'priority' => 20
            ])
        ->register();

    // Rule 4: Add author bio to posts
    Rules::create('author_bio', 'wp')
        ->on('the_content', 10)
        ->when()
            ->is_singular('post')
            ->post_type('post')
        ->then()
            ->custom('append_content', [
                'text' => '<?php
                    $author_id = get_the_author_meta("ID");
                    $author_name = get_the_author();
                    $author_bio = get_the_author_meta("description");
                    $author_url = get_author_posts_url($author_id);

                    echo "<div class=\"author-bio\" style=\"background: #f9f9f9; padding: 20px; margin-top: 30px; border-radius: 5px;\">";
                    echo "<h4>About " . esc_html($author_name) . "</h4>";
                    echo "<p>" . esc_html($author_bio) . "</p>";
                    echo "<a href=\"" . esc_url($author_url) . "\">View all posts by " . esc_html($author_name) . "</a>";
                    echo "</div>";
                ?>',
                'priority' => 30
            ])
        ->register();

}, 1);
```

---

## User Tracking and Analytics

Track user behavior and log analytics events.

```php
<?php
/**
 * Plugin Name: MilliRules Analytics
 * Description: User tracking and analytics
 */

require_once __DIR__ . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;

add_action('init', function() {
    MilliRules::init();

    // Register tracking actions
    Rules::register_action('track_page_view', function($context, $config) {
        global $wpdb;

        $table = $wpdb->prefix . 'page_views';
        $user_id = $context['wp']['user']['id'] ?? 0;
        $url = $context['request']['uri'] ?? '';
        $ip = $context['request']['ip'] ?? '';
        $user_agent = $context['request']['user_agent'] ?? '';

        $wpdb->insert($table, [
            'user_id' => $user_id,
            'url' => $url,
            'ip' => $ip,
            'user_agent' => $user_agent,
            'viewed_at' => current_time('mysql'),
        ]);
    });

    Rules::register_action('track_event', function($context, $config) {
        global $wpdb;

        $table = $wpdb->prefix . 'analytics_events';
        $event_type = $config['event_type'] ?? 'pageview';
        $event_data = $config['event_data'] ?? [];
        $user_id = $context['wp']['user']['id'] ?? 0;

        $wpdb->insert($table, [
            'user_id' => $user_id,
            'event_type' => $event_type,
            'event_data' => json_encode($event_data),
            'created_at' => current_time('mysql'),
        ]);
    });

    Rules::register_action('update_user_activity', function($context, $config) {
        $user_id = $context['wp']['user']['id'] ?? 0;

        if ($user_id) {
            update_user_meta($user_id, 'last_activity', time());
            update_user_meta($user_id, 'total_visits',
                (int) get_user_meta($user_id, 'total_visits', true) + 1
            );
        }
    });

    // Rule 1: Track all page views
    Rules::create('track_all_pages', 'wp')
        ->on('wp', 10)
        ->when()->request_url('*')
        ->then()->custom('track_page_view')
        ->register();

    // Rule 2: Track user activity
    Rules::create('track_user_activity', 'wp')
        ->on('wp', 10)
        ->when()->is_user_logged_in()
        ->then()->custom('update_user_activity')
        ->register();

    // Rule 3: Track important pages
    Rules::create('track_important_pages', 'wp')
        ->on('wp', 10)
        ->when()
            ->request_url(['/pricing', '/contact', '/checkout'], 'IN')
        ->then()
            ->custom('track_event', [
                'event_type' => 'important_page_view',
                'event_data' => [
                    'page' => '{request:uri}',
                    'referrer' => '{request:referer}'
                ]
            ])
        ->register();

    // Rule 4: Track downloads
    Rules::create('track_downloads', 'wp')
        ->on('wp', 10)
        ->when()
            ->request_url('/downloads/*', 'LIKE')
            ->request_param('file')
        ->then()
            ->custom('track_event', [
                'event_type' => 'file_download',
                'event_data' => [
                    'file' => '{param:file}',
                    'user_id' => '{wp:user:id}'
                ]
            ])
        ->register();

}, 1);

// Create tables on plugin activation
register_activation_hook(__FILE__, function() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();

    $sql1 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}page_views (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL DEFAULT 0,
        url varchar(255) NOT NULL,
        ip varchar(45) NOT NULL,
        user_agent text,
        viewed_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY viewed_at (viewed_at)
    ) $charset_collate;";

    $sql2 = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}analytics_events (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        user_id bigint(20) NOT NULL DEFAULT 0,
        event_type varchar(100) NOT NULL,
        event_data text,
        created_at datetime NOT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY event_type (event_type),
        KEY created_at (created_at)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
});
```

---

## API Rate Limiting

Implement rate limiting for API endpoints.

```php
<?php
/**
 * Plugin Name: MilliRules API Rate Limiter
 * Description: Rate limiting for API endpoints
 */

require_once __DIR__ . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;

add_action('init', function() {
    MilliRules::init();

    // Register rate limit condition
    Rules::register_condition('within_rate_limit', function($context, $config) {
        $ip = $context['request']['ip'] ?? '';
        $limit = $config['limit'] ?? 60; // Requests per minute
        $period = $config['period'] ?? 60; // Seconds

        $cache_key = 'rate_limit_' . md5($ip);
        $current = get_transient($cache_key) ?: 0;

        if ($current >= $limit) {
            return false; // Rate limit exceeded
        }

        // Increment counter
        set_transient($cache_key, $current + 1, $period);

        return true;
    });

    // Register rate limit response action
    Rules::register_action('send_rate_limit_response', function($context, $config) {
        $retry_after = $config['retry_after'] ?? 60;

        status_header(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . $retry_after);

        echo json_encode([
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $retry_after
        ]);

        exit;
    });

    // Rule 1: Rate limit API endpoints
    Rules::create('api_rate_limit', 'php')
        ->order(5)
        ->when()
            ->request_url('/wp-json/*', 'LIKE')
            ->custom('within_rate_limit', ['limit' => 60, 'period' => 60])
            ->match_none() // If NOT within limit
        ->then()
            ->custom('send_rate_limit_response', ['retry_after' => 60])
        ->register();

    // Rule 2: Stricter limits for authentication endpoints
    Rules::create('auth_rate_limit', 'php')
        ->order(3)
        ->when()
            ->request_url('/wp-json/*/auth/*', 'LIKE')
            ->custom('within_rate_limit', ['limit' => 10, 'period' => 60])
            ->match_none()
        ->then()
            ->custom('send_rate_limit_response', ['retry_after' => 300])
        ->register();

    // Execute early
    MilliRules::execute_rules(['PHP']);

}, 1);
```

---

## Feature Flags

Implement dynamic feature flags.

```php
<?php
/**
 * Plugin Name: MilliRules Feature Flags
 * Description: Dynamic feature flag system
 */

require_once __DIR__ . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;

add_action('init', function() {
    MilliRules::init();

    // Register feature flag condition
    Rules::register_condition('feature_enabled', function($context, $config) {
        $feature = $config['feature'] ?? '';
        return get_option("feature_flag_{$feature}", false);
    });

    // Register feature actions
    Rules::register_action('enable_feature', function($context, $config) {
        $feature = $config['feature'] ?? '';

        if ($feature) {
            // Mark feature as enabled
            update_option("feature_enabled_{$feature}", true);

            // Load feature code
            $feature_file = plugin_dir_path(__FILE__) . "features/{$feature}.php";
            if (file_exists($feature_file)) {
                require_once $feature_file;
            }
        }
    });

    // Rule 1: Enable beta features for admins in dev
    Rules::create('enable_beta_for_admins', 'wp')
        ->when()
            ->is_user_logged_in()
            ->custom('user_has_role', ['role' => 'administrator'])
            ->constant('WP_ENVIRONMENT_TYPE', ['local', 'development'], 'IN')
        ->then()
            ->custom('enable_feature', ['feature' => 'beta_dashboard'])
            ->custom('enable_feature', ['feature' => 'advanced_editor'])
        ->register();

    // Rule 2: Enable features based on flags
    Rules::create('load_new_checkout', 'wp')
        ->when()->custom('feature_enabled', ['feature' => 'new_checkout'])
        ->then()->custom('enable_feature', ['feature' => 'new_checkout'])
        ->register();

    // Rule 3: Gradual rollout (10% of users)
    Rules::register_condition('in_rollout_group', function($context, $config) {
        $percentage = $config['percentage'] ?? 10;
        $user_id = $context['wp']['user']['id'] ?? 0;

        // Consistent assignment based on user ID
        return ($user_id % 100) < $percentage;
    });

    Rules::create('gradual_rollout', 'wp')
        ->when()
            ->is_user_logged_in()
            ->custom('in_rollout_group', ['percentage' => 10])
        ->then()
            ->custom('enable_feature', ['feature' => 'experimental_ui'])
        ->register();

}, 1);

// Helper function to check if feature is enabled
function is_feature_enabled($feature) {
    return get_option("feature_enabled_{$feature}", false);
}
```

---

## WooCommerce Integration

Complete WooCommerce conditional logic example.

```php
<?php
/**
 * Plugin Name: MilliRules WooCommerce Integration
 * Description: Advanced WooCommerce rules
 */

require_once __DIR__ . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;

add_action('init', function() {
    if (!class_exists('WooCommerce')) {
        return;
    }

    MilliRules::init();

    // Register WooCommerce conditions
    Rules::register_condition('cart_total', function($context, $config) {
        $minimum = $config['minimum'] ?? 0;
        $operator = $config['operator'] ?? '>=';
        $cart_total = WC()->cart->get_total('edit');

        return BaseCondition::compare_values($cart_total, $minimum, $operator);
    });

    Rules::register_condition('cart_item_count', function($context, $config) {
        $count = $config['count'] ?? 1;
        $operator = $config['operator'] ?? '>=';
        $cart_count = WC()->cart->get_cart_contents_count();

        return BaseCondition::compare_values($cart_count, $count, $operator);
    });

    Rules::register_condition('has_product_category_in_cart', function($context, $config) {
        $category_slug = $config['category'] ?? '';

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            if (has_term($category_slug, 'product_cat', $product_id)) {
                return true;
            }
        }

        return false;
    });

    // Register WooCommerce actions
    Rules::register_action('apply_discount', function($context, $config) {
        $coupon = $config['coupon'] ?? '';

        if ($coupon && !WC()->cart->has_discount($coupon)) {
            WC()->cart->apply_coupon($coupon);
            wc_add_notice('Discount applied automatically!', 'success');
        }
    });

    Rules::register_action('add_cart_notice', function($context, $config) {
        $message = $config['message'] ?? '';
        $type = $config['type'] ?? 'notice';

        if ($message) {
            wc_add_notice($message, $type);
        }
    });

    // Rule 1: Free shipping for orders over $50
    Rules::create('free_shipping_notice', 'wp')
        ->on('woocommerce_before_cart', 10)
        ->when()
            ->custom('cart_total', ['minimum' => 50])
        ->then()
            ->custom('add_cart_notice', [
                'message' => '🎉 You qualify for free shipping!',
                'type' => 'success'
            ])
        ->register();

    // Rule 2: Auto-apply discount for bulk orders
    Rules::create('bulk_order_discount', 'wp')
        ->on('woocommerce_before_calculate_totals', 10)
        ->when()
            ->custom('cart_item_count', ['count' => 10, 'operator' => '>='])
        ->then()
            ->custom('apply_discount', ['coupon' => 'BULK10'])
        ->register();

    // Rule 3: Category-specific promotion
    Rules::create('electronics_promo', 'wp')
        ->on('woocommerce_before_cart', 10)
        ->when()
            ->custom('has_product_category_in_cart', ['category' => 'electronics'])
            ->custom('cart_total', ['minimum' => 100, 'operator' => '>='])
        ->then()
            ->custom('apply_discount', ['coupon' => 'ELECTRONICS15'])
            ->custom('add_cart_notice', [
                'message' => '15% discount applied to your electronics purchase!',
                'type' => 'success'
            ])
        ->register();

    // Rule 4: Minimum order notice
    Rules::create('minimum_order_notice', 'wp')
        ->on('woocommerce_before_cart', 10)
        ->when()
            ->custom('cart_total', ['minimum' => 25, 'operator' => '<'])
        ->then()
            ->custom('add_cart_notice', [
                'message' => 'Add $' . (25 - WC()->cart->get_total('edit')) . ' more to meet our minimum order amount.',
                'type' => 'notice'
            ])
        ->register();

}, 1);
```

---

## Membership System

Complete membership system with tiers and access control.

```php
<?php
/**
 * Plugin Name: MilliRules Membership System
 * Description: Complete membership tier system
 */

require_once __DIR__ . '/vendor/autoload.php';

use MilliRules\MilliRules;
use MilliRules\Rules;

add_action('init', function() {
    MilliRules::init();

    // Register membership conditions
    Rules::register_condition('has_membership_level', function($context, $config) {
        $required_level = $config['level'] ?? 'free';
        $user_id = $context['wp']['user']['id'] ?? 0;

        if (!$user_id) {
            return $required_level === 'free';
        }

        $user_level = get_user_meta($user_id, 'membership_level', true) ?: 'free';

        $levels = ['free' => 0, 'basic' => 1, 'premium' => 2, 'enterprise' => 3];

        return ($levels[$user_level] ?? 0) >= ($levels[$required_level] ?? 0);
    });

    Rules::register_condition('membership_expired', function($context, $config) {
        $user_id = $context['wp']['user']['id'] ?? 0;

        if (!$user_id) {
            return false;
        }

        $expiry = get_user_meta($user_id, 'membership_expiry', true);

        if (!$expiry) {
            return false; // No expiry = lifetime
        }

        return time() > $expiry;
    });

    // Register membership actions
    Rules::register_action('restrict_content', function($context, $config) {
        $message = $config['message'] ?? 'This content requires a membership.';
        $cta_url = $config['cta_url'] ?? home_url('/membership');

        add_filter('the_content', function($content) use ($message, $cta_url) {
            $restricted = '<div class="membership-required" style="background: #f9f9f9; padding: 30px; text-align: center; border: 2px solid #ddd; border-radius: 5px;">';
            $restricted .= '<h3>🔒 Members Only Content</h3>';
            $restricted .= '<p>' . esc_html($message) . '</p>';
            $restricted .= '<a href="' . esc_url($cta_url) . '" class="button" style="background: #007cba; color: white; padding: 12px 30px; text-decoration: none; border-radius: 3px; display: inline-block;">Upgrade Membership</a>';
            $restricted .= '</div>';

            return $restricted;
        });
    });

    Rules::register_action('show_membership_badge', function($context, $config) {
        $user_id = $context['wp']['user']['id'] ?? 0;
        $level = get_user_meta($user_id, 'membership_level', true) ?: 'free';

        $badges = [
            'free' => '⚪',
            'basic' => '🔵',
            'premium' => '⭐',
            'enterprise' => '👑'
        ];

        add_filter('the_author', function($author) use ($level, $badges) {
            return $author . ' ' . ($badges[$level] ?? '');
        });
    });

    // Rule 1: Restrict premium content
    Rules::create('restrict_premium_posts', 'wp')
        ->on('the_content', 10)
        ->when()
            ->post_type('post')
            ->custom('post_meta', ['key' => 'membership_required', 'value' => 'premium'])
            ->custom('has_membership_level', ['level' => 'premium'])
            ->match_none()
        ->then()
            ->custom('restrict_content', [
                'message' => 'This premium content is available to Premium and Enterprise members.',
                'cta_url' => home_url('/upgrade-to-premium')
            ])
        ->register();

    // Rule 2: Expired membership redirect
    Rules::create('expired_membership_redirect', 'wp')
        ->on('template_redirect', 5)
        ->when()
            ->is_user_logged_in()
            ->custom('membership_expired')
            ->request_url('/members/*', 'LIKE')
        ->then()
            ->custom('redirect_to', [
                'url' => home_url('/renew-membership'),
                'message' => 'Your membership has expired. Please renew to access member content.'
            ])
        ->register();

    // Rule 3: Show membership badge on comments
    Rules::create('show_member_badge', 'wp')
        ->on('comment_text', 10)
        ->when()->is_user_logged_in()
        ->then()->custom('show_membership_badge')
        ->register();

    // Rule 4: Member-only downloads
    Rules::create('protect_downloads', 'wp')
        ->on('template_redirect', 10)
        ->when()
            ->request_url('/downloads/*', 'LIKE')
            ->custom('has_membership_level', ['level' => 'basic'])
            ->match_none()
        ->then()
            ->custom('redirect_to', [
                'url' => home_url('/membership'),
                'message' => 'Membership required to access downloads.'
            ])
        ->register();

}, 1);
```

---

## Summary

These examples demonstrate:

✅ **Complete implementations** - Ready-to-use code
✅ **Real-world scenarios** - Common use cases
✅ **Best practices** - Proper error handling and validation
✅ **WordPress integration** - Hook usage and compatibility
✅ **Advanced patterns** - Complex condition logic and actions

## Next Steps

- **[Getting Started](../getting-started/introduction.md)** - Begin your MilliRules journey
- **[Core Concepts](../core/concepts.md)** - Understand the fundamentals
- **[API Reference](../reference/api.md)** - Complete method documentation

---

**Have questions or suggestions?** Visit the [MilliRules GitHub repository](https://github.com/millipress/millirules) for support and contributions.
