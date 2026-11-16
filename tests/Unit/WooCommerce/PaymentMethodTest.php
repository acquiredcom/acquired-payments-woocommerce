<?php
/**
 * PaymentMethodTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\WooCommerce;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\Traits\AssetsServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\SettingsServiceMock;
use AcquiredComForWooCommerce\WooCommerce\PaymentGateway;
use AcquiredComForWooCommerce\WooCommerce\PaymentMethod;
use Mockery;
use Mockery\MockInterface;
use Brain\Monkey\Functions;

/**
 * PaymentMethodTest class.
 *
 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentMethod
 */
class PaymentMethodTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use AssetsServiceMock;
	use SettingsServiceMock;

	/**
	 * Test class instance.
	 *
	 * @var object
	 */
	protected object $test_class;

	/**
	 * PaymentGateway mock.
	 *
	 * @var MockInterface&PaymentGateway
	 */
	protected MockInterface $payment_gateway;

	/**
	 * Create and configure PaymentGateway mock.
	 *
	 * @return void
	 */
	protected function mock_payment_gateway(): void {
		$this->payment_gateway = Mockery::mock( PaymentGateway::class );

		// Set up basic properties that PaymentMethod needs.
		$this->payment_gateway->id          = $this->config['plugin_id'];
		$this->payment_gateway->title       = 'Acquired.com';
		$this->payment_gateway->description = 'Securely accept Cards, Apple Pay & Google Pay on your store using Acquired.com.';
		$this->payment_gateway->supports    = [ 'products', 'refunds' ];
	}

	/**
	 * Get PaymentGateway.
	 *
	 * @return MockInterface&PaymentGateway
	 */
	public function get_payment_gateway(): MockInterface {
		return $this->payment_gateway;
	}

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_payment_gateway();
		$this->mock_assets_service();
		$this->mock_settings_service();

		$this->test_class = new PaymentMethod(
			$this->get_payment_gateway(),
			$this->get_assets_service(),
			$this->get_settings_service()
		);

		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Test constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentMethod::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertSame( $this->get_payment_gateway(), $this->get_private_property_value( 'gateway' ) );
		$this->assertSame( $this->get_assets_service(), $this->get_private_property_value( 'assets_service' ) );
		$this->assertSame( $this->get_settings_service(), $this->get_private_property_value( 'settings_service' ) );
		$this->assertEquals( 'acfw', $this->get_private_property_value( 'name' ) );
		$this->assertEquals( 'acfw-block-checkout', $this->get_private_property_value( 'script_handle' ) );
	}

	/**
	 * Test initialize method.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentMethod::initialize
	 * @return void
	 */
	public function test_initialize_sets_settings(): void {
		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_options' )
			->once()
			->andReturn( [ 'test_setting' => 'test_value' ] );

		// Test the method.
		$this->test_class->initialize();
		$this->assertEquals( [ 'test_setting' => 'test_value' ], $this->get_private_property_value( 'settings' ) );
	}

	/**
	 * Test is_active method when the gateway is available.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentMethod::is_active
	 * @return void
	 */
	public function test_is_active_when_available(): void {
		// Mock PaymentGateway.
		$this->get_payment_gateway()
			->shouldReceive( 'is_available' )
			->once()
			->andReturn( true );

		// Test the method.
		$this->assertTrue( $this->test_class->is_active() );
	}

	/**
	 * Test is_active method when the gateway is unavailable.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentMethod::is_active
	 * @return void
	 */
	public function test_is_active_when_unavailable(): void {
		// Mock PaymentGateway.
		$this->get_payment_gateway()
			->shouldReceive( 'is_available' )
			->once()
			->andReturn( false );

		// Test the method.
		$this->assertFalse( $this->test_class->is_active() );
	}

	/**
	 * Test get_payment_method_script_handles method.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentMethod::get_payment_method_script_handles
	 * @return void
	 */
	public function test_get_payment_method_script_handles(): void {
		// Mock AssetsService.
		$this->get_assets_service()
			->shouldReceive( 'get_asset_uri' )
			->once()
			->with( 'js/acfw-block-checkout.js' )
			->andReturn( 'https://example.com/wp-content/plugins/acquired-com-for-woocommerce/assets/dist/js/acfw-block-checkout.js' );

		// Mock wp_register_script.
		Functions\expect( 'wp_register_script' )
			->once()
			->with(
				'acfw-block-checkout',
				'https://example.com/wp-content/plugins/acquired-com-for-woocommerce/assets/dist/js/acfw-block-checkout.js',
				[ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ],
				'2.0.0',
				true
			);

		// Test the method.
		$this->assertEquals( [ 'acfw-block-checkout' ], $this->test_class->get_payment_method_script_handles() );
	}

	/**
	 * Test get_payment_method_data method.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentMethod::get_payment_method_data
	 * @return void
	 */
	public function test_get_payment_method_data(): void {
		// Expected gateway data from mock_payment_gateway().
		$expected = [
			'title'       => 'Acquired.com',
			'description' => 'Securely accept Cards, Apple Pay & Google Pay on your store using Acquired.com.',
			'supports'    => [ 'products', 'refunds' ],
		];

		// Test the method.
		$this->assertEquals( $expected, $this->test_class->get_payment_method_data() );
	}
}
