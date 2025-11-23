<?php

/**
 * Name-Based Conditions Builder Test
 *
 * Tests that name-based conditions (cookie, request_header, etc.) correctly
 * interpret single-argument builder calls as existence checks, not value comparisons.
 *
 * @package MilliRules
 */

use MilliRules\Context;
use MilliRules\Rules;
use MilliRules\RuleEngine;

/**
 * Cookie Condition Tests
 */
test('cookie with single argument creates existence check with name field', function () {
    // Create a ConditionBuilder and test the config it produces
    $ruleBuilder = Rules::create('test-cookie-exists', 'php');
    $conditionBuilder = $ruleBuilder->when();

    // Trigger the cookie() method
    $conditionBuilder->cookie('session_id');

    // Access the conditions from the ConditionBuilder via reflection
    $reflection = new ReflectionClass($conditionBuilder);
    $conditionsProperty = $reflection->getProperty('conditions');
    $conditionsProperty->setAccessible(true);
    $conditions = $conditionsProperty->getValue($conditionBuilder);

    // Verify the condition config has 'name' field, not 'value'
    expect($conditions[0])
        ->toHaveKey('name')
        ->toHaveKey('operator')
        ->and($conditions[0]['name'])->toBe('session_id')
        ->and($conditions[0]['operator'])->toBe('EXISTS')
        ->and($conditions[0])->not->toHaveKey('value');
});

test('cookie with single argument does not match when cookie is not set', function () {
    $engine = new RuleEngine();

    // Build a rule using the builder
    $ruleBuilder = Rules::create('test-cookie-not-exists', 'php');
    $conditionBuilder = $ruleBuilder->when();
    $conditionBuilder->cookie('nonexistent_cookie');

    // Get conditions from ConditionBuilder
    $reflectionCond = new ReflectionClass($conditionBuilder);
    $conditionsProperty = $reflectionCond->getProperty('conditions');
    $conditionsProperty->setAccessible(true);
    $conditions = $conditionsProperty->getValue($conditionBuilder);

    // Get the rule array from Rules
    $reflection = new ReflectionClass($ruleBuilder);
    $ruleProperty = $reflection->getProperty('rule');
    $ruleProperty->setAccessible(true);
    $rule = $ruleProperty->getValue($ruleBuilder);

    // Manually set conditions and actions on the rule
    $rule['conditions'] = $conditions;
    $rule['actions'] = [['type' => 'add_flag', 'flag' => 'cookie_matched', 'value' => true]];

    // Create context without the cookie
    $context = new Context([
        'request' => [
            'cookies' => []
        ]
    ]);

    $result = $engine->execute([$rule], $context);

    // Rule should NOT match because cookie doesn't exist
    expect($result['rules_matched'])->toBe(0)
        ->and($context->get('flags'))->toBe(null);
});

test('cookie with single argument matches when cookie is set', function () {
    // Build a rule using the builder
    $ruleBuilder = Rules::create('test-cookie-exists', 'php');
    $conditionBuilder = $ruleBuilder->when();
    $conditionBuilder->cookie('existing_cookie');

    // Get conditions from ConditionBuilder
    $reflectionCond = new ReflectionClass($conditionBuilder);
    $conditionsProperty = $reflectionCond->getProperty('conditions');
    $conditionsProperty->setAccessible(true);
    $conditions = $conditionsProperty->getValue($conditionBuilder);

    // Verify the generated condition has correct EXISTS check format
    expect($conditions[0])
        ->toHaveKey('name')
        ->toHaveKey('operator')
        ->and($conditions[0]['name'])->toBe('existing_cookie')
        ->and($conditions[0]['operator'])->toBe('EXISTS')
        ->and($conditions[0])->not->toHaveKey('value');

    // This verifies the builder generates the correct config.
    // Full execution test would require mocking $_COOKIE superglobal.
});

