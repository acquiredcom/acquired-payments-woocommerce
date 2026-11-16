<?php
/**
 * Test TransactionList class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\TransactionList;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;
use AcquiredComForWooCommerce\Tests\Framework\Helpers\ReflectionHelper;
use Exception;

/**
 * Test TransactionList class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\TransactionList
 */
class TransactionListTest extends ResponseTestCase {
	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$meta = [
			'count'  => 1,
			'offset' => 0,
			'limit'  => 1,
			'total'  => 1,
		];

		$test_data = [
			'success' => [
				'meta' => $meta,
				'data' => [
					[
						'transaction_id' => 'transaction_1234567890',
					],
				],
			],
			'empty'   => [
				'meta' => array_merge(
					$meta,
					[
						'count' => 0,
						'total' => 0,
					]
				),
				'data' => null,
			],
		];

		$this->set_test_response_data( $test_data );
	}

	/**
	 * Test validate_data when data is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionList::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_data(): void {
		$response_data = $this->get_test_response_data( 'empty' );
		unset( $response_data->data );

		$result     = TransactionList::make( $this->mock_response( 200, 'OK', $response_data ), [], 'TransactionList' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a TransactionList instance.
		$this->assertInstanceOf( TransactionList::class, $result );

		// Test response data.
		$this->assertTrue( $result->request_is_error() );
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Required transaction list data not found.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test transaction list with a transaction.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionList::validate_data
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionList::set_status
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionList::get_first_transaction_id
	 * @return void
	 */
	public function test_transaction_list_with_transaction(): void {
		$result = TransactionList::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'TransactionList' );

		// Test if we get a TransactionList instance.
		$this->assertInstanceOf( TransactionList::class, $result );

		// Test response data.
		$this->assertTrue( $result->request_is_success() );
		$this->assertEquals( 'success', $result->get_status() );
		$this->assertEquals( 'transaction_1234567890', $result->get_first_transaction_id() );
	}

	/**
	 * Test transaction list when no transactions match.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionList::get_first_transaction_id
	 * @return void
	 */
	public function test_transaction_list_empty(): void {
		$result = TransactionList::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'empty' ) ), [], 'TransactionList' );

		// Test response data.
		$this->assertTrue( $result->request_is_success() );
		$this->assertNull( $result->get_first_transaction_id() );
	}

	/**
	 * Test transaction list request error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\TransactionList::set_status
	 * @return void
	 */
	public function test_transaction_list_request_error(): void {
		$result = TransactionList::make( $this->mock_request_exception( 401, 'Unauthorized', $this->get_test_response_data( 'error_authorization' ) ), [], 'TransactionList' );

		// Test response data.
		$this->assertTrue( $result->request_is_error() );
		$this->assertEquals( 'error', $result->get_status() );
		$this->assertNull( $result->get_first_transaction_id() );
	}
}
