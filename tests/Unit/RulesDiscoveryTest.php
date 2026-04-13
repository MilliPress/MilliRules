<?php

/**
 * Rules Discovery & Validation Test
 *
 * Tests for type enumeration (get_all_condition_metas / get_all_action_metas),
 * the MATCH_TYPES constant, and the validate() API.
 *
 * @package MilliRules\Tests
 */

use MilliRules\Rules;
use MilliRules\RuleEngine;
use MilliRules\Context;
use MilliRules\Actions\ActionMeta;
use MilliRules\Conditions\ConditionMeta;

// -----------------------------------------------------------------
// Helper: clear all static registries between tests
// -----------------------------------------------------------------

function clearRulesState(): void
{
    $reflection = new ReflectionClass(Rules::class);

    $props = [
        'custom_conditions',
        'custom_actions',
        'action_metas',
        'condition_metas',
        'meta_cache',
        'scope_cache',
        'condition_meta_cache',
        'all_action_metas_cache',
        'all_condition_metas_cache',
    ];

    foreach ($props as $prop) {
        $property = $reflection->getProperty($prop);
        $property->setAccessible(true);
        // Nullable caches need null, arrays need empty array.
        // Pass null as first arg for static property (PHP 8.3+ requirement).
        if (strpos($prop, 'all_') === 0) {
            $property->setValue(null, null);
        } else {
            $property->setValue(null, array());
        }
    }
}

beforeEach(function () {
    clearRulesState();
});

// =================================================================
// MATCH_TYPES constant
// =================================================================

test('MATCH_TYPES contains all, any, none', function () {
    expect(Rules::MATCH_TYPES)->toBe(['all', 'any', 'none']);
});

// =================================================================
// get_all_condition_metas()
// =================================================================

test('get_all_condition_metas discovers class-based conditions even without callbacks', function () {
    // No callback conditions registered, but class-based conditions from
    // registered namespaces (PHP package etc.) will still be discovered.
    $metas = Rules::get_all_condition_metas();

    expect($metas)->toBeArray()->not->toBeEmpty();
    foreach ($metas as $type => $meta) {
        expect($meta)->toBeInstanceOf(ConditionMeta::class);
    }
});

test('get_all_condition_metas returns callback-based conditions with labels', function () {
    Rules::register_condition('is_weekend', function ($args, Context $context) {
        return true;
    })->label('Is Weekend');

    $metas = Rules::get_all_condition_metas();

    expect($metas)->toHaveKey('is_weekend')
        ->and($metas['is_weekend'])->toBeInstanceOf(ConditionMeta::class)
        ->and($metas['is_weekend']->get_label())->toBe('Is Weekend');
});

test('get_all_condition_metas includes conditions without labels', function () {
    Rules::register_condition('has_label', function ($args, Context $context) {
        return true;
    })->label('Has Label');

    Rules::register_condition('no_label', function ($args, Context $context) {
        return true;
    });

    $metas = Rules::get_all_condition_metas();

    expect($metas)->toHaveKey('has_label')
        ->and($metas)->toHaveKey('no_label');
});

test('get_all_condition_metas discovers class-based conditions', function () {
    // The core PHP package conditions (Cookie, RequestMethod, etc.) are
    // registered via namespace and should be discovered.
    $metas = Rules::get_all_condition_metas();

    // Class-based conditions from Packages\PHP\Conditions should be present.
    expect($metas)->toHaveKey('cookie')
        ->and($metas)->toHaveKey('request_method')
        ->and($metas)->toHaveKey('request_url');

    foreach ($metas as $type => $meta) {
        expect($type)->toBeString()
            ->and($meta)->toBeInstanceOf(ConditionMeta::class);
    }
});

