<?php
/**
 * Test TransactionCapture class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\TransactionCapture;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;

/**
 * Test TransactionCapture class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\TransactionCapture
 */
class TransactionCaptureTest extends ResponseTestCase {
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
	 * Test is_captured when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionCapture::is_captured
	 * @return void
	 */
	public function test_is_captured_success(): void {
		$result = TransactionCapture::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'TransactionCapture' );
		$this->assertTrue( $result->is_captured() );
	}

	/**
	 * Test is_captured when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionCapture::is_captured
	 * @return void
	 */
	public function test_is_captured_error(): void {
		$result = TransactionCapture::make( $this->mock_response( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ), [], 'TransactionCapture' );
		$this->assertFalse( $result->is_captured() );
	}
}
