<?php
/**
 * Placeholder Resolver
 *
 * Resolves dynamic placeholders in rule values.
 *
 * @package     MilliRules
 * @author      Philipp Wellmer
 */

namespace MilliRules;

/**
 * Class PlaceholderResolver
 *
 * Handles resolution of dynamic placeholders using colon-separated syntax.
 * Examples: {post:id}, {request:cookie:name}, {request:header:referer}
 *
 * @since 1.0.0
 */
class PlaceholderResolver {
	/**
	 * Execution context data.
	 *
	 * @since 1.0.0
	 * @var array<string, mixed>
	 */
	protected array $context;

	/**
	 * Custom placeholder resolvers.
	 *
	 * @since 1.0.0
	 * @var array<string, callable>
	 */
	protected static array $custom_resolvers = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed> $context The execution context.
	 */
	public function __construct( array $context ) {
		$this->context = $context;
	}

	/**
	 * Resolve all placeholders in a string value.
	 *
	 * @since 1.0.0
	 *
	 * @param string $value The value containing placeholders.
	 * @return string The value with placeholders resolved.
	 */
	public function resolve( string $value ): string {
		$result = preg_replace_callback(
			'/\{([^}]+)\}/',
			function ( $matches ) {
				$placeholder = $matches[1];
				return $this->get_placeholder_value( $placeholder );
			},
			$value
		);
		return is_string( $result ) ? $result : $value;
	}

	/**
	 * Register a custom placeholder resolver.
	 *
	 * @since 1.0.0
	 *
	 * @param string   $placeholder The placeholder name (e.g., 'custom' for {custom:value}).
	 * @param callable $resolver    The resolver callback (receives context and parts array).
	 * @return void
	 */
	public static function register_placeholder( string $placeholder, callable $resolver ): void {
		self::$custom_resolvers[ $placeholder ] = $resolver;
	}

	/**
	 * Get the value for a placeholder.
	 *
	 * @since 1.0.0
	 *
	 * @param string $placeholder The placeholder name (e.g., 'post:id' or 'request:cookie:name').
	 * @return string The resolved value or the original placeholder if not found.
	 */
	protected function get_placeholder_value( string $placeholder ): string {
		$parts = explode( ':', $placeholder );

		$category = array_shift( $parts );

		if ( ! is_string( $category ) || '' === $category ) {
			return '{' . $placeholder . '}';
		}

		// Check custom resolvers first.
		if ( isset( self::$custom_resolvers[ $category ] ) ) {
			try {
				$value = call_user_func( self::$custom_resolvers[ $category ], $this->context, $parts );
				return null !== $value ? (string) $value : '{' . $placeholder . '}';
			} catch ( \Exception $e ) {
				error_log( 'MilliRules: Error in custom placeholder resolver for ' . $category . ': ' . $e->getMessage() );
				return '{' . $placeholder . '}';
			}
		}

		// Resolve built-in placeholders.
		$value = $this->resolve_builtin_placeholder( $category, $parts );

		if ( null === $value ) {
			return '{' . $placeholder . '}';
		}

		return is_scalar( $value ) ? (string) $value : '{' . $placeholder . '}';
	}

	/**
	 * Resolve built-in placeholder categories.
	 *
	 * @since 1.0.0
	 *
	 * @param string             $category The top-level category.
	 * @param array<int, string> $parts The remaining parts after the category.
	 * @return mixed|null The resolved value or null if not found.
	 */
	protected function resolve_builtin_placeholder( string $category, array $parts ): mixed {
		// Check if the category exists in context.
		if ( ! isset( $this->context[ $category ] ) ) {
			return null;
		}

		// If no parts, return null (can't return the entire category).
		if ( empty( $parts ) ) {
			return null;
		}

		// Navigate through the nested structure.
		$current = $this->context[ $category ];

		foreach ( $parts as $key ) {
			if ( ! is_array( $current ) ) {
				return null;
			}

			if ( ! isset( $current[ $key ] ) ) {
				return null;
			}

			$current = $current[ $key ];
		}

		// Only return scalar values.
		return is_scalar( $current ) ? $current : null;
	}
}
