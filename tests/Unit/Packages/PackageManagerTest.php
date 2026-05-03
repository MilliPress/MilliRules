<?php

namespace MilliRules\Tests\Unit\Packages;

use MilliRules\Tests\TestCase;
use MilliRules\Packages\PackageManager;
use MilliRules\Packages\PackageInterface;
use MilliRules\Context;

/**
 * Comprehensive tests for PackageManager class
 *
 * Tests package registration, dependency resolution, circular dependency detection,
 * namespace mapping with caching, and context building
 */
class PackageManagerTest extends TestCase
{
    /**
     * Create a mock package for testing
     */
    private function createMockPackage(
        string $name,
        array $namespaces = [],
        array $requiredPackages = [],
        bool $isAvailable = true
    ): PackageInterface {
        return new class ($name, $namespaces, $requiredPackages, $isAvailable) implements PackageInterface {
            private string $name;
            private array $namespaces;
            private array $requiredPackages;
            private bool $isAvailable;
            private array $rules = [];
            private bool $namespacesRegistered = false;

            public function __construct(string $name, array $namespaces, array $requiredPackages, bool $isAvailable)
            {
                $this->name = $name;
                $this->namespaces = $namespaces;
                $this->requiredPackages = $requiredPackages;
                $this->isAvailable = $isAvailable;
            }

            public function get_name(): string
            {
                return $this->name;
            }

            public function get_namespaces(): array
            {
                return $this->namespaces;
            }

            public function is_available(): bool
            {
                return $this->isAvailable;
            }

            public function get_required_packages(): array
            {
                return $this->requiredPackages;
            }

            public function register_namespaces(): void
            {
                $this->namespacesRegistered = true;
            }

            public function build_context(): array
            {
                return [$this->name => 'context_' . $this->name];
            }

            public function register_context_providers(Context $context): void
            {
                $name = $this->name;
                $context->register_provider($name, function () use ($name) {
                    return [$name => 'context_' . $name];
                });
            }

            public function get_placeholder_resolver(Context $context)
            {
                return null;
            }

            public function register_rule(array $rule, array $metadata): void
            {
                $this->rules[] = array_merge($rule, ['_metadata' => $metadata]);
            }

            public function execute_rules(array $rules, Context $context): array
            {
                return ['executed' => count($rules)];
            }

            public function get_rules(): array
            {
                return $this->rules;
            }

            public function clear(): void
            {
                $this->rules = [];
                $this->namespacesRegistered = false;
            }

            public function resolve_class_name(string $type, string $category): ?string
            {
                return null;
            }

            public function unregister_rule(string $rule_id): bool
            {
                foreach ($this->rules as $index => $rule) {
                    if (($rule['id'] ?? null) === $rule_id) {
                        array_splice($this->rules, $index, 1);
                        return true;
                    }
                }
                return false;
            }

            public function wasNamespacesRegisteredCalled(): bool
            {
                return $this->namespacesRegistered;
            }
        };
    }

    // ============================================
    // Package Registration Tests
    // ============================================

    public function testRegisterPackage(): void
    {
        $package = $this->createMockPackage('TestPackage');

        PackageManager::register_package($package);

        $this->assertTrue(PackageManager::has_packages());
        $this->assertSame($package, PackageManager::get_package('TestPackage'));
    }

    public function testRegisterMultiplePackages(): void
    {
        $package1 = $this->createMockPackage('Package1');
        $package2 = $this->createMockPackage('Package2');

        PackageManager::register_package($package1);
        PackageManager::register_package($package2);

        $this->assertSame($package1, PackageManager::get_package('Package1'));
        $this->assertSame($package2, PackageManager::get_package('Package2'));
    }

    public function testGetNonExistentPackageReturnsNull(): void
    {
        $this->assertNull(PackageManager::get_package('NonExistent'));
    }

    public function testHasPackagesReturnsFalseWhenEmpty(): void
    {
        $this->assertFalse(PackageManager::has_packages());
    }

    public function testIsInitializedReturnsTrueWhenPackagesRegistered(): void
    {
        $package = $this->createMockPackage('Test');
        PackageManager::register_package($package);

        $this->assertTrue(PackageManager::is_initialized());
    }

    // ============================================
    // Namespace Registry Tests
    // ============================================

    public function testRegisterPackageStoresNamespaces(): void
    {
        $package = $this->createMockPackage(
            'TestPkg',
            ['Test\\Namespace\\', 'Another\\Namespace\\']
        );

        PackageManager::register_package($package);

        // Verify namespace mapping
        $this->assertEquals('TestPkg', PackageManager::map_namespace_to_package('Test\\Namespace\\Foo'));
        $this->assertEquals('TestPkg', PackageManager::map_namespace_to_package('Another\\Namespace\\Bar'));
    }

