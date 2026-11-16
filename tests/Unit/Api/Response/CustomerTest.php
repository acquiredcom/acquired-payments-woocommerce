<?php
/**
 * Test Customer class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\Customer;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;
use AcquiredComForWooCommerce\Tests\Framework\Helpers\ReflectionHelper;
use Exception;

/**
 * Test Customer class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\Customer
 */
class CustomerTest extends ResponseTestCase {
	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$test_data = [
			'success' => [
				'reference' => 'reference_1234567890',
			],
		];

		$this->set_test_response_data( $test_data );
	}

	/**
	 * Test validate_data when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Customer::validate_data
	 * @return void
	 */
	public function test_validate_data_success(): void {
		$result = Customer::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Customer' );

		// Test if we get a Customer instance.
		$this->assertInstanceOf( Customer::class, $result );

		// Test response data.
		$this->assertTrue( $result->request_is_success() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test validate_data when reference is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Customer::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_reference(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->reference );

		$result     = Customer::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Customer' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a Customer instance.
		$this->assertInstanceOf( Customer::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Required customer data not found.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test set_status when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Customer::set_status
	 * @return void
	 */
	public function test_set_status_success(): void {
		$result = Customer::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Customer' );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test set_status when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Customer::set_status
	 * @return void
	 */
	public function test_set_status_error(): void {
		$result = Customer::make( $this->mock_response( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ), [], 'Customer' );
		$this->assertEquals( 'error', $result->get_status() );
	}
}
