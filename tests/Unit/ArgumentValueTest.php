<?php

namespace MilliRules\Tests\Unit;

use MilliRules\Tests\TestCase;
use MilliRules\ArgumentValue;
use MilliRules\PlaceholderResolver;
use MilliRules\Context;

/**
 * Comprehensive tests for ArgumentValue class
 *
 * Tests type conversions, default values, placeholder resolution, and edge cases
 */
class ArgumentValueTest extends TestCase
{
	/**
	 * Create a mock PlaceholderResolver that doesn't actually resolve
	 */
	private function createMockResolver(): PlaceholderResolver
	{
		$context = new Context();
		return new PlaceholderResolver( $context );
	}

	/**
	 * Create a PlaceholderResolver with specific context data
	 */
	private function createResolverWithContext( array $data ): PlaceholderResolver
	{
		$context = new Context();
		foreach ( $data as $key => $value ) {
			$context->set( $key, $value );
		}
		return new PlaceholderResolver( $context );
	}

	// ============================================
	// String Conversion Tests
	// ============================================

	public function testStringWithStringValue(): void
	{
		$arg = new ArgumentValue( 'hello', null, $this->createMockResolver() );
		expect( $arg->string() )->toBe( 'hello' );
	}

	public function testStringWithNullReturnsEmpty(): void
	{
		$arg = new ArgumentValue( null, null, $this->createMockResolver() );
		expect( $arg->string() )->toBe( '' );
	}

	public function testStringWithIntegerValue(): void
	{
		$arg = new ArgumentValue( 123, null, $this->createMockResolver() );
		expect( $arg->string() )->toBe( '123' );
	}

	public function testStringWithBooleanTrue(): void
	{
		$arg = new ArgumentValue( true, null, $this->createMockResolver() );
		expect( $arg->string() )->toBe( '1' );
	}

	public function testStringWithBooleanFalse(): void
	{
		$arg = new ArgumentValue( false, null, $this->createMockResolver() );
		expect( $arg->string() )->toBe( '' );
	}

	public function testStringWithArrayValue(): void
	{
		$arg = new ArgumentValue( array( 'a', 'b', 'c' ), null, $this->createMockResolver() );
		expect( $arg->string() )->toBe( '["a","b","c"]' );
	}

	public function testStringWithObjectReturnsEmpty(): void
	{
		$obj = new \stdClass();
		$arg = new ArgumentValue( $obj, null, $this->createMockResolver() );
		expect( $arg->string() )->toBe( '' );
	}

	// ============================================
	// Boolean Conversion Tests
	// ============================================

	public function testBoolWithTrue(): void
	{
		$arg = new ArgumentValue( true, null, $this->createMockResolver() );
		expect( $arg->bool() )->toBeTrue();
	}

	public function testBoolWithFalse(): void
	{
		$arg = new ArgumentValue( false, null, $this->createMockResolver() );
		expect( $arg->bool() )->toBeFalse();
	}

	public function testBoolWithNullReturnsFalse(): void
	{
		$arg = new ArgumentValue( null, null, $this->createMockResolver() );
		expect( $arg->bool() )->toBeFalse();
	}

	public function testBoolWithStringTrue(): void
	{
		expect( ( new ArgumentValue( 'true', null, $this->createMockResolver() ) )->bool() )->toBeTrue()
			->and( ( new ArgumentValue( 'TRUE', null, $this->createMockResolver() ) )->bool() )->toBeTrue()
			->and( ( new ArgumentValue( 'yes', null, $this->createMockResolver() ) )->bool() )->toBeTrue()
			->and( ( new ArgumentValue( 'YES', null, $this->createMockResolver() ) )->bool() )->toBeTrue()
			->and( ( new ArgumentValue( '1', null, $this->createMockResolver() ) )->bool() )->toBeTrue();
	}