    public function testNamespaceMappingReturnsNullForUnmapped(): void
    {
        $package = $this->createMockPackage('Test', ['Test\\']);
        PackageManager::register_package($package);

        $this->assertNull(PackageManager::map_namespace_to_package('Unmapped\\Class'));
    }

    public function testNamespaceMappingUsesLongestMatch(): void
    {
        $package1 = $this->createMockPackage('Short', ['MilliRules\\']);
        $package2 = $this->createMockPackage('Long', ['MilliRules\\Extended\\']);

        PackageManager::register_package($package1);
        PackageManager::register_package($package2);

        // Should match the longer (more specific) namespace
        $this->assertEquals('Long', PackageManager::map_namespace_to_package('MilliRules\\Extended\\CustomClass'));
        // Shorter namespace should still work for classes not in extended
        $this->assertEquals('Short', PackageManager::map_namespace_to_package('MilliRules\\BasicClass'));
    }

    public function testNamespaceCachingWorks(): void
    {
        $package = $this->createMockPackage('Cached', ['Cached\\Namespace\\']);
        PackageManager::register_package($package);

        // First call should populate cache
        $result1 = PackageManager::map_namespace_to_package('Cached\\Namespace\\ClassA');
        // Second call should use cache
        $result2 = PackageManager::map_namespace_to_package('Cached\\Namespace\\ClassA');

        $this->assertEquals('Cached', $result1);
        $this->assertEquals('Cached', $result2);
    }

    public function testNamespaceCacheClearedOnNewPackageRegistration(): void
    {
        $package1 = $this->createMockPackage('Pkg1', ['Test\\']);
        PackageManager::register_package($package1);

        // Populate cache
        PackageManager::map_namespace_to_package('Test\\Class');

        // Register new package - should clear cache
        $package2 = $this->createMockPackage('Pkg2', ['Other\\']);
        PackageManager::register_package($package2);

        // Cache should be cleared and rebuild correctly
        $this->assertEquals('Pkg1', PackageManager::map_namespace_to_package('Test\\Class'));
    }

    // ============================================
    // Package Loading Tests
    // ============================================

    public function testLoadAvailablePackage(): void
    {
        $package = $this->createMockPackage('Available', [], [], true);
        PackageManager::register_package($package);

        $loaded = PackageManager::load_packages(['Available']);

        $this->assertEquals(['Available'], $loaded);
        $this->assertTrue(PackageManager::is_package_loaded('Available'));
    }

    public function testLoadUnavailablePackageFails(): void
    {
        $package = $this->createMockPackage('Unavailable', [], [], false);
        PackageManager::register_package($package);

        $loaded = PackageManager::load_packages(['Unavailable']);

        $this->assertEmpty($loaded);
        $this->assertFalse(PackageManager::is_package_loaded('Unavailable'));
    }

    public function testLoadNonExistentPackageFails(): void
    {
        $loaded = PackageManager::load_packages(['NonExistent']);

        $this->assertEmpty($loaded);
    }

    public function testLoadPackagesWithNullLoadsAllAvailable(): void
    {
        $package1 = $this->createMockPackage('Available1', [], [], true);
        $package2 = $this->createMockPackage('Available2', [], [], true);
        $package3 = $this->createMockPackage('Unavailable', [], [], false);

        PackageManager::register_package($package1);
        PackageManager::register_package($package2);
        PackageManager::register_package($package3);

        $loaded = PackageManager::load_packages(null);

        $this->assertContains('Available1', $loaded);
        $this->assertContains('Available2', $loaded);
        $this->assertNotContains('Unavailable', $loaded);
    }

    public function testPackageNamespacesRegisteredOnLoad(): void
    {
        $package = $this->createMockPackage('WithNamespaces');
        PackageManager::register_package($package);

        PackageManager::load_packages(['WithNamespaces']);

        $this->assertTrue($package->wasNamespacesRegisteredCalled());
    }

    // ============================================
    // Dependency Resolution Tests
    // ============================================

    public function testLoadPackageWithDependencies(): void
    {
        $dependency = $this->createMockPackage('Dependency');
        $main = $this->createMockPackage('Main', [], ['Dependency']);

        PackageManager::register_package($dependency);
        PackageManager::register_package($main);

        $loaded = PackageManager::load_packages(['Main']);

        $this->assertContains('Dependency', $loaded);
        $this->assertContains('Main', $loaded);
        $this->assertTrue(PackageManager::is_package_loaded('Dependency'));
        $this->assertTrue(PackageManager::is_package_loaded('Main'));
    }

