<?php
/**
 * ObserverServiceTest.
 *
 * @package AcquiredComForWooCommerce\Tests\Unit\Services
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Services;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Services\ObserverService;
use AcquiredComForWooCommerce\Observers\ObserverInterface;
use Mockery;

/**
 * ObserverServiceTest class.
 *
 * @covers \AcquiredComForWooCommerce\Services\ObserverService
 */
class ObserverServiceTest extends TestCase {
	/**
	 * Test constructor and initialize observers.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\ObserverService::__construct
	 * @covers \AcquiredComForWooCommerce\Services\ObserverService::init_observers
	 * @return void
	 */
	public function test_constructor_and_init_observers(): void {
		// Create mock observers.
		$observer1 = Mockery::mock( ObserverInterface::class );
		$observer2 = Mockery::mock( ObserverInterface::class );
		// Expect the init_hooks method to be called once for each observer.
		$observer1->shouldReceive( 'init_hooks' )->once()->withNoArgs();
		$observer2->shouldReceive( 'init_hooks' )->once()->withNoArgs();
		// Create the ObserverService instance with the mock observers.
		new ObserverService( [ $observer1, $observer2 ] );
	}
}
