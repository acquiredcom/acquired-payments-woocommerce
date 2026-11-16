<?php
/**
 * CustomerObserverTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Observers;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Observers\CustomerObserver;
use AcquiredComForWooCommerce\Tests\Framework\Traits\CustomerServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Mockery;

/**
 * Test case for CustomerObserver.
 *
 * @covers AcquiredComForWooCommerce\Observers\CustomerObserver
 */
class CustomerObserverTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use CustomerServiceMock;

	/**
	 * Test class.
	 *
	 * @var CustomerObserver
	 */
	private CustomerObserver $test_class;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_customer_service();

		$this->test_class = new CustomerObserver(
			$this->get_customer_service()
		);

		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Test constructor.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\CustomerObserver::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertSame( $this->get_customer_service(), $this->get_private_property_value( 'customer_service' ) );
	}

	/**
	 * Test init_hooks.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\CustomerObserver::init_hooks
	 * @return void
	 */
	public function test_init_hooks(): void {
		// Expect the actions to be added.
		Actions\expectAdded( 'woocommerce_customer_object_updated_props' )
			->once()
			->whenHappen(
				function ( $callback ) {
					$this->assertSame( $this->test_class, $callback[0] );
					$this->assertEquals( 'customer_updated', $callback[1] );
				}
			);

		// Test the method.
		$this->test_class->init_hooks();
	}

	/**
	 * Test customer_updated when conditions are met.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\CustomerObserver::customer_updated
	 * @return void
	 */
	public function test_customer_updated(): void {
		// Mock is_account_page.
		Functions\expect( 'is_account_page' )
			->once()
			->andReturn( true );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_changes' )->once()->andReturn( [ 'email' => 'new@email.com' ] );
		$customer->shouldReceive( 'get_meta' )->with( '_acfw_customer_id' )->once()->andReturn( 'customer_123' );

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'update_customer_in_my_account' )
			->once()
			->with( $customer );

		// Test the method.
		$this->test_class->customer_updated( $customer );
	}


	/**
	 * Test customer_updated when not on account page.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\CustomerObserver::customer_updated
	 * @return void
	 */
	public function test_customer_updated_when_not_account_page(): void {
		// Mock is_account_page.
		Functions\expect( 'is_account_page' )
			->once()
			->andReturn( false );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldNotReceive( 'get_changes' );
		$customer->shouldNotReceive( 'get_meta' );

		$this->get_customer_service()->shouldNotReceive( 'update_customer_in_my_account' );

		// Test the method.
		$this->test_class->customer_updated( $customer );
	}

	/**
	 * Test customer_updated when no changes.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\CustomerObserver::customer_updated
	 * @return void
	 */
	public function test_customer_updated_when_no_changes(): void {
		// Mock is_account_page.
		Functions\expect( 'is_account_page' )
			->once()
			->andReturn( true );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_changes' )->once()->andReturn( [] );
		$customer->shouldNotReceive( 'get_meta' );

		// Should not update.
		$this->get_customer_service()->shouldNotReceive( 'update_customer_in_my_account' );

		// Test the method.
		$this->test_class->customer_updated( $customer );
	}

	/**
	 * Test customer_updated when no meta.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\CustomerObserver::customer_updated
	 * @return void
	 */
	public function test_customer_updated_when_no_meta(): void {
		// Mock is_account_page.
		Functions\expect( 'is_account_page' )
			->once()
			->andReturn( true );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_changes' )->once()->andReturn( [ 'email' => 'new@email.com' ] );
		$customer->shouldReceive( 'get_meta' )->with( '_acfw_customer_id' )->once()->andReturn( '' );

		// Should not update.
		$this->get_customer_service()->shouldNotReceive( 'update_customer_in_my_account' );

		// Test the method.
		$this->test_class->customer_updated( $customer );
	}
}
