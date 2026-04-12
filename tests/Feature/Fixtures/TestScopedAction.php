<?php

/**
 * Test fixture for class-based scoped action locking.
 *
 * Used by LockedActionTest to verify that actions declaring scope via
 * the static get_scope() method are properly locked by the engine.
 *
 * The fixture also sets a global flag if set_meta() is ever called, so
 * regression tests can verify that the engine's hot path does NOT call
 * set_meta() when only scope resolution is needed.
 *
 * @package MilliRules\Tests
 */

namespace MilliRules\Tests\Feature\Fixtures;

use MilliRules\Actions\ActionMeta;
use MilliRules\Actions\BaseAction;
use MilliRules\Context;

/**
 * A class-based action that declares its scope via get_scope().
 *
 * Executes by appending its first positional argument to a global log,
 * allowing tests to verify which calls were executed versus blocked.
 */
class TestScopedAction extends BaseAction
{
    public static function get_scope(): string
    {
        return 'test_scope';
    }

    public static function set_meta(ActionMeta $meta): void
    {
        // Tracked via a global so regression tests can assert that the
        // engine's hot path (get_action_scope()) does NOT trigger set_meta().
        $GLOBALS['__test_set_meta_was_called'] = true;

        $meta->label('Test Scoped Action');
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
