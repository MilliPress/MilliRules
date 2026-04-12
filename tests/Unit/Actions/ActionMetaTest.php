<?php

/**
 * ActionMeta Test
 *
 * Tests for the ActionMeta fluent metadata builder, focusing on the
 * args() context and the extension bag. Basic scope/label/description/
 * category coverage is provided by LockedActionTest and the doc examples.
 *
 * @package MilliRules\Tests
 */

use MilliRules\Actions\ActionMeta;
use MilliRules\ArgumentSchema;
use MilliRules\ArgumentsBuilder;

// -----------------------------------------------------------------
// args() — nested arguments context
// -----------------------------------------------------------------

test('args() returns an ArgumentsBuilder', function () {
    $meta = new ActionMeta('test_action');
    expect($meta->args())->toBeInstanceOf(ArgumentsBuilder::class);
});

test('args() returns the same builder on repeated calls (cached)', function () {
    $meta = new ActionMeta('test_action');
    $first  = $meta->args();
    $second = $meta->args();
    expect($first)->toBe($second);
});

test('args() collects declared schemas', function () {
    $meta = new ActionMeta('test_action');
    $meta->args()
        ->integer('ttl')->label('TTL')->default(3600)
        ->string('reason')->label('Reason');

    $args = $meta->get_arguments();
    expect($args)->toHaveCount(2)
        ->and($args[0])->toBeInstanceOf(ArgumentSchema::class)
        ->and($args[0]->get_key())->toBe('ttl')
        ->and($args[0]->get_type())->toBe('integer')
        ->and($args[1]->get_key())->toBe('reason')
        ->and($args[1]->get_type())->toBe('string');
});

test('args() preserves declaration order', function () {
    $meta = new ActionMeta('test_action');
    $meta->args()
        ->string('b')
        ->string('a')
        ->string('c');

    $keys = array_map(fn($arg) => $arg->get_key(), $meta->get_arguments());
    expect($keys)->toBe(['b', 'a', 'c']);
});

test('get_arguments() returns empty array when args() never called', function () {
    $meta = new ActionMeta('test_action');
    expect($meta->get_arguments())->toBe([]);
});

test('args() supports both string and integer keys', function () {
    $meta = new ActionMeta('test_action');
    $meta->args()
        ->integer(0)->label('First')
        ->string(1)->label('Second')
        ->boolean('debug')->label('Debug');

    $args = $meta->get_arguments();
    expect($args[0]->get_key())->toBe(0)
        ->and($args[1]->get_key())->toBe(1)
        ->and($args[2]->get_key())->toBe('debug');
});

// -----------------------------------------------------------------
// Auto-forwarding: args() chain can reach back to ActionMeta methods
// -----------------------------------------------------------------

test('unknown methods on an argument schema forward to ActionMeta', function () {
    $meta = new ActionMeta('set_ttl');
    $meta
        ->label('Set TTL')
        ->args()
            ->integer('ttl')->default(3600)
            ->string('reason')->default('')
        ->extend('millicache:icon', 'clock')
        ->extend('millicache:category_color', '#ff0000');

    expect($meta->get_label())->toBe('Set TTL')
        ->and($meta->get_extension('millicache:icon'))->toBe('clock')
        ->and($meta->get_extension('millicache:category_color'))->toBe('#ff0000')
        ->and($meta->get_arguments())->toHaveCount(2);
});

test('unknown methods on the arguments builder forward to ActionMeta', function () {
    $meta = new ActionMeta('set_ttl');
    // Call a meta method directly on the builder (no schemas declared yet)
    $meta->args()->extend('plugin:key', 'value');

    expect($meta->get_extension('plugin:key'))->toBe('value');
});

test('forwarded methods can continue the chain through ActionMeta', function () {
    $meta = new ActionMeta('test_action');
    $meta
        ->label('Test')
        ->args()
            ->integer('count')->default(0)
        ->categories('testing')     // forwarded to meta
        ->extend('my:flag', true); // forwarded to meta

    expect($meta->get_label())->toBe('Test')
        ->and($meta->get_categories())->toBe(['testing'])
        ->and($meta->get_extension('my:flag'))->toBeTrue();
});

test('truly unknown methods throw BadMethodCallException', function () {
    $meta = new ActionMeta('test_action');
    $meta->args()->integer('k')->nonexistent_method();
})->throws(BadMethodCallException::class);

