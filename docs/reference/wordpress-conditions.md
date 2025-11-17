# WordPress `is_*` Conditions

MilliRules provides a generic WordPress condition class `IsConditional` that
acts as a fallback for all WordPress `is_*` conditional functions when no
more specific condition class exists.

## Basic Usage (Boolean Mode)

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

- The underlying WordPress function is called with **no arguments**.
- The boolean result is compared to the configured `value` (default: `true`)
  using the configured `operator` (default: `IS`).

Examples:

- `->is_404()` → `is_404() IS true`
- `->is_user_logged_in(false)` → `is_user_logged_in() IS false`

## Function Call Mode (Arguments)

For conditionals that accept arguments, you can pass them directly to the
builder. `IsConditional` will call the underlying `is_*` function with those
arguments and compare the result to `true`.

Examples:

```php
// Single-argument conditional
->is_singular('page')           // is_singular('page') IS TRUE

// Multi-argument conditional
->is_tax('genre', 'sci-fi')     // is_tax('genre', 'sci-fi') IS TRUE
```

In this mode:

- All non-boolean arguments are treated as **function arguments** for the
  underlying `is_*` function.
- The condition always checks whether the function result is `true` (using
  `value = true` internally).

## Optional Operator from the Last Argument

You can optionally pass a comparison operator as the **last argument** when
using function call mode. This operator controls how the boolean result of
the `is_*` function is compared to `true`.

Supported operators:

- `=`
- `!=`
- `IS`
- `IS NOT`

Example:

```php
// Calls is_tax('genre', 'sci-fi') and compares result != TRUE
->is_tax('genre', 'sci-fi', '!=');
```

This is useful to express "NOT this conditional" while still using the
function-call form.

## Implementation Notes

- The builder records all raw method arguments in a generic `_args` key
  in the condition config.
- The WordPress `IsConditional` class interprets `_args` to determine
  whether to operate in boolean mode or function-call mode.
- Other packages can reuse the `_args` convention in their own condition
  classes without any changes to core engine or base condition logic.
