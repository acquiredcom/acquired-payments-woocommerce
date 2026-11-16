<?php
/**
 * AdminServiceTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Services;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\Traits\AssetsServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\SettingsServiceMock;
use AcquiredComForWooCommerce\Services\AdminService;
use Brain\Monkey\Functions;
use Mockery;
use WC_Order;

/**
 * Test case for AdminService.
 *
 * @covers \AcquiredComForWooCommerce\Services\AdminService
 */
class AdminServiceTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use AssetsServiceMock;
	use SettingsServiceMock;

	/**
	 * AdminService instance.
	 *
	 * @var AdminService
	 */
	private AdminService $service;

	/**
	 * Order ID for testing.
	 *
	 * @var int
	 */
	private int $order_id = 666;

	/**
	 * Mock admin screen.
	 *
	 * @param string|null $screen_id
	 * @return void
	 */
	private function mock_admin_screen( string|null $screen_id ): void {
		if ( ! $screen_id ) {
			Functions\expect( 'get_current_screen' )
				->once()
				->andReturn( null );
			return;
		}

		$screen     = Mockery::mock( 'WP_Screen' );
		$screen->id = $screen_id;

		Functions\expect( 'get_current_screen' )
			->once()
			->andReturn( $screen );
	}

	/**
	 * Mock getting and sanitizing GET parameters.
	 *
	 * @return void
	 */
	private function mock_get_params_for_settings_page(): void {
		$_GET['tab']     = 'checkout';
		$_GET['section'] = 'acfw';

		Functions\expect( 'wp_unslash' )
			->twice()
			->andReturnUsing(
				function( $input ) {
					return $input;
				}
			);

		Functions\expect( 'sanitize_text_field' )
			->twice()
			->andReturnUsing(
				function( $input ) {
					return $input;
				}
			);
	}

	/**
	 * Mock admin notice.
	 *
	 * @param string $message
	 * @param string $type
	 * @param bool   $dismissible
	 * @return void
	 */
	private function mock_admin_notice( string $message, string $type, bool $dismissible = false ): void {
		Functions\expect( 'wp_admin_notice' )
			->once()
			->with(
				$message,
				[
					'type'        => $type,
					'dismissible' => $dismissible,
				]
			);
	}

	/**
	 * Mock get notice transient.
	 *
	 * @param int $order_id
	 * @param array|null $return_value
	 * @return void
	 */
	private function mock_get_notice_transient( int $order_id, null|array $return_value ): void {
		Functions\expect( 'get_transient' )
			->once()
			->with( 'acfw_order_notice_' . $order_id )
			->andReturn( $return_value );

		Functions\expect( 'delete_transient' )
			->once()
			->with( 'acfw_order_notice_' . $order_id );
	}

	/**
	 * Mock order with get payment method.
	 *
	 * @param string $payment_method
	 * @return WC_Order
	 */
	private function mock_order_with_get_payment_method( string $payment_method ) {
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )
			->once()
			->andReturn( $payment_method );

		Functions\expect( 'wc_get_order' )
			->once()
			->with( $this->order_id )
			->andReturn( $order );

		return $order;
	}

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Clear $_GET before each test.
		$_GET = [];

		$this->mock_assets_service();
		$this->mock_settings_service();

		$this->service = new AdminService(
			$this->get_assets_service(),
			$this->get_settings_service()
		);

		$this->initialize_reflection( $this->service );
	}

	/**
	 * Tear down the test case.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Clear $_GET after each test.
		$_GET = [];

		parent::tearDown();
	}

	/**
	 * Test add notice.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::add_notice
	 * @return void
	 */
	public function test_add_notice(): void {
		// Test notice.
		$this->mock_admin_notice( 'Test message', 'error' );
		$this->get_private_method_value( 'add_notice', 'Test message', 'error' );

		// Test dismissible notice.
		$this->mock_admin_notice( 'Test message', 'success', true );
		$this->get_private_method_value( 'add_notice', 'Test message', 'success', true );
	}

	/**
	 * Test get current screen.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::get_current_screen
	 * @return void
	 */
	public function test_get_current_screen(): void {
		// Test when screen is null.
		$this->mock_admin_screen( null );
		$this->assertNull( $this->get_private_method_value( 'get_current_screen' ) );

		// Test when screen is valid WP_Screen.
		$this->mock_admin_screen( 'woocommerce_page_wc-settings' );
		$this->assertInstanceOf( 'WP_Screen', $this->get_private_method_value( 'get_current_screen' ) );
	}

	/**
	 * Test is payment gateway screen.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::is_payment_gateway_screen
	 * @return void
	 */
	public function test_is_payment_gateway_screen(): void {
		// Test when not on the right screen.
		$this->mock_admin_screen( 'not_the_right_screen' );
		$this->assertFalse( $this->service->is_payment_gateway_screen() );

		// Test when on the correct screen but without correct GET parameters.
		$this->mock_admin_screen( 'woocommerce_page_wc-settings' );
		$this->assertFalse( $this->service->is_payment_gateway_screen() );

		// Test when on the correct screen with correct GET parameters.
		$this->mock_admin_screen( 'woocommerce_page_wc-settings' );
		$this->mock_get_params_for_settings_page();
		$this->assertTrue( $this->service->is_payment_gateway_screen() );
	}

	/**
	 * Test get order from order admin screen.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::get_order_from_order_admin_screen
	 * @return void
	 */
	public function test_get_order_from_order_admin_screen(): void {
		$method_name = 'get_order_from_order_admin_screen';

		// Test when not on orders screen.
		$this->mock_admin_screen( 'not_the_right_screen' );
		$this->assertNull( $this->get_private_method_value( $method_name ) );

		// Test when on orders screen with no order ID.
		$this->mock_admin_screen( 'woocommerce_page_wc-orders' );
		$this->assertNull( $this->get_private_method_value( $method_name ) );

		// Test when on orders screen with invalid order ID.
		$this->mock_admin_screen( 'woocommerce_page_wc-orders' );
		$_GET['id'] = 'invalid';
		$this->assertNull( $this->get_private_method_value( $method_name ) );

		// Test when on orders screen with valid order ID but not made with our payment method.
		$this->mock_admin_screen( 'woocommerce_page_wc-orders' );
		$_GET['id'] = $this->order_id;
		$this->mock_order_with_get_payment_method( 'other_payment_method' );
		$this->assertNull( $this->get_private_method_value( $method_name ) );

		// Test when on orders screen with valid order ID and made with our payment method.
		$this->mock_admin_screen( 'woocommerce_page_wc-orders' );
		$_GET['id'] = $this->order_id;
		$this->mock_order_with_get_payment_method( 'acfw' );
		$this->assertInstanceOf( 'WC_Order', $this->get_private_method_value( $method_name ) );
	}

	/**
	 * Test add order notice.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::add_order_notice
	 * @return void
	 */
	public function test_add_order_notice(): void {
		Functions\expect( 'set_transient' )
			->once()
			->with(
				'acfw_order_notice_' . $this->order_id,
				[
					'id'    => 'test_notice',
					'value' => 'success',
				],
				3600
			);

		$this->service->add_order_notice( $this->order_id, 'test_notice', 'success' );
	}

	/**
	 * Test get order notice.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::get_order_notice
	 * @return void
	 */
	public function test_get_order_notice(): void {
		// Test when notice does not exist.
		$this->mock_get_notice_transient( $this->order_id, null );
		$this->assertNull( $this->get_private_method_value( 'get_order_notice', $this->order_id ) );

		// Test when notice exists.

		$this->mock_get_notice_transient(
			$this->order_id,
			[
				'id'    => 'capture_transaction',
				'value' => 'success',
			]
		);

		$result = $this->get_private_method_value( 'get_order_notice', $this->order_id );
		$this->assertIsArray( $result );
		$this->assertEquals( 'capture_transaction', $result['id'] );
		$this->assertEquals( 'success', $result['value'] );
	}

	/**
	 * Test show order notice.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::show_order_notice
	 * @return void
	 */
	public function test_show_order_notice(): void {
		// Test empty notice data.
		Functions\expect( 'wp_admin_notice' )->never();
		$this->get_private_method_value( 'show_order_notice', [] );

		// Test capture transaction success.

		$this->mock_admin_notice(
			'Payment capture successful. Check order notes for more details.',
			'success',
			true
		);

		$this->get_private_method_value(
			'show_order_notice',
			[
				'id'    => 'capture_transaction',
				'value' => 'success',
			]
		);

		// Test capture transaction error.

		$this->mock_admin_notice(
			'Payment capture failed. Check order notes for more details.',
			'error',
			true
		);

		$this->get_private_method_value(
			'show_order_notice',
			[
				'id'    => 'capture_transaction',
				'value' => 'error',
			]
		);

		// Test cancel order success.

		$this->mock_admin_notice(
			'Order cancellation successful.',
			'success',
			true
		);

		$this->get_private_method_value(
			'show_order_notice',
			[
				'id'    => 'cancel_order',
				'value' => 'success',
			]
		);

		// Test invalid notice data.
		Functions\expect( 'wp_admin_notice' )->never();
		$this->get_private_method_value(
			'show_order_notice',
			[
				'id'    => 'invalid_id',
				'value' => 'invalid_value',
			]
		);
	}

	/**
	 * Test order notice display.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::order_notice
	 * @return void
	 */
	public function test_order_notice(): void {
		// Test when not on order screen.
		$this->mock_admin_screen( 'not_the_right_screen' );
		$this->service->order_notice();

		// Test when on order screen with notice.

		$this->mock_admin_screen( 'woocommerce_page_wc-orders' );
		$_GET['id'] = (string) $this->order_id;

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $this->order_id );

		Functions\expect( 'wc_get_order' )
			->once()
			->with( $this->order_id )
			->andReturn( $order );

		$this->mock_get_notice_transient(
			$this->order_id,
			[
				'id'    => 'capture_transaction',
				'value' => 'success',
			]
		);

		$this->mock_admin_notice(
			'Payment capture successful. Check order notes for more details.',
			'success',
			true
		);
		$this->service->order_notice();
	}

	/**
	 * Test settings notice display.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::settings_notice
	 * @return void
	 */
	public function test_settings_notice(): void {
		// Test when API credentials are missing.

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn( [] );

		$this->get_settings_service()
			->shouldReceive( 'get_admin_settings_url' )
			->once()
			->andReturn( 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw' );

		$this->get_settings_service()
			->shouldReceive( 'get_signing_key_issue' )
			->once()
			->andReturn( '' );

		$this->mock_admin_notice(
			'Acquired.com for WooCommerce is not fully configured. Please enter your API credentials <a href="https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw">in the settings page</a>.',
			'error'
		);

		$this->service->settings_notice();

		// Test when API credentials are invalid.

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn(
				[
					'app_id'  => 'test_id',
					'app_key' => 'test_key',
				]
			);

		$this->get_settings_service()
			->shouldReceive( 'are_api_credentials_valid' )
			->once()
			->andReturn( false );

		$this->get_settings_service()
			->shouldReceive( 'get_admin_settings_url' )
			->once()
			->andReturn( 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw' );

		$this->get_settings_service()
			->shouldReceive( 'get_signing_key_issue' )
			->once()
			->andReturn( '' );

		$this->mock_admin_notice(
			'Your API credentials and configuration are incorrect or incomplete. You are receiving a 401 response. Please check that your API credentials are correct <a href="https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw">in the settings page</a> and that you have whitelisted your IP address within the Acquired Hub. To get your IP address contact your hosting provider.',
			'error'
		);

		$this->service->settings_notice();

		// Test when everything is valid.

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn(
				[
					'app_id'  => 'test_id',
					'app_key' => 'test_key',
				]
			);

		$this->get_settings_service()
			->shouldReceive( 'are_api_credentials_valid' )
			->once()
			->andReturn( true );

		$this->get_settings_service()
			->shouldReceive( 'get_signing_key_issue' )
			->once()
			->andReturn( '' );

		$this->service->settings_notice();
	}

	/**
	 * Test settings notice display for signing key issues.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::settings_notice
	 * @return void
	 */
	public function test_settings_notice_with_signing_key_issue(): void {
		// Test when the redirect hash is empty.

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn(
				[
					'app_id'  => 'test_id',
					'app_key' => 'test_key',
				]
			);

		$this->get_settings_service()
			->shouldReceive( 'are_api_credentials_valid' )
			->once()
			->andReturn( true );

		$this->get_settings_service()
			->shouldReceive( 'get_signing_key_issue' )
			->once()
			->andReturn( 'empty' );

		$this->get_settings_service()
			->shouldReceive( 'get_admin_settings_url' )
			->once()
			->andReturn( 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw' );

		$this->mock_admin_notice(
			'Acquired.com sent a redirect without a hash, so it couldn\'t be verified. Activate a signing key in the Acquired Hub and enter it <a href="https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw">in the settings page</a>. Until then, payment status is checked with the Acquired.com API.',
			'error'
		);

		$this->service->settings_notice();

		// Test when the app key is used for signing.

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn(
				[
					'app_id'  => 'test_id',
					'app_key' => 'test_key',
				]
			);

		$this->get_settings_service()
			->shouldReceive( 'are_api_credentials_valid' )
			->once()
			->andReturn( true );

		$this->get_settings_service()
			->shouldReceive( 'get_signing_key_issue' )
			->once()
			->andReturn( 'using_app_key_for_signing' );

		$this->get_settings_service()
			->shouldReceive( 'get_admin_settings_url' )
			->once()
			->andReturn( 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw' );

		$this->mock_admin_notice(
			'Acquired.com is signing redirects with a different key than the signing key <a href="https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw">in the settings page</a>. Check that the signing key is activated in the Acquired Hub. Until then, payment status is checked with the Acquired.com API.',
			'error'
		);

		$this->service->settings_notice();

		// Test when no signing key is added.

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn(
				[
					'app_id'  => 'test_id',
					'app_key' => 'test_key',
				]
			);

		$this->get_settings_service()
			->shouldReceive( 'are_api_credentials_valid' )
			->once()
			->andReturn( true );

		$this->get_settings_service()
			->shouldReceive( 'get_signing_key_issue' )
			->once()
			->andReturn( 'no_signing_key' );

		$this->get_settings_service()
			->shouldReceive( 'get_admin_settings_url' )
			->once()
			->andReturn( 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw' );

		$this->mock_admin_notice(
			'Acquired.com is signing redirects with a signing key that isn\'t added to the plugin. Add your signing key from the Acquired Hub <a href="https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw">in the settings page</a>. Until then, payment status is checked with the Acquired.com API.',
			'error'
		);

		$this->service->settings_notice();

		// Test when the signing key is invalid.

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn(
				[
					'app_id'  => 'test_id',
					'app_key' => 'test_key',
				]
			);

		$this->get_settings_service()
			->shouldReceive( 'are_api_credentials_valid' )
			->once()
			->andReturn( true );

		$this->get_settings_service()
			->shouldReceive( 'get_signing_key_issue' )
			->once()
			->andReturn( 'invalid' );

		$this->get_settings_service()
			->shouldReceive( 'get_admin_settings_url' )
			->once()
			->andReturn( 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw' );

		$this->mock_admin_notice(
			'Redirects from Acquired.com don\'t match the signing key <a href="https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw">in the settings page</a>. Check the key for typos and that it belongs to the selected environment. Until then, payment status is checked with the Acquired.com API.',
			'error'
		);

		$this->service->settings_notice();
	}


	/**
	 * Test add order assets.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::add_order_assets
	 * @return void
	 */
	public function test_add_order_assets(): void {
		// Test when not on order screen.
		$this->mock_admin_screen( 'not_the_right_screen' );
		$this->service->add_order_assets();

		// Test when on order screen.

		$this->mock_admin_screen( 'woocommerce_page_wc-orders' );
		$_GET['id'] = $this->order_id;
		$this->mock_order_with_get_payment_method( 'acfw' );

		$asset_uri = 'https://example.com/wp-content/plugins/acquired-com-for-woocommerce/assets/dist/js/acfw-admin-order.js';

		$this->get_assets_service()
			->shouldReceive( 'get_asset_uri' )
			->once()
			->with( 'js/acfw-admin-order.js' )
			->andReturn( $asset_uri );

		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'acfw-admin-order',
				$asset_uri,
				[ 'wp-i18n' ],
				'2.0.0',
				true
			);

		Functions\expect( 'wp_set_script_translations' )->once();

		$this->service->add_order_assets();
	}

	/**
	 * Test add settings assets.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AdminService::add_settings_assets
	 * @return void
	 */
	public function test_add_settings_assets(): void {
		// Test when not on payment gateway setting screen.
		$this->mock_admin_screen( 'not_the_right_screen' );
		$this->service->add_settings_assets();

		// Test when on payment gateway settings screen.

		$this->mock_admin_screen( 'woocommerce_page_wc-settings' );
		$this->mock_get_params_for_settings_page();

		$asset_uri = 'https://example.com/wp-content/plugins/acquired-com-for-woocommerce/assets/dist/js/acfw-admin-settings.js';

		$this->get_assets_service()
			->shouldReceive( 'get_asset_uri' )
			->once()
			->with( 'js/acfw-admin-settings.js' )
			->andReturn( $asset_uri );

		Functions\expect( 'wp_enqueue_script' )
			->once()
			->with(
				'acfw-admin-settings',
				$asset_uri,
				[],
				'2.0.0',
				true
			);

		$this->service->add_settings_assets();
	}
}
