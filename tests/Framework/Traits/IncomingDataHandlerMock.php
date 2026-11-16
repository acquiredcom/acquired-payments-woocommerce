<?php
/**
 * IncomingDataHandlerMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Api\IncomingDataHandler;
use AcquiredComForWooCommerce\Services\LoggerService;
use Mockery;
use Mockery\MockInterface;

/**
 * IncomingDataHandlerMock.
 */
trait IncomingDataHandlerMock {
	/**
	 * IncomingDataHandler mock.
	 *
	 * @var MockInterface&IncomingDataHandler
	 */
	protected MockInterface $incoming_data_handler;

	/**
	 * Create and configure IncomingDataHandler mock.
	 *
	 * @return void
	 */
	protected function mock_incoming_data_handler(): void {
		$this->incoming_data_handler = Mockery::mock(
			IncomingDataHandler::class,
			[
				Mockery::mock( LoggerService::class ),
				'123456789',
			]
		);
	}

	/**
	 * Get IncomingDataHandler.
	 *
	 * @return MockInterface&IncomingDataHandler
	 */
	public function get_incoming_data_handler(): MockInterface {
		return $this->incoming_data_handler;
	}
}
