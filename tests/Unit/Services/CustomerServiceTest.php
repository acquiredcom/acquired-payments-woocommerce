<?php
/**
 * CustomerServiceTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Services;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\ApiClientMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\LoggerServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Services\CustomerService;
use AcquiredComForWooCommerce\Api\Response\Response;
use AcquiredComForWooCommerce\Api\Response\CustomerCreate;
use AcquiredComForWooCommerce\Tests\Framework\Traits\CustomerFactoryMock;
use Mockery;
use Brain\Monkey\Functions;
use Exception;

/**
 * CustomerServiceTest class.
 *
 * @covers \AcquiredComForWooCommerce\Services\CustomerService
 */
class CustomerServiceTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use ApiClientMock;
	use LoggerServiceMock;
	use CustomerFactoryMock;

	/**
	 * Test user ID.
	 *
	 * @var int
	 */
	private int $test_user_id = 123;

	/**
	 * Test order ID.
	 *
	 * @var int
	 */
	private int $test_order_id = 456;

	/**
	 * Test customer ID (this is not the same as $test_user_id, it's the ID for the customer in the Acquired API).
	 *
	 * @var string
	 */
	private string $test_customer_id = '789';

	/**
	 * CustomerService class.
	 *
	 * @var CustomerService
	 */
	private CustomerService $service;

	/**
	 * Get test address data.
	 *
	 * @param string $address_type
	 * @return array
	 */
	private function get_test_address_data( string $address_type ): array {
		$address_data = [
			'billing'  => [
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'email'      => 'john@example.com',
				'address_1'  => '123 Main St',
				'city'       => 'London',
				'state'      => '',
				'postcode'   => '123456',
				'country'    => 'UK',
				'phone'      => '1234567890',
			],
			'shipping' => [
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'email'      => 'john@example.com',
				'address_1'  => '456 Other St',
				'city'       => 'Bristol',
				'state'      => '',
				'postcode'   => '789123',
				'country'    => 'UK',
				'phone'      => '1011121314',
			],
		];

		return $address_data[ $address_type ] ?? $address_data['billing'];
	}

	/**
	 * Get expected address data.
	 *
	 * @param bool $add_email
	 * @param bool $address_match
	 * @return array
	 */
	private function get_expected_address_data( bool $add_email, bool $address_match ): array {
		$address_data = [
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'email'      => 'john@example.com',
			'billing'    => [
				'address' => [
					'line_1'       => '123 Main St',
					'line_2'       => '',
					'city'         => 'London',
					'postcode'     => '123456',
					'country_code' => 'uk',
				],
			],
			'shipping'   => [
				'address_match' => $address_match,
				'address'       => [
					'line_1'       => '456 Other St',
					'line_2'       => '',
					'city'         => 'Bristol',
					'postcode'     => '789123',
					'country_code' => 'uk',
				],
			],
		];

		if ( $add_email ) {
			$address_data['billing']['email']  = 'john@example.com';
			$address_data['shipping']['email'] = 'john@example.com';
		}

		if ( $address_match ) {
			unset( $address_data['shipping']['address'] );
			unset( $address_data['shipping']['email'] );
		}

		return $address_data;
	}

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_api_client();
		$this->mock_logger_service();
		$this->mock_customer_factory();

		$this->service = new CustomerService(
			$this->get_api_client(),
			$this->get_logger_service(),
			$this->get_customer_factory(),
		);

		$this->initialize_reflection( $this->service );
	}

	/**
	 * Test constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertSame( $this->get_api_client(), $this->get_private_property_value( 'api_client' ) );
		$this->assertSame( $this->get_logger_service(), $this->get_private_property_value( 'logger_service' ) );
		$this->assertSame( $this->get_customer_factory(), $this->get_private_property_value( 'customer_factory' ) );
	}

	/**
	 * Test truncate_to_length.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::truncate_to_length
	 * @return void
	 */
	public function test_truncate_to_length(): void {
		$input    = 'This is a long string that exceeds the limit.';
		$expected = 'This is a ';

		$result = $this->get_private_method_value( 'truncate_to_length', $input, 10 );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test validate_email.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::validate_email
	 * @return void
	 */
	public function test_validate_email(): void {
		$valid_email   = 'john@example.com';
		$invalid_email = 'invalid-email';

		$this->assertTrue( $this->get_private_method_value( 'validate_email', $valid_email ) );
		$this->assertFalse( $this->get_private_method_value( 'validate_email', $invalid_email ) );
	}

	/**
	 * Test validate_name.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::validate_name
	 * @return void
	 */
	public function test_validate_name(): void {
		$valid_name   = 'John Doe';
		$invalid_name = 'John123';

		$this->assertTrue( $this->get_private_method_value( 'validate_name', $valid_name ) );
		$this->assertFalse( $this->get_private_method_value( 'validate_name', $invalid_name ) );
	}

	/**
	 * Test validate_address.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::validate_address
	 * @return void
	 */
	public function test_validate_address(): void {
		$valid_address   = '123 Main St, London, UK';
		$invalid_address = '#Invalid Address';

		$this->assertTrue( $this->get_private_method_value( 'validate_address', $valid_address ) );
		$this->assertFalse( $this->get_private_method_value( 'validate_address', $invalid_address ) );
	}

	/**
	 * Test format_basic_address_data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::format_basic_address_data
	 * @return void
	 */
	public function test_format_basic_address_data(): void {
		$address_data = [
			'first_name'  => 'John',
			'last_name'   => 'Doe',
			'email'       => 'john@example.com',
			'extra_field' => 'should be ignored',
		];

		$expected = [
			'first_name' => 'John',
			'last_name'  => 'Doe',
			'email'      => 'john@example.com',
		];

		$this->assertEquals(
			$expected,
			$this->get_private_method_value( 'format_basic_address_data', $address_data )
		);
	}

	/**
	 * Test format_basic_address_data with invalid email.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::format_basic_address_data
	 * @return void
	 */
	public function test_format_basic_address_data_with_invalid_email(): void {
		$address_data = [
			'first_name'  => 'John',
			'last_name'   => 'Doe',
			'email'       => '',
			'extra_field' => 'should be ignored',
		];

		$this->assertNull( $this->get_private_method_value( 'format_basic_address_data', $address_data ) );
	}

	/**
	 * Test format_basic_address_data with invalid data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::format_basic_address_data
	 * @return void
	 */
	public function test_format_basic_address_data_with_invalid_data(): void {
		$address_data = [
			'first_name' => '1John',
			'last_name'  => '#Doe',
			'email'      => 'john@example.com',
		];

		$expected = [
			'first_name' => '',
			'last_name'  => '',
			'email'      => 'john@example.com',
		];

		$this->assertEquals(
			$expected,
			$this->get_private_method_value( 'format_basic_address_data', $address_data )
		);
	}

	/**
	 * Test format_address_data with valid data without state.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::format_address_data
	 * @return void
	 */
	public function test_format_address_data_with_valid_data_without_state(): void {
		$result = $this->get_private_method_value( 'format_address_data', $this->get_test_address_data( 'billing' ) );
		$this->assertArrayNotHasKey( 'state', $result );
	}

	/**
	 * Test format_address_data with US address and state.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::format_address_data
	 * @return void
	 */
	public function test_format_address_data_with_us_address_and_state(): void {
		$address_data            = $this->get_test_address_data( 'billing' );
		$address_data['country'] = 'US';
		$address_data['state']   = 'ny';

		$result = $this->get_private_method_value( 'format_address_data', $address_data );
		$this->assertArrayHasKey( 'state', $result );
		$this->assertEquals( 'ny', $result['state'] );
		$this->assertEquals( 'us', $result['country_code'] );
	}

	/**
	 * Test format_address_data with empty address fields.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::format_address_data
	 * @return void
	 */
	public function test_format_address_data_with_empty_address_fields(): void {
		$address_data = [
			'address_1' => '',
			'address_2' => '',
			'city'      => '',
			'postcode'  => '',
			'country'   => '',
			'state'     => '',
		];

		$expected = [
			'line_1'       => '',
			'line_2'       => '',
			'city'         => '',
			'postcode'     => '',
			'country_code' => '',
		];

		$result = $this->get_private_method_value( 'format_address_data', $address_data );
		$this->assertEquals( $expected, $result );
	}

	/**
	 * Test addresses_match with identical addresses.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::addresses_match
	 * @return void
	 */
	public function test_addresses_match_with_identical_addresses(): void {
		$this->assertTrue( $this->get_private_method_value( 'addresses_match', $this->get_test_address_data( 'billing' ), $this->get_test_address_data( 'billing' ) ) );
	}

	/**
	 * Test addresses_match with different addresses.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::addresses_match
	 * @return void
	 */
	public function test_addresses_match_with_different_addresses(): void {
		$this->assertFalse( $this->get_private_method_value( 'addresses_match', $this->get_test_address_data( 'billing' ), $this->get_test_address_data( 'shipping' ) ) );
	}

	/**
	 * Test addresses_match ignores email and phone.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::addresses_match
	 * @return void
	 */
	public function test_addresses_match_ignores_email_and_phone(): void {
		$address_1 = $this->get_test_address_data( 'billing' );
		$address_2 = $this->get_test_address_data( 'billing' );

		// Add data to $address_2 so it's different than $address_1.
		$address_2['email'] = 'diferent@email.com';
		$address_2['phone'] = '0987654321';

		$this->assertTrue( $this->get_private_method_value( 'addresses_match', $address_1, $address_2 ) );
	}

	/**
	 * Test get_address_data_formatted.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_address_data_formatted
	 * @return void
	 */
	public function test_get_address_data_formatted(): void {
		$this->assertEquals(
			$this->get_expected_address_data( false, true ),
			$this->get_private_method_value( 'get_address_data_formatted', $this->get_test_address_data( 'billing' ), [], false )
		);
	}

	/**
	 * Test get_address_data_formatted with email.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_address_data_formatted
	 * @return void
	 */
	public function test_get_address_data_formatted_with_email(): void {
		$this->assertEquals(
			$this->get_expected_address_data( true, true ),
			$this->get_private_method_value( 'get_address_data_formatted', $this->get_test_address_data( 'billing' ), [], true )
		);
	}

	/**
	 * Test get_address_data_formatted with different shipping address.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_address_data_formatted
	 * @return void
	 */
	public function test_get_address_data_formatted_with_different_shipping_address(): void {
		$this->assertEquals(
			$this->get_expected_address_data( false, false ),
			$this->get_private_method_value(
				'get_address_data_formatted',
				$this->get_test_address_data( 'billing' ),
				$this->get_test_address_data( 'shipping' ),
				false
			)
		);
	}

	/**
	 * Test get_address_data_formatted with different shipping address and email.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_address_data_formatted
	 * @return void
	 */
	public function test_get_address_data_formatted_with_different_shipping_address_and_email(): void {
		$this->assertEquals(
			$this->get_expected_address_data( true, false ),
			$this->get_private_method_value(
				'get_address_data_formatted',
				$this->get_test_address_data( 'billing' ),
				$this->get_test_address_data( 'shipping' ),
				true
			)
		);
	}

	/**
	 * Test get_address_data_formatted with empty billing address.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_address_data_formatted
	 * @return void
	 */
	public function test_get_address_data_formatted_with_empty_billing_address(): void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Billing address is empty.' );
		$this->get_private_method_value( 'get_address_data_formatted', [] );
	}

	/**
	 * Test get_address_data_formatted with invalid customer data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_address_data_formatted
	 * @return void
	 */
	public function test_get_address_data_formatted_with_invalid_customer_data(): void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Customer data is not valid.' );
		$this->get_private_method_value( 'get_address_data_formatted', [ 'address_1' => '123 Main St' ] );
	}

	/**
	 * Test get_customer_address_data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_address_data
	 * @return void
	 */
	public function test_get_customer_address_data(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( $this->get_test_address_data( 'billing' ) );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );

		// Test the method.
		$this->assertEquals( $this->get_expected_address_data( true, true ), $this->get_private_method_value( 'get_customer_address_data', $customer ) );
	}

	/**
	 * Test get_customer_address_data without a billing address.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_address_data
	 * @return void
	 */
	public function test_get_customer_address_data_without_billing_address(): void {
		$expected_address_data = [
			'first_name' => '',
			'last_name'  => '',
			'email'      => 'john@example.com',
			'billing'    => [
				'address' => [
					'line_1'       => '',
					'line_2'       => '',
					'city'         => '',
					'postcode'     => '',
					'country_code' => '',
				],
				'email'   => 'john@example.com',
			],
			'shipping'   => [
				'address_match' => true,
			],
		];

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( [] );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$customer->shouldReceive( 'get_email' )->twice()->andReturn( 'john@example.com' );

		// Test the method.
		$this->assertEquals( $expected_address_data, $this->get_private_method_value( 'get_customer_address_data', $customer ) );
	}

	/**
	 * Test get_customer_address_data invalid.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_address_data
	 * @return void
	 */
	public function test_get_customer_address_data_invalid(): void {
		// Set test data.
		$billing_address = $this->get_test_address_data( 'billing' );
		unset( $billing_address['email'] ); // Remove email to simulate invalid data.

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( $billing_address );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$customer->shouldReceive( 'get_email' )->once()->andReturn( '' );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Customer data is not valid.' );
		$this->get_private_method_value( 'get_customer_address_data', $customer );
	}

	/**
	 * Test get_customer_address_data with different shipping address.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_address_data
	 * @return void
	 */
	public function test_get_customer_address_data_with_different_shipping_address(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( $this->get_test_address_data( 'billing' ) );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( true );
		$customer->shouldReceive( 'get_shipping' )->once()->andReturn( $this->get_test_address_data( 'shipping' ) );

		// Test the method.
		$result = $this->get_private_method_value( 'get_customer_address_data', $customer );
		$this->assertArrayHasKey( 'shipping', $result );
		$this->assertFalse( $result['shipping']['address_match'] );
		$this->assertEquals( '456 Other St', $result['shipping']['address']['line_1'] );
		$this->assertEquals( 'Bristol', $result['shipping']['address']['city'] );
	}

	/**
	 * Test get_customer_address_data with same billing and shipping address.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_address_data
	 * @return void
	 */
	public function test_get_customer_address_data_with_same_billing_and_shipping_address(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( $this->get_test_address_data( 'billing' ) );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( true );
		$customer->shouldReceive( 'get_shipping' )->once()->andReturn( $this->get_test_address_data( 'billing' ) );

		// Test the method.
		$this->assertEquals( $this->get_expected_address_data( true, true ), $this->get_private_method_value( 'get_customer_address_data', $customer ) );
	}

	/**
	 * Test get_customer_address_data_from_wc_order with user.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_address_data_from_wc_order
	 * @return void
	 */
	public function test_get_customer_address_data_from_wc_order_with_user(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_address' )->once()->with( 'billing' )->andReturn( $this->get_test_address_data( 'billing' ) );
		$order->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$order->shouldReceive( 'get_user_id' )->once()->andReturn( $this->test_user_id );

		// Test the method.
		$this->assertEquals( $this->get_expected_address_data( true, true ), $this->get_private_method_value( 'get_customer_address_data_from_wc_order', $order ) );
	}

	/**
	 * Test get_customer_address_data_from_wc_order with user and different shipping address.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_address_data_from_wc_order
	 * @return void
	 */
	public function test_get_customer_address_data_from_wc_order_with_user_and_different_shipping_address(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_address' )->once()->with( 'billing' )->andReturn( $this->get_test_address_data( 'billing' ) );
		$order->shouldReceive( 'has_shipping_address' )->once()->andReturn( true );
		$order->shouldReceive( 'get_address' )->once()->with( 'shipping' )->andReturn( $this->get_test_address_data( 'shipping' ) );
		$order->shouldReceive( 'get_user_id' )->once()->andReturn( $this->test_user_id );

		// Test the method.
		$this->assertEquals( $this->get_expected_address_data( true, false ), $this->get_private_method_value( 'get_customer_address_data_from_wc_order', $order ) );
	}

	/**
	 * Test get_customer_address_data_from_wc_order with no user.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_address_data_from_wc_order
	 * @return void
	 */
	public function test_get_customer_address_data_from_wc_order_with_no_user(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_address' )->once()->with( 'billing' )->andReturn( $this->get_test_address_data( 'billing' ) );
		$order->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$order->shouldReceive( 'get_user_id' )->once()->andReturn( 0 );

		// Test the method.
		$this->assertEquals( $this->get_expected_address_data( false, true ), $this->get_private_method_value( 'get_customer_address_data_from_wc_order', $order ) );
	}

	/**
	 * Test create_customer success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::create_customer
	 * @return void
	 */
	public function test_create_customer_success(): void {
		// Set test data.
		$customer_data = $this->get_expected_address_data( true, false );

		// Mock CustomerCreate.
		$response = Mockery::mock( CustomerCreate::class );
		$response->shouldReceive( 'is_created' )->once()->andReturn( true );
		$response->shouldReceive( 'get_customer_id' )->once()->andReturn( $this->test_customer_id );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'create_customer' )
			->once()
			->with( $customer_data )
			->andReturn( $response );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_customer_id', $this->test_customer_id )->andReturn( true );
		$customer->shouldReceive( 'save' )->once()->andReturn( true );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer creation successful.', 'debug', [] );

		// Test the method.
		$this->assertSame( $customer, $this->get_private_method_value( 'create_customer', $customer, $customer_data ) );
	}

	/**
	 * Test create_customer failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::create_customer
	 * @return void
	 */
	public function test_create_customer_failure(): void {
		// Set test data.
		$customer_data = $this->get_expected_address_data( true, false );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );

		// Mock CustomerCreate response.
		$response = Mockery::mock( CustomerCreate::class );
		$response->shouldReceive( 'is_created' )->once()->andReturn( false );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'create_customer' )
			->once()
			->with( $customer_data )
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer creation failed.', 'error', [] );

		// Test the method.
		$this->assertNull( $this->get_private_method_value( 'create_customer', $customer, $customer_data ) );
	}

	/**
	 * Test update_customer with missing customer ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::update_customer
	 * @return void
	 */
	public function test_update_customer_with_missing_customer_id(): void {
		// Set test data.
		$customer_data = $this->get_expected_address_data( true, true );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_meta' )->once()->with( '_acfw_customer_id' )->andReturn( '' );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer ID not found.', 'error' );

		// Test the method.
		$this->assertNull( $this->get_private_method_value( 'update_customer', $customer, $customer_data ) );
	}

	/**
	 * Test update_customer with successful update.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::update_customer
	 * @return void
	 */
	public function test_update_customer_success(): void {
		// Set test data.
		$customer_data = $this->get_expected_address_data( true, true );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_meta' )->once()->with( '_acfw_customer_id' )->andReturn( $this->test_customer_id );

		// Mock Response.
		$response = Mockery::mock( Response::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( true );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Set API client expectations.
		$this->get_api_client()
			->shouldReceive( 'update_customer' )
			->once()
			->with( $this->test_customer_id, $customer_data )
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer update successful.', 'debug', [] );

		// Test the method.
		$this->assertSame( $customer, $this->get_private_method_value( 'update_customer', $customer, $customer_data ) );
	}

	/**
	 * Test update_customer with failed update.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::update_customer
	 * @return void
	 */
	public function test_update_customer_failure(): void {
		// Set test data.
		$customer_data = $this->get_expected_address_data( true, true );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_meta' )->once()->with( '_acfw_customer_id' )->andReturn( $this->test_customer_id );

		// Mock Response.
		$response = Mockery::mock( Response::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( false );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
		->shouldReceive( 'update_customer' )
			->once()
			->with( $this->test_customer_id, $customer_data )
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer update failed.', 'error', [] );

		// Test the method.
		$this->assertNull( $this->get_private_method_value( 'update_customer', $customer, $customer_data ) );
	}

	/**
	 * Test create_or_update_customer_for_checkout with exception.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::create_or_update_customer_for_checkout
	 * @return void
	 */
	public function test_create_or_update_customer_for_checkout_failure(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_user_id' )->once()->andReturn( $this->test_user_id );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $this->test_order_id );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( 123 )
			->andThrow( new Exception( 'Failed to create customer.' ) );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Creating/updating customer data for checkout failed. Order ID: 456. Error: "Failed to create customer.".', 'error' );

		// Test the method.
		$this->assertNull( $this->get_private_method_value( 'create_or_update_customer_for_checkout', $order ) );
	}

	/**
	 * Test create_or_update_customer_for_checkout with create success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::create_or_update_customer_for_checkout
	 * @return void
	 */
	public function test_create_or_update_customer_for_checkout_create_success(): void {
		// Set test data.
		$customer_data = $this->get_expected_address_data( true, true );

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_user_id' )->twice()->andReturn( $this->test_user_id );
		$order->shouldReceive( 'get_address' )->once()->with( 'billing' )->andReturn( $this->get_test_address_data( 'billing' ) );
		$order->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_meta' )->once()->with( '_acfw_customer_id' )->andReturn( '' );
		$customer->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_customer_id', $this->test_customer_id )->andReturn( true );
		$customer->shouldReceive( 'save' )->once()->andReturn( true );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( $this->test_user_id )
			->andReturn( $customer );

		// Mock CustomerCreate response.
		$response = Mockery::mock( CustomerCreate::class );
		$response->shouldReceive( 'is_created' )->once()->andReturn( true );
		$response->shouldReceive( 'get_customer_id' )->once()->andReturn( $this->test_customer_id );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'create_customer' )
			->once()
			->with( $customer_data )
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer creation successful.', 'debug', [] );

		// Test the method.
		$this->assertInstanceOf( 'WC_Customer', $this->get_private_method_value( 'create_or_update_customer_for_checkout', $order ) );
	}

	/**
	 * Test create_or_update_customer_for_checkout with update success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::create_or_update_customer_for_checkout
	 * @return void
	 */
	public function test_create_or_update_customer_for_checkout_update_success(): void {
		// Set test data.
		$customer_data = $this->get_expected_address_data( true, true );

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_user_id' )->twice()->andReturn( $this->test_user_id );
		$order->shouldReceive( 'get_address' )->once()->with( 'billing' )->andReturn( $this->get_test_address_data( 'billing' ) );
		$order->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_meta' )->twice()->with( '_acfw_customer_id' )->andReturn( $this->test_customer_id );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( $this->test_user_id )
			->andReturn( $customer );

		// Mock Response.
		$response = Mockery::mock( Response::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( true );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'update_customer' )
			->once()
			->with( $this->test_customer_id, $customer_data )
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer update successful.', 'debug', [] );

		// Test the method.
		$this->assertInstanceOf( 'WC_Customer', $this->get_private_method_value( 'create_or_update_customer_for_checkout', $order ) );
	}

	/**
	 * Test get_customer_data_for_guest_checkout success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_data_for_guest_checkout
	 * @return void
	 */
	public function test_get_customer_data_for_guest_checkout_success(): void {
		// Set test data.
		$customer_data = $this->get_expected_address_data( false, true );

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_address' )->once()->with( 'billing' )->andReturn( $this->get_test_address_data( 'billing' ) );
		$order->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $this->test_order_id );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Creating customer data for guest checkout successful. Order ID: 456.', 'debug' );

		// Test the method.
		$this->assertEquals( $customer_data, $this->get_private_method_value( 'get_customer_data_for_guest_checkout', $order ) );
	}

	/**
	 * Test get_customer_data_for_guest_checkout failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_data_for_guest_checkout
	 * @return void
	 */
	public function test_get_customer_data_for_guest_checkout_failure(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_address' )->once()->with( 'billing' )->andReturn( [] );
		$order->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $this->test_order_id );

		// Mock LoggerService.
		$this->get_logger_service()
		->shouldReceive( 'log' )
			->once()
			->with( 'Creating customer data for guest checkout failed. Order ID: 456. Error: "Billing address is empty.".', 'error' );

		// Test the method.
		$this->assertEmpty( $this->get_private_method_value( 'get_customer_data_for_guest_checkout', $order ) );
	}

	/**
	 * Test get_customer_data_for_checkout returns guest checkout data when no customer ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_data_for_checkout
	 * @return void
	 */
	public function test_get_customer_data_for_checkout_returns_guest_data_no_customer(): void {
		// Set test data.
		$customer_data = $this->get_expected_address_data( false, true );

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_customer_id' )->once()->andReturn( 0 );
		$order->shouldReceive( 'get_address' )->once()->with( 'billing' )->andReturn( $this->get_test_address_data( 'billing' ) );
		$order->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $this->test_order_id );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Creating customer data for guest checkout successful. Order ID: 456.', 'debug' );

		// Test the method.
		$this->assertEquals( $customer_data, $this->get_private_method_value( 'get_customer_data_for_checkout', $order ) );
	}


	/**
	 * Test get_customer_data_for_checkout returns customer ID when update succeeds.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_data_for_checkout
	 * @return void
	 */
	public function test_get_customer_data_for_checkout_returns_customer_id_success(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_customer_id' )->once()->andReturn( $this->test_user_id );
		$order->shouldReceive( 'get_user_id' )->twice()->andReturn( $this->test_user_id );
		$order->shouldReceive( 'get_address' )->once()->with( 'billing' )->andReturn( $this->get_test_address_data( 'billing' ) );

		$order->shouldReceive( 'has_shipping_address' )
			->once()
			->andReturn( false );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_meta' )->times( 3 )->with( '_acfw_customer_id' )->andReturn( $this->test_customer_id );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( $this->test_user_id )
			->andReturn( $customer );

		// Mock Response.
		$response = Mockery::mock( Response::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( true );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'update_customer' )
			->once()
			->with( $this->test_customer_id, Mockery::any() )
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer update successful.', 'debug', [] );

		// Test the method.
		$this->assertEquals(
			[ 'customer_id' => $this->test_customer_id ],
			$this->get_private_method_value( 'get_customer_data_for_checkout', $order )
		);
	}


	/**
	 * Test get_customer_data_for_checkout returns guest data when update fails.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_data_for_checkout
	 * @return void
	 */
	public function test_get_customer_data_for_checkout_returns_guest_data_on_failure(): void {
		// Set test data.
		$customer_data = $this->get_expected_address_data( false, true );

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_customer_id' )->once()->andReturn( $this->test_user_id );
		$order->shouldReceive( 'get_user_id' )->twice()->andReturn( $this->test_user_id );
		$order->shouldReceive( 'get_address' )->twice()->with( 'billing' )->andReturn( $this->get_test_address_data( 'billing' ) );
		$order->shouldReceive( 'has_shipping_address' )->twice()->andReturn( false );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $this->test_order_id );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_meta' )->once()->with( '_acfw_customer_id' )->andReturn( '' );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( $this->test_user_id )
			->andReturn( $customer );

		// Mock CustomerCreate.
		$response = Mockery::mock( CustomerCreate::class );
		$response->shouldReceive( 'is_created' )->once()->andReturn( false );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'create_customer' )
			->once()
			->with( Mockery::any() )
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer creation failed.', 'error', [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Creating customer data for guest checkout successful. Order ID: 456.', 'debug' );

		// Test the method.
		$this->assertEquals(
			$customer_data,
			$this->get_private_method_value( 'get_customer_data_for_checkout', $order )
		);
	}

	/**
	 * Test update_customer_in_my_account success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::update_customer_in_my_account
	 * @return void
	 */
	public function test_update_customer_in_my_account_success(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( $this->get_test_address_data( 'billing' ) );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$customer->shouldReceive( 'get_meta' )->once()->with( '_acfw_customer_id' )->andReturn( $this->test_customer_id );

		// Mock Response.
		$response = Mockery::mock( Response::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( true );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'update_customer' )
			->once()
			->with( $this->test_customer_id, Mockery::any() )
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer update successful.', 'debug', [] );

		// Test the method.
		$this->get_private_method_value( 'update_customer_in_my_account', $customer );
	}


	/**
	 * Test update_customer_in_my_account get data failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::update_customer_in_my_account
	 * @return void
	 */
	public function test_update_customer_in_my_account_get_data_failure(): void {
		$billing_address = $this->get_test_address_data( 'billing' );
		unset( $billing_address['email'] );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( $billing_address );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$customer->shouldReceive( 'get_id' )->once()->andReturn( $this->test_user_id );
		$customer->shouldReceive( 'get_email' )->once()->andReturn( '' );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Updating customer data in my account failed. User ID: 123. Error: "Customer data is not valid.".', 'error' );

		// Test the method.
		$this->get_private_method_value( 'update_customer_in_my_account', $customer );
	}


	/**
	 * Test update_customer_in_my_account update failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::update_customer_in_my_account
	 * @return void
	 */
	public function test_update_customer_in_my_account_update_failure(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( $this->get_test_address_data( 'billing' ) );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$customer->shouldReceive( 'get_meta' )->once()->with( '_acfw_customer_id' )->andReturn( '' );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer ID not found.', 'error' );

		// Test the method.
		$this->get_private_method_value( 'update_customer_in_my_account', $customer );
	}
	/**
	 * Test get_or_create_customer_for_new_payment_method failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_or_create_customer_for_new_payment_method
	 * @return void
	 */
	public function test_get_or_create_customer_for_new_payment_method_failure(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( [] );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$customer->shouldReceive( 'get_email' )->once()->andReturn( '' );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( $this->test_user_id )
			->andReturn( $customer );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Getting customer for new payment method failed. User ID: 123. Error: "Billing address is empty.".', 'error' );

		// Test the method.
		$this->assertNull( $this->get_private_method_value( 'get_or_create_customer_for_new_payment_method', $this->test_user_id ) );
	}

	/**
	 * Test get_or_create_customer_for_new_payment_method success with existing customer.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_or_create_customer_for_new_payment_method
	 * @return void
	 */
	public function test_get_or_create_customer_for_new_payment_method_existing_customer(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( $this->get_test_address_data( 'billing' ) );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$customer->shouldReceive( 'get_meta' )->once()->with( '_acfw_customer_id' )->andReturn( $this->test_customer_id );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( $this->test_user_id )
			->andReturn( $customer );

		// Test the method.
		$this->assertInstanceOf( 'WC_Customer', $this->get_private_method_value( 'get_or_create_customer_for_new_payment_method', $this->test_user_id ) );
	}

	/**
	 * Test get_or_create_customer_for_new_payment_method success with new customer.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_or_create_customer_for_new_payment_method
	 * @return void
	 */
	public function test_get_or_create_customer_for_new_payment_method_new_customer(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( $this->get_test_address_data( 'billing' ) );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$customer->shouldReceive( 'get_meta' )->once()->with( '_acfw_customer_id' )->andReturn( '' );
		$customer->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_customer_id', $this->test_customer_id );
		$customer->shouldReceive( 'save' )->once()->andReturn( true );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( $this->test_user_id )
			->andReturn( $customer );

		// Mock CustomerCreate.
		$response = Mockery::mock( CustomerCreate::class );
		$response->shouldReceive( 'is_created' )->once()->andReturn( true );
		$response->shouldReceive( 'get_customer_id' )->once()->andReturn( $this->test_customer_id );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'create_customer' )
			->once()
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Customer creation successful.', 'debug', [] );

		// Test the method.
		$this->assertInstanceOf( 'WC_Customer', $this->get_private_method_value( 'get_or_create_customer_for_new_payment_method', $this->test_user_id ) );
	}

	/**
	 * Test get_customer_data_for_new_payment_method returns customer ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_data_for_new_payment_method
	 * @return void
	 */
	public function test_get_customer_data_for_new_payment_method_returns_customer_id(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( $this->get_test_address_data( 'billing' ) );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$customer->shouldReceive( 'get_meta' )->twice()->with( '_acfw_customer_id' )->andReturn( $this->test_customer_id );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( $this->test_user_id )
			->andReturn( $customer );

		// Test the method.
		$this->assertEquals(
			[ 'customer_id' => $this->test_customer_id ],
			$this->service->get_customer_data_for_new_payment_method( $this->test_user_id )
		);
	}

	/**
	 * Test get_customer_data_for_new_payment_method returns empty array on failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_data_for_new_payment_method
	 * @return void
	 */
	public function test_get_customer_data_for_new_payment_method_returns_empty_array(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_billing' )->once()->andReturn( [] );
		$customer->shouldReceive( 'has_shipping_address' )->once()->andReturn( false );
		$customer->shouldReceive( 'get_email' )->once()->andReturn( '' );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( $this->test_user_id )
			->andReturn( $customer );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Getting customer for new payment method failed. User ID: 123. Error: "Billing address is empty.".', 'error' );

		// Test the method.
		$this->assertEquals(
			[],
			$this->service->get_customer_data_for_new_payment_method( $this->test_user_id )
		);
	}

	/**
	 * Test get_customer_from_customer_id success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_from_customer_id
	 * @return void
	 */
	public function test_get_customer_from_customer_id_success(): void {
		// Mock WordPress get_users function.
		Functions\expect( 'get_users' )
			->once()
			->with(
				[
					'meta_key'   => '_acfw_customer_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $this->test_customer_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'number'     => 1,
					'fields'     => 'ID',
				]
			)
			->andReturn( [ $this->test_user_id ] );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( $this->test_user_id )
			->andReturn( $customer );

		// Test the method.
		$result = $this->service->get_customer_from_customer_id( $this->test_customer_id );
		$this->assertInstanceOf( 'WC_Customer', $result );
	}

	/**
	 * Test get_customer_from_customer_id user not found.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\CustomerService::get_customer_from_customer_id
	 * @return void
	 */
	public function test_get_customer_from_customer_id_user_not_found(): void {
		// Mock WordPress get_users function to return empty array.
		Functions\expect( 'get_users' )
			->once()
			->with(
				[
					'meta_key'   => '_acfw_customer_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $this->test_customer_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value,
					'number'     => 1,
					'fields'     => 'ID',
				]
			)
			->andReturn( [] );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'User not found.' );
		$this->service->get_customer_from_customer_id( $this->test_customer_id );
	}
}
