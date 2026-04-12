<?php

/**
 * Arguments Builder
 *
 * Collects the ArgumentSchema instances declared inside an ActionMeta::args()
 * (or future ConditionMeta::args()) context. Consumer code never references
 * this class directly — it's obtained via $meta->args() and used implicitly
 * by chaining type factories.
 *
 * # Usage (via ActionMeta)
 *
 *     $meta->args()
 *         ->integer('ttl')->format('seconds')->default(3600)->min(0)
 *         ->string('reason')->default('')
 *         ->choice('mode')->options(['auto', 'manual'])->default('auto');
 *
 * Each type factory (integer, string, etc.) creates a new ArgumentSchema
 * and appends it to the internal list. The schema's config setters return
 * `self` for continued configuration; its walking type methods delegate
 * back here to start a new argument.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer <hello@millirules.com>
 * @since       1.2.0
 */

namespace MilliRules;

/**
 * Class ArgumentsBuilder
 *
 * @since 1.2.0
 */
class ArgumentsBuilder
{
    /**
     * The argument schemas declared so far.
     *
     * @since 1.2.0
     * @var array<int, ArgumentSchema>
     */
    private array $schemas = array();

    /**
     * Parent metadata object (ActionMeta, or ConditionMeta in the future).
     *
     * Used by __call() to auto-forward unknown method calls back to the parent.
     * This lets consumers continue chaining meta-level methods like ->extend()
     * or ->category() after declaring arguments, instead of being stuck on
     * the arguments chain.
     *
     * @since 1.2.0
     * @var object|null
     */
    private ?object $parent = null;

    /**
     * Constructor.
     *
     * @since 1.2.0
     *
     * @param object|null $parent The parent metadata object (ActionMeta or
     *                            ConditionMeta). When set, unknown method
     *                            calls on this builder are forwarded back
     *                            to the parent via __call().
     */
    public function __construct(?object $parent = null)
    {
        $this->parent = $parent;
    }

    /**
     * Get the parent metadata object, if one was provided.
     *
     * @since 1.2.0
     *
     * @return object|null
     */
    public function get_parent(): ?object
    {
        return $this->parent;
    }

    /**
     * Forward unknown method calls to the parent metadata object.
     *
     * This is what lets the arguments chain "escape" back to the parent meta:
     *
     *     $meta
     *         ->args()
     *             ->integer('ttl')->default(3600)
     *         ->extend('millicache:icon', 'clock');  // forwarded to $meta
     *
     * @since 1.2.0
     *
     * @param string            $method The method name.
     * @param array<int, mixed> $args   The method arguments.
     * @return mixed
     * @throws \BadMethodCallException If the method exists nowhere.
     */
    public function __call(string $method, array $args)
    {
        if (null !== $this->parent && method_exists($this->parent, $method)) {
            $callable = array($this->parent, $method);
            assert(is_callable($callable));
            return call_user_func_array($callable, $args);
        }

        throw new \BadMethodCallException(
            sprintf('Method %s::%s() does not exist', static::class, $method)
        );
    }

    /**
     * Declare a string argument.
     *
     * @since 1.2.0
     *
     * @param int|string $key The argument key.
     * @return ArgumentSchema The new schema (for config chaining).
     */
    public function string($key): ArgumentSchema
    {
        return $this->push($key, ArgumentSchema::TYPE_STRING);
    }

    /**
     * Declare an integer argument.
     *
     * @since 1.2.0
     *
     * @param int|string $key The argument key.
     * @return ArgumentSchema The new schema (for config chaining).
     */
    public function integer($key): ArgumentSchema
    {
        return $this->push($key, ArgumentSchema::TYPE_INTEGER);
    }

    /**
     * Declare a number (float) argument.
     *
     * @since 1.2.0
     *
     * @param int|string $key The argument key.
     * @return ArgumentSchema The new schema (for config chaining).
     */
    public function number($key): ArgumentSchema
    {
        return $this->push($key, ArgumentSchema::TYPE_NUMBER);
    }

    /**
     * Declare a boolean argument.
     *
     * @since 1.2.0
     *
     * @param int|string $key The argument key.
     * @return ArgumentSchema The new schema (for config chaining).
     */
    public function boolean($key): ArgumentSchema
    {
        return $this->push($key, ArgumentSchema::TYPE_BOOLEAN);
    }

    /**
     * Declare a single-choice argument.
     *
     * Use ArgumentSchema::options() to set the allowed values.
     *
     * @since 1.2.0
     *
     * @param int|string $key The argument key.
     * @return ArgumentSchema The new schema (for config chaining).
     */
    public function choice($key): ArgumentSchema
    {
        return $this->push($key, ArgumentSchema::TYPE_CHOICE);
    }

    /**
     * Declare a multi-choice argument.
     *
     * Use ArgumentSchema::options() to set the allowed values.
     *
     * @since 1.2.0
     *
     * @param int|string $key The argument key.
     * @return ArgumentSchema The new schema (for config chaining).
     */
    public function choices($key): ArgumentSchema
    {
        return $this->push($key, ArgumentSchema::TYPE_CHOICES);
    }

    /**
     * Get all declared schemas in their declaration order.
     *
     * @since 1.2.0
     *
     * @return array<int, ArgumentSchema>
     */
    public function get_schemas(): array
    {
        return $this->schemas;
    }

    /**
     * Create a new schema, append it to the internal list, and return it.
     *
     * @since 1.2.0
     *
     * @param int|string $key  The argument key.
     * @param string     $type One of the ArgumentSchema::TYPE_* constants.
     * @return ArgumentSchema
     */
    private function push($key, string $type): ArgumentSchema
    {
        $schema          = new ArgumentSchema($this, $key, $type);
        $this->schemas[] = $schema;
        return $schema;
    }
}
