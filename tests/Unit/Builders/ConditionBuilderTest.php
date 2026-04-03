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

/**
 * Condition Group Builder Tests (and() connector)
 */
test('and() connector produces grouped conditions that evaluate correctly', function () {
    $engine = new RuleEngine();
    $actionExecuted = false;

    Rules::register_condition('cb_group_true', fn($args, Context $context) => true);
    Rules::register_condition('cb_group_false', fn($args, Context $context) => false);
    Rules::register_action('cb_group_action', function ($args, Context $context) use (&$actionExecuted) {
        $actionExecuted = true;
    });

    // Build rule: (any: true OR false) AND (none: false) → should match.
    $builder = Rules::create('test-and-connector');
    $builder
        ->when_any()
            ->custom('cb_group_true', fn(Context $c) => true)
            ->custom('cb_group_false_2', fn(Context $c) => false)
        ->and()->when_none()
            ->custom('cb_group_false_3', fn(Context $c) => false)
        ->then()
            ->custom('cb_group_action_2', function (Context $c) use (&$actionExecuted) {
                $actionExecuted = true;
            })
        ->register();

    // Execute the rule via engine with the generated structure.
    // Access the rule array via reflection to test the engine directly.
    $reflection = new ReflectionClass($builder);
    $prop = $reflection->getProperty('rule');
    $prop->setAccessible(true);
    $rule = $prop->getValue($builder);

    $result = $engine->execute([$rule], new Context());

    expect($result['rules_matched'])->toBe(1)
        ->and($actionExecuted)->toBeTrue();
});

test('and() connector structure wraps conditions into groups', function () {
    $builder = Rules::create('test-and-structure');

    $builder
        ->when_any()
            ->custom('struct_cond_a', fn(Context $c) => true)
            ->custom('struct_cond_b', fn(Context $c) => false)
        ->and()->when_none()
            ->custom('struct_cond_c', fn(Context $c) => false)
        ->then()
            ->custom('struct_action', fn(Context $c) => null);

    $reflection = new ReflectionClass($builder);
    $prop = $reflection->getProperty('rule');
    $prop->setAccessible(true);
    $rule = $prop->getValue($builder);

    // Should have 2 group entries in conditions.
    expect($rule['conditions'])->toHaveCount(2);

    // First group: match_type 'any' with 2 conditions.
    expect($rule['conditions'][0]['match_type'])->toBe('any')
        ->and($rule['conditions'][0]['conditions'])->toHaveCount(2);

    // Second group: match_type 'none' with 1 condition.
    expect($rule['conditions'][1]['match_type'])->toBe('none')
        ->and($rule['conditions'][1]['conditions'])->toHaveCount(1);
});

test('single group without and() stays flat', function () {
    $builder = Rules::create('test-flat-stays-flat');

    $builder
        ->when_any()
            ->custom('flat_cond_a', fn(Context $c) => true)
            ->custom('flat_cond_b', fn(Context $c) => false)
        ->then()
            ->custom('flat_action', fn(Context $c) => null);

    $reflection = new ReflectionClass($builder);
    $prop = $reflection->getProperty('rule');
    $prop->setAccessible(true);
    $rule = $prop->getValue($builder);

    // Should be flat: conditions are direct condition entries, not groups.
    expect($rule['match_type'])->toBe('any')
        ->and($rule['conditions'])->toHaveCount(2)
        ->and($rule['conditions'][0])->toHaveKey('type');
});

test('three groups chained with two and() calls', function () {
    $engine = new RuleEngine();

    $builder = Rules::create('test-three-groups');

    $builder
        ->when_any()
            ->custom('three_a', fn(Context $c) => true)
            ->custom('three_b', fn(Context $c) => false)
        ->and()->when_all()
            ->custom('three_c', fn(Context $c) => true)
        ->and()->when_none()
            ->custom('three_d', fn(Context $c) => false)
        ->then()
            ->custom('three_action', fn(Context $c) => null);

    $reflection = new ReflectionClass($builder);
    $prop = $reflection->getProperty('rule');
    $prop->setAccessible(true);
    $rule = $prop->getValue($builder);

    // Should have 3 group entries.
    expect($rule['conditions'])->toHaveCount(3)
        ->and($rule['conditions'][0]['match_type'])->toBe('any')
        ->and($rule['conditions'][1]['match_type'])->toBe('all')
        ->and($rule['conditions'][2]['match_type'])->toBe('none');

    // Execute and verify it matches.
    $result = $engine->execute([$rule], new Context());
    expect($result['rules_matched'])->toBe(1);
});
