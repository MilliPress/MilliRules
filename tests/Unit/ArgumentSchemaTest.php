<?php

/**
 * ArgumentSchema Test
 *
 * Tests for the walking-builder ArgumentSchema + ArgumentsBuilder pair.
 * Schemas are obtained via $meta->args()->type($key) in production code;
 * these tests use a standalone builder for isolation.
 *
 * @package MilliRules\Tests
 */

use MilliRules\ArgumentSchema;
use MilliRules\ArgumentsBuilder;

// -----------------------------------------------------------------
// ArgumentsBuilder factories
// -----------------------------------------------------------------

test('builder creates schemas with correct type and key', function () {
    $builder = new ArgumentsBuilder();

    $string  = $builder->string('a');
    $integer = $builder->integer(0);
    $number  = $builder->number('rate');
    $boolean = $builder->boolean('flag');
    $choice  = $builder->choice('mode');
    $choices = $builder->choices('tags');

    expect($string->get_type())->toBe(ArgumentSchema::TYPE_STRING)->and($string->get_key())->toBe('a');
    expect($integer->get_type())->toBe(ArgumentSchema::TYPE_INTEGER)->and($integer->get_key())->toBe(0);
    expect($number->get_type())->toBe(ArgumentSchema::TYPE_NUMBER)->and($number->get_key())->toBe('rate');
    expect($boolean->get_type())->toBe(ArgumentSchema::TYPE_BOOLEAN)->and($boolean->get_key())->toBe('flag');
    expect($choice->get_type())->toBe(ArgumentSchema::TYPE_CHOICE)->and($choice->get_key())->toBe('mode');
    expect($choices->get_type())->toBe(ArgumentSchema::TYPE_CHOICES)->and($choices->get_key())->toBe('tags');
});

test('builder collects all schemas in declaration order', function () {
    $builder = new ArgumentsBuilder();
    $builder->string('a');
    $builder->integer('b');
    $builder->boolean('c');

    $schemas = $builder->get_schemas();
    expect($schemas)->toHaveCount(3)
        ->and($schemas[0]->get_key())->toBe('a')
        ->and($schemas[1]->get_key())->toBe('b')
        ->and($schemas[2]->get_key())->toBe('c');
});

// -----------------------------------------------------------------
// Walking delegation — type methods on a schema delegate to the builder
// -----------------------------------------------------------------

test('walking: calling a type method on a schema starts a new argument', function () {
    $builder = new ArgumentsBuilder();

    $builder
        ->integer('ttl')->default(3600)
        ->string('reason')->default('');

    $schemas = $builder->get_schemas();
    expect($schemas)->toHaveCount(2)
        ->and($schemas[0]->get_type())->toBe('integer')
        ->and($schemas[0]->get_default())->toBe(3600)
        ->and($schemas[1]->get_type())->toBe('string')
        ->and($schemas[1]->get_default())->toBe('');
});

test('walking: all type methods delegate correctly', function () {
    $builder = new ArgumentsBuilder();

    $builder->integer('i')
        ->string('s')
        ->number('n')
        ->boolean('b')
        ->choice('c')->options(['x', 'y'])
        ->choices('cs')->options(['a', 'b']);

    $schemas = $builder->get_schemas();
    $types   = array_map(fn($s) => $s->get_type(), $schemas);

    expect($types)->toBe(['integer', 'string', 'number', 'boolean', 'choice', 'choices']);
});

// -----------------------------------------------------------------
// Fluent setters on a schema
// -----------------------------------------------------------------

test('fluent setters return self for continued configuration', function () {
    $builder = new ArgumentsBuilder();
    $schema  = $builder->string('name');

    expect($schema->label('Name'))->toBe($schema)
        ->and($schema->description('Help text'))->toBe($schema)
        ->and($schema->format('email'))->toBe($schema)
        ->and($schema->required())->toBe($schema)
        ->and($schema->default('foo'))->toBe($schema)
        ->and($schema->also_accepts('array'))->toBe($schema);
});

test('setters store and expose values correctly', function () {
    $builder = new ArgumentsBuilder();
    $schema  = $builder->string('website')
        ->format('url')
        ->label('Website')
        ->description('Your homepage URL')
        ->required();

    expect($schema->get_format())->toBe('url')
        ->and($schema->get_label())->toBe('Website')
        ->and($schema->get_description())->toBe('Your homepage URL')
        ->and($schema->is_required())->toBeTrue();
});

