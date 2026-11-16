<?php
/**
 * TokenFactoryMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Factories\TokenFactory;
use Mockery;
use Mockery\MockInterface;

/**
 * TokenFactoryMock.
 */
trait TokenFactoryMock {
	/**
	 * TokenFactory mock.
	 *
	 * @var MockInterface&TokenFactory
	 */
	protected MockInterface $token_factory;

	/**
	 * Create and configure TokenFactory mock.
	 *
	 * @return void
	 */
	protected function mock_token_factory(): void {
		$this->token_factory = Mockery::mock( TokenFactory::class );
	}

	/**
	 * Get TokenFactory.
	 *
	 * @return MockInterface&TokenFactory
	 */
	public function get_token_factory(): MockInterface {
		return $this->token_factory;
	}
}
