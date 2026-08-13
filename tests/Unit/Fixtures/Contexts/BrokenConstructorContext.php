<?php

/**
 * Test fixture: a third-party context that blows up on construction.
 *
 * @package MilliRules\Tests
 */

namespace MilliRules\Tests\Unit\Fixtures\Contexts;

use MilliRules\Context;
use MilliRules\Contexts\BaseContext;

/**
 * A context that throws from its constructor.
 */
class BrokenConstructorContext extends BaseContext
{
    public function __construct(Context $context)
    {
        parent::__construct($context);

        throw new \RuntimeException('constructor exploded');
    }

    public function get_key(): string
    {
        return 'broken_constructor';
    }

    protected function build(): array
    {
        return array();
    }
}
