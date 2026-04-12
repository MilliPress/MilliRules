<?php

/**
 * Action Meta
 *
 * Fluent metadata container for registered action types. Used for both
 * engine-relevant configuration (e.g., scope for value-level locking) and
 * consumer-relevant information (e.g., label, description, category for UIs).
 *
 * # Engine-relevant vs consumer-relevant fields
 *
 * - **scope** — read by RuleEngine during execution to build lock keys.
 * - **label, description, category** — stored but never interpreted by
 *   MilliRules itself. Consumers (UIs, CLIs, docs generators) introspect
 *   these via Rules::get_action_meta().
 *
 * The split is a documentation concern, not a storage concern. All fields
 * are first-class typed properties for IDE autocomplete and PHPStan coverage.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer <hello@millirules.com>
 * @since       1.2.0
 */

namespace MilliRules\Actions;

use MilliRules\ArgumentSchema;
use MilliRules\ArgumentsBuilder;

/**
 * Class ActionMeta
 *
 * Metadata declaration for a registered action type. Obtained from:
 * - Rules::register_action() returns a new ActionMeta for callback-based actions
 * - BaseAction::set_meta() configures an ActionMeta for class-based actions
 *
 * Example (callback-based):
 *   Rules::register_action('add_flag', $callback)
 *       ->scope('flag')
 *       ->label('Add Flag')
 *       ->description('Tag the response with a flag.')
 *       ->categories('flags');
 *
 * Example (class-based):
 *   class AddFlag extends BaseAction {
 *       public static function get_scope(): string { return 'flag'; }
 *       public static function set_meta(ActionMeta $meta): void {
 *           $meta
 *               ->label('Add Flag')
 *               ->description('Tag the response with a flag.')
 *               ->categories('flags');
 *       }
 *   }
 *
 * @since 1.2.0
 */
class ActionMeta
{
    /**
     * The action type identifier.
     *
     * @since 1.2.0
     * @var string
     */
    private string $type;

    /**
     * Lock scope for value-level locking (engine-relevant).
     *
     * Actions sharing a scope use lock keys like "scope:value" instead of
     * just the action type. Empty string means type-level locking.
     *
     * @since 1.2.0
     * @var string
     */
    private string $scope = '';

    /**
     * Human-readable label (consumer-relevant).
     *
     * @since 1.2.0
     * @var string
     */
    private string $label = '';

    /**
     * Help text description (consumer-relevant).
     *
     * @since 1.2.0
     * @var string
     */
    private string $description = '';

    /**
     * UI grouping categories (consumer-relevant).
     *
     * @since 1.2.0
     * @var array<int, string>
     */
    private array $categories = array();

    /**
     * Arguments builder (lazy). Created on first access via args().
     *
     * Arguments are a first-class concept on ActionMeta because every rule
     * engine with actions has arguments. The builder is instantiated lazily
     * so actions with no arguments don't pay any allocation cost.
     *
     * @since 1.2.0
     * @var ArgumentsBuilder|null
     */
    private ?ArgumentsBuilder $args_builder = null;

    /**
     * Plugin-specific metadata bag.
     *
     * For metadata that doesn't belong in MilliRules core (icons,
     * conditional visibility rules, documentation URLs, plugin-defined
     * widgets, etc.). MilliRules stores values but never interprets them.
     *
     * Callers are encouraged to namespace keys (e.g., 'millicache:icon',
     * 'seo-redirects:http_status') to avoid cross-plugin collisions.
     *
     * @since 1.2.0
     * @var array<string, mixed>
     */
    private array $extensions = array();

    /**
     * Constructor.
     *
     * @since 1.2.0
     *
     * @param string $type The action type identifier.
     */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    /**
     * Set the lock scope for this action.
     *
     * Actions sharing the same scope (e.g., 'add_flag' and 'remove_flag' both
     * scoped to 'flag') use value-level lock keys (scope:value) instead of
     * type-level keys. This allows locking a specific value without blocking
     * other values of the same action type.
     *
     * @since 1.2.0
     *
     * @param string $scope The lock scope identifier.
     * @return self
     */
    public function scope(string $scope): self
    {
        $this->scope = $scope;
        return $this;
    }

