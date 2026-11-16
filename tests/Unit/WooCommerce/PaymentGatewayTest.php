<?php
/**
 * PaymentGatewayTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\WooCommerce;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\AdminServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\Traits\IncomingDataHandlerMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\LoggerServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\OrderServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\PaymentMethodServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\SettingsServiceMock;
use AcquiredComForWooCommerce\WooCommerce\PaymentGateway;
use AcquiredComForWooCommerce\Api\IncomingData\WebhookData;
use Brain\Monkey\Functions;
use Mockery;
use Exception;

/**
 * PaymentGatewayTest class.
 *
 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway
 */
class PaymentGatewayTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use IncomingDataHandlerMock;
	use AdminServiceMock;
	use LoggerServiceMock;
	use OrderServiceMock;
	use PaymentMethodServiceMock;
	use SettingsServiceMock;

	/**
	 * Test class instance.
	 *
	 * @var PaymentGateway
	 */
	protected PaymentGateway $test_class;

	/**
	 * Mock settings for constructor.
	 *
	 * @return void
	 */
	private function mock_settings_for_constructor(): void {
		$this->get_settings_service()->shouldReceive( 'get_option' )->andReturn( 'test_value' );
		$this->get_settings_service()->shouldReceive( 'get_fields' )->andReturn( [] );
		$this->get_settings_service()->shouldReceive( 'get_wc_api_endpoint' )->with( 'webhook' )->andReturn( 'acquired-com-for-woocommerce-webhook' );
		$this->get_settings_service()->shouldReceive( 'get_wc_api_endpoint' )->with( 'redirect-new-order' )->andReturn( 'acquired-com-for-woocommerce-redirect-new-order' );
		$this->get_settings_service()->shouldReceive( 'get_wc_api_endpoint' )->with( 'redirect-new-payment-method' )->andReturn( 'redirect-new-payment-method' );
	}

	/**
	 * Mock init_hooks method.
	 *
	 * @return void
	 */
	private function mock_init_hooks(): void {
		Functions\expect( 'add_action' )
			->with(
				'woocommerce_update_options_payment_gateways_acfw',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'process_admin_options' === $callback[1];
					}
				)
			)
			->once();

		Functions\expect( 'add_action' )
			->with(
				'admin_notices',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'display_errors' === $callback[1];
					}
				)
			)
			->once();

		Functions\expect( 'add_action' )
			->with(
				'admin_enqueue_scripts',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'add_order_assets' === $callback[1];
					}
				)
			)
			->once();

		Functions\expect( 'add_action' )
			->with(
				'admin_enqueue_scripts',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'add_settings_assets' === $callback[1];
					}
				)
			)
			->once();

		Functions\expect( 'add_action' )
			->with(
				'woocommerce_api_webhook-endpoint',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'process_webhook' === $callback[1];
					}
				)
			)
			->once();

		Functions\expect( 'add_action' )
			->with(
				'woocommerce_api_redirect-order-endpoint',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'redirect_new_order' === $callback[1];
					}
				)
			)
			->once();

		Functions\expect( 'add_action' )
			->with(
				'woocommerce_api_redirect-payment-endpoint',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'redirect_new_payment_method' === $callback[1];
					}
				)
			)
			->once();

		Functions\expect( 'add_action' )
			->with(
				'woocommerce_order_action_acfw_capture_payment',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'process_capture' === $callback[1];
					}
				)
			)
			->once();

		Functions\expect( 'add_action' )
			->with(
				'woocommerce_order_action_acfw_cancel_order',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'process_cancellation' === $callback[1];
					}
				)
			)
			->once();

		Functions\expect( 'add_filter' )
			->with(
				'woocommerce_order_actions',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'add_order_actions' === $callback[1];
					}
				),
				10,
				2
			)
		->once();

		Functions\expect( 'add_filter' )
			->with(
				'woocommerce_gateway_description',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'show_staging_message' === $callback[1];
					}
				),
				10,
				2
			)
			->once();

		Functions\expect( 'add_filter' )
			->with(
				'woocommerce_order_fully_refunded_status',
				Mockery::on(
					function( $callback ) {
						return is_array( $callback ) && count( $callback ) === 2 && 'set_order_fully_refunded_status' === $callback[1];
					}
				),
				10,
				2
			)
			->once();
	}

	/**
	 * Mock the webhook hash header.
	 *
	 * @param string $hash
	 * @return void
	 */
	private function mock_webhook_hash_header( string $hash ): void {
		$_SERVER['HTTP_HASH'] = $hash;

		Functions\expect( 'wp_unslash' )
			->once()
			->with( $hash )
			->andReturn( $hash );
	}

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Clear $_SERVER before each test.
		$_SERVER = [];

		$this->mock_incoming_data_handler();
		$this->mock_admin_service();
		$this->mock_logger_service();
		$this->mock_order_service();
		$this->mock_payment_method_service();
		$this->mock_settings_service();

		// Mock requirements for the constructor.
		$this->mock_settings_for_constructor();
		$this->get_settings_service()->shouldReceive( 'is_enabled' )->with( 'tokenization' )->andReturn( false );
		$this->mock_init_hooks();

		// Create the test class instance - this will trigger all the hook expectations.
		$this->test_class = new PaymentGateway(
			$this->get_incoming_data_handler(),
			$this->get_admin_service(),
			$this->get_logger_service(),
			$this->get_order_service(),
			$this->get_payment_method_service(),
			$this->get_settings_service()
		);

		$this->initialize_reflection( $this->test_class );
	}


	/**
	 * Tear down the test case.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Clear $_SERVER after each test.
		$_SERVER = [];

		parent::tearDown();
	}

	/**
	 * Test constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertSame( $this->get_incoming_data_handler(), $this->get_private_property_value( 'incoming_data_handler' ) );
		$this->assertSame( $this->get_admin_service(), $this->get_private_property_value( 'admin_service' ) );
		$this->assertSame( $this->get_logger_service(), $this->get_private_property_value( 'logger_service' ) );
		$this->assertSame( $this->get_order_service(), $this->get_private_property_value( 'order_service' ) );
		$this->assertSame( $this->get_payment_method_service(), $this->get_private_property_value( 'payment_method_service' ) );
		$this->assertSame( $this->get_settings_service(), $this->get_private_property_value( 'settings_service' ) );
		$this->assertEquals( 'acfw', $this->test_class->id );
		$this->assertEquals( 'Acquired.com', $this->test_class->method_title );
		$this->assertEquals(
			'Securely accept Cards, Apple Pay & Google Pay on your store using Acquired.com.',
			$this->test_class->method_description
		);
		$this->assertFalse( $this->test_class->has_fields );
		$this->assertEquals( [ 'products', 'refunds' ], $this->test_class->supports );
	}

	/**
	 * Test constructor adds tokenization support when enabled.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::__construct
	 * @return void
	 */
	public function test_constructor_adds_tokenization_support_when_enabled(): void {
		// Create a completely new mock for settings service.
		$this->mock_settings_service();

		// Mock requirements for the constructor.
		$this->mock_settings_for_constructor();
		$this->get_settings_service()->shouldReceive( 'is_enabled' )->with( 'tokenization' )->andReturn( true );
		$this->mock_init_hooks();

		// Create test instance with tokenization enabled.
		$test_class_instance = new PaymentGateway(
			$this->get_incoming_data_handler(),
			$this->get_admin_service(),
			$this->get_logger_service(),
			$this->get_order_service(),
			$this->get_payment_method_service(),
			$this->get_settings_service()
		);

		// Test the supports property.
		$this->assertEquals( [ 'products', 'refunds', 'tokenization' ], $test_class_instance->supports );
	}

	/**
	 * Test admin_options method outputs correct HTML.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::admin_options
	 * @return void
	 */
	public function test_admin_options(): void {
		// Test the method.
		$this->expectOutputString( "\t\t<h2>Acquired.com</h2>\n\n\t\t<table class=\"form-table\">\n\t\t\t\t\t</table>\n\t\t" );
		$this->test_class->admin_options();
	}

	/**
	 * Test needs_setup method returns true when not in production.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::needs_setup
	 * @return void
	 */
	public function test_needs_setup_returns_true_when_not_in_production(): void {
		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'is_environment_production' )
			->once()
			->andReturn( false );

		// Test the method.
		$this->assertTrue( $this->test_class->needs_setup() );
	}

	/**
	 * Test needs_setup method returns false when properly configured.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::needs_setup
	 * @return void
	 */
	public function test_needs_setup_returns_false_when_properly_configured(): void {
		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'is_environment_production' )
			->once()
			->andReturn( true );

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials_for_environment' )
			->with( 'production' )
			->once()
			->andReturn(
				[
					'app_id'  => 'production-app-id',
					'api_key' => 'production-app-key',
				]
			);

		// Test the method.
		$this->assertFalse( $this->test_class->needs_setup() );
	}

	/**
	 * Test needs_setup method returns true when production credentials missing.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::needs_setup
	 * @return void
	 */
	public function test_needs_setup_returns_true_when_production_credentials_missing(): void {
		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'is_environment_production' )
			->once()
			->andReturn( true );

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials_for_environment' )
			->with( 'production' )
			->once()
			->andReturn( [] );

		// Test the method.
		$this->assertTrue( $this->test_class->needs_setup() );
	}

	/**
	 * Test is_available method returns false when no API credentials.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::is_available
	 * @return void
	 */
	public function test_is_available_returns_false_when_no_api_credentials(): void {
		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn( [] );

		// Test the method.
		$this->assertFalse( $this->test_class->is_available() );
	}

	/**
	 * Test is_available method returns false when invalid API credentials.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::is_available
	 * @return void
	 */
	public function test_is_available_returns_false_when_invalid_api_credentials(): void {
		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn(
				[
					'app_id'  => 'production-app-id',
					'api_key' => 'production-app-key',
				]
			);

		$this->get_settings_service()
			->shouldReceive( 'are_api_credentials_valid' )
			->once()
			->andReturn( false );

		// Test the method.
		$this->assertFalse( $this->test_class->is_available() );
	}

	/**
	 * Test is_available method returns true when credentials valid.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::is_available
	 * @return void
	 */
	public function test_is_available_returns_true_when_credentials_valid(): void {
		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn(
				[
					'app_id'  => 'production-app-id',
					'api_key' => 'production-app-key',
				]
			);

		$this->get_settings_service()
			->shouldReceive( 'are_api_credentials_valid' )
			->once()
			->andReturn( true );

		// Test the method.
		$this->assertTrue( $this->test_class->is_available() );
	}

	/**
	 * Test show_staging_message returns original description when ID doesn't match.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::show_staging_message
	 * @return void
	 */
	public function test_show_staging_message_returns_original_when_id_mismatch(): void {
		$description = 'Original description';

		// Test the method.
		$this->assertEquals( $description, $this->test_class->show_staging_message( $description, 'other-payment-method' ) );
	}

	/**
	 * Test show_staging_message returns original description when not in staging.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::show_staging_message
	 * @return void
	 */
	public function test_show_staging_message_returns_original_when_not_staging(): void {
		$description = 'Original description';

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'is_environment_staging' )
			->once()
			->andReturn( false );

		// Test the method.
		$this->assertEquals( $description, $this->test_class->show_staging_message( $description, 'acfw' ) );
	}

	/**
	 * Test show_staging_message adds staging notice when in staging mode.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::show_staging_message
	 * @return void
	 */
	public function test_show_staging_message_adds_notice_when_staging(): void {
		$description = 'Original description';

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'is_environment_staging' )
			->once()
			->andReturn( true );

		// Test the method.
		$result = $this->test_class->show_staging_message( $description, 'acfw' );
		$this->assertStringContainsString( $description, $result );
		$this->assertStringContainsString( '<p>Staging mode is enabled. For test card numbers visit <a href="https://docs.acquired.com/docs/test-cards" target="_blank">https://docs.acquired.com/docs/test-cards</a>.</p>', $result );
	}

	/**
	 * Test set_order_fully_refunded_status returns cancelled when conditions met.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::set_order_fully_refunded_status
	 * @return void
	 */
	public function test_set_order_fully_refunded_status_returns_cancelled_when_conditions_met(): void {
		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'is_acfw_payment_method' )
			->with( 123 )
			->once()
			->andReturn( true );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'is_enabled' )
			->with( 'cancel_refunded' )
			->once()
			->andReturn( true );

		// Test the method.
		$this->assertEquals( 'cancelled', $this->test_class->set_order_fully_refunded_status( 'refunded', 123 ) );
	}

	/**
	 * Test set_order_fully_refunded_status returns original when not acfw payment.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::set_order_fully_refunded_status
	 * @return void
	 */
	public function test_set_order_fully_refunded_status_returns_original_when_not_acfw_payment(): void {
		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'is_acfw_payment_method' )
			->with( 123 )
			->once()
			->andReturn( false );

		// Test the method.
		$this->assertEquals( 'refunded', $this->test_class->set_order_fully_refunded_status( 'refunded', 123 ) );
	}

	/**
	 * Test set_order_fully_refunded_status returns original when setting disabled.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::set_order_fully_refunded_status
	 * @return void
	 */
	public function test_set_order_fully_refunded_status_returns_original_when_setting_disabled(): void {
		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'is_acfw_payment_method' )
			->with( 123 )
			->once()
			->andReturn( true );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'is_enabled' )
			->with( 'cancel_refunded' )
			->once()
			->andReturn( false );

		// Test the method.
		$this->assertEquals( 'refunded', $this->test_class->set_order_fully_refunded_status( 'refunded', 123 ) );
	}

	/**
	 * Test add_order_actions returns original actions when order is null.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::add_order_actions
	 * @return void
	 */
	public function test_add_order_actions_returns_original_when_order_null(): void {
		// Test data.
		$actions = [ 'send_order_details' => 'Send order details to customer' ];

		// Test the method.
		$this->assertEquals( $actions, $this->test_class->add_order_actions( $actions, null ) );
	}

	/**
	 * Test add_order_actions adds capture action when order can be captured.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::add_order_actions
	 * @return void
	 */
	public function test_add_order_actions_adds_capture_action_when_can_be_captured(): void {
		// Test data.
		$actions = [ 'send_order_details' => 'Send order details to customer' ];

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'can_be_captured' )
			->with( $order )
			->once()
			->andReturn( true );

		$this->get_order_service()
			->shouldReceive( 'can_be_cancelled' )
			->with( $order )
			->once()
			->andReturn( false );

		// Test the method.
		$this->assertEquals(
			[
				'send_order_details'   => 'Send order details to customer',
				'acfw_capture_payment' => 'Capture payment',
			],
			$this->test_class->add_order_actions( $actions, $order )
		);
	}

	/**
	 * Test add_order_actions adds cancel action when order can be cancelled.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::add_order_actions
	 * @return void
	 */
	public function test_add_order_actions_adds_cancel_action_when_can_be_cancelled(): void {
		// Test data.
		$actions = [ 'send_order_details' => 'Send order details to customer' ];

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'can_be_captured' )
			->with( $order )
			->once()
			->andReturn( false );

		$this->get_order_service()
			->shouldReceive( 'can_be_cancelled' )
			->with( $order )
			->once()
			->andReturn( true );

		// Test the method.
		$this->assertEquals(
			[
				'send_order_details' => 'Send order details to customer',
				'acfw_cancel_order'  => 'Cancel order',
			],
			$this->test_class->add_order_actions( $actions, $order )
		);
	}

	/**
	 * Test add_order_actions adds both actions when order can be captured and cancelled.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::add_order_actions
	 * @return void
	 */
	public function test_add_order_actions_adds_both_actions_when_both_possible(): void {
		// Test data.
		$actions = [ 'send_order_details' => 'Send order details to customer' ];

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'can_be_captured' )
			->with( $order )
			->once()
			->andReturn( true );

		$this->get_order_service()
			->shouldReceive( 'can_be_cancelled' )
			->with( $order )
			->once()
			->andReturn( true );

		// Test the method.
		$this->assertEquals(
			[
				'send_order_details'   => 'Send order details to customer',
				'acfw_capture_payment' => 'Capture payment',
				'acfw_cancel_order'    => 'Cancel order',
			],
			$this->test_class->add_order_actions( $actions, $order )
		);
	}

	/**
	 * Test get_saved_payment_method_option_html returns empty on add payment method page.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::get_saved_payment_method_option_html
	 * @return void
	 */
	public function test_get_saved_payment_method_option_html_returns_empty_on_add_payment_page(): void {
		// Mock is_add_payment_method_page function.
		Functions\expect( 'is_add_payment_method_page' )
			->once()
			->andReturn( true );

		// Mock WC_Payment_Token.
		$token = Mockery::mock( 'WC_Payment_Token' );

		// Test the method.
		$this->assertEquals( '', $this->test_class->get_saved_payment_method_option_html( $token ) );
	}

	/**
	 * Test get_saved_payment_method_option_html returns parent HTML when not on add payment page.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::get_saved_payment_method_option_html
	 * @return void
	 */
	public function test_get_saved_payment_method_option_html_returns_parent_html(): void {
		// Mock is_add_payment_method_page function.
		Functions\expect( 'is_add_payment_method_page' )
			->once()
			->andReturn( false );

		// Mock WC_Payment_Token.
		$token = Mockery::mock( 'WC_Payment_Token' );

		// Test the method.
		$this->assertEquals( '<div>Test payment method HTML</div>', $this->test_class->get_saved_payment_method_option_html( $token ) );
	}

	/**
	 * Test get_new_payment_method_option_html returns empty when no tokens on checkout.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::get_new_payment_method_option_html
	 * @return void
	 */
	public function test_get_new_payment_method_option_html_returns_empty_when_no_tokens_on_checkout(): void {
		// Mock is_checkout function.
		Functions\expect( 'is_checkout' )
			->once()
			->andReturn( true );

		// Set empty tokens.
		$this->set_private_property_value( 'tokens', [] );

		// Test the method.
		$this->assertEquals( '', $this->test_class->get_new_payment_method_option_html() );
	}

	/**
	 * Test get_new_payment_method_option_html returns empty when on add payment method page.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::get_new_payment_method_option_html
	 * @return void
	 */
	public function test_get_new_payment_method_option_html_returns_empty_on_add_payment_page(): void {
		// Mock is_checkout function.
		Functions\expect( 'is_checkout' )
			->once()
			->andReturn( false );

		// Set tokens property.
		$this->set_private_property_value( 'tokens', [ 'token' ] );

		// Mock is_add_payment_method_page function.
		Functions\expect( 'is_add_payment_method_page' )
			->once()
			->andReturn( true );

		// Test the method.
		$this->assertEquals( '', $this->test_class->get_new_payment_method_option_html() );
	}

	/**
	 * Test get_new_payment_method_option_html returns parent HTML when tokens exist.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::get_new_payment_method_option_html
	 * @return void
	 */
	public function test_get_new_payment_method_option_html_returns_parent_html_when_tokens_exist(): void {
		// Mock is_checkout function.
		Functions\expect( 'is_checkout' )
			->once()
			->andReturn( false );

		// Set tokens property.
		$this->set_private_property_value( 'tokens', [ 'token' ] );

		// Mock is_add_payment_method_page function.
		Functions\expect( 'is_add_payment_method_page' )
			->once()
			->andReturn( false );

		// Test the method.
		$this->assertEquals( '<div>Test payment method HTML</div>', $this->test_class->get_new_payment_method_option_html() );
	}

	/**
	 * Test payment fields outputs correct HTML.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::payment_fields
	 * @return void
	 */
	public function test_payment_fields_outputs_expected_html(): void {
		$this->expectOutputString( 'Test payment fields outputTest saved payment methods output' );
		$this->test_class->payment_fields();
	}

	/**
	 * Test process_payment returns success array when payment link obtained.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_payment
	 * @return void
	 */
	public function test_process_payment_returns_success_array_when_payment_link_obtained(): void {
		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'get_payment_link' )
			->once()
			->with( 123 )
			->andReturn( 'https://pay.acquired.com/v1/link_123' );

		// Test the method.
		$this->assertEquals(
			[
				'result'   => 'success',
				'redirect' => 'https://pay.acquired.com/v1/link_123',
			],
			$this->test_class->process_payment( 123 )
		);
	}

	/**
	 * Test process_payment returns failure array when exception thrown.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_payment
	 * @return void
	 */
	public function test_process_payment_returns_failure_array_when_exception_thrown(): void {
		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'get_payment_link' )
			->once()
			->with( 123 )
			->andThrow( new Exception( 'Payment link creation failed.' ) );

		// Mock wc_add_notice function.
		Functions\expect( 'wc_add_notice' )
			->once()
			->with( 'Payment link creation failed.', 'error' );

		// Test the method.
		$this->assertEquals(
			[
				'result' => 'failure',
			],
			$this->test_class->process_payment( 123 )
		);
	}

	/**
	 * Test process_refund returns true when refund succeeds.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_refund
	 * @return void
	 */
	public function test_process_refund_returns_true_when_refund_succeeds(): void {
		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'refund_order' )
			->once()
			->with( 123, 10.00 )
			->andReturn( true );

		$this->assertTrue( $this->test_class->process_refund( 123, 10.00, 'Test refund' ) );
	}

	/**
	 * Test process_refund throws exception when refund fails.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_refund
	 * @return void
	 */
	public function test_process_refund_throws_exception_when_refund_fails(): void {
		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'refund_order' )
			->once()
			->with( 123, 10.00 )
			->andThrow( new Exception( 'Payment refund failed.' ) );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment refund failed.' );
		$this->test_class->process_refund( 123, 10.00, 'Test refund' );
	}

	/**
	 * Test process_capture logs error when order is null.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_capture
	 * @return void
	 */
	public function test_process_capture_logs_error_when_order_is_null(): void {
		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Order not found for capture payment action.' );

		// Test the method.
		$this->test_class->process_capture( null );
	}

	/**
	 * Test process_capture success.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_capture
	 * @return void
	 */
	public function test_process_capture_success(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'capture_order' )
			->once()
			->with( $order )
			->andReturn( 'success' );

		// Mock AdminService.
		$this->get_admin_service()
			->shouldReceive( 'add_order_notice' )
			->once()
			->with( 123, 'capture_transaction', 'success' );

		// Test the method.
		$this->test_class->process_capture( $order );
	}

	/**
	 * Test process_cancellation logs error when order is null.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_cancellation
	 * @return void
	 */
	public function test_process_cancellation_logs_error_when_order_is_null(): void {
		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Order not found for cancel order action.' );

		// Test the method.
		$this->test_class->process_cancellation( null );
	}

	/**
	 * Test process_cancellation success.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_cancellation
	 * @return void
	 */
	public function test_process_cancellation_success(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'cancel_order' )
			->once()
			->with( $order )
			->andReturn( 'success' );

		// Mock AdminService.
		$this->get_admin_service()
			->shouldReceive( 'add_order_notice' )
			->once()
			->with( 123, 'cancel_order', 'success' );

		// Test the method.
		$this->test_class->process_cancellation( $order );
	}

	/**
	 * Test add_payment_method returns success array when payment link obtained.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::add_payment_method
	 * @return void
	 */
	public function test_add_payment_method_returns_success_array_when_payment_link_obtained(): void {
		// Mock get_current_user_id function.
		Functions\expect( 'get_current_user_id' )
			->once()
			->andReturn( 123 );

		// Mock payment method service.
		$this->get_payment_method_service()
			->shouldReceive( 'get_payment_link' )
			->once()
			->with( 123 )
			->andReturn( 'https://pay.acquired.com/v1/link_123' );

		// Test the method.
		$this->assertEquals(
			[
				'result'   => '',
				'redirect' => 'https://pay.acquired.com/v1/link_123',
			],
			$this->test_class->add_payment_method()
		);
	}

	/**
	 * Test add_payment_method returns failure array when exception thrown.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::add_payment_method
	 * @return void
	 */
	public function test_add_payment_method_returns_failure_array_when_exception_thrown(): void {
		// Mock get_current_user_id function.
		Functions\expect( 'get_current_user_id' )
			->once()
			->andReturn( 123 );

		// Mock payment method service.
		$this->get_payment_method_service()
			->shouldReceive( 'get_payment_link' )
			->once()
			->with( 123 )
			->andThrow( new Exception( 'Payment link creation failed.' ) );

		// Mock wc_get_endpoint_url function.
		Functions\expect( 'wc_get_endpoint_url' )
			->once()
			->with( 'payment-methods' )
			->andReturn( 'https://example.com/my-account/payment-methods' );

		// Test the method.
		$this->assertEquals(
			[
				'result'   => 'failure',
				'redirect' => 'https://example.com/my-account/payment-methods',
			],
			$this->test_class->add_payment_method()
		);
	}

	/**
	 * Test process_webhook status_update webhook.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_webhook
	 * @return void
	 */
	public function test_process_webhook_status_update(): void {
		// Test data.
		$webhook_data = '{"type":"status_update"}';
		$hash         = 'test-hash';

		// Mock the HTTP_HASH header.
		$this->mock_webhook_hash_header( $hash );

		// Mock file_get_contents.
		Functions\expect( 'file_get_contents' )
			->once()
			->with( 'php://input' )
			->andReturn( $webhook_data );

		// Mock WebhookData.
		$data = Mockery::mock( WebhookData::class );
		$data->shouldReceive( 'get_webhook_type' )->once()->andReturn( 'status_update' );

		// Mock IncomingDataHandler.
		$this->get_incoming_data_handler()
			->shouldReceive( 'get_webhook_data' )
			->once()
			->with( $webhook_data, $hash )
			->andReturn( $data );

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'schedule_process_order' )
			->once()
			->with( $data, $hash );

		// Mock WordPress functions.
		Functions\expect( 'wp_send_json_success' )
			->once()
			->with( 'Webhook processed successfully.' );

		// Test the method.
		$this->test_class->process_webhook();
	}

	/**
	 * Test process_webhook card_new webhook for payment method.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_webhook
	 * @return void
	 */
	public function test_process_webhook_card_new_payment_method(): void {
		// Test data.
		$webhook_data = '{"type":"card_new"}';
		$hash         = 'test-hash';

		// Mock the HTTP_HASH header.
		$this->mock_webhook_hash_header( $hash );

		// Mock file_get_contents.
		Functions\expect( 'file_get_contents' )
			->once()
			->with( 'php://input' )
			->andReturn( $webhook_data );

		// Mock WebhookData.
		$data = Mockery::mock( WebhookData::class );
		$data->shouldReceive( 'get_webhook_type' )->once()->andReturn( 'card_new' );
		$data->shouldReceive( 'get_order_id' )->once()->andReturn( '123' );

		// Mock IncomingDataHandler.
		$this->get_incoming_data_handler()
			->shouldReceive( 'get_webhook_data' )
			->once()
			->with( $webhook_data, $hash )
			->andReturn( $data );

		// Mock PaymentMethodService.

		$this->get_payment_method_service()
			->shouldReceive( 'is_for_payment_method' )
			->once()
			->with( '123' )
			->andReturn( true );

		$this->get_payment_method_service()
			->shouldReceive( 'schedule_save_payment_method' )
			->once()
			->with( $data, $hash );

		// Mock WordPress functions.
		Functions\expect( 'wp_send_json_success' )
			->once()
			->with( 'Webhook processed successfully.' );

		// Test the method.
		$this->test_class->process_webhook();
	}

	/**
	 * Test process_webhook card_new webhook for new order.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_webhook
	 * @return void
	 */
	public function test_process_webhook_card_new_order(): void {
		// Test data.
		$webhook_data = '{"type":"card_new"}';
		$hash         = 'test-hash';

		// Mock the HTTP_HASH header.
		$this->mock_webhook_hash_header( $hash );

		// Mock file_get_contents.
		Functions\expect( 'file_get_contents' )
			->once()
			->with( 'php://input' )
			->andReturn( $webhook_data );

		// Mock WebhookData.
		$data = Mockery::mock( WebhookData::class );
		$data->shouldReceive( 'get_webhook_type' )->once()->andReturn( 'card_new' );
		$data->shouldReceive( 'get_order_id' )->once()->andReturn( '123' );

		// Mock IncomingDataHandler.
		$this->get_incoming_data_handler()
			->shouldReceive( 'get_webhook_data' )
			->once()
			->with( $webhook_data, $hash )
			->andReturn( $data );

		// Mock PaymentMethodService.

		$this->get_payment_method_service()
			->shouldReceive( 'is_for_payment_method' )
			->once()
			->with( '123' )
			->andReturn( false );

		$this->get_payment_method_service()
			->shouldReceive( 'save_payment_method_from_order' )
			->once()
			->with( $data );

		// Mock WordPress functions.
		Functions\expect( 'wp_send_json_success' )
			->once()
			->with( 'Webhook processed successfully.' );

		// Test the method.
		$this->test_class->process_webhook();
	}

	/**
	 * Test process_webhook card_update webhook.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_webhook
	 * @return void
	 */
	public function test_process_webhook_card_update(): void {
		// Test data.
		$webhook_data = '{"type":"card_update"}';
		$hash         = 'test-hash';

		// Mock the HTTP_HASH header.
		$this->mock_webhook_hash_header( $hash );

		// Mock file_get_contents.
		Functions\expect( 'file_get_contents' )
			->once()
			->with( 'php://input' )
			->andReturn( $webhook_data );

		// Mock WebhookData.
		$data = Mockery::mock( WebhookData::class );
		$data->shouldReceive( 'get_webhook_type' )->once()->andReturn( 'card_update' );

		// Mock IncomingDataHandler.
		$this->get_incoming_data_handler()
			->shouldReceive( 'get_webhook_data' )
			->once()
			->with( $webhook_data, $hash )
			->andReturn( $data );

		// Mock PaymentMethodService.
		$this->get_payment_method_service()
			->shouldReceive( 'update_payment_method' )
			->once()
			->with( $data );

		// Mock WordPress functions.
		Functions\expect( 'wp_send_json_success' )
			->once()
			->with( 'Webhook processed successfully.' );

		// Test the method.
		$this->test_class->process_webhook();
	}

	/**
	 * Test process_webhook empty webhook data.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_webhook
	 * @return void
	 */
	public function test_process_webhook_empty_data(): void {
		// Mock file_get_contents.
		Functions\expect( 'file_get_contents' )
			->once()
			->with( 'php://input' )
			->andReturn( false );

		// Test the method.
		$this->test_class->process_webhook();
	}

	/**
	 * Test process_webhook exception.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_webhook
	 * @return void
	 */
	public function test_process_webhook_exception(): void {
		// Test data.
		$webhook_data = '{"type":"invalid"}';
		$hash         = 'test-hash';

		// Mock the HTTP_HASH header.
		$this->mock_webhook_hash_header( $hash );

		// Mock file_get_contents.
		Functions\expect( 'file_get_contents' )
			->once()
			->with( 'php://input' )
			->andReturn( $webhook_data );

		// Mock incoming data handler to throw exception.
		$this->get_incoming_data_handler()
			->shouldReceive( 'get_webhook_data' )
			->once()
			->with( $webhook_data, $hash )
			->andThrow( new Exception( 'Webhook data is invalid.' ) );

		// Mock status_header.
		Functions\expect( 'status_header' )
			->once()
			->with( 400, 'Webhook processing failed.' );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( 'Webhook processing failed. Error: "Webhook data is invalid.".' );

		// Test the method.
		$this->test_class->process_webhook();
	}

	/**
	 * Test process_webhook without the hash.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::process_webhook
	 * @return void
	 */
	public function test_process_webhook_without_hash(): void {
		// Test data.
		$webhook_data = '{"type":"invalid"}';

		// Mock the HTTP_HASH header.
		$this->mock_webhook_hash_header( '' );

		// Mock file_get_contents.
		Functions\expect( 'file_get_contents' )
			->once()
			->with( 'php://input' )
			->andReturn( $webhook_data );

		// Mock incoming data handler to throw exception.
		$this->get_incoming_data_handler()
			->shouldReceive( 'get_webhook_data' )
			->once()
			->with( $webhook_data, '' )
			->andThrow( new Exception( 'Webhook data is invalid.' ) );

		// Mock status_header.
		Functions\expect( 'status_header' )
			->once()
			->with( 400, 'Webhook processing failed.' );

		Functions\expect( 'wp_send_json_error' )
			->once()
			->with( 'Webhook processing failed. Error: "Webhook data is invalid.".' );

		// Test the method.
		$this->test_class->process_webhook();
	}

	/**
	 * Test validate_select_field with valid value.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_select_field
	 * @return void
	 */
	public function test_validate_select_field_with_valid_value(): void {
		// Test data.
		$key   = 'test_field';
		$value = 'option_1';
		$field = [
			'title'   => 'Test Field',
			'options' => [
				'option_1' => 'Option 1',
				'option_2' => 'Option 2',
			],
		];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$result = $this->test_class->validate_select_field( $key, $value );

		// Verify the result is the same as the input since it's valid.
		$this->assertEquals( $value, $result );
	}

	/**
	 * Test validate_select_field with invalid field.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_select_field
	 * @return void
	 */
	public function test_validate_select_field_with_empty_options(): void {
		// Test data.
		$key   = 'test_field';
		$value = 'option_1';
		$field = [
			'title'   => 'Test Field',
			'options' => [],
		];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid field.' );
		$this->test_class->validate_select_field( $key, $value );
	}

	/**
	 * Test validate_select_field with invalid value.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_select_field
	 * @return void
	 */
	public function test_validate_select_field_with_invalid_value(): void {
		// Test data.
		$key   = 'test_field';
		$value = 'invalid_option';
		$field = [
			'title'   => 'Test Field',
			'options' => [
				'option_1' => 'Option 1',
				'option_2' => 'Option 2',
			],
		];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid value for "Test Field" field. Accepted values are: Option 1, Option 2.' );
		$this->test_class->validate_select_field( $key, $value );
	}

	/**
	 * Test validate_url_field with valid URL.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_url_field
	 * @return void
	 */
	public function test_validate_url_field_with_valid_url(): void {
		// Test data.
		$key   = 'test_field';
		$value = 'https://example.com';
		$field = [ 'title' => 'Test Field' ];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->assertEquals( $value, $this->test_class->validate_url_field( $key, $value ) );
	}

	/**
	 * Test validate_url_field with invalid URL.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_url_field
	 * @return void
	 */
	public function test_validate_url_field_with_invalid_url(): void {
		// Test data.
		$key   = 'test_field';
		$value = 'not-a-url';
		$field = [ 'title' => 'Test Field' ];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid value for "Test Field" field. Field value has to be URL.' );
		$this->test_class->validate_url_field( $key, $value );
	}

	/**
	 * Test validate_payment_reference_field with valid value.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_payment_reference_field
	 * @return void
	 */
	public function test_validate_payment_reference_field_with_valid_value(): void {
		// Test data.
		$key   = 'payment_reference';
		$value = 'REF-123-ABC';
		$field = [ 'title' => 'Payment Reference' ];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->assertEquals( $value, $this->test_class->validate_payment_reference_field( $key, $value ) );
	}

	/**
	 * Test validate_payment_reference_field with invalid characters.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_payment_reference_field
	 * @return void
	 */
	public function test_validate_payment_reference_field_with_invalid_chars(): void {
		// Test data.
		$key   = 'payment_reference';
		$value = 'REF@123$ABC';
		$field = [ 'title' => 'Payment Reference' ];

		// Mock SettingsService.
		$this->get_settings_service()
		->shouldReceive( 'get_field' )
		->once()
		->with( $key )
		->andReturn( $field );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid value for "Payment Reference". Field can\'t be empty and must contain only letters, numbers, spaces, hyphens and be between 1-18 characters.' );
		$this->test_class->validate_payment_reference_field( $key, $value );
	}

	/**
	 * Test validate_payment_reference_field with too long value.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_payment_reference_field
	 * @return void
	 */
	public function test_validate_payment_reference_field_with_too_long_value(): void {
		// Test data.
		$key   = 'payment_reference';
		$value = 'THIS-IS-WAY-TOO-LONG-REFERENCE-123';
		$field = [ 'title' => 'Payment Reference' ];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid value for "Payment Reference". Field can\'t be empty and must contain only letters, numbers, spaces, hyphens and be between 1-18 characters.' );
		$this->test_class->validate_payment_reference_field( $key, $value );
	}

	/**
	 * Test validate_payment_reference_field with empty value.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_payment_reference_field
	 * @return void
	 */
	public function test_validate_payment_reference_field_with_empty_value(): void {
		// Test data.
		$key   = 'payment_reference';
		$value = '';
		$field = [ 'title' => 'Payment Reference' ];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid value for "Payment Reference". Field can\'t be empty and must contain only letters, numbers, spaces, hyphens and be between 1-18 characters.' );
		$this->test_class->validate_payment_reference_field( $key, $value );
	}

	/**
	 * Test validate_company_id_field with valid UUID.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_company_id_field
	 * @return void
	 */
	public function test_validate_company_id_field_with_valid_uuid(): void {
		// Test data.
		$key   = 'company_id';
		$value = '123e4567-e89b-12d3-a456-426614174000';
		$field = [ 'title' => 'Company ID' ];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->assertEquals( $value, $this->test_class->validate_company_id_field( $key, $value ) );
	}

	/**
	 * Test validate_company_id_field with empty value.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_company_id_field
	 * @return void
	 */
	public function test_validate_company_id_field_with_empty_value(): void {
		// Test data.
		$key   = 'company_id';
		$value = '';
		$field = [ 'title' => 'Company ID' ];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		$this->assertEquals( $value, $this->test_class->validate_company_id_field( $key, $value ) );
	}

	/**
	 * Test validate_company_id_field with invalid UUID.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_company_id_field
	 * @return void
	 */
	public function test_validate_company_id_field_with_invalid_uuid(): void {
		// Test data.
		$key   = 'company_id';
		$value = 'invalid-uuid';
		$field = [ 'title' => 'Company ID' ];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid format for "Company ID" field.' );
		$this->test_class->validate_company_id_field( $key, $value );
	}

	/**
	 * Test validate_company_id_staging_field with valid UUID.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_company_id_staging_field
	 * @return void
	 */
	public function test_validate_company_id_staging_field_with_valid_uuid(): void {
		// Test data.
		$key   = 'company_id';
		$value = '123e4567-e89b-12d3-a456-426614174000';
		$field = [ 'title' => 'Company ID' ];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->assertEquals( $value, $this->test_class->validate_company_id_staging_field( $key, $value ) );
	}

	/**
	 * Test validate_company_id_production_field with valid UUID.
	 *
	 * @covers \AcquiredComForWooCommerce\WooCommerce\PaymentGateway::validate_company_id_production_field
	 * @return void
	 */
	public function test_validate_company_id_production_field_with_valid_uuid(): void {
		// Test data.
		$key   = 'company_id';
		$value = '123e4567-e89b-12d3-a456-426614174000';
		$field = [ 'title' => 'Company ID' ];

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_field' )
			->once()
			->with( $key )
			->andReturn( $field );

		// Test the method.
		$this->assertEquals( $value, $this->test_class->validate_company_id_production_field( $key, $value ) );
	}
}
