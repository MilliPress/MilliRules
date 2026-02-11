---
title: 'MilliRules Changelog'
post_excerpt: 'Version-by-version breakdown of new features, bug fixes, refactoring, and API changes in MilliRules.'
menu_order: 40
---

# Changelog

## [0.7.0](https://github.com/MilliPress/MilliRules/compare/v0.6.2...v0.7.0) (2026-02-11)


### ⚠ BREAKING CHANGES

* **builders:** Add method normalization for camelCase and snake_case compatibility

### Features

* **builders:** Add method normalization for camelCase and snake_case compatibility ([fad8f3d](https://github.com/MilliPress/MilliRules/commit/fad8f3d7ebc7cee997becb882d8f0bc1c51ec9d0))

## [0.6.2](https://github.com/MilliPress/MilliRules/compare/v0.6.1...v0.6.2) (2026-02-11)


### Bug Fixes

* **package-manager:** Ensure case-insensitive package name mapping ([f6cc490](https://github.com/MilliPress/MilliRules/commit/f6cc490f3e8634992f2b90dc798935b8202c732b))

## [0.6.1](https://github.com/MilliPress/MilliRules/compare/v0.6.0...v0.6.1) (2026-02-09)


### Features

* **docs:** Add changelog file for version tracking and updates ([207f41c](https://github.com/MilliPress/MilliRules/commit/207f41c660fcfc977ad7412891f5226e320ad94d))
* **docs:** Revise package description to highlight features and improve clarity ([9612260](https://github.com/MilliPress/MilliRules/commit/961226066e4aa109e700816fa83ba5a1ee6d0c80))
* **package-manager:** Add method to retrieve all rules with package names ([db1f732](https://github.com/MilliPress/MilliRules/commit/db1f7322e7e3312520d615028bad9607b423d915))


### Documentation

* Replace ASCII diagrams with mermaid flowcharts for better visualization ([5ef0ac5](https://github.com/MilliPress/MilliRules/commit/5ef0ac5d6940949391c7cac58387cae38b7a024e))
* Update internal links to use relative paths for consistency ([bd20128](https://github.com/MilliPress/MilliRules/commit/bd201286ed3590bbad7a6137e0c56b8903e1a9ff))

## [0.6.0](https://github.com/MilliPress/MilliRules/compare/v0.5.0...v0.6.0) (2025-12-17)


### Features

* Add rule replacement by ID and Rules::unregister() method ([05c9a73](https://github.com/MilliPress/MilliRules/commit/05c9a73c5237dc7b2bfd02192663d070776e4502))
* Add rule replacement by ID, Rules::unregister(), and Release Please ([#2](https://github.com/MilliPress/MilliRules/issues/2)) ([9e80708](https://github.com/MilliPress/MilliRules/commit/9e8070820515a4b0d97e7569b65a66d372ab7ef6))


### Bug Fixes

* Remove return type declaration from resolve_builtin_placeholder method ([efc8504](https://github.com/MilliPress/MilliRules/commit/efc850418166fc3db02746d88f0dfd89285c747a))
* Rename normalize_operator to avoid PHP 7.4 static method conflict ([5e6c3e5](https://github.com/MilliPress/MilliRules/commit/5e6c3e5f4e47561a401e1ce6edf3b9f8da83a393))


## [0.5.0](https://github.com/MilliPress/MilliRules/compare/v0.4.0...v0.5.0) (2025-12-17)


### Features

* Add WordPress has_* conditional support ([06ddde9](https://github.com/MilliPress/MilliRules/commit/06ddde9))


## [0.4.0](https://github.com/MilliPress/MilliRules/compare/v0.3.1...v0.4.0) (2025-12-03)


### Features

* Add VERSION constant to MilliRules and update release workflow ([b313037](https://github.com/MilliPress/MilliRules/commit/b313037))
* Add GitHub Actions release workflow ([cf4c7a1](https://github.com/MilliPress/MilliRules/commit/cf4c7a1))
* Add fluent argument access API to action classes ([bee5af0](https://github.com/MilliPress/MilliRules/commit/bee5af0))


### Bug Fixes

* Use PHP 8.1 in release workflow for PHPStan compatibility ([ac7f1ae](https://github.com/MilliPress/MilliRules/commit/ac7f1ae))
* Remove PHP 8.0 mixed type hint for PHP 7.4 compatibility ([0c60c92](https://github.com/MilliPress/MilliRules/commit/0c60c92))
* Remove static from normalize_operator method in IsConditional ([41a1eed](https://github.com/MilliPress/MilliRules/commit/41a1eed))


### Refactoring

* Add type declarations for private properties in ArgumentValue class ([3fc1a21](https://github.com/MilliPress/MilliRules/commit/3fc1a21))


## [0.3.1](https://github.com/MilliPress/MilliRules/compare/v0.3.0...v0.3.1) (2025-12-03)


### Refactoring

* Rename query_vars context to query and remove redundant Query context ([46c7035](https://github.com/MilliPress/MilliRules/commit/46c7035))


## [0.3.0](https://github.com/MilliPress/MilliRules/compare/v0.2.1...v0.3.0) (2025-12-02)


### Features

* Add object property access support to Context::get() ([676bff5](https://github.com/MilliPress/MilliRules/commit/676bff5))


## [0.2.1](https://github.com/MilliPress/MilliRules/compare/v0.2.0...v0.2.1) (2025-11-28)


### Bug Fixes

* Context discovery fails with Mozart and other scoping tools ([cf27cf0](https://github.com/MilliPress/MilliRules/commit/cf27cf0))


## [0.2.0](https://github.com/MilliPress/MilliRules/compare/v0.1.0...v0.2.0) (2025-11-28)


### Features

* Add intelligent logging system with rate limiting ([c4e0c22](https://github.com/MilliPress/MilliRules/commit/c4e0c22))
* Add action-level locking with ->lock() method ([0a34cd7](https://github.com/MilliPress/MilliRules/commit/0a34cd7))
* Refactor comprehensive documentation ([9380a1d](https://github.com/MilliPress/MilliRules/commit/9380a1d))
* Add `load_packages` method for package loading with dependency resolution ([cac041c](https://github.com/MilliPress/MilliRules/commit/cac041c))


### Bug Fixes

* Skip disabled rules in WordPress Package registration ([6a4414f](https://github.com/MilliPress/MilliRules/commit/6a4414f))


### Refactoring

* Rename _args to args for cleaner API ([82e6341](https://github.com/MilliPress/MilliRules/commit/82e6341))
* Change placeholder syntax from colon to dot notation for consistency ([a76c5ed](https://github.com/MilliPress/MilliRules/commit/a76c5ed))
* Rename callback parameter from $config to $args for consistency ([7bac347](https://github.com/MilliPress/MilliRules/commit/7bac347))
* Remove _args wrapper from ActionBuilder for cleaner config structure ([d989bd5](https://github.com/MilliPress/MilliRules/commit/d989bd5))
* Rename `value`/`config` to `type`/`args` in BaseAction for clarity ([195b0cd](https://github.com/MilliPress/MilliRules/commit/195b0cd))
* Pass Context objects to callback actions and conditions ([127c782](https://github.com/MilliPress/MilliRules/commit/127c782))
* Update default hook and priority in Rules and registration logic ([5cb08a4](https://github.com/MilliPress/MilliRules/commit/5cb08a4))


## [0.1.0](https://github.com/MilliPress/MilliRules/releases/tag/v0.1.0) (2025-11-17)


### Features

* Initial MilliRules implementation ([d3a7b44](https://github.com/MilliPress/MilliRules/commit/d3a7b44))
* Implement lazy context loading system ([96d9977](https://github.com/MilliPress/MilliRules/commit/96d9977))
* Add generic package-based class resolution ([2bde3e5](https://github.com/MilliPress/MilliRules/commit/2bde3e5))
* Add deferred rule registration with pending queue for unloaded packages ([6eb8ce4](https://github.com/MilliPress/MilliRules/commit/6eb8ce4))
* Auto-add WP package if 'wp' type is detected without it in required packages ([d23f10d](https://github.com/MilliPress/MilliRules/commit/d23f10d))
* Add support for accessing WordPress hook arguments in execution context ([02bc150](https://github.com/MilliPress/MilliRules/commit/02bc150))


### Bug Fixes

* Add fallback for generic `is_*` conditions in RuleEngine ([a68da5f](https://github.com/MilliPress/MilliRules/commit/a68da5f))
* Simplify conditional logic in WordPress hook registration ([494eb20](https://github.com/MilliPress/MilliRules/commit/494eb20))
* Update context key to `wp.hook` for WordPress hook arguments ([ce759cb](https://github.com/MilliPress/MilliRules/commit/ce759cb))
* Add safeguard for missing `add_action` function in WordPress hook registration ([5182ce3](https://github.com/MilliPress/MilliRules/commit/5182ce3))
* Adjust `get_rules` to return rules grouped by hook in WordPress package ([68f94b9](https://github.com/MilliPress/MilliRules/commit/68f94b9))
* Correct package name from 'WordPress' to 'WP' in required package check ([60fcb4d](https://github.com/MilliPress/MilliRules/commit/60fcb4d))


### Refactoring

* Update conditions and actions to use Context object ([7b277ee](https://github.com/MilliPress/MilliRules/commit/7b277ee))
* Update core classes to use Context object ([178bbda](https://github.com/MilliPress/MilliRules/commit/178bbda))
* Update packages to use lazy context system ([4266ff5](https://github.com/MilliPress/MilliRules/commit/4266ff5))
* Replace individual condition classes with unified implementations ([4c3240b](https://github.com/MilliPress/MilliRules/commit/4c3240b))
