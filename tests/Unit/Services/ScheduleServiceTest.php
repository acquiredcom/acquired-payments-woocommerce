<?php
/**
 * ScheduleServiceTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Services;

use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Services\ScheduleService;
use Brain\Monkey\Functions;
use Exception;

/**
 * ScheduleServiceTest class.
 *
 * @covers \AcquiredComForWooCommerce\Services\ScheduleService
 */
class ScheduleServiceTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;

	/**
	 * ScheduleService class.
	 *
	 * @var ScheduleService
	 */
	private ScheduleService $service;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->service = new ScheduleService( 'test-group' );
		$this->initialize_reflection( $this->service );
	}

	/**
	 * Test constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\ScheduleService::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		// Test if constructor sets the expected properties to the right values.
		$this->assertEquals( 'test-group', $this->get_private_property_value( 'group' ) );
		$this->assertEquals( 30, $this->get_private_property_value( 'delay' ) );
	}

	/**
	 * Test schedule method success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\ScheduleService::schedule
	 * @return void
	 */
	public function test_schedule_success(): void {
		// Test successful scheduling of an action.

		Functions\expect( 'time' )
			->once()
			->andReturn( 1000 );

		Functions\expect( 'as_schedule_single_action' )
			->once()
			->with( 1030, 'test-hook', [ 'test' => 'args' ], 'test-group' )
			->andReturn( true );

		$this->service->schedule( 'test-hook', [ 'test' => 'args' ] );

		// If the schedule method does not throw an exception, the test passes.
		$this->assertTrue( true );
	}

	/**
	 * Test schedule method failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\ScheduleService::schedule
	 * @return void
	 */
	public function test_schedule_failure(): void {
		// Test failed scheduling of an action.

		Functions\expect( 'time' )
			->once()
			->andReturn( 1000 );

		Functions\expect( 'as_schedule_single_action' )
			->once()
			->with( 1030, 'test-hook', [ 'test' => 'args' ], 'test-group' )
			->andReturn( false );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Failed to schedule action.' );

		$this->service->schedule( 'test-hook', [ 'test' => 'args' ] );
	}
}
