<?php

namespace MilliRules\Tests\Feature;

use MilliRules\Tests\TestCase;
use MilliRules\Rules;
use MilliRules\RuleEngine;
use MilliRules\Context;

/**
 * @covers \MilliRules\Builders\ActionBuilder::lock
 * @covers \MilliRules\RuleEngine::execute_actions
 */
class LockedActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any registered custom conditions/actions
        $this->clearCustomCallbacks();
    }

    /**
     * Clear custom conditions and actions using reflection
     */
    private function clearCustomCallbacks(): void
    {
        $reflection = new \ReflectionClass(Rules::class);

        // Clear custom conditions
        $conditionsProperty = $reflection->getProperty('custom_conditions');
        $conditionsProperty->setAccessible(true);
        $conditionsProperty->setValue(array());

        // Clear custom actions
        $actionsProperty = $reflection->getProperty('custom_actions');
        $actionsProperty->setAccessible(true);
        $actionsProperty->setValue(array());

        // Clear action metas registry
        $metasProperty = $reflection->getProperty('action_metas');
        $metasProperty->setAccessible(true);
        $metasProperty->setValue(array());

        // Clear resolved meta cache
        $cacheProperty = $reflection->getProperty('meta_cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(array());

        // Clear resolved scope cache (engine hot-path cache)
        $scopeCacheProperty = $reflection->getProperty('scope_cache');
        $scopeCacheProperty->setAccessible(true);
        $scopeCacheProperty->setValue(array());

        // Clear condition meta registries
        $condMetasProperty = $reflection->getProperty('condition_metas');
        $condMetasProperty->setAccessible(true);
        $condMetasProperty->setValue(array());

        $condCacheProperty = $reflection->getProperty('condition_meta_cache');
        $condCacheProperty->setAccessible(true);
        $condCacheProperty->setValue(array());

        // Reset the set_meta-was-called tracking flag
        unset($GLOBALS['__test_set_meta_was_called']);
    }

    /**
     * Test that a locked action prevents subsequent same-type actions.
     */
    public function testLockedActionPreventsSameTypeActions(): void
    {
        $execution_log = array();

        // Register custom action
        Rules::register_action('test_action', function ($args, $context) use (&$execution_log) {
            $rule_id = $args['_rule_id'] ?? 'unknown';
            $execution_log[] = $rule_id;
            $context->set('test_value', 'from-' . $rule_id);
        });

        // Define rules manually
        $rules = array(
            array(
                'id' => 'rule-1',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'test_action', '_rule_id' => 'rule-1', '_locked' => true),
                ),
            ),
            array(
                'id' => 'rule-2',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'test_action', '_rule_id' => 'rule-2'),
                ),
            ),
        );

        // Execute rules
        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // Assert: Only rule-1 executed, rule-2 was blocked
        $this->assertSame(array('rule-1'), $execution_log);
        $this->assertSame('from-rule-1', $context->get('test_value'));
        $this->assertSame(2, $result['rules_matched']);
        $this->assertSame(1, $result['actions_executed']);
    }

    /**
     * Test that locked action allows different-type actions to execute.
     */
    public function testLockedActionAllowsDifferentTypeActions(): void
    {
        $execution_log = array();

        // Register two different action types
        Rules::register_action('test_action_a', function ($args, $context) use (&$execution_log) {
            $rule_id = $args['_rule_id'] ?? 'unknown';
            $execution_log[] = $rule_id . '-action-a';
        });

        Rules::register_action('test_action_b', function ($args, $context) use (&$execution_log) {
            $rule_id = $args['_rule_id'] ?? 'unknown';
            $execution_log[] = $rule_id . '-action-b';
        });

        // Define rules
        $rules = array(
            array(
                'id' => 'rule-1',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'test_action_a', '_rule_id' => 'rule-1', '_locked' => true),
                    array('type' => 'test_action_b', '_rule_id' => 'rule-1'),
                ),
            ),
            array(
                'id' => 'rule-2',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'test_action_a', '_rule_id' => 'rule-2'), // Blocked
                    array('type' => 'test_action_b', '_rule_id' => 'rule-2'), // Executes
                ),
            ),
        );

        // Execute
        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // Assert: action_a blocked in rule-2, action_b executed in both
        $expected = array('rule-1-action-a', 'rule-1-action-b', 'rule-2-action-b');
        $this->assertSame($expected, $execution_log);
        $this->assertSame(3, $result['actions_executed']);
    }

    /**
     * Test that multiple locked actions work independently.
     */
    public function testMultipleLockedActionsWorkIndependently(): void
    {
        $execution_log = array();

        // Register three action types
        foreach (array('x', 'y', 'z') as $type) {
            Rules::register_action('action_' . $type, function ($args, $context) use (&$execution_log, $type) {
                $rule_id = $args['_rule_id'] ?? 'unknown';
                $execution_log[] = $rule_id . '-' . $type;
            });
        }

        // Define rules
        $rules = array(
            array(
                'id' => 'rule-1',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'action_x', '_rule_id' => 'rule-1', '_locked' => true),
                    array('type' => 'action_y', '_rule_id' => 'rule-1', '_locked' => true),
                    array('type' => 'action_z', '_rule_id' => 'rule-1'), // Not locked
                ),
            ),
            array(
                'id' => 'rule-2',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'action_x', '_rule_id' => 'rule-2'), // Blocked
                    array('type' => 'action_y', '_rule_id' => 'rule-2'), // Blocked
                    array('type' => 'action_z', '_rule_id' => 'rule-2'), // Executes
                ),
            ),
        );

        // Execute
        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // Assert: x and y blocked in rule-2, z executed in both
        $expected = array('rule-1-x', 'rule-1-y', 'rule-1-z', 'rule-2-z');
        $this->assertSame($expected, $execution_log);
        $this->assertSame(4, $result['actions_executed']);
    }

    /**
     * Test that lock scope is per-execution (fresh context clears locks).
     */
    public function testLockScopeIsPerExecution(): void
    {
        $execution_count = array('first' => 0, 'second' => 0);

        // Register action
        Rules::register_action('scoped_action', function ($args, $context) use (&$execution_count) {
            $key = $args['_key'] ?? 'unknown';
            $execution_count[$key]++;
        });

        // Define rules
        $rules = array(
            array(
                'id' => 'lock-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'scoped_action', '_key' => 'first', '_locked' => true),
                ),
            ),
            array(
                'id' => 'blocked-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'scoped_action', '_key' => 'second'),
                ),
            ),
        );

        $engine = new RuleEngine();

        // First execution - second should be blocked
        $context1 = new Context(array());
        $engine->execute($rules, $context1);
        $this->assertSame(1, $execution_count['first']);
        $this->assertSame(0, $execution_count['second']);

        // Second execution - lock resets, second should still be blocked
        $context2 = new Context(array());
        $engine->execute($rules, $context2);
        $this->assertSame(2, $execution_count['first']);
        $this->assertSame(0, $execution_count['second']);
    }

    /**
     * Test that unlocked actions execute normally.
     */
    public function testUnlockedActionsExecuteNormally(): void
    {
        $execution_log = array();

        // Register action
        Rules::register_action('normal_action', function ($args, $context) use (&$execution_log) {
            $rule_id = $args['_rule_id'] ?? 'unknown';
            $execution_log[] = $rule_id;
        });

        // Define rules without locks
        $rules = array(
            array(
                'id' => 'rule-1',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'normal_action', '_rule_id' => 'rule-1'), // No lock
                ),
            ),
            array(
                'id' => 'rule-2',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'normal_action', '_rule_id' => 'rule-2'), // No lock
                ),
            ),
        );

        // Execute
        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // Assert: Both execute
        $this->assertSame(array('rule-1', 'rule-2'), $execution_log);
        $this->assertSame(2, $result['actions_executed']);
    }

    /**
     * Test that lock only applies if the rule's conditions match.
     */
    public function testLockOnlyAppliesWhenConditionsMatch(): void
    {
        $execution_log = array();

        // Register action and condition
        Rules::register_action('conditional_action', function ($args, $context) use (&$execution_log) {
            $rule_id = $args['_rule_id'] ?? 'unknown';
            $execution_log[] = $rule_id;
        });

        Rules::register_condition('always_false', function ($args, $context) {
            return false;
        });

        // Define rules
        $rules = array(
            array(
                'id' => 'rule-1',
                'match_type' => 'all',
                'conditions' => array(
                    array('type' => 'always_false'), // Won't match
                ),
                'actions' => array(
                    array('type' => 'conditional_action', '_rule_id' => 'rule-1', '_locked' => true),
                ),
            ),
            array(
                'id' => 'rule-2',
                'match_type' => 'all',
                'conditions' => array(), // Always matches
                'actions' => array(
                    array('type' => 'conditional_action', '_rule_id' => 'rule-2'),
                ),
            ),
        );

        // Execute
        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // Assert: Rule-1 didn't match, so lock not applied. Rule-2 executes.
        $this->assertSame(array('rule-2'), $execution_log);
        $this->assertSame(1, $result['actions_executed']);
    }

    /**
     * Test real-world cache use case.
     */
    public function testCacheUseCaseExample(): void
    {
        $cache_values = array();

        // Register action
        Rules::register_action('set_cache', function ($args, $context) use (&$cache_values) {
            $enabled = $args['enabled'] ?? false;
            $cache_values[] = $enabled;
            $context->set('cache.enabled', $enabled);
        });

        // Define rules
        $rules = array(
            array(
                'id' => 'no-cache-logged-in',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'set_cache', 'enabled' => false, '_locked' => true),
                ),
            ),
            array(
                'id' => 'cache-api',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'set_cache', 'enabled' => true), // Blocked
                ),
            ),
        );

        // Execute
        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // Assert: First rule locked cache, second rule's set_cache was blocked
        $this->assertSame(array(false), $cache_values);
        $this->assertSame(false, $context->get('cache.enabled'));
        $this->assertSame(1, $result['actions_executed']);
    }

    // ===========================
    // Scoped Action Locking Tests
    // ===========================

    /**
     * Test that scoped action locks by value, not by type.
     *
     * add_flag('x')->lock() should NOT block add_flag('y').
     */
    public function testScopedActionLocksByValue(): void
    {
        $flags = array();

        Rules::register_action('add_flag', function ($args, $context) use (&$flags) {
            $flags[] = $args[0] ?? 'unknown';
        })->scope('flag');

        $rules = array(
            array(
                'id' => 'rule-1',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'add_flag', 0 => 'archive:author:1', '_locked' => true),
                ),
            ),
            array(
                'id' => 'rule-2',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'add_flag', 0 => 'my-custom-flag'),
                ),
            ),
        );

        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // Both flags should be added — different values, different lock keys.
        $this->assertSame(array('archive:author:1', 'my-custom-flag'), $flags);
        $this->assertSame(2, $result['actions_executed']);
    }

    /**
     * Test that scoped lock blocks the same value across action types sharing a scope.
     *
     * add_flag('x')->lock() should block remove_flag('x') since both share scope 'flag'.
     */
    public function testScopedActionLocksCrossType(): void
    {
        $execution_log = array();

        Rules::register_action('add_flag', function ($args, $context) use (&$execution_log) {
            $execution_log[] = 'add:' . ($args[0] ?? '');
        })->scope('flag');

        Rules::register_action('remove_flag', function ($args, $context) use (&$execution_log) {
            $execution_log[] = 'remove:' . ($args[0] ?? '');
        })->scope('flag');

        $rules = array(
            array(
                'id' => 'core-flags',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'add_flag', 0 => 'archive:author:1', '_locked' => true),
                ),
            ),
            array(
                'id' => 'user-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'remove_flag', 0 => 'archive:author:1'), // Blocked — same scope:value.
                ),
            ),
        );

        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // remove_flag('archive:author:1') should be blocked.
        $this->assertSame(array('add:archive:author:1'), $execution_log);
        $this->assertSame(1, $result['actions_executed']);
    }

    /**
     * Test that scoped lock allows different values across action types sharing a scope.
     *
     * add_flag('x')->lock() should NOT block remove_flag('y').
     */
    public function testScopedActionAllowsDifferentValues(): void
    {
        $execution_log = array();

        Rules::register_action('add_flag', function ($args, $context) use (&$execution_log) {
            $execution_log[] = 'add:' . ($args[0] ?? '');
        })->scope('flag');

        Rules::register_action('remove_flag', function ($args, $context) use (&$execution_log) {
            $execution_log[] = 'remove:' . ($args[0] ?? '');
        })->scope('flag');

        $rules = array(
            array(
                'id' => 'core-flags',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'add_flag', 0 => 'archive:author:1', '_locked' => true),
                ),
            ),
            array(
                'id' => 'user-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'remove_flag', 0 => 'some-other-flag'), // Allowed — different value.
                ),
            ),
        );

        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // Both should execute — different lock keys.
        $this->assertSame(array('add:archive:author:1', 'remove:some-other-flag'), $execution_log);
        $this->assertSame(2, $result['actions_executed']);
    }

    /**
     * Test that unscoped actions still lock by type (backward compatible).
     */
    public function testUnscopedActionStillLocksByType(): void
    {
        $execution_log = array();

        // Register WITHOUT scope — should behave exactly as before.
        Rules::register_action('set_ttl', function ($args, $context) use (&$execution_log) {
            $execution_log[] = 'ttl:' . ($args[0] ?? '');
        });

        $rules = array(
            array(
                'id' => 'rule-1',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'set_ttl', 0 => 300, '_locked' => true),
                ),
            ),
            array(
                'id' => 'rule-2',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'set_ttl', 0 => 600), // Blocked — same type, no scope.
                ),
            ),
        );

        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // Only first set_ttl should execute.
        $this->assertSame(array('ttl:300'), $execution_log);
        $this->assertSame(1, $result['actions_executed']);
    }

    /**
     * Test scoped lock from remove_flag also blocks add_flag (bidirectional).
     */
    public function testScopedLockIsBidirectional(): void
    {
        $execution_log = array();

        Rules::register_action('add_flag', function ($args, $context) use (&$execution_log) {
            $execution_log[] = 'add:' . ($args[0] ?? '');
        })->scope('flag');

        Rules::register_action('remove_flag', function ($args, $context) use (&$execution_log) {
            $execution_log[] = 'remove:' . ($args[0] ?? '');
        })->scope('flag');

        $rules = array(
            array(
                'id' => 'rule-1',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    // Lock removal of a flag.
                    array('type' => 'remove_flag', 0 => 'no-store', '_locked' => true),
                ),
            ),
            array(
                'id' => 'rule-2',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    // Try to add same flag back — blocked by same scope:value.
                    array('type' => 'add_flag', 0 => 'no-store'),
                ),
            ),
        );

        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // add_flag('no-store') blocked because remove_flag('no-store') locked 'flag:no-store'.
        $this->assertSame(array('remove:no-store'), $execution_log);
        $this->assertSame(1, $result['actions_executed']);
    }

    /**
     * Test that scoped actions with non-scalar first arguments skip locking
     * entirely rather than silently producing "Array" lock keys.
     *
     * Previously the (string) cast on an array produced the literal "Array",
     * which meant two different arrays would collide on the same lock key.
     * Now the action executes but is simply not lockable.
     */
    public function testScopedActionSkipsLockOnNonScalarValue(): void
    {
        $execution_log = array();

        Rules::register_action('add_flag', function ($args, $context) use (&$execution_log) {
            $val = $args[0] ?? null;
            $execution_log[] = is_array($val) ? 'array:' . json_encode($val) : 'scalar:' . $val;
        })->scope('flag');

        $rules = array(
            array(
                'id' => 'rule-1',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    // Non-scalar first arg — previously would become "flag:Array".
                    // Now: not lockable, warning logged, action still executes.
                    array('type' => 'add_flag', 0 => array('nested' => 'value'), '_locked' => true),
                ),
            ),
            array(
                'id' => 'rule-2',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    // A different array — previously would collide on "flag:Array".
                    array('type' => 'add_flag', 0 => array('different' => 'structure')),
                ),
            ),
            array(
                'id' => 'rule-3',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    // Scalar works normally — different scoped key.
                    array('type' => 'add_flag', 0 => 'custom-flag'),
                ),
            ),
        );

        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // All three execute — non-scalar fallback means no lock is set or checked.
        $this->assertCount(3, $execution_log);
        $this->assertSame(3, $result['actions_executed']);
    }

    /**
     * Test that class-based actions declaring scope via get_scope() work.
     *
     * Uses a real class (defined in Fixtures/) registered in the
     * MilliRules\Actions namespace via a temporary namespace registration.
     */
    public function testClassBasedActionScopeViaGetScope(): void
    {
        // Register a temporary namespace where our test class lives.
        \MilliRules\RuleEngine::register_namespace('Actions', 'MilliRules\\Tests\\Feature\\Fixtures');

        $execution_log = array();

        // Inject log collector via global since the fixture class needs to access it.
        $GLOBALS['__test_class_based_log'] = &$execution_log;

        $rules = array(
            array(
                'id' => 'rule-1',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'test_scoped_action', 0 => 'val-1', '_locked' => true),
                ),
            ),
            array(
                'id' => 'rule-2',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'test_scoped_action', 0 => 'val-2'), // Allowed
                    array('type' => 'test_scoped_action', 0 => 'val-1'), // Blocked (same scope:value)
                ),
            ),
        );

        $context = new Context(array());
        $engine = new RuleEngine();
        $engine->execute($rules, $context);

        $this->assertSame(array('val-1', 'val-2'), $execution_log);

        unset($GLOBALS['__test_class_based_log']);
    }

    /**
     * Test that Rules::get_action_meta() for class-based actions returns
     * a meta whose type matches the action type string, not the class name.
     *
     * Regression guard: an earlier implementation used static::class as the
     * type, which produced 'MilliRules\Tests\Feature\Fixtures\TestScopedAction'
     * instead of 'test_scoped_action'.
     */
    public function testClassBasedActionMetaHasCorrectType(): void
    {
        \MilliRules\RuleEngine::register_namespace('Actions', 'MilliRules\\Tests\\Feature\\Fixtures');

        $meta = Rules::get_action_meta('test_scoped_action');

        $this->assertNotNull($meta);
        $this->assertSame('test_scoped_action', $meta->get_type());
        $this->assertSame('test_scope', $meta->get_scope());
    }

    /**
     * Test that Rules::get_action_scope() does NOT trigger set_meta().
     *
     * Regression guard: the engine's hot path (build_lock_key) uses
     * get_action_scope(), which must be safe to call during advanced-cache.php
     * boot before WordPress loads. If set_meta() were called, any __() usage
     * inside it would fatal.
     *
     * The TestScopedAction fixture sets $GLOBALS['__test_set_meta_was_called']
     * inside set_meta(). After calling get_action_scope(), the flag must NOT
     * be set.
     */
    public function testGetActionScopeDoesNotTriggerSetMeta(): void
    {
        \MilliRules\RuleEngine::register_namespace('Actions', 'MilliRules\\Tests\\Feature\\Fixtures');

        // Sanity: flag is unset after teardown.
        $this->assertFalse(isset($GLOBALS['__test_set_meta_was_called']));

        // Hot path: resolve scope without triggering set_meta().
        $scope = Rules::get_action_scope('test_scoped_action');

        $this->assertSame('test_scope', $scope);
        $this->assertFalse(
            isset($GLOBALS['__test_set_meta_was_called']),
            'Rules::get_action_scope() must not trigger set_meta() on the action class'
        );
    }

    /**
     * Test that Rules::get_action_meta() DOES call set_meta().
     *
     * Counterpart to the previous test: the consumer-facing full-metadata
     * path should call set_meta() to populate label, description, etc.
     */
    public function testGetActionMetaDoesTriggerSetMeta(): void
    {
        \MilliRules\RuleEngine::register_namespace('Actions', 'MilliRules\\Tests\\Feature\\Fixtures');

        // Sanity: flag is unset after teardown.
        $this->assertFalse(isset($GLOBALS['__test_set_meta_was_called']));

        // Consumer path: full metadata resolution, set_meta() is called.
        $meta = Rules::get_action_meta('test_scoped_action');

        $this->assertNotNull($meta);
        $this->assertTrue(
            isset($GLOBALS['__test_set_meta_was_called']),
            'Rules::get_action_meta() should trigger set_meta() to populate consumer fields'
        );
        $this->assertSame('Test Scoped Action', $meta->get_label());
    }
}
