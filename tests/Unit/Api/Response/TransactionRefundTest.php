<?php
/**
 * Test TransactionRefund class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\TransactionRefund;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;

/**
 * Test TransactionRefund class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\TransactionRefund
 */
class TransactionRefundTest extends ResponseTestCase {
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
	 * Test is_refunded when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionRefund::is_refunded
	 * @return void
	 */
	public function test_is_refunded_success(): void {
		$result = TransactionRefund::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'TransactionRefund' );
		$this->assertTrue( $result->is_refunded() );
	}

	/**
	 * Test is_refunded when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionRefund::is_refunded
	 * @return void
	 */
	public function test_is_refunded_error(): void {
		$result = TransactionRefund::make( $this->mock_response( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ), [], 'TransactionRefund' );
		$this->assertFalse( $result->is_refunded() );
	}
}
