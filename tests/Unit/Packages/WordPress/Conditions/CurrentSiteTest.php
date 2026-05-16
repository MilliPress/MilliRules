<?php

namespace MilliRules\Tests\Unit\Packages\WordPress\Conditions;

require_once __DIR__ . '/wordpress-test-functions.php';

use MilliRules\Tests\TestCase;
use MilliRules\Packages\WordPress\Conditions\CurrentSite;
use MilliRules\Conditions\ConditionMeta;
use MilliRules\Context;

/**
 * @covers \MilliRules\Packages\WordPress\Conditions\CurrentSite
 */
class CurrentSiteTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['millirules_test_current_blog_id']);
        parent::tearDown();
    }

    public function testSingleSiteDefaultsToBlogOne(): void
    {
        // No global set — get_current_blog_id() returns 1 (single-site default).
        $condition = new CurrentSite(
            array('type' => 'current_site', 'value' => 1),
            new Context()
        );

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testNotEqualOnSingleSite(): void
    {
        $condition = new CurrentSite(
            array('type' => 'current_site', 'value' => 1, 'operator' => '!='),
            new Context()
        );

        $this->assertFalse($condition->matches(new Context()));
    }

    public function testInOperatorMatchesWhenBlogInList(): void
    {
        $GLOBALS['millirules_test_current_blog_id'] = 5;

        $condition = new CurrentSite(
            array('type' => 'current_site', 'value' => array(2, 5, 7), 'operator' => 'IN'),
            new Context()
        );

        $this->assertTrue($condition->matches(new Context()));
    }

    public function testInOperatorDoesNotMatchWhenBlogNotInList(): void
    {
        $GLOBALS['millirules_test_current_blog_id'] = 3;

        $condition = new CurrentSite(
            array('type' => 'current_site', 'value' => array(2, 5, 7), 'operator' => 'IN'),
            new Context()
        );

        $this->assertFalse($condition->matches(new Context()));
    }

    public function testNotInOperatorExcludes(): void
    {
        $GLOBALS['millirules_test_current_blog_id'] = 3;

        $condition = new CurrentSite(
            array('type' => 'current_site', 'value' => array(1, 2), 'operator' => 'NOT IN'),
            new Context()
        );

        $this->assertTrue($condition->matches(new Context()));
    }

    /**
     * Regression: NOT IN must return false when actual IS in the array.
     *
     * The previous matches() decomposed array values via check_multiple_values
     * with default match_type='any', which made element-wise NOT IN ['2'] true
     * (correctly) and NOT IN ['5'] false — then aggregated to true overall,
     * even though blog 5 IS in [2, 5].
     */
    public function testNotInOperatorBlocksWhenBlogInList(): void
    {
        $GLOBALS['millirules_test_current_blog_id'] = 5;

        $condition = new CurrentSite(
            array('type' => 'current_site', 'value' => array(2, 5), 'operator' => 'NOT IN'),
            new Context()
        );

        $this->assertFalse($condition->matches(new Context()));
    }

    public function testSetMetaConfiguresOperatorsAndIntegerValueArg(): void
    {
        $meta = new ConditionMeta('current_site');
        CurrentSite::set_meta($meta);

        $this->assertSame(array('=', '!=', 'IN', 'NOT IN'), $meta->get_operators());
        $this->assertSame('Current Site', $meta->get_label());

        $args = $meta->get_arguments();
        $this->assertCount(1, $args);

        $value_arg = $args[0];
        $this->assertSame('value', $value_arg->get_key());
        $this->assertSame('integer', $value_arg->get_type());
        $this->assertTrue($value_arg->is_required());
        $this->assertSame('Site ID', $value_arg->get_label());
    }
}
