<?php
/**
 * OrderTraitTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Traits;

use AcquiredComForWooCommerce\Tests\Framework\TraitTestCase;
use AcquiredComForWooCommerce\Tests\Framework\TestClasses\OrderTraitTest as TestClass;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Test Order trait.
 *
 * @covers \AcquiredComForWooCommerce\Traits\Order
 */
class OrderTraitTest extends TraitTestCase {
	/**
	 * Get the test class instance.
	 *
	 * @return object
	 */
	protected function get_test_class(): object {
		return new TestClass( $this->config );
	}

	/**
	 * Test get WooCommerce order.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\Order::get_wc_order
	 * @return void
	 */
	public function test_get_wc_order(): void {
		// Test with order ID that doesn't exist.

		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( false );

		$this->assertNull( $this->get_private_method_value( 'get_wc_order', 123 ) );

		// Test with valid order ID.

		$order = Mockery::mock( 'WC_Order' );

		Functions\expect( 'wc_get_order' )
			->once()
			->with( 456 )
			->andReturn( $order );

		$this->assertInstanceOf( 'WC_Order', $this->get_private_method_value( 'get_wc_order', 456 ) );
	}

	/**
	 * Test is Acquired.com payment method.
	 *
	 * @covers \AcquiredComForWooCommerce\Traits\Order::is_acfw_payment_method
	 * @return void
	 */
	public function test_is_acfw_payment_method(): void {
		// Test with order ID that doesn't exist.

		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( false );

		$this->assertFalse( $this->test_class->is_acfw_payment_method( 123 ) );

		// Test with order not made with our payment method.

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'other_payment_method' );

		$this->assertFalse( $this->test_class->is_acfw_payment_method( $order ) );

		// Test with order made by our payment method.

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		$this->assertTrue( $this->test_class->is_acfw_payment_method( $order ) );

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		Functions\expect( 'wc_get_order' )
			->once()
			->with( 456 )
			->andReturn( $order );

		$this->assertTrue( $this->test_class->is_acfw_payment_method( 456 ) );
	}
}
