<?php
/**
 * AdminObserverTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Observers;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Observers\AdminObserver;
use AcquiredComForWooCommerce\Tests\Framework\Traits\AdminServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use Brain\Monkey\Actions;

/**
 * Test case for AdminObserver.
 *
 * @covers AcquiredComForWooCommerce\Observers\AdminObserver
 */
class AdminObserverTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use AdminServiceMock;

	/**
	 * Test class.
	 *
	 * @var AdminObserver
	 */
	private AdminObserver $test_class;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_admin_service();

		$this->test_class = new AdminObserver(
			$this->get_admin_service()
		);

		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Test constructor.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\AdminObserver::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertSame( $this->get_admin_service(), $this->get_private_property_value( 'admin_service' ) );
	}

	/**
	 * Test init_hooks.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\AdminObserver::init_hooks
	 * @return void
	 */
	public function test_init_hooks(): void {
		// Expect the actions to be added.
		Actions\expectAdded( 'admin_notices' )
			->times( 2 )
			->whenHappen(
				function ( $callback ) {
					$this->assertSame( $this->get_admin_service(), $callback[0] );
					$this->assertContains( $callback[1], [ 'settings_notice', 'order_notice' ] );
				}
			);

		// Test the method.
		$this->test_class->init_hooks();
	}
}
