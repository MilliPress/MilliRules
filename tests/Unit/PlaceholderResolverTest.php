<?php

namespace MilliRules\Tests\Unit;

use MilliRules\Tests\TestCase;
use MilliRules\PlaceholderResolver;
use MilliRules\Context;

/**
 * Comprehensive tests for PlaceholderResolver class
 *
 * Tests built-in placeholders, custom resolvers, nested structures, and error handling
 */
class PlaceholderResolverTest extends TestCase
{
    /**
     * Create an ExecutionContext from array data
     */
    private function createExecutionContext(array $data = []): Context
    {
        $context = new Context();
        foreach ($data as $key => $value) {
            $context->set($key, $value);
        }
        return $context;
    }

    protected function tearDown(): void
    {
        // Clear custom resolvers between tests using reflection
        $reflection = new \ReflectionClass(PlaceholderResolver::class);
        $property = $reflection->getProperty('custom_resolvers');
        $property->setAccessible(true);
        $property->setValue([]);

        parent::tearDown();
    }

    // ============================================
    // Basic Placeholder Resolution Tests
    // ============================================

    public function testResolveSimplePlaceholder(): void
    {
        $context = [
            'user' => [
                'id' => '123',
                'name' => 'John',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('123', $resolver->resolve('{user.id}'));
        $this->assertEquals('John', $resolver->resolve('{user.name}'));
    }

    public function testResolveNestedPlaceholder(): void
    {
        $context = [
            'request' => [
                'headers' => [
                    'user-agent' => 'Mozilla/5.0',
                    'accept' => 'text/html',
                ],
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('Mozilla/5.0', $resolver->resolve('{request.headers.user-agent}'));
        $this->assertEquals('text/html', $resolver->resolve('{request.headers.accept}'));
    }

    public function testResolveDeeplyNestedPlaceholder(): void
    {
        $context = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => 'deep value',
                    ],
                ],
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('deep value', $resolver->resolve('{level1.level2.level3.level4}'));
    }

    public function testResolveMultiplePlaceholdersInString(): void
    {
        $context = [
            'user' => [
                'name' => 'John',
                'age' => '30',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $result = $resolver->resolve('Hello {user.name}, you are {user.age} years old');
        $this->assertEquals('Hello John, you are 30 years old', $result);
    }

    public function testResolveWithNoPlaceholders(): void
    {
        $resolver = new PlaceholderResolver($this->createExecutionContext([]));

        $result = $resolver->resolve('This is a plain string without placeholders');
        $this->assertEquals('This is a plain string without placeholders', $result);
    }

    // ============================================
    // Missing/Invalid Placeholder Tests
    // ============================================

    public function testUnresolvedPlaceholderReturnsOriginal(): void
    {
        $context = [
            'user' => [
                'name' => 'John',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        // Non-existent category
        $this->assertEquals('{nonexistent.value}', $resolver->resolve('{nonexistent.value}'));

        // Non-existent key in valid category
        $this->assertEquals('{user.missing}', $resolver->resolve('{user.missing}'));
    }

    public function testPlaceholderWithMissingNestedKey(): void
    {
        $context = [
            'request' => [
                'headers' => [
                    'user-agent' => 'Mozilla',
                ],
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('{request.headers.missing}', $resolver->resolve('{request.headers.missing}'));
        $this->assertEquals('{request.missing.key}', $resolver->resolve('{request.missing.key}'));
    }

    public function testPlaceholderWithOnlyCategoryReturnsOriginal(): void
    {
        $context = [
            'user' => [
                'name' => 'John',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        // Category without key should return original placeholder
        $this->assertEquals('{user}', $resolver->resolve('{user}'));
    }

    public function testEmptyPlaceholderReturnsOriginal(): void
    {
        $resolver = new PlaceholderResolver($this->createExecutionContext([]));

        $this->assertEquals('{}', $resolver->resolve('{}'));
    }

    public function testPlaceholderWithEmptyCategoryReturnsOriginal(): void
    {
        $resolver = new PlaceholderResolver($this->createExecutionContext([]));

        $this->assertEquals('{:value}', $resolver->resolve('{:value}'));
    }

    // ============================================
    // Data Type Tests
    // ============================================

    public function testResolveStringValue(): void
    {
        $context = [
            'data' => [
                'string' => 'test string',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('test string', $resolver->resolve('{data.string}'));
        $this->assertIsString($resolver->resolve('{data.string}'));
    }

    public function testResolveIntegerValue(): void
    {
        $context = [
            'data' => [
                'integer' => 42,
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $result = $resolver->resolve('{data.integer}');
        $this->assertEquals('42', $result);
        $this->assertIsString($result);
    }

    public function testResolveFloatValue(): void
    {
        $context = [
            'data' => [
                'float' => 3.14,
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $result = $resolver->resolve('{data.float}');
        $this->assertEquals('3.14', $result);
        $this->assertIsString($result);
    }

    public function testResolveBooleanValue(): void
    {
        $context = [
            'data' => [
                'true' => true,
                'false' => false,
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('1', $resolver->resolve('{data.true}'));
        $this->assertEquals('', $resolver->resolve('{data.false}'));
    }

    public function testNonScalarValueReturnsOriginalPlaceholder(): void
    {
        $context = [
            'data' => [
                'array' => ['value1', 'value2'],
                'object' => (object)['key' => 'value'],
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        // Arrays and objects should not be resolved
        $this->assertEquals('{data.array}', $resolver->resolve('{data.array}'));
        $this->assertEquals('{data.object}', $resolver->resolve('{data.object}'));
    }

    public function testNullValueReturnsOriginalPlaceholder(): void
    {
        $context = [
            'data' => [
                'null' => null,
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('{data.null}', $resolver->resolve('{data.null}'));
    }

    // ============================================
    // Custom Placeholder Resolver Tests
    // ============================================

    public function testRegisterCustomPlaceholder(): void
    {
        PlaceholderResolver::register_placeholder('custom', function ($context, $parts) {
            return 'custom_' . implode('_', $parts);
        });

        $resolver = new PlaceholderResolver($this->createExecutionContext([]));
        $result = $resolver->resolve('{custom.foo.bar}');

        $this->assertEquals('custom_foo_bar', $result);
    }

    public function testCustomPlaceholderReceivesContext(): void
    {
        PlaceholderResolver::register_placeholder('ctx', function ($context, $parts) {
            return $context['value'] ?? 'default';
        });

        $context = ['value' => 'context_value'];
        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('context_value', $resolver->resolve('{ctx.anything}'));
    }

    public function testCustomPlaceholderReceivesParts(): void
    {
        PlaceholderResolver::register_placeholder('join', function ($context, $parts) {
            return implode('-', $parts);
        });

        $resolver = new PlaceholderResolver($this->createExecutionContext([]));

        $this->assertEquals('a-b-c', $resolver->resolve('{join.a.b.c}'));
        $this->assertEquals('x', $resolver->resolve('{join.x}'));
        $this->assertEquals('', $resolver->resolve('{join}'));
    }

    public function testCustomPlaceholderReturningNull(): void
    {
        PlaceholderResolver::register_placeholder('null_resolver', function ($context, $parts) {
            return null;
        });

        $resolver = new PlaceholderResolver($this->createExecutionContext([]));

        // Null return should result in original placeholder
        $this->assertEquals('{null_resolver.test}', $resolver->resolve('{null_resolver.test}'));
    }

    public function testCustomPlaceholderOverridesBuiltin(): void
    {
        // Register custom resolver for existing category
        PlaceholderResolver::register_placeholder('request', function ($context, $parts) {
            return 'custom_request_handler';
        });

        $context = [
            'request' => [
                'url' => 'https://example.com',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        // Custom resolver should take precedence
        $this->assertEquals('custom_request_handler', $resolver->resolve('{request.url}'));
    }

    public function testMultipleCustomPlaceholders(): void
    {
        PlaceholderResolver::register_placeholder('foo', function ($context, $parts) {
            return 'foo_value';
        });

        PlaceholderResolver::register_placeholder('bar', function ($context, $parts) {
            return 'bar_value';
        });

        $resolver = new PlaceholderResolver($this->createExecutionContext([]));

        $this->assertEquals('foo_value', $resolver->resolve('{foo.test}'));
        $this->assertEquals('bar_value', $resolver->resolve('{bar.test}'));
    }

    // ============================================
    // Custom Placeholder Error Handling Tests
    // ============================================

    public function testCustomPlaceholderThrowingExceptionReturnsOriginal(): void
    {
        PlaceholderResolver::register_placeholder('error', function ($context, $parts) {
            throw new \Exception('Test exception');
        });

        $resolver = new PlaceholderResolver($this->createExecutionContext([]));
        $result = $resolver->resolve('{error.test}');

        // Should return original placeholder when exception is thrown
        $this->assertEquals('{error.test}', $result);
    }

    public function testCustomPlaceholderWithInvalidReturnType(): void
    {
        PlaceholderResolver::register_placeholder('array_return', function ($context, $parts) {
            return ['array', 'value'];
        });

        $resolver = new PlaceholderResolver($this->createExecutionContext([]));
        $result = $resolver->resolve('{array_return.test}');

        // Non-scalar return should be converted to string if possible, or return original
        $this->assertIsString($result);
    }

    // ============================================
    // Complex Scenarios Tests
    // ============================================

    public function testMixedBuiltinAndCustomPlaceholders(): void
    {
        PlaceholderResolver::register_placeholder('custom', function ($context, $parts) {
            return 'CUSTOM';
        });

        $context = [
            'user' => [
                'name' => 'John',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $result = $resolver->resolve('User: {user.name}, Type: {custom.value}');
        $this->assertEquals('User: John, Type: CUSTOM', $result);
    }

    public function testPlaceholderInComplexString(): void
    {
        $context = [
            'request' => [
                'url' => '/admin/users',
                'method' => 'POST',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $template = 'Request: {request.method} {request.url} was processed';
        $result = $resolver->resolve($template);

        $this->assertEquals('Request: POST /admin/users was processed', $result);
    }

    public function testAdjacentPlaceholders(): void
    {
        $context = [
            'a' => ['v' => '1'],
            'b' => ['v' => '2'],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $result = $resolver->resolve('{a.v}{b.v}');
        $this->assertEquals('12', $result);
    }

    public function testPlaceholderWithSpecialCharactersInValue(): void
    {
        $context = [
            'data' => [
                'special' => 'value with {braces} and $special @chars!',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $result = $resolver->resolve('{data.special}');
        $this->assertEquals('value with {braces} and $special @chars!', $result);
    }

    public function testPlaceholderWithNumericKeys(): void
    {
        $context = [
            'array' => [
                0 => 'first',
                1 => 'second',
                2 => 'third',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('first', $resolver->resolve('{array.0}'));
        $this->assertEquals('second', $resolver->resolve('{array.1}'));
        $this->assertEquals('third', $resolver->resolve('{array.2}'));
    }

    public function testDeepNestingWithMixedTypes(): void
    {
        $context = [
            'level1' => [
                'level2' => [
                    'string' => 'value',
                    'number' => 42,
                    'level3' => [
                        'nested' => 'deep',
                    ],
                ],
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('value', $resolver->resolve('{level1.level2.string}'));
        $this->assertEquals('42', $resolver->resolve('{level1.level2.number}'));
        $this->assertEquals('deep', $resolver->resolve('{level1.level2.level3.nested}'));
    }

    // ============================================
    // Edge Cases
    // ============================================

    public function testEmptyContext(): void
    {
        $resolver = new PlaceholderResolver($this->createExecutionContext([]));

        $this->assertEquals('{any.value}', $resolver->resolve('{any.value}'));
        $this->assertEquals('plain text', $resolver->resolve('plain text'));
    }

    public function testPlaceholderWithColonInValue(): void
    {
        $context = [
            'data' => [
                'time' => '10:30:45',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('10:30:45', $resolver->resolve('{data.time}'));
    }

    public function testPlaceholderAtMiddleOfNonArrayPath(): void
    {
        $context = [
            'request' => [
                'url' => '/admin',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        // Trying to access nested property of non-array value
        $this->assertEquals('{request.url.path}', $resolver->resolve('{request.url.path}'));
    }

    public function testZeroStringValue(): void
    {
        $context = [
            'data' => [
                'zero' => '0',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('0', $resolver->resolve('{data.zero}'));
    }

    public function testEmptyStringValue(): void
    {
        $context = [
            'data' => [
                'empty' => '',
            ],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('', $resolver->resolve('{data.empty}'));
    }

    public function testPlaceholderCaseSensitivity(): void
    {
        $context = [
            'User' => ['Name' => 'John'],
            'user' => ['name' => 'Jane'],
        ];

        $resolver = new PlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('John', $resolver->resolve('{User.Name}'));
        $this->assertEquals('Jane', $resolver->resolve('{user.name}'));
        $this->assertEquals('{USER.NAME}', $resolver->resolve('{USER.NAME}'));
    }

    public function testMalformedPlaceholderSyntax(): void
    {
        $resolver = new PlaceholderResolver($this->createExecutionContext([]));

        // Missing closing brace
        $this->assertEquals('{incomplete', $resolver->resolve('{incomplete'));

        // Missing opening brace
        $this->assertEquals('incomplete}', $resolver->resolve('incomplete}'));
    }
}
