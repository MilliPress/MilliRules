<?php

/**
 * RuleEngine Test
 *
 * Demonstrates testing rule evaluation using Pest v1.x syntax.
 * This test suite covers basic rule execution, condition matching,
 * and action execution scenarios.
 *
 * @package MilliRules\Tests
 */

use MilliRules\RuleEngine;
use MilliRules\Rules;
use MilliRules\Context;

/**
 * Basic Rule Execution Tests
 */
test('rule engine executes simple rule with matching condition', function () {
    $engine = new RuleEngine();

    // Register a custom condition
    Rules::register_condition('always_true', function ($args, Context $context) {
        return true;
    });

    // Register a custom action
    $actionExecuted = false;
    Rules::register_action('test_action', function ($args, Context $context) use (&$actionExecuted) {
        $actionExecuted = true;
    });

    $rules = [
        [
            'id' => 'test-rule-1',
            'enabled' => true,
            'match_type' => 'all',
            'conditions' => [
                ['type' => 'always_true']
            ],
            'actions' => [
                ['type' => 'test_action']
            ]
        ]
    ];

    $result = $engine->execute($rules, new Context());

    expect($result['rules_processed'])->toBe(1)
        ->and($result['rules_matched'])->toBe(1)
        ->and($actionExecuted)->toBeTrue();
});

test('rule engine skips rule with non-matching condition', function () {
    $engine = new RuleEngine();

    // Register a custom condition that returns false
    Rules::register_condition('always_false', function ($args, Context $context) {
        return false;
    });

    $rules = [
        [
            'id' => 'test-rule-2',
            'enabled' => true,
            'match_type' => 'all',
            'conditions' => [
                ['type' => 'always_false']
            ],
            'actions' => []
        ]
    ];

    $result = $engine->execute($rules, new Context());

    expect($result['rules_processed'])->toBe(1)
        ->and($result['rules_matched'])->toBe(0);
});

test('rule engine respects "all" match type', function () {
    $engine = new RuleEngine();

    Rules::register_condition('condition_a', function ($args, Context $context) {
        return true;
    });

    Rules::register_condition('condition_b', function ($args, Context $context) {
        return false;
    });

    $rules = [
        [
            'id' => 'test-rule-all',
            'enabled' => true,
            'match_type' => 'all',
            'conditions' => [
                ['type' => 'condition_a'],
                ['type' => 'condition_b']
            ],
            'actions' => []
        ]
    ];

    $result = $engine->execute($rules, new Context());

    // Should not match because one condition is false
    expect($result['rules_matched'])->toBe(0);
});

test('rule engine respects "any" match type', function () {
    $engine = new RuleEngine();

    Rules::register_condition('true_condition', function ($args, Context $context) {
        return true;
    });

    Rules::register_condition('false_condition', function ($args, Context $context) {
        return false;
    });

    $rules = [
        [
            'id' => 'test-rule-any',
            'enabled' => true,
            'match_type' => 'any',
            'conditions' => [
                ['type' => 'true_condition'],
                ['type' => 'false_condition']
            ],
            'actions' => []
        ]
    ];

    $result = $engine->execute($rules, new Context());

    // Should match because at least one condition is true
    expect($result['rules_matched'])->toBe(1);
});

test('rule engine respects "none" match type', function () {
    $engine = new RuleEngine();

    Rules::register_condition('failing_condition', function ($args, Context $context) {
        return false;
    });

    $rules = [
        [
            'id' => 'test-rule-none',
            'enabled' => true,
            'match_type' => 'none',
            'conditions' => [
                ['type' => 'failing_condition']
            ],
            'actions' => []
        ]
    ];

    $result = $engine->execute($rules, new Context());

    // Should match because no conditions are true
    expect($result['rules_matched'])->toBe(1);
});

/**
 * Context Tests
 */
test('rule engine passes context to conditions', function () {
    $engine = new RuleEngine();

    Rules::register_condition('check_user', function ($args, Context $context) {
        return $context->has('user_id') && $context->get('user_id') === 123;
    });

    $rules = [
        [
            'id' => 'test-rule-context',
            'enabled' => true,
            'conditions' => [
                ['type' => 'check_user']
            ],
            'actions' => []
        ]
    ];

    $context = new Context();
    $context->set('user_id', 123);
    $result = $engine->execute($rules, $context);

    expect($result['rules_matched'])->toBe(1)
        ->and($result['context']['user_id'])->toBe(123);
});

test('rule engine allows actions to access context', function () {
    $engine = new RuleEngine();

    Rules::register_condition('always_match', function ($args, Context $context) {
        return true;
    });

    $capturedUserId = null;
    Rules::register_action('capture_user', function ($args, Context $context) use (&$capturedUserId) {
        $capturedUserId = $context->get('user_id');
    });

    $rules = [
        [
            'id' => 'test-rule-action-context',
            'enabled' => true,
            'conditions' => [
                ['type' => 'always_match']
            ],
            'actions' => [
                ['type' => 'capture_user']
            ]
        ]
    ];

    $context = new Context();
    $context->set('user_id', 456);
    $result = $engine->execute($rules, $context);

    expect($capturedUserId)->toBe(456);
});

/**
 * Disabled Rule Tests
 */
test('rule engine skips disabled rules', function () {
    $engine = new RuleEngine();

    Rules::register_condition('test_condition', function ($args, Context $context) {
        return true;
    });

    $rules = [
        [
            'id' => 'disabled-rule',
            'enabled' => false,
            'conditions' => [
                ['type' => 'test_condition']
            ],
            'actions' => []
        ]
    ];

    $result = $engine->execute($rules, new Context());

    // Rule is processed but not matched because it's disabled
    expect($result['rules_processed'])->toBe(1)
        ->and($result['rules_matched'])->toBe(0);
});

/**
 * Multiple Rules Tests
 */
test('rule engine executes multiple rules in sequence', function () {
    $engine = new RuleEngine();

    Rules::register_condition('rule_condition', function ($args, Context $context) {
        return true;
    });

    $executionOrder = [];
    Rules::register_action('track_execution', function ($args, Context $context) use (&$executionOrder) {
        $executionOrder[] = $args['rule_name'] ?? 'unknown';
    });

    $rules = [
        [
            'id' => 'rule-1',
            'enabled' => true,
            'conditions' => [
                ['type' => 'rule_condition']
            ],
            'actions' => [
                ['type' => 'track_execution', 'rule_name' => 'first']
            ]
        ],
        [
            'id' => 'rule-2',
            'enabled' => true,
            'conditions' => [
                ['type' => 'rule_condition']
            ],
            'actions' => [
                ['type' => 'track_execution', 'rule_name' => 'second']
            ]
        ],
        [
            'id' => 'rule-3',
            'enabled' => true,
            'conditions' => [
                ['type' => 'rule_condition']
            ],
            'actions' => [
                ['type' => 'track_execution', 'rule_name' => 'third']
            ]
        ]
    ];

    $result = $engine->execute($rules, new Context());

    expect($result['rules_matched'])->toBe(3)
        ->and($executionOrder)->toBe(['first', 'second', 'third']);
});

/**
 * Empty Conditions Tests
 */
test('rule engine matches rules with empty conditions', function () {
    $engine = new RuleEngine();

    $rules = [
        [
            'id' => 'no-conditions-rule',
            'enabled' => true,
            'conditions' => [],
            'actions' => []
        ]
    ];

    $result = $engine->execute($rules, new Context());

    // Rules with no conditions should match
    expect($result['rules_matched'])->toBe(1);
});
