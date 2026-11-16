<?php
/**
 * ScheduleServiceMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Services\ScheduleService;
use Mockery;
use Mockery\MockInterface;

/**
 * ScheduleServiceMock.
 */
trait ScheduleServiceMock {
	/**
	 * ScheduleService mock.
	 *
	 * @var MockInterface&ScheduleService
	 */
	protected MockInterface $schedule_service;

	/**
	 * Create and configure ScheduleService mock.
	 *
	 * @return void
	 */
	protected function mock_schedule_service(): void {
		$this->schedule_service = Mockery::mock( ScheduleService::class, [ $this->config['plugin_id'] ] );
	}

	/**
	 * Get ScheduleService.
	 *
	 * @return MockInterface&ScheduleService
	 */
	public function get_schedule_service(): MockInterface {
		return $this->schedule_service;
	}
}
