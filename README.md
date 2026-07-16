# MilliRules

[![CI](https://github.com/MilliPress/MilliRules/actions/workflows/ci.yml/badge.svg)](https://github.com/MilliPress/MilliRules/actions/workflows/ci.yml)
[![Discord](https://img.shields.io/badge/Discord-Join%20the%20community-5865F2?logo=discord&logoColor=white)](https://discord.gg/mx8HAXaKGY)
[![GitHub Discussions](https://img.shields.io/github/discussions/MilliPress/MilliRules?logo=github&label=Discussions)](https://github.com/MilliPress/MilliRules/discussions)

A flexible, framework-agnostic rule evaluation engine for PHP 7.4+.

## Overview

MilliRules is a powerful rule engine that allows you to define complex conditional logic using a fluent API. It's designed to be framework-agnostic while providing specialized support for HTTP and WordPress environments.

## Features

- **Fluent API**: Build complex rules with an intuitive, chainable syntax
- **Framework Agnostic**: Core engine works with any PHP application
- **Lazy-Loaded Context**: On-demand loading of context data for optimal performance
- **HTTP Support**: Built-in conditions for request handling
- **WordPress Integration**: Native support for WordPress queries and context
- **Extensible**: Easy to add custom conditions and actions
- **Flexible Naming**: Use snake_case or camelCase — `when_all()` and `whenAll()` both work
- **PHP 7.4+ Compatible**: Works with PHP 7.4+, PHP 8.0+ recommended

## Installation

```bash
composer require millipress/millirules
```

## Quick Start

```php
use MilliRules\Rules;

// Simple HTTP rule
Rules::create('api_check')
    ->when()
        ->request_url('/api/*')
    ->then()
        ->custom( 'auth-check', function($context) {
            // Your action here
        })
    ->register();
```

## Documentation

See the [full documentation](https://millipress.com/docs/millirules/) for detailed guides and API reference.

## Community

The [Discord](https://discord.gg/mx8HAXaKGY) server and [GitHub Discussions](https://github.com/MilliPress/MilliRules/discussions) are places to swap setups, trade tips, and share your ideas for where MilliRules should head next. We're listening.

For bug reports, open a [GitHub issue](https://github.com/MilliPress/MilliRules/issues).

## License

GPL-2.0-or-later