    public function testLoadPackageWithChainedDependencies(): void
    {
        // A -> B -> C dependency chain
        $pkgC = $this->createMockPackage('C');
        $pkgB = $this->createMockPackage('B', [], ['C']);
        $pkgA = $this->createMockPackage('A', [], ['B']);

        PackageManager::register_package($pkgC);
        PackageManager::register_package($pkgB);
        PackageManager::register_package($pkgA);

        $loaded = PackageManager::load_packages(['A']);

        $this->assertEquals(['C', 'B', 'A'], $loaded);
    }

    public function testLoadPackageWithMissingDependencyFails(): void
    {
        $package = $this->createMockPackage('NeedsDep', [], ['MissingDep']);
        PackageManager::register_package($package);

        $loaded = PackageManager::load_packages(['NeedsDep']);

        $this->assertEmpty($loaded);
        $this->assertFalse(PackageManager::is_package_loaded('NeedsDep'));
    }

    public function testLoadPackageWithUnavailableDependencyFails(): void
    {
        $dependency = $this->createMockPackage('UnavailableDep', [], [], false);
        $main = $this->createMockPackage('Main', [], ['UnavailableDep']);

        PackageManager::register_package($dependency);
        PackageManager::register_package($main);

        $loaded = PackageManager::load_packages(['Main']);

        $this->assertEmpty($loaded);
        $this->assertFalse(PackageManager::is_package_loaded('Main'));
    }

    // ============================================
    // Circular Dependency Detection Tests
    // ============================================

    public function testCircularDependencyDetected(): void
    {
        // A -> B -> A circular dependency
        $pkgA = $this->createMockPackage('A', [], ['B']);
        $pkgB = $this->createMockPackage('B', [], ['A']);

        PackageManager::register_package($pkgA);
        PackageManager::register_package($pkgB);

        $loaded = PackageManager::load_packages(['A']);

        $this->assertEmpty($loaded);
        $this->assertFalse(PackageManager::is_package_loaded('A'));
        $this->assertFalse(PackageManager::is_package_loaded('B'));
    }

    public function testCircularDependencyWithThreePackages(): void
    {
        // A -> B -> C -> A
        $pkgA = $this->createMockPackage('A', [], ['B']);
        $pkgB = $this->createMockPackage('B', [], ['C']);
        $pkgC = $this->createMockPackage('C', [], ['A']);

        PackageManager::register_package($pkgA);
        PackageManager::register_package($pkgB);
        PackageManager::register_package($pkgC);

        $loaded = PackageManager::load_packages(['A']);

        $this->assertEmpty($loaded);
        $this->assertFalse(PackageManager::is_package_loaded('A'));
        $this->assertFalse(PackageManager::is_package_loaded('B'));
        $this->assertFalse(PackageManager::is_package_loaded('C'));
    }

    public function testSelfDependencyDetected(): void
    {
        $package = $this->createMockPackage('SelfRef', [], ['SelfRef']);
        PackageManager::register_package($package);

        $loaded = PackageManager::load_packages(['SelfRef']);

        $this->assertEmpty($loaded);
        $this->assertFalse(PackageManager::is_package_loaded('SelfRef'));
    }

    // ============================================
    // Package Already Loaded Tests
    // ============================================

    public function testAlreadyLoadedPackageNotLoadedAgain(): void
    {
        $package = $this->createMockPackage('OnlyOnce');
        PackageManager::register_package($package);

        // Load once
        PackageManager::load_packages(['OnlyOnce']);
        $package->clear(); // Reset to check it's not called again

        // Try to load again
        PackageManager::load_packages(['OnlyOnce']);

        // Should still be loaded but not re-initialized
        $this->assertTrue(PackageManager::is_package_loaded('OnlyOnce'));
    }

    public function testSharedDependencyLoadedOnce(): void
    {
        // Both A and B depend on C, but C should only be loaded once
        $pkgC = $this->createMockPackage('C');
        $pkgA = $this->createMockPackage('A', [], ['C']);
        $pkgB = $this->createMockPackage('B', [], ['C']);

        PackageManager::register_package($pkgC);
        PackageManager::register_package($pkgA);
        PackageManager::register_package($pkgB);

        $loaded = PackageManager::load_packages(['A', 'B']);

        // C should appear only once in loaded packages
        $this->assertEquals(['C', 'A', 'B'], $loaded);
    }

    // ============================================
    // Context Building Tests
    // ============================================

    public function testBuildContextFromSinglePackage(): void
    {
        $package = $this->createMockPackage('Pkg1');
        PackageManager::register_package($package);
        PackageManager::load_packages(['Pkg1']);

        $context = PackageManager::build_context();

        $this->assertInstanceOf(Context::class, $context);
        $this->assertEquals('context_Pkg1', $context->get('Pkg1'));
    }

