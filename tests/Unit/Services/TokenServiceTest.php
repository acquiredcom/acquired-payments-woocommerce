<?php
/**
 * TokenServiceTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Services;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Services\TokenService;
use Mockery;
use Exception;

/**
 * TokenServiceTest class.
 *
 * @covers \AcquiredComForWooCommerce\Services\TokenService
 */
class TokenServiceTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;

	/**
	 * TokenService class.
	 *
	 * @var TokenService
	 */
	private TokenService $service;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->service = new TokenService( $this->config['plugin_id'] );
		$this->initialize_reflection( $this->service );
	}

	/**
	 * Test constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertEquals( 'acfw', $this->get_private_property_value( 'gateway_id' ) );
	}

	/**
	 * Test get_token success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::get_token
	 * @return void
	 */
	public function test_get_token_success(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'get_gateway_id' )->once()->andReturn( 'acfw' );

		// Mock WC_Payment_Tokens.
		$payment_tokens = Mockery::mock( 'overload:WC_Payment_Tokens' );
		$payment_tokens->shouldReceive( 'get' )->once()->with( 123 )->andReturn( $token );

		// Test the method.
		$this->assertInstanceOf( 'WC_Payment_Token_CC', $this->service->get_token( 123 ) );
	}

	/**
	 * Test get_token not found.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::get_token
	 * @return void
	 */
	public function test_get_token_not_found(): void {
		// Mock WC_Payment_Tokens.
		$payment_tokens = Mockery::mock( 'overload:WC_Payment_Tokens' );
		$payment_tokens->shouldReceive( 'get' )->once()->with( 123 )->andReturn( null );

		// Test the method.
		$this->assertNull( $this->service->get_token( 123 ) );
	}

	/**
	 * Test get_token returns null when gateway ID doesn't match.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::get_token
	 * @return void
	 */
	public function test_get_token_token_not_our_payment_gateway(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'get_gateway_id' )->once()->andReturn( 'other_payment_method' );

		// Mock WC_Payment_Tokens.
		$payment_tokens = Mockery::mock( 'overload:WC_Payment_Tokens' );
		$payment_tokens->shouldReceive( 'get' )->once()->with( 123 )->andReturn( $token );

		// Test the method.
		$this->assertNull( $this->get_private_method_value( 'get_token', 123 ) );
	}

	/**
	 * Test get_user_tokens.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::get_user_tokens
	 * @return void
	 */
	public function test_get_user_tokens_success(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );

		// Mock WC_Payment_Tokens.
		$payment_tokens = Mockery::mock( 'overload:WC_Payment_Tokens' );
		$payment_tokens->shouldReceive( 'get_tokens' )
			->once()
			->with(
				[
					'user_id'    => 456,
					'gateway_id' => 'acfw',
				]
			)
			->andReturn( [ $token ] );

		// Test the method.
		$result = $this->service->get_user_tokens( 456 );
		$this->assertIsArray( $result );
		$this->assertCount( 1, $result );
		$this->assertInstanceOf( 'WC_Payment_Token_CC', $result[0] );
	}

	/**
	 * Test get_user_tokens returns empty array when no tokens.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::get_user_tokens
	 * @return void
	 */
	public function test_get_user_tokens_returns_empty_array(): void {
		// Mock WC_Payment_Tokens.
		$payment_tokens = Mockery::mock( 'overload:WC_Payment_Tokens' );
		$payment_tokens->shouldReceive( 'get_tokens' )
			->once()
			->with(
				[
					'user_id'    => 456,
					'gateway_id' => 'acfw',
				]
			)
			->andReturn( [] );

		// Test the method.
		$result = $this->service->get_user_tokens( 456 );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test get_token_by_user_and_card_id returns token when found.
	 *
	 * @runInSeparateProcess
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::get_token_by_user_and_card_id
	 * @return void
	 */
	public function test_get_token_by_user_and_card_id_success(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'get_token' )->once()->andReturn( 'token_123' );

		// Mock WC_Payment_Tokens.
		$payment_tokens = Mockery::mock( 'overload:WC_Payment_Tokens' );
		$payment_tokens->shouldReceive( 'get_tokens' )
			->once()
			->with(
				[
					'user_id'    => 456,
					'gateway_id' => 'acfw',
				]
			)
			->andReturn( [ $token ] );

		// Test the method.
		$this->assertInstanceOf( 'WC_Payment_Token_CC', $this->service->get_token_by_user_and_card_id( 456, 'token_123' ) );
	}

	/**
	 * Test get_token_by_user_and_card_id throws exception when token id is not found.
	 *
	 * @runInSeparateProcess
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::get_token_by_user_and_card_id
	 * @return void
	 */
	public function test_get_token_by_user_and_token_id_not_found(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'get_token' )->once()->andReturn( 'token_456' );

		// Mock WC_Payment_Tokens.
		$payment_tokens = Mockery::mock( 'overload:WC_Payment_Tokens' );
		$payment_tokens->shouldReceive( 'get_tokens' )
			->once()
			->with(
				[
					'user_id'    => 456,
					'gateway_id' => 'acfw',
				]
			)
			->andReturn( [ $token ] );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Token not found.' );
		$this->service->get_token_by_user_and_card_id( 456, 'token_123' );
	}

	/**
	 * Test get_token_by_user_and_card_id throws exception when no tokens exist.
	 *
	 * @runInSeparateProcess
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::get_token_by_user_and_card_id
	 * @return void
	 */
	public function test_get_token_by_user_and_card_id_no_tokens(): void {
		// Mock WC_Payment_Tokens.
		$payment_tokens = Mockery::mock( 'overload:WC_Payment_Tokens' );
		$payment_tokens->shouldReceive( 'get_tokens' )
			->once()
			->with(
				[
					'user_id'    => 456,
					'gateway_id' => 'acfw',
				]
			)
			->andReturn( [] );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Token not found.' );
		$this->service->get_token_by_user_and_card_id( 456, 'token_123' );
	}

	/**
	 * Test payment_token_exists returns true when token exists.
	 *
	 * @runInSeparateProcess
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::payment_token_exists
	 * @return void
	 */
	public function test_payment_token_exists_returns_true(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'get_token' )->once()->andReturn( 'token_123' );

		// Mock WC_Payment_Tokens.
		$payment_tokens = Mockery::mock( 'overload:WC_Payment_Tokens' );
		$payment_tokens->shouldReceive( 'get_tokens' )
			->once()
			->with(
				[
					'user_id'    => 456,
					'gateway_id' => 'acfw',
				]
			)
			->andReturn( [ $token ] );

		// Test the method.
		$this->assertTrue( $this->service->payment_token_exists( 456, 'token_123' ) );
	}

	/**
	 * Test payment_token_exists returns false when token doesn't exist.
	 *
	 * @runInSeparateProcess
	 * @covers \AcquiredComForWooCommerce\Services\TokenService::payment_token_exists
	 * @return void
	 */
	public function test_payment_token_exists_returns_false(): void {
		// Mock WC_Payment_Tokens.
		$payment_tokens = Mockery::mock( 'overload:WC_Payment_Tokens' );
		$payment_tokens->shouldReceive( 'get_tokens' )
			->once()
			->with(
				[
					'user_id'    => 456,
					'gateway_id' => 'acfw',
				]
			)
			->andReturn( [] );

		// Test the method.
		$this->assertFalse( $this->service->payment_token_exists( 456, 'token_123' ) );
	}
}
