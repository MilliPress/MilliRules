<?php

namespace MilliRules\Tests\Unit\Packages\WordPress\Conditions;

// Load WordPress test function helpers
require_once __DIR__ . '/wordpress-test-functions.php';

use MilliRules\Tests\TestCase;
use MilliRules\Packages\WordPress\Conditions\IsConditional;
use MilliRules\Context;

/**
 * @covers \MilliRules\Packages\WordPress\Conditions\IsConditional
 */
class IsConditionalTest extends TestCase
{
    /**
     * Ensure boolean mode without args uses is_*() with no parameters
     * and compares the result to TRUE by default.
     */
    public function testBooleanModeNoArgsDefaultsToTrue(): void
    {
        $config = array(
            'type' => 'is_404',
            // no value, no operator, no _args -> boolean mode, value=true, operator=IS
        );

        $condition = new IsConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Boolean mode when first raw arg is boolean.
     * Example: ->is_404(false) should compare is_404() IS FALSE.
     */
    public function testBooleanModeWithBooleanRawArg(): void
    {
        $config = array(
            'type'      => 'is_404',
            'args' => array(false),
        );

        $condition = new IsConditional($config, new Context());

        // is_404() returns true, value=false, operator=IS -> should NOT match.
        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Function-call mode: arguments-only, default operator 'IS'.
     * Example: ->is_tax('genre', 'sci-fi')
     *          calls is_tax('genre','sci-fi') and compares result IS TRUE.
     */
    public function testFunctionCallModeDefaultOperator(): void
    {
        $config = array(
            'type'      => 'is_tax',
            'args' => array('genre', 'sci-fi'),
        );

        $condition = new IsConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Function-call mode with trailing operator.
     * Example: ->is_tax('genre','sci-fi','!=')
     *          calls is_tax('genre','sci-fi') and compares result != TRUE.
     */
    public function testFunctionCallModeWithNotEqualOperator(): void
    {
        $config = array(
            'type'      => 'is_tax',
            'args' => array('genre', 'sci-fi', '!='),
        );

        $condition = new IsConditional($config, new Context());

        // is_tax(...) returns true, value=true, operator='!=' -> should not match.
        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Function-call mode with single argument.
     * Example: ->is_singular('page')
     *          calls is_singular('page') and compares result IS TRUE.
     */
    public function testFunctionCallModeWithSingleArgument(): void
    {
        $config = array(
            'type'      => 'is_singular',
            'args' => array('page'),
        );

        $condition = new IsConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Ensure non-matching arguments return false.
     * Example: ->is_singular('post')
     *          calls is_singular('post') which returns false.
     */
    public function testFunctionCallModeWithNonMatchingArgument(): void
    {
        $config = array(
            'type'      => 'is_singular',
            'args' => array('post'),
        );

        $condition = new IsConditional($config, new Context());

        $this->assertFalse($condition->matches(new Context()));
    }
}
