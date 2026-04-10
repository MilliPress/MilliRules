<?php

namespace MilliRules\Tests\Feature;

use MilliRules\Tests\TestCase;
use MilliRules\Rules;
use MilliRules\RuleEngine;
use MilliRules\Context;
use MilliRules\Packages\BasePackage;

/**
 * @covers \MilliRules\Rules::lock
 * @covers \MilliRules\Packages\BasePackage::register_rule
 * @covers \MilliRules\Packages\BasePackage::unregister_rule
 */
class LockedRuleTest extends TestCase
{
    private BasePackage $package;

    protected function setUp(): void
    {
        parent::setUp();
        $this->clearCustomCallbacks();

        // Create a concrete BasePackage for testing.
        $this->package = new class extends BasePackage {
            public function get_name(): string
            {
                return 'PHP';
            }

            public function get_namespaces(): array
            {
                return array();
            }

            public function is_available(): bool
            {
                return true;
            }
        };
    }

    /**
     * Clear custom conditions and actions using reflection.
     */
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
    }

    /**
     * Test that a locked rule cannot be overwritten by re-registering the same ID.
     */
    public function testLockedRuleCannotBeOverwritten(): void
    {
        $original_rule = array(
            'id'         => 'core-no-cache-post',
            '_locked'    => true,
            'match_type' => 'all',
            'conditions' => array(
                array('type' => 'request_method', 'operator' => '=', 'value' => 'POST'),
            ),
            'actions' => array(
                array('type' => 'set_cache', 'enabled' => false, '_locked' => true),
            ),
        );

        $replacement_rule = array(
            'id'         => 'core-no-cache-post',
            'match_type' => 'all',
            'conditions' => array(), // Permissive — always matches.
            'actions'    => array(
                array('type' => 'set_cache', 'enabled' => true), // Flipped!
            ),
        );

        $metadata = array('required_packages' => array('PHP'), 'type' => 'php', 'order' => 0, 'enabled' => true);

        // Register the locked rule.
        $this->package->register_rule($original_rule, $metadata);

        // Attempt to overwrite it.
        $this->package->register_rule($replacement_rule, $metadata);

        // Assert: Original rule survives.
        $rules = $this->package->get_rules();
        $this->assertCount(1, $rules);
        $this->assertTrue($rules[0]['_locked']);
        $this->assertCount(1, $rules[0]['conditions'], 'Conditions must not be replaced');
        $this->assertSame('POST', $rules[0]['conditions'][0]['value']);
    }

    /**
     * Test that a locked rule cannot be unregistered.
     */
    public function testLockedRuleCannotBeUnregistered(): void
    {
        $rule = array(
            'id'         => 'core-safety-rule',
            '_locked'    => true,
            'match_type' => 'all',
            'conditions' => array(),
            'actions'    => array(array('type' => 'set_cache', 'enabled' => false)),
        );

        $metadata = array('required_packages' => array('PHP'), 'type' => 'php', 'order' => 0, 'enabled' => true);

        $this->package->register_rule($rule, $metadata);

        // Attempt to unregister.
        $result = $this->package->unregister_rule('core-safety-rule');

        // Assert: Unregister refused.
        $this->assertFalse($result);
        $this->assertCount(1, $this->package->get_rules());
    }

    /**
     * Test that an unlocked rule can still be overwritten (no regression).
     */
    public function testUnlockedRuleCanBeOverwritten(): void
    {
        $original = array(
            'id'         => 'user-rule',
            'match_type' => 'all',
            'conditions' => array(),
            'actions'    => array(array('type' => 'set_ttl', 0 => 300)),
        );

        $replacement = array(
            'id'         => 'user-rule',
            'match_type' => 'all',
            'conditions' => array(),
            'actions'    => array(array('type' => 'set_ttl', 0 => 600)),
        );

        $metadata = array('required_packages' => array('PHP'), 'type' => 'php', 'order' => 10, 'enabled' => true);

        $this->package->register_rule($original, $metadata);
        $this->package->register_rule($replacement, $metadata);

        $rules = $this->package->get_rules();
        $this->assertCount(1, $rules);
        $this->assertSame(600, $rules[0]['actions'][0][0]);
    }

    /**
     * Test that an unlocked rule can be unregistered (no regression).
     */
    public function testUnlockedRuleCanBeUnregistered(): void
    {
        $rule = array(
            'id'         => 'user-rule',
            'match_type' => 'all',
            'conditions' => array(),
            'actions'    => array(array('type' => 'set_ttl', 0 => 300)),
        );

        $metadata = array('required_packages' => array('PHP'), 'type' => 'php', 'order' => 10, 'enabled' => true);

        $this->package->register_rule($rule, $metadata);

        $result = $this->package->unregister_rule('user-rule');
        $this->assertTrue($result);
        $this->assertCount(0, $this->package->get_rules());
    }

    /**
     * Test that a locked rule's actions still execute and lock action types.
     *
     * Combines rule-level lock (can't overwrite) with action-level lock (can't re-execute).
     */
    public function testLockedRuleWithLockedActionsExecutesCorrectly(): void
    {
        $execution_log = array();

        Rules::register_action('set_cache', function ($args, $context) use (&$execution_log) {
            $execution_log[] = $args['_rule_id'] ?? 'unknown';
            $context->set('cache.enabled', $args['enabled'] ?? true);
        });

        $rules = array(
            array(
                'id'         => 'core-no-cache-post',
                '_locked'    => true,
                'match_type' => 'all',
                'conditions' => array(),
                'actions'    => array(
                    array('type' => 'set_cache', '_rule_id' => 'core-no-cache-post', 'enabled' => false, '_locked' => true),
                ),
            ),
            array(
                'id'         => 'user-enable-cache',
                'match_type' => 'all',
                'conditions' => array(),
                'actions'    => array(
                    array('type' => 'set_cache', '_rule_id' => 'user-enable-cache', 'enabled' => true),
                ),
            ),
        );

        $context = new Context(array());
        $engine  = new RuleEngine();
        $engine->execute($rules, $context);

        // Core rule executed; user rule's set_cache was blocked by action lock.
        $this->assertSame(array('core-no-cache-post'), $execution_log);
        $this->assertFalse($context->get('cache.enabled'));
    }

    /**
     * Test attack vector: condition swap on a locked rule.
     */
    public function testConditionSwapBlockedOnLockedRule(): void
    {
        $locked_rule = array(
            'id'         => 'no-cache-admin',
            '_locked'    => true,
            'match_type' => 'all',
            'conditions' => array(
                array('type' => 'is_admin', 'operator' => '=', 'value' => true),
            ),
            'actions' => array(
                array('type' => 'set_cache', 'enabled' => false),
            ),
        );

        $swapped_rule = array(
            'id'         => 'no-cache-admin',
            'match_type' => 'all',
            'conditions' => array(), // Always matches — removes the admin check.
            'actions'    => array(
                array('type' => 'set_cache', 'enabled' => true), // Flipped action.
            ),
        );

        $metadata = array('required_packages' => array('PHP'), 'type' => 'php', 'order' => 0, 'enabled' => true);

        $this->package->register_rule($locked_rule, $metadata);
        $this->package->register_rule($swapped_rule, $metadata);

        $rules = $this->package->get_rules();
        $this->assertCount(1, $rules);

        // Original conditions preserved.
        $this->assertSame('is_admin', $rules[0]['conditions'][0]['type']);
        // Original action preserved.
        $this->assertFalse($rules[0]['actions'][0]['enabled']);
    }

    /**
     * Test that the _locked flag is set correctly by the builder.
     */
    public function testBuilderSetsLockedFlag(): void
    {
        $reflection = new \ReflectionClass(Rules::class);
        $ruleProperty = $reflection->getProperty('rule');
        $ruleProperty->setAccessible(true);

        $builder = Rules::create('test-locked-builder');
        $builder->lock();

        $rule = $ruleProperty->getValue($builder);
        $this->assertTrue($rule['_locked']);
    }
}