    public function testBuildContextFromMultiplePackages(): void
    {
        $pkg1 = $this->createMockPackage('Pkg1');
        $pkg2 = $this->createMockPackage('Pkg2');

        PackageManager::register_package($pkg1);
        PackageManager::register_package($pkg2);
        PackageManager::load_packages(['Pkg1', 'Pkg2']);

        $context = PackageManager::build_context();

        $this->assertInstanceOf(Context::class, $context);
        $this->assertEquals('context_Pkg1', $context->get('Pkg1'));
        $this->assertEquals('context_Pkg2', $context->get('Pkg2'));
    }

    public function testBuildContextMergesLaterPackagesOverEarlier(): void
    {
        $pkg1 = new class ('Pkg1') implements PackageInterface {
            private string $name;
            public function __construct(string $name)
            {
                $this->name = $name;
            }
            public function get_name(): string
            {
                return $this->name;
            }
            public function get_namespaces(): array
            {
                return [];
            }
            public function is_available(): bool
            {
                return true;
            }
            public function get_required_packages(): array
            {
                return [];
            }
            public function register_namespaces(): void
            {
            }
            public function build_context(): array
            {
                return ['shared' => 'from_pkg1'];
            }
            public function register_context_providers(Context $context): void
            {
                $context->register_provider('shared', fn() => ['shared' => 'from_pkg1']);
            }
            public function get_placeholder_resolver(Context $context)
            {
                return null;
            }
            public function register_rule(array $rule, array $metadata): void
            {
            }
            public function execute_rules(array $rules, Context $context): array
            {
                return [];
            }
            public function get_rules(): array
            {
                return [];
            }
            public function clear(): void
            {
            }
            public function resolve_class_name(string $type, string $category): ?string
            {
                return null;
            }
            public function unregister_rule(string $rule_id): bool
            {
                return false;
            }
        };

        $pkg2 = new class ('Pkg2') implements PackageInterface {
            private string $name;
            public function __construct(string $name)
            {
                $this->name = $name;
            }
            public function get_name(): string
            {
                return $this->name;
            }
            public function get_namespaces(): array
            {
                return [];
            }
            public function is_available(): bool
            {
                return true;
            }
            public function get_required_packages(): array
            {
                return [];
            }
            public function register_namespaces(): void
            {
            }
            public function build_context(): array
            {
                return ['shared' => 'from_pkg2'];
            }
            public function register_context_providers(Context $context): void
            {
                $context->register_provider('shared', fn() => ['shared' => 'from_pkg2']);
            }
            public function get_placeholder_resolver(Context $context)
            {
                return null;
            }
            public function register_rule(array $rule, array $metadata): void
            {
            }
            public function execute_rules(array $rules, Context $context): array
            {
                return [];
            }
            public function get_rules(): array
            {
                return [];
            }
            public function clear(): void
            {
            }
            public function resolve_class_name(string $type, string $category): ?string
            {
                return null;
            }
            public function unregister_rule(string $rule_id): bool
            {
                return false;
            }
        };

        PackageManager::register_package($pkg1);
        PackageManager::register_package($pkg2);
        PackageManager::load_packages(['Pkg1', 'Pkg2']);

        $context = PackageManager::build_context();

        // Pkg2's value should override Pkg1's
        $this->assertInstanceOf(Context::class, $context);
        $this->assertEquals('from_pkg2', $context->get('shared'));
    }

    // ============================================
    // Rule Registration Tests
    // ============================================

    public function testRegisterRuleWithUnloadedPackage(): void
    {
        $package = $this->createMockPackage('TestPkg');
        PackageManager::register_package($package);

        $rule = ['id' => 'test-rule', 'conditions' => [], 'actions' => []];
        $metadata = ['required_packages' => ['TestPkg'], 'type' => 'php'];

        // Should not throw - rule gets registered even though package not loaded
        PackageManager::register_rule($rule, $metadata);

        // Verify package is not loaded
        $this->assertFalse(PackageManager::is_package_loaded('TestPkg'));
    }

    public function testRegisterRuleWithNonExistentPackageHandledGracefully(): void
    {
        $rule = ['id' => 'test-rule'];
        $metadata = ['required_packages' => ['NonExistent']];

        // Should not throw
        PackageManager::register_rule($rule, $metadata);

        // Nothing to assert - just verify it doesn't crash
        $this->assertTrue(true);
    }

    public function testRegisterRuleWithNoPackagesHandledGracefully(): void
    {
        $rule = ['id' => 'test-rule'];
        $metadata = ['required_packages' => []];

        // Should not throw
        PackageManager::register_rule($rule, $metadata);

        // Nothing to assert - just verify it doesn't crash
        $this->assertTrue(true);
    }

