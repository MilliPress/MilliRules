<?php

namespace MilliRules\Tests\Feature;

use MilliRules\Packages\PackageManager;
use MilliRules\Packages\PHP\Package as PhpPackage;
use MilliRules\Rules;
use MilliRules\Tests\TestCase;

/**
 * Order decides who wins a same-id collision, so the outcome does not depend
 * on which file happened to load first. A locked rule wins regardless.
 */
class OrderPrecedenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PackageManager::register_package(new PhpPackage());
        PackageManager::load_packages();

        Rules::register_action('noop', function ($args, $context): void {
        });
    }

    public function testHigherOrderReplaces(): void
    {
        $this->register('claimed', 10, 'first');
        $this->register('claimed', 20, 'second');

        $this->assertSame('second', $this->title_of('claimed'));
    }

    public function testEqualOrderReplaces(): void
    {
        // A stored rule must still be able to take over a built-in that was
        // registered with the same number.
        $this->register('claimed', 10, 'first');
        $this->register('claimed', 10, 'second');

        $this->assertSame('second', $this->title_of('claimed'));
    }

    public function testLowerOrderIsDiscarded(): void
    {
        $this->register('claimed', 20, 'first');
        $this->register('claimed', 10, 'second');

        $this->assertSame('first', $this->title_of('claimed'));
    }

    public function testLockedRuleSurvivesAHigherOrder(): void
    {
        Rules::create('locked-rule', 'php')
            ->title('original')
            ->order(10)
            ->lock()
            ->set_conditions(array())
            ->set_actions(array(array('type' => 'noop')))
            ->register();

        $this->register('locked-rule', 999, 'attempt');

        $this->assertSame('original', $this->title_of('locked-rule'));
    }

    public function testTheDiscardedOrderIsRecorded(): void
    {
        // The registry keeps only the winner, so the loser's order is the one
        // piece of evidence a caller has that it is up against something.
        $this->register('claimed', 20, 'first');
        $this->register('claimed', 10, 'second');

        $this->assertSame(array(10), PackageManager::discarded_orders('claimed'));
    }

    public function testTheSameDiscardedOrderIsRecordedOnce(): void
    {
        $this->register('claimed', 20, 'first');
        $this->register('claimed', 10, 'second');
        $this->register('claimed', 10, 'third');

        $this->assertSame(array(10), PackageManager::discarded_orders('claimed'));
    }

    public function testNothingIsRecordedWhenTheRuleWins(): void
    {
        $this->register('claimed', 10, 'first');
        $this->register('claimed', 20, 'second');

        $this->assertSame(array(), PackageManager::discarded_orders('claimed'));
    }

    private function register(string $id, int $order, string $title): void
    {
        Rules::create($id, 'php')
            ->title($title)
            ->order($order)
            ->set_conditions(array())
            ->set_actions(array(array('type' => 'noop')))
            ->register();
    }

    private function title_of(string $id): string
    {
        foreach (PackageManager::get_all_rules() as $rule) {
            if (($rule['id'] ?? '') === $id) {
                return (string) ($rule['title'] ?? '');
            }
        }

        return '';
    }
}
