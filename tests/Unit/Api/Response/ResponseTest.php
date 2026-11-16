<?php
/**
 * Test Response class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\Response;
use AcquiredComForWooCommerce\Tests\Framework\Helpers\ReflectionHelper;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;
use Exception;

/**
 * Test Response class.
 *
 * @covers \AcquiredComForWooCommerce\Api\Response\Response
 */
class ResponseTest extends ResponseTestCase {
	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$test_data = [
			'success' => [
				'transaction_id' => 'transaction_1234567890',
				'card_id'        => 'card_1234567890',
				'status'         => 'success',
				'payment_method' => 'card',
				'reason'         => '',
				'created'        => '2025-05-24T08:42:20Z',
			],
			'decline' => [
				'transaction_id' => 'transaction_1234567890',
				'card_id'        => 'card_1234567890',
				'status'         => 'declined',
				'payment_method' => 'card',
				'reason'         => 'Insufficient funds',
				'created'        => '2025-05-24T08:42:20Z',
			],
		];

		$this->set_test_response_data( $test_data );
	}

	/**
	 * Test with ResponseInterface.
	 *
	 * @return void
	 */
	public function test_response(): void {
		$result = Response::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ) );

		// Test if we get a Response instance.
		$this->assertInstanceOf( Response::class, $result );

		// Test if the request is successful.
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );

		// Test response data.
		$reflection = new ReflectionHelper( $result );
		$this->assertEquals( '', $reflection->get_private_method_value( 'get_error_message' ) );
		$this->assertEquals( 200, $reflection->get_private_method_value( 'get_status_code' ) );
		$this->assertEquals( 'OK', $reflection->get_private_method_value( 'get_reason_phrase' ) );
	}

	/**
	 * Test has_body_field.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Response::has_body_field
	 * @return void
	 */
	public function test_has_body_field(): void {
		$result     = Response::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ) );
		$reflection = new ReflectionHelper( $result );

		// Test with field that has a value.
		$this->assertTrue( $reflection->get_private_method_value( 'has_body_field', 'transaction_id' ) );

		// Test with field that is empty.
		$this->assertTrue( $reflection->get_private_method_value( 'has_body_field', 'reason' ) );

		// Test with missing field.
		$this->assertFalse( $reflection->get_private_method_value( 'has_body_field', 'missing_field' ) );
	}

	/**
	 * Test with RequestException.
	 *
	 * @return void
	 */
	public function test_request_exception(): void {
		$result = Response::make( $this->mock_request_exception( 400, 'Bad Request', (object) $this->get_test_response_data( 'error_validation' ) ) );

		// Test if we get a Response instance.
		$this->assertInstanceOf( Response::class, $result );

		// Test if the request is an error.
		$this->assertTrue( $result->request_is_error() );
		$this->assertFalse( $result->request_is_success() );
		$this->assertEquals( 'error', $result->get_status() );

		// Test response data.
		$reflection = new ReflectionHelper( $result );
		$this->assertEquals( 'Bad Request', $reflection->get_private_method_value( 'get_error_message' ) );
		$this->assertEquals( 400, $reflection->get_private_method_value( 'get_status_code' ) );
		$this->assertEquals( 'Bad Request', $reflection->get_private_method_value( 'get_reason_phrase' ) );
	}

	/**
	 * Test with Exception.
	 *
	 * @return void
	 */
	public function test_exception(): void {
		$exception = new Exception( 'Something went wrong' );
		$result    = Response::make( $exception );

		// Test if we get a Response instance.
		$this->assertInstanceOf( Response::class, $result );

		// Test if the request is an error.
		$this->assertTrue( $result->request_is_error() );
		$this->assertFalse( $result->request_is_success() );
		$this->assertEquals( 'error_unknown', $result->get_status() );

		// Test response data.
		$reflection = new ReflectionHelper( $result );
		$this->assertEquals( 'Something went wrong', $reflection->get_private_method_value( 'get_error_message' ) );
		$this->assertEquals( 0, $reflection->get_private_method_value( 'get_status_code' ) );
		$this->assertEquals( '', $reflection->get_private_method_value( 'get_reason_phrase' ) );
	}

	/**
	 * Test with RuntimeException.
	 *
	 * @return void
	 */
	public function test_runtime_exception(): void {
		$response = $this->mock_runtime_exception( 200, 'OK' );
		$result   = Response::make( $response );

		// Test if we get a Response instance.
		$this->assertInstanceOf( Response::class, $result );

		// Test response data.
		$this->assertTrue( $result->request_is_error() );
		$this->assertFalse( $result->request_is_success() );
		$this->assertEquals( 'error_unknown', $result->get_status() );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Failed to read stream' );
		$reflection = new ReflectionHelper( $result );
		$this->assertEquals( 'Failed to read stream', $reflection->get_private_method_value( 'get_error_message' ) );
		$this->assertEquals( 200, $reflection->get_private_method_value( 'get_status_code' ) );
		$this->assertEquals( 'OK', $reflection->get_private_method_value( 'get_reason_phrase' ) );
		$reflection->get_private_method_value( 'read_content', $response );
	}

	/**
	 * Test get_error_message_formatted.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Response::get_error_message_formatted
	 * @return void
	 */
	public function test_get_error_message_formatted(): void {
		$result = Response::make( $this->mock_request_exception( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ) );

		$reflection         = new ReflectionHelper( $result );
		$invalid_parameters = $reflection->get_private_method_value( 'get_body_field', 'invalid_parameters' );

		// Test the invalid parameters.
		$this->assertIsArray( $invalid_parameters );
		$this->assertCount( 2, $invalid_parameters );
		$this->assertEquals( 'The amount must be a positive number.', $invalid_parameters[0]->reason );
		$this->assertEquals( 'The currency code is invalid.', $invalid_parameters[1]->reason );

		// Test the formatted invalid parameters.
		$invalid_parameters_formatted = $reflection->get_private_property_value( 'invalid_parameters' );
		$this->assertIsArray( $invalid_parameters_formatted );
		$this->assertCount( 2, $invalid_parameters_formatted );
		$this->assertEquals( 'amount - The amount must be a positive number.', $invalid_parameters_formatted[0] );
		$this->assertEquals( 'currency - The currency code is invalid.', $invalid_parameters_formatted[1] );

		// Test the error message formatted.
		$error_message = $result->get_error_message_formatted( true );
		$this->assertStringContainsString( 'Error message:', $error_message );
		$this->assertStringContainsString( 'Invalid parameters:', $error_message );

		// Test the error message formatted without invalid parameters.
		$error_message = $result->get_error_message_formatted( false );
		$this->assertStringContainsString( 'Error message:', $error_message );
		$this->assertStringNotContainsString( 'Invalid parameters:', $error_message );
	}

	/**
	 * Test get_error_message_formatted without invalid_parameters.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Response::get_error_message_formatted
	 * @return void
	 */
	public function test_get_error_message_formatted_without_invalid_parameters(): void {
		$result = Response::make( $this->mock_request_exception( 400, 'Bad Request', $this->get_test_response_data( 'error_authorization' ) ) );

		$reflection         = new ReflectionHelper( $result );
		$invalid_parameters = $reflection->get_private_method_value( 'get_body_field', 'invalid_parameters' );

		// Test the invalid parameters.
		$this->assertNull( $invalid_parameters );

		// Test the formatted invalid parameters.
		$invalid_parameters_formatted = $reflection->get_private_property_value( 'invalid_parameters' );
		$this->assertIsArray( $invalid_parameters_formatted );
		$this->assertCount( 0, $invalid_parameters_formatted );

		// Test the error message formatted.
		$error_message = $result->get_error_message_formatted( true );
		$this->assertStringContainsString( 'Error message:', $error_message );
		$this->assertStringNotContainsString( 'Invalid parameters:', $error_message );
	}

	/**
	 * Test get_error_message_formatted with no error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Response::get_error_message_formatted
	 * @return void
	 */
	public function test_get_error_message_formatted_with_no_error(): void {
		$result     = Response::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ) );
		$reflection = new ReflectionHelper( $result );

		// Test the error message doesn't exist if request was successful.
		$this->assertEquals( '', $result->get_error_message_formatted() );
		$this->assertEquals( '', $reflection->get_private_method_value( 'get_error_message' ) );
	}

	/**
	 * Test validate_data with invalid body.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Response::validate_data
	 * @return void
	 */
	public function test_validate_data_with_invalid_body(): void {
		$result     = Response::make( new Exception( 'Test exception' ) );
		$reflection = new ReflectionHelper( $result );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid response body' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test read_content with invalid response.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Response::read_content
	 * @return void
	 */
	public function test_read_content_with_invalid_response(): void {
		$response   = Response::make( new Exception( 'Test exception' ) );
		$reflection = new ReflectionHelper( $response );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Empty response.' );
		$reflection->get_private_method_value( 'read_content', null );
	}

	/**
	 * Test get_log_data when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Response::get_log_data
	 * @return void
	 */
	public function test_get_log_data_success(): void {
		$result = Response::make(
			$this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ),
			[
				'amount'   => 1000,
				'currency' => 'GBP',
			]
		);

		// Test log data.

		$log_data = $result->get_log_data();

		$this->assertIsArray( $log_data );
		$this->assertArrayHasKey( 'status', $log_data );
		$this->assertArrayHasKey( 'response_code', $log_data );
		$this->assertArrayHasKey( 'reason_phrase', $log_data );
		$this->assertArrayHasKey( 'request_body', $log_data );
		$this->assertArrayHasKey( 'response_body', $log_data );
		$this->assertArrayNotHasKey( 'error_message', $log_data );
		$this->assertEquals( 'success', $log_data['status'] );
		$this->assertEquals( 200, $log_data['response_code'] );
		$this->assertEquals( 'OK', $log_data['reason_phrase'] );
		$this->assertEquals(
			[
				'amount'   => 1000,
				'currency' => 'GBP',
			],
			$log_data['request_body']
		);
		$this->assertEquals(
			(object) [
				'transaction_id' => 'transaction_1234567890',
				'status'         => 'success',
				'payment_method' => 'card',
				'card_id'        => 'card_1234567890',
				'reason'         => '',
				'created'        => '2025-05-24T08:42:20Z',
			],
			$log_data['response_body']
		);
	}

	/**
	 * Test get_log_data when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Response::get_log_data
	 * @return void
	 */
	public function test_get_log_data_error(): void {
		$result = Response::make(
			$this->mock_request_exception(
				400,
				'Bad Request',
				$this->get_test_response_data( 'error_validation' )
			),
			[
				'amount'   => -1000,
				'currency' => 'INVALID',
			]
		);

		// Test log data.

		$log_data = $result->get_log_data();

		$this->assertIsArray( $log_data );
		$this->assertArrayHasKey( 'status', $log_data );
		$this->assertArrayHasKey( 'response_code', $log_data );
		$this->assertArrayHasKey( 'reason_phrase', $log_data );
		$this->assertArrayHasKey( 'request_body', $log_data );
		$this->assertArrayHasKey( 'response_body', $log_data );
		$this->assertArrayHasKey( 'error_message', $log_data );
		$this->assertEquals( 'error', $log_data['status'] );
		$this->assertEquals( 400, $log_data['response_code'] );
		$this->assertEquals( 'Bad Request', $log_data['reason_phrase'] );
		$this->assertEquals(
			[
				'amount'   => -1000,
				'currency' => 'INVALID',
			],
			$log_data['request_body']
		);
		$this->assertEquals(
			(object) [
				'status'             => 'error',
				'error_type'         => 'validation',
				'title'              => 'Your request parameters did not pass our validation.',
				'invalid_parameters' => [
					(object) [
						'parameter' => 'amount',
						'reason'    => 'The amount must be a positive number.',
					],
					(object) [
						'parameter' => 'currency',
						'reason'    => 'The currency code is invalid.',
					],
				],
			],
			$log_data['response_body']
		);
	}
}
