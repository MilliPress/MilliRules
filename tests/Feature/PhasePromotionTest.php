<?php

namespace MilliRules\Tests\Feature;

use MilliRules\Context;
use MilliRules\MilliRules;
use MilliRules\Packages\PackageManager;
use MilliRules\Packages\PHP\Package as PhpPackage;
use MilliRules\Rules;
use MilliRules\Tests\TestCase;

/**
 * A php-typed rule registered after the PHP phase has run can never execute,
 * so it is moved to the WordPress phase instead of failing silently.
 */
class PhasePromotionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        PackageManager::register_package(new PhpPackage());
        PackageManager::load_packages();

        Rules::register_action('noop', function ($args, $context): void {
        });
    }

    public function testStaysPhpBeforeThePhaseHasRun(): void
    {
        Rules::create('before-phase', 'php')
            ->set_conditions(array())
            ->set_actions(array(array('type' => 'noop')))
            ->register();

        $this->assertSame('php', $this->metadata_type('before-phase'));
    }

    public function testMovesToWordPressPhaseAfterThePhaseHasRun(): void
    {
        MilliRules::execute_rules(array('PHP'), new Context(array()));

        Rules::create('after-phase', 'php')
            ->set_conditions(array())
            ->set_actions(array(array('type' => 'noop')))
            ->register();

        $this->assertSame('wp', $this->metadata_type('after-phase'));
    }

    public function testAFilterThatNamesNothingLoadedMarksNothing(): void
    {
        // The validated list is empty here, which must not read as "no filter
        // given": every package would count as run, and every php rule
        // registered afterwards would be promoted for a phase that never went.
        MilliRules::execute_rules(array('NOT-LOADED'), new Context(array()));

        $this->assertFalse(PackageManager::has_executed('PHP'));
    }

    /**
     * The phase a registered rule ended up in. A promoted rule waits for the
     * WordPress package, which this test does not load, so look there too.
     */
    private function metadata_type(string $id): string
    {
        foreach (PackageManager::get_all_rules() as $rule) {
            if (($rule['id'] ?? '') === $id) {
                return (string) ($rule['_metadata']['type'] ?? '');
            }
        }

        $reflection = new \ReflectionClass(PackageManager::class);
        $property   = $reflection->getProperty('pending_rules');
        $property->setAccessible(true);

        foreach ((array) $property->getValue() as $pending) {
            if (($pending['rule']['id'] ?? '') === $id) {
                return (string) ($pending['metadata']['type'] ?? '');
            }
        }

        return '';
    }
}