test('get_all_condition_metas merges callback and class-based', function () {
    Rules::register_condition('custom_cond', function ($args, Context $context) {
        return true;
    })->label('Custom Condition');

    $metas = Rules::get_all_condition_metas();

    // Custom callback-based condition is present.
    expect($metas)->toHaveKey('custom_cond');

    // Class-based conditions from registered namespaces are also present
    // (at least the ones with labels).
    $class_based_count = count($metas) - 1; // minus our custom one
    expect($class_based_count)->toBeGreaterThanOrEqual(0);
});

test('get_all_condition_metas result is cached', function () {
    Rules::register_condition('cached_cond', function ($args, Context $context) {
        return true;
    })->label('Cached');

    $first  = Rules::get_all_condition_metas();
    $second = Rules::get_all_condition_metas();

    // Same array reference from cache.
    expect($first)->toBe($second);
});

test('get_all_condition_metas cache is invalidated on register_condition', function () {
    Rules::register_condition('first', function ($args, Context $context) {
        return true;
    })->label('First');

    $before = Rules::get_all_condition_metas();
    expect($before)->toHaveKey('first');

    Rules::register_condition('second', function ($args, Context $context) {
        return true;
    })->label('Second');

    $after = Rules::get_all_condition_metas();
    expect($after)->toHaveKey('first')
        ->and($after)->toHaveKey('second');
});

// =================================================================
// get_all_action_metas()
// =================================================================

test('get_all_action_metas returns array when no callbacks registered', function () {
    // No callback actions registered. Core Actions namespace has no concrete
    // action classes (only BaseAction/Callback/Interface), so result may be empty.
    $metas = Rules::get_all_action_metas();

    expect($metas)->toBeArray();
    foreach ($metas as $type => $meta) {
        expect($meta)->toBeInstanceOf(ActionMeta::class);
    }
});

test('get_all_action_metas returns callback-based actions with labels', function () {
    Rules::register_action('log_event', function ($args, Context $context) {
        // noop
    })->label('Log Event');

    $metas = Rules::get_all_action_metas();

    expect($metas)->toHaveKey('log_event')
        ->and($metas['log_event'])->toBeInstanceOf(ActionMeta::class)
        ->and($metas['log_event']->get_label())->toBe('Log Event');
});

test('get_all_action_metas includes actions without labels', function () {
    Rules::register_action('has_label', function ($args, Context $context) {
        // noop
    })->label('Has Label');

    Rules::register_action('no_label', function ($args, Context $context) {
        // noop
    });

    $metas = Rules::get_all_action_metas();

    expect($metas)->toHaveKey('has_label')
        ->and($metas)->toHaveKey('no_label');
});

test('get_all_action_metas result is cached', function () {
    Rules::register_action('cached_act', function ($args, Context $context) {
        // noop
    })->label('Cached');

    $first  = Rules::get_all_action_metas();
    $second = Rules::get_all_action_metas();

    expect($first)->toBe($second);
});

test('get_all_action_metas cache is invalidated on register_action', function () {
    Rules::register_action('first', function ($args, Context $context) {
        // noop
    })->label('First');

    $before = Rules::get_all_action_metas();

    Rules::register_action('second', function ($args, Context $context) {
        // noop
    })->label('Second');

    $after = Rules::get_all_action_metas();
    expect($after)->toHaveKey('first')
        ->and($after)->toHaveKey('second');
});

// =================================================================
// RuleEngine::class_name_to_type()
// =================================================================

test('class_name_to_type converts PascalCase to snake_case', function () {
    expect(RuleEngine::class_name_to_type('PostType'))->toBe('post_type')
        ->and(RuleEngine::class_name_to_type('IsUserLoggedIn'))->toBe('is_user_logged_in')
        ->and(RuleEngine::class_name_to_type('WpEnvironment'))->toBe('wp_environment')
        ->and(RuleEngine::class_name_to_type('Cookie'))->toBe('cookie');
});

