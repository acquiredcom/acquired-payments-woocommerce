<?php
/**
 * OrderServiceMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Api\ApiClient;
use AcquiredComForWooCommerce\Services\OrderService;
use AcquiredComForWooCommerce\Services\LoggerService;
use AcquiredComForWooCommerce\Services\CustomerService;
use AcquiredComForWooCommerce\Services\PaymentMethodService;
use AcquiredComForWooCommerce\Services\ScheduleService;
use AcquiredComForWooCommerce\Services\SettingsService;
use AcquiredComForWooCommerce\Factories\CustomerFactory;
use Mockery;
use Mockery\MockInterface;

/**
 * OrderServiceMock.
 */
trait OrderServiceMock {
	/**
	 * OrderService mock.
	 *
	 * @var MockInterface&OrderService
	 */
	protected MockInterface $order_service;

	/**
	 * Create and configure OrderService mock.
	 *
	 * @return void
	 */
	protected function mock_order_service(): void {
		$this->order_service = Mockery::mock(
			OrderService::class,
			[
				Mockery::mock( ApiClient::class ),
				Mockery::mock( CustomerService::class ),
				Mockery::mock( LoggerService::class ),
				Mockery::mock( PaymentMethodService::class ),
				Mockery::mock( ScheduleService::class ),
				Mockery::mock( SettingsService::class ),
				Mockery::mock( CustomerFactory::class ),
			]
		);
	}

	/**
	 * Get OrderService.
	 *
	 * @return MockInterface&OrderService
	 */
	public function get_order_service(): MockInterface {
		return $this->order_service;
	}
}
