<?php

namespace MilliRules\Tests\Unit;

use MilliRules\Tests\TestCase;
use MilliRules\RuleEngine;
use MilliRules\Rules;
use MilliRules\Packages\PackageManager;
use MilliRules\Context;

/**
 * Comprehensive tests for RuleEngine class
 *
 * Tests rule execution, condition matching, action execution, package filtering,
 * namespace resolution, and statistics tracking
 */
class RuleEngineTestPHP extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any registered custom conditions/actions
        $this->clearCustomCallbacks();
    }

    /**
     * Clear custom conditions and actions using reflection
     */
    private function clearCustomCallbacks(): void
    {
        $reflection = new \ReflectionClass(Rules::class);

        // Clear custom conditions
        $conditionsProperty = $reflection->getProperty('custom_conditions');
        $conditionsProperty->setAccessible(true);
        $conditionsProperty->setValue([]);

        // Clear custom actions
        $actionsProperty = $reflection->getProperty('custom_actions');
        $actionsProperty->setAccessible(true);
        $actionsProperty->setValue([]);
    }

    // ============================================
    // Basic Rule Execution Tests
    // ============================================

    public function testExecuteEmptyRulesArray(): void
    {
        $engine = new RuleEngine();
        $result = $engine->execute([]);

        $this->assertEquals(0, $result['rules_processed']);
        $this->assertEquals(0, $result['rules_matched']);
    }

    public function testExecuteRuleWithNoConditions(): void
    {
        $engine = new RuleEngine();

        $executedActionAll = false;
        $executedActionAny = false;
        $executedActionNone = false;

        Rules::register_action('test_action_all', function ($args, Context $context) use (&$executedActionAll) {
            $executedActionAll = true;
        });

        Rules::register_action('test_action_any', function ($args, Context $context) use (&$executedActionAny) {
            $executedActionAny = true;
        });

        Rules::register_action('test_action_none', function ($args, Context $context) use (&$executedActionNone) {
            $executedActionNone = true;
        });

        $rules = [
            [
                'id' => 'no-conditions-all',
                'enabled' => true,
                'match_type' => 'all',
                'conditions' => [],
                'actions' => [
                    ['type' => 'test_action_all'],
                ],
            ],
            [
                'id' => 'no-conditions-any',
                'enabled' => true,
                'match_type' => 'any',
                'conditions' => [],
                'actions' => [
                    ['type' => 'test_action_any'],
                ],
            ],
            [
                'id' => 'no-conditions-none',
                'enabled' => true,
                'match_type' => 'none',
                'conditions' => [],
                'actions' => [
                    ['type' => 'test_action_none'],
                ],
            ],
        ];

        $result = $engine->execute($rules);

        // All 3 rules processed
        $this->assertEquals(3, $result['rules_processed']);

        // Only 2 rules matched: 'all' and 'none' (not 'any')
        $this->assertEquals(2, $result['rules_matched']);

        // Only actions for 'all' and 'none' executed
        $this->assertTrue($executedActionAll, 'match_type=all with empty conditions should match');
        $this->assertFalse($executedActionAny, 'match_type=any with empty conditions should not match');
        $this->assertTrue($executedActionNone, 'match_type=none with empty conditions should match');
    }

    public function testExecuteRuleWithMatchingCondition(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('always_true', function ($args, Context $context) {
            return true;
        });

        Rules::register_action('track_execution', function ($args, Context $context) use (&$executed) {
            $executed = true;
        });

        $executed = false;
        $rules = [
            [
                'id' => 'matching-rule',
                'enabled' => true,
                'match_type' => 'all',
                'conditions' => [
                    ['type' => 'always_true'],
                ],
                'actions' => [
                    ['type' => 'track_execution'],
                ],
            ],
        ];

        $result = $engine->execute($rules);

        $this->assertEquals(1, $result['rules_matched']);
        $this->assertTrue($executed);
    }

    public function testExecuteRuleWithNonMatchingCondition(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('always_false', function ($args, Context $context) {
            return false;
        });

        $rules = [
            [
                'id' => 'non-matching-rule',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'always_false'],
                ],
                'actions' => [],
            ],
        ];

        $result = $engine->execute($rules);

        $this->assertEquals(1, $result['rules_processed']);
        $this->assertEquals(0, $result['rules_matched']);
    }

    // ============================================
    // Match Type Tests
    // ============================================

    public function testMatchTypeAll(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('true1', function ($args, Context $context) {
            return true;
        });

        Rules::register_condition('true2', function ($args, Context $context) {
            return true;
        });

        $rules = [
            [
                'id' => 'all-match',
                'enabled' => true,
                'match_type' => 'all',
                'conditions' => [
                    ['type' => 'true1'],
                    ['type' => 'true2'],
                ],
                'actions' => [],
            ],
        ];

        $result = $engine->execute($rules);
        $this->assertEquals(1, $result['rules_matched']);
    }

    public function testMatchTypeAllWithOneFalse(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('true_cond', function ($args, Context $context) {
            return true;
        });

        Rules::register_condition('false_cond', function ($args, Context $context) {
            return false;
        });

        $rules = [
            [
                'id' => 'all-fail',
                'enabled' => true,
                'match_type' => 'all',
                'conditions' => [
                    ['type' => 'true_cond'],
                    ['type' => 'false_cond'],
                ],
                'actions' => [],
            ],
        ];

        $result = $engine->execute($rules);
        $this->assertEquals(0, $result['rules_matched']);
    }

    public function testMatchTypeAny(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('true_cond', function ($args, Context $context) {
            return true;
        });

        Rules::register_condition('false_cond', function ($args, Context $context) {
            return false;
        });

        $rules = [
            [
                'id' => 'any-match',
                'enabled' => true,
                'match_type' => 'any',
                'conditions' => [
                    ['type' => 'true_cond'],
                    ['type' => 'false_cond'],
                ],
                'actions' => [],
            ],
        ];

        $result = $engine->execute($rules);
        $this->assertEquals(1, $result['rules_matched']);
    }

    public function testMatchTypeNone(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('false1', function ($args, Context $context) {
            return false;
        });

        Rules::register_condition('false2', function ($args, Context $context) {
            return false;
        });

        $rules = [
            [
                'id' => 'none-match',
                'enabled' => true,
                'match_type' => 'none',
                'conditions' => [
                    ['type' => 'false1'],
                    ['type' => 'false2'],
                ],
                'actions' => [],
            ],
        ];

        $result = $engine->execute($rules);
        $this->assertEquals(1, $result['rules_matched']);
    }

    // ============================================
    // Disabled Rule Tests
    // ============================================

    public function testDisabledRuleNotExecuted(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('always_true', function ($args, Context $context) {
            return true;
        });

        $rules = [
            [
                'id' => 'disabled-rule',
                'enabled' => false,
                'conditions' => [
                    ['type' => 'always_true'],
                ],
                'actions' => [],
            ],
        ];

        $result = $engine->execute($rules);

        $this->assertEquals(1, $result['rules_processed']);
        $this->assertEquals(0, $result['rules_matched']);
    }

    public function testMissingEnabledDefaultsToTrue(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('always_true', function ($args, Context $context) {
            return true;
        });

        $rules = [
            [
                'id' => 'no-enabled-key',
                // Missing 'enabled' key
                'conditions' => [
                    ['type' => 'always_true'],
                ],
                'actions' => [],
            ],
        ];

        $result = $engine->execute($rules);
        $this->assertEquals(1, $result['rules_matched']);
    }

    // ============================================
    // Context Tests
    // ============================================

    public function testContextPassedToConditions(): void
    {
        $engine = new RuleEngine();

        $capturedContext = null;
        Rules::register_condition('capture_context', function ($args, Context $context) use (&$capturedContext) {
            $capturedContext = $context;
            return true;
        });

        $rules = [
            [
                'id' => 'context-test',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'capture_context'],
                ],
                'actions' => [],
            ],
        ];

        $context = ['user_id' => 123, 'request' => ['url' => '/test']];
        $engine->execute($rules, $context);

        $this->assertEquals(123, $capturedContext->get('user_id'));
        $this->assertEquals('/test', $capturedContext->get('request.url'));
    }

    public function testContextPassedToActions(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('always_true', function ($args, Context $context) {
            return true;
        });

        $capturedContext = null;
        Rules::register_action('capture_context', function ($args, Context $context) use (&$capturedContext) {
            $capturedContext = $context;
        });

        $rules = [
            [
                'id' => 'action-context-test',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'always_true'],
                ],
                'actions' => [
                    ['type' => 'capture_context'],
                ],
            ],
        ];

        $context = ['user_id' => 456];
        $result = $engine->execute($rules, $context);

        $this->assertEquals(456, $capturedContext->get('user_id'));
    }

    public function testGetContext(): void
    {
        $engine = new RuleEngine();
        $context = ['key' => 'value', 'number' => 42];

        $engine->execute([], $context);

        $this->assertEquals('value', $engine->get_context('key'));
        $this->assertEquals(42, $engine->get_context('number'));
        $this->assertNull($engine->get_context('nonexistent'));
    }

    // ============================================
    // Package Filtering Tests
    // ============================================

    public function testRuleWithMissingPackageSkipped(): void
    {
        $engine = new RuleEngine();

        $rules = [
            [
                'id' => 'missing-package-rule',
                'enabled' => true,
                'conditions' => [],
                'actions' => [],
                '_metadata' => [
                    'required_packages' => ['NonExistentPackage'],
                ],
            ],
        ];

        $result = $engine->execute($rules, [], ['PHP']); // Only PHP package available

        $this->assertEquals(1, $result['rules_processed']);
        $this->assertEquals(1, $result['rules_skipped']);
        $this->assertEquals(0, $result['rules_matched']);
    }

    public function testRuleWithAvailablePackageExecuted(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('always_true', function ($args, Context $context) {
            return true;
        });

        $rules = [
            [
                'id' => 'available-package-rule',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'always_true'],
                ],
                'actions' => [],
                '_metadata' => [
                    'required_packages' => ['PHP'],
                ],
            ],
        ];

        $result = $engine->execute($rules, [], ['PHP']);

        $this->assertEquals(1, $result['rules_processed']);
        $this->assertEquals(0, $result['rules_skipped']);
        $this->assertEquals(1, $result['rules_matched']);
    }

    public function testRuleWithNoMetadataExecuted(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('always_true', function ($args, Context $context) {
            return true;
        });

        $rules = [
            [
                'id' => 'no-metadata-rule',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'always_true'],
                ],
                'actions' => [],
                // No _metadata key
            ],
        ];

        $result = $engine->execute($rules, [], ['PHP']);

        $this->assertEquals(1, $result['rules_matched']);
    }

    // ============================================
    // Statistics Tests
    // ============================================

    public function testStatisticsTracking(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('true_cond', function ($args, Context $context) {
            return true;
        });

        Rules::register_condition('false_cond', function ($args, Context $context) {
            return false;
        });

        Rules::register_action('test_action', function ($args, Context $context) {
            // Action
        });

        $rules = [
            [
                'id' => 'matching-rule',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'true_cond'],
                ],
                'actions' => [
                    ['type' => 'test_action'],
                ],
            ],
            [
                'id' => 'non-matching-rule',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'false_cond'],
                ],
                'actions' => [],
            ],
            [
                'id' => 'disabled-rule',
                'enabled' => false,
                'conditions' => [],
                'actions' => [],
            ],
        ];

        $result = $engine->execute($rules);

        $this->assertEquals(3, $result['rules_processed']);
        $this->assertEquals(1, $result['rules_matched']);
        $this->assertEquals(1, $result['actions_executed']);
    }

    // ============================================
    // Error Handling Tests
    // ============================================

    public function testConditionThrowingExceptionHandledGracefully(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('error_condition', function ($args, Context $context) {
            throw new \Exception('Test exception in condition');
        });

        $rules = [
            [
                'id' => 'error-rule',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'error_condition'],
                ],
                'actions' => [],
            ],
        ];

        $result = $engine->execute($rules);

        // Rule should not match when condition throws exception
        $this->assertEquals(0, $result['rules_matched']);
    }

    public function testActionThrowingExceptionHandledGracefully(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('always_true', function ($args, Context $context) {
            return true;
        });

        Rules::register_action('error_action', function ($args, Context $context) {
            throw new \Exception('Test exception in action');
        });

        $rules = [
            [
                'id' => 'action-error-rule',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'always_true'],
                ],
                'actions' => [
                    ['type' => 'error_action'],
                ],
            ],
        ];

        // Should not throw - exceptions are caught
        $result = $engine->execute($rules);

        // Rule should match but action execution has error
        $this->assertEquals(1, $result['rules_matched']);
    }

    public function testUnknownConditionTypeHandledGracefully(): void
    {
        $engine = new RuleEngine();

        $rules = [
            [
                'id' => 'unknown-condition',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'nonexistent_condition'],
                ],
                'actions' => [],
            ],
        ];

        // Should not throw
        $result = $engine->execute($rules);

        // Unknown condition should be treated as false
        $this->assertEquals(0, $result['rules_matched']);
    }

    public function testUnknownActionTypeHandledGracefully(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('always_true', function ($args, Context $context) {
            return true;
        });

        $rules = [
            [
                'id' => 'unknown-action',
                'enabled' => true,
                'conditions' => [
                    ['type' => 'always_true'],
                ],
                'actions' => [
                    ['type' => 'nonexistent_action'],
                ],
            ],
        ];

        // Should not throw
        $result = $engine->execute($rules);

        // Rule should match but action is skipped
        $this->assertEquals(1, $result['rules_matched']);
    }

    public function testMissingConditionTypeHandledGracefully(): void
    {
        $engine = new RuleEngine();

        $rules = [
            [
                'id' => 'missing-type',
                'enabled' => true,
                'conditions' => [
                    ['value' => 'test'], // Missing 'type'
                ],
                'actions' => [],
            ],
        ];

        // Should not throw
        $result = $engine->execute($rules);

        // Missing type should be treated as false
        $this->assertEquals(0, $result['rules_matched']);
    }

    // ============================================
    // Namespace Registration Tests
    // ============================================

    public function testRegisterNamespace(): void
    {
        RuleEngine::register_namespace('Conditions', 'Test\\Conditions');

        // Verify namespace was registered by checking type_to_class_name
        $className = RuleEngine::type_to_class_name('test_condition', 'Conditions');

        $this->assertStringContainsString('TestCondition', $className);
    }

    public function testTypeToClassNameConvertsSnakeToPascal(): void
    {
        $className = RuleEngine::type_to_class_name('is_user_logged_in', 'Conditions');

        $this->assertStringContainsString('IsUserLoggedIn', $className);
    }

    // ============================================
    // Multiple Rules Tests
    // ============================================

    public function testExecuteMultipleRulesInSequence(): void
    {
        $engine = new RuleEngine();

        Rules::register_condition('always_true', function ($args, Context $context) {
            return true;
        });

        $executionOrder = [];
        Rules::register_action('track_order', function ($args, Context $context) use (&$executionOrder) {
            $executionOrder[] = $args['rule_id'] ?? 'unknown';
        });

        $rules = [
            [
                'id' => 'rule-1',
                'enabled' => true,
                'conditions' => [['type' => 'always_true']],
                'actions' => [['type' => 'track_order', 'rule_id' => 'first']],
            ],
            [
                'id' => 'rule-2',
                'enabled' => true,
                'conditions' => [['type' => 'always_true']],
                'actions' => [['type' => 'track_order', 'rule_id' => 'second']],
            ],
            [
                'id' => 'rule-3',
                'enabled' => true,
                'conditions' => [['type' => 'always_true']],
                'actions' => [['type' => 'track_order', 'rule_id' => 'third']],
            ],
        ];

        $result = $engine->execute($rules);

        $this->assertEquals(['first', 'second', 'third'], $executionOrder);
        $this->assertEquals(3, $result['rules_matched']);
    }
}
