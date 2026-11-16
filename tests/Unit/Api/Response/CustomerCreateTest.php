<?php
/**
 * Test CustomerCreate class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\CustomerCreate;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;
use AcquiredComForWooCommerce\Tests\Framework\Helpers\ReflectionHelper;
use Exception;

/**
 * Test CustomerCreate class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\CustomerCreate
 */
class CustomerCreateTest extends ResponseTestCase {
	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$test_data = [
			'success' => [
				'customer_id' => 'customer_1234567890',
			],
		];

		$this->set_test_response_data( $test_data );
	}

	/**
	 * Test validate_data when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\CustomerCreate::validate_data
	 * @return void
	 */
	public function test_validate_data_success(): void {
		$result = CustomerCreate::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'CustomerCreate' );

		// Test if we get a CustomerCreate instance.
		$this->assertInstanceOf( CustomerCreate::class, $result );

		// Test response data.
		$this->assertTrue( $result->request_is_success() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test validate_data when customer_type is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\CustomerCreate::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_customer_type(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->customer_id );

		$result     = CustomerCreate::make( $this->mock_response( 200, 'OK', $response_data ), [], 'CustomerCreate' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a CustomerCreate instance.
		$this->assertInstanceOf( CustomerCreate::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Required customer data not found.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test set_status when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\CustomerCreate::set_status
	 * @return void
	 */
	public function test_set_status_success(): void {
		$result = CustomerCreate::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'CustomerCreate' );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test set_status when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\CustomerCreate::set_status
	 * @return void
	 */
	public function test_set_status_error(): void {
		$result = CustomerCreate::make( $this->mock_response( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ), [], 'CustomerCreate' );
		$this->assertEquals( 'error', $result->get_status() );
	}

	/**
	 * Test customer data when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\CustomerCreate::is_created
	 * @covers \AcquiredComForWooCommerce\Api\Response\CustomerCreate::get_customer_id
	 * @return void
	 */
	public function test_customer_data_success(): void {
		$result = CustomerCreate::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'CustomerCreate' );
		$this->assertTrue( $result->is_created() );
		$this->assertEquals( 'customer_1234567890', $result->get_customer_id() );
	}

	/**
	 * Test customer data when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\CustomerCreate::is_created
	 * @covers \AcquiredComForWooCommerce\Api\Response\CustomerCreate::get_customer_id
	 * @return void
	 */
	public function test_customer_data_error(): void {
		$result = CustomerCreate::make( $this->mock_response( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ), [], 'CustomerCreate' );
		$this->assertFalse( $result->is_created() );
		$this->assertNull( $result->get_customer_id() );
	}
}
