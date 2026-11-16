<?php
/**
 * OrderObserverTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Observers;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Observers\OrderObserver;
use AcquiredComForWooCommerce\Tests\Framework\Traits\IncomingDataHandlerMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\LoggerServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\OrderServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\Traits\SettingsServiceMock;
use AcquiredComForWooCommerce\Api\IncomingData\WebhookData;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Mockery;
use Exception;

/**
 * Test case for OrderObserver.
 *
 * @covers AcquiredComForWooCommerce\Observers\OrderObserver
 */
class OrderObserverTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use IncomingDataHandlerMock;
	use LoggerServiceMock;
	use OrderServiceMock;
	use SettingsServiceMock;

	/**
	 * Test class.
	 *
	 * @var OrderObserver
	 */
	private OrderObserver $test_class;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_incoming_data_handler();
		$this->mock_logger_service();
		$this->mock_order_service();
		$this->mock_settings_service();

		$this->test_class = new OrderObserver(
			$this->get_incoming_data_handler(),
			$this->get_logger_service(),
			$this->get_order_service(),
			$this->get_settings_service(),
		);

		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Test constructor.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\OrderObserver::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		// Test if constructor sets the expected properties to the right values.
		$this->assertSame( $this->get_incoming_data_handler(), $this->get_private_property_value( 'incoming_data_handler' ) );
		$this->assertSame( $this->get_logger_service(), $this->get_private_property_value( 'logger_service' ) );
		$this->assertSame( $this->get_order_service(), $this->get_private_property_value( 'order_service' ) );
		$this->assertSame( $this->get_settings_service(), $this->get_private_property_value( 'settings_service' ) );
	}

	/**
	 * Test init_hooks.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\OrderObserver::init_hooks
	 * @return void
	 */
	public function test_init_hooks(): void {
		// Test woocommerce_before_thankyou action.
		Actions\expectAdded( 'woocommerce_before_thankyou' )
			->once()
			->whenHappen(
				function ( $callback ) {
					$this->assertSame( $this->test_class, $callback[0] );
					$this->assertEquals( 'add_fail_notice', $callback[1] );
				}
			);

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'get_scheduled_action_hook' )
			->once()
			->andReturn( 'acfw_process_scheduled_order' );

		// Test acfw_process_scheduled_order action.
		Actions\expectAdded( 'acfw_process_scheduled_order' )
			->once()
			->whenHappen(
				function ( $callback, $priority, $accepted_args ) {
					$this->assertSame( $this->test_class, $callback[0] );
					$this->assertEquals( 'run_process_scheduled_order', $callback[1] );
					$this->assertEquals( 2, $accepted_args );
				}
			);

		// Test woocommerce_order_status_failed action.
		Actions\expectAdded( 'woocommerce_order_status_failed' )
			->once()
			->whenHappen(
				function ( $callback, $priority, $accepted_args ) {
					$this->assertSame( $this->test_class, $callback[0] );
					$this->assertEquals( 'refund_woo_wallet', $callback[1] );
					$this->assertEquals( 2, $accepted_args );
				}
			);

		// Test the method.
		$this->test_class->init_hooks();
	}

	/**
	 * Test add_fail_notice with error notice.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\OrderObserver::add_fail_notice
	 * @return void
	 */
	public function test_add_fail_notice_with_notice(): void {
		$order_id           = 123;
		$error_message      = 'Payment failed';
		$error_message_html = '<div class="woocommerce-error">Payment failed</div>';

		// Mock OrderService.

		$this->get_order_service()
			->shouldReceive( 'get_fail_notice' )
			->once()
			->with( $order_id )
			->andReturn( $error_message );

		Functions\expect( 'wp_kses_post' )
			->once()
			->with( $error_message_html )
			->andReturn( $error_message_html );

		Functions\expect( 'apply_filters' )
			->once()
			->with(
				'acfw_order_fail_notice',
				$error_message_html,
				$error_message,
				$order_id
			)
			->andReturn( $error_message_html );

		// Test the method.
		$this->expectOutputString( '<div class="woocommerce-error">Payment failed</div>' );
		$this->test_class->add_fail_notice( $order_id );
	}

	/**
	 * Test add_fail_notice without error notice.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\OrderObserver::add_fail_notice
	 * @return void
	 */
	public function test_add_fail_notice_without_notice(): void {
		$order_id = 123;

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'get_fail_notice' )
			->once()
			->with( $order_id )
			->andReturn( '' );

		// Test the method.
		$this->expectOutputString( '' );
		$this->test_class->add_fail_notice( $order_id );
	}

	/**
	 * Test run_process_scheduled_order success.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\OrderObserver::run_process_scheduled_order
	 * @return void
	 */
	public function test_run_process_scheduled_order_success(): void {
		// Set test data.
		$webhook_data = json_encode(
			(object) [
				'webhook_type' => 'status_update',
				'webhook_id'   => 'webhook_123',
				'timestamp'    => 1621234567,
				'webhook_body' => (object) [
					'transaction_id' => 'test_transaction_456',
					'status'         => 'success',
					'order_id'       => 'order_789',
				],
			]
		);
		$hash         = 'test_hash';

		// Mock the WebhookData.
		$webhook = Mockery::mock( WebhookData::class );

		// Mock get_webhook_data to return WebhookData instance.
		$this->get_incoming_data_handler()
			->shouldReceive( 'get_webhook_data' )
			->once()
			->with( $webhook_data, $hash )
			->andReturn( $webhook );

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'process_scheduled_order' )
			->once()
			->with( $webhook );

		// Test the method.
		$this->test_class->run_process_scheduled_order( $webhook_data, $hash );
	}

	/**
	 * Test run_process_scheduled_order failure.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\OrderObserver::run_process_scheduled_order
	 * @return void
	 */
	public function test_run_process_scheduled_order_failure(): void {
		// Set test data.
		$webhook_data = json_encode( (object) [ 'invalid_data' ] );
		$hash         = 'test_hash';

		// Mock get_webhook_data to throw exception.
		$this->get_incoming_data_handler()
			->shouldReceive( 'get_webhook_data' )
			->once()
			->with( $webhook_data, $hash )
			->andThrow( new Exception( 'Failed to process webhook' ) );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Scheduled order processing failed.', 'error' );

		// Test the method.
		$this->test_class->run_process_scheduled_order( $webhook_data, $hash );
	}

	/**
	 * Test refund_woo_wallet when all conditions are met.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\OrderObserver::refund_woo_wallet
	 * @return void
	 */
	public function test_refund_woo_wallet_when_conditions_met(): void {
		// Set test data.
		$order_id = 123;

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'is_enabled' )
			->once()
			->with( 'woo_wallet_refund' )
			->andReturn( true );

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'is_acfw_payment_method' )
			->once()
			->with( $order )
			->andReturn( true );

		// Mock Woo_Wallet functionality.

		$wallet          = Mockery::mock( 'Woo_Wallet' );
		$wallet_instance = Mockery::mock();

		Functions\expect( 'woo_wallet' )
			->once()
			->andReturn( $wallet );

		$wallet->wallet = $wallet_instance;
		$wallet_instance->shouldReceive( 'process_cancelled_order' )
			->once()
			->with( $order_id );

		// Test the method.
		$this->test_class->refund_woo_wallet( $order_id, $order );
	}

	/**
	 * Test refund_woo_wallet when wallet refund is disabled.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\OrderObserver::refund_woo_wallet
	 * @return void
	 */
	private function test_refund_woo_wallet_when_disabled(): void {
		// Set test data.
		$order_id = 123;

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'is_enabled' )
			->once()
			->with( 'woo_wallet_refund' )
			->andReturn( false );

		// Mock OrderService.
		$this->get_order_service()->shouldNotReceive( 'is_acfw_payment_method' );

		// Woo_Wallet should not be called.
		Functions\expect( 'woo_wallet' )->never();

		// Test the method.
		$this->test_class->refund_woo_wallet( $order_id, $order );
	}

	/**
	 * Test refund_woo_wallet when not ACFW payment method.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\OrderObserver::refund_woo_wallet
	 * @return void
	 */
	public function test_refund_woo_wallet_when_not_acfw_payment(): void {
		// Set test data.
		$order_id = 123;

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldNotReceive( 'get_id' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'is_enabled' )
			->once()
			->with( 'woo_wallet_refund' )
			->andReturn( true );

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'is_acfw_payment_method' )
			->once()
			->with( $order )
			->andReturn( false );

		// Woo_Wallet should not be called.
		Functions\expect( 'woo_wallet' )->never();

		// Test the method.
		$this->test_class->refund_woo_wallet( $order_id, $order );
	}

	/**
	 * Test refund_woo_wallet when no WooWallet.
	 *
	 * @runInSeparateProcess
	 * @covers AcquiredComForWooCommerce\Observers\OrderObserver::refund_woo_wallet
	 * @return void
	 */
	public function test_refund_woo_wallet_when_no_woo_wallet(): void {
		// Set test data.
		$order_id = 123;

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldNotReceive( 'get_id' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'is_enabled' )
			->once()
			->with( 'woo_wallet_refund' )
			->andReturn( true );

		// Mock OrderService.
		$this->get_order_service()
			->shouldReceive( 'is_acfw_payment_method' )
			->once()
			->with( $order )
			->andReturn( true );

		// Mock class_exists.
		Functions\when( 'class_exists' )->justReturn( false );

		// Woo_Wallet should not be called.
		Functions\expect( 'woo_wallet' )->never();

		// Test the method.
		$this->test_class->refund_woo_wallet( $order_id, $order );
	}
}
