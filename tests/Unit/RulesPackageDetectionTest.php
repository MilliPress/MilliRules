<?php

namespace MilliRules\Tests\Unit;

use MilliRules\Tests\TestCase;
use MilliRules\Rules;
use MilliRules\Packages\PackageManager;
use MilliRules\Packages\PackageInterface;
use MilliRules\Context;

/**
 * Tests for Rules::detect_packages_for_rule() static helper.
 *
 * Covers the resolved/unresolved split that drives PackageManager's deferral
 * logic when a rule references action/condition classes whose package isn't
 * registered yet.
 */
class RulesPackageDetectionTest extends TestCase
{
    private function createMockPackage(string $name, array $namespaces): PackageInterface
    {
        return new class ($name, $namespaces) implements PackageInterface {
            private string $name;
            private array $namespaces;

            public function __construct(string $name, array $namespaces)
            {
                $this->name = $name;
                $this->namespaces = $namespaces;
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
    }

    public function testCustomActionShortCircuitsToCore(): void
    {
        Rules::register_action('do_x', function () {
            return null;
        });

        $rule = [
            'id' => 'r',
            'conditions' => [],
            'actions' => [['type' => 'do_x']],
        ];

        $detection = Rules::detect_packages_for_rule($rule);

        $this->assertSame([], $detection['resolved']);
        $this->assertSame([], $detection['unresolved']);
    }

    public function testNonExistentActionInCoreNamespaceMarkedUnresolved(): void
    {
        // 'add_flag' has no class anywhere — fallback path produces
        // MilliRules\Actions\AddFlag (Core namespace) but class doesn't exist,
        // so it should be deferred rather than silently labeled Core.
        $rule = [
            'id' => 'r',
            'conditions' => [],
            'actions' => [['type' => 'add_flag']],
        ];

        $detection = Rules::detect_packages_for_rule($rule);

        $this->assertSame([], $detection['resolved']);
        $this->assertNotEmpty($detection['unresolved']);
        $this->assertStringContainsString('AddFlag', $detection['unresolved'][0]);
    }

    public function testRegisteredPackageNamespaceResolvesAction(): void
    {
        Rules::register_action('caching_action', function () {
            return null;
        });

        // Custom actions skip the namespace path. Use a real class instead:
        // the existing Callback action class lives in MilliRules\Actions\.
        $rule = [
            'id' => 'r',
            'conditions' => [],
            'actions' => [['type' => 'callback']],
        ];

        $detection = Rules::detect_packages_for_rule($rule);

        // 'callback' resolves to MilliRules\Actions\Callback — class exists,
        // namespace maps to Core, so resolved is empty (Core is filtered out)
        // and unresolved is empty too.
        $this->assertSame([], $detection['resolved']);
        $this->assertSame([], $detection['unresolved']);
    }

    public function testExplicitTypeForUnregisteredPackageMarkedUnresolved(): void
    {
        $rule = [
            'id' => 'r',
            'conditions' => [],
            'actions' => [],
        ];

        $detection = Rules::detect_packages_for_rule($rule, 'Acme');

        $this->assertSame([], $detection['resolved']);
        $this->assertContains('__explicit_type:Acme', $detection['unresolved']);
    }

    public function testExplicitTypePhpAndWpAreNotMarkedUnresolved(): void
    {
        // 'php' and 'wp' are detection labels, not always-registered packages.
        // They should never trigger explicit-type deferral even when no
        // matching package is registered.
        $rule = [
            'id' => 'r',
            'conditions' => [],
            'actions' => [],
        ];

        $detection_php = Rules::detect_packages_for_rule($rule, 'php');
        $detection_wp  = Rules::detect_packages_for_rule($rule, 'wp');

        $this->assertSame([], $detection_php['unresolved']);
        $this->assertSame([], $detection_wp['unresolved']);
    }

    public function testExplicitTypeForRegisteredPackageResolves(): void
    {
        $package = $this->createMockPackage('Acme', ['Acme\\']);
        PackageManager::register_package($package);

        $rule = [
            'id' => 'r',
            'conditions' => [],
            'actions' => [],
        ];

        $detection = Rules::detect_packages_for_rule($rule, 'Acme');

        $this->assertContains('Acme', $detection['resolved']);
        $this->assertSame([], $detection['unresolved']);
    }

    public function testNestedConditionGroupsAreInspected(): void
    {
        $rule = [
            'id' => 'r',
            'conditions' => [
                [
                    'match_type' => 'any',
                    'conditions' => [
                        ['type' => 'mystery_condition'],
                    ],
                ],
            ],
            'actions' => [],
        ];

        $detection = Rules::detect_packages_for_rule($rule);

        $this->assertNotEmpty($detection['unresolved']);
    }
}
