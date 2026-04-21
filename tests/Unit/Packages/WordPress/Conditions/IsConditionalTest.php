<?php

namespace MilliRules\Tests\Unit\Packages\WordPress\Conditions;

// Load WordPress test function helpers
require_once __DIR__ . '/wordpress-test-functions.php';

use MilliRules\Tests\TestCase;
use MilliRules\Packages\WordPress\Conditions\IsConditional;
use MilliRules\Conditions\ConditionMeta;
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

    /**
     * Value-based mode with IS operator.
     * is_category('news') returns true → match.
     */
    public function testValueBasedIsOperator(): void
    {
        $config = array(
            'type'     => 'is_category',
            'value'    => 'news',
            'operator' => 'IS',
        );

        $condition = new IsConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Value-based mode with IS NOT operator.
     * is_category('news') returns true → negated → no match.
     */
    public function testValueBasedIsNotOperator(): void
    {
        $config = array(
            'type'     => 'is_category',
            'value'    => 'news',
            'operator' => 'IS NOT',
        );

        $condition = new IsConditional($config, new Context());

        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Value-based mode with IS operator, non-matching value.
     * is_category('sports') returns false → no match.
     */
    public function testValueBasedIsOperatorNoMatch(): void
    {
        $config = array(
            'type'     => 'is_category',
            'value'    => 'sports',
            'operator' => 'IS',
        );

        $condition = new IsConditional($config, new Context());

        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Value-based mode with IN operator (array value).
     * is_category(['news', 'sports']) returns true → match.
     */
    public function testValueBasedInOperator(): void
    {
        $config = array(
            'type'     => 'is_category',
            'value'    => array('news', 'sports'),
            'operator' => 'IN',
        );

        $condition = new IsConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Value-based mode with NOT IN operator (array value).
     * is_category(['news', 'sports']) returns true → negated → no match.
     */
    public function testValueBasedNotInOperator(): void
    {
        $config = array(
            'type'     => 'is_category',
            'value'    => array('news', 'sports'),
            'operator' => 'NOT IN',
        );

        $condition = new IsConditional($config, new Context());

        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Value-based mode with IN operator, non-matching array.
     * is_category(['tech', 'sports']) returns false → no match.
     */
    public function testValueBasedInOperatorNoMatch(): void
    {
        $config = array(
            'type'     => 'is_category',
            'value'    => array('tech', 'sports'),
            'operator' => 'IN',
        );

        $condition = new IsConditional($config, new Context());

        $this->assertFalse($condition->matches(new Context()));
    }

    /**
     * Value-based mode with NOT IN operator, non-matching array.
     * is_category(['tech', 'sports']) returns false → negated → match.
     */
    public function testValueBasedNotInOperatorMatch(): void
    {
        $config = array(
            'type'     => 'is_category',
            'value'    => array('tech', 'sports'),
            'operator' => 'NOT IN',
        );

        $condition = new IsConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    // -----------------------------------------------------------------
    // Metadata: docblock-based operator detection
    // -----------------------------------------------------------------

    /**
     * Parameterless function (is_404) gets boolean operators only, no arguments.
     */
    public function testMetaNoParamsGetsBooleanOperators(): void
    {
        $meta = new ConditionMeta('is_404');
        IsConditional::set_meta($meta);

        $array = $meta->to_array();
        $this->assertStringContainsString('404', $array['description']);
        $this->assertSame(array( 'IS', 'IS NOT' ), $array['operators']);
        $this->assertSame(array(), $array['argument_mapping']);
        $this->assertSame(array(), $array['arguments']);
    }

    /**
     * Single-param function with array type (is_category) gets IN/NOT IN
     * and a value-based argument schema with label and description.
     */
    public function testMetaArrayParamGetsInOperatorsAndSchema(): void
    {
        $meta = new ConditionMeta('is_category');
        IsConditional::set_meta($meta);

        $array = $meta->to_array();
        $this->assertStringContainsString('category archive', $array['description']);
        $this->assertSame(array( 'IS', 'IS NOT', 'IN', 'NOT IN' ), $array['operators']);
        $this->assertSame(array( 'value' ), $array['argument_mapping']);
        $this->assertCount(1, $array['arguments']);

        $arg = $array['arguments'][0];
        $this->assertSame('value', $arg['key']);
        $this->assertSame('Category', $arg['label']);
        $this->assertNotEmpty($arg['description']);
        $this->assertFalse($arg['required']);
        $this->assertSame(array( 'string', 'array' ), $arg['accepts']);
    }

    /**
     * Single-param function with scalar type (is_author) gets IS/IS NOT
     * and a properly described value argument.
     */
    public function testMetaScalarParamGetsIsOperatorsAndSchema(): void
    {
        $meta = new ConditionMeta('is_author');
        IsConditional::set_meta($meta);

        $array = $meta->to_array();
        $this->assertSame(array( 'IS', 'IS NOT' ), $array['operators']);
        $this->assertSame(array( 'value' ), $array['argument_mapping']);
        $this->assertCount(1, $array['arguments']);

        $arg = $array['arguments'][0];
        $this->assertSame('value', $arg['key']);
        $this->assertSame('Author', $arg['label']);
        $this->assertStringContainsString('Author ID', $arg['description']);
        $this->assertFalse($arg['required']);
        $this->assertSame(array( 'string' ), $arg['accepts']);
    }

    /**
     * Multi-param function (is_tax) gets IS/IS NOT only (args mode),
     * indexed argument schemas with accepts reflecting docblock types.
     */
    public function testMetaMultiParamGetsIndexedSchemas(): void
    {
        $meta = new ConditionMeta('is_tax');
        IsConditional::set_meta($meta);

        $array = $meta->to_array();
        // Args mode: operators describe boolean result, no IN/NOT IN.
        $this->assertSame(array( 'IS', 'IS NOT' ), $array['operators']);
        $this->assertSame('args', $array['mode']);
        $this->assertSame(array(), $array['argument_mapping']);
        $this->assertCount(2, $array['arguments']);

        $this->assertSame(0, $array['arguments'][0]['key']);
        $this->assertSame('Taxonomy', $array['arguments'][0]['label']);
        $this->assertSame(array( 'string', 'array' ), $array['arguments'][0]['accepts']);

        $this->assertSame(1, $array['arguments'][1]['key']);
        $this->assertSame('Term', $array['arguments'][1]['label']);
        $this->assertSame(array( 'string', 'array' ), $array['arguments'][1]['accepts']);
    }

    /**
     * has_post_thumbnail: single optional param, no array type → IS/IS NOT,
     * value argument is not required, has description from docblock.
     */
    public function testMetaHasPostThumbnailSchema(): void
    {
        $meta = new ConditionMeta('has_post_thumbnail');
        IsConditional::set_meta($meta);

        $array = $meta->to_array();
        $this->assertSame(array( 'IS', 'IS NOT' ), $array['operators']);
        $this->assertSame(array( 'value' ), $array['argument_mapping']);
        $this->assertCount(1, $array['arguments']);

        $arg = $array['arguments'][0];
        $this->assertSame('value', $arg['key']);
        $this->assertSame('Post', $arg['label']);
        $this->assertNotEmpty($arg['description']);
        $this->assertFalse($arg['required']);
    }

    /**
     * is_author in boolean mode (no value) still works.
     */
    public function testIsAuthorBooleanMode(): void
    {
        $config = array( 'type' => 'is_author' );
        $condition = new IsConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * is_author in value-based mode with matching value.
     */
    public function testIsAuthorValueBasedMatch(): void
    {
        $config = array(
            'type'     => 'is_author',
            'value'    => 'john',
            'operator' => 'IS',
        );
        $condition = new IsConditional($config, new Context());

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * is_author in value-based mode with non-matching value.
     */
    public function testIsAuthorValueBasedNoMatch(): void
    {
        $config = array(
            'type'     => 'is_author',
            'value'    => 'jane',
            'operator' => 'IS',
        );
        $condition = new IsConditional($config, new Context());

        $this->assertFalse($condition->matches(new Context()));
    }
}
