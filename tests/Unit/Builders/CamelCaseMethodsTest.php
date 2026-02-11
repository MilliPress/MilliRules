<?php

/**
 * CamelCase Method Names Test
 *
 * Tests that all fluent API methods can be called in either snake_case or camelCase.
 *
 * @package MilliRules\Tests
 */

use MilliRules\Rules;
use MilliRules\RuleEngine;
use MilliRules\Context;
use MilliRules\Builders\ConditionBuilder;
use MilliRules\Builders\ActionBuilder;

/**
 * Rules camelCase tests
 */
test('Rules whenAll() delegates to when_all()', function () {
    $rule = Rules::create('test-camel-when-all', 'php');
    $builder = $rule->whenAll();

    expect($builder)->toBeInstanceOf(ConditionBuilder::class);
});

test('Rules whenAny() delegates to when_any()', function () {
    $rule = Rules::create('test-camel-when-any', 'php');
    $builder = $rule->whenAny();

    expect($builder)->toBeInstanceOf(ConditionBuilder::class);
});

test('Rules whenNone() delegates to when_none()', function () {
    $rule = Rules::create('test-camel-when-none', 'php');
    $builder = $rule->whenNone();

    expect($builder)->toBeInstanceOf(ConditionBuilder::class);
});

test('Rules setConditions() delegates to set_conditions()', function () {
    $rule = Rules::create('test-camel-set-conditions', 'php');
    $result = $rule->setConditions(
        array( array( 'type' => 'request_url', 'value' => '/test' ) ),
        'all'
    );

    expect($result)->toBeInstanceOf(Rules::class);
});

test('Rules setActions() delegates to set_actions()', function () {
    $rule = Rules::create('test-camel-set-actions', 'php');
    $result = $rule->setActions(
        array( array( 'type' => 'test_action' ) )
    );

    expect($result)->toBeInstanceOf(Rules::class);
});

test('Rules __call throws BadMethodCallException for unknown methods', function () {
    $rule = Rules::create('test-bad-method', 'php');
    $rule->nonExistentMethod();
})->throws(\BadMethodCallException::class);

/**
 * ConditionBuilder camelCase delegation tests
 */
test('ConditionBuilder matchAll() delegates to match_all()', function () {
    $rule = Rules::create('test-cond-match-all', 'php');
    $builder = $rule->when();
    $result = $builder->matchAll();

    expect($result)->toBeInstanceOf(ConditionBuilder::class);
});

test('ConditionBuilder matchAny() delegates to match_any()', function () {
    $rule = Rules::create('test-cond-match-any', 'php');
    $builder = $rule->when();
    $result = $builder->matchAny();

    expect($result)->toBeInstanceOf(ConditionBuilder::class);
});

test('ConditionBuilder matchNone() delegates to match_none()', function () {
    $rule = Rules::create('test-cond-match-none', 'php');
    $builder = $rule->when();
    $result = $builder->matchNone();

    expect($result)->toBeInstanceOf(ConditionBuilder::class);
});

test('ConditionBuilder camelCase then() delegates to Rules', function () {
    $rule = Rules::create('test-cond-then-camel', 'php');
    $result = $rule->when()->requestUrl('/test')->then();

    expect($result)->toBeInstanceOf(ActionBuilder::class);
});

test('ConditionBuilder camelCase whenAll() delegates to Rules', function () {
    $rule = Rules::create('test-cond-when-all-camel', 'php');
    $result = $rule->when()->requestUrl('/test')->whenAll();

    expect($result)->toBeInstanceOf(ConditionBuilder::class);
});

/**
 * ActionBuilder camelCase delegation tests
 */
test('ActionBuilder camelCase register() delegates to Rules', function () {
    $rule = Rules::create('test-action-register-camel', 'php');

    $rule->when()->custom('camel_cond', function () {
        return true;
    });

    $rule->then()->custom('camel_action', function () {
        // no-op
    })->register();

    // If we got here without exception, delegation worked.
    expect(true)->toBeTrue();
});

/**
 * Full chain tests: camelCase used throughout
 */
test('full chain with camelCase builder methods produces working rule', function () {
    $actionExecuted = false;

    Rules::create('test-full-camel', 'php')
        ->whenAll()
            ->custom('full_camel_cond', function () {
                return true;
            })
        ->then()
            ->custom('full_camel_action', function () use (&$actionExecuted) {
                $actionExecuted = true;
            })
        ->register();

    $engine = new RuleEngine();
    $rules = array(
        array(
            'id'         => 'test-full-camel',
            'enabled'    => true,
            'conditions' => array( array( 'type' => 'full_camel_cond' ) ),
            'match_type' => 'all',
            'actions'    => array( array( 'type' => 'full_camel_action' ) ),
        ),
    );

    $engine->execute($rules, new Context());

    expect($actionExecuted)->toBeTrue();
});

test('mixed snake_case and camelCase in same chain works', function () {
    $actionExecuted = false;

    Rules::create('test-mixed-case', 'php')
        ->when_all()
            ->custom('mixed_cond', function () {
                return true;
            })
        ->then()
            ->custom('mixed_action', function () use (&$actionExecuted) {
                $actionExecuted = true;
            })
        ->register();

    $engine = new RuleEngine();
    $rules = array(
        array(
            'id'         => 'test-mixed-case',
            'enabled'    => true,
            'conditions' => array( array( 'type' => 'mixed_cond' ) ),
            'match_type' => 'all',
            'actions'    => array( array( 'type' => 'mixed_action' ) ),
        ),
    );

    $engine->execute($rules, new Context());

    expect($actionExecuted)->toBeTrue();
});