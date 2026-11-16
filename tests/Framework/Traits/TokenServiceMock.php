<?php
/**
 * TokenServiceMock.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use AcquiredComForWooCommerce\Services\TokenService;
use Mockery;
use Mockery\MockInterface;

/**
 * TokenServiceMock.
 */
trait TokenServiceMock {
	/**
	 * TokenService mock.
	 *
	 * @var MockInterface&TokenService
	 */
	protected MockInterface $token_service;

	/**
	 * Create and configure TokenService mock.
	 *
	 * @return void
	 */
	protected function mock_token_service(): void {
		$this->token_service = Mockery::mock(
			TokenService::class,
			[
				$this->config['plugin_id'],
			]
		);
	}

	/**
	 * Get TokenService.
	 *
	 * @return MockInterface&TokenService
	 */
	public function get_token_service(): MockInterface {
		return $this->token_service;
	}
}
