<?php
/**
 * Test Token class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\Token;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;
use AcquiredComForWooCommerce\Tests\Framework\Helpers\ReflectionHelper;
use Exception;

/**
 * Test Token class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\Token
 */
class TokenTest extends ResponseTestCase {
	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$test_data = [
			'success' => [
				'token_type'   => 'Bearer',
				'expires_in'   => 3600,
				'access_token' => 'token_1234567890',
			],
		];

		$this->set_test_response_data( $test_data );
	}

	/**
	 * Test validate_data when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Token::validate_data
	 * @return void
	 */
	public function test_validate_data_success(): void {
		$result = Token::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Token' );

		// Test if we get a Token instance.
		$this->assertInstanceOf( Token::class, $result );

		// Test response data.
		$this->assertTrue( $result->request_is_success() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test validate_data when token_type is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Token::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_token_type(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->token_type );

		$result     = Token::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Token' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a Token instance.
		$this->assertInstanceOf( Token::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Access token creation failed.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test validate_data when access_token is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Token::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_access_token(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->access_token );

		$result     = Token::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Token' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a Token instance.
		$this->assertInstanceOf( Token::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Access token creation failed.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test set_status when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Token::set_status
	 * @return void
	 */
	public function test_set_status_success(): void {
		$result = Token::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Token' );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test set_status when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Token::set_status
	 * @return void
	 */
	public function test_set_status_error(): void {
		$result = Token::make( $this->mock_response( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ), [], 'Token' );
		$this->assertEquals( 'error', $result->get_status() );
	}

	/**
	 * Test get_token_formatted when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Token::get_token_formatted
	 * @return void
	 */
	public function test_get_token_formatted_success(): void {
		$result = Token::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Token' );
		$this->assertEquals( 'Bearer token_1234567890', $result->get_token_formatted() );
	}

	/**
	 * Test get_token_formatted when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Token::get_token_formatted
	 * @return void
	 */
	public function test_get_token_formatted_error(): void {
		$result = Token::make( $this->mock_response( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ), [], 'Token' );
		$this->assertNull( $result->get_token_formatted() );
	}

	/**
	 * Test get_log_data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Token::get_log_data
	 * @return void
	 */
	public function test_get_log_data_error(): void {
		$result = Token::make(
			$this->mock_response(
				200,
				'OK',
				$this->get_test_response_data( 'success' )
			),
			[
				'app_id'  => 'test_id',
				'app_key' => 'test_key',
			],
			'Token'
		);

		// Test log data.

		$log_data = $result->get_log_data();

		$this->assertIsArray( $log_data );
		$this->assertArrayNotHasKey( 'request_body', $log_data );
		$this->assertArrayNotHasKey( 'response_body', $log_data );
		$this->assertCount( 3, $log_data );
	}
}
