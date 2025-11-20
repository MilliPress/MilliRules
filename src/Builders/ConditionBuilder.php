<?php

/**
 * Condition Builder
 *
 * Fluent API for building rule conditions.
 *
 * @package     MilliRules
 * @subpackage  Builders
 * @author      Philipp Wellmer <hello@millirules.com>
 */

namespace MilliRules\Builders;

use MilliRules\Rules;

/**
 * Condition Builder
 *
 * Fluent API for building rule conditions.
 *
 * Provides dynamic method resolution for conditions - any method call is converted to a condition
 * configuration. Method names are converted from camelCase to snake_case for condition types.
 *
 * Example: ->isUserLoggedIn(true) becomes ['type' => 'is_user_logged_in', 'value' => true, 'operator' => 'IS']
 *
 * PHP Condition Methods (examples - any snake_case condition type can be called):
 * @method self request_url($value, string $operator = 'LIKE') Check request URL
 * @method self request_header(string $name, $value = null, ?string $operator = null) Check the request header
 * @method self request_method($value, string $operator = '=') Check request method
 * @method self request_param(string $name, $value = null, ?string $operator = null) Check request parameter
 * @method self cookie(string $name, $value = null, string $operator = 'LIKE') Check cookie exists or value
 *
 * WordPress Boolean Condition Methods (is_* prefix - boolean checks):
 * @method self is_archive($value = true, string $operator = 'IS')
 * @method self is_home($value = true, string $operator = 'IS')
 * @method self is_front_page($value = true, string $operator = 'IS')
 * @method self is_single($value = true, string $operator = 'IS')
 * @method self is_page($value = true, string $operator = 'IS')
 * @method self is_singular($value = true, string $operator = 'IS')
 * @method self is_category($value = true, string $operator = 'IS')
 * @method self is_tag($value = true, string $operator = 'IS')
 * @method self is_tax($value = true, string $operator = 'IS')
 * @method self is_date($value = true, string $operator = 'IS')
 * @method self is_author($value = true, string $operator = 'IS')
 * @method self is_search($value = true, string $operator = 'IS')
 * @method self is_404($value = true, string $operator = 'IS')
 * @method self is_admin($value = true, string $operator = 'IS')
 * @method self is_customize_preview($value = true, string $operator = 'IS')
 * @method self is_embed($value = true, string $operator = 'IS')
 * @method self is_attachment($value = true, string $operator = 'IS')
 * @method self is_comment_feed($value = true, string $operator = 'IS')
 * @method self is_post_type_archive($value = true, string $operator = 'IS')
 * @method self is_paged($value = true, string $operator = 'IS')
 * @method self is_user_logged_in(bool $value = true, string $operator = 'IS')
 *
 * WordPress Value Condition Methods (no is_ prefix - return scalar values):
 * @method self post($value, string $operator = '=') Check current post by ID or slug
 * @method self post_type($value, string $operator = '=') Check current post type
 * @method self post_status($value, string $operator = '=') Check current post status
 * @method self post_parent($value, string $operator = '=') Check post parent ID
 * @method self author($value, string $operator = '=') Check post author by ID or username
 * @method self user_role($value, string $operator = 'IN') Check current user roles
 * @method self category($value, string $operator = '=') Check queried category (ID, slug, or name)
 * @method self tag($value, string $operator = '=') Check queried tag (ID, slug, or name)
 * @method self taxonomy($value, string $operator = '=') Check queried taxonomy name
 * @method self term($value, string $operator = '=') Check queried term slug
 * @method self query_var(string $name, $value = null, ?string $operator = null) Check WordPress query variable
 * @method self template($value, string $operator = '=') Check page template
 * @method self wp_environment($value, string $operator = '=') Check WordPress environment type
 * @method self constant(string $name, $value = null, ?string $operator = null) Check a PHP constant (including WordPress constants)
 *
 * Finalization Methods (delegated to Rules):
 * @method ActionBuilder then(?array $actions = null) Start building actions or set actions directly
 *
 * Auto-delegation:
 * When a Rules method is called (e.g., ->then(), ->register()), automatically transfers conditions to Rules
 * and delegates the method call, allowing seamless chaining from ConditionBuilder to Rules/ActionBuilder.
 *
 * @package MilliRules
 * @subpackage Builders
 * @since 0.1.0
 *
 * @see custom() For adding custom conditions from third-party plugins
 * @see Rules::register_condition() For registering custom condition callbacks
 */
class ConditionBuilder
{
    /**
     * Collected conditions.
     *
     * @since 0.1.0
     * @var array<int, array<string, mixed>>
     */
    private array $conditions = array();

    /**
     * Match type for conditions (all/any/none).
     *
     * @since 0.1.0
     * @var string
     */
    private string $match_type;

    /**
     * Registered namespaces to search for conditions.
     *
     * @since 0.1.0
     * @var array<int, string>
     */
    private array $namespaces;