    // ============================================
    // Get All Rules Tests
    // ============================================

    public function testGetAllRulesReturnsEmptyWhenNoPackagesLoaded(): void
    {
        $this->assertSame([], PackageManager::get_all_rules());
    }

    public function testGetAllRulesReturnsEmptyWhenPackagesHaveNoRules(): void
    {
        $package = $this->createMockPackage('Pkg1');
        PackageManager::register_package($package);
        PackageManager::load_packages(['Pkg1']);

        $this->assertSame([], PackageManager::get_all_rules());
    }

    public function testGetAllRulesAggregatesRulesFromMultiplePackages(): void
    {
        $pkg1 = $this->createMockPackage('Pkg1');
        $pkg2 = $this->createMockPackage('Pkg2');

        PackageManager::register_package($pkg1);
        PackageManager::register_package($pkg2);
        PackageManager::load_packages(['Pkg1', 'Pkg2']);

        PackageManager::register_rule(
            ['id' => 'rule-a', 'conditions' => [], 'actions' => []],
            ['required_packages' => ['Pkg1']]
        );
        PackageManager::register_rule(
            ['id' => 'rule-b', 'conditions' => [], 'actions' => []],
            ['required_packages' => ['Pkg2']]
        );

        $all = PackageManager::get_all_rules();

        $this->assertCount(2, $all);

        // Each rule should be tagged with its package name.
        $ruleA = $this->findRuleById($all, 'rule-a');
        $ruleB = $this->findRuleById($all, 'rule-b');

        $this->assertNotNull($ruleA);
        $this->assertNotNull($ruleB);
        $this->assertSame('Pkg1', $ruleA['_package']);
        $this->assertSame('Pkg2', $ruleB['_package']);
    }

    public function testGetAllRulesTagsEachRuleWithPackageName(): void
    {
        $package = $this->createMockPackage('MyPkg');
        PackageManager::register_package($package);
        PackageManager::load_packages(['MyPkg']);

        PackageManager::register_rule(
            ['id' => 'tagged-rule', 'conditions' => [], 'actions' => []],
            ['required_packages' => ['MyPkg']]
        );

        $all = PackageManager::get_all_rules();

        $this->assertCount(1, $all);
        $this->assertSame('MyPkg', $all[0]['_package']);
        $this->assertSame('tagged-rule', $all[0]['id']);
    }

    public function testGetAllRulesFlattensGroupedRules(): void
    {
        // Create a package that returns grouped rules (like WordPress groups by hook).
        $grouped = new class implements PackageInterface {
            private array $rules = [];
            public function get_name(): string
            {
                return 'Grouped';
            }
            public function get_namespaces(): array
            {
                return [];
            }
            public function is_available(): bool
            {
                return true;
            }
            public function get_required_packages(): array
            {
                return [];
            }
            public function register_namespaces(): void
            {
            }
            public function build_context(): array
            {
                return [];
            }
            public function register_context_providers(Context $context): void
            {
            }
            public function get_placeholder_resolver(Context $context)
            {
                return null;
            }
            public function register_rule(array $rule, array $metadata): void
            {
                $hook = $metadata['hook'] ?? 'default';
                $rule['_metadata'] = $metadata;
                $this->rules[$hook][] = $rule;
            }
            public function execute_rules(array $rules, Context $context): array
            {
                return [];
            }
            public function get_rules(): array
            {
                // Returns grouped structure: ['hook_name' => [rule1, ...]]
                return $this->rules;
            }
            public function clear(): void
            {
                $this->rules = [];
            }
            public function resolve_class_name(string $type, string $category): ?string
            {
                return null;
            }
            public function unregister_rule(string $rule_id): bool
            {
                return false;
            }
        };

        PackageManager::register_package($grouped);
        PackageManager::load_packages(['Grouped']);

        PackageManager::register_rule(
            ['id' => 'init-rule', 'conditions' => [], 'actions' => []],
            ['required_packages' => ['Grouped'], 'hook' => 'init']
        );
        PackageManager::register_rule(
            ['id' => 'wp-rule', 'conditions' => [], 'actions' => []],
            ['required_packages' => ['Grouped'], 'hook' => 'wp']
        );

        $all = PackageManager::get_all_rules();

        // Both rules should be flattened into a single array.
        $this->assertCount(2, $all);

        $initRule = $this->findRuleById($all, 'init-rule');
        $wpRule   = $this->findRuleById($all, 'wp-rule');

        $this->assertNotNull($initRule);
        $this->assertNotNull($wpRule);
        $this->assertSame('Grouped', $initRule['_package']);
        $this->assertSame('Grouped', $wpRule['_package']);
    }

