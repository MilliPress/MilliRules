<?php

namespace MilliRules\Tests\Feature;

use MilliRules\Tests\TestCase;
use MilliRules\Rules;
use MilliRules\Packages\PackageManager;
use MilliRules\Packages\PHP\Package as PhpPackage;
use MilliRules\Packages\WordPress\Package as WordPressPackage;

/**
 * Parity: each fluent surface must produce the same registry entry as the
 * equivalent array passed to Rules::register_rule().
 *
 * Pins the contract that consumers loading rules from settings storage can
 * use the array API as a complete substitute for the fluent builder.
 *
 * @covers \MilliRules\Rules::register_rule
 * @covers \MilliRules\Rules::register
 */
class RegisterRuleParityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WordPressPackage::set_hook_callback(function ($hook, $callback, $priority): void {
        });

        $wpPackage = new class () extends WordPressPackage {
            public function is_available(): bool
            {
                return true;
            }
        };

        PackageManager::register_package(new PhpPackage());
        PackageManager::register_package($wpPackage);
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

    /**
     * Find a single rule by ID in the global registry.
     *
     * @return array<string, mixed>
     */
    private function findRule(string $id): array
    {
        foreach (PackageManager::get_all_rules() as $rule) {
            if (($rule['id'] ?? null) === $id) {
                return $rule;
            }
        }
        $this->fail("Rule '{$id}' not registered");
    }

    /**
     * Assert two registered rules are identical aside from the id-bound fields.
     *
     * @param array<string, mixed> $fluent
     * @param array<string, mixed> $array
     */
    private function assertParity(array $fluent, array $array): void
    {
        // Normalize ids before comparing — they're intentionally different.
        unset($fluent['id'], $array['id']);

        // _package may differ if rules land in different packages, but for
        // these tests we register with matching shape so it should match.
        $this->assertSame($fluent, $array);
    }

    public function testEmptyRuleParity(): void
    {
        Rules::create('fluent-empty', 'php')->register();
        Rules::register_rule(array('id' => 'array-empty'), 'php');

        $this->assertParity($this->findRule('fluent-empty'), $this->findRule('array-empty'));
    }

    public function testTitleParity(): void
    {
        Rules::create('fluent-title', 'php')->title('My Rule')->register();
        Rules::register_rule(array(
            'id'    => 'array-title',
            'title' => 'My Rule',
        ), 'php');

        $this->assertParity($this->findRule('fluent-title'), $this->findRule('array-title'));
    }

    public function testOrderParity(): void
    {
        Rules::create('fluent-order', 'php')->order(42)->register();
        Rules::register_rule(array(
            'id'    => 'array-order',
            'order' => 42,
        ), 'php');

        $this->assertParity($this->findRule('fluent-order'), $this->findRule('array-order'));
    }

    public function testEnabledFalseParity(): void
    {
        Rules::create('fluent-disabled', 'php')->enabled(false)->register();
        Rules::register_rule(array(
            'id'      => 'array-disabled',
            'enabled' => false,
        ), 'php');

        $this->assertParity($this->findRule('fluent-disabled'), $this->findRule('array-disabled'));
    }

    public function testRuleLockParity(): void
    {
        Rules::create('fluent-locked', 'php')->lock()->register();
        Rules::register_rule(array(
            'id'     => 'array-locked',
            'locked' => true,
        ), 'php');

        $this->assertParity($this->findRule('fluent-locked'), $this->findRule('array-locked'));
    }

    public function testHookParity(): void
    {
        Rules::create('fluent-hook')->on('init', 5)->register();
        Rules::register_rule(array(
            'id'       => 'array-hook',
            'hook'     => 'init',
            'priority' => 5,
        ));

        $this->assertParity($this->findRule('fluent-hook'), $this->findRule('array-hook'));
    }

    public function testWhenAllParity(): void
    {
        $conditions = array(
            array('type' => 'request_method', 'operator' => '=', 'value' => 'GET'),
        );

        Rules::create('fluent-when-all')->when_all($conditions)->register();
        Rules::register_rule(array(
            'id'         => 'array-when-all',
            'match_type' => 'all',
            'conditions' => $conditions,
        ));

        $this->assertParity($this->findRule('fluent-when-all'), $this->findRule('array-when-all'));
    }

    public function testWhenAnyParity(): void
    {
        $conditions = array(
            array('type' => 'request_method', 'operator' => '=', 'value' => 'POST'),
            array('type' => 'request_method', 'operator' => '=', 'value' => 'PUT'),
        );

        Rules::create('fluent-when-any')->when_any($conditions)->register();
        Rules::register_rule(array(
            'id'         => 'array-when-any',
            'match_type' => 'any',
            'conditions' => $conditions,
        ));

        $this->assertParity($this->findRule('fluent-when-any'), $this->findRule('array-when-any'));
    }

    public function testWhenNoneParity(): void
    {
        $conditions = array(
            array('type' => 'request_method', 'operator' => '=', 'value' => 'DELETE'),
        );

        Rules::create('fluent-when-none')->when_none($conditions)->register();
        Rules::register_rule(array(
            'id'         => 'array-when-none',
            'match_type' => 'none',
            'conditions' => $conditions,
        ));

        $this->assertParity($this->findRule('fluent-when-none'), $this->findRule('array-when-none'));
    }

    public function testThenArrayParity(): void
    {
        Rules::register_action('record_run', function (): void {
        });

        $actions = array(
            array('type' => 'record_run', 0 => 300),
        );

        Rules::create('fluent-then', 'php')->then($actions)->register();
        Rules::register_rule(array(
            'id'      => 'array-then',
            'actions' => $actions,
        ), 'php');

        $this->assertParity($this->findRule('fluent-then'), $this->findRule('array-then'));
    }

    public function testActionLockParity(): void
    {
        Rules::register_action('record_run', function (): void {
        });

        // Fluent: ActionBuilder->lock() sets _locked on the last action.
        Rules::create('fluent-action-lock', 'php')
            ->then()
                ->custom('record_run')->lock()
            ->register();

        // Array form: public 'locked' is translated to internal '_locked'.
        Rules::register_rule(array(
            'id'      => 'array-action-lock',
            'actions' => array(
                array('type' => 'record_run', 'locked' => true),
            ),
        ), 'php');

        $this->assertParity($this->findRule('fluent-action-lock'), $this->findRule('array-action-lock'));
    }

    public function testExplicitTypeParity(): void
    {
        Rules::create('fluent-explicit-type', 'wp')->register();
        Rules::register_rule(array('id' => 'array-explicit-type'), 'wp');

        $this->assertParity($this->findRule('fluent-explicit-type'), $this->findRule('array-explicit-type'));
    }

    public function testFullCompositionParity(): void
    {
        Rules::register_action('record_run', function (): void {
        });

        $conditions = array(
            array('type' => 'request_method', 'operator' => '=', 'value' => 'GET'),
        );
        $actions = array(
            array('type' => 'record_run', '_locked' => true),
        );

        Rules::create('fluent-full')
            ->title('Full rule')
            ->order(20)
            ->enabled(true)
            ->lock()
            ->on('init', 5)
            ->when_all($conditions)
            ->then($actions)
            ->register();

        Rules::register_rule(array(
            'id'         => 'array-full',
            'title'      => 'Full rule',
            'order'      => 20,
            'enabled'    => true,
            'locked'     => true,
            'hook'       => 'init',
            'priority'   => 5,
            'match_type' => 'all',
            'conditions' => $conditions,
            'actions'    => array(
                array('type' => 'record_run', 'locked' => true),
            ),
        ));

        $this->assertParity($this->findRule('fluent-full'), $this->findRule('array-full'));
    }
}
