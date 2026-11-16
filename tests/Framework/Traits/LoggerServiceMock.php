<?php
/**
 * LoggerServiceMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Services\LoggerService;
use AcquiredComForWooCommerce\Services\SettingsService;
use Mockery;
use Mockery\MockInterface;

/**
 * LoggerServiceMock.
 */
trait LoggerServiceMock {
	/**
	 * LoggerService mock.
	 *
	 * @var MockInterface&LoggerService
	 */
	protected MockInterface $logger_service;

	/**
	 * Create and configure LoggerService mock.
	 *
	 * @return void
	 */
	protected function mock_logger_service(): void {
		$this->logger_service = Mockery::mock(
			LoggerService::class,
			[
				Mockery::mock( SettingsService::class ),
				Mockery::mock( 'WC_Logger_Interface' ),
			]
		);
	}

	/**
	 * Get LoggerService.
	 *
	 * @return MockInterface&LoggerService
	 */
	public function get_logger_service(): MockInterface {
		return $this->logger_service;
	}
}
