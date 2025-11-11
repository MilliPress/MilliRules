# MilliRules Tests

This directory contains the test suite for MilliRules using Pest v1.x testing framework.

## Requirements

- PHP 7.4+
- Pest v1.23+

## Running Tests

Run all tests:
```bash
./vendor/bin/pest
```

Run tests without coverage:
```bash
./vendor/bin/pest --no-coverage
```

Run specific test file:
```bash
./vendor/bin/pest tests/Unit/RuleEngineTest.php
```

Run tests with verbose output:
```bash
./vendor/bin/pest --verbose
```

## Test Structure

- `tests/Unit/` - Unit tests for individual components
- `tests/Feature/` - Feature tests for end-to-end scenarios
- `tests/Pest.php` - Pest configuration and global helpers

## Writing Tests

### Basic Test Example

```php
test('description of what it tests', function () {
    // Arrange
    $engine = new RuleEngine();

    // Act
    $result = $engine->execute($rules);

    // Assert
    expect($result['stopped'])->toBeFalse();
});
```

### Using Custom Conditions

```php
Rules::register_condition('my_condition', function ($context, $config) {
    return $context['value'] === $config['expected'];
});
```

### Using Custom Actions

```php
Rules::register_action('my_action', function ($context, $config) {
    // Perform action
});
```

## Pest v1 Syntax

Pest v1 uses a simple, expressive syntax:

```php
// Basic expectation
expect($value)->toBe(123);

// Chained expectations
expect($result['debug']['rules_processed'])->toBe(1)
    ->and($result['debug']['rules_matched'])->toBe(1);

// Boolean checks
expect($value)->toBeTrue();
expect($value)->toBeFalse();

// Array checks
expect($array)->toHaveCount(3);
expect($array)->toContain('value');
```

## Documentation

- [Pest Documentation](https://pestphp.com/docs/v1/overview)
- [Pest Expectations](https://pestphp.com/docs/v1/expectations)