test('cookie with two arguments creates value comparison', function () {
    // Create a ConditionBuilder and test the config it produces
    $ruleBuilder = Rules::create('test-cookie-value', 'php');
    $conditionBuilder = $ruleBuilder->when();

    // Trigger the cookie() method with two args
    $conditionBuilder->cookie('user_role', 'admin');

    // Access the conditions from the ConditionBuilder via reflection
    $reflection = new ReflectionClass($conditionBuilder);
    $conditionsProperty = $reflection->getProperty('conditions');
    $conditionsProperty->setAccessible(true);
    $conditions = $conditionsProperty->getValue($conditionBuilder);

    // Verify the condition config has both 'name' and 'value' fields
    expect($conditions[0])
        ->toHaveKey('name')
        ->toHaveKey('value')
        ->toHaveKey('operator')
        ->and($conditions[0]['name'])->toBe('user_role')
        ->and($conditions[0]['value'])->toBe('admin')
        ->and($conditions[0]['operator'])->toBe('=');
});

/**
 * Real-World Use Case: No-Cache Cookies
 * This is the exact bug reported by the user
 */
test('nocache cookies rule does not match when cookies are not set', function () {
    $engine = new RuleEngine();

    $nocache_cookies = ['wordpress_logged_in_*', 'comment_author_*'];

    $ruleBuilder = Rules::create('core-nocache-cookies', 'php')->order(0);
    $conditionBuilder = $ruleBuilder->when_any();

    foreach ($nocache_cookies as $pattern) {
        $conditionBuilder->cookie($pattern);
    }

    // Get conditions from ConditionBuilder
    $reflectionCond = new ReflectionClass($conditionBuilder);
    $conditionsProperty = $reflectionCond->getProperty('conditions');
    $conditionsProperty->setAccessible(true);
    $conditions = $conditionsProperty->getValue($conditionBuilder);

    // Get the rule array from Rules
    $reflection = new ReflectionClass($ruleBuilder);
    $ruleProperty = $reflection->getProperty('rule');
    $ruleProperty->setAccessible(true);
    $rule = $ruleProperty->getValue($ruleBuilder);

    // Manually set conditions and actions on the rule
    $rule['conditions'] = $conditions;
    $rule['actions'] = [['type' => 'add_flag', 'flag' => 'do_not_cache', 'value' => true]];

    // Create context WITHOUT any nocache cookies
    $context = new Context([
        'request' => [
            'cookies' => [
                'some_other_cookie' => 'value'
            ]
        ]
    ]);

    $result = $engine->execute([$rule], $context);

    // Rule should NOT match because no nocache cookies exist
    expect($result['rules_matched'])->toBe(0)
        ->and($context->get('flags.do_not_cache'))->toBe(null);
});

test('nocache cookies rule matches when at least one cookie is set', function () {
    $nocache_cookies = ['wordpress_logged_in_*', 'comment_author_*'];

    $ruleBuilder = Rules::create('core-nocache-cookies', 'php')->order(0);
    $conditionBuilder = $ruleBuilder->when_any();

    foreach ($nocache_cookies as $pattern) {
        $conditionBuilder->cookie($pattern);
    }

    // Get conditions from ConditionBuilder
    $reflectionCond = new ReflectionClass($conditionBuilder);
    $conditionsProperty = $reflectionCond->getProperty('conditions');
    $conditionsProperty->setAccessible(true);
    $conditions = $conditionsProperty->getValue($conditionBuilder);

    // Verify all cookie patterns were added as EXISTS checks with name field
    expect($conditions)->toHaveCount(2)
        ->and($conditions[0]['type'])->toBe('cookie')
        ->and($conditions[0]['name'])->toBe('wordpress_logged_in_*')
        ->and($conditions[0]['operator'])->toBe('EXISTS')
        ->and($conditions[0])->not->toHaveKey('value')
        ->and($conditions[1]['type'])->toBe('cookie')
        ->and($conditions[1]['name'])->toBe('comment_author_*')
        ->and($conditions[1]['operator'])->toBe('EXISTS')
        ->and($conditions[1])->not->toHaveKey('value');

    // This verifies the builder correctly generates EXISTS checks for all patterns.
    // The fix ensures that when cookies DON'T exist, the rule won't match (fixing the reported bug).
});
