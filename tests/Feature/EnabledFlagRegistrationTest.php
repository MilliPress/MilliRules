<?php

namespace MilliRules\Tests\Feature;

use MilliRules\Tests\TestCase;
use MilliRules\MilliRules;
use MilliRules\Rules;
use MilliRules\Context;
use MilliRules\Packages\PackageManager;
use MilliRules\Packages\PHP\Package as PhpPackage;

/**
 * Regression: ->enabled(false) must be honored end-to-end.
 *
 * The fluent registration pipeline strips the top-level `enabled` key and
 * stores the flag inside `_metadata` (Rules::complete_registration). The
 * RuleEngine consumed the now-missing top-level key, so disabled rules
 * silently still ran for non-WordPress packages.
 *
 * This test pins the contract by going through the real producer
 * (Rules::create()->enabled(false)->register()) and the real entry point
 * (MilliRules::execute_rules()), so any future move of the flag breaks it.
 *
 * @covers \MilliRules\RuleEngine::execute_rule
 * @covers \MilliRules\Rules::enabled
 * @covers \MilliRules\Rules::complete_registration
 */
class EnabledFlagRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PackageManager::register_package(new PhpPackage());
        PackageManager::load_packages();

        $this->clearRulesRegistry();
    }

    protected function tearDown(): void
    {
        $this->clearRulesRegistry();
        parent::tearDown();
    }

    private function clearRulesRegistry(): void
    {
        $reflection = new \ReflectionClass(Rules::class);
        foreach (
            ['custom_conditions', 'custom_actions', 'action_metas', 'meta_cache',
             'scope_cache', 'condition_metas', 'condition_meta_cache'] as $prop
        ) {
            if ($reflection->hasProperty($prop)) {
                $p = $reflection->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(array());
            }
        }
    }

    public function testDisabledPhpRuleDoesNotExecute(): void
    {
        $log = array();
        Rules::register_action('record_run', function ($args, $context) use (&$log): void {
            $log[] = $args['_rule_id'] ?? 'unknown';
        });

        Rules::create('disabled-php-rule', 'php')
            ->set_conditions(array())
            ->set_actions(array(
                array('type' => 'record_run', '_rule_id' => 'disabled-php-rule'),
            ))
            ->enabled(false)
            ->register();

        $result = MilliRules::execute_rules(array('PHP'), new Context(array()));

        $this->assertSame(array(), $log, 'Disabled rule must not execute its actions');
        $this->assertSame(0, $result['rules_matched']);
        $this->assertSame(0, $result['actions_executed']);
    }

    public function testEnabledPhpRuleStillExecutes(): void
    {
        $log = array();
        Rules::register_action('record_run', function ($args, $context) use (&$log): void {
            $log[] = $args['_rule_id'] ?? 'unknown';
        });

        Rules::create('enabled-php-rule', 'php')
            ->set_conditions(array())
            ->set_actions(array(
                array('type' => 'record_run', '_rule_id' => 'enabled-php-rule'),
            ))
            ->register();

        $result = MilliRules::execute_rules(array('PHP'), new Context(array()));

        $this->assertSame(array('enabled-php-rule'), $log);
        $this->assertSame(1, $result['rules_matched']);
        $this->assertSame(1, $result['actions_executed']);
    }
}
