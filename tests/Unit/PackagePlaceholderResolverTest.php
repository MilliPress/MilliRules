<?php

namespace MilliRules\Tests\Unit;

use MilliRules\Tests\TestCase;
use MilliRules\Context;
use MilliRules\PlaceholderResolver as BasePlaceholderResolver;
use MilliRules\Packages\PHP\Package as PhpPackage;
use MilliRules\Packages\PHP\PlaceholderResolver as PhpPlaceholderResolver;
use MilliRules\Packages\WordPress\PlaceholderResolver as WordPressPlaceholderResolver;

/**
 * Tests for the PHP and WordPress PlaceholderResolver subclasses.
 *
 * The base PlaceholderResolver is covered by PlaceholderResolverTest; the
 * subclass overrides (nested {request.*} and {wp.*} handling, plus the
 * cookie/param/header/wp custom resolvers) were previously untested.
 */
class PackagePlaceholderResolverTest extends TestCase
{
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
        // Custom resolvers live in a static property shared across all
        // PlaceholderResolver subclasses; reset it so registrations from one
        // test (e.g. the WordPress 'wp' resolver) do not leak into the next.
        $reflection = new \ReflectionClass(BasePlaceholderResolver::class);
        $property = $reflection->getProperty('custom_resolvers');
        $property->setAccessible(true);
        $property->setValue([]);

