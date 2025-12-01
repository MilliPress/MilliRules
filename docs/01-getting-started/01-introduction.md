---
title: 'Introduction to MilliRules'
post_excerpt: 'Discover MilliRules, a powerful rule engine for PHP and WordPress that simplifies conditional logic with an elegant fluent API.'
menu_order: 10
---

# Introduction to MilliRules

MilliRules is a powerful, flexible rule engine for PHP and WordPress that lets you create conditional logic using an elegant fluent API. Whether you're building a WordPress plugin or a framework-agnostic PHP application, MilliRules makes it easy to implement complex business rules without tangling your code with if-else statements.

## What is MilliRules?

MilliRules allows you to define rules that automatically execute actions when specific conditions are met. Think of it as a sophisticated "if-then" system that:

- **Separates logic from code** - Define rules independently of your application logic
- **Works everywhere** - Use in WordPress, Laravel, Symfony, or any PHP 7.4+ project
- **Provides a fluent API** - Write readable, chainable code that's easy to understand
- **Extends easily** - Add custom conditions, actions, and packages for your needs

## Why Use MilliRules?

### Clean, Declarative Code

Instead of scattering conditional logic throughout your codebase:

```php
<?php
// Traditional approach - logic mixed with implementation
if (is_admin() && is_user_logged_in() && $_SERVER['REQUEST_URI'] === '/wp-admin/settings.php') {
    if (current_user_can('manage_options')) {
        do_action('my_admin_action');
        update_option('last_settings_access', time());
        error_log('Admin accessed settings');
    }
}
```

With MilliRules, you define rules declaratively:

```php
<?php
// MilliRules approach - clean and declarative
Rules::create('log_settings_access', 'wp')
    ->title('Log Settings Page Access')
    ->when()
        ->request_url('/wp-admin/settings.php')
        ->is_user_logged_in()
        ->user_can('manage_options')
    ->then()
        ->custom('trigger_admin_action')
        ->custom('update_last_access')
        ->custom('log_access')
    ->register();
```

### Key Benefits

1. **Maintainability** - Rules are self-contained and easy to understand, update, or remove
2. **Reusability** - Define conditions and actions once, use them across multiple rules
3. **Testability** - Test rules in isolation without complex setup
4. **Flexibility** - Dynamically register or unregister rules based on runtime conditions
5. **Organization** - Group related rules together, control execution order
6. **Extensibility** - Create custom conditions, actions, and packages tailored to your needs

## When to Use MilliRules

MilliRules is ideal for:

### WordPress Development
- **Content Filtering** - Modify content based on user roles, post types, or custom conditions
- **Access Control** - Restrict or grant access to pages, features, or content
- **Caching Logic** - Apply cache headers based on request patterns
- **Feature Flags** - Enable/disable features based on environment or user attributes
- **Admin Customization** - Modify admin behavior based on user roles or contexts

### PHP Applications
- **API Rate Limiting** - Apply rate limits based on user tiers or endpoints
- **Request Routing** - Route requests based on complex conditions
- **Data Validation** - Apply validation rules based on context
- **Business Logic** - Implement business rules that change frequently
- **Event Handling** - Trigger actions based on application events

## Core Components

MilliRules is built around four main concepts:

### 1. Rules
The foundation of MilliRules - a rule combines conditions and actions with metadata like title, order, and enabled status.

### 2. Conditions
Define when a rule should execute. MilliRules provides built-in conditions for URLs, HTTP methods, cookies, constants, and WordPress-specific checks.

### 3. Actions
Define what happens when conditions are met. Actions can be simple callbacks, class methods, or complex custom implementations.

### 4. Packages
Modular functionality bundles that provide conditions, actions, context providers, and placeholder resolvers. MilliRules comes with PHP and WordPress packages out of the box.

## How It Works

The MilliRules execution flow:

1. **Initialization** - `MilliRules::init()` registers and loads packages
2. **Rule Registration** - Rules are created and registered using the fluent API
3. **Execution** - Rules execute automatically (WordPress hooks) or manually
4. **Condition Evaluation** - Each rule's conditions are evaluated against the current context
5. **Action Execution** - When conditions match, the rule's actions execute in sequence

## When NOT to Use MilliRules

MilliRules might be overkill for:

- **Simple one-time checks** - A basic `if` statement is often sufficient
- **Performance-critical hot paths** - The rule engine adds minimal but measurable overhead
- **Extremely simple applications** - If you only need 1-2 conditional checks, MilliRules might be unnecessary

## Getting Started

Ready to start using MilliRules? Follow these steps:

1. **[Quick Start](02-quick-start.md)** - Install and initialize MilliRules in minutes
2. **[Your First Rule](03-first-rule.md)** - Create your first rule with a hands-on tutorial
3. **[Core Concepts](../02-core-concepts/01-concepts.md)** - Deep dive into architecture and concepts

## Learn More

### Core Documentation
- **[Core Concepts](../02-core-concepts/01-concepts.md)** - Understand rules, conditions, actions, and packages
- **[Packages System](../02-core-concepts/02-packages.md)** - Learn about the package architecture
- **[Building Rules](../02-core-concepts/03-building-rules.md)** - Master the fluent API

### Customization
- **[Custom Conditions](../03-customization/01-custom-conditions.md)** - Create your own condition types
- **[Custom Actions](../03-customization/02-custom-actions.md)** - Build custom action handlers
- **[Custom Packages](../03-customization/03-custom-packages.md)** - Extend MilliRules with custom packages

### Reference
- **[Conditions Reference](../05-reference/01-conditions.md)** - All available built-in conditions
- **[Actions Reference](../05-reference/02-actions.md)** - Action patterns and examples
- **[API Reference](../05-reference/03-api.md)** - Complete API documentation

---

**Ready to get started?** Continue to [Quick Start Guide](02-quick-start.md) to install and initialize MilliRules.
