<?php
/**
 * AssetsServiceMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Services\AssetsService;
use Mockery;
use Mockery\MockInterface;

/**
 * AssetsServiceMock.
 */
trait AssetsServiceMock {
	/**
	 * AssetsService mock.
	 *
	 * @var MockInterface&AssetsService
	 */
	protected MockInterface $assets_service;

	/**
	 * Create and configure AssetsService mock.
	 *
	 * @return void
	 */
	protected function mock_assets_service(): void {
		$this->assets_service = Mockery::mock(
			AssetsService::class,
			[
				$this->config['version'],
				$this->config['dir_path'],
				$this->config['dir_url'],
			]
		);
	}

	/**
	 * Get AssetsService.
	 *
	 * @return MockInterface&AssetsService
	 */
	public function get_assets_service(): MockInterface {
		return $this->assets_service;
	}
}
