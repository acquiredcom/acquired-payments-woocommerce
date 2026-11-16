<?php
/**
 * Test TransactionAction class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\TransactionAction;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;
use AcquiredComForWooCommerce\Tests\Framework\Helpers\ReflectionHelper;
use Exception;

/**
 * Test TransactionAction class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\TransactionAction
 */
class TransactionActionTest extends ResponseTestCase {
	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$test_data = [
			'success'  => [
				'transaction_id' => 'transaction_1234567890',
				'status'         => 'success',
			],
			'pending'  => [
				'transaction_id' => 'transaction_1234567890',
				'status'         => 'pending',
			],
			'declined' => [
				'transaction_id' => 'transaction_1234567890',
				'status'         => 'declined',
			],
			'blocked'  => [
				'transaction_id' => 'transaction_1234567890',
				'status'         => 'blocked',
			],
		];

		$this->set_test_response_data( $test_data );
	}

	/**
	 * Test validate_data when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::validate_data
	 * @return void
	 */
	public function test_validate_data_success(): void {
		$result = TransactionAction::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'TransactionAction' );

		// Test if we get a TransactionAction instance.
		$this->assertInstanceOf( TransactionAction::class, $result );

		// Test response data.
		$this->assertTrue( $result->request_is_success() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test validate_data when transaction_id is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_transaction_id(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->transaction_id );

		$result     = TransactionAction::make( $this->mock_response( 200, 'OK', $response_data ), [], 'TransactionAction' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a TransactionAction instance.
		$this->assertInstanceOf( TransactionAction::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Required transaction data not found.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test validate_data when status is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_status(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->status );

		$result     = TransactionAction::make( $this->mock_response( 200, 'OK', $response_data ), [], 'TransactionAction' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a TransactionAction instance.
		$this->assertInstanceOf( TransactionAction::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Required transaction data not found.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test transaction data when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::get_transaction_id
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::action_is_successful
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::get_decline_reason
	 * @return void
	 */
	public function test_transaction_data_success(): void {
		$result     = TransactionAction::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'TransactionAction' );
		$reflection = new ReflectionHelper( $result );

		$this->assertEquals( 'transaction_1234567890', $result->get_transaction_id() );
		$this->assertTrue( $reflection->get_private_method_value( 'action_is_successful' ) );
		$this->assertNull( $result->get_decline_reason() );
	}

	/**
	 * Test transaction data when pending.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::get_transaction_id
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::action_is_successful
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::get_decline_reason
	 * @return void
	 */
	public function test_transaction_data_pending(): void {
		$result     = TransactionAction::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'pending' ) ), [], 'TransactionAction' );
		$reflection = new ReflectionHelper( $result );

		$this->assertEquals( 'transaction_1234567890', $result->get_transaction_id() );
		$this->assertTrue( $reflection->get_private_method_value( 'action_is_successful' ) );
		$this->assertNull( $result->get_decline_reason() );
	}

	/**
	 * Test transaction data when declined.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::get_transaction_id
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::action_is_successful
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::get_decline_reason
	 * @return void
	 */
	public function test_transaction_data_declined(): void {
		$result     = TransactionAction::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'declined' ) ), [], 'TransactionAction' );
		$reflection = new ReflectionHelper( $result );

		$this->assertEquals( 'transaction_1234567890', $result->get_transaction_id() );
		$this->assertFalse( $reflection->get_private_method_value( 'action_is_successful' ) );
		$this->assertEquals( 'declined', $result->get_decline_reason() );
	}

	/**
	 * Test transaction data when blocked.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::get_transaction_id
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::action_is_successful
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionAction::get_decline_reason
	 * @return void
	 */
	public function test_transaction_data_blocked(): void {
		$result     = TransactionAction::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'blocked' ) ), [], 'TransactionAction' );
		$reflection = new ReflectionHelper( $result );

		$this->assertEquals( 'transaction_1234567890', $result->get_transaction_id() );
		$this->assertFalse( $reflection->get_private_method_value( 'action_is_successful' ) );
		$this->assertEquals( 'blocked', $result->get_decline_reason() );
	}
}
