<?php
/**
 * Test TokenFactory class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Factories;

use AcquiredComForWooCommerce\Factories\TokenFactory;
use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use Mockery;

/**
 * Test TokenFactory class.
 *
 * @covers \AcquiredComForWooCommerce\Factories\TokenFactory
 */
class TokenFactoryTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;

	/**
	 * Test class.
	 *
	 * @var TokenFactory
	 */
	private TokenFactory $test_class;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->test_class = new TokenFactory();
		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Test get_wc_payment_token.
	 *
	 * @runInSeparateProcess
	 * @covers \AcquiredComForWooCommerce\Factories\TokenFactory::get_wc_payment_token
	 * @return void
	 */
	public function test_get_wc_payment_token(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'overload:WC_Payment_Token_CC' );
		$token->shouldReceive( '__construct' )->once();

		// Test the method.
		$this->assertInstanceOf( 'WC_Payment_Token_CC', $this->test_class->get_wc_payment_token() );
	}
}
