<?php
/**
 * CustomerFactoryMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Factories\CustomerFactory;
use Mockery;
use Mockery\MockInterface;

/**
 * CustomerFactoryMock.
 */
trait CustomerFactoryMock {
	/**
	 * CustomerFactory mock.
	 *
	 * @var MockInterface&CustomerFactory
	 */
	protected MockInterface $customer_factory;

	/**
	 * Create and configure CustomerFactory mock.
	 *
	 * @return void
	 */
	protected function mock_customer_factory(): void {
		$this->customer_factory = Mockery::mock( CustomerFactory::class );
	}

	/**
	 * Get CustomerFactory.
	 *
	 * @return MockInterface&CustomerFactory
	 */
	public function get_customer_factory(): MockInterface {
		return $this->customer_factory;
	}
}