    /**
     * Reference to parent Rules object.
     *
     * @since 0.1.0
     * @var Rules
     */
    private Rules $rule_builder;

    /**
     * Constructor.
     *
     * @since 0.1.0
     *
     * @param Rules $rule_builder The parent Rules object.
     * @param string      $match_type   The match type (all/any/none).
     */
    public function __construct(Rules $rule_builder, string $match_type = 'all')
    {
        $this->rule_builder = $rule_builder;
        $this->match_type   = $match_type;

        // Initialize default namespaces to search for conditions.
        $this->namespaces = array(
            'MilliRules\\Conditions\\',
            'MilliRules\\Packages\\PHP\\Conditions\\',
            'MilliRules\\Packages\\WordPress\\Conditions\\',
        );
    }

    /**
     * Add a namespace to search for conditions.
     *
     * @since 0.1.0
     *
     * @param string $namespace The fully-qualified namespace to add.
     * @return self
     */
    public function add_namespace(string $namespace): self
    {
        if (! in_array($namespace, $this->namespaces, true)) {
            $this->namespaces[] = $namespace;
        }
        return $this;
    }

    /**
     * Get registered namespaces.
     *
     * @since 0.1.0
     *
     * @return array<int, string>
     */
    public function get_namespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Set match type to 'all'.
     *
     * @since 0.1.0
     *
     * @return self
     */
    public function match_all(): self
    {
        $this->match_type = 'all';
        return $this;
    }

    /**
     * Set the match type to 'any'.
     *
     * @since 0.1.0
     *
     * @return self
     */
    public function match_any(): self
    {
        $this->match_type = 'any';
        return $this;
    }

    /**
     * Set the match type to 'none'.
     *
     * @since 0.1.0
     *
     * @return self
     */
    public function match_none(): self
    {
        $this->match_type = 'none';
        return $this;
    }

    /**
     * Add custom condition (for 3rd-party extensions and dynamically registered conditions).
     *
     * Use this method for conditions registered via Rules::register_condition() by other plugins.
     *
     * When passing a callable as the second parameter (inline callback), the callback
     * receives only the Context parameter for a cleaner signature.
     *
     * Examples:
     *   ->custom('is_premium_user')
     *   ->custom('has_role', ['role' => 'editor'])
     *   ->custom('my_check', function(Context $context) {
     *       // Inline condition logic - only receives Context
     *       return $context->get('user.role') === 'admin';
     *   })
     *
     * @since 0.1.0
     *
     * @param string                                    $type The condition type identifier.
     * @param array<string, mixed>|callable(\MilliRules\Context): bool|null $arg  The condition configuration or a callable function.
     *                                                                 Inline callback signature: function(\MilliRules\Context $context): bool
     *                                                                 Registered callback signature: function(array $args, \MilliRules\Context $context): bool
     * @return self
     */
    public function custom(string $type, $arg = array()): self
    {
        // Handle callback passed as the second parameter.
        if (is_callable($arg)) {
            // Wrap callback to pass only Context (args is redundant for inline callbacks).
            $wrappedCallback = function($args, $context) use ($arg) {
                // Call original callback with only Context.
                return call_user_func($arg, $context);
            };

            // Register the wrapped callback.
            Rules::register_condition($type, $wrappedCallback);

            // Use the provided type with empty config.
            $this->conditions[] = array( 'type' => $type );

            return $this;
        }

        // Ensure $arg is an array before using it.
        if (! is_array($arg)) {
            $arg = array();
        }
        $arg['type'] = $type;
        $this->conditions[] = $arg;
        return $this;
    }

    /**
     * End condition building and return Rules object.
     *
     * @since 0.1.0
     * @deprecated No longer needed - use magic method delegation instead
     *
     * @return Rules
     */
    public function end(): Rules
    {
        return $this->rule_builder->set_conditions($this->conditions, $this->match_type);
    }

    /**
     * Magic method to handle auto-delegation and auto-resolution of condition methods.
     *
     * Automatically switches context from ConditionBuilder to Rules when a Rules method is called,
     * or creates condition configurations from method names.
     *
     * Method name resolution:
     * - Converts camelCase to snake_case (e.g., isUserLoggedIn → is_user_logged_in)
     * - Intelligently builds condition config from arguments based on condition type
     * - Name-based conditions (header, param, cookie, constant): first arg is name, second is value
     * - Value-based conditions: first arg is value, second is operator
     * - Auto-infers operator from value type (array → IN, boolean → IS, wildcards → LIKE, etc.)
     *
     * Auto-delegation:
     * - If method exists on Rules object, transfers conditions and delegates the call
     * - Allows seamless chaining like ->isHome()->then()->register()
     *
     * @since 0.1.0
     *
     * @param string            $method The method name.
     * @param array<int, mixed> $args The method arguments.
     * @return self|mixed Returns self for condition methods, or mixed for delegated/special methods.
     * @throws \BadMethodCallException If the method doesn't exist on Rules or as a condition.
     */
    public function __call(string $method, array $args)
    {
        // Check if this is a Rules method (auto-delegation).
        if (method_exists($this->rule_builder, $method)) {
            // Transfer collected conditions to Rules.
            $this->rule_builder->set_conditions($this->conditions, $this->match_type);

            // Delegate the call to Rules.
            $callable = array( $this->rule_builder, $method );
            assert(is_callable($callable));
            return call_user_func_array($callable, $args);
        }

        // Check if this is one of the special builder methods.
        $special_methods = array( 'match_all', 'match_any', 'match_none', 'custom', 'add_namespace', 'get_namespaces' );
        if (in_array($method, $special_methods, true) && method_exists($this, $method)) {
            return call_user_func_array(array( $this, $method ), $args);
        }

        // Auto-resolve condition type from method name.
        // Convert: isUserLoggedIn() → is_user_logged_in.
        $type = $this->method_to_type($method);

        // Build condition configuration from arguments.
        $config = $this->build_condition_config($type, $args);

        // Add to the condition array.
        $this->conditions[] = $config;

        return $this;
    }

