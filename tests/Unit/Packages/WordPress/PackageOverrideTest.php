<?php

namespace MilliRules\Tests\Unit\Packages\WordPress;

use MilliRules\Tests\TestCase;
use MilliRules\Packages\PackageManager;
use MilliRules\Packages\WordPress\Package;

/**
 * Override tracking for the WordPress package.
 *
 * WordPress\Package overrides register_rule() with hook-aware storage, so it
 * marks overrides independently of BasePackage. Rules are registered directly
 * on the package: without WordPress loaded, is_available() is false, so hook
 * registration is deferred but rule storage still happens.
 */
class PackageOverrideTest extends TestCase
{
    private function makePackage(): Package
    {
        $package = new Package();
        PackageManager::register_package($package);
        return $package;
    }

    public function testReplacingARuleMarksItAsOverridden(): void
    {
        $package = $this->makePackage();

        $package->register_rule(['id' => 'rule1'], ['enabled' => true]);
        $this->assertSame([], PackageManager::get_overridden_rule_ids());

        $package->register_rule(['id' => 'rule1'], ['enabled' => true]);

        $this->assertSame(['rule1'], PackageManager::get_overridden_rule_ids());
    }

    public function testDisabledRuleDoesNotOverrideExistingRule(): void
    {
        $package = $this->makePackage();

        $package->register_rule(['id' => 'rule1', 'v' => 'original'], ['enabled' => true]);

        // A disabled rule is skipped entirely, so it replaces nothing.
        $package->register_rule(['id' => 'rule1', 'v' => 'disabled'], ['enabled' => false]);

        $rules = $package->get_rules();
        $this->assertCount(1, $rules);
        $this->assertSame('original', $rules[0]['v']);
        $this->assertSame([], PackageManager::get_overridden_rule_ids());
    }

    public function testLockedRuleIsNotOverridden(): void
    {
        $package = $this->makePackage();

        $package->register_rule(['id' => 'rule1', '_locked' => true], ['enabled' => true]);
        $package->register_rule(['id' => 'rule1'], ['enabled' => true]);

        $this->assertSame([], PackageManager::get_overridden_rule_ids());
    }
}