	public function testBoolWithStringFalse(): void
	{
		expect( ( new ArgumentValue( 'false', null, $this->createMockResolver() ) )->bool() )->toBeFalse()
			->and( ( new ArgumentValue( 'FALSE', null, $this->createMockResolver() ) )->bool() )->toBeFalse()
			->and( ( new ArgumentValue( 'no', null, $this->createMockResolver() ) )->bool() )->toBeFalse()
			->and( ( new ArgumentValue( 'NO', null, $this->createMockResolver() ) )->bool() )->toBeFalse()
			->and( ( new ArgumentValue( '0', null, $this->createMockResolver() ) )->bool() )->toBeFalse()
			->and( ( new ArgumentValue( '', null, $this->createMockResolver() ) )->bool() )->toBeFalse();
	}

	public function testBoolWithStringWhitespace(): void
	{
		expect( ( new ArgumentValue( '  true  ', null, $this->createMockResolver() ) )->bool() )->toBeTrue()
			->and( ( new ArgumentValue( '  false  ', null, $this->createMockResolver() ) )->bool() )->toBeFalse();
	}

	public function testBoolWithNonEmptyString(): void
	{
		$arg = new ArgumentValue( 'hello', null, $this->createMockResolver() );
		expect( $arg->bool() )->toBeTrue();
	}

	public function testBoolWithInteger(): void
	{
		expect( ( new ArgumentValue( 1, null, $this->createMockResolver() ) )->bool() )->toBeTrue()
			->and( ( new ArgumentValue( 0, null, $this->createMockResolver() ) )->bool() )->toBeFalse()
			->and( ( new ArgumentValue( 42, null, $this->createMockResolver() ) )->bool() )->toBeTrue();
	}

	// ============================================
	// Integer Conversion Tests
	// ============================================

	public function testIntWithInteger(): void
	{
		$arg = new ArgumentValue( 123, null, $this->createMockResolver() );
		expect( $arg->int() )->toBe( 123 );
	}

	public function testIntWithNullReturnsZero(): void
	{
		$arg = new ArgumentValue( null, null, $this->createMockResolver() );
		expect( $arg->int() )->toBe( 0 );
	}

	public function testIntWithNumericString(): void
	{
		expect( ( new ArgumentValue( '123', null, $this->createMockResolver() ) )->int() )->toBe( 123 )
			->and( ( new ArgumentValue( '0', null, $this->createMockResolver() ) )->int() )->toBe( 0 )
			->and( ( new ArgumentValue( '-456', null, $this->createMockResolver() ) )->int() )->toBe( -456 );
	}

	public function testIntWithFloat(): void
	{
		expect( ( new ArgumentValue( 123.45, null, $this->createMockResolver() ) )->int() )->toBe( 123 )
			->and( ( new ArgumentValue( 123.99, null, $this->createMockResolver() ) )->int() )->toBe( 123 );
	}

	public function testIntWithBooleanTrue(): void
	{
		$arg = new ArgumentValue( true, null, $this->createMockResolver() );
		expect( $arg->int() )->toBe( 1 );
	}

	public function testIntWithBooleanFalse(): void
	{
		$arg = new ArgumentValue( false, null, $this->createMockResolver() );
		expect( $arg->int() )->toBe( 0 );
	}

	public function testIntWithNonNumericString(): void
	{
		$arg = new ArgumentValue( 'hello', null, $this->createMockResolver() );
		expect( $arg->int() )->toBe( 0 );
	}

	// ============================================
	// Float Conversion Tests
	// ============================================

	public function testFloatWithFloat(): void
	{
		$arg = new ArgumentValue( 123.45, null, $this->createMockResolver() );
		expect( $arg->float() )->toBe( 123.45 );
	}

	public function testFloatWithNullReturnsZero(): void
	{
		$arg = new ArgumentValue( null, null, $this->createMockResolver() );
		expect( $arg->float() )->toBe( 0.0 );
	}