    /**
     * Get collected conditions.
     *
     * @since 0.1.0
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_conditions(): array
    {
        return $this->conditions;
    }

    /**
     * Get match type.
     *
     * @since 0.1.0
     *
     * @return string
     */
    public function get_match_type(): string
    {
        return $this->match_type;
    }

    // ===========================
    // Helper Methods
    // ===========================

    /**
     * Convert the method name to a condition type.
     *
     * Converts camelCase method name to snake_case type.
     * Example: isUserLoggedIn → is_user_logged_in
     *
     * @since 0.1.0
     *
     * @param string $method The method name.
     * @return string The condition type.
     */
    private function method_to_type(string $method): string
    {
        // Convert camelCase to snake_case.
        $replaced = preg_replace('/(?<!^)[A-Z]/', '_$0', $method);
        return strtolower(is_string($replaced) ? $replaced : $method);
    }

    /**
     * Build condition configuration from method arguments.
     *
     * Intelligently maps method arguments to condition config based on argument count and types.
     *
     * @since 0.1.0
     *
     * @param string            $type The condition type.
     * @param array<int, mixed> $args The method arguments.
     * @return array<string, mixed> The condition configuration.
     */
    private function build_condition_config(string $type, array $args): array
    {
		// Store raw arguments for conditions that need them (e.g., IsConditional).
		$config = [
			'type' => $type,
			'_args' => $args,
		];

		// No arguments - boolean condition.
		if (empty($args)) {
			$config['operator'] = 'IS';
			return $config;
		}

		$operator = null;
		if (is_string(end($args)) && $this->is_operator(end($args))) {
			$operator = array_pop($args);
		}

		// Name-based condition: 2+ args means first is name, second is value.
		// Examples: ->cookie('session_id', 'abc123') or ->cookie('session_id', 'abc123', '=')
		if (count($args) >= 2) {
			$config['name'] = $args[0];
			$config['value'] = $args[1];
			$config['operator'] = $operator ?? $this->infer_operator($args[1], '=');
			return $config;
		}

		// Value-based condition: 1 arg means it's the value to compare.
		// Examples: ->user_age(18) or ->user_age(18, '>=')
		$config['value'] = $args[0];
		$config['operator'] = $operator ?? $this->infer_operator($args[0], '=');
		return $config;
    }

    /**
     * Check if a string is a known operator.
     *
     * @since 0.1.0
     *
     * @param string $value The string to check.
     * @return bool True if the value is a known operator, false otherwise.
     */
    private function is_operator(string $value): bool
    {
        $operators = array(
            '=', '==', '!=', '<>', '>', '>=', '<', '<=',
            'LIKE', 'NOT LIKE',
            'IN', 'NOT IN',
            'REGEXP',
            'EXISTS', 'NOT EXISTS',
            'IS',
        );

        return in_array($value, $operators, true);
    }

    /**
     * Intelligently infer operator based on value type and pattern.
     *
     * @since 0.1.0
     *
     * @param mixed  $value    The value to check.
     * @param string $operator The explicitly provided operator.
     * @return string The inferred or provided operator.
     */
    private function infer_operator($value, string $operator): string
    {
        // If operator explicitly provided, use it.
        if ('=' !== $operator && 'LIKE' !== $operator) {
            return $operator;
        }

        // Array value → IN.
        if (is_array($value)) {
            return 'IN';
        }

        // Boolean value → IS.
        if (is_bool($value)) {
            return 'IS';
        }

        // Null value → EXISTS.
        if (null === $value) {
            return 'EXISTS';
        }

        // String with wildcards → LIKE.
        if (is_string($value) && ( strpos($value, '*') !== false || strpos($value, '?') !== false )) {
            return 'LIKE';
        }

        // String with regex pattern → REGEXP.
        if (is_string($value) && preg_match('/^\/.*\/$/', $value)) {
            return 'REGEXP';
        }

        // Default to provided operator.
        return $operator;
    }
}
