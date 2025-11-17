<?php

namespace MilliRules\Tests\Unit\Conditions;

use MilliRules\Tests\TestCase;
use MilliRules\Conditions\BaseCondition;
use MilliRules\Conditions\ConditionInterface;
use MilliRules\Context;

/**
 * Comprehensive tests for BaseCondition class
 *
 * Tests all operators, edge cases, match types, and placeholder resolution
 */
class BaseConditionTest extends TestCase
{
    /**
     * Create a concrete implementation of BaseCondition for testing
     */
    private function createTestCondition(array $config, Context $context = null): ConditionInterface
    {
        if ($context === null) {
            $context = new Context();
        }

        return new class ($config, $context) extends BaseCondition {
            private $actualValue;

            public function __construct(array $config, Context $context)
            {
                parent::__construct($config, $context);
                $this->actualValue = $config['_test_actual_value'] ?? '';
            }

            protected function get_actual_value(Context $context)
            {
                return $this->actualValue;
            }

            public function setActualValue($value): void
            {
                $this->actualValue = $value;
            }

            public function get_type(): string
            {
                return 'test_condition';
            }
        };
    }

    // ============================================
    // Equality Operators Tests
    // ============================================

    public function testEqualityOperatorWithMatchingStrings(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => 'test',
            '_test_actual_value' => 'test',
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testEqualityOperatorWithNonMatchingStrings(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => 'test',
            '_test_actual_value' => 'other',
        ]);

