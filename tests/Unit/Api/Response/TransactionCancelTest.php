<?php
/**
 * Test TransactionCancel class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\TransactionCancel;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;

/**
 * Test TransactionCancel class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\TransactionCancel
 */
class TransactionCancelTest extends ResponseTestCase {
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
				'status'         => 'success',
			],
		];

		$this->set_test_response_data( $test_data );
	}
	/**
	 * Test is_cancelled when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionCancel::is_cancelled
	 * @return void
	 */
	public function test_is_cancelled_success(): void {
		$result = TransactionCancel::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'TransactionCancel' );
		$this->assertTrue( $result->is_cancelled() );
	}

	/**
	 * Test is_cancelled when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionCancel::is_cancelled
	 * @return void
	 */
	public function test_is_cancelled_error(): void {
		$result = TransactionCancel::make( $this->mock_response( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ), [], 'TransactionCancel' );
		$this->assertFalse( $result->is_cancelled() );
	}
}