    /**
     * Set the human-readable label.
     *
     * @since 1.2.0
     *
     * @param string $label The label (typically translated).
     * @return self
     */
    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    /**
     * Set the help text description.
     *
     * @since 1.2.0
     *
     * @param string $description The description (typically translated).
     * @return self
     */
    public function description(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Set the UI grouping categories.
     *
     * Actions can belong to one or more categories. UIs use these to
     * group actions in dropdowns, sidebars, or filter panels.
     *
     * @since 1.2.0
     *
     * @param string ...$categories One or more category identifiers.
     * @return self
     */
    public function categories(string ...$categories): self
    {
        $this->categories = $categories;
        return $this;
    }

    /**
     * Enter the arguments declaration context.
     *
     * Returns an ArgumentsBuilder that collects the arguments for this
     * action. Inside the builder, use type factories (->integer($key),
     * ->string($key), etc.) to declare arguments; each returns an
     * ArgumentSchema for continued configuration.
     *
     * The pattern mirrors ->when()/->then() in rule building: a scoped
     * context where methods have a focused vocabulary.
     *
     * Example:
     *     $meta->args()
     *         ->integer('ttl')->format('seconds')->default(3600)->min(0)
     *         ->string('reason')->default('');
     *
     * The builder is cached — calling args() repeatedly returns the same
     * instance, so you can continue declaring arguments across multiple
     * statements if needed.
     *
     * @since 1.2.0
     *
     * @return ArgumentsBuilder
     */
    public function args(): ArgumentsBuilder
    {
        if (null === $this->args_builder) {
            // Pass $this as the parent so the builder (and its schemas) can
            // auto-forward unknown method calls back to this ActionMeta.
            // That lets consumers continue the chain after ->args(), e.g.
            // ->args()->integer('ttl')->extend('millicache:icon', 'clock').
            $this->args_builder = new ArgumentsBuilder($this);
        }
        return $this->args_builder;
    }

    /**
     * Attach plugin-specific metadata under a namespaced key.
     *
     * Use for metadata that doesn't belong in MilliRules core: icons,
     * conditional visibility rules, documentation URLs, plugin-defined
     * widgets, etc. MilliRules stores the value but never interprets it.
     *
     * Callers are encouraged to namespace their keys (e.g., 'millicache:icon',
     * 'seo-redirects:http_status') to avoid collisions across plugins.
     * MilliRules does not enforce namespacing — the convention is the contract.
     *
     * @since 1.2.0
     *
     * @param string $key   The extension key (should be namespaced).
     * @param mixed  $value Any JSON-serializable value.
     * @return self
     */
    public function extend(string $key, $value): self
    {
        $this->extensions[ $key ] = $value;
        return $this;
    }

    /**
     * Get the action type identifier.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public function get_type(): string
    {
        return $this->type;
    }

    /**
     * Get the lock scope.
     *
     * @since 1.2.0
     *
     * @return string The scope, or empty string if unscoped.
     */
    public function get_scope(): string
    {
        return $this->scope;
    }

    /**
     * Get the label.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public function get_label(): string
    {
        return $this->label;
    }

    /**
     * Get the description.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public function get_description(): string
    {
        return $this->description;
    }

    /**
     * Get the categories.
     *
     * @since 1.2.0
     *
     * @return array<int, string>
     */
    public function get_categories(): array
    {
        return $this->categories;
    }

    /**
     * Get the declared argument schemas.
     *
     * Returns an empty array if args() was never called.
     *
     * @since 1.2.0
     *
     * @return array<int, ArgumentSchema>
     */
    public function get_arguments(): array
    {
        return null === $this->args_builder ? array() : $this->args_builder->get_schemas();
    }

    /**
     * Get the value of an extension key.
     *
     * Returns null for unset keys. Use has_extension() to distinguish
     * "explicitly set to null" from "not set".
     *
     * @since 1.2.0
     *
     * @param string $key The extension key.
     * @return mixed|null
     */
    public function get_extension(string $key)
    {
        return $this->extensions[ $key ] ?? null;
    }

    /**
     * Check whether an extension key is set.
     *
     * @since 1.2.0
     *
     * @param string $key The extension key.
     * @return bool
     */
    public function has_extension(string $key): bool
    {
        return array_key_exists($key, $this->extensions);
    }

    /**
     * Get all extensions as a keyed array.
     *
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function get_extensions(): array
    {
        return $this->extensions;
    }

    /**
     * Convert the metadata to an array, suitable for REST/JSON serialization.
     *
     * Wire format (stable):
     *   [
     *     'type'        => string,
     *     'scope'       => string,
     *     'label'       => string,
     *     'description' => string,
     *     'categories'  => array<int, string>,
     *     'arguments'   => array<int, array>,    // each via ArgumentSchema::to_array()
     *     'extensions'  => array<string, mixed>, // plugin-specific bag
     *   ]
     *
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return array(
            'type'        => $this->type,
            'scope'       => $this->scope,
            'label'       => $this->label,
            'description' => $this->description,
            'categories'  => $this->categories,
            'arguments'   => array_map(
                fn(ArgumentSchema $arg) => $arg->to_array(),
                $this->get_arguments()
            ),
            'extensions'  => $this->extensions,
        );
    }
}
