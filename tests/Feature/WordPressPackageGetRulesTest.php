<?php

namespace MilliRules\Tests\Feature;

use MilliRules\Tests\TestCase;
use MilliRules\MilliRules;
use MilliRules\Rules;
use MilliRules\Context;
use MilliRules\Packages\PackageManager;
use MilliRules\Packages\PHP\Package as PhpPackage;
use MilliRules\Packages\WordPress\Package as WordPressPackage;

/**
 * @covers \MilliRules\Packages\WordPress\Package::get_rules
 * @covers \MilliRules\Packages\WordPress\Package::get_rules_by_hook
 * @covers \MilliRules\MilliRules::execute_rules
 */
class WordPressPackageGetRulesTest extends TestCase
{
    private WordPressPackage $wpPackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpPackage = new class () extends WordPressPackage {
            public function is_available(): bool
            {
                return true;
            }
        };

        WordPressPackage::set_hook_callback(function ($hook, $callback, $priority): void {
        });

        PackageManager::register_package(new PhpPackage());
        PackageManager::register_package($this->wpPackage);
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

    public function testGetRulesReturnsFlatList(): void
    {
        $rule = array(
            'id'         => 'wp-only-flat',
            'match_type' => 'all',
            'conditions' => array(),
            'actions'    => array(),
        );
        $this->wpPackage->register_rule($rule, array(
            'required_packages' => array('WP'),
            'hook'              => 'wp',
            'hook_priority'     => 10,
            'order'             => 10,
            'enabled'           => true,
        ));

        $rules = $this->wpPackage->get_rules();

        $this->assertCount(1, $rules);
        $this->assertSame('wp-only-flat', $rules[0]['id']);
        $this->assertSame(array_keys($rules), array(0), 'get_rules() must return a numerically-indexed list');
    }

    public function testGetRulesByHookReturnsGroupedShape(): void
    {
        $this->wpPackage->register_rule(
            array('id' => 'r-wp', 'match_type' => 'all', 'conditions' => array(), 'actions' => array()),
            array('required_packages' => array('WP'), 'hook' => 'wp', 'hook_priority' => 10, 'order' => 10, 'enabled' => true)
        );
        $this->wpPackage->register_rule(
            array('id' => 'r-init', 'match_type' => 'all', 'conditions' => array(), 'actions' => array()),
            array('required_packages' => array('WP'), 'hook' => 'init', 'hook_priority' => 5, 'order' => 10, 'enabled' => true)
        );

        $byHook = $this->wpPackage->get_rules_by_hook();

        $this->assertArrayHasKey('wp', $byHook);
        $this->assertArrayHasKey('init', $byHook);
        $this->assertSame('r-wp', $byHook['wp'][0]['id']);
        $this->assertSame('r-init', $byHook['init'][0]['id']);
    }

    public function testWordPressOnlyRuleExecutesViaExecuteRules(): void
    {
        $log = array();
        Rules::register_action('wp_only_action', function ($args, $context) use (&$log): void {
            $log[] = $args['_rule_id'] ?? 'unknown';
        });

        PackageManager::register_rule(
            array(
                'id'         => 'wp-only-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions'    => array(
                    array('type' => 'wp_only_action', '_rule_id' => 'wp-only-rule'),
                ),
            ),
            array(
                'required_packages' => array('WP'),
                'hook'              => 'wp',
                'hook_priority'     => 10,
                'order'             => 10,
                'enabled'           => true,
            )
        );

        $result = MilliRules::execute_rules(null, new Context(array()));

        $this->assertSame(array('wp-only-rule'), $log, 'WP-only rule must execute via execute_rules()');
        $this->assertSame(1, $result['rules_matched']);
        $this->assertSame(1, $result['actions_executed']);
    }

    public function testDualPackageRuleExecutesExactlyOnce(): void
    {
        $log = array();
        Rules::register_action('dual_action', function ($args, $context) use (&$log): void {
            $log[] = $args['_rule_id'] ?? 'unknown';
        });

        PackageManager::register_rule(
            array(
                'id'         => 'dual-rule',
                'match_type' => 'all',
                'conditions' => array(),
                'actions'    => array(
                    array('type' => 'dual_action', '_rule_id' => 'dual-rule'),
                ),
            ),
            array(
                'required_packages' => array('PHP', 'WP'),
                'hook'              => 'wp',
                'hook_priority'     => 10,
                'order'             => 10,
                'enabled'           => true,
            )
        );

        // Sanity: the rule lives in both packages' flat lists.
        $this->assertCount(1, PackageManager::get_package('PHP')->get_rules());
        $this->assertCount(1, PackageManager::get_package('WP')->get_rules());

        $result = MilliRules::execute_rules(null, new Context(array()));

        $this->assertSame(array('dual-rule'), $log, 'Dual-package rule must execute exactly once');
        $this->assertSame(1, $result['rules_matched']);
        $this->assertSame(1, $result['actions_executed']);
    }
}