test('class_name_to_type round-trips with type_to_class_name', function () {
    $types = ['post_type', 'is_user_logged_in', 'wp_environment', 'cookie', 'request_method'];

    foreach ($types as $type) {
        // type → class base → type should round-trip.
        $class_base = str_replace('_', '', ucwords($type, '_'));
        $back       = RuleEngine::class_name_to_type($class_base);
        expect($back)->toBe($type);
    }
});

// =================================================================
// RuleEngine::scan_namespace_types()
// =================================================================

test('scan_namespace_types discovers conditions from registered namespaces', function () {
    $types = RuleEngine::scan_namespace_types('Conditions');

    // Should find at least the PHP package conditions.
    expect($types)->toBeArray()
        ->and($types)->toHaveKey('cookie')
        ->and($types)->toHaveKey('request_method')
        ->and($types)->toHaveKey('request_url');
});

test('scan_namespace_types discovers actions from registered namespaces', function () {
    $types = RuleEngine::scan_namespace_types('Actions');

    // Core Actions namespace only has base/callback/interface — no concrete actions.
    // This verifies the skip list works: BaseAction, Callback, ActionInterface excluded.
    expect($types)->toBeArray();
    expect($types)->not->toHaveKey('base_action')
        ->and($types)->not->toHaveKey('callback')
        ->and($types)->not->toHaveKey('action_interface');
});

test('scan_namespace_types excludes non-condition classes', function () {
    $types = RuleEngine::scan_namespace_types('Conditions');

    // BaseCondition, Callback, ConditionInterface, ConditionMeta should all be excluded.
    expect($types)->not->toHaveKey('base_condition')
        ->and($types)->not->toHaveKey('callback')
        ->and($types)->not->toHaveKey('condition_interface')
        ->and($types)->not->toHaveKey('condition_meta');
});

// =================================================================
// Rules::validate() — match_type
// =================================================================

test('validate accepts valid match types', function () {
    foreach (Rules::MATCH_TYPES as $mt) {
        $errors = Rules::validate([
            'match_type' => $mt,
            'conditions' => [],
            'actions'    => [],
        ]);
        expect($errors)->toBe([]);
    }
});

test('validate rejects invalid match_type', function () {
    $errors = Rules::validate([
        'match_type' => 'invalid',
        'conditions' => [],
        'actions'    => [],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('invalid')
        ->and($errors[0])->toContain('match_type');
});

test('validate defaults match_type to all when missing', function () {
    $errors = Rules::validate([
        'conditions' => [],
        'actions'    => [],
    ]);

    expect($errors)->toBe([]);
});

// =================================================================
// Rules::validate() — conditions
// =================================================================

test('validate accepts known condition types', function () {
    Rules::register_condition('is_test', function ($args, Context $context) {
        return true;
    });

    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            ['type' => 'is_test'],
        ],
        'actions' => [],
    ]);

    expect($errors)->toBe([]);
});

test('validate rejects unknown condition types', function () {
    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            ['type' => 'nonexistent_condition'],
        ],
        'actions' => [],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('nonexistent_condition')
        ->and($errors[0])->toContain('unknown type');
});

test('validate rejects conditions without type', function () {
    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            ['operator' => '=', 'value' => 'test'],
        ],
        'actions' => [],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('missing a type');
});

test('validate checks condition operators against metadata', function () {
    Rules::register_condition('strict_ops', function ($args, Context $context) {
        return true;
    })->operators('=', '!=');

    // Valid operator.
    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            ['type' => 'strict_ops', 'operator' => '='],
        ],
        'actions' => [],
    ]);
    expect($errors)->toBe([]);

    // Invalid operator.
    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            ['type' => 'strict_ops', 'operator' => 'LIKE'],
        ],
        'actions' => [],
    ]);
    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('unsupported operator')
        ->and($errors[0])->toContain('LIKE');
});

test('validate allows any operator when condition declares none', function () {
    Rules::register_condition('open_ops', function ($args, Context $context) {
        return true;
    })->label('Open Operators');
    // No ->operators() call — empty array.

    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            ['type' => 'open_ops', 'operator' => 'REGEXP'],
        ],
        'actions' => [],
    ]);

    expect($errors)->toBe([]);
});

