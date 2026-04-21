<?php

/**
 * Cookie Condition
 *
 * Checks client cookie existence and values.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules\Packages\PHP\Conditions;

use MilliRules\Conditions\BaseCondition;
use MilliRules\Conditions\ConditionMeta;
use MilliRules\Context;

/**
 * Class Cookie
 *
 * Checks if a cookie exists and/or matches a value.
 * Performs case-insensitive cookie name matching for maximum compatibility.
 *
 * Cookie name matching supports:
 * - Regex patterns (enclosed in forward slashes): ['name' => '/^session_[a-z]+$/']
 * - Wildcard patterns (* and ?): ['name' => 'session_*'] or ['name' => 'user_?']
 * - Exact match (case-insensitive): ['name' => 'session_id']
 *
 * Supported operators:
 * - EXISTS/IS: Check if cookie exists (when no value specified)
 * - IS NOT/!=: Check if cookie doesn't exist (when no value specified)
 * - =: Exact value match
 * - !=: Value doesn't match
 * - LIKE: Pattern matching with wildcards (* and ?)
 * - REGEXP: Regular expression matching
 * - IN: Check if value is in array
 *
 * Examples:
 * Array syntax:
 * - Check existence: ['type' => 'cookie', 'name' => 'session_id']
 * - Check with wildcard: ['type' => 'cookie', 'name' => 'session_*']
 * - Check with regex: ['type' => 'cookie', 'name' => '/^wp_[a-z]+$/']
 * - Check value: ['type' => 'cookie', 'name' => 'user_role', 'value' => 'admin']
 * - Pattern match: ['type' => 'cookie', 'name' => 'preferences', 'value' => 'dark_*', 'operator' => 'LIKE']
 * - Multiple values: ['type' => 'cookie', 'name' => 'lang', 'value' => ['en', 'de', 'fr'], 'operator' => 'IN']
 * - Cookie doesn't exist: ['type' => 'cookie', 'name' => 'tracking', 'operator' => 'IS NOT']
 *
 * Builder syntax:
 * - ->cookie('session_id') // exists
 * - ->cookie('session_*') // wildcard match
 * - ->cookie('/^wp_.*$/') // regex match
 * - ->cookie('user_role', 'admin') // exact match
 * - ->cookie('preferences', 'dark_*', 'LIKE') // pattern match
 *
 * @since 0.1.0
 */
class Cookie extends BaseCondition
{
    /**
     * Define argument mapping for Cookie condition.
     *
     * Cookie is name-based: first arg = cookie name, second arg = value to compare.
     * See BaseCondition::get_argument_mapping() for detailed explanation.
     *
     * @since 0.1.0
     *
     * @return array<int, string>
     */
    public static function get_argument_mapping(): array
    {
        return ['name', 'value'];
    }

    /**
     * Get the condition type.
     *
     * @since 0.1.0
     *
     * @return string The condition type identifier.
     */
    public function get_type(): string
    {
        return 'cookie';
    }

    /**
     * Check if the condition matches.
     *
     * Override matches() for special cookie logic including existence checks.
     *
     * @since 0.1.0
     *
     * @param Context $context The execution context.
     * @return bool True if the condition matches, false otherwise.
     */
    public function matches(Context $context): bool
    {
        $cookie_name = $this->get_cookie_name();
        $cookies     = $this->get_cookies_from_context($context);

        // If no cookie name specified, check if any cookies exist.
        if (empty($cookie_name)) {
            return ! empty($cookies);
        }

        // Check if value comparison is requested.
        $check_value = array_key_exists('value', $this->config);

        if (! $check_value) {
            // Existence checks only.
            $cookie_exists = null !== $this->find_cookie($cookie_name, $cookies);

            if ('IS NOT' === $this->operator || '!=' === $this->operator || 'NOT EXISTS' === $this->operator) {
                return ! $cookie_exists;
            }

            return $cookie_exists;
        }

        // Value comparison - use standard BaseCondition logic.
        return parent::matches($context);
    }

    /**
     * Get the actual value from context.
     *
     * @since 0.1.0
     *
     * @param Context $context The execution context.
     * @return string The cookie value or empty string if not found.
     */
    protected function get_actual_value(Context $context): string
    {
        $cookie_name = $this->get_cookie_name();

        if (empty($cookie_name)) {
            return '';
        }

        $cookies = $this->get_cookies_from_context($context);

        return $this->find_cookie($cookie_name, $cookies) ?? '';
    }

    /**
     * Get the cookie name from config.
     *
     * @since 0.1.0
     *
     * @return string The cookie name or empty string.
     */
    private function get_cookie_name(): string
    {
        $cookie_name_raw = $this->config['name'] ?? $this->config['cookie'] ?? '';
        return is_string($cookie_name_raw) ? $cookie_name_raw : '';
    }

    /**
     * Get the cookie array from context.
     *
     * @since 0.1.0
     *
     * @param Context $context The execution context.
     * @return array<string, string> The cookie array with cookie names as keys and values.
     */
    private function get_cookies_from_context(Context $context): array
    {
        // Ensure cookie data is loaded.
        $context->load('cookie');

        // Get cookie data from context.
        $cookies = $context->get('cookie', array());

        if (! is_array($cookies)) {
            return array();
        }

        return $this->sanitize_cookies($cookies);
    }

    /**
     * Sanitize the cookie array to ensure all values are strings.
     *
     * @since 0.1.0
     *
     * @param array<string, mixed> $cookies Raw cookies array.
     * @return array<string, string> Sanitized cookies with string values only.
     */
    private function sanitize_cookies(array $cookies): array
    {
        $sanitized = array();

        foreach ($cookies as $key => $value) {
            if (is_string($key) && ( is_string($value) || is_numeric($value) )) {
                $sanitized[ $key ] = (string) $value;
            }
        }

        return $sanitized;
    }

    /**
     * Find a cookie by name pattern, returning its value.
     *
     * Delegates to BaseCondition::compare_values() for pattern matching,
     * which auto-infers LIKE for wildcards (* and ?) and REGEXP for
     * /regex/ patterns. Case-insensitive for exact and wildcard matches.
     *
     * @since 0.1.0
     *
     * @param string               $cookie_name The cookie name or pattern to search for.
     * @param array<string,string> $cookies     The cookie array.
     * @return string|null The cookie value if found, null otherwise.
     */
    private function find_cookie(string $cookie_name, array $cookies): ?string
    {
        foreach ($cookies as $key => $value) {
            if (self::compare_values(strtolower($key), strtolower($cookie_name), '=')) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @since 1.1.0
     *
     * @param ConditionMeta $meta The metadata object to configure.
     */
    public static function set_meta(ConditionMeta $meta): void
    {
        $meta
            ->label('Cookie')
            ->description('Check if a cookie exists and/or matches a value.')
            ->categories('request')
            ->operators('=', '!=', 'EXISTS', 'NOT EXISTS')
            ->args()
                ->string('name')->label('Cookie Name')->required()
                ->string('value')->label('Cookie Value')->description('Value to compare against. If not specified, condition checks for cookie existence only.');
    }
}