    public function testGetAllRulesOnlyIncludesLoadedPackages(): void
    {
        $loaded   = $this->createMockPackage('Loaded');
        $unloaded = $this->createMockPackage('Unloaded', [], [], false);

        PackageManager::register_package($loaded);
        PackageManager::register_package($unloaded);
        PackageManager::load_packages();

        PackageManager::register_rule(
            ['id' => 'loaded-rule'],
            ['required_packages' => ['Loaded']]
        );

        $all = PackageManager::get_all_rules();

        $this->assertCount(1, $all);
        $this->assertSame('Loaded', $all[0]['_package']);
    }

    public function testRegisterRuleRemovesFromOldPackageWhenRequiredPackagesChange(): void
    {
        $wp = $this->createMockPackage('WP');
        $php = $this->createMockPackage('PHP');
        PackageManager::register_package($wp);
        PackageManager::register_package($php);
        PackageManager::load_packages(['WP', 'PHP']);

        // Rule initially registered in WP package.
        PackageManager::register_rule(
            ['id' => 'my-rule', 'conditions' => [], 'actions' => []],
            ['required_packages' => ['WP']]
        );

        $all = PackageManager::get_all_rules();
        $this->assertCount(1, $all);
        $this->assertSame('WP', $all[0]['_package']);

        // User overrides rule — now targets PHP package instead.
        PackageManager::register_rule(
            ['id' => 'my-rule', 'conditions' => ['changed'], 'actions' => []],
            ['required_packages' => ['PHP']]
        );

        $all = PackageManager::get_all_rules();

        // Should exist only once, in the PHP package.
        $this->assertCount(1, $all);
        $this->assertSame('PHP', $all[0]['_package']);
        $this->assertSame(['changed'], $all[0]['conditions']);
    }

    /**
     * Find a rule by ID in an array of rules.
     *
     * @param array<int, array<string, mixed>> $rules The rules to search.
     * @param string $id The rule ID to find.
     * @return array<string, mixed>|null The rule or null if not found.
     */
    private function findRuleById(array $rules, string $id): ?array
    {
        foreach ($rules as $rule) {
            if (($rule['id'] ?? null) === $id) {
                return $rule;
            }
        }
        return null;
    }

    // ============================================
    // Clear and Reset Tests
    // ============================================

    public function testClearRemovesRulesButKeepsPackages(): void
    {
        $package = $this->createMockPackage('Pkg');
        PackageManager::register_package($package);
        PackageManager::load_packages(['Pkg']);

        PackageManager::clear();

        // Packages should still be registered
        $this->assertTrue(PackageManager::has_packages());
        $this->assertNotNull(PackageManager::get_package('Pkg'));

        // But loaded packages list should be empty
        $this->assertFalse(PackageManager::is_package_loaded('Pkg'));
        $this->assertEquals([], PackageManager::get_loaded_package_names());
    }

    public function testResetClearsEverything(): void
    {
        $package = $this->createMockPackage('Pkg', ['Test\\Namespace\\']);
        PackageManager::register_package($package);
        PackageManager::load_packages(['Pkg']);

        PackageManager::reset();

        $this->assertFalse(PackageManager::has_packages());
        $this->assertFalse(PackageManager::is_initialized());
        $this->assertNull(PackageManager::get_package('Pkg'));
        $this->assertFalse(PackageManager::is_package_loaded('Pkg'));
        $this->assertNull(PackageManager::map_namespace_to_package('Test\\Namespace\\Class'));
    }

    public function testClearCallsClearOnPackages(): void
    {
        $package = $this->createMockPackage('Pkg');
        PackageManager::register_package($package);
        PackageManager::load_packages(['Pkg']);

        // Add a rule to verify it gets cleared
        PackageManager::register_rule(
            ['id' => 'rule1'],
            ['required_packages' => ['Pkg']]
        );

        PackageManager::clear();

        // Package should be cleared (loaded packages list empty)
        $this->assertFalse(PackageManager::is_package_loaded('Pkg'));
        // But package should still be registered
        $this->assertTrue(PackageManager::has_packages());
    }

    // ============================================
    // Get Loaded Packages Tests
    // ============================================

    public function testGetLoadedPackageNames(): void
    {
        $pkg1 = $this->createMockPackage('Pkg1');
        $pkg2 = $this->createMockPackage('Pkg2');

        PackageManager::register_package($pkg1);
        PackageManager::register_package($pkg2);
        PackageManager::load_packages(['Pkg1', 'Pkg2']);

        $names = PackageManager::get_loaded_package_names();

        $this->assertContains('Pkg1', $names);
        $this->assertContains('Pkg2', $names);
    }