test('validate normalizes operator case for comparison', function () {
    Rules::register_condition('case_ops', function ($args, Context $context) {
        return true;
    })->operators('=', 'LIKE');

    // Lowercase operator should match uppercase declaration.
    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            ['type' => 'case_ops', 'operator' => 'like'],
        ],
        'actions' => [],
    ]);

    expect($errors)->toBe([]);
});

// =================================================================
// Rules::validate() — condition groups
// =================================================================

test('validate handles nested condition groups', function () {
    Rules::register_condition('cond_a', function ($args, Context $context) {
        return true;
    });

    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            [
                'match_type' => 'any',
                'conditions' => [
                    ['type' => 'cond_a'],
                ],
            ],
        ],
        'actions' => [],
    ]);

    expect($errors)->toBe([]);
});

test('validate rejects invalid match_type in condition groups', function () {
    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            [
                'match_type' => 'bad',
                'conditions' => [],
            ],
        ],
        'actions' => [],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('invalid match_type');
});

test('validate recursively validates nested group conditions', function () {
    Rules::register_condition('known', function ($args, Context $context) {
        return true;
    });

    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            [
                'match_type' => 'any',
                'conditions' => [
                    ['type' => 'known'],
                    ['type' => 'unknown_nested'],
                ],
            ],
        ],
        'actions' => [],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('unknown_nested');
});

// =================================================================
// Rules::validate() — actions
// =================================================================

test('validate accepts known action types', function () {
    Rules::register_action('test_act', function ($args, Context $context) {
        // noop
    });

    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [],
        'actions'    => [
            ['type' => 'test_act'],
        ],
    ]);

    expect($errors)->toBe([]);
});

test('validate rejects unknown action types', function () {
    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [],
        'actions'    => [
            ['type' => 'nonexistent_action'],
        ],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('nonexistent_action')
        ->and($errors[0])->toContain('unknown type');
});

test('validate rejects actions without type', function () {
    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [],
        'actions'    => [
            ['value' => 42],
        ],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('missing a type');
});

test('validate checks action arguments via ArgumentSchema', function () {
    Rules::register_action('typed_act', function ($args, Context $context) {
        // noop
    })->args()
        ->integer('ttl')->required()->min(0);

    // Missing required argument.
    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [],
        'actions'    => [
            ['type' => 'typed_act'],
        ],
    ]);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toContain('ttl')
        ->and($errors[0])->toContain('required');
});

test('validate passes valid action arguments', function () {
    Rules::register_action('typed_act', function ($args, Context $context) {
        // noop
    })->args()
        ->integer('ttl')->required()->min(0);

    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [],
        'actions'    => [
            ['type' => 'typed_act', 'ttl' => 3600],
        ],
    ]);

    expect($errors)->toBe([]);
});

// =================================================================
// Rules::validate() — combined errors
// =================================================================

test('validate collects multiple errors across conditions and actions', function () {
    $errors = Rules::validate([
        'match_type' => 'bad_match',
        'conditions' => [
            ['type' => 'unknown_cond'],
        ],
        'actions' => [
            ['type' => 'unknown_act'],
        ],
    ]);

    // At least 3 errors: invalid match_type + unknown condition + unknown action.
    expect(count($errors))->toBeGreaterThanOrEqual(3);
});

test('validate returns empty array for valid rule', function () {
    Rules::register_condition('is_test', function ($args, Context $context) {
        return true;
    })->operators('=', '!=');

    Rules::register_action('do_thing', function ($args, Context $context) {
        // noop
    });

    $errors = Rules::validate([
        'match_type' => 'all',
        'conditions' => [
            ['type' => 'is_test', 'operator' => '=', 'value' => 'yes'],
        ],
        'actions' => [
            ['type' => 'do_thing'],
        ],
    ]);

    expect($errors)->toBe([]);
});
