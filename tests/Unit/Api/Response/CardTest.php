<?php
/**
 * Test Card class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\Response;

use AcquiredComForWooCommerce\Api\Response\Card;
use AcquiredComForWooCommerce\Tests\Framework\ResponseTestCase;
use AcquiredComForWooCommerce\Tests\Framework\Helpers\ReflectionHelper;
use Exception;

/**
 * Test Card class.
 *
 * @coversDefaultClass \AcquiredComForWooCommerce\Api\Response\Card
 */
class CardTest extends ResponseTestCase {
	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$test_data = [
			'success' => [
				'card_id'     => 'card_1234567890',
				'customer_id' => 'customer_1234567890',
				'is_active'   => true,
				'card'        => (object) [
					'holder_name'  => 'E Johnson',
					'scheme'       => 'visa',
					'number'       => '8710',
					'expiry_month' => 12,
					'expiry_year'  => 40,
				],
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
		$result = Card::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Card' );

		// Test if we get a Card instance.
		$this->assertInstanceOf( Card::class, $result );

		// Test response data.
		$this->assertTrue( $result->request_is_success() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test validate_data when card data is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Card::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_card(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->card );

		$result     = Card::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Card' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a Card instance.
		$this->assertInstanceOf( Card::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Required card data not found.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test validate_data when customer_id is missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Card::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_customer_id(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->customer_id );

		$result     = Card::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Card' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a Card instance.
		$this->assertInstanceOf( Card::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Required card data not found.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test validate_data when required card fields are missing.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Card::validate_data
	 * @return void
	 */
	public function test_validate_data_missing_required_fields(): void {
		$response_data = $this->get_test_response_data( 'success' );
		unset( $response_data->card->number );

		$result     = Card::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Card' );
		$reflection = new ReflectionHelper( $result );

		// Test if we get a Card instance.
		$this->assertInstanceOf( Card::class, $result );

		// Test response data.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Required card field "number" not found.' );
		$reflection->get_private_method_value( 'validate_data' );
	}

	/**
	 * Test set_status when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Card::set_status
	 * @return void
	 */
	public function test_set_status_success(): void {
		$result = Card::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Card' );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test set_status when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Card::set_status
	 * @return void
	 */
	public function test_set_status_error(): void {
		$result = Card::make( $this->mock_response( 400, 'Bad Request', $this->get_test_response_data( 'error_validation' ) ), [], 'Card' );
		$this->assertEquals( 'error', $result->get_status() );
	}

	/**
	 * Test get_card_data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Card::get_card_data
	 * @return void
	 */
	public function test_get_card_data(): void {
		$card_data = (object) [
			'holder_name'  => 'E Johnson',
			'scheme'       => 'visa',
			'number'       => '8710',
			'expiry_month' => 12,
			'expiry_year'  => 40,
		];

		$result = Card::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Card' );
		$this->assertEquals( $card_data, $result->get_card_data() );
	}

	/**
	 * Test get_card_id.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Card::get_card_id
	 * @return void
	 */
	public function test_get_card_id(): void {
		$result = Card::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Card' );
		$this->assertEquals( 'card_1234567890', $result->get_card_id() );
	}

	/**
	 * Test get_customer_id.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Card::get_customer_id
	 * @return void
	 */
	public function test_get_customer_id(): void {
		$result = Card::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Card' );
		$this->assertEquals( 'customer_1234567890', $result->get_customer_id() );
	}

	/**
	 * Test is_active.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Card::is_active
	 * @return void
	 */
	public function test_is_active_returns_true(): void {
		$result = Card::make( $this->mock_response( 200, 'OK', $this->get_test_response_data( 'success' ) ), [], 'Card' );
		$this->assertTrue( $result->is_active() );
	}

	/**
	 * Test is_active when card is not active.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\Response\Card::is_active
	 * @return void
	 */
	public function test_is_active_returns_false(): void {
		$response_data            = $this->get_test_response_data( 'success' );
		$response_data->is_active = false;

		$result = Card::make( $this->mock_response( 200, 'OK', $response_data ), [], 'Card' );
		$this->assertFalse( $result->is_active() );
	}
}
