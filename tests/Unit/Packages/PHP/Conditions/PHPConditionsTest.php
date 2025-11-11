<?php

namespace MilliRules\Tests\Unit\Packages\PHP\Conditions;

use MilliRules\Tests\TestCase;
use MilliRules\Packages\PHP\Conditions\RequestUrl;
use MilliRules\Packages\PHP\Conditions\Cookie;
use MilliRules\Packages\PHP\Conditions\RequestMethod;
use MilliRules\Packages\PHP\Conditions\RequestHeader;
use MilliRules\Packages\PHP\Conditions\RequestParam;
use MilliRules\Packages\PHP\Conditions\Constant;

/**
 * Comprehensive tests for PHP Package Condition classes
 *
 * Tests RequestUrl, Cookie, RequestMethod, RequestHeader, RequestParam, and Constant conditions
 */
class PHPConditionsTest extends TestCase
{
    // ============================================
    // RequestUrl Tests
    // ============================================

    public function testRequestUrlExactMatch(): void
    {
        $context = ['request' => ['uri' => '/admin/posts']];
        $condition = new RequestUrl(['value' => '/admin/posts', 'operator' => '='], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestUrlExactMismatch(): void
    {
        $context = ['request' => ['uri' => '/admin/posts']];
        $condition = new RequestUrl(['value' => '/admin/users', 'operator' => '='], $context);

        $this->assertFalse($condition->matches($context));
    }

    public function testRequestUrlWildcardPattern(): void
    {
        $context = ['request' => ['uri' => '/admin/posts/edit']];
        $condition = new RequestUrl(['value' => '/admin/*', 'operator' => 'LIKE'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestUrlWildcardNoMatch(): void
    {
        $context = ['request' => ['uri' => '/public/page']];
        $condition = new RequestUrl(['value' => '/admin/*', 'operator' => 'LIKE'], $context);

        $this->assertFalse($condition->matches($context));
    }

    public function testRequestUrlRegexMatch(): void
    {
        $context = ['request' => ['uri' => '/post-123']];
        $condition = new RequestUrl(['value' => '/^\\/post-\\d+$/', 'operator' => 'REGEXP'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestUrlInArray(): void
    {
        $context = ['request' => ['uri' => '/login']];
        $condition = new RequestUrl([
            'value' => ['/login', '/register', '/forgot-password'],
            'operator' => 'IN'
        ], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestUrlMissingContextDefaultsEmpty(): void
    {
        $context = [];
        $condition = new RequestUrl(['value' => '', 'operator' => '='], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestUrlGetType(): void
    {
        $condition = new RequestUrl([], []);
        $this->assertEquals('request_url', $condition->get_type());
    }

    // ============================================
    // Cookie Tests
    // ============================================

    public function testCookieExistsWithExactName(): void
    {
        $context = ['request' => ['cookies' => ['session_id' => 'abc123']]];
        $condition = new Cookie(['name' => 'session_id'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testCookieNotExists(): void
    {
        $context = ['request' => ['cookies' => ['other_cookie' => 'value']]];
        $condition = new Cookie(['name' => 'session_id'], $context);

        $this->assertFalse($condition->matches($context));
    }

    public function testCookieExistsWithWildcard(): void
    {
        $context = ['request' => ['cookies' => ['session_abc' => 'value', 'other' => 'val']]];
        $condition = new Cookie(['name' => 'session_*'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testCookieExistsWithRegex(): void
    {
        $context = ['request' => ['cookies' => ['wp_user_123' => 'value']]];
        $condition = new Cookie(['name' => '/^wp_user_\\d+$/'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testCookieValueExactMatch(): void
    {
        $context = ['request' => ['cookies' => ['user_role' => 'admin']]];
        $condition = new Cookie(['name' => 'user_role', 'value' => 'admin', 'operator' => '='], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testCookieValueMismatch(): void
    {
        $context = ['request' => ['cookies' => ['user_role' => 'editor']]];
        $condition = new Cookie(['name' => 'user_role', 'value' => 'admin', 'operator' => '='], $context);

        $this->assertFalse($condition->matches($context));
    }

    public function testCookieValuePatternMatch(): void
    {
        $context = ['request' => ['cookies' => ['preferences' => 'dark_mode_enabled']]];
        $condition = new Cookie(['name' => 'preferences', 'value' => 'dark_*', 'operator' => 'LIKE'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testCookieValueIn(): void
    {
        $context = ['request' => ['cookies' => ['lang' => 'de']]];
        $condition = new Cookie([
            'name' => 'lang',
            'value' => ['en', 'de', 'fr'],
            'operator' => 'IN'
        ], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testCookieNotExistsOperator(): void
    {
        $context = ['request' => ['cookies' => ['other' => 'value']]];
        $condition = new Cookie(['name' => 'tracking', 'operator' => 'IS NOT'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testCookieNotExistsWithExisting(): void
    {
        $context = ['request' => ['cookies' => ['tracking' => 'enabled']]];
        $condition = new Cookie(['name' => 'tracking', 'operator' => 'IS NOT'], $context);

        $this->assertFalse($condition->matches($context));
    }

    public function testCookieCaseInsensitiveMatch(): void
    {
        $context = ['request' => ['cookies' => ['Session_ID' => 'abc123']]];
        $condition = new Cookie(['name' => 'session_id'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testCookieNoCookiesInContext(): void
    {
        $context = [];
        $condition = new Cookie(['name' => 'any_cookie'], $context);

        $this->assertFalse($condition->matches($context));
    }

    public function testCookieGetType(): void
    {
        $condition = new Cookie([], []);
        $this->assertEquals('cookie', $condition->get_type());
    }

    // ============================================
    // RequestMethod Tests
    // ============================================

    public function testRequestMethodGet(): void
    {
        $context = ['request' => ['method' => 'GET']];
        $condition = new RequestMethod(['value' => 'GET', 'operator' => '='], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestMethodPost(): void
    {
        $context = ['request' => ['method' => 'POST']];
        $condition = new RequestMethod(['value' => 'POST', 'operator' => '='], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestMethodCaseInsensitive(): void
    {
        $context = ['request' => ['method' => 'get']];
        $condition = new RequestMethod(['value' => 'GET', 'operator' => '='], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestMethodNotEqual(): void
    {
        $context = ['request' => ['method' => 'GET']];
        $condition = new RequestMethod(['value' => 'POST', 'operator' => '!='], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestMethodInArray(): void
    {
        $context = ['request' => ['method' => 'GET']];
        $condition = new RequestMethod([
            'value' => ['GET', 'HEAD', 'OPTIONS'],
            'operator' => 'IN'
        ], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestMethodNotInArray(): void
    {
        $context = ['request' => ['method' => 'DELETE']];
        $condition = new RequestMethod([
            'value' => ['GET', 'POST'],
            'operator' => 'IN'
        ], $context);

        $this->assertFalse($condition->matches($context));
    }

    public function testRequestMethodMissingContext(): void
    {
        $context = [];
        $condition = new RequestMethod(['value' => '', 'operator' => '='], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestMethodGetType(): void
    {
        $condition = new RequestMethod([], []);
        $this->assertEquals('request_method', $condition->get_type());
    }

    // ============================================
    // RequestHeader Tests
    // ============================================

    public function testRequestHeaderExists(): void
    {
        $context = ['request' => ['headers' => ['user-agent' => 'Mozilla/5.0']]];
        $condition = new RequestHeader(['name' => 'user-agent', 'operator' => 'EXISTS'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestHeaderNotExists(): void
    {
        $context = ['request' => ['headers' => ['accept' => 'text/html']]];
        $condition = new RequestHeader(['name' => 'referer', 'operator' => 'NOT EXISTS'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestHeaderValueMatch(): void
    {
        $context = ['request' => ['headers' => ['accept' => 'application/json']]];
        $condition = new RequestHeader([
            'name' => 'accept',
            'value' => 'application/json',
            'operator' => '='
        ], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestHeaderValuePattern(): void
    {
        $context = ['request' => ['headers' => ['user-agent' => 'Mozilla/5.0 Chrome']]];
        $condition = new RequestHeader([
            'name' => 'user-agent',
            'value' => '*Chrome*',
            'operator' => 'LIKE'
        ], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestHeaderCaseInsensitive(): void
    {
        $context = ['request' => ['headers' => ['Content-Type' => 'text/html']]];
        $condition = new RequestHeader(['name' => 'content-type', 'operator' => 'EXISTS'], $context);

        $this->assertTrue($condition->matches($context));
    }

    // ============================================
    // RequestParam Tests
    // ============================================

    public function testRequestParamExists(): void
    {
        $context = ['request' => ['params' => ['page' => '1']]];
        $condition = new RequestParam(['name' => 'page', 'operator' => 'EXISTS'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestParamNotExists(): void
    {
        $context = ['request' => ['params' => ['other' => 'value']]];
        $condition = new RequestParam(['name' => 'page', 'operator' => 'NOT EXISTS'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestParamValueMatch(): void
    {
        $context = ['request' => ['params' => ['action' => 'edit']]];
        $condition = new RequestParam([
            'name' => 'action',
            'value' => 'edit',
            'operator' => '='
        ], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testRequestParamValueMismatch(): void
    {
        $context = ['request' => ['params' => ['action' => 'delete']]];
        $condition = new RequestParam([
            'name' => 'action',
            'value' => 'edit',
            'operator' => '='
        ], $context);

        $this->assertFalse($condition->matches($context));
    }

    public function testRequestParamInArray(): void
    {
        $context = ['request' => ['params' => ['status' => 'published']]];
        $condition = new RequestParam([
            'name' => 'status',
            'value' => ['published', 'draft', 'pending'],
            'operator' => 'IN'
        ], $context);

        $this->assertTrue($condition->matches($context));
    }

    // ============================================
    // Constant Tests
    // ============================================

    public function testConstantDefined(): void
    {
        define('TEST_CONSTANT_123', 'test_value');

        $context = [];
        $condition = new Constant(['name' => 'TEST_CONSTANT_123', 'operator' => 'EXISTS'], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testConstantNotDefined(): void
    {
        $context = [];
        $condition = new Constant(['name' => 'NONEXISTENT_CONSTANT', 'operator' => 'EXISTS'], $context);

        $this->assertFalse($condition->matches($context));
    }

    public function testConstantValueMatch(): void
    {
        define('TEST_CONSTANT_456', 'expected_value');

        $context = [];
        $condition = new Constant([
            'name' => 'TEST_CONSTANT_456',
            'value' => 'expected_value',
            'operator' => '='
        ], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testConstantValueMismatch(): void
    {
        define('TEST_CONSTANT_789', 'actual_value');

        $context = [];
        $condition = new Constant([
            'name' => 'TEST_CONSTANT_789',
            'value' => 'expected_value',
            'operator' => '='
        ], $context);

        $this->assertFalse($condition->matches($context));
    }

    public function testConstantBooleanTrue(): void
    {
        define('TEST_BOOL_CONSTANT', true);

        $context = [];
        $condition = new Constant([
            'name' => 'TEST_BOOL_CONSTANT',
            'value' => true,
            'operator' => 'IS'
        ], $context);

        $this->assertTrue($condition->matches($context));
    }

    // ============================================
    // Edge Cases Tests
    // ============================================

    public function testCookieEmptyName(): void
    {
        $context = ['request' => ['cookies' => ['cookie1' => 'value']]];
        $condition = new Cookie(['name' => ''], $context);

        // Empty name should check if any cookies exist
        $this->assertTrue($condition->matches($context));
    }

    public function testCookieNonStringValues(): void
    {
        $context = ['request' => ['cookies' => ['num' => 123, 'bool' => true]]];
        $condition = new Cookie(['name' => 'num'], $context);

        // Non-string values should be sanitized
        $this->assertTrue($condition->matches($context));
    }

    public function testRequestUrlNonStringUri(): void
    {
        $context = ['request' => ['uri' => 123]];
        $condition = new RequestUrl(['value' => '', 'operator' => '='], $context);

        // Non-string URIs should be converted to empty string
        $this->assertTrue($condition->matches($context));
    }

    public function testRequestMethodNonStringMethod(): void
    {
        $context = ['request' => ['method' => null]];
        $condition = new RequestMethod(['value' => '', 'operator' => '='], $context);

        $this->assertTrue($condition->matches($context));
    }

    public function testCookieWithAlternativeConfigKey(): void
    {
        // Test 'cookie' key instead of 'name'
        $context = ['request' => ['cookies' => ['test' => 'value']]];
        $condition = new Cookie(['cookie' => 'test'], $context);

        $this->assertTrue($condition->matches($context));
    }
}