// -----------------------------------------------------------------
// Extension bag
// -----------------------------------------------------------------

test('extend() stores arbitrary values under namespaced keys', function () {
    $meta = (new ActionMeta('test_action'))
        ->extend('millicache:icon', 'clock')
        ->extend('seo:status', 301);

    expect($meta->get_extension('millicache:icon'))->toBe('clock')
        ->and($meta->get_extension('seo:status'))->toBe(301);
});

test('get_extension() returns null for unset keys', function () {
    $meta = new ActionMeta('test_action');
    expect($meta->get_extension('missing'))->toBeNull();
});

test('has_extension() distinguishes set-to-null from not-set', function () {
    $meta = (new ActionMeta('test_action'))
        ->extend('explicitly_null', null);

    expect($meta->has_extension('explicitly_null'))->toBeTrue()
        ->and($meta->has_extension('never_set'))->toBeFalse()
        ->and($meta->get_extension('explicitly_null'))->toBeNull();
});

test('get_extensions() returns the full keyed array', function () {
    $meta = (new ActionMeta('test_action'))
        ->extend('a', 1)
        ->extend('b', 'two')
        ->extend('c', ['nested' => true]);

    expect($meta->get_extensions())->toBe([
        'a' => 1,
        'b' => 'two',
        'c' => ['nested' => true],
    ]);
});

test('extend() accepts arbitrary JSON-serializable values', function () {
    $meta = (new ActionMeta('test_action'))
        ->extend('bool', true)
        ->extend('int', 42)
        ->extend('float', 3.14)
        ->extend('string', 'hello')
        ->extend('array', [1, 2, 3])
        ->extend('nested', ['a' => ['b' => 'c']]);

    expect($meta->get_extension('bool'))->toBeTrue()
        ->and($meta->get_extension('int'))->toBe(42)
        ->and($meta->get_extension('float'))->toBe(3.14)
        ->and($meta->get_extension('string'))->toBe('hello')
        ->and($meta->get_extension('array'))->toBe([1, 2, 3])
        ->and($meta->get_extension('nested'))->toBe(['a' => ['b' => 'c']]);
});

test('extend() is chainable and returns self', function () {
    $meta = new ActionMeta('test_action');
    expect($meta->extend('k', 'v'))->toBe($meta);
});

// -----------------------------------------------------------------
// to_array() integration
// -----------------------------------------------------------------

test('to_array() includes arguments serialized via ArgumentSchema::to_array()', function () {
    $meta = new ActionMeta('set_ttl');
    $meta->label('Set TTL')
        ->categories('caching')
        ->args()
            ->integer('ttl')
                ->format('seconds')
                ->label('TTL')
                ->default(3600)
                ->min(0);

    $array = $meta->to_array();

    expect($array['arguments'])->toHaveCount(1)
        ->and($array['arguments'][0]['key'])->toBe('ttl')
        ->and($array['arguments'][0]['type'])->toBe('integer')
        ->and($array['arguments'][0]['format'])->toBe('seconds')
        ->and($array['arguments'][0]['label'])->toBe('TTL')
        ->and($array['arguments'][0]['default'])->toBe(3600)
        ->and($array['arguments'][0]['min'])->toBe(0);
});

test('to_array() includes extensions bag', function () {
    $meta = (new ActionMeta('test_action'))
        ->label('Test')
        ->extend('millicache:icon', 'clock')
        ->extend('docs:url', 'https://example.com');

    $array = $meta->to_array();

    expect($array['extensions'])->toBe([
        'millicache:icon' => 'clock',
        'docs:url'        => 'https://example.com',
    ]);
});

test('to_array() returns empty arguments and extensions when none set', function () {
    $meta = new ActionMeta('empty_action');
    $array = $meta->to_array();

    expect($array['arguments'])->toBe([])
        ->and($array['extensions'])->toBe([]);
});

test('to_array() includes all top-level keys in stable order', function () {
    $meta = (new ActionMeta('test_action'))
        ->scope('flag')
        ->label('Test')
        ->description('A test action')
        ->categories('testing');

    expect(array_keys($meta->to_array()))->toBe([
        'type',
        'scope',
        'label',
        'description',
        'categories',
        'arguments',
        'extensions',
    ]);
});
