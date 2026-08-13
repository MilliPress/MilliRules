<?php

/**
 * Test fixture: a third-party context whose get_keys() raises an Error.
 *
 * Covers Throwable, not just Exception.
 *
 * @package MilliRules\Tests
 */

namespace MilliRules\Tests\Unit\Fixtures\Contexts;

use MilliRules\Contexts\BaseContext;

/**
 * A context that throws an Error from get_keys().
 */
class BrokenKeysContext extends BaseContext
{
    public function get_key(): string
    {
        return 'broken_keys';
    }

    public function get_keys(): array
    {
        throw new \Error('keys exploded');
    }

    protected function build(): array
    {
        return array();
    }
}
