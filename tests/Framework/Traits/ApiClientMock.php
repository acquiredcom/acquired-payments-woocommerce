<?php
/**
 * ApiClientMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Api\ApiClient;
use AcquiredComForWooCommerce\Services\LoggerService;
use AcquiredComForWooCommerce\Services\SettingsService;
use GuzzleHttp\Client;
use Mockery;
use Mockery\MockInterface;

/**
 * ApiClientMock.
 */
trait ApiClientMock {
	/**
	 * ApiClient mock.
	 *
	 * @var MockInterface&ApiClient
	 */
	protected MockInterface $api_client;

	/**
	 * Create and configure ApiClient mock.
	 *
	 * @return void
	 */
	protected function mock_api_client(): void {
		$this->api_client = Mockery::mock(
			ApiClient::class,
			[
				Mockery::mock( Client::class ),
				Mockery::mock( LoggerService::class ),
				Mockery::mock( SettingsService::class ),
			]
		);
	}

	/**
	 * Get ApiClient.
	 *
	 * @return MockInterface&ApiClient
	 */
	public function get_api_client(): MockInterface {
		return $this->api_client;
	}
}
