<?php

/**
 * ActionBuilder Test
 *
 * Tests for ActionBuilder inline callback signature handling.
 *
 * @package MilliRules\Tests
 */

use MilliRules\Rules;
use MilliRules\RuleEngine;
use MilliRules\Context;

/**
 * Test: ActionBuilder->custom() with inline callback receives only Context
 */
test('ActionBuilder custom() wraps inline callback to receive Context-only', function () {
    $receivedArgs = [];

    // Create a temporary ActionBuilder to test the custom() method
    $rule = Rules::create('test-action-inline');
    $rule->when()->custom('test_condition', function(Context $context) {
        return true;
    });

    // This should wrap the callback and register it in Rules::$custom_actions
    $rule->then()->custom('test_action_inline', function(Context $context) use (&$receivedArgs) {
        // Capture all arguments received
        $receivedArgs = func_get_args();
    });

    // Now execute a rule that uses the registered callback
    $engine = new RuleEngine();
    $rules = [
        [
            'id' => 'test-inline',
            'enabled' => true,
            'conditions' => [],  // No conditions for simplicity
            'actions' => [
                ['type' => 'test_action_inline'],
            ],
        ],
    ];

    $engine->execute($rules, new Context());

    // Verify only one argument (Context) was received
    expect(count($receivedArgs))->toBe(1)
        ->and($receivedArgs[0])->toBeInstanceOf(Context::class);
});

/**
 * Test: ActionBuilder->custom() inline callback can access context data
 */
test('ActionBuilder custom() inline callback can access context data', function () {
    $capturedValue = null;

    $rule = Rules::create('test-context');
    $rule->when()->custom('cond', function(Context $context) {
        return true;
    });

    $rule->then()->custom('test_action_context', function(Context $context) use (&$capturedValue) {
        $capturedValue = $context->get('test.value');
    });

    $engine = new RuleEngine();
    $rules = [
        [
            'id' => 'test',
            'enabled' => true,
            'conditions' => [],
            'actions' => [
                ['type' => 'test_action_context'],
            ],
        ],
    ];

    $context = new Context();
    $context->set('test.value', 'hello');

    $engine->execute($rules, $context);

    expect($capturedValue)->toBe('hello');
});

/**
 * Test: Multiple inline actions work correctly
 */
test('ActionBuilder custom() multiple inline actions work in sequence', function () {
    $executionOrder = [];

    $rule = Rules::create('test-seq');
    $rule->when()->custom('c', function(Context $context) {
        return true;
    });

    $rule->then()
        ->custom('action_first', function(Context $context) use (&$executionOrder) {
            $executionOrder[] = 'first';
        })
        ->custom('action_second', function(Context $context) use (&$executionOrder) {
            $executionOrder[] = 'second';
        })
        ->custom('action_third', function(Context $context) use (&$executionOrder) {
            $executionOrder[] = 'third';
        });

    $engine = new RuleEngine();
    $rules = [
        [
            'id' => 'test',
            'enabled' => true,
            'conditions' => [],
            'actions' => [
                ['type' => 'action_first'],
                ['type' => 'action_second'],
                ['type' => 'action_third'],
            ],
        ],
    ];

    $engine->execute($rules, new Context());

    expect($executionOrder)->toBe(['first', 'second', 'third']);
});

/**
 * Test: Inline action with no type hint still works
 */
test('ActionBuilder custom() inline action works without type hint', function () {
    $executed = false;

    $rule = Rules::create('test-notype');
    $rule->when()->custom('c2', function(Context $context) {
        return true;
    });

    $rule->then()->custom('action_notype', function($context) use (&$executed) {
        $executed = $context instanceof Context;
    });

    $engine = new RuleEngine();
    $rules = [
        [
            'id' => 'test',
            'enabled' => true,
            'conditions' => [],
            'actions' => [
                ['type' => 'action_notype'],
            ],
        ],
    ];

    $engine->execute($rules, new Context());

    expect($executed)->toBeTrue();
});
