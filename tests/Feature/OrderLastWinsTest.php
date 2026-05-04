<?php

namespace MilliRules\Tests\Feature;

use MilliRules\Tests\TestCase;
use MilliRules\Rules;
use MilliRules\RuleEngine;
use MilliRules\Context;

/**
 * Regression guard: when no locks are set, the LAST-executed rule wins.
 *
 * This pins the contract documented on Rules::create():
 * "When multiple rules modify the same values (e.g., TTL),
 *  the LAST executed rule (highest order) wins."
 *
 * Downstream consumers (e.g., MilliCache) rely on this for unlocked
 * order-based composition, so the unlocked path needs its own coverage —
 * the existing locked-path tests in LockedActionTest do not exercise it.
 *
 * @covers \MilliRules\RuleEngine::execute_actions
 */
class OrderLastWinsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->clearCustomCallbacks();
    }

    /**
     * Mirror of the helper in LockedActionTest — kept local to avoid
     * coupling the two files.
     */
    private function clearCustomCallbacks(): void
    {
        $reflection = new \ReflectionClass(Rules::class);

        foreach (
            array(
                'custom_conditions',
                'custom_actions',
                'action_metas',
                'meta_cache',
                'scope_cache',
                'condition_metas',
                'condition_meta_cache',
            ) as $prop
        ) {
            $property = $reflection->getProperty($prop);
            $property->setAccessible(true);
            $property->setValue(array());
        }
    }

    /**
     * Two unlocked rules at orders 5 and 10 both call the same stateful
     * action. The order-10 rule must win because it executes last.
     */
    public function testHigherOrderUnlockedRuleWinsForSharedState(): void
    {
        $execution_log = array();

        Rules::register_action('set_value', function ($args, $context) use (&$execution_log) {
            $value = $args[0] ?? null;
            $execution_log[] = $value;
            $context->set('value', $value);
        });

        // Rules are passed in execution order (matching their order field),
        // since the engine itself executes in array order — sorting by
        // _metadata.order is the registry's job (BasePackage::sort_rules_by_order).
        $rules = array(
            array(
                'id'         => 'rule-low',
                'match_type' => 'all',
                'conditions' => array(),
                '_metadata'  => array('order' => 5),
                'actions'    => array(
                    array('type' => 'set_value', 0 => 'low'),
                ),
            ),
            array(
                'id'         => 'rule-high',
                'match_type' => 'all',
                'conditions' => array(),
                '_metadata'  => array('order' => 10),
                'actions'    => array(
                    array('type' => 'set_value', 0 => 'high'),
                ),
            ),
        );

        $context = new Context(array());
        $engine = new RuleEngine();
        $result = $engine->execute($rules, $context);

        // Both rules ran (no locks); the order-10 value persists.
        $this->assertSame(array('low', 'high'), $execution_log);
        $this->assertSame('high', $context->get('value'));
        $this->assertSame(2, $result['rules_matched']);
        $this->assertSame(2, $result['actions_executed']);
    }
}