    public function testGetLoadedPackages(): void
    {
        $pkg1 = $this->createMockPackage('Pkg1');
        $pkg2 = $this->createMockPackage('Pkg2');

        PackageManager::register_package($pkg1);
        PackageManager::register_package($pkg2);
        PackageManager::load_packages(['Pkg1', 'Pkg2']);

        $packages = PackageManager::get_loaded_packages();

        $this->assertCount(2, $packages);
        $this->assertContains($pkg1, $packages);
        $this->assertContains($pkg2, $packages);
    }

    // ============================================
    // Placeholder Resolver Tests
    // ============================================

    public function testGetPlaceholderResolverPrioritizesWP(): void
    {
        $wpResolver = new \stdClass();
        $phpResolver = new \stdClass();

        $wpPackage = new class ('WP', $wpResolver) implements PackageInterface {
            private string $name;
            private $resolver;
            public function __construct(string $name, $resolver)
            {
                $this->name = $name;
                $this->resolver = $resolver;
            }
            public function get_name(): string
            {
                return $this->name;
            }
            public function get_namespaces(): array
            {
                return [];
            }
            public function is_available(): bool
            {
                return true;
            }
            public function get_required_packages(): array
            {
                return [];
            }
            public function register_namespaces(): void
            {
            }
            public function build_context(): array
            {
                return [];
            }
            public function register_context_providers(Context $context): void
            {
            }
            public function get_placeholder_resolver(Context $context)
            {
                return $this->resolver;
            }
            public function register_rule(array $rule, array $metadata): void
            {
            }
            public function execute_rules(array $rules, Context $context): array
            {
                return [];
            }
            public function get_rules(): array
            {
                return [];
            }
            public function clear(): void
            {
            }
            public function resolve_class_name(string $type, string $category): ?string
            {
                return null;
            }
            public function unregister_rule(string $rule_id): bool
            {
                return false;
            }
        };

        $phpPackage = new class ('PHP', $phpResolver) implements PackageInterface {
            private string $name;
            private $resolver;
            public function __construct(string $name, $resolver)
            {
                $this->name = $name;
                $this->resolver = $resolver;
            }
            public function get_name(): string
            {
                return $this->name;
            }
            public function get_namespaces(): array
            {
                return [];
            }
            public function is_available(): bool
            {
                return true;
            }
            public function get_required_packages(): array
            {
                return [];
            }
            public function register_namespaces(): void
            {
            }
            public function build_context(): array
            {
                return [];
            }
            public function register_context_providers(Context $context): void
            {
            }
            public function get_placeholder_resolver(Context $context)
            {
                return $this->resolver;
            }
            public function register_rule(array $rule, array $metadata): void
            {
            }
            public function execute_rules(array $rules, Context $context): array
            {
                return [];
            }
            public function get_rules(): array
            {
                return [];
            }
            public function clear(): void
            {
            }
            public function resolve_class_name(string $type, string $category): ?string
            {
                return null;
            }
            public function unregister_rule(string $rule_id): bool
            {
                return false;
            }
        };

        PackageManager::register_package($phpPackage);
        PackageManager::register_package($wpPackage);
        PackageManager::load_packages(['PHP', 'WP']);

        $resolver = PackageManager::get_placeholder_resolver(new Context());

        // Should return WP resolver (higher priority)
        $this->assertSame($wpResolver, $resolver);
    }

    // ============================================
    // Pending Rules / Unresolved Namespace Tests
    // ============================================

    /**
     * @param array<int, array<string,mixed>> $actions
     * @param array<string, mixed>  $metadata_overrides
     * @return array{rule: array<string,mixed>, metadata: array<string,mixed>}
     */
    private function buildRuleEntry(string $id, array $actions, array $metadata_overrides = []): array
    {
        $rule = [
            'id' => $id,
            'title' => 'Test ' . $id,
            'match_type' => 'all',
            'conditions' => [],
            'actions' => $actions,
        ];

        $metadata = array_merge([
            'required_packages' => [],
            'unresolved_namespaces' => [],
            'explicit_type' => null,
            'type' => 'php',
            'order' => 10,
            'enabled' => true,
        ], $metadata_overrides);

        return ['rule' => $rule, 'metadata' => $metadata];
    }

    public function testRegisterRuleWithUnresolvedNamespacesIsDeferred(): void
    {
        $entry = $this->buildRuleEntry('rule-deferred', [
            ['type' => 'add_flag'],
        ], [
            'required_packages' => [],
            'unresolved_namespaces' => ['MilliRules\\Packages\\WordPress\\Actions\\AddFlag'],
        ]);

        PackageManager::register_rule($entry['rule'], $entry['metadata']);

        $pending = PackageManager::get_pending_rules();
        $this->assertCount(1, $pending);
        $this->assertSame('rule-deferred', $pending[0]['rule']['id']);

        // Should also surface in get_unresolved_pending().
        $unresolved = PackageManager::get_unresolved_pending();
        $this->assertCount(1, $unresolved);
    }

