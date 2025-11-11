<?php

namespace MilliRules\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use MilliRules\Packages\PackageManager;

/**
 * Base TestCase class with helper methods and WordPress function mocks
 */
abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Logged error messages during test execution
     *
     * @var array<int, string>
     */
    protected array $errorLogs = [];

    /**
     * WordPress functions mocked state
     *
     * @var array<string, mixed>
     */
    protected array $wpFunctions = [];

    /**
     * Set up test environment before each test
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Clear PackageManager state between tests
        PackageManager::reset();

        // Reset error logs
        $this->errorLogs = [];

        // Reset WordPress function mocks
        $this->wpFunctions = [];

        // Set up error log handler to capture error_log calls
        set_error_handler(function ($errno, $errstr) {
            $this->errorLogs[] = $errstr;
            return true;
        });
    }

    /**
     * Tear down test environment after each test
     */
    protected function tearDown(): void
    {
        restore_error_handler();
        parent::tearDown();
    }

    /**
     * Assert that an error was logged containing specific text
     *
     * @param string $expectedMessage Expected message substring
     * @param string $message Optional assertion message
     */
    protected function assertErrorLogged(string $expectedMessage, string $message = ''): void
    {
        $found = false;
        foreach ($this->errorLogs as $log) {
            if (strpos($log, $expectedMessage) !== false) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, $message ?: "Failed asserting that error containing '{$expectedMessage}' was logged");
    }

    /**
     * Assert that no errors were logged
     *
     * @param string $message Optional assertion message
     */
    protected function assertNoErrorsLogged(string $message = ''): void
    {
        $this->assertCount(0, $this->errorLogs, $message ?: 'Expected no errors to be logged, but found: ' . implode(', ', $this->errorLogs));
    }

    /**
     * Get all logged errors
     *
     * @return array<int, string>
     */
    protected function getErrorLogs(): array
    {
        return $this->errorLogs;
    }

    /**
     * Clear logged errors
     */
    protected function clearErrorLogs(): void
    {
        $this->errorLogs = [];
    }

    /**
     * Mock a WordPress function
     *
     * @param string $functionName Function name to mock
     * @param mixed $returnValue Value to return when called
     */
    protected function mockWordPressFunction(string $functionName, $returnValue): void
    {
        $this->wpFunctions[$functionName] = $returnValue;
    }

    /**
     * Simulate WordPress function being defined
     *
     * @param string $functionName Function name
     * @return bool
     */
    protected function isWordPressFunctionDefined(string $functionName): bool
    {
        return isset($this->wpFunctions[$functionName]) || function_exists($functionName);
    }

    /**
     * Call mocked WordPress function
     *
     * @param string $functionName Function name
     * @param array<int, mixed> $args Arguments
     * @return mixed
     */
    protected function callWordPressFunction(string $functionName, array $args = [])
    {
        if (isset($this->wpFunctions[$functionName])) {
            $value = $this->wpFunctions[$functionName];
            return is_callable($value) ? call_user_func_array($value, $args) : $value;
        }

        if (function_exists($functionName)) {
            return call_user_func_array($functionName, $args);
        }

        return null;
    }

    /**
     * Create a mock superglobal array
     *
     * @param array<string, mixed> $data Data to populate
     * @return array<string, mixed>
     */
    protected function createSuperglobal(array $data = []): array
    {
        return $data;
    }

    /**
     * Create test context with common structure
     *
     * @param array<string, mixed> $overrides Override default context values
     * @return array<string, mixed>
     */
    protected function createContext(array $overrides = []): array
    {
        $default = [
            'request' => [
                'url' => 'https://example.com/test',
                'method' => 'GET',
                'headers' => [],
                'cookies' => [],
                'params' => [],
            ],
            'server' => $_SERVER,
        ];

        return array_merge($default, $overrides);
    }

    /**
     * Create test rule configuration
     *
     * @param array<string, mixed> $overrides Override default rule values
     * @return array<string, mixed>
     */
    protected function createRule(array $overrides = []): array
    {
        $default = [
            'id' => 'test-rule-' . uniqid(),
            'title' => 'Test Rule',
            'enabled' => true,
            'match_type' => 'all',
            'conditions' => [],
            'actions' => [],
        ];

        return array_merge($default, $overrides);
    }

    /**
     * Create test condition configuration
     *
     * @param string $type Condition type
     * @param array<string, mixed> $overrides Override default condition values
     * @return array<string, mixed>
     */
    protected function createCondition(string $type, array $overrides = []): array
    {
        $default = [
            'type' => $type,
            'operator' => '=',
            'value' => '',
        ];

        return array_merge($default, $overrides);
    }

    /**
     * Create test action configuration
     *
     * @param string $type Action type
     * @param array<string, mixed> $overrides Override default action values
     * @return array<string, mixed>
     */
    protected function createAction(string $type, array $overrides = []): array
    {
        $default = [
            'type' => $type,
        ];

        return array_merge($default, $overrides);
    }
}
