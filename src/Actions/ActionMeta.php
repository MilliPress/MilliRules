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

/**
 * Class ActionMeta
 *
 * Metadata declaration for a registered action type. Obtained from:
 * - Rules::register_action() returns a new ActionMeta for callback-based actions
 * - BaseAction::describe() returns a new ActionMeta for class-based actions
 *
 * Example (callback-based):
 *   Rules::register_action('add_flag', $callback)
 *       ->scope('flag')
 *       ->label(__('Add Flag', 'millirules'))
 *       ->description(__('Tag the response with a flag.', 'millirules'))
 *       ->category('flags');
 *
 * Example (class-based):
 *   class AddFlag extends BaseAction {
 *       public static function describe(): ActionMeta {
 *           return parent::describe()
 *               ->scope('flag')
 *               ->label(__('Add Flag', 'millirules'))
 *               ->description(__('Tag the response with a flag.', 'millirules'))
 *               ->category('flags');
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
     * UI grouping category (consumer-relevant).
     *
     * @since 1.2.0
     * @var string
     */
    private string $category = '';

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
     * Set the UI grouping category.
     *
     * @since 1.2.0
     *
     * @param string $category The category identifier.
     * @return self
     */
    public function category(string $category): self
    {
        $this->category = $category;
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
     * Get the category.
     *
     * @since 1.2.0
     *
     * @return string
     */
    public function get_category(): string
    {
        return $this->category;
    }

    /**
     * Convert the metadata to an array, suitable for REST/JSON serialization.
     *
     * @since 1.2.0
     *
     * @return array<string, string>
     */
    public function to_array(): array
    {
        return array(
            'type'        => $this->type,
            'scope'       => $this->scope,
            'label'       => $this->label,
            'description' => $this->description,
            'category'    => $this->category,
        );
    }
}
