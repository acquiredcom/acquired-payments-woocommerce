<?php
/**
 * SettingsObserverTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Observers;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Observers\SettingsObserver;
use AcquiredComForWooCommerce\Tests\Framework\Traits\ApiClientMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\Traits\SettingsServiceMock;
use Brain\Monkey\Actions;

/**
 * Test case for SettingsObserver.
 *
 * @covers AcquiredComForWooCommerce\Observers\SettingsObserver
 */
class SettingsObserverTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use ApiClientMock;
	use SettingsServiceMock;

	/**
	 * Test class.
	 *
	 * @var SettingsObserver
	 */
	private SettingsObserver $test_class;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_api_client();
		$this->mock_settings_service();

		$this->test_class = new SettingsObserver(
			$this->get_api_client(),
			$this->get_settings_service()
		);

		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Test constructor.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\SettingsObserver::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertSame( $this->get_api_client(), $this->get_private_property_value( 'api_client' ) );
		$this->assertSame( $this->get_settings_service(), $this->get_private_property_value( 'settings_service' ) );
	}

	/**
	 * Test init_hooks.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\SettingsObserver::init_hooks
	 * @return void
	 */
	public function test_init_hooks(): void {
		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( '__get' )
			->with( 'config' )
			->andReturn( [ 'plugin_id' => 'acfw' ] );

		// Test woocommerce_update_options_payment_gateways_acfw action.
		Actions\expectAdded( 'woocommerce_update_options_payment_gateways_acfw' )
			->once()
			->whenHappen(
				function( $callback, $priority ) {
					$this->assertSame( [ $this->test_class, 'options_updated' ], $callback );
					$this->assertEquals( 20, $priority );
				}
			);

		// Test the method.
		$this->test_class->init_hooks();
	}

	/**
	 * Test options_updated.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\SettingsObserver::options_updated
	 * @return void
	 */
	public function test_options_updated(): void {
		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'validate_credentials' )
			->once()
			->andReturn( true );

		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'reload_options' )
			->once();

		$this->get_settings_service()
			->shouldReceive( 'set_api_credentials_validation_status' )
			->once()
			->withArgs(
				function( $value ) {
					$this->assertIsBool( $value );
					return $value;
				}
			);

		$this->get_settings_service()
			->shouldReceive( 'clear_signing_key_issue' )
			->once();

			// Test the method.
		$this->test_class->options_updated();
	}
}
