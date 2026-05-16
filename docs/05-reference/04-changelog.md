---
title: 'MilliRules Changelog'
post_excerpt: 'Version-by-version breakdown of new features, bug fixes, refactoring, and API changes in MilliRules.'
menu_order: 40
---

# Changelog

## [1.1.6](https://github.com/MilliPress/MilliRules/compare/v1.1.5...v1.1.6) (2026-05-16)


### Features

* add current_site condition for multisite blog targeting ([ee02693](https://github.com/MilliPress/MilliRules/commit/ee0269375718d44d8925796106851d6ba0061837))
* register rules from array config via Rules::register_rule() ([2e4a8a5](https://github.com/MilliPress/MilliRules/commit/2e4a8a51b8064c2d5e3843f114f2bd8675e1562e))


### Bug Fixes

* honor NOT IN semantics for conditions with array values ([1da7e65](https://github.com/MilliPress/MilliRules/commit/1da7e65e97ff53a650c656a6254dac3154b57c82))

## [1.1.5](https://github.com/MilliPress/MilliRules/compare/v1.1.4...v1.1.5) (2026-05-04)


### Bug Fixes

* honor -&gt;enabled(false) for non-WordPress rules ([ca54b36](https://github.com/MilliPress/MilliRules/commit/ca54b360aaf0e9bb876bd6648521376c76afd595))

## [1.1.4](https://github.com/MilliPress/MilliRules/compare/v1.1.3...v1.1.4) (2026-05-04)


### Bug Fixes

* execute WordPress-package rules via MilliRules::execute_rules() ([8c46c56](https://github.com/MilliPress/MilliRules/commit/8c46c5649d18f29335eea5f88cfedf8260f5bf3c))

## [1.1.3](https://github.com/MilliPress/MilliRules/compare/v1.1.2...v1.1.3) (2026-05-03)


### Bug Fixes

* defer rules referencing unloaded packages instead of misregistering as Core ([2ce0628](https://github.com/MilliPress/MilliRules/commit/2ce0628db0925689cced5ab7794aded9aaef0310))

## [1.1.2](https://github.com/MilliPress/MilliRules/compare/v1.1.1...v1.1.2) (2026-04-29)


### Bug Fixes

* prevent duplicate package registration when explicit type matches auto-detected package ([29becd4](https://github.com/MilliPress/MilliRules/commit/29becd4228818fdda9958db430e23cd24ef52076))

## [1.1.1](https://github.com/MilliPress/MilliRules/compare/v1.1.0...v1.1.1) (2026-04-24)


### Bug Fixes

* **discovery:** Find actions and conditions in Strauss-prefixed host plugins ([400ea12](https://github.com/MilliPress/MilliRules/commit/400ea120236d445b7936e9ef86c353fceae08a60))

## [1.1.0](https://github.com/MilliPress/MilliRules/compare/v1.0.0...v1.1.0) (2026-04-23)


### Features

* **actions:** Add value-level locking for paired actions via ActionMeta ([74edea9](https://github.com/MilliPress/MilliRules/commit/74edea900eba3fc89bb1864e78f756192efc66d1))
* **actions:** Allow actions to declare metadata for UI-driven rule builders ([3919f2b](https://github.com/MilliPress/MilliRules/commit/3919f2b8482574df9f358b33b04c17ffd018db8d))
* Add rule validation API and metadata discovery methods ([3ad448e](https://github.com/MilliPress/MilliRules/commit/3ad448e9bbf5911f7bf89311088370504f39ee85))
* **conditions:** Add and() connector for combining condition groups with different match types ([3f37214](https://github.com/MilliPress/MilliRules/commit/3f37214cb905e98c2d03c972a3067d5c37ad1904))
* **conditions:** Add format('pattern') to name fields that support wildcards ([c82baf3](https://github.com/MilliPress/MilliRules/commit/c82baf3b41809288bacec1d1d08fe1112939106b))
* **conditions:** Add metadata to all built-in conditions and auto-generate for WordPress conditionals ([0ec5e35](https://github.com/MilliPress/MilliRules/commit/0ec5e35702c61a055962086eae8b684602279f09))
* **conditions:** Add mode/accepts to schema, auto-infer pattern operators ([24dc931](https://github.com/MilliPress/MilliRules/commit/24dc9313fd94468d256c779260c3003ccf9455cb))
* **conditions:** Allow conditions to declare metadata for UI-driven rule builders ([8c2cf46](https://github.com/MilliPress/MilliRules/commit/8c2cf463b42c09f2353812df671c36be20ba4930))
* **conditions:** Parse condition description from WP function docblocks ([5db66d9](https://github.com/MilliPress/MilliRules/commit/5db66d9d71ac0ac0524069c0aab342376ad15bf9))
* **rules:** Add rule-level locking to prevent overwriting or unregistering safety-critical rules ([ed64a28](https://github.com/MilliPress/MilliRules/commit/ed64a28c2f85555b10709cdb9fbd8dac7c34c6a7))
* **schema:** Validate and sanitize array values via also_accepts('array') ([b60f5e6](https://github.com/MilliPress/MilliRules/commit/b60f5e63c5be718e4699ff5013beaf15febfa144))
* **wp:** Group rules by hook priority and reuse engine across priorities ([45a5180](https://github.com/MilliPress/MilliRules/commit/45a518044b7185a314c216bb17502f3a57bcd5b2))


### Bug Fixes

* **actions:** Support named argument keys from data-stored rules ([5b3ac7c](https://github.com/MilliPress/MilliRules/commit/5b3ac7c9dfc71b0b59d6b2fbdf31b156a151b4df))
* **conditions:** Rename fn_args to args and trim trailing empty strings ([d40a3c7](https://github.com/MilliPress/MilliRules/commit/d40a3c7ff200d45a96065b86ea68e39752103965))
* Correct [@since](https://github.com/since) version tags from 1.2.0 to 1.1.0 ([e84498b](https://github.com/MilliPress/MilliRules/commit/e84498bd87abe434e37a6a65286ea86776625797))
* **discovery:** Exclude handler base classes from condition catalog ([29186a8](https://github.com/MilliPress/MilliRules/commit/29186a815cf9f03e841610abfb6e8e8968669760))
* **engine:** Resolve scoped lock keys from named action arguments ([50ef450](https://github.com/MilliPress/MilliRules/commit/50ef450ffb267d74a9d74b5c5a38d2b2c09a9c89))
* **packages:** Deduplicate rules when required packages change ([d7105d3](https://github.com/MilliPress/MilliRules/commit/d7105d3842801759b914ef6b6842bd672fc1c556))


### Refactoring

* **conditions:** Remove boolean mode from Is/Has conditionals ([c3cffa6](https://github.com/MilliPress/MilliRules/commit/c3cffa68b695d873016f566f4ac2b54fa092fbfc))
* **conditions:** Use compare_values() for name field pattern matching ([58a9a34](https://github.com/MilliPress/MilliRules/commit/58a9a3477151afc7da68756c97821c89eb8f59e8))

## [1.0.0](https://github.com/MilliPress/MilliRules/compare/v0.7.3...v1.0.0) (2026-03-31)


### ⚠ BREAKING CHANGES

**conditions:** The following dedicated condition classes have been removed in favor of the generic `is_*` and `has_*` conditional bridges:

- `category` → use `is_category()` instead
- `tag` → use `is_tag()` instead
- `author` → use `is_author()` instead
- `taxonomy` → use `is_tax()` instead
- `term` → use `has_term()` instead
- `template` → use `is_page_template()` instead
- `post` → use `is_single()` or `is_page()` instead

### Features

* **conditions:** Remove redundant WordPress conditions covered by is_*/has_* ([bd7eed0](https://github.com/MilliPress/MilliRules/commit/bd7eed041ab140d17ca5bd3b0ec9972f4e65f909))

## [0.7.3](https://github.com/MilliPress/MilliRules/compare/v0.7.2...v0.7.3) (2026-02-15)


### Bug Fixes

* **rules:** Always include package for set explicit type ([b2c0d23](https://github.com/MilliPress/MilliRules/commit/b2c0d233782bba937c34beffc2dbb96b167d974c))

## [0.7.2](https://github.com/MilliPress/MilliRules/compare/v0.7.1...v0.7.2) (2026-02-11)


### Bug Fixes

* **conditions:** Return wildcard type identifiers for generic WP conditionals ([0a3b0eb](https://github.com/MilliPress/MilliRules/commit/0a3b0eb4ad131e5d77b7e66ae1b5b22ef12f87fc))

## [0.7.1](https://github.com/MilliPress/MilliRules/compare/v0.7.0...v0.7.1) (2026-02-11)


### Features

* Add public getters for registered namespaces and custom types ([6d1b517](https://github.com/MilliPress/MilliRules/commit/6d1b5175a1c671ff3a03b3f25e3c786797c2eac4))


### Bug Fixes

* **ci:** Drop PHP 8.1 from CI matrix ([ab0ab6e](https://github.com/MilliPress/MilliRules/commit/ab0ab6ec06951a028a79627992a12cad3f6f8335))
* **ci:** Loosen Pest constraint to ^2.0 for PHP 8.1 compatibility ([124d4db](https://github.com/MilliPress/MilliRules/commit/124d4dbaac1030f12cb1b8578e2188753cd2c281))
* **docs:** Correct indentation in README example for better readability ([38b09b8](https://github.com/MilliPress/MilliRules/commit/38b09b8691d864a51054e3712b71d1098909de98))

## [0.7.0](https://github.com/MilliPress/MilliRules/compare/v0.6.2...v0.7.0) (2026-02-11)


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