    public function testRegisterRuleWithKnownButUnloadedPackageIsDeferred(): void
    {
        // Package is registered but never loaded.
        $package = $this->createMockPackage('PendingPkg', ['Pending\\Ns\\']);
        PackageManager::register_package($package);

        $entry = $this->buildRuleEntry('rule-pkg-unloaded', [
            ['type' => 'do_thing'],
        ], [
            'required_packages' => ['PendingPkg'],
        ]);

        PackageManager::register_rule($entry['rule'], $entry['metadata']);

        $pending = PackageManager::get_pending_rules();
        $this->assertCount(1, $pending);

        // Should NOT show up in unresolved-only view (different defer cause).
        $this->assertCount(0, PackageManager::get_unresolved_pending());
    }

    public function testCoreOnlyRuleRegistersImmediately(): void
    {
        $entry = $this->buildRuleEntry('rule-core', [
            ['type' => 'callback'],
        ], [
            'required_packages' => [],
            'unresolved_namespaces' => [],
        ]);

        PackageManager::register_rule($entry['rule'], $entry['metadata']);

        $this->assertCount(0, PackageManager::get_pending_rules());
    }

    public function testFinalizePendingRulesEmitsWarningForUnresolved(): void
    {
        $entry = $this->buildRuleEntry('rule-typo', [
            ['type' => 'add_flagg'],
        ], [
            'unresolved_namespaces' => ['MilliRules\\Actions\\AddFlagg'],
        ]);

        PackageManager::register_rule($entry['rule'], $entry['metadata']);

        $still_pending = PackageManager::finalize_pending_rules();
        $this->assertSame(1, $still_pending);

        // The rule stays pending; finalize doesn't drop it.
        $this->assertCount(1, PackageManager::get_pending_rules());
    }

    public function testFinalizePendingRulesEmitsWarningForUnloadedPackage(): void
    {
        $package = $this->createMockPackage('NeverLoaded', ['Never\\']);
        PackageManager::register_package($package);

        $entry = $this->buildRuleEntry('rule-stuck', [
            ['type' => 'noop'],
        ], [
            'required_packages' => ['NeverLoaded'],
        ]);

        PackageManager::register_rule($entry['rule'], $entry['metadata']);

        $still_pending = PackageManager::finalize_pending_rules();
        $this->assertSame(1, $still_pending);
    }

    public function testRegisterPendingRulesProcessesQueueWhenPackageLoads(): void
    {
        // Custom action so re-detection can resolve it (no real class exists for 'late_action').
        \MilliRules\Rules::register_action('late_action', function () {
            return null;
        });

        $package = $this->createMockPackage('LateLoad', ['LateLoad\\Ns\\']);
        PackageManager::register_package($package);

        $entry = $this->buildRuleEntry('rule-late', [
            ['type' => 'late_action'],
        ], [
            'required_packages' => ['LateLoad'],
        ]);

        PackageManager::register_rule($entry['rule'], $entry['metadata']);
        $this->assertCount(1, PackageManager::get_pending_rules());

        // Loading the package triggers register_pending_rules() automatically.
        PackageManager::load_packages(['LateLoad']);

        $this->assertCount(0, PackageManager::get_pending_rules());
        $this->assertCount(1, $package->get_rules());
        $this->assertSame('rule-late', $package->get_rules()[0]['id']);
    }

    public function testGetUnresolvedPendingFiltersByDeferralCause(): void
    {
        \MilliRules\Rules::register_action('a_action', function () {
            return null;
        });

        $unloaded_pkg = $this->createMockPackage('Unloaded', ['Unloaded\\']);
        PackageManager::register_package($unloaded_pkg);

        $a = $this->buildRuleEntry('rule-a', [['type' => 'a_action']], [
            'required_packages' => ['Unloaded'],
        ]);
        $b = $this->buildRuleEntry('rule-b', [['type' => 'mystery']], [
            'unresolved_namespaces' => ['Mystery\\Ns\\Class'],
        ]);

        PackageManager::register_rule($a['rule'], $a['metadata']);
        PackageManager::register_rule($b['rule'], $b['metadata']);

        $this->assertCount(2, PackageManager::get_pending_rules());

        $unresolved = PackageManager::get_unresolved_pending();
        $this->assertCount(1, $unresolved);
        $this->assertSame('rule-b', $unresolved[0]['rule']['id']);
    }
}