// -----------------------------------------------------------------
// default() handling
// -----------------------------------------------------------------

test('has_default() is false until default() is called', function () {
    $builder = new ArgumentsBuilder();
    $schema  = $builder->string('k');
    expect($schema->has_default())->toBeFalse();

    $schema->default('x');
    expect($schema->has_default())->toBeTrue();
});

test('default() stores null as an intentional default', function () {
    $builder = new ArgumentsBuilder();
    $schema  = $builder->string('k')->default(null);
    expect($schema->has_default())->toBeTrue()
        ->and($schema->get_default())->toBeNull();
});

test('default() stores zero, false, and empty string distinctly from unset', function () {
    $builder = new ArgumentsBuilder();
    $zero    = $builder->integer('a')->default(0);
    $false   = $builder->boolean('b')->default(false);
    $empty   = $builder->string('c')->default('');

    expect($zero->has_default())->toBeTrue()->and($zero->get_default())->toBe(0);
    expect($false->has_default())->toBeTrue()->and($false->get_default())->toBeFalse();
    expect($empty->has_default())->toBeTrue()->and($empty->get_default())->toBe('');
});

test('default() rejects closures', function () {
    (new ArgumentsBuilder())->string('k')->default(fn() => 'dynamic');
})->throws(InvalidArgumentException::class, 'closures');

// -----------------------------------------------------------------
// Runtime guards at declaration time
// -----------------------------------------------------------------

test('min() throws on boolean type', function () {
    (new ArgumentsBuilder())->boolean('k')->min(5);
})->throws(InvalidArgumentException::class, 'min');

test('max() throws on choice type', function () {
    (new ArgumentsBuilder())->choice('k')->max(10);
})->throws(InvalidArgumentException::class, 'max');

test('min() throws on choices type', function () {
    (new ArgumentsBuilder())->choices('k')->min(1);
})->throws(InvalidArgumentException::class, 'choices');

test('min() and max() work on string, integer, and number', function () {
    $builder = new ArgumentsBuilder();
    expect($builder->string('a')->min(1)->max(100)->get_min())->toBe(1);
    expect($builder->integer('b')->min(0)->max(999)->get_max())->toBe(999);
    expect($builder->number('c')->min(-50)->get_min())->toBe(-50);
});

test('min() and max() accept float bounds on number type', function () {
    $schema = (new ArgumentsBuilder())->number('ratio')->min(0.1)->max(1.0);
    expect($schema->get_min())->toBe(0.1)
        ->and($schema->get_max())->toBe(1.0);
});

test('validate() enforces float bounds on number type', function () {
    $schema = (new ArgumentsBuilder())->number('ratio')->min(0.1)->max(1.0);
    expect($schema->validate(0.05))->toContain('at least 0.1')
        ->and($schema->validate(1.5))->toContain('at most 1')
        ->and($schema->validate(0.5))->toBeNull();
});

test('min() and max() reject non-numeric types', function () {
    (new ArgumentsBuilder())->number('k')->min('not a number');
})->throws(InvalidArgumentException::class, 'int or float');

test('options() throws on non-choice types', function () {
    (new ArgumentsBuilder())->string('k')->options(['a', 'b']);
})->throws(InvalidArgumentException::class, 'options');

// -----------------------------------------------------------------
// options() normalization
// -----------------------------------------------------------------

test('options() accepts simple form and normalizes to structured', function () {
    $schema = (new ArgumentsBuilder())->choice('k')->options(['GET', 'POST', 'PUT']);

    expect($schema->get_options())->toBe([
        ['value' => 'GET', 'label' => 'GET'],
        ['value' => 'POST', 'label' => 'POST'],
        ['value' => 'PUT', 'label' => 'PUT'],
    ]);
});

test('options() accepts structured form as-is', function () {
    $schema = (new ArgumentsBuilder())->choice('k')->options([
        ['value' => 'get', 'label' => 'GET Request'],
        ['value' => 'post', 'label' => 'POST Request'],
    ]);

    expect($schema->get_options())->toBe([
        ['value' => 'get', 'label' => 'GET Request'],
        ['value' => 'post', 'label' => 'POST Request'],
    ]);
});

