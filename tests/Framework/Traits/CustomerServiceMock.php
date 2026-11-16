<?php
/**
 * CustomerServiceMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Api\ApiClient;
use AcquiredComForWooCommerce\Factories\CustomerFactory;
use AcquiredComForWooCommerce\Services\CustomerService;
use AcquiredComForWooCommerce\Services\LoggerService;
use Mockery;
use Mockery\MockInterface;

/**
 * CustomerServiceMock.
 */
trait CustomerServiceMock {
	/**
	 * CustomerService mock.
	 *
	 * @var MockInterface&CustomerService
	 */
	protected MockInterface $customer_service;

	/**
	 * Create and configure CustomerService mock.
	 *
	 * @return void
	 */
	protected function mock_customer_service(): void {
		$this->customer_service = Mockery::mock(
			CustomerService::class,
			[
				Mockery::mock( ApiClient::class ),
				Mockery::mock( LoggerService::class ),
				Mockery::mock( CustomerFactory::class ),
			]
		);
	}

	/**
	 * Get CustomerService.
	 *
	 * @return MockInterface&CustomerService
	 */
	public function get_customer_service(): MockInterface {
		return $this->customer_service;
	}
}
