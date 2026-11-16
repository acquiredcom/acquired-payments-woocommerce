<?php
/**
 * PluginTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\Traits\SettingsServiceMock;
use AcquiredComForWooCommerce\Plugin;
use AcquiredComForWooCommerce\Services\ObserverService;
use AcquiredComForWooCommerce\Services\SettingsService;
use AcquiredComForWooCommerce\WooCommerce\PaymentGateway;
use AcquiredComForWooCommerce\WooCommerce\PaymentMethod;
use Psr\Container\ContainerInterface;
use Mockery;
use Mockery\MockInterface;
use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;

/**
 * PluginTest class.
 *
 * @covers \AcquiredComForWooCommerce\Plugin
 */
class PluginTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use SettingsServiceMock;

	/**
	 * Test class instance.
	 *
	 * @var object
	 */
	protected object $test_class;

	/**
	 * ContainerInterface mock.
	 *
	 * @var MockInterface&ContainerInterface
	 */
	protected MockInterface $container;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_settings_service();

		$observer_service_mock = Mockery::mock( ObserverService::class );

		$this->container = Mockery::mock( ContainerInterface::class );
		$this->container->shouldReceive( 'get' )->with( SettingsService::class )->andReturn( $this->get_settings_service() );
		$this->container->shouldReceive( 'get' )->with( ObserverService::class )->andReturn( $observer_service_mock );

		$this->test_class = new Plugin( $this->container );

		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Test test_constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\Plugin::__construct
	 * @return void
	 */
	public function test_test_constructor(): void {
		$this->assertEquals( '/path/to/acquired-com-for-woocommerce/acquired-com-for-woocommerce.php', $this->get_private_property_value( 'root_file' ) );
		$this->assertEquals( 'acquired-com-for-woocommerce/acquired-com-for-woocommerce.php', $this->get_private_property_value( 'basename' ) );
		$this->assertSame( $this->container, $this->get_private_property_value( 'container' ) );
	}

	/**
	 * Test that init_hooks.
	 *
	 * @covers \AcquiredComForWooCommerce\Plugin::init_hooks
	 * @return void
	 */
	public function test_init_hooks(): void {
		// Expect the actions and filters to be added.
		Actions\expectAdded( 'init' )->with( [ $this->test_class, 'load_textdomain' ], 0 );
		Actions\expectAdded( 'before_woocommerce_init' )->with( [ $this->test_class, 'custom_order_tables_support' ] );
		Filters\expectAdded( 'woocommerce_payment_gateways' )->with( [ $this->test_class, 'register_gateway' ] );
		Actions\expectAdded( 'woocommerce_blocks_loaded' )->with( [ $this->test_class, 'register_block_checkout' ] );
		Filters\expectAdded( 'plugin_action_links_' . $this->get_private_property_value( 'basename' ) )->with( [ $this->test_class, 'add_settings_link' ] );

		// Test the method.
		$this->get_private_method_value( 'init_hooks' );
	}

	/**
	 * Test load_textdomain.
	 *
	 * @covers \AcquiredComForWooCommerce\Plugin::load_textdomain
	 * @return void
	 */
	public function test_load_textdomain(): void {
		// Mock dirname.
		Functions\when( 'dirname' )->justReturn( '/acquired-com-for-woocommerce' );

		// Mock trailingslashit.
		Functions\expect( 'trailingslashit' )
			->once()
			->with( '/acquired-com-for-woocommerce' )
			->andReturn( '/acquired-com-for-woocommerce/' );

		// Mock load_plugin_textdomain.
		Functions\expect( 'load_plugin_textdomain' )
			->once()
			->with(
				'acquired-com-for-woocommerce',
				false,
				'/acquired-com-for-woocommerce/languages'
			);

		// Test the method.
		$this->test_class->load_textdomain();
	}

	/**
	 * Test custom_order_tables_support.
	 *
	 * @covers \AcquiredComForWooCommerce\Plugin::custom_order_tables_support
	 * @return void
	 */
	public function test_custom_order_tables_support(): void {
		// Mock FeaturesUtil.
		$features = Mockery::mock( 'overload:\Automattic\WooCommerce\Utilities\FeaturesUtil' );
		$features->shouldReceive( 'declare_compatibility' )
			->once()
			->with( 'custom_order_tables', $this->get_private_property_value( 'root_file' ), true );

		// Mock class_exists.
		Functions\when( 'class_exists' )->justReturn( true );

		// Test the method.
		$this->test_class->custom_order_tables_support();
	}

	/**
	 * Test register_gateway.
	 *
	 * @covers \AcquiredComForWooCommerce\Plugin::register_gateway
	 * @return void
	 */
	public function test_register_gateway(): void {
		// Mock PaymentGateway.
		$payment_gateway = Mockery::mock( PaymentGateway::class );

		// Mock ContainerInterface.
		$this->container->shouldReceive( 'get' )
			->with( PaymentGateway::class )
			->once()
			->andReturn( $payment_gateway );

		// Test the method.
		$result = $this->test_class->register_gateway( [] );
		$this->assertContains( $payment_gateway, $result );
	}

	/**
	 * Test register_block_checkout.
	 *
	 * @covers \AcquiredComForWooCommerce\Plugin::register_block_checkout
	 * @return void
	 */
	public function test_register_block_checkout(): void {
		// Mock PaymentMethodRegistry.
		$payment_method_registry = Mockery::mock( '\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry' );

		// Mock PaymentMethod.
		$payment_method = Mockery::mock( PaymentMethod::class );

		// Mock class_exists.
		Functions\when( 'class_exists' )->justReturn( true );

		// Mock ContainerInterface.
		$this->container->shouldReceive( 'get' )
			->with( PaymentMethod::class )
			->once()
			->andReturn( $payment_method );

		// Expect add_action.
		Actions\expectAdded( 'woocommerce_blocks_payment_method_type_registration' )
			->with(
				Mockery::on(
					function( $callback ) use ( $payment_method_registry, $payment_method ) {
						$payment_method_registry->shouldReceive( 'register' )
							->once()
							->with( $payment_method );

							$callback( $payment_method_registry );

						return is_callable( $callback );
					}
				)
			);

		// Test the method.
		$this->test_class->register_block_checkout();
	}

	/**
	 * Test add_settings_link.
	 *
	 * @covers \AcquiredComForWooCommerce\Plugin::add_settings_link
	 * @return void
	 */
	public function test_add_settings_link(): void {
		// Mock SettingsService.
		$this->get_settings_service()->shouldReceive( 'get_admin_settings_url' )
			->once()
			->andReturn( 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw' );

		// Test the method.
		$result = $this->test_class->add_settings_link( [] );
		$this->assertArrayHasKey( 'settings', $result );
		$this->assertEquals( '<a href="https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw" aria-label="View Acquired.com for WooCommerce settings">Settings</a>', $result['settings'] );
	}
}
