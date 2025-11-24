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
}
