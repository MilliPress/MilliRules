<?php

/**
 * Condition Meta
 *
 * Fluent metadata container for registered condition types. Consumer-relevant
 * information (label, description, categories, operators, arguments) that UIs,
 * CLIs, and documentation generators use to introspect conditions.
 *
 * MilliRules stores all metadata but never interprets it during engine
 * execution. The RuleEngine evaluates conditions via their matches() method,
 * not via metadata. Metadata is purely for consumers.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer <hello@millirules.com>
 * @since       1.2.0
 */

namespace MilliRules\Conditions;

use MilliRules\ArgumentSchema;
use MilliRules\ArgumentsBuilder;

/**
 * Class ConditionMeta
 *
 * Metadata declaration for a registered condition type. Obtained from:
 * - Rules::register_condition() returns a new ConditionMeta for callback-based conditions
 * - BaseCondition::set_meta() configures a ConditionMeta for class-based conditions
 *
 * Example (callback-based):
 *   Rules::register_condition('is_admin', $callback)
 *       ->label('Is Admin')
 *       ->operators('=', '!=')
 *       ->categories('user');
 *
 * Example (class-based):
 *   class RequestUrl extends BaseCondition {
 *       public static function set_meta(ConditionMeta $meta): void {
 *           $meta
 *               ->label('Request URL')
 *               ->description('Match the current request URL.')
 *               ->categories('request')
 *               ->operators('=', '!=', 'LIKE', 'REGEXP', 'IN', 'NOT IN')
 *               ->args()
 *                   ->string('value')->label('URL Pattern')->required();
 *       }
 *   }
 *
 * @since 1.2.0
 */
class ConditionMeta
{
    /**
     * The condition type identifier.
     *
     * @since 1.2.0
     * @var string
     */
    private string $type;

    /**
     * Human-readable label.
     *
     * @since 1.2.0
     * @var string
     */
    private string $label = '';

    /**
     * Help text description.
     *
     * @since 1.2.0
     * @var string
     */
    private string $description = '';

    /**
     * UI grouping categories.
     *
     * @since 1.2.0
     * @var array<int, string>
     */
    private array $categories = array();

    /**
     * Supported comparison operators.
     *
     * Declares which operators the condition supports (e.g., '=', '!=',
     * 'LIKE', 'IN'). UIs use this to populate operator dropdowns.
     *
     * @since 1.2.0
     * @var array<int, string>
     */
    private array $operators = array();

    /**
     * Argument-to-config-key mapping.
     *
     * Tells UIs how builder arguments map to the condition config array.
     * E.g., ['value'] for value-based conditions, ['name', 'value'] for
     * name-based conditions like Cookie or RequestHeader.
     *
     * Set automatically from BaseCondition::get_argument_mapping() when
     * resolved via Rules::get_condition_meta() for class-based conditions.
     *
     * @since 1.2.0
     * @var array<int, string>
     */
    private array $argument_mapping = array();

    /**
     * Arguments builder (lazy).
     *
     * @since 1.2.0
     * @var ArgumentsBuilder|null
     */
    private ?ArgumentsBuilder $args_builder = null;

    /**
     * Plugin-specific metadata bag.
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
     * @param string $type The condition type identifier.
     */
    public function __construct(string $type)
    {
        $this->type = $type;
    }

    // -----------------------------------------------------------------
    // Fluent setters
    // -----------------------------------------------------------------

    /**
     * Set the human-readable label.
     *
     * @since 1.2.0
     *
     * @param string $label The label.
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
     * @param string $description The description.
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
     * Set the supported comparison operators.
     *
     * Declares which operators this condition supports. UIs use this
     * to populate operator dropdowns in rule builders.
     *
     * @since 1.2.0
     *
     * @param string ...$operators One or more operator strings (e.g., '=', '!=', 'LIKE', 'IN').
     * @return self
     */
    public function operators(string ...$operators): self
    {
        $this->operators = $operators;
        return $this;
    }

    /**
     * Set the argument-to-config-key mapping.
     *
     * Typically set automatically from BaseCondition::get_argument_mapping()
     * during class-based resolution. Callback-based conditions can set it
     * manually if needed.
     *
     * @since 1.2.0
     *
     * @param array<int, string> $mapping Array of config keys (e.g., ['value'] or ['name', 'value']).
     * @return self
     */
    public function argument_mapping(array $mapping): self
    {
        $this->argument_mapping = $mapping;
        return $this;
    }

    /**
     * Enter the arguments declaration context.
     *
     * Returns an ArgumentsBuilder that collects the arguments for this
     * condition. Same walking-builder pattern as ActionMeta::args().
     *
     * @since 1.2.0
     *
     * @return ArgumentsBuilder
     */
    public function args(): ArgumentsBuilder
    {
        if (null === $this->args_builder) {
            $this->args_builder = new ArgumentsBuilder($this);
        }
        return $this->args_builder;
    }

    /**
     * Attach plugin-specific metadata under a namespaced key.
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

    // -----------------------------------------------------------------
    // Getters
    // -----------------------------------------------------------------

    /**
     * Get the condition type identifier.
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
     * Get the supported operators.
     *
     * @since 1.2.0
     *
     * @return array<int, string>
     */
    public function get_operators(): array
    {
        return $this->operators;
    }

    /**
     * Get the argument-to-config-key mapping.
     *
     * @since 1.2.0
     *
     * @return array<int, string>
     */
    public function get_argument_mapping(): array
    {
        return $this->argument_mapping;
    }

    /**
     * Get the declared argument schemas.
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
     *     'type'             => string,
     *     'label'            => string,
     *     'description'      => string,
     *     'categories'       => array<int, string>,
     *     'operators'        => array<int, string>,
     *     'argument_mapping' => array<int, string>,
     *     'arguments'        => array<int, array>,
     *     'extensions'       => array<string, mixed>,
     *   ]
     *
     * @since 1.2.0
     *
     * @return array<string, mixed>
     */
    public function to_array(): array
    {
        return array(
            'type'             => $this->type,
            'label'            => $this->label,
            'description'      => $this->description,
            'categories'       => $this->categories,
            'operators'        => $this->operators,
            'argument_mapping' => $this->argument_mapping,
            'arguments'        => array_map(
                fn(ArgumentSchema $arg) => $arg->to_array(),
                $this->get_arguments()
            ),
            'extensions'       => $this->extensions,
        );
    }
}
