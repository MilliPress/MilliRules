<?php

/**
 * Test fixture: a well-behaved third-party context.
 *
 * @package MilliRules\Tests
 */

namespace MilliRules\Tests\Unit\Fixtures\Contexts;

use MilliRules\Contexts\BaseContext;

/**
 * A context declaring a closed key set.
 */
class TenantContext extends BaseContext
{
    public function get_key(): string
    {
        return 'tenant_ctx';
    }

    public function get_label(): string
    {
        return 'Tenant';
    }

    public function get_description(): string
    {
        return 'The current tenant, for example {tenant_ctx.plan}.';
    }

    public function get_keys(): array
    {
        return array( 'id', 'plan' );
    }

    protected function build(): array
    {
        return array(
            'tenant_ctx' => array(
                'id'   => 'acme',
                'plan' => 'pro',
            ),
        );
    }
}
