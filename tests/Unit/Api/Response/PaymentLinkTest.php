<?php
/**
 * Test PaymentLink class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\PaymentLink;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;
use AcquiredComForWooCommerce\Tests\Framework\Helpers\ReflectionHelper;
use Exception;

/**
 * Test PaymentLink class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\PaymentLink
 */
class PaymentLinkTest extends ResponseTestCase {
	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$test_data = [
			'success' => [
				'link_id' => 'link_1234567890',
				'status'  => 'success',
			],
		];

		$this->set_test_response_data( $test_data );
	}

	/**
	 * Test validate_data when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\PaymentLink::validate_data
	 * @return void
	 */
	public function test_validate_data_success(): void {
		$result = PaymentLink::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'PaymentLink' );

		// Test if we get a PaymentLink instance.
		$this->assertInstanceOf( PaymentLink::class, $result );

		// Test response data.
		$this->assertTrue( $result->request_is_success() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test validate_data when link_id is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\PaymentLink::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_link_id(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->link_id );

		$result     = PaymentLink::make( $this->mock_response( 200, 'OK', $response_data ), [], 'PaymentLink' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a PaymentLink instance.
		$this->assertInstanceOf( PaymentLink::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment link ID not found in response.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test get_link_id when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\PaymentLink::get_link_id
	 * @return void
	 */
	public function test_get_link_id_success(): void {
		$result = PaymentLink::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'PaymentLink' );
		$this->assertEquals( 'link_1234567890', $result->get_link_id() );
	}

	/**
	 * Test get_link_id when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\PaymentLink::get_link_id
	 * @return void
	 */
	public function test_get_link_id_error(): void {
		$result = PaymentLink::make( $this->mock_response( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ), [], 'PaymentLink' );
		$this->assertNull( $result->get_link_id() );
	}
}
