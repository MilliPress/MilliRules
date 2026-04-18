<?php

namespace MilliRules\Tests\Feature;

use MilliRules\Tests\TestCase;
use MilliRules\Rules;
use MilliRules\RuleEngine;
use MilliRules\Context;

/**
 * @covers \MilliRules\RuleEngine::execute_continue
 */
class ExecuteContinueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearCustomCallbacks();
    }

    private function clearCustomCallbacks(): void
    {
        $reflection = new \ReflectionClass(Rules::class);

        $conditionsProperty = $reflection->getProperty('custom_conditions');
        $conditionsProperty->setAccessible(true);
        $conditionsProperty->setValue(array());

        $actionsProperty = $reflection->getProperty('custom_actions');
        $actionsProperty->setAccessible(true);
        $actionsProperty->setValue(array());

        $metasProperty = $reflection->getProperty('action_metas');
        $metasProperty->setAccessible(true);
        $metasProperty->setValue(array());

        $cacheProperty = $reflection->getProperty('meta_cache');
        $cacheProperty->setAccessible(true);
        $cacheProperty->setValue(array());

        $scopeCacheProperty = $reflection->getProperty('scope_cache');
        $scopeCacheProperty->setAccessible(true);
        $scopeCacheProperty->setValue(array());
    }

    /**
     * Test that execute_continue() preserves locked actions from prior execute().
     */
    public function testLocksCarryAcrossContinue(): void
    {
        $execution_count = array('first' => 0, 'second' => 0);

        Rules::register_action('track_action', function ($args, $context) use (&$execution_count) {
            $key = $args['_key'] ?? 'unknown';
            $execution_count[$key]++;
        });

        // First batch: sets a lock on track_action.
        $batch1 = array(
            array(
                'id' => 'locking-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'track_action', '_key' => 'first', '_locked' => true),
                ),
            ),
        );

        // Second batch: tries to use the same action type (blocked by lock).
        $batch2 = array(
            array(
                'id' => 'blocked-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'track_action', '_key' => 'second'),
                ),
            ),
        );

        $engine = new RuleEngine();
        $context = new Context(array());

        $engine->execute($batch1, $context);
        $this->assertSame(1, $execution_count['first']);
        $this->assertSame(0, $execution_count['second']);

        // execute_continue preserves the lock from batch1.
        $engine->execute_continue($batch2, $context);
        $this->assertSame(1, $execution_count['first']);
        $this->assertSame(0, $execution_count['second'], 'Lock from execute() must carry into execute_continue()');
    }

    /**
     * Test that execute() resets locks even after execute_continue().
     */
    public function testExecuteResetsLocksAfterContinue(): void
    {
        $execution_count = array('first' => 0, 'second' => 0);

        Rules::register_action('track_action', function ($args, $context) use (&$execution_count) {
            $key = $args['_key'] ?? 'unknown';
            $execution_count[$key]++;
        });

        $locking_rules = array(
            array(
                'id' => 'locking-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'track_action', '_key' => 'first', '_locked' => true),
                ),
            ),
        );

        $blocked_rules = array(
            array(
                'id' => 'blocked-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'track_action', '_key' => 'second'),
                ),
            ),
        );

        $engine = new RuleEngine();
        $context = new Context(array());

        // Lock via execute + continue.
        $engine->execute($locking_rules, $context);
        $engine->execute_continue($blocked_rules, $context);
        $this->assertSame(0, $execution_count['second']);

        // Fresh execute() resets locks — second should now run.
        $engine->execute($blocked_rules, $context);
        $this->assertSame(1, $execution_count['second'], 'execute() must reset locks');
    }

    /**
     * Test that rule metadata is exposed on context during action execution.
     */
    public function testRuleMetadataOnContext(): void
    {
        $captured = array();

        Rules::register_action('capture_context', function ($args, $context) use (&$captured) {
            $captured['id'] = $context->get('rule.id');
            $captured['order'] = $context->get('rule.order');
        });

        $rules = array(
            array(
                'id' => 'test-rule-42',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'capture_context'),
                ),
                '_metadata' => array(
                    'order' => 42,
                    'required_packages' => array(),
                ),
            ),
        );

        $engine = new RuleEngine();
        $engine->execute($rules, new Context(array()));

        $this->assertSame('test-rule-42', $captured['id']);
        $this->assertSame(42, $captured['order']);
    }

    /**
     * Test that rule metadata defaults when _metadata is absent.
     */
    public function testRuleMetadataDefaults(): void
    {
        $captured = array();

        Rules::register_action('capture_context', function ($args, $context) use (&$captured) {
            $captured['id'] = $context->get('rule.id');
            $captured['order'] = $context->get('rule.order');
        });

        $rules = array(
            array(
                'id' => 'no-metadata-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'capture_context'),
                ),
                // No _metadata key.
            ),
        );

        $engine = new RuleEngine();
        $engine->execute($rules, new Context(array()));

        $this->assertSame('no-metadata-rule', $captured['id']);
        $this->assertSame(10, $captured['order'], 'Order should default to 10 when _metadata is absent');
    }

    /**
     * Test that rule metadata updates per rule (not stale from prior rule).
     */
    public function testRuleMetadataUpdatesPerRule(): void
    {
        $orders = array();

        Rules::register_action('capture_order', function ($args, $context) use (&$orders) {
            $orders[] = $context->get('rule.order');
        });

        $rules = array(
            array(
                'id' => 'rule-a',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'capture_order'),
                ),
                '_metadata' => array(
                    'order' => 5,
                    'required_packages' => array(),
                ),
            ),
            array(
                'id' => 'rule-b',
                'match_type' => 'all',
                'conditions' => array(),
                'actions' => array(
                    array('type' => 'capture_order'),
                ),
                '_metadata' => array(
                    'order' => 99,
                    'required_packages' => array(),
                ),
            ),
        );

        $engine = new RuleEngine();
        $engine->execute($rules, new Context(array()));

        $this->assertSame(array(5, 99), $orders, 'Each rule should set its own order on context');
    }
}
