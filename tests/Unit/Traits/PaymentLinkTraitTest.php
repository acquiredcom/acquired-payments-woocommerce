<?php
/**
 * PaymentLinkTraitTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Traits;

use AcquiredComForWooCommerce\Tests\Framework\TraitTestCase;
use AcquiredComForWooCommerce\Tests\Framework\TestClasses\PaymentLinkTraitTest as TestClass;
use AcquiredComForWooCommerce\Tests\Framework\Traits\CustomerFactoryMock;
use Brain\Monkey\Functions;
use Exception;
use Mockery;

/**
 * Test PaymentLink trait.
 *
 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink
 */
class PaymentLinkTraitTest extends TraitTestCase {
	/**
	 * Get the test class instance.
	 *
	 * @return object
	 */
	protected function get_test_class(): object {
		return new TestClass( $this->config );
	}

	/**
	 * Test format order ID for payment link with WC_Order.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::format_order_id_for_payment_link
	 * @return void
	 */
	public function test_format_order_id_for_payment_link_with_order(): void {
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );
		$this->assertEquals( '123-wc_order_key', $this->get_private_method_value( 'format_order_id_for_payment_link', $order ) );
	}

	/**
	 * Test format order ID for payment link with WC_Customer.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::format_order_id_for_payment_link
	 * @return void
	 */
	public function test_format_order_id_for_payment_link_with_customer(): void {
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->once()->andReturn( 456 );

		Functions\expect( 'wp_generate_password' )
			->once()
			->with( 13, false )
			->andReturn( 'random123abcdef' );

		$this->assertEquals( '456-add_payment_method_random123abcdef', $this->get_private_method_value( 'format_order_id_for_payment_link', $customer ) );
	}

	/**
	 * Test get ID from incoming data order ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::get_id_from_incoming_data_order_id
	 * @return void
	 */
	public function test_get_id_from_incoming_data_order_id(): void {
		// Valid format.
		$this->assertEquals( 123, $this->get_private_method_value( 'get_id_from_incoming_data_order_id', '123-wc_order_key' ) );

		// Invalid formats.
		$this->assertNull( $this->get_private_method_value( 'get_id_from_incoming_data_order_id', 'invalid' ) );
		$this->assertNull( $this->get_private_method_value( 'get_id_from_incoming_data_order_id', 'invalid-key-123' ) );
	}

	/**
	 * Test get key from incoming data order ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::get_key_from_incoming_data_order_id
	 * @return void
	 */
	public function test_get_key_from_incoming_data_order_id(): void {
		// Valid format.
		$this->assertEquals( 'wc_order_key', $this->get_private_method_value( 'get_key_from_incoming_data_order_id', '123-wc_order_key' ) );

		// Invalid format.
		$this->assertNull( $this->get_private_method_value( 'get_key_from_incoming_data_order_id', 'invalid' ) );
		$this->assertNull( $this->get_private_method_value( 'get_key_from_incoming_data_order_id', 'invalid-key-123' ) );
	}

	/**
	 * Test get WC order from incoming data with invalid order ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::get_wc_order_from_incoming_data
	 * @return void
	 */
	public function test_get_wc_order_from_incoming_data_with_invalid_order_id(): void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No valid order ID in incoming data.' );
		$this->get_private_method_value( 'get_wc_order_from_incoming_data', 'invalid' );
	}

	/**
	 * Test get WC order from incoming data with non-existent order.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::get_wc_order_from_incoming_data
	 * @return void
	 */
	public function test_get_wc_order_from_incoming_data_with_non_existent_order(): void {
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( false );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Failed to find order. Order ID: 123.' );
		$this->get_private_method_value( 'get_wc_order_from_incoming_data', '123-wc_order_key' );
	}

	/**
	 * Test get WC order from incoming data with invalid order key.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::get_wc_order_from_incoming_data
	 * @return void
	 */
	public function test_get_wc_order_from_incoming_data_with_invalid_order_key(): void {
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'invalid-key-123' );

		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Order key in incoming data is invalid. Order ID: 123.' );
		$this->get_private_method_value( 'get_wc_order_from_incoming_data', '123-wc_order_key' );
	}

	/**
	 * Test get WC order from incoming data with valid order.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::get_wc_order_from_incoming_data
	 * @return void
	 */
	public function test_get_wc_order_from_incoming_data_with_valid_order(): void {
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );

		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		$this->assertInstanceOf( 'WC_Order', $this->get_private_method_value( 'get_wc_order_from_incoming_data', '123-wc_order_key' ) );
	}

	/**
	 * Test get WC customer from incoming data with invalid customer ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::get_wc_customer_from_incoming_data
	 * @return void
	 */
	public function test_get_wc_customer_from_incoming_data_with_invalid_customer_id(): void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No valid customer ID in incoming data.' );
		$this->get_private_method_value( 'get_wc_customer_from_incoming_data', 'invalid' );
	}

	/**
	 * Test get WC customer from incoming data with non-existent customer.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::get_wc_customer_from_incoming_data
	 * @return void
	 */
	public function test_get_wc_customer_from_incoming_data_with_non_existent_customer(): void {
		// Mock CustomerFactory.
		$this->test_class->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( 456 )
			->andThrow( new Exception( 'Failed to find customer. Customer ID: 456.' ) );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Failed to find customer. Customer ID: 456.' );
		$this->get_private_method_value( 'get_wc_customer_from_incoming_data', '456-add_payment_method_key' );
	}

	/**
	 * Test get WC customer from incoming data with valid customer.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::get_wc_customer_from_incoming_data
	 * @return void
	 */
	public function test_get_wc_customer_from_incoming_data_with_valid_customer(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->once()->andReturn( 456 );

		// Mock CustomerFactory.
		$this->test_class->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( 456 )
			->andReturn( $customer );

		// Test the method.
		$result = $this->get_private_method_value( 'get_wc_customer_from_incoming_data', '456-add_payment_method_key' );
		$this->assertInstanceOf( 'WC_Customer', $result );
		$this->assertEquals( 456, $result->get_id() );
	}

	/**
	 * Test is for payment method.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::is_for_payment_method
	 * @return void
	 */
	public function test_is_for_payment_method(): void {
		// Payment method link.
		$result = $this->test_class->is_for_payment_method( '456-add_payment_method_key' );
		$this->assertTrue( $result );

		// Order link.
		$this->assertFalse( $this->test_class->is_for_payment_method( '123-wc_order_key' ) );
	}

	/**
	 * Test is for order.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::is_for_order
	 * @return void
	 */
	public function test_is_for_order(): void {
		// Order link.
		$this->assertTrue( $this->test_class->is_for_order( '123-wc_order_key' ) );

		// Payment method link.
		$this->assertFalse( $this->test_class->is_for_order( '456-add_payment_method_key' ) );
	}

	/**
	 * Test get pay URL.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\PaymentLink::get_pay_url
	 * @return void
	 */
	public function test_get_pay_url(): void {
		$this->test_class->get_settings_service()
			->shouldReceive( 'get_pay_url' )
			->once()
			->andReturn( 'https://pay.acquired.com/v1/' );

		$this->assertEquals(
			'https://pay.acquired.com/v1/test-link-id',
			$this->get_private_method_value( 'get_pay_url', 'test-link-id' )
		);
	}
}
