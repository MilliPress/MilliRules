<?php

namespace MilliRules\Tests\Unit;

use MilliRules\Tests\TestCase;
use MilliRules\Context;

/**
 * Tests for Context::get() object property access support
 *
 * Verifies that Context can access object properties using dot notation,
 * including public properties, magic properties, and nested object/array combinations.
 */
class ContextObjectTest extends TestCase
{
    // ============================================
    // Test Helper Classes
    // ============================================

    /**
     * Mock object with public properties (simulates WordPress WP_Post)
     */
    private function createMockPost(): object
    {
        return (object) [
            'ID' => 123,
            'post_author' => '1',
            'post_date' => '2024-01-15 10:30:00',
            'post_title' => 'Test Post',
            'post_type' => 'post',
            'post_status' => 'publish',
        ];
    }

    /**
     * Mock object with magic __get() method
     */
    private function createMockPostWithMagic(): object
    {
        return new class {
            public int $ID = 456;
            public string $post_title = 'Magic Post';
            private array $magic_properties = [
                'permalink' => 'https://example.com/magic-post/',
                'post_url' => 'https://example.com/magic-post/',
            ];

            public function __get(string $name)
            {
                return $this->magic_properties[$name] ?? null;
            }
        };
    }

    // ============================================
    // Public Property Access Tests
    // ============================================

    public function testAccessPublicObjectProperty(): void
    {
        $context = new Context();
        $post = $this->createMockPost();

        $context->set('hook.args.0', $post);

        $this->assertSame(123, $context->get('hook.args.0.ID'));
        $this->assertSame('1', $context->get('hook.args.0.post_author'));
        $this->assertSame('Test Post', $context->get('hook.args.0.post_title'));
    }

    public function testAccessNestedObjectProperties(): void
    {
        $context = new Context();

        // Create nested object structure
        $parent = (object) [
            'child' => (object) [
                'name' => 'Child Object',
                'value' => 42,
            ],
        ];

        $context->set('data', $parent);

        $this->assertSame('Child Object', $context->get('data.child.name'));
        $this->assertSame(42, $context->get('data.child.value'));
    }

    public function testAccessObjectInArray(): void
    {
        $context = new Context();
        $post1 = $this->createMockPost();
        $post2 = (object) ['ID' => 789, 'post_title' => 'Second Post'];

        $context->set('hook.args', [$post1, $post2]);

        $this->assertSame(123, $context->get('hook.args.0.ID'));
        $this->assertSame('Test Post', $context->get('hook.args.0.post_title'));
        $this->assertSame(789, $context->get('hook.args.1.ID'));
        $this->assertSame('Second Post', $context->get('hook.args.1.post_title'));
    }

    // ============================================
    // Magic Property Access Tests
    // ============================================

    public function testAccessMagicProperty(): void
    {
        $context = new Context();
        $post = $this->createMockPostWithMagic();

        $context->set('hook.args.2', $post);

        // Access public properties
        $this->assertSame(456, $context->get('hook.args.2.ID'));
        $this->assertSame('Magic Post', $context->get('hook.args.2.post_title'));

        // Access magic properties
        $this->assertSame('https://example.com/magic-post/', $context->get('hook.args.2.permalink'));
        $this->assertSame('https://example.com/magic-post/', $context->get('hook.args.2.post_url'));
    }

    // ============================================
    // Mixed Access Tests
    // ============================================

    public function testMixedArrayAndObjectAccess(): void
    {
        $context = new Context();

        // Simulate WordPress hook structure: ['new_status', 'old_status', $post]
        $post = $this->createMockPost();
        $context->set('hook', [
            'name' => 'transition_post_status',
            'args' => ['publish', 'draft', $post],
        ]);

        // Access array elements
        $this->assertSame('transition_post_status', $context->get('hook.name'));
        $this->assertSame('publish', $context->get('hook.args.0'));
        $this->assertSame('draft', $context->get('hook.args.1'));

        // Access object properties in array
        $this->assertSame(123, $context->get('hook.args.2.ID'));
        $this->assertSame('Test Post', $context->get('hook.args.2.post_title'));
    }

    public function testReturnsObjectWhenNoPropertySpecified(): void
    {
        $context = new Context();
        $post = $this->createMockPost();

        $context->set('hook.args.0', $post);

        $result = $context->get('hook.args.0');
        $this->assertIsObject($result);
        $this->assertSame(123, $result->ID);
    }

    // ============================================
    // Default Value Tests
    // ============================================

    public function testNonExistentPropertyReturnsDefault(): void
    {
        $context = new Context();
        $post = $this->createMockPost();

        $context->set('hook.args.0', $post);

        $this->assertNull($context->get('hook.args.0.nonexistent'));
        $this->assertSame('default', $context->get('hook.args.0.nonexistent', 'default'));
    }

    public function testNullObjectReturnsDefault(): void
    {
        $context = new Context();
        $context->set('hook.args.0', null);

        $this->assertNull($context->get('hook.args.0.ID'));
        $this->assertSame('fallback', $context->get('hook.args.0.ID', 'fallback'));
    }

    public function testAccessPropertyOnNonObjectReturnsDefault(): void
    {
        $context = new Context();
        $context->set('hook.args.0', 'string value');

        $this->assertNull($context->get('hook.args.0.property'));
        $this->assertSame('default', $context->get('hook.args.0.property', 'default'));
    }

    // ============================================
    // Real-World Use Case Tests
    // ============================================

    public function testWordPressCacheClearingScenario(): void
    {
        $context = new Context();

        // Simulate WordPress transition_post_status hook
        $post = $this->createMockPostWithMagic();
        $context->set('hook', [
            'name' => 'transition_post_status',
            'args' => ['publish', 'draft', $post],
        ]);

        // Test the actual placeholder patterns used in Clearing rules
        $this->assertSame('https://example.com/magic-post/', $context->get('hook.args.2.permalink'));
        $this->assertSame(456, $context->get('hook.args.2.ID'));
        $this->assertSame('publish', $context->get('hook.args.0'));
    }

    public function testDeepNestedObjectArrayStructure(): void
    {
        $context = new Context();

        // Complex nested structure
        $data = [
            'response' => (object) [
                'data' => [
                    'items' => [
                        (object) ['id' => 1, 'name' => 'Item 1'],
                        (object) ['id' => 2, 'name' => 'Item 2'],
                    ],
                ],
            ],
        ];

        $context->set('api', $data);

        $this->assertSame(1, $context->get('api.response.data.items.0.id'));
        $this->assertSame('Item 1', $context->get('api.response.data.items.0.name'));
        $this->assertSame(2, $context->get('api.response.data.items.1.id'));
    }

    // ============================================
    // Backward Compatibility Tests
    // ============================================

    public function testArrayAccessStillWorks(): void
    {
        $context = new Context();
        $context->set('request.headers.content-type', 'application/json');
        $context->set('user.roles', ['editor', 'subscriber']);

        $this->assertSame('application/json', $context->get('request.headers.content-type'));
        $this->assertSame(['editor', 'subscriber'], $context->get('user.roles'));
    }

    public function testHasMethodWorksWithObjects(): void
    {
        $context = new Context();
        $post = $this->createMockPost();
        $context->set('post', $post);

        $this->assertTrue($context->has('post.ID'));
        $this->assertTrue($context->has('post.post_title'));
        $this->assertFalse($context->has('post.nonexistent'));
    }
}