        $this->assertFalse($condition->matches(new Context()));
    }

    public function testEqualityOperatorWithNumbers(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => '123',
            '_test_actual_value' => 123,
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testNotEqualOperatorWithDifferentValues(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '!=',
            'value' => 'test',
            '_test_actual_value' => 'other',
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testNotEqualOperatorWithSameValues(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '!=',
            'value' => 'test',
            '_test_actual_value' => 'test',
        ]);

        $this->assertFalse($condition->matches(new Context()));
    }

    // ============================================
    // Numeric Comparison Operators Tests
    // ============================================

    /**
     * @dataProvider greaterThanProvider
     */
    public function testGreaterThanOperator($actual, $expected, bool $shouldMatch): void
    {
        $condition = $this->createTestCondition([
            'operator' => '>',
            'value' => $expected,
            '_test_actual_value' => $actual,
        ]);

        $this->assertEquals($shouldMatch, $condition->matches(new Context()));
    }

    public function greaterThanProvider(): array
    {
        return [
            'greater integer' => [10, 5, true],
            'equal integer' => [5, 5, false],
            'lesser integer' => [3, 5, false],
            'greater float' => [5.5, 5.0, true],
            'string numbers' => ['10', '5', true],
            'non-numeric actual' => ['abc', 5, false],
            'non-numeric expected' => [5, 'abc', false],
            'both non-numeric' => ['abc', 'xyz', false],
            'zero comparison' => [1, 0, true],
            'negative numbers' => [-5, -10, true],
        ];
    }

    /**
     * @dataProvider greaterThanOrEqualProvider
     */
    public function testGreaterThanOrEqualOperator($actual, $expected, bool $shouldMatch): void
    {
        $condition = $this->createTestCondition([
            'operator' => '>=',
            'value' => $expected,
            '_test_actual_value' => $actual,
        ]);

        $this->assertEquals($shouldMatch, $condition->matches(new Context()));
    }

    public function greaterThanOrEqualProvider(): array
    {
        return [
            'greater' => [10, 5, true],
            'equal' => [5, 5, true],
            'lesser' => [3, 5, false],
            'equal floats' => [5.0, 5.0, true],
        ];
    }

    /**
     * @dataProvider lessThanProvider
     */
    public function testLessThanOperator($actual, $expected, bool $shouldMatch): void
    {
        $condition = $this->createTestCondition([
            'operator' => '<',
            'value' => $expected,
            '_test_actual_value' => $actual,
        ]);

        $this->assertEquals($shouldMatch, $condition->matches(new Context()));
    }

    public function lessThanProvider(): array
    {
        return [
            'lesser integer' => [3, 5, true],
            'equal integer' => [5, 5, false],
            'greater integer' => [10, 5, false],
            'negative comparison' => [-10, -5, true],
        ];
    }

    /**
     * @dataProvider lessThanOrEqualProvider
     */
    public function testLessThanOrEqualOperator($actual, $expected, bool $shouldMatch): void
    {
        $condition = $this->createTestCondition([
            'operator' => '<=',
            'value' => $expected,
            '_test_actual_value' => $actual,
        ]);

        $this->assertEquals($shouldMatch, $condition->matches(new Context()));
    }

    public function lessThanOrEqualProvider(): array
    {
        return [
            'lesser' => [3, 5, true],
            'equal' => [5, 5, true],
            'greater' => [10, 5, false],
        ];
    }

    // ============================================
    // Pattern Matching Operators Tests
    // ============================================

    /**
     * @dataProvider likePatternProvider
     */
    public function testLikeOperator(string $actual, string $pattern, bool $shouldMatch): void
    {
        $condition = $this->createTestCondition([
            'operator' => 'LIKE',
            'value' => $pattern,
            '_test_actual_value' => $actual,
        ]);

        $this->assertEquals(
            $shouldMatch,
            $condition->matches(new Context()),
            "Pattern '{$pattern}' should " . ($shouldMatch ? '' : 'not ') . "match '{$actual}'"
        );
    }

    public function likePatternProvider(): array
    {
        return [
            'exact match' => ['test', 'test', true],
            'wildcard prefix' => ['test123', 'test*', true],
            'wildcard suffix' => ['123test', '*test', true],
            'wildcard both' => ['hello world test', '*world*', true],
            'single char wildcard' => ['test', 'tes?', true],
            'no match' => ['test', 'other', false],
            'case insensitive' => ['TEST', 'test', true],
            'empty pattern' => ['test', '', false],
            'empty actual' => ['', '*', true],
            'multiple wildcards' => ['foo bar baz', 'foo*ba?', true],
        ];
    }

    public function testNotLikeOperator(): void
    {
        $condition = $this->createTestCondition([
            'operator' => 'NOT LIKE',
            'value' => 'test*',
            '_test_actual_value' => 'other',
        ]);

        $this->assertTrue($condition->matches(new Context()));

        $condition2 = $this->createTestCondition([
            'operator' => 'NOT LIKE',
            'value' => 'test*',
            '_test_actual_value' => 'test123',
        ]);

        $this->assertFalse($condition2->matches(new Context()));
    }

    /**
     * @dataProvider regexpPatternProvider
     */
    public function testRegexpOperator(string $actual, string $pattern, bool $shouldMatch): void
    {
        $condition = $this->createTestCondition([
            'operator' => 'REGEXP',
            'value' => $pattern,
            '_test_actual_value' => $actual,
        ]);

        $this->assertEquals($shouldMatch, $condition->matches(new Context()));
    }

    public function regexpPatternProvider(): array
    {
        return [
            'regex pattern' => ['test123', '/test\d+/', true],
            'regex no match' => ['test', '/\d+/', false],
            'wildcard pattern' => ['test123', 'test*', true],
            'invalid regex handled' => ['test', '/[/', false], // Invalid regex should not crash
        ];
    }

    // ============================================
    // Array Operators Tests
    // ============================================

    /**
     * @dataProvider inOperatorProvider
     */
    public function testInOperator($actual, $expected, bool $shouldMatch): void
    {
        $condition = $this->createTestCondition([
            'operator' => 'IN',
            'value' => $expected,
            '_test_actual_value' => $actual,
        ]);

        $this->assertEquals($shouldMatch, $condition->matches(new Context()));
    }

    public function inOperatorProvider(): array
    {
        return [
            'string in array' => ['apple', ['apple', 'banana', 'cherry'], true],
            'string not in array' => ['grape', ['apple', 'banana'], false],
            'number in array' => [2, [1, 2, 3], true],
            'string number match' => ['2', [1, 2, 3], true],
            'empty array' => ['test', [], false],
            'single value treated as array' => ['test', 'test', true],
        ];
    }

    public function testNotInOperatorViaCompareValues(): void
    {
        // Test NOT IN via static compare_values method
        $this->assertTrue(BaseCondition::compare_values('grape', ['apple', 'banana'], 'NOT IN'));
        $this->assertFalse(BaseCondition::compare_values('apple', ['apple', 'banana'], 'NOT IN'));
    }

    // ============================================
    // Existence Operators Tests
    // ============================================

    /**
     * @dataProvider existsOperatorProvider
     */
    public function testExistsOperator($value, bool $shouldExist): void
    {
        $condition = $this->createTestCondition([
            'operator' => 'EXISTS',
            'value' => '',
            '_test_actual_value' => $value,
        ]);

        $this->assertEquals($shouldExist, $condition->matches(new Context()));
    }

    public function existsOperatorProvider(): array
    {
        return [
            'non-empty string' => ['test', true],
            'empty string' => ['', false],
            'zero string' => ['0', true],
            'zero integer' => [0, true],
            'null' => [null, false],
            'false' => [false, false],
            'true' => [true, true],
            'array with values' => [[1, 2, 3], true],
            'empty array' => [[], false],
        ];
    }

    /**
     * @dataProvider notExistsOperatorProvider
     */
    public function testNotExistsOperator($value, bool $shouldNotExist): void
    {
        $condition = $this->createTestCondition([
            'operator' => 'NOT EXISTS',
            'value' => '',
            '_test_actual_value' => $value,
        ]);

        $this->assertEquals($shouldNotExist, $condition->matches(new Context()));
    }

    public function notExistsOperatorProvider(): array
    {
        return [
            'non-empty string' => ['test', false],
            'empty string' => ['', true],
            'zero string' => ['0', false],
            'zero integer' => [0, false],
            'null' => [null, true],
        ];
    }

    // ============================================
    // Boolean Operators Tests
    // ============================================

    /**
     * @dataProvider isOperatorProvider
     */
    public function testIsOperator($actual, $expected, bool $shouldMatch): void
    {
        $condition = $this->createTestCondition([
            'operator' => 'IS',
            'value' => $expected,
            '_test_actual_value' => $actual,
        ]);

        $this->assertEquals($shouldMatch, $condition->matches(new Context()));
    }

    public function isOperatorProvider(): array
    {
        return [
            'both true' => [true, true, true],
            'both false' => [false, false, true],
            'true vs false' => [true, false, false],
            'truthy string' => ['test', true, true],
            'empty string vs false' => ['', false, true],
            'one vs true' => [1, true, true],
            'zero vs false' => [0, false, true],
        ];
    }

    public function testIsNotOperator(): void
    {
        $condition = $this->createTestCondition([
            'operator' => 'IS NOT',
            'value' => true,
            '_test_actual_value' => false,
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    // ============================================
    // Unknown Operator Tests
    // ============================================

    public function testUnknownOperatorReturnsFalse(): void
    {
        // Test that unknown operators return false via static method
        $this->assertFalse(BaseCondition::compare_values('test', 'test', 'UNKNOWN_OP'));
    }

    // ============================================
    // Operator Normalization Tests
    // ============================================

    public function testOperatorNormalization(): void
    {
        // Lowercase operator should be normalized to uppercase
        $condition = $this->createTestCondition([
            'operator' => 'like',
            'value' => 'test*',
            '_test_actual_value' => 'test123',
        ]);

        $this->assertTrue($condition->matches(new Context()));

        // Operator with whitespace
        $condition2 = $this->createTestCondition([
            'operator' => '  >=  ',
            'value' => 5,
            '_test_actual_value' => 10,
        ]);

        $this->assertTrue($condition2->matches(new Context()));
    }

    // ============================================
    // Match Type Tests (all/any/none)
    // ============================================

    public function testMatchTypeAllWithAllMatching(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => ['test', 'test', 'test'],
            'match_type' => 'all',
            '_test_actual_value' => 'test',
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testMatchTypeAllWithOneNotMatching(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => ['test', 'other', 'test'],
            'match_type' => 'all',
            '_test_actual_value' => 'test',
        ]);

        $this->assertFalse($condition->matches(new Context()));
    }

    public function testMatchTypeAnyWithOneMatching(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => ['other', 'test', 'another'],
            'match_type' => 'any',
            '_test_actual_value' => 'test',
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testMatchTypeAnyWithNoneMatching(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => ['other', 'another', 'different'],
            'match_type' => 'any',
            '_test_actual_value' => 'test',
        ]);

        $this->assertFalse($condition->matches(new Context()));
    }

    public function testMatchTypeNoneWithNoneMatching(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => ['other', 'another', 'different'],
            'match_type' => 'none',
            '_test_actual_value' => 'test',
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testMatchTypeNoneWithOneMatching(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => ['other', 'test', 'another'],
            'match_type' => 'none',
            '_test_actual_value' => 'test',
        ]);

        $this->assertFalse($condition->matches(new Context()));
    }

    public function testDefaultMatchTypeIsAny(): void
    {
        // When match_type is not specified, it should default to 'any'
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => ['other', 'test'],
            '_test_actual_value' => 'test',
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    // ============================================
    // Static compare_values Method Tests
    // ============================================

    public function testCompareValuesStaticMethodEquality(): void
    {
        $this->assertTrue(BaseCondition::compare_values('test', 'test', '='));
        $this->assertFalse(BaseCondition::compare_values('test', 'other', '='));
    }

    public function testCompareValuesStaticMethodGreaterThan(): void
    {
        $this->assertTrue(BaseCondition::compare_values(10, 5, '>'));
        $this->assertFalse(BaseCondition::compare_values(5, 10, '>'));
    }

    public function testCompareValuesStaticMethodLike(): void
    {
        $this->assertTrue(BaseCondition::compare_values('test123', 'test*', 'LIKE'));
        $this->assertFalse(BaseCondition::compare_values('other', 'test*', 'LIKE'));
    }

    // ============================================
    // Edge Cases and Type Coercion Tests
    // ============================================

    public function testNullValueInConfig(): void
    {
        // When value key exists but is null
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => null,
            '_test_actual_value' => '',
        ]);

        // null should be coerced to empty string
        $this->assertTrue($condition->matches(new Context()));
    }

    public function testMissingValueInConfig(): void
    {
        // When value key doesn't exist, should default to empty string
        $condition = $this->createTestCondition([
            'operator' => '=',
            '_test_actual_value' => '',
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testNonStringOperatorDefaultsToEquals(): void
    {
        $condition = $this->createTestCondition([
            'operator' => 123, // Non-string operator
            'value' => 'test',
            '_test_actual_value' => 'test',
        ]);

        // Should default to '=' operator
        $this->assertTrue($condition->matches(new Context()));
    }

    public function testArrayActualValueWithScalarExpected(): void
    {
        // Arrays should be converted to empty string for comparison
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => '',
            '_test_actual_value' => ['array', 'value'],
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testBooleanValueComparison(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => '1',
            '_test_actual_value' => true,
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testEmptyStringVsZeroComparison(): void
    {
        // Empty string and zero should not be equal
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => '0',
            '_test_actual_value' => '',
        ]);

        $this->assertFalse($condition->matches(new Context()));
    }

    public function testFloatStringComparison(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '>',
            'value' => '5.5',
            '_test_actual_value' => '10.2',
        ]);

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testInvalidMatchTypeDefaultsToAny(): void
    {
        $condition = $this->createTestCondition([
            'operator' => '=',
            'value' => ['other', 'test'],
            'match_type' => 'invalid_type',
            '_test_actual_value' => 'test',
        ]);

        // Should default to 'any' behavior
        $this->assertTrue($condition->matches(new Context()));
    }
}