	public function testFloatWithInteger(): void
	{
		$arg = new ArgumentValue( 123, null, $this->createMockResolver() );
		expect( $arg->float() )->toBe( 123.0 );
	}

	public function testFloatWithNumericString(): void
	{
		expect( ( new ArgumentValue( '123.45', null, $this->createMockResolver() ) )->float() )->toBe( 123.45 )
			->and( ( new ArgumentValue( '0.0', null, $this->createMockResolver() ) )->float() )->toBe( 0.0 )
			->and( ( new ArgumentValue( '-456.78', null, $this->createMockResolver() ) )->float() )->toBe( -456.78 );
	}

	public function testFloatWithNonNumericString(): void
	{
		$arg = new ArgumentValue( 'hello', null, $this->createMockResolver() );
		expect( $arg->float() )->toBe( 0.0 );
	}

	// ============================================
	// Array Conversion Tests
	// ============================================

	public function testArrayWithArray(): void
	{
		$arr = array( 'a', 'b', 'c' );
		$arg = new ArgumentValue( $arr, null, $this->createMockResolver() );
		expect( $arg->array() )->toBe( $arr );
	}

	public function testArrayWithNullReturnsEmpty(): void
	{
		$arg = new ArgumentValue( null, null, $this->createMockResolver() );
		expect( $arg->array() )->toBe( array() );
	}

	public function testArrayWithJsonString(): void
	{
		$json = '["a","b","c"]';
		$arg  = new ArgumentValue( $json, null, $this->createMockResolver() );
		expect( $arg->array() )->toBe( array( 'a', 'b', 'c' ) );
	}

	public function testArrayWithJsonObject(): void
	{
		$json = '{"name":"John","age":30}';
		$arg  = new ArgumentValue( $json, null, $this->createMockResolver() );
		expect( $arg->array() )->toBe( array( 'name' => 'John', 'age' => 30 ) );
	}

	public function testArrayWithScalarWraps(): void
	{
		expect( ( new ArgumentValue( 'hello', null, $this->createMockResolver() ) )->array() )->toBe( array( 'hello' ) )
			->and( ( new ArgumentValue( 123, null, $this->createMockResolver() ) )->array() )->toBe( array( 123 ) )
			->and( ( new ArgumentValue( true, null, $this->createMockResolver() ) )->array() )->toBe( array( true ) );
	}

	public function testArrayWithInvalidJsonWraps(): void
	{
		$arg = new ArgumentValue( 'not json', null, $this->createMockResolver() );
		expect( $arg->array() )->toBe( array( 'not json' ) );
	}

	public function testArrayWithEmptyString(): void
	{
		$arg = new ArgumentValue( '', null, $this->createMockResolver() );
		expect( $arg->array() )->toBe( array( '' ) );
	}

	// ============================================
	// Raw Value Tests
	// ============================================

	public function testRawReturnsResolvedValue(): void
	{
		$arg = new ArgumentValue( 'hello', null, $this->createMockResolver() );
		expect( $arg->raw() )->toBe( 'hello' );
	}

	public function testRawWithNull(): void
	{
		$arg = new ArgumentValue( null, null, $this->createMockResolver() );
		expect( $arg->raw() )->toBeNull();
	}

	public function testRawWithArray(): void
	{
		$arr = array( 'a', 'b', 'c' );
		$arg = new ArgumentValue( $arr, null, $this->createMockResolver() );
		expect( $arg->raw() )->toBe( $arr );
	}

	// ============================================
	// Default Value Tests
	// ============================================

	public function testDefaultUsedWhenValueIsNull(): void
	{
		$arg = new ArgumentValue( null, 'default', $this->createMockResolver() );
		expect( $arg->string() )->toBe( 'default' );
	}

	public function testDefaultIgnoredWhenValueExists(): void
	{
		$arg = new ArgumentValue( 'actual', 'default', $this->createMockResolver() );
		expect( $arg->string() )->toBe( 'actual' );
	}

