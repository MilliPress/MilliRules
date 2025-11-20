<?php

/**
 * ConditionBuilder Test
 *
 * Tests for ConditionBuilder inline callback signature handling.
 *
 * @package MilliRules\Tests
 */

use MilliRules\Rules;
use MilliRules\RuleEngine;
use MilliRules\Context;

/**
 * Test: ConditionBuilder->custom() with inline callback receives only Context
 */
test('ConditionBuilder custom() wraps inline callback to receive Context-only', function () {
    $receivedArgs = [];
    $actionExecuted = false;

    $rule = Rules::create('test-cond-inline');

    // This should wrap the callback and register it in Rules::$custom_conditions
    $rule->when()->custom('test_cond_inline', function(Context $context) use (&$receivedArgs) {
        // Capture all arguments received
        $receivedArgs = func_get_args();
        return true;
    });

    $rule->then()->custom('test_action', function(Context $context) use (&$actionExecuted) {
        $actionExecuted = true;
    });

    // Execute a rule using the registered callback
    $engine = new RuleEngine();
    $rules = [
        [
            'id' => 'test',
            'enabled' => true,
            'conditions' => [
                ['type' => 'test_cond_inline'],
            ],
            'actions' => [
                ['type' => 'test_action'],
            ],
        ],
    ];

    $engine->execute($rules, new Context());

    // Verify only one argument (Context) was received
    expect(count($receivedArgs))->toBe(1)
        ->and($receivedArgs[0])->toBeInstanceOf(Context::class)
        ->and($actionExecuted)->toBeTrue();
});

/**
 * Test: ConditionBuilder->custom() inline callback can access context data
 */
test('ConditionBuilder custom() inline callback can access context data', function () {
    $contextValueChecked = false;

    $rule = Rules::create('test-cond-context');

    $rule->when()->custom('test_cond_ctx', function(Context $context) use (&$contextValueChecked) {
        $contextValueChecked = ($context->get('user.id') === 123);
        return $contextValueChecked;
    });

    $rule->then()->custom('act', function(Context $context) {
        // Action executes
    });

    $engine = new RuleEngine();
    $rules = [
        [
            'id' => 'test',
            'enabled' => true,
            'conditions' => [
                ['type' => 'test_cond_ctx'],
            ],
            'actions' => [
                ['type' => 'act'],
            ],
        ],
    ];

    $context = new Context();
    $context->set('user.id', 123);

    $result = $engine->execute($rules, $context);

    expect($contextValueChecked)->toBeTrue()
        ->and($result['rules_matched'])->toBe(1);
});

/**
 * Test: ConditionBuilder->custom() inline condition returning false stops rule execution
 */
test('ConditionBuilder custom() inline condition returning false stops rule execution', function () {
    $actionExecuted = false;

    $rule = Rules::create('test-false');

    $rule->when()->custom('false_cond', function(Context $context) {
        return false;
    });

    $rule->then()->custom('action_false', function(Context $context) use (&$actionExecuted) {
        $actionExecuted = true;
    });

    $engine = new RuleEngine();
    $rules = [
        [
            'id' => 'test',
            'enabled' => true,
            'conditions' => [
                ['type' => 'false_cond'],
            ],
            'actions' => [
                ['type' => 'action_false'],
            ],
        ],
    ];

    $result = $engine->execute($rules, new Context());

    expect($actionExecuted)->toBeFalse()
        ->and($result['rules_matched'])->toBe(0);
});

/**
 * Test: Multiple inline conditions work with match_all
 */
test('ConditionBuilder custom() multiple inline conditions work with match_all logic', function () {
    $firstChecked = false;
    $secondChecked = false;

    $rule = Rules::create('test-multi');

    $rule->when()
        ->match_all()
        ->custom('first_cond', function(Context $context) use (&$firstChecked) {
            $firstChecked = true;
            return true;
        })
        ->custom('second_cond', function(Context $context) use (&$secondChecked) {
            $secondChecked = true;
            return true;
        });

    $rule->then()->custom('action_multi', function(Context $context) {
        // Action executes
    });

    $engine = new RuleEngine();
    $rules = [
        [
            'id' => 'test',
            'enabled' => true,
            'conditions' => [
                ['type' => 'first_cond'],
                ['type' => 'second_cond'],
            ],
            'match_type' => 'all',
            'actions' => [
                ['type' => 'action_multi'],
            ],
        ],
    ];

    $result = $engine->execute($rules, new Context());

    expect($firstChecked)->toBeTrue()
        ->and($secondChecked)->toBeTrue()
        ->and($result['rules_matched'])->toBe(1);
});

/**
 * Test: Inline condition with no type hint still works
 */
test('ConditionBuilder custom() inline condition works without type hint', function () {
    $conditionPassed = false;

    $rule = Rules::create('test-notypeC');

    $rule->when()->custom('cond_notype', function($context) use (&$conditionPassed) {
        $conditionPassed = $context instanceof Context;
        return true;
    });

    $rule->then()->custom('action_notypeC', function(Context $context) {
        // Action executes
    });

    $engine = new RuleEngine();
    $rules = [
        [
            'id' => 'test',
            'enabled' => true,
            'conditions' => [
                ['type' => 'cond_notype'],
            ],
            'actions' => [
                ['type' => 'action_notypeC'],
            ],
        ],
    ];

    $engine->execute($rules, new Context());

    expect($conditionPassed)->toBeTrue();
});
