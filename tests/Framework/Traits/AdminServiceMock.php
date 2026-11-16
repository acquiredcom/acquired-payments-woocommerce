<?php
/**
 * AdminServiceMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Services\AdminService;
use AcquiredComForWooCommerce\Services\AssetsService;
use AcquiredComForWooCommerce\Services\SettingsService;
use Mockery;
use Mockery\MockInterface;

/**
 * AdminServiceMock.
 */
trait AdminServiceMock {
	/**
	 * AdminService mock.
	 *
	 * @var MockInterface&AdminService
	 */
	protected MockInterface $admin_service;

	/**
	 * Create and configure AdminService mock.
	 *
	 * @return void
	 */
	protected function mock_admin_service(): void {
		$this->admin_service = Mockery::mock(
			AdminService::class,
			[
				Mockery::mock( AssetsService::class ),
				Mockery::mock( SettingsService::class ),
			]
		);
	}

	/**
	 * Get AdminService.
	 *
	 * @return MockInterface&AdminService
	 */
	public function get_admin_service(): MockInterface {
		return $this->admin_service;
	}
}