	public function testDefaultWithZeroValue(): void
	{
		$arg = new ArgumentValue( 0, 999, $this->createMockResolver() );
		expect( $arg->int() )->toBe( 0 );
	}

	public function testDefaultWithEmptyString(): void
	{
		$arg = new ArgumentValue( '', 'default', $this->createMockResolver() );
		expect( $arg->string() )->toBe( '' );
	}

	public function testDefaultWithFalse(): void
	{
		$arg = new ArgumentValue( false, true, $this->createMockResolver() );
		expect( $arg->bool() )->toBeFalse();
	}

	// ============================================
	// Placeholder Resolution Tests
	// ============================================

	public function testPlaceholderResolutionInString(): void
	{
		$resolver = $this->createResolverWithContext(
			array(
				'user' => array(
					'name'  => 'John',
					'email' => 'john@example.com',
				),
			)
		);

		$arg = new ArgumentValue( '{user.name}', null, $resolver );
		expect( $arg->string() )->toBe( 'John' );
	}

	public function testPlaceholderResolutionWithMultiplePlaceholders(): void
	{
		$resolver = $this->createResolverWithContext(
			array(
				'user' => array(
					'first' => 'John',
					'last'  => 'Doe',
				),
			)
		);

		$arg = new ArgumentValue( 'Hello {user.first} {user.last}!', null, $resolver );
		expect( $arg->string() )->toBe( 'Hello John Doe!' );
	}

	public function testPlaceholderNotResolvedForNonStrings(): void
	{
		$resolver = $this->createResolverWithContext(
			array(
				'user' => array( 'id' => '123' ),
			)
		);

		// Integer should not be resolved
		$arg = new ArgumentValue( 123, null, $resolver );
		expect( $arg->int() )->toBe( 123 );

		// Array should not be resolved
		$arg = new ArgumentValue( array( '{user.id}' ), null, $resolver );
		expect( $arg->array() )->toBe( array( '{user.id}' ) );
	}

	public function testPlaceholderResolutionCached(): void
	{
		$resolver = $this->createResolverWithContext(
			array(
				'user' => array( 'name' => 'John' ),
			)
		);

		$arg = new ArgumentValue( '{user.name}', null, $resolver );

		// Call multiple times - should resolve only once (cached)
		$result1 = $arg->string();
		$result2 = $arg->string();
		$result3 = $arg->raw();

		expect( $result1 )->toBe( 'John' )
			->and( $result2 )->toBe( 'John' )
			->and( $result3 )->toBe( 'John' );
	}

	// ============================================
	// Edge Cases
	// ============================================

	public function testEmptyStringIsNotNull(): void
	{
		$arg = new ArgumentValue( '', 'default', $this->createMockResolver() );
		expect( $arg->string() )->toBe( '' );
	}

	public function testZeroIsNotNull(): void
	{
		$arg = new ArgumentValue( 0, 999, $this->createMockResolver() );
		expect( $arg->int() )->toBe( 0 );
	}

	public function testFalseIsNotNull(): void
	{
		$arg = new ArgumentValue( false, true, $this->createMockResolver() );
		expect( $arg->bool() )->toBeFalse();
	}

	public function testMultipleTypeConversionsOnSameValue(): void
	{
		$arg = new ArgumentValue( '123', null, $this->createMockResolver() );

		expect( $arg->string() )->toBe( '123' )
			->and( $arg->int() )->toBe( 123 )
			->and( $arg->float() )->toBe( 123.0 )
			->and( $arg->array() )->toBe( array( '123' ) );
	}

	public function testTypeJugglingWithStringNumber(): void
	{
		$arg = new ArgumentValue( '42', null, $this->createMockResolver() );

		expect( $arg->string() )->toBe( '42' )
			->and( $arg->int() )->toBe( 42 )
			->and( $arg->bool() )->toBeTrue();
	}
}
