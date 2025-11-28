# Changelog

All notable changes to MilliRules will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2024-11-24

### Added

- **Intelligent Logging System with Rate Limiting**
  - New `Logger` class with smart rate limiting to prevent log bloat
  - Exact message matching - only identical messages are rate-limited
  - Static cache that persists across requests in PHP-FPM workers
  - Configurable severity levels (ERROR, WARNING, INFO, DEBUG)
  - Environment-aware logging with `WP_DEBUG` and `MILLIRULES_DEBUG` support
  - Error aggregation for high-frequency logging points
  - Automatic summary logs showing repeat counts
  - Configuration via environment variables:
    - `MILLIRULES_DEBUG`: Enable all logging (DEBUG level)
    - `MILLIRULES_LOG_LEVEL`: Set minimum log level
    - `MILLIRULES_RATE_LIMIT`: Configure rate limit window (default: 60 seconds)

### Changed

- **Logging Infrastructure**
  - Replaced all `error_log()` calls with `Logger` class methods across 11 core files
  - Messages now include severity levels in output: `[ERROR]`, `[WARNING]`, `[INFO]`, `[DEBUG]`
  - Identical log messages are suppressed within 60-second window (configurable)
  - INFO and DEBUG logs only appear when debug mode is enabled

### Improved

- **Production Performance**
  - Dramatically reduced log file size and disk usage
  - Prevents duplicate message flooding from repeated requests
  - Minimal performance overhead with in-memory static cache
  - Works without external dependencies or cache systems

### Documentation

- Added `@author` tags to `ActionInterface.php` and `BaseAction.php`
- All 50 PHP class files now have proper `@since` and `@author` documentation tags

### Technical

- All 259 tests passing
- PHPStan analysis: No errors
- Backward compatible - no breaking changes to public API

---

## [0.1.0] - 2024-11-20

### Added

- **Action-Level Locking** (`->lock()` method)
  - Prevent multiple rules from executing the same action type within a single request
  - Use `->lock()` method when building actions via `Rules::create()`
  - Example: Cache operations that should only run once per request

### Fixed

- Skip disabled rules during WordPress Package registration
- Use `pull_request_target` event in release-drafter workflow for proper permissions

### Changed

- **API Improvements**
  - Renamed `_args` to `args` throughout API for cleaner, more intuitive naming
  - Updated `ConditionBuilder` to use `get_argument_mapping()` approach
  - Replaced `is_name_based` with `get_argument_mapping` for more flexible argument handling

### Refactored

- **Placeholder Syntax**
  - Changed from colon notation (`:`) to dot notation (`.`) for consistency
  - Example: `{post:title}` → `{post.title}`

- **Callback Parameters**
  - Renamed callback parameter from `$config` to `$args` for consistency across API

- **BaseAction Structure**
  - Renamed `value`/`config` to `type`/`args` for better clarity
  - Removed `_args` wrapper from `ActionBuilder` for cleaner config structure
  - Enhanced handling of condition arguments in `build_condition_config`

### Documentation

- Comprehensive rewrite of `custom-actions.md` with examples and structure
- Comprehensive rewrite of `custom-conditions.md` to match actions documentation structure
- Updated `wordpress-conditions.md` to reflect `args` parameter rename

### Testing

- Added comprehensive tests for `ActionBuilder` and `ConditionBuilder`
- Added tests for empty conditions with all match_type variants (all/any/none)
- All tests passing with full coverage

---

## Legend

- `Added` - New features
- `Changed` - Changes in existing functionality
- `Deprecated` - Soon-to-be removed features
- `Removed` - Removed features
- `Fixed` - Bug fixes
- `Security` - Vulnerability fixes
- `Improved` - Performance or quality improvements
- `Documentation` - Documentation changes
- `Technical` - Internal technical changes

[0.2.0]: https://github.com/millipress/millirules/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/millipress/millirules/releases/tag/v0.1.0
