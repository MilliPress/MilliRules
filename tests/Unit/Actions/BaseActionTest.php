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

    /**
     * Public wrapper for testing get_arg() method.
     */
    public function test_get_arg($key, $default = null)
    {
        return $this->get_arg($key, $default);
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

// ============================================
// get_arg() Integration Tests
// ============================================

/**
 * Test: get_arg() with positional key
 */
test('get_arg() with positional key returns ArgumentValue', function () {
    $config = [
        'type' => 'test_action',
        0 => 'value1',
        1 => 'value2'
    ];

    $action = new TestAction($config, new Context());
    $result = $action->test_get_arg(0);

    expect($result)->toBeInstanceOf(\MilliRules\ArgumentValue::class)
        ->and($result->string())->toBe('value1');
});

/**
 * Test: get_arg() with named key returns ArgumentValue
 */
test('get_arg() with named key returns ArgumentValue', function () {
    $config = [
        'type' => 'test_action',
        'to' => 'user@example.com',
        'subject' => 'Hello'
    ];

    $action = new TestAction($config, new Context());
    $result = $action->test_get_arg('to');

    expect($result)->toBeInstanceOf(\MilliRules\ArgumentValue::class)
        ->and($result->string())->toBe('user@example.com');
});

/**
 * Test: get_arg() with missing key uses default
 */
test('get_arg() with missing key uses default', function () {
    $config = [
        'type' => 'test_action'
    ];

    $action = new TestAction($config, new Context());
    $result = $action->test_get_arg('missing', 'default_value');

    expect($result->string())->toBe('default_value');
});

/**
 * Test: get_arg() with null value uses default
 */
test('get_arg() with null value uses default', function () {
    $config = [
        'type' => 'test_action',
        'key' => null
    ];

    $action = new TestAction($config, new Context());
    $result = $action->test_get_arg('key', 'fallback');

    expect($result->string())->toBe('fallback');
});

/**
 * Test: get_arg() supports type conversions
 */
test('get_arg() supports all type conversions', function () {
    $config = [
        'type' => 'test_action',
        'str' => 'hello',
        'int' => 123,
        'bool' => 'true',
        'float' => '123.45',
        'arr' => ['a', 'b', 'c']
    ];

    $action = new TestAction($config, new Context());

    expect($action->test_get_arg('str')->string())->toBe('hello')
        ->and($action->test_get_arg('int')->int())->toBe(123)
        ->and($action->test_get_arg('bool')->bool())->toBeTrue()
        ->and($action->test_get_arg('float')->float())->toBe(123.45)
        ->and($action->test_get_arg('arr')->array())->toBe(['a', 'b', 'c']);
});

/**
 * Test: get_arg() resolves placeholders from context
 */
test('get_arg() resolves placeholders from context', function () {
    $context = new Context();
    $context->set('user', ['name' => 'John', 'email' => 'john@example.com']);

    $config = [
        'type' => 'test_action',
        'greeting' => 'Hello {user.name}!',
        'email' => '{user.email}'
    ];

    $action = new TestAction($config, $context);

    expect($action->test_get_arg('greeting')->string())->toBe('Hello John!')
        ->and($action->test_get_arg('email')->string())->toBe('john@example.com');
});

/**
 * Test: get_arg() with zero value doesn't use default
 */
test('get_arg() with zero value does not use default', function () {
    $config = [
        'type' => 'test_action',
        'count' => 0
    ];

    $action = new TestAction($config, new Context());
    $result = $action->test_get_arg('count', 999);

    expect($result->int())->toBe(0);
});

/**
 * Test: get_arg() with false value doesn't use default
 */
test('get_arg() with false value does not use default', function () {
    $config = [
        'type' => 'test_action',
        'enabled' => false
    ];

    $action = new TestAction($config, new Context());
    $result = $action->test_get_arg('enabled', true);

    expect($result->bool())->toBeFalse();
});

/**
 * Test: get_arg() with empty string doesn't use default
 */
test('get_arg() with empty string does not use default', function () {
    $config = [
        'type' => 'test_action',
        'message' => ''
    ];

    $action = new TestAction($config, new Context());
    $result = $action->test_get_arg('message', 'default');

    expect($result->string())->toBe('');
});

/**
 * Test: get_arg() real-world email scenario
 */
test('get_arg() real-world email action scenario', function () {
    $context = new Context();
    $context->set('user', ['email' => 'recipient@example.com', 'name' => 'Jane']);

    $config = [
        'type' => 'send_email',
        'to' => '{user.email}',
        'subject' => 'Welcome {user.name}!',
        'html' => 'true',
        'priority' => '10'
    ];

    $action = new TestAction($config, $context);

    // Simulates real action class accessing args
    $to = $action->test_get_arg('to', 'admin@example.com')->string();
    $subject = $action->test_get_arg('subject', 'Default Subject')->string();
    $html = $action->test_get_arg('html', false)->bool();
    $priority = $action->test_get_arg('priority', 5)->int();

    expect($to)->toBe('recipient@example.com')
        ->and($subject)->toBe('Welcome Jane!')
        ->and($html)->toBeTrue()
        ->and($priority)->toBe(10);
});

/**
 * Test: get_arg() real-world logging scenario with positional args
 */
test('get_arg() real-world logging scenario with positional args', function () {
    $config = [
        'type' => 'log_message',
        0 => 'ERROR',
        1 => 'Something broke',
        2 => 3
    ];

    $action = new TestAction($config, new Context());

    // Simulates action class with positional arguments
    $level = $action->test_get_arg(0, 'info')->string();
    $message = $action->test_get_arg(1, 'No message')->string();
    $priority = $action->test_get_arg(2, 1)->int();

    expect($level)->toBe('ERROR')
        ->and($message)->toBe('Something broke')
        ->and($priority)->toBe(3);
});
