<?php

/**
 * Test fixture for class-based scoped action locking.
 *
 * Used by LockedActionTest to verify that actions declaring scope via
 * the static describe() method are properly locked by the engine.
 *
 * @package MilliRules\Tests
 */

namespace MilliRules\Tests\Feature\Fixtures;

use MilliRules\Actions\ActionMeta;
use MilliRules\Actions\BaseAction;
use MilliRules\Context;

/**
 * A class-based action that declares its scope via describe().
 *
 * Executes by appending its first positional argument to a global log,
 * allowing tests to verify which calls were executed versus blocked.
 */
class TestScopedAction extends BaseAction
{
    public static function describe(): ActionMeta
    {
        return parent::describe()->scope('test_scope');
    }

    public function execute(Context $context): void
    {
        $value = $this->get_arg(0, '')->string();
        if (isset($GLOBALS['__test_class_based_log']) && is_array($GLOBALS['__test_class_based_log'])) {
            $GLOBALS['__test_class_based_log'][] = $value;
        }
    }

    public function get_type(): string
    {
        return 'test_scoped_action';
    }
}
