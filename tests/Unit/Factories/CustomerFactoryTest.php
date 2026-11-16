<?php
/**
 * Test CustomerFactory class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Factories;

use AcquiredComForWooCommerce\Factories\CustomerFactory;
use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use Mockery;
use Exception;

/**
 * Test CustomerFactory class.
 *
 * @covers \AcquiredComForWooCommerce\Factories\CustomerFactory
 */
class CustomerFactoryTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;

	/**
	 * Test class.
	 *
	 * @var CustomerFactory
	 */
	private CustomerFactory $test_class;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->test_class = new CustomerFactory();
		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Test get_wc_customer success.
	 *
	 * @runInSeparateProcess
	 * @covers \AcquiredComForWooCommerce\Factories\CustomerFactory::get_wc_customer
	 * @return void
	 */
	public function test_get_wc_customer_success(): void {
		// Mock WC_Customer constructor.
		$customer = Mockery::mock( 'overload:WC_Customer' );
		$customer->shouldReceive( '__construct' )->with( 123 )->once();

		// Test the method.
		$customer->allows( 'get_id' )->andReturn( 123 );
		$result = $this->get_private_method_value( 'get_wc_customer', 123 );
		$this->assertInstanceOf( 'WC_Customer', $result );
		$this->assertEquals( 123, $result->get_id() );
	}

	/**
	 * Test get_wc_customer failure.
	 *
	 * @runInSeparateProcess
	 * @covers \AcquiredComForWooCommerce\Factories\CustomerFactory::get_wc_customer
	 * @return void
	 */
	public function test_get_wc_customer_failure(): void {
		$customer = Mockery::mock( 'overload:WC_Customer' );
		$customer->shouldReceive( '__construct' )->with( 123 )->andThrow( new Exception( 'Invalid customer' ) );
		$customer->shouldReceive( 'get_id' )->never();

		// Test the method.
		$this->assertNull( $this->get_private_method_value( 'get_wc_customer', 123 ) );
	}
}