test('options() fills missing label with string-cast value', function () {
    $schema = (new ArgumentsBuilder())->choice('k')->options([
        ['value' => 'foo'],
    ]);

    expect($schema->get_options())->toBe([
        ['value' => 'foo', 'label' => 'foo'],
    ]);
});

// -----------------------------------------------------------------
// validate() — happy path
// -----------------------------------------------------------------

test('validate() returns null for valid string', function () {
    $schema = (new ArgumentsBuilder())->string('k');
    expect($schema->validate('hello'))->toBeNull();
});

test('validate() returns null for valid integer (int, numeric string)', function () {
    $schema = (new ArgumentsBuilder())->integer('k');
    expect($schema->validate(42))->toBeNull()
        ->and($schema->validate('42'))->toBeNull();
});

test('validate() returns null for valid number', function () {
    $schema = (new ArgumentsBuilder())->number('k');
    expect($schema->validate(3.14))->toBeNull()
        ->and($schema->validate('2.5'))->toBeNull();
});

test('validate() returns null for valid boolean', function () {
    $schema = (new ArgumentsBuilder())->boolean('k');
    expect($schema->validate(true))->toBeNull()
        ->and($schema->validate('yes'))->toBeNull()
        ->and($schema->validate('0'))->toBeNull();
});

test('validate() returns null for valid choice', function () {
    $schema = (new ArgumentsBuilder())->choice('k')->options(['a', 'b', 'c']);
    expect($schema->validate('a'))->toBeNull()
        ->and($schema->validate('b'))->toBeNull();
});

test('validate() returns null for valid choices', function () {
    $schema = (new ArgumentsBuilder())->choices('k')->options(['x', 'y', 'z']);
    expect($schema->validate(['x', 'y']))->toBeNull();
});

// -----------------------------------------------------------------
// validate() — failure cases
// -----------------------------------------------------------------

test('validate() enforces required', function () {
    $schema = (new ArgumentsBuilder())->string('k')->required();
    expect($schema->validate(null))->toContain('required')
        ->and($schema->validate(''))->toContain('required');
});

test('validate() accepts null for non-required', function () {
    $schema = (new ArgumentsBuilder())->string('k');
    expect($schema->validate(null))->toBeNull();
});

test('validate() enforces string length bounds', function () {
    $schema = (new ArgumentsBuilder())->string('k')->min(3)->max(5);
    expect($schema->validate('hi'))->toContain('at least 3')
        ->and($schema->validate('abcdef'))->toContain('at most 5')
        ->and($schema->validate('hey'))->toBeNull();
});

test('validate() enforces integer value bounds', function () {
    $schema = (new ArgumentsBuilder())->integer('k')->min(1)->max(10);
    expect($schema->validate(0))->toContain('at least 1')
        ->and($schema->validate(11))->toContain('at most 10')
        ->and($schema->validate(5))->toBeNull();
});

test('validate() rejects non-integer values on integer type', function () {
    $schema = (new ArgumentsBuilder())->integer('k');
    expect($schema->validate('not a number'))->toContain('integer')
        ->and($schema->validate(3.14))->toContain('integer');
});

test('validate() rejects invalid boolean values', function () {
    $schema = (new ArgumentsBuilder())->boolean('k');
    expect($schema->validate('maybe'))->toContain('boolean');
});

test('validate() rejects invalid choice', function () {
    $schema = (new ArgumentsBuilder())->choice('k')->options(['a', 'b']);
    expect($schema->validate('c'))->toContain('allowed options');
});

test('validate() rejects invalid choices element', function () {
    $schema = (new ArgumentsBuilder())->choices('k')->options(['x', 'y']);
    expect($schema->validate(['x', 'z']))->toContain('invalid');
});

test('validate() rejects non-array value on choices', function () {
    $schema = (new ArgumentsBuilder())->choices('k')->options(['x', 'y']);
    expect($schema->validate('x'))->toContain('array');
});

// -----------------------------------------------------------------
// sanitize()
// -----------------------------------------------------------------

