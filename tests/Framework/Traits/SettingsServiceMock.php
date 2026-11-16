<?php
/**
 * SettingsServiceMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Services\SettingsService;
use Mockery;
use Mockery\MockInterface;

/**
 * SettingsServiceMock.
 */
trait SettingsServiceMock {
	/**
	 * SettingsService mock.
	 *
	 * @var MockInterface&SettingsService
	 */
	protected MockInterface $settings_service;

	/**
	 * Create and configure SettingsService mock.
	 *
	 * @return void
	 */
	protected function mock_settings_service(): void {
		$this->settings_service = Mockery::mock( SettingsService::class, [ $this->config ] );
	}

	/**
	 * Get SettingsService.
	 *
	 * @return MockInterface&SettingsService
	 */
	public function get_settings_service(): MockInterface {
		return $this->settings_service;
	}
}
