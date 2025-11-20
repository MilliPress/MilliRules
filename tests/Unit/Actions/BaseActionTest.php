<?php

/**
 * BaseAction Test
 *
 * Tests for BaseAction argument processing.
 *
 * @package MilliRules\Tests
 */

use MilliRules\Actions\BaseAction;
use MilliRules\Context;

/**
 * Test action class for testing BaseAction behavior.
 */
class TestAction extends BaseAction
{
    public function execute(Context $context): void
    {
        // No-op for testing
    }

    public function get_type(): string
    {
        return 'test_action';
    }

    /**
     * Public getter for testing args access.
     */
    public function get_args(): array
    {
        return $this->args;
    }
}

/**
 * Test: Named parameters from dynamic method
 */
test('BaseAction processes named parameters', function () {
    $config = [
        'type' => 'test_action',
        'to' => 'email@example.com',
        'subject' => 'Test Subject'
    ];

    $action = new TestAction($config, new Context());

    expect($action->get_args())->toBe(['to' => 'email@example.com', 'subject' => 'Test Subject'])
        ->and($action->get_type())->toBe('test_action');
});

/**
 * Test: Positional parameters from dynamic method
 */
test('BaseAction processes positional parameters', function () {
    $config = [
        'type' => 'test_action',
        0 => 'arg1',
        1 => 'arg2',
        2 => 'arg3'
    ];

    $action = new TestAction($config, new Context());

    expect($action->get_args())->toBe([0 => 'arg1', 1 => 'arg2', 2 => 'arg3'])
        ->and($action->get_type())->toBe('test_action');
});

/**
 * Test: Single scalar parameter
 */
test('BaseAction processes single scalar parameter', function () {
    $config = [
        'type' => 'test_action',
        0 => 'single_value'
    ];

    $action = new TestAction($config, new Context());

    expect($action->get_args())->toBe([0 => 'single_value'])
        ->and($action->get_type())->toBe('test_action');
});

/**
 * Test: Empty config (no parameters)
 */
test('BaseAction handles empty config', function () {
    $config = [
        'type' => 'test_action'
    ];

    $action = new TestAction($config, new Context());

    expect($action->get_args())->toBe([])
        ->and($action->get_type())->toBe('test_action');
});

/**
 * Test: Mixed array with numeric and string keys
 */
test('BaseAction preserves mixed array keys', function () {
    $config = [
        'type' => 'test_action',
        0 => 'positional',
        'named' => 'value',
        1 => 'second'
    ];

    $action = new TestAction($config, new Context());

    expect($action->get_args())->toBe([0 => 'positional', 'named' => 'value', 1 => 'second'])
        ->and($action->get_type())->toBe('test_action');
});

/**
 * Test: Action can access args via public property
 */
test('BaseAction args are accessible via public property', function () {
    $config = [
        'type' => 'test_action',
        'to' => 'email@example.com'
    ];

    $action = new TestAction($config, new Context());

    // Accessing via property
    expect($action->get_args()['to'])->toBe('email@example.com');
});

/**
 * Test: Type key is filtered out from args
 */
test('BaseAction filters type key from args', function () {
    $config = [
        'type' => 'test_action',
        'param1' => 'value1',
        'param2' => 'value2'
    ];

    $action = new TestAction($config, new Context());

    // Type should not be in args
    expect($action->get_args())->toBe(['param1' => 'value1', 'param2' => 'value2'])
        ->and(isset($action->get_args()['type']))->toBeFalse();
});
