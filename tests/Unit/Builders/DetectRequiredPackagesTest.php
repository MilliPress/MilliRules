<?php

/**
 * Regression test for detect_required_packages().
 *
 * Combining an explicit type with conditions/actions that auto-detect to the
 * same package previously produced duplicate entries (e.g. ['PHP', 'PHP']),
 * which caused PackageManager::register_rule() to register the rule twice.
 *
 * @package MilliRules\Tests
 */

use MilliRules\Context;
use MilliRules\Packages\PackageInterface;
use MilliRules\Packages\PackageManager;
use MilliRules\Rules;

beforeEach(function () {
    PackageManager::reset();
});

afterEach(function () {
    PackageManager::reset();
});

function makePhpPackageStub(): PackageInterface
{
    return new class () implements PackageInterface {
        public function get_name(): string
        {
            return 'PHP';
        }

        public function get_namespaces(): array
        {
            return ['MilliRules\\Packages\\PHP\\'];
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

function invokeDetectRequiredPackages(Rules $rule): array
{
    $rule_array_prop = new ReflectionProperty(Rules::class, 'rule');
    $rule_array_prop->setAccessible(true);
    $explicit_type_prop = new ReflectionProperty(Rules::class, 'explicit_type');
    $explicit_type_prop->setAccessible(true);

    $detection = Rules::detect_packages_for_rule(
        $rule_array_prop->getValue($rule),
        $explicit_type_prop->getValue($rule)
    );

    return $detection['resolved'];
}

test('explicit type matching auto-detected package yields exactly one entry', function () {
    PackageManager::register_package(makePhpPackageStub());

    $rule = Rules::create('regression-explicit-type-dedup', 'php')
        ->set_conditions([
            ['type' => 'request_url', 'operator' => 'contains', 'value' => '/api'],
        ]);

    expect(invokeDetectRequiredPackages($rule))->toBe(['PHP']);
});

test('explicit type without auto-detected match still adds the package', function () {
    PackageManager::register_package(makePhpPackageStub());

    $rule = Rules::create('regression-explicit-type-only', 'php');

    expect(invokeDetectRequiredPackages($rule))->toBe(['PHP']);
});

test('explicit type lookup is case-insensitive and yields canonical name once', function () {
    PackageManager::register_package(makePhpPackageStub());

    $rule = Rules::create('regression-explicit-type-case', 'PhP')
        ->set_conditions([
            ['type' => 'request_url', 'operator' => 'contains', 'value' => '/api'],
        ]);

    expect(invokeDetectRequiredPackages($rule))->toBe(['PHP']);
});