test('sanitize() coerces strings', function () {
    $schema = (new ArgumentsBuilder())->string('k');
    expect($schema->sanitize(42))->toBe('42')
        ->and($schema->sanitize(null))->toBe('');
});

test('sanitize() coerces integers', function () {
    $schema = (new ArgumentsBuilder())->integer('k');
    expect($schema->sanitize('3600'))->toBe(3600)
        ->and($schema->sanitize(3.7))->toBe(3)
        ->and($schema->sanitize(null))->toBe(0);
});

test('sanitize() coerces numbers', function () {
    $schema = (new ArgumentsBuilder())->number('k');
    expect($schema->sanitize('2.5'))->toBe(2.5)
        ->and($schema->sanitize(null))->toBe(0.0);
});

test('sanitize() coerces booleans from string tokens', function () {
    $schema = (new ArgumentsBuilder())->boolean('k');
    expect($schema->sanitize('yes'))->toBeTrue()
        ->and($schema->sanitize('no'))->toBeFalse()
        ->and($schema->sanitize('1'))->toBeTrue()
        ->and($schema->sanitize('0'))->toBeFalse()
        ->and($schema->sanitize(null))->toBeFalse();
});

test('sanitize() filters choices to valid values', function () {
    $schema = (new ArgumentsBuilder())->choices('k')->options(['a', 'b', 'c']);
    expect($schema->sanitize(['a', 'invalid', 'b']))->toBe(['a', 'b']);
});

test('sanitize() returns default when value is null and default is set', function () {
    $schema = (new ArgumentsBuilder())->integer('k')->default(42);
    expect($schema->sanitize(null))->toBe(42);
});

test('sanitize() returns type zero value when null and no default', function () {
    $schema = (new ArgumentsBuilder())->integer('k');
    expect($schema->sanitize(null))->toBe(0);
});

test('sanitize() falls back to default for invalid choice', function () {
    $schema = (new ArgumentsBuilder())->choice('k')
        ->options(['a', 'b'])
        ->default('a');
    expect($schema->sanitize('invalid'))->toBe('a');
});

// -----------------------------------------------------------------
// also_accepts()
// -----------------------------------------------------------------

test('accepts defaults to primary type only', function () {
    $schema = (new ArgumentsBuilder())->string('k');
    expect($schema->get_accepts())->toBe(['string']);
});

test('also_accepts() adds an additional type', function () {
    $schema = (new ArgumentsBuilder())->string('k')->also_accepts('array');
    expect($schema->get_accepts())->toBe(['string', 'array']);
});

test('also_accepts() does not duplicate existing types', function () {
    $schema = (new ArgumentsBuilder())->string('k')->also_accepts('array')->also_accepts('array');
    expect($schema->get_accepts())->toBe(['string', 'array']);
});

// -----------------------------------------------------------------
// to_array()
// -----------------------------------------------------------------

test('to_array() produces the full wire format', function () {
    $schema = (new ArgumentsBuilder())->integer('ttl')
        ->format('seconds')
        ->label('TTL')
        ->description('Time to live in seconds')
        ->default(3600)
        ->min(0)
        ->max(86400)
        ->required();

    expect($schema->to_array())->toBe([
        'key'            => 'ttl',
        'type'           => 'integer',
        'format'         => 'seconds',
        'label'          => 'TTL',
        'description'    => 'Time to live in seconds',
        'default'        => 3600,
        'has_default'    => true,
        'required'       => true,
        'min'         => 0,
        'max'         => 86400,
        'accepts'     => ['integer'],
        'options'     => [],
    ]);
});

test('to_array() reflects unset defaults correctly', function () {
    $schema = (new ArgumentsBuilder())->string('name');
    $array  = $schema->to_array();

    expect($array['default'])->toBeNull()
        ->and($array['has_default'])->toBeFalse()
        ->and($array['min'])->toBeNull()
        ->and($array['max'])->toBeNull()
        ->and($array['options'])->toBe([]);
});

test('to_array() includes normalized options', function () {
    $schema = (new ArgumentsBuilder())->choice('method')->options(['GET', 'POST']);
    $array  = $schema->to_array();

    expect($array['options'])->toBe([
        ['value' => 'GET', 'label' => 'GET'],
        ['value' => 'POST', 'label' => 'POST'],
    ]);
});
