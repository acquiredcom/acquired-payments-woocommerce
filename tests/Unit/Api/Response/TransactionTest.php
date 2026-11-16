<?php
/**
 * Test Transaction class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\Transaction;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;
use AcquiredComForWooCommerce\Tests\Framework\Helpers\ReflectionHelper;
use Exception;

/**
 * Test Transaction class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\Transaction
 */
class TransactionTest extends ResponseTestCase {
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
	 * Test validate_data when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::validate_data
	 * @return void
	 */
	public function test_validate_data_success(): void {
		$result = Transaction::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Transaction' );

		// Test if we get a Transaction instance.
		$this->assertInstanceOf( Transaction::class, $result );

		// Test response data.
		$this->assertTrue( $result->request_is_success() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test validate_data when transaction_id is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_transaction_id(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->transaction_id );

		$result     = Transaction::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Transaction' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a Transaction instance.
		$this->assertInstanceOf( Transaction::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Required transaction data not found.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test validate_data when status is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_transaction_status(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->status );

		$result     = Transaction::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Transaction' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a Transaction instance.
		$this->assertInstanceOf( Transaction::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Required transaction data not found.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test transaction success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::is_transaction_success
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_transaction_id
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_payment_method
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_card_id
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_decline_reason
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_created_timestamp
	 * @return void
	 */
	public function test_transaction_success(): void {
		$response_data = $this->get_test_response_data( 'success' );

		$result     = Transaction::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Transaction' );
		$reflection = new ReflectionHelper( $result );

		// Test response data.
		$this->assertTrue( $reflection->get_private_method_value( 'is_transaction_success' ) );
		$this->assertTrue( $result->request_is_success() );
		$this->assertEquals( 'transaction_1234567890', $result->get_transaction_id() );
		$this->assertEquals( 'card', $result->get_payment_method() );
		$this->assertEquals( 'card_1234567890', $result->get_card_id() );
		$this->assertEquals( '', $result->get_decline_reason() );
		$this->assertEquals( 1748076140, $result->get_created_timestamp() );
	}

	/**
	 * Test transaction decline.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::is_transaction_success
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_transaction_id
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_payment_method
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_card_id
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_decline_reason
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_created_timestamp
	 * @return void
	 */
	public function test_transaction_decline(): void {
		$response_data = $this->get_test_response_data( 'decline' );

		$result     = Transaction::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Transaction' );
		$reflection = new ReflectionHelper( $result );

		// Test response data.
		$this->assertFalse( $reflection->get_private_method_value( 'is_transaction_success' ) );
		$this->assertTrue( $result->request_is_success() );
		$this->assertEquals( 'transaction_1234567890', $result->get_transaction_id() );
		$this->assertEquals( 'card', $result->get_payment_method() );
		$this->assertEquals( 'card_1234567890', $result->get_card_id() );
		$this->assertEquals( 'Insufficient funds', $result->get_decline_reason() );
		$this->assertEquals( 1748076140, $result->get_created_timestamp() );
	}


	/**
	 * Test transaction decline.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::is_transaction_success
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_transaction_id
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_payment_method
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_card_id
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_decline_reason
	 * @covers \AcquiredComForWooCommerce\Api\Response\Transaction::get_created_timestamp
	 * @return void
	 */
	public function test_transaction_fail(): void {
		$response_data = $this->get_test_response_data( 'error_authorization' );

		$result     = Transaction::make( $this->mock_response( 400, 'Bad Request', $response_data ), [], 'Transaction' );
		$reflection = new ReflectionHelper( $result );

		// Test response data.
		$this->assertFalse( $reflection->get_private_method_value( 'is_transaction_success' ) );
		$this->assertFalse( $result->request_is_success() );
		$this->assertNull( $result->get_transaction_id() );
		$this->assertNull( $result->get_payment_method() );
		$this->assertNull( $result->get_card_id() );
		$this->assertNull( $result->get_decline_reason() );
		$this->assertNull( $result->get_created_timestamp() );
	}
}
