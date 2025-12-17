<?php

namespace MilliRules\Tests\Unit\Packages\WordPress\Conditions;

// Load WordPress test function helpers
require_once __DIR__ . '/wordpress-test-functions.php';

use MilliRules\Tests\TestCase;
use MilliRules\Packages\WordPress\Conditions\HasConditional;
use MilliRules\Context;

/**
 * @covers \MilliRules\Packages\WordPress\Conditions\HasConditional
 */
class HasConditionalTest extends TestCase
{
    /**
     * Ensure boolean mode without args uses has_*() with no parameters
     * and compares the result to TRUE by default.
     */
    public function testBooleanModeNoArgsDefaultsToTrue(): void
    {
        $config = array(
            'type' => 'has_post_thumbnail',
            // no value, no operator, no args -> boolean mode, value=true, operator=IS
        );

        $condition = new HasConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Boolean mode when first raw arg is boolean.
     * Example: ->has_post_thumbnail(false) should compare has_post_thumbnail() IS FALSE.
     */
    public function testBooleanModeWithBooleanRawArg(): void
    {
        $config = array(
            'type' => 'has_post_thumbnail',
            'args' => array(false),
        );

        $condition = new HasConditional($config, new Context());

        // has_post_thumbnail() returns true, value=false, operator=IS -> should NOT match.
        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Function-call mode with single argument.
     * Example: ->has_block('core/paragraph')
     *          calls has_block('core/paragraph') and compares result IS TRUE.
     */
    public function testFunctionCallModeWithSingleArgument(): void
    {
        $config = array(
            'type' => 'has_block',
            'args' => array('core/paragraph'),
        );

        $condition = new HasConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Ensure non-matching arguments return false.
     * Example: ->has_block('core/heading')
     *          calls has_block('core/heading') which returns false.
     */
    public function testFunctionCallModeWithNonMatchingArgument(): void
    {
        $config = array(
            'type' => 'has_block',
            'args' => array('core/heading'),
        );

        $condition = new HasConditional($config, new Context());

        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Function-call mode: arguments-only, default operator 'IS'.
     * Example: ->has_term('news', 'category')
     *          calls has_term('news','category') and compares result IS TRUE.
     */
    public function testFunctionCallModeWithMultipleArguments(): void
    {
        $config = array(
            'type' => 'has_term',
            'args' => array('news', 'category'),
        );

        $condition = new HasConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Function-call mode with non-matching multiple arguments.
     * Example: ->has_term('sports', 'category')
     *          calls has_term('sports','category') which returns false.
     */
    public function testFunctionCallModeWithNonMatchingMultipleArguments(): void
    {
        $config = array(
            'type' => 'has_term',
            'args' => array('sports', 'category'),
        );

        $condition = new HasConditional($config, new Context());

        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Function-call mode with trailing operator.
     * Example: ->has_block('core/paragraph','!=')
     *          calls has_block('core/paragraph') and compares result != TRUE.
     */
    public function testFunctionCallModeWithNotEqualOperator(): void
    {
        $config = array(
            'type' => 'has_block',
            'args' => array('core/paragraph', '!='),
        );

        $condition = new HasConditional($config, new Context());

        // has_block('core/paragraph') returns true, value=true, operator='!=' -> should not match.
        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Function-call mode with 'IS NOT' operator.
     * Example: ->has_block('core/heading', 'IS NOT')
     *          calls has_block('core/heading') and compares result IS NOT TRUE.
     */
    public function testFunctionCallModeWithIsNotOperator(): void
    {
        $config = array(
            'type' => 'has_block',
            'args' => array('core/heading', 'IS NOT'),
        );

        $condition = new HasConditional($config, new Context());

        // has_block('core/heading') returns false, value=true, operator='IS NOT' -> should match.
        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Test has_shortcode function with multiple arguments.
     * Example: ->has_shortcode('some content', 'gallery')
     *          calls has_shortcode('some content','gallery') and compares result IS TRUE.
     */
    public function testHasShortcodeWithMultipleArguments(): void
    {
        $config = array(
            'type' => 'has_shortcode',
            'args' => array('some content', 'gallery'),
        );

        $condition = new HasConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Test has_shortcode function with non-matching shortcode.
     */
    public function testHasShortcodeWithNonMatchingTag(): void
    {
        $config = array(
            'type' => 'has_shortcode',
            'args' => array('some content', 'video'),
        );

        $condition = new HasConditional($config, new Context());

        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Test that non-existent function returns false.
     */
    public function testNonExistentFunctionReturnsFalse(): void
    {
        $config = array(
            'type' => 'has_nonexistent_function',
        );

        $condition = new HasConditional($config, new Context());

        $this->assertFalse($condition->matches(new Context()));
    }
}
