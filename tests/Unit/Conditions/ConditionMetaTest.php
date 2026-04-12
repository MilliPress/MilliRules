<?php

/**
 * ConditionMeta Test
 *
 * @package MilliRules\Tests
 */

use MilliRules\Conditions\ConditionMeta;
use MilliRules\ArgumentSchema;
use MilliRules\ArgumentsBuilder;

// -----------------------------------------------------------------
// Core fields
// -----------------------------------------------------------------

test('ConditionMeta stores label and description', function () {
    $meta = (new ConditionMeta('request_url'))
        ->label('Request URL')
        ->description('Match the current request URL.');

    expect($meta->get_type())->toBe('request_url')
        ->and($meta->get_label())->toBe('Request URL')
        ->and($meta->get_description())->toBe('Match the current request URL.');
});

test('ConditionMeta stores variadic categories', function () {
    $meta = (new ConditionMeta('request_url'))
        ->categories('request', 'url');

    expect($meta->get_categories())->toBe(['request', 'url']);
});

test('ConditionMeta stores variadic operators', function () {
    $meta = (new ConditionMeta('request_url'))
        ->operators('=', '!=', 'LIKE', 'REGEXP', 'IN', 'NOT IN');

    expect($meta->get_operators())->toBe(['=', '!=', 'LIKE', 'REGEXP', 'IN', 'NOT IN']);
});

test('operators() defaults to empty array', function () {
    $meta = new ConditionMeta('is_admin');
    expect($meta->get_operators())->toBe([]);
});

test('argument_mapping() stores and returns mapping', function () {
    $meta = (new ConditionMeta('cookie'))
        ->argument_mapping(['name', 'value']);

    expect($meta->get_argument_mapping())->toBe(['name', 'value']);
});

test('argument_mapping() defaults to empty array', function () {
    $meta = new ConditionMeta('is_admin');
    expect($meta->get_argument_mapping())->toBe([]);
});

// -----------------------------------------------------------------
// args() — nested arguments context
// -----------------------------------------------------------------

test('args() returns an ArgumentsBuilder', function () {
    $meta = new ConditionMeta('request_url');
    expect($meta->args())->toBeInstanceOf(ArgumentsBuilder::class);
});

test('args() collects declared schemas', function () {
    $meta = new ConditionMeta('request_header');
    $meta->args()
        ->string('name')->label('Header Name')->required()
        ->string('value')->label('Header Value');

    $args = $meta->get_arguments();
    expect($args)->toHaveCount(2)
        ->and($args[0]->get_key())->toBe('name')
        ->and($args[1]->get_key())->toBe('value');
});

test('get_arguments() returns empty array when args() never called', function () {
    $meta = new ConditionMeta('is_admin');
    expect($meta->get_arguments())->toBe([]);
});

// -----------------------------------------------------------------
// Extension bag
// -----------------------------------------------------------------

test('extend() stores and retrieves values', function () {
    $meta = (new ConditionMeta('request_url'))
        ->extend('my-plugin:icon', 'globe');

    expect($meta->get_extension('my-plugin:icon'))->toBe('globe')
        ->and($meta->has_extension('my-plugin:icon'))->toBeTrue()
        ->and($meta->has_extension('missing'))->toBeFalse();
});

test('get_extensions() returns the full bag', function () {
    $meta = (new ConditionMeta('test'))
        ->extend('a', 1)
        ->extend('b', 'two');

    expect($meta->get_extensions())->toBe(['a' => 1, 'b' => 'two']);
});

// -----------------------------------------------------------------
// Auto-forwarding from args chain
// -----------------------------------------------------------------

test('meta methods called after args() are auto-forwarded', function () {
    $meta = new ConditionMeta('request_url');
    $meta
        ->label('Request URL')
        ->args()
            ->string('value')->label('URL Pattern')
        ->operators('=', '!=', 'LIKE')
        ->extend('my:icon', 'link');

    expect($meta->get_label())->toBe('Request URL')
        ->and($meta->get_operators())->toBe(['=', '!=', 'LIKE'])
        ->and($meta->get_extension('my:icon'))->toBe('link')
        ->and($meta->get_arguments())->toHaveCount(1);
});

// -----------------------------------------------------------------
// to_array()
// -----------------------------------------------------------------

test('to_array() produces the full wire format', function () {
    $meta = (new ConditionMeta('request_url'))
        ->label('Request URL')
        ->description('Match the current request URL.')
        ->categories('request')
        ->operators('=', '!=', 'LIKE')
        ->argument_mapping(['value'])
        ->extend('my:icon', 'globe');

    $meta->args()
        ->string('value')->label('URL Pattern')->required();

    $array = $meta->to_array();

    expect($array['type'])->toBe('request_url')
        ->and($array['label'])->toBe('Request URL')
        ->and($array['description'])->toBe('Match the current request URL.')
        ->and($array['categories'])->toBe(['request'])
        ->and($array['operators'])->toBe(['=', '!=', 'LIKE'])
        ->and($array['argument_mapping'])->toBe(['value'])
        ->and($array['arguments'])->toHaveCount(1)
        ->and($array['arguments'][0]['key'])->toBe('value')
        ->and($array['extensions'])->toBe(['my:icon' => 'globe']);
});

test('to_array() includes all keys in stable order', function () {
    $meta = new ConditionMeta('test');

    expect(array_keys($meta->to_array()))->toBe([
        'type',
        'label',
        'description',
        'categories',
        'operators',
        'argument_mapping',
        'arguments',
        'extensions',
    ]);
});

test('to_array() returns empty arrays for unset collections', function () {
    $meta = new ConditionMeta('empty');
    $array = $meta->to_array();

    expect($array['categories'])->toBe([])
        ->and($array['operators'])->toBe([])
        ->and($array['argument_mapping'])->toBe([])
        ->and($array['arguments'])->toBe([])
        ->and($array['extensions'])->toBe([]);
});