        parent::tearDown();
    }

    // ============================================
    // PHP PlaceholderResolver
    // ============================================

    public function testPhpResolverResolvesNestedRequestPlaceholder(): void
    {
        $context = [
            'request' => [
                'headers' => [
                    'user-agent' => 'Mozilla/5.0',
                    'accept'     => 'text/html',
                ],
            ],
        ];

        $resolver = new PhpPlaceholderResolver($this->createExecutionContext($context));

        // Regression: this nested-request path runs through the subclass
        // resolve_builtin_placeholder() override, which previously did array
        // access on the Context object and threw a fatal Error.
        $this->assertEquals('Mozilla/5.0', $resolver->resolve('{request.headers.user-agent}'));
        $this->assertEquals('text/html', $resolver->resolve('{request.headers.accept}'));
    }

    public function testPhpResolverNestedRequestMissingKeyReturnsOriginal(): void
    {
        $context = [
            'request' => [
                'headers' => [
                    'user-agent' => 'Mozilla/5.0',
                ],
            ],
        ];

        $resolver = new PhpPlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('{request.headers.missing}', $resolver->resolve('{request.headers.missing}'));
        $this->assertEquals('{request.missing.key}', $resolver->resolve('{request.missing.key}'));
    }

    public function testPhpResolverNestedRequestWithNoRequestDataReturnsOriginal(): void
    {
        $resolver = new PhpPlaceholderResolver($this->createExecutionContext([]));

        $this->assertEquals('{request.headers.user-agent}', $resolver->resolve('{request.headers.user-agent}'));
    }

    public function testPhpResolverCookieResolverIsCaseInsensitive(): void
    {
        $context = [
            'request' => [
                'cookies' => [
                    'Session_ID' => 'abc123',
                ],
            ],
        ];

        $resolver = new PhpPlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('abc123', $resolver->resolve('{cookie.session_id}'));
        $this->assertEquals('abc123', $resolver->resolve('{cookie.SESSION_ID}'));
    }

    public function testPhpResolverParamResolver(): void
    {
        $context = [
            'request' => [
                'params' => [
                    'page' => '2',
                ],
            ],
        ];

        $resolver = new PhpPlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('2', $resolver->resolve('{param.page}'));
        $this->assertEquals('{param.missing}', $resolver->resolve('{param.missing}'));
    }

    public function testPhpResolverHeaderResolverIsCaseInsensitive(): void
    {
        $context = [
            'request' => [
                'headers' => [
                    'X-Custom-Header' => 'value',
                ],
            ],
        ];

        $resolver = new PhpPlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('value', $resolver->resolve('{header.x-custom-header}'));
    }

    public function testPhpResolverCookieWithNoRequestDataReturnsOriginal(): void
    {
        $resolver = new PhpPlaceholderResolver($this->createExecutionContext([]));

        $this->assertEquals('{cookie.session}', $resolver->resolve('{cookie.session}'));
    }

    public function testPhpResolverDelegatesNonRequestCategoryToBase(): void
    {
        $context = [
            'user' => [
                'name' => 'John',
            ],
        ];

        $resolver = new PhpPlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('John', $resolver->resolve('{user.name}'));
    }

    // ============================================
    // Resolution against the contexts the engine builds
    // ============================================

    /**
     * A context carrying the PHP package's own providers, as a real request has.
     */
    private function createPackageContext(): Context
    {
        $context = new Context();
        ( new PhpPackage() )->register_context_providers($context);

        return $context;
    }

    public function testPhpResolverReadsTheCookieContext(): void
    {
        $original = $_COOKIE;
        $_COOKIE  = [ 'Session_ID' => 'abc123' ];

        try {
            $resolver = new PhpPlaceholderResolver($this->createPackageContext());

            $this->assertEquals('abc123', $resolver->resolve('{cookie.session_id}'));
            $this->assertEquals('{cookie.missing}', $resolver->resolve('{cookie.missing}'));
        } finally {
            $_COOKIE = $original;
        }
    }

    public function testPhpResolverReadsTheParamContext(): void
    {
        $original = $_GET;
        $_GET     = [ 'plan' => 'pro' ];

        try {
            $resolver = new PhpPlaceholderResolver($this->createPackageContext());

            $this->assertEquals('pro', $resolver->resolve('{param.plan}'));
        } finally {
            $_GET = $original;
        }
    }

    public function testPhpResolverReadsTheHeaderContext(): void
    {
        $original                = $_SERVER;
        $_SERVER['HTTP_ACCEPT']  = 'text/html';

        try {
            $resolver = new PhpPlaceholderResolver($this->createPackageContext());

            $this->assertEquals('text/html', $resolver->resolve('{header.accept}'));
        } finally {
            $_SERVER = $original;
        }
    }

    /**
     * Registering the package resolvers must not change any answer.
     *
     * They register into a static registry from their constructor, and the
     * base resolver consults custom resolvers first — so a package resolver
     * built anywhere reroutes resolution for the whole process, including for
     * the base resolvers BaseAction and BaseCondition already hold.
     */
    public function testPackageResolverDoesNotChangeWhatPlaceholdersResolveTo(): void
    {
        $originalCookie         = $_COOKIE;
        $originalGet            = $_GET;
        $originalServer         = $_SERVER;
        $_COOKIE                = [ 'session_id' => 'abc123' ];
        $_GET                   = [ 'plan' => 'pro' ];
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        try {
            $placeholders = [
                '{cookie.session_id}',
                '{param.plan}',
                '{header.accept}',
                '{request.method}',
            ];

            // What BaseAction and BaseCondition construct.
            $base = new BasePlaceholderResolver($this->createPackageContext());

            $before = [];
            foreach ($placeholders as $placeholder) {
                $before[ $placeholder ] = $base->resolve($placeholder);
            }

            new PhpPlaceholderResolver($this->createPackageContext());

            $after = [];
            foreach ($placeholders as $placeholder) {
                $after[ $placeholder ] = $base->resolve($placeholder);
            }

            $this->assertSame($before, $after);
            $this->assertSame('abc123', $before['{cookie.session_id}']);
            $this->assertSame('pro', $before['{param.plan}']);
            $this->assertSame('text/html', $before['{header.accept}']);
        } finally {
            $_COOKIE = $originalCookie;
            $_GET    = $originalGet;
            $_SERVER = $originalServer;
        }
    }

    // ============================================
    // WordPress PlaceholderResolver
    // ============================================

    public function testWordPressResolverResolvesWpPlaceholderViaCustomResolver(): void
    {
        $context = [
            'wp' => [
                'post' => [
                    'id'    => 42,
                    'title' => 'Hello World',
                ],
            ],
        ];

        $resolver = new WordPressPlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('42', $resolver->resolve('{wp.post.id}'));
        $this->assertEquals('Hello World', $resolver->resolve('{wp.post.title}'));
    }

    public function testWordPressResolverWpMissingKeyReturnsOriginal(): void
    {
        $context = [
            'wp' => [
                'post' => [
                    'id' => 42,
                ],
            ],
        ];

        $resolver = new WordPressPlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('{wp.post.missing}', $resolver->resolve('{wp.post.missing}'));
        $this->assertEquals('{wp.user.login}', $resolver->resolve('{wp.user.login}'));
    }

    public function testWordPressResolverBuiltinWpBranchReadsContextWithoutFatal(): void
    {
        $context = [
            'wp' => [
                'post' => [
                    'id' => 42,
                ],
            ],
        ];

        $resolver = new WordPressPlaceholderResolver($this->createExecutionContext($context));

        // In normal use the 'wp' custom resolver (registered in the
        // constructor) shadows resolve_builtin_placeholder()'s 'wp' branch,
        // so the only way to exercise the fixed Context array-access line is
        // to invoke the protected override directly.
        $method = new \ReflectionMethod(WordPressPlaceholderResolver::class, 'resolve_builtin_placeholder');
        $method->setAccessible(true);

        $this->assertSame(42, $method->invoke($resolver, 'wp', ['post', 'id']));
    }

    public function testWordPressResolverDelegatesRequestToPhpParent(): void
    {
        $context = [
            'request' => [
                'headers' => [
                    'user-agent' => 'Mozilla/5.0',
                ],
            ],
        ];

        $resolver = new WordPressPlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('Mozilla/5.0', $resolver->resolve('{request.headers.user-agent}'));
    }

    public function testWordPressResolverInheritsCookieResolver(): void
    {
        $context = [
            'request' => [
                'cookies' => [
                    'token' => 'xyz',
                ],
            ],
        ];

        $resolver = new WordPressPlaceholderResolver($this->createExecutionContext($context));

        $this->assertEquals('xyz', $resolver->resolve('{cookie.token}'));
    }
}
