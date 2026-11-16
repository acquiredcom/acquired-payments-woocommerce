<?php
/**
 * OrderServiceTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Services;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\ApiClientMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\LoggerServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\Traits\CustomerFactoryMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\CustomerServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\PaymentMethodServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\ScheduleServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\SettingsServiceMock;
use AcquiredComForWooCommerce\Services\OrderService;
use AcquiredComForWooCommerce\Api\Response\PaymentLink;
use AcquiredComForWooCommerce\Api\Response\Transaction;
use AcquiredComForWooCommerce\Api\IncomingData\WebhookData;
use AcquiredComForWooCommerce\Api\IncomingData\RedirectData;
use AcquiredComForWooCommerce\Api\Response\TransactionCapture;
use AcquiredComForWooCommerce\Api\Response\TransactionCancel;
use AcquiredComForWooCommerce\Api\Response\TransactionList;
use AcquiredComForWooCommerce\Api\Response\TransactionRefund;
use Exception;
use Mockery;
use Mockery\MockInterface;
use Brain\Monkey\Functions;
use WC_Order;

/**
 * OrderServiceTest class.
 *
 * @covers \AcquiredComForWooCommerce\Services\OrderService
 */
class OrderServiceTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use ApiClientMock;
	use CustomerServiceMock;
	use LoggerServiceMock;
	use PaymentMethodServiceMock;
	use ScheduleServiceMock;
	use SettingsServiceMock;
	use CustomerFactoryMock;

	/**
	 * OrderService class.
	 *
	 * @var OrderService
	 */
	private OrderService $service;

	/**
	 * Mock get_payment_link_default_body creation.
	 *
	 * @return void
	 */
	private function mock_get_payment_link_default_body_creation(): void {
		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_payment_link_default_body' )
			->once()
			->andReturn(
				[
					'transaction' => [
						'currency' => 'GBP',
						'custom1'  => '2.0.0',
					],
					'payment'     => [
						'reference' => 'Test Store',
					],
					'count_retry' => 1,
				]
			);

		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'get_option' )
			->once()
			->with( 'transaction_type', 'capture' )
			->andReturn( 'capture' );

		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'redirect-new-order' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-order' );

		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'webhook' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook' );

		$this->get_settings_service()
			->shouldReceive( 'get_option' )
			->with( 'submit_type', 'pay' )
			->once()
			->andReturn( 'pay' );

		$this->get_settings_service()
			->shouldReceive( 'get_wc_hold_stock_time' )
			->once()
			->andReturn( 300 );

		$this->get_settings_service()
			->shouldReceive( 'get_payment_link_max_expiration_time' )
			->once()
			->andReturn( 2678400 );
	}

	/**
	 * Mock set additional order data.
	 *
	 * @param MockInterface&WC_Order $order
	 * @param MockInterface&Transaction $transaction
	 * @return void
	 */
	private function mock_set_additional_order_data( MockInterface&WC_Order $order, MockInterface&Transaction $transaction ): void {
		// Mock WC_Order.
		$order->shouldReceive( 'get_id' )->andReturn( 123 );
		$order->shouldReceive( 'update_meta_data' )->with( '_acfw_transaction_payment_method', 'card' );
		$order->shouldReceive( 'add_order_note' )->with( 'Transaction (ID: transaction_123) payment method: "card".' );
		$order->shouldReceive( 'delete_meta_data' )->with( '_acfw_transaction_decline_reason' );
		$order->shouldReceive( 'save' )->once();

		// Mock Transaction.
		$transaction->shouldReceive( 'get_payment_method' )->andReturn( 'card' );
		$transaction->shouldReceive( 'get_transaction_id' )->andReturn( 'transaction_123' );
		$transaction->shouldReceive( 'get_decline_reason' )->andReturn( null );
		$transaction->shouldReceive( 'get_log_data' )->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Order additional data set successfully. Order ID: 123.',
				'debug',
				[]
			);
	}

	/**
	 * Mock order for processing.
	 *
	 * @param string $transaction_type
	 * @param string $transaction_status
	 * @param int $order_id
	 * @param string $transaction_id
	 * @param int $timestamp
	 * @return MockInterface&WC_Order
	 */
	private function mock_order_for_processing( string $transaction_type, string $transaction_status, int $order_id, string $transaction_id, int $timestamp ): MockInterface {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->times( 5 )->andReturn( $order_id );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( null );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_time_updated' )->once()->andReturn( '' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( $transaction_status );
		$order->shouldReceive( 'has_status' )->once()->with( [ 'pending', 'failed', 'on-hold' ] )->andReturn( true );
		$order->shouldReceive( 'set_transaction_id' )->once()->with( $transaction_id );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_transaction_status', $transaction_status );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_updated', $timestamp );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_version', '2.0.0' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Order processed successfully from incoming webhook data.' );
		$order->shouldReceive( 'save' )->twice();

		if ( in_array( $transaction_status, [ 'success', 'settled' ], true ) ) {
			$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( $transaction_type );
		}

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		return $order;
	}

	/**
	 * Mock transaction for processing.
	 *
	 * @param string $transaction_id
	 * @param string $status
	 * @param int $timestamp
	 * @return MockInterface&Transaction
	 */
	private function mock_transaction_for_processing( string $transaction_id, string $status, int $timestamp ): MockInterface {
		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $transaction_id );
		$transaction->shouldReceive( 'get_status' )->once()->andReturn( $status );
		$transaction->shouldReceive( 'get_created_timestamp' )->twice()->andReturn( $timestamp );
		$transaction->shouldReceive( 'get_log_data' )->twice()->andReturn( [] );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );
		$transaction->shouldReceive( 'get_payment_method' )->twice()->andReturn( 'card' );
		$transaction->shouldReceive( 'get_decline_reason' )->once()->andReturn( '' );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( $transaction_id )
			->andReturn( $transaction );

		return $transaction;
	}

	/**
	 * Mock log for processing.
	 *
	 * @param int $order_id
	 * @return void
	 */
	private function mock_log_for_processing( int $order_id ): void {
		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->times( 3 )
			->withArgs(
				function( $message, $level ) use ( $order_id ) {
					return in_array(
						$message,
						[
							sprintf( 'Order found successfully from incoming webhook data. Order ID: %d.', $order_id ),
							sprintf( 'Transaction found successfully from incoming webhook data. Order ID: %d.', $order_id ),
							sprintf( 'Order processed successfully from incoming webhook data. Order ID: %d.', $order_id ),
						],
						true
					) && 'debug' === $level;
				}
			);
	}

	/**
	 * Mock a pending order for payment status checks.
	 *
	 * @param int $order_id
	 * @return MockInterface&WC_Order
	 */
	private function mock_order_for_status_check( int $order_id ): MockInterface {
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( $order_id );
		$order->shouldReceive( 'get_order_key' )->andReturn( 'wc_order_key' );
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'acfw' );
		$order->shouldReceive( 'has_status' )->with( [ 'pending', 'failed' ] )->andReturn( true );

		return $order;
	}

	/**
	 * Mock get_transactions API call for an order.
	 *
	 * @param string $transaction_order_id
	 * @param string|null $transaction_id
	 * @param bool $is_error
	 * @return void
	 */
	private function mock_get_transactions( string $transaction_order_id, ?string $transaction_id, bool $is_error = false ): void {
		// Mock TransactionList.
		$transaction_list = Mockery::mock( TransactionList::class );
		$transaction_list->shouldReceive( 'request_is_error' )->once()->andReturn( $is_error );
		$transaction_list->shouldReceive( 'get_first_transaction_id' )->andReturn( $transaction_id );
		$transaction_list->shouldReceive( 'get_log_data' )->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transactions' )
			->once()
			->with(
				[
					'order_id' => $transaction_order_id,
					'status'   => 'success',
					'limit'    => 1,
				],
				[ 'transaction_id' ]
			)
			->andReturn( $transaction_list );
	}

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_api_client();
		$this->mock_logger_service();
		$this->mock_customer_service();
		$this->mock_payment_method_service();
		$this->mock_schedule_service();
		$this->mock_settings_service();
		$this->mock_customer_factory();

		$this->service = new OrderService(
			$this->get_api_client(),
			$this->get_customer_service(),
			$this->get_logger_service(),
			$this->get_payment_method_service(),
			$this->get_schedule_service(),
			$this->get_settings_service(),
			$this->get_customer_factory()
		);

		$this->initialize_reflection( $this->service );
	}

	/**
	 * Test constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertSame( $this->get_api_client(), $this->get_private_property_value( 'api_client' ) );
		$this->assertSame( $this->get_customer_service(), $this->get_private_property_value( 'customer_service' ) );
		$this->assertSame( $this->get_logger_service(), $this->get_private_property_value( 'logger_service' ) );
		$this->assertSame( $this->get_payment_method_service(), $this->get_private_property_value( 'payment_method_service' ) );
		$this->assertSame( $this->get_schedule_service(), $this->get_private_property_value( 'schedule_service' ) );
		$this->assertSame( $this->get_settings_service(), $this->get_private_property_value( 'settings_service' ) );
		$this->assertSame( $this->get_customer_factory(), $this->get_private_property_value( 'customer_factory' ) );
	}

	/**
	 * Test is_capture method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::is_capture
	 * @return void
	 */
	public function test_is_capture(): void {
		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_option' )
			->once()
			->with( 'transaction_type', 'capture' )
			->andReturn( 'capture' );

		// Test the method.
		$this->assertTrue( $this->get_private_method_value( 'is_capture' ) );
	}

	/**
	 * Test is_capture method with authorization.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::is_capture
	 * @return void
	 */
	public function test_is_capture_with_authorization(): void {
		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_option' )
			->once()
			->with( 'transaction_type', 'capture' )
			->andReturn( 'authorization' );

		// Test the method.
		$this->assertFalse( $this->get_private_method_value( 'is_capture' ) );
	}

	/**
	 * Test set_transaction_type method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::set_transaction_type
	 * @return void
	 */
	public function test_set_transaction_type(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_transaction_type', 'capture' );
		$order->shouldReceive( 'save' )->once();

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_option' )
			->once()
			->with( 'transaction_type', 'capture' )
			->andReturn( 'capture' );

		// Test the method.
		$this->set_private_method_value( 'set_transaction_type', $order );
	}

	/**
	 * Test set_transaction_type method with authorization.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::set_transaction_type
	 * @return void
	 */
	public function test_set_transaction_type_with_authorization(): void {
		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_option' )
			->once()
			->with( 'transaction_type', 'capture' )
			->andReturn( 'authorization' );

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_transaction_type', 'authorization' );
		$order->shouldReceive( 'save' )->once();

		// Test the method.
		$this->set_private_method_value( 'set_transaction_type', $order );
	}

	/**
	 * Test get_scheduled_action_hook method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_scheduled_action_hook
	 * @return void
	 */
	public function test_get_scheduled_action_hook(): void {
		$this->assertEquals( 'acfw_scheduled_process_order', $this->service->get_scheduled_action_hook() );
	}

	/**
	 * Test order_transaction_id_processed method when transaction ID matches.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::order_transaction_id_processed
	 * @return void
	 */
	public function test_order_transaction_id_processed_with_matching_ids(): void {
		$this->assertTrue( $this->get_private_method_value( 'order_transaction_id_processed', 'transaction_123', 'transaction_123' ) );
	}

	/**
	 * Test order_transaction_id_processed method when transaction ID doesn't match.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::order_transaction_id_processed
	 * @return void
	 */
	public function test_order_transaction_id_processed_with_different_ids(): void {
		$this->assertFalse( $this->get_private_method_value( 'order_transaction_id_processed', 'transaction_123', 'transaction_456' ) );
	}

	/**
	 * Test order_transaction_id_processed method with null order transaction ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::order_transaction_id_processed
	 * @return void
	 */
	public function test_order_transaction_id_processed_with_null_order_id(): void {
		$this->assertFalse( $this->get_private_method_value( 'order_transaction_id_processed', 'transaction_123', null ) );
	}

	/**
	 * Test is_day_older method with older timestamp.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::is_day_older
	 * @return void
	 */
	public function test_is_day_older_with_older_timestamp(): void {
		$this->assertTrue( $this->service->is_day_older( strtotime( '-1 day' ) ) );
	}

	/**
	 * Test is_day_older method with same day timestamp.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::is_day_older
	 * @return void
	 */
	public function test_is_day_older_with_same_day(): void {
		$this->assertFalse( $this->service->is_day_older( strtotime( 'today' ) ) );
	}

	/**
	 * Test is_day_older method with future timestamp.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::is_day_older
	 * @return void
	 */
	public function test_is_day_older_with_future_timestamp(): void {
		$this->assertFalse( $this->service->is_day_older( strtotime( '+1 day' ) ) );
	}

	/**
	 * Test get_transaction method with successful response.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_transaction
	 * @return void
	 */
	public function test_get_transaction_with_successful_response(): void {
		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( 'transaction_123' )
			->andReturn( $transaction );

		// Test the method.
		$this->assertSame( $transaction, $this->get_private_method_value( 'get_transaction', 'transaction_123' ) );
	}

	/**
	 * Test get_transaction method with error response.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_transaction
	 * @return void
	 */
	public function test_get_transaction_with_error_response(): void {
		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( true );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( 'transaction_123' )
			->andReturn( $transaction );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Failed to get transaction.' );
		$this->get_private_method_value( 'get_transaction', 'transaction_123' );
	}

	/**
	 * Test get_transaction_time_updated method with successful response.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_transaction_time_updated
	 * @return void
	 */
	public function test_get_transaction_time_updated_with_successful_response(): void {
		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( 1234567890 );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( 'transaction_123' )
			->andReturn( $transaction );

		// Test the method.
		$this->assertEquals( 1234567890, $this->get_private_method_value( 'get_transaction_time_updated', 'transaction_123' ) );
	}

	/**
	 * Test get_transaction_time_updated method with error response.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_transaction_time_updated
	 * @return void
	 */
	public function test_get_transaction_time_updated_with_error_response(): void {
		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( 'transaction_123' )
			->andThrow( new Exception( 'Failed to get transaction.' ) );

		// Mock the time function to return a fixed timestamp.
		Functions\expect( 'time' )
			->once()
			->andReturn( 1234567890 );

		// Test the method.
		$this->assertEquals( 1234567890, $this->get_private_method_value( 'get_transaction_time_updated', 'transaction_123' ) );
	}

	/**
	 * Test can_be_processed method when order can be processed.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_processed
	 * @return void
	 */
	public function test_can_be_processed_with_processable_order(): void {
		// Mock WC_Order with 'authorised' state.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_state' )->andReturn( 'authorised' );
		$this->assertTrue( $this->get_private_method_value( 'can_be_processed', $order ) );

		// Mock WC_Order with 'failed' state.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_state' )->andReturn( 'failed' );
		$this->assertTrue( $this->get_private_method_value( 'can_be_processed', $order ) );

		// Mock WC_Order with no state.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_state' )->andReturn( '' );
		$this->assertTrue( $this->get_private_method_value( 'can_be_processed', $order ) );
	}

	/**
	 * Test can_be_processed method when order can't be processed.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_processed
	 * @return void
	 */
	public function test_can_be_processed_with_non_processable_states(): void {
		// Mock WC_Order with 'completed' state.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_state' )->andReturn( 'completed' );
		$this->assertFalse( $this->get_private_method_value( 'can_be_processed', $order ) );

		// Mock WC_Order with 'cancelled' state.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_state' )->andReturn( 'cancelled' );
		$this->assertFalse( $this->get_private_method_value( 'can_be_processed', $order ) );

		// Mock WC_Order with 'executed' state.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_state' )->andReturn( 'executed' );
		$this->assertFalse( $this->get_private_method_value( 'can_be_processed', $order ) );

		// Mock WC_Order with 'refunded_full' state.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_state' )->andReturn( 'refunded_full' );
		$this->assertFalse( $this->get_private_method_value( 'can_be_processed', $order ) );

		// Mock WC_Order with 'refunded_partial' state.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_state' )->andReturn( 'refunded_partial' );
		$this->assertFalse( $this->get_private_method_value( 'can_be_processed', $order ) );
	}

	/**
	 * Test get_payment_link_expiration_time method when hold stock is disabled.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_payment_link_expiration_time
	 * @return void
	 */
	public function test_get_payment_link_expiration_time_with_disabled_hold_stock(): void {
		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'get_wc_hold_stock_time' )
			->once()
			->andReturn( 0 );

		$this->get_settings_service()
			->shouldReceive( 'get_payment_link_expiration_time' )
			->once()
			->andReturn( 300 ); // 5 minutes.

		// Test the method.
		$this->assertEquals( 300, $this->get_private_method_value( 'get_payment_link_expiration_time' ) );
	}

	/**
	 * Test get_payment_link_expiration_time method with hold stock enabled.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_payment_link_expiration_time
	 * @return void
	 */
	public function test_get_payment_link_expiration_time_with_enabled_hold_stock(): void {
		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_wc_hold_stock_time' )
			->once()
			->andReturn( 600 ); // 10 minutes.

		$this->get_settings_service()
			->shouldReceive( 'get_payment_link_max_expiration_time' )
			->once()
			->andReturn( 2678400 ); // 31 days (maximum time allowed by the API).

		// Test the method.
		$this->assertEquals( 600, $this->get_private_method_value( 'get_payment_link_expiration_time' ) );
	}

	/**
	 * Test get_payment_link_expiration_time method with max expiration limit.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_payment_link_expiration_time
	 * @return void
	 */
	public function test_get_payment_link_expiration_time_with_max_limit(): void {
		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'get_wc_hold_stock_time' )
			->once()
			->andReturn( 2678401 ); // Exceeds the maximum time by 1 second.

		$this->get_settings_service()
			->shouldReceive( 'get_payment_link_max_expiration_time' )
			->once()
			->andReturn( 2678400 ); // 31 days (maximum time allowed by the API).

		// Test the method.
		$this->assertEquals( 2678400, $this->get_private_method_value( 'get_payment_link_expiration_time' ) );
	}

	/**
	 * Test get_payment_link_body.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_payment_link_body
	 * @return void
	 */
	public function test_get_payment_link_body(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_total' )->once()->andReturn( '100.00' );
		$order->shouldReceive( 'get_currency' )->once()->andReturn( 'GBP' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );

		// Mock link body creation.
		$this->mock_get_payment_link_default_body_creation();

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_data_for_checkout' )
			->with( $order )
			->once()
			->andReturn( [] );

		// Mock PaymentMethodService.
		$this->get_payment_method_service()
			->shouldReceive( 'get_payment_method_for_checkout' )
			->with( $order )
			->once()
			->andReturn( null );

		// Set expected response.
		$expected = [
			'transaction'  => [
				'currency' => 'GBP',
				'custom1'  => '2.0.0',
				'order_id' => '123-wc_order_key',
				'amount'   => 100.00,
				'capture'  => true,
			],
			'payment'      => [
				'reference' => 'Test Store',
			],
			'count_retry'  => 1,
			'redirect_url' => 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-order',
			'webhook_url'  => 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook',
			'submit_type'  => 'pay',
			'expires_in'   => 300,
		];

		$this->assertEquals( $expected, $this->get_private_method_value( 'get_payment_link_body', $order ) );
	}

	/**
	 * Test get_payment_link_body with customer data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_payment_link_body
	 * @return void
	 */
	public function test_get_payment_link_body_with_customer_data(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_total' )->once()->andReturn( '100.00' );
		$order->shouldReceive( 'get_currency' )->once()->andReturn( 'GBP' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );

		// Mock link body creation.
		$this->mock_get_payment_link_default_body_creation();

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_data_for_checkout' )
			->with( $order )
			->once()
			->andReturn( [ 'customer_id' => '789' ] );

		// Mock PaymentMethodService.
		$this->get_payment_method_service()
			->shouldReceive( 'get_payment_method_for_checkout' )
			->with( $order )
			->once()
			->andReturn( null );

		// Set expected response.
		$expected = [
			'transaction'  => [
				'currency' => 'GBP',
				'custom1'  => '2.0.0',
				'order_id' => '123-wc_order_key',
				'amount'   => 100.00,
				'capture'  => true,
			],
			'payment'      => [
				'reference' => 'Test Store',
			],
			'count_retry'  => 1,
			'redirect_url' => 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-order',
			'webhook_url'  => 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook',
			'submit_type'  => 'pay',
			'expires_in'   => 300,
			'customer'     => [ 'customer_id' => '789' ],
		];

		$this->assertEquals( $expected, $this->get_private_method_value( 'get_payment_link_body', $order ) );
	}

	/**
	 * Test get_payment_link_body with customer data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_payment_link_body
	 * @return void
	 */
	public function test_get_payment_link_body_with_payment_method_data(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_total' )->once()->andReturn( '100.00' );
		$order->shouldReceive( 'get_currency' )->once()->andReturn( 'GBP' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );

		// Mock link body creation.
		$this->mock_get_payment_link_default_body_creation();

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_data_for_checkout' )
			->with( $order )
			->once()
			->andReturn( [ 'customer_id' => '789' ] );

		// Mock PaymentMethodService.
		$this->get_payment_method_service()
			->shouldReceive( 'get_payment_method_for_checkout' )
			->with( $order )
			->once()
			->andReturn( 'token_123' );

		// Set expected response.
		$expected = [
			'transaction'  => [
				'currency' => 'GBP',
				'custom1'  => '2.0.0',
				'order_id' => '123-wc_order_key',
				'amount'   => 100.00,
				'capture'  => true,
			],
			'payment'      => [
				'reference' => 'Test Store',
				'card_id'   => 'token_123',
			],
			'count_retry'  => 1,
			'redirect_url' => 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-order',
			'webhook_url'  => 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook',
			'submit_type'  => 'pay',
			'expires_in'   => 300,
			'customer'     => [ 'customer_id' => '789' ],
		];

		$this->assertEquals( $expected, $this->get_private_method_value( 'get_payment_link_body', $order ) );
	}

	/**
	 * Test get_payment_link method with non-existent order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_payment_link
	 * @return void
	 */
	public function test_get_payment_link_with_non_existent_order(): void {
		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( false );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Failed to find order. Order ID: 123.',
				'error'
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Failed to find order.' );
		$this->service->get_payment_link( 123 );
	}

	/**
	 * Test get_payment_link method with already processed order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_payment_link
	 * @return void
	 */
	public function test_get_payment_link_with_processed_order(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'get_meta' )->twice()->with( '_acfw_order_state' )->andReturn( 'completed' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Payment link creation failed. Order has already been processed and can\'t be processed again.' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				"Payment link creation failed. Order has already been processed and can't be processed again. Order ID: 123.",
				'debug',
				[ 'order_state' => 'completed' ]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'This order has already been processed and can\'t be processed again.' );
		$this->service->get_payment_link( 123 );
	}

	/**
	 * Test get_payment_link method with successful response.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_payment_link
	 * @return void
	 */
	public function test_get_payment_link_with_successful_response(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->twice()->andReturn( 123 );
		$order->shouldReceive( 'get_total' )->once()->andReturn( '100.00' );
		$order->shouldReceive( 'get_currency' )->once()->andReturn( 'GBP' );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_state' )->andReturn( '' );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_transaction_type', 'capture' );
		$order->shouldReceive( 'save' )->once();

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Mock link body creation.
		$this->mock_get_payment_link_default_body_creation();

		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'get_option' )
			->once()
			->with( 'transaction_type', 'capture' )
			->andReturn( 'capture' );

		$this->get_settings_service()
			->shouldReceive( 'get_pay_url' )
			->once()
			->andReturn( 'https://pay.acquired.com/v1/' );

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_data_for_checkout' )
			->with( $order )
			->once()
			->andReturn( [] );

		// Mock PaymentMethodService.
		$this->get_payment_method_service()
			->shouldReceive( 'get_payment_method_for_checkout' )
			->with( $order )
			->once()
			->andReturn( null );

		// Mock PaymentLink.
		$response = Mockery::mock( PaymentLink::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( true );
		$response->shouldReceive( 'get_link_id' )->once()->andReturn( 'link_123' );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_payment_link' )
			->once()
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
		->shouldReceive( 'log' )
		->once()
		->with(
			'Payment link created successfully. Order ID: 123.',
			'debug',
			[]
		);

		// Test the method.
		$this->assertEquals( 'https://pay.acquired.com/v1/link_123', $this->service->get_payment_link( 123 ) );
	}

	/**
	 * Test get_payment_link method with error response.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_payment_link
	 * @return void
	 */
	public function test_get_payment_link_with_error_response(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->twice()->andReturn( 123 );
		$order->shouldReceive( 'get_total' )->once()->andReturn( '100.00' );
		$order->shouldReceive( 'get_currency' )->once()->andReturn( 'GBP' );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_state' )->andReturn( '' );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Payment link creation failed. Error message' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Mock link body creation.
		$this->mock_get_payment_link_default_body_creation();

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_data_for_checkout' )
			->with( $order )
			->once()
			->andReturn( [] );

		// Mock PaymentMethodService.
		$this->get_payment_method_service()
			->shouldReceive( 'get_payment_method_for_checkout' )
			->with( $order )
			->once()
			->andReturn( null );

		// Mock PaymentLink.
		$response = Mockery::mock( PaymentLink::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( false );
		$response->shouldReceive( 'get_error_message_formatted' )->once()->with( true )->andReturn( 'Error message' );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_payment_link' )
			->once()
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
		->shouldReceive( 'log' )
		->once()
		->with(
			'Payment link creation failed. Order ID: 123.',
			'error',
			[]
		);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment link creation failed.' );
		$this->service->get_payment_link( 123 );
	}

	/**
	 * Test set_additional_order_data method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::set_additional_order_data
	 * @return void
	 */
	public function test_set_additional_order_data(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_transaction_payment_method', 'card' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Transaction (ID: transaction_123) payment method: "card".' );
		$order->shouldReceive( 'delete_meta_data' )->once()->with( '_acfw_transaction_decline_reason' );
		$order->shouldReceive( 'save' )->once();

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'get_payment_method' )->twice()->andReturn( 'card' );
		$transaction->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$transaction->shouldReceive( 'get_decline_reason' )->once()->andReturn( null );
		$transaction->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Order additional data set successfully. Order ID: 123.',
				'debug',
				[]
			);

		// Test the method.
		$this->set_private_method_value( 'set_additional_order_data', $order, $transaction );
	}

	/**
	 * Test set_additional_order_data method with decline reason.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::set_additional_order_data
	 * @return void
	 */
	public function test_set_additional_order_data_with_decline_reason(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_transaction_payment_method', 'card' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Transaction (ID: transaction_123) payment method: "card".' );
		$order->shouldReceive( 'delete_meta_data' )->once()->with( '_acfw_transaction_decline_reason' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_transaction_decline_reason', 'Insufficient funds' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Transaction (ID: transaction_123) decline reason: "Insufficient funds".' );
		$order->shouldReceive( 'save' )->once();

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'get_payment_method' )->twice()->andReturn( 'card' );
		$transaction->shouldReceive( 'get_transaction_id' )->twice()->andReturn( 'transaction_123' );
		$transaction->shouldReceive( 'get_decline_reason' )->times( 3 )->andReturn( 'Insufficient funds' );
		$transaction->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Order additional data set successfully. Order ID: 123.',
				'debug',
				[]
			);

		// Test the method.
		$this->set_private_method_value( 'set_additional_order_data', $order, $transaction );
	}

	/**
	 * Test schedule_process_order method when not for order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::schedule_process_order
	 * @return void
	 */
	public function test_schedule_process_order_not_for_order(): void {
		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->once()->andReturn( '456-add_payment_method' );

		// Test the method.
		$this->get_private_method_value( 'schedule_process_order', $webhook, 'hash_123' );
	}

	/**
	 * Test schedule_process_order method with successful scheduling.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::schedule_process_order
	 * @return void
	 */
	public function test_schedule_process_order_with_successful_scheduling(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( '123-wc_order_key' );
		$webhook->shouldReceive( 'get_incoming_data' )->once()->andReturn( [ 'transaction_id' => 'transaction_123' ] );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock wp_json_encode.
		Functions\expect( 'wp_json_encode' )
			->once()
			->with( [ 'transaction_id' => 'transaction_123' ] )
			->andReturn( '{"transaction_id":"transaction_123"}' );

		// Mock ScheduleService.
		$this->get_schedule_service()
			->shouldReceive( 'schedule' )
			->once()
			->with(
				'acfw_scheduled_process_order',
				[
					'webhook_data' => '{"transaction_id":"transaction_123"}',
					'hash'         => 'hash_123',
				]
			);

		// Mock LoggerService.
			$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Order processing scheduled successfully from incoming webhook data. Order ID: 123.',
				'debug',
				[]
			);

		// Test the method.
		$this->service->schedule_process_order( $webhook, 'hash_123' );
	}

	/**
	 * Test schedule_process_order method with error.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::schedule_process_order
	 * @return void
	 */
	public function test_schedule_process_order_with_error(): void {
		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( 'invalid_id' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Error scheduling order processing from incoming webhook data. No valid order ID in incoming data.',
				'error',
				[]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No valid order ID in incoming data.' );
		$this->service->schedule_process_order( $webhook, 'hash_123' );
	}

	/**
	 * Test process_order method when not for order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_order
	 * @return void
	 */
	public function test_process_order_not_for_order(): void {
		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->once()->andReturn( '456-add_payment_method' );

		// Test the method.
		$this->service->process_order( $webhook );
	}

	/**
	 * Test process_order method with already processed transaction.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_order
	 * @return void
	 */
	public function test_process_order_with_already_processed_transaction(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( $transaction_order_id );
		$webhook->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$webhook->shouldReceive( 'get_type' )->twice()->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->twice()->andReturn( $order_id );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->twice()
			->withArgs(
				function( $message, $level ) use ( $order_id ) {
					return in_array(
						$message,
						[
							sprintf( 'Order found successfully from incoming webhook data. Order ID: %d.', $order_id ),
							sprintf( 'Order transaction already processed. Skipping the processing. Order ID: %d.', $order_id ),
						],
						true
					) && 'debug' === $level;
				}
			);

		// Test the method.
		$this->service->process_order( $webhook );
	}

	/**
	 * Test process_order method with already updated order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_order
	 * @return void
	 */
	public function test_process_order_with_already_updated_order(): void {
		// Set test data.
		$order_id               = 123;
		$transaction_order_id   = '123-wc_order_key';
		$transaction_id         = 'transaction_123';
		$timestamp              = 1234567890;
		$earlier_timestamp      = $timestamp - 1000;
		$earlier_transaction_id = 'transaction_456';

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( $transaction_order_id );
		$webhook->shouldReceive( 'get_transaction_id' )->once()->andReturn( $earlier_transaction_id );
		$webhook->shouldReceive( 'get_type' )->twice()->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->times( 3 )->andReturn( $order_id );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_time_updated' )->andReturn( $timestamp ); // Simulate that the order was updated after the transaction creation.

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( $earlier_timestamp );
		$transaction->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( $earlier_transaction_id )
			->andReturn( $transaction );

		// Mock LoggerService.

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->times( 3 )
			->withArgs(
				function( $message, $level ) {
					return in_array(
						$message,
						[
							'Order found successfully from incoming webhook data. Order ID: 123.',
							'Transaction found successfully from incoming webhook data. Order ID: 123.',
							'Incoming webhook time created is not newer than the order time updated. Skipping the processing. Order ID: 123.',
						],
						true
					) && 'debug' === $level;
				}
			);

		// Test the method.
		$this->service->process_order( $webhook );
	}

	/**
	 * Test process_order method with invalid order status.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_order
	 * @return void
	 */
	public function test_process_order_with_invalid_order_status(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';
		$timestamp            = 1234567890;

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( $transaction_order_id );
		$webhook->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$webhook->shouldReceive( 'get_type' )->times( 3 )->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->twice()->andReturn( [] );

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->times( 3 )->andReturn( $order_id );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( null );
		$order->shouldReceive( 'get_meta' )->once()->with( '_acfw_order_time_updated' )->andReturn( '' );
		$order->shouldReceive( 'has_status' )->once()->with( [ 'pending', 'failed', 'on-hold' ] )->andReturn( false );
		$order->shouldReceive( 'get_status' )->once()->andReturn( 'paid' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( $timestamp );
		$transaction->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( $transaction_id )
			->andReturn( $transaction );

		// Mock LoggerService.

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->times( 2 )
			->withArgs(
				function( $message, $level ) {
					return in_array(
						$message,
						[
							'Order found successfully from incoming webhook data. Order ID: 123.',
							'Transaction found successfully from incoming webhook data. Order ID: 123.',
						],
						true
					) && 'debug' === $level;
				}
			);

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Error processing order from incoming webhook data. Received incoming webhook data for an order that can\'t be processed again. Order ID: 123, order status: paid.',
				'error',
				[]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Received incoming webhook data for an order that can\'t be processed again. Order ID: 123, order status: paid.' );
		$this->service->process_order( $webhook );
	}

	/**
	 * Test process_order method with success status and capture type.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_order
	 * @return void
	 */
	public function test_process_order_with_success_status_and_capture_type(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';
		$timestamp            = 1234567890;

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( $transaction_order_id );
		$webhook->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$webhook->shouldReceive( 'get_type' )->twice()->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock WC_Order.
		$order = $this->mock_order_for_processing( 'capture', 'success', $order_id, $transaction_id, $timestamp );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'completed' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_completed', $timestamp );
		$order->shouldReceive( 'payment_complete' )->once();
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment successful. Transaction ID: %s.', $transaction_id ) );

		// Mock Transaction.
		$transaction = $this->mock_transaction_for_processing( $transaction_id, 'success', $timestamp );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( $timestamp );

		// Mock set_additional_order_data.
		$this->mock_set_additional_order_data( $order, $transaction );

		// Mock LoggerService.

		$this->mock_log_for_processing( $order_id );

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment complete for order. Order ID: %d.', $order_id ),
				'debug',
				[]
			);

		// Test the method.
		$this->service->process_order( $webhook );
	}

	/**
	 * Test process_order method with settled status and capture type.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_order
	 * @return void
	 */
	public function test_process_order_with_settled_status_and_capture_type(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';
		$timestamp            = 1234567890;

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( $transaction_order_id );
		$webhook->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$webhook->shouldReceive( 'get_type' )->twice()->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock WC_Order.
		$order = $this->mock_order_for_processing( 'capture', 'settled', $order_id, $transaction_id, $timestamp );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'completed' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_completed', $timestamp );
		$order->shouldReceive( 'payment_complete' )->once();
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment successful. Transaction ID: %s.', $transaction_id ) );

		// Mock Transaction.
		$transaction = $this->mock_transaction_for_processing( $transaction_id, 'settled', $timestamp );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( $timestamp );

		// Mock set_additional_order_data.
		$this->mock_set_additional_order_data( $order, $transaction );

		// Mock LoggerService.

		$this->mock_log_for_processing( $order_id );

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment complete for order. Order ID: %d.', $order_id ),
				'debug',
				[]
			);

		// Test the method.
		$this->service->process_order( $webhook );
	}

	/**
	 * Test process_order method with success status and authorisation type.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_order
	 * @return void
	 */
	public function test_process_order_with_success_status_and_authorisation_type(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';
		$timestamp            = 1234567890;

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( $transaction_order_id );
		$webhook->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$webhook->shouldReceive( 'get_type' )->twice()->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock WC_Order.
		$order = $this->mock_order_for_processing( 'authorisation', 'success', $order_id, $transaction_id, $timestamp );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'authorised' );
		$order->shouldReceive( 'update_status' )->once()->with( 'on-hold' );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment authorised. Transaction ID: %s.', $transaction_id ) );

		// Mock Transaction.
		$transaction = $this->mock_transaction_for_processing( $transaction_id, 'success', $timestamp );

		// Mock set_additional_order_data.
		$this->mock_set_additional_order_data( $order, $transaction );

		// Mock LoggerService.

		$this->mock_log_for_processing( $order_id );

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment authorised for order. Order ID: %d.', $order_id ),
				'debug',
				[]
			);

		// Test the method.
		$this->service->process_order( $webhook );
	}

	/**
	 * Test process_order method with executed status and capture type.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_order
	 * @return void
	 */
	public function test_process_order_with_executed_status_and_capture_type(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';
		$timestamp            = 1234567890;

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( $transaction_order_id );
		$webhook->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$webhook->shouldReceive( 'get_type' )->twice()->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock WC_Order.
		$order = $this->mock_order_for_processing( 'capture', 'executed', $order_id, $transaction_id, $timestamp );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'executed' );
		$order->shouldReceive( 'update_status' )->once()->with( 'on-hold' );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Bank payment executed. Transaction ID: %s.', $transaction_id ) );

		// Mock Transaction.
		$transaction = $this->mock_transaction_for_processing( $transaction_id, 'executed', $timestamp );

		// Mock set_additional_order_data.
		$this->mock_set_additional_order_data( $order, $transaction );

		// Mock LoggerService.

		$this->mock_log_for_processing( $order_id );

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Bank payment executed for order. Order ID: %d.', $order_id ),
				'debug',
				[]
			);

		// Test the method.
		$this->service->process_order( $webhook );
	}

	/**
	 * Test process_order method with failed status and capture type.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_order
	 * @return void
	 */
	public function test_process_order_with_failed_status_and_capture_type(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';
		$timestamp            = 1234567890;

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( $transaction_order_id );
		$webhook->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$webhook->shouldReceive( 'get_type' )->twice()->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock WC_Order.
		$order = $this->mock_order_for_processing( 'capture', 'failed', $order_id, $transaction_id, $timestamp );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'failed' );
		$order->shouldReceive( 'update_status' )->once()->with( 'failed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'failed' );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment failed with status "failed". Transaction ID: %s.', $transaction_id ) );

		// Mock Transaction.
		$transaction = $this->mock_transaction_for_processing( $transaction_id, 'failed', $timestamp );

		// Mock set_additional_order_data.
		$this->mock_set_additional_order_data( $order, $transaction );

		// Mock LoggerService.

		$this->mock_log_for_processing( $order_id );

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment failed for order. Order ID: %d.', $order_id ),
				'debug',
				[]
			);

		// Test the method.
		$this->service->process_order( $webhook );
	}

	/**
	 * Test process_order method with invalid order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_order
	 * @return void
	 */
	public function test_process_order_with_invalid_order(): void {
		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( 'invalid_order_id' );
		$webhook->shouldReceive( 'get_type' )->once()->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Error processing order from incoming webhook data. No valid order ID in incoming data.',
				'error',
				[]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No valid order ID in incoming data.' );
		$this->service->process_order( $webhook );
	}

	/**
	 * Test process_scheduled_order method success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_scheduled_order
	 * @return void
	 */
	public function test_process_scheduled_order_success(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';
		$timestamp            = 1234567890;

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( $transaction_order_id );
		$webhook->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$webhook->shouldReceive( 'get_type' )->twice()->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock WC_Order.
		$order = $this->mock_order_for_processing( 'capture', 'success', $order_id, $transaction_id, $timestamp );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'completed' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_completed', $timestamp );
		$order->shouldReceive( 'payment_complete' )->once();
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment successful. Transaction ID: %s.', $transaction_id ) );

		// Mock Transaction.
		$transaction = $this->mock_transaction_for_processing( $transaction_id, 'success', $timestamp );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( $timestamp );

		// Mock set_additional_order_data.
		$this->mock_set_additional_order_data( $order, $transaction );

		// Mock LoggerService.

		$this->mock_log_for_processing( $order_id );

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment complete for order. Order ID: %d.', $order_id ),
				'debug',
				[]
			);

		// Test the method.
		$this->service->process_scheduled_order( $webhook );
	}

	/**
	 * Test process_scheduled_order error.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_scheduled_order
	 * @return void
	 */
	public function test_process_scheduled_order_error(): void {
		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->twice()->andReturn( 'invalid_id' );
		$webhook->shouldReceive( 'get_log_data' )->twice()->andReturn( [] );
		$webhook->shouldReceive( 'get_type' )->once()->andReturn( 'webhook' );

		// Mock LoggerService.

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Error processing order from incoming webhook data. No valid order ID in incoming data.',
				'error',
				[]
			);

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Error processing order from scheduled webhook data. Error: "No valid order ID in incoming data.".',
				'error',
				[]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No valid order ID in incoming data.' );
		$this->service->process_scheduled_order( $webhook );
	}

	/**
	 * Test confirm_order method success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::confirm_order
	 * @return void
	 */
	public function test_confirm_order_success(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';
		$timestamp            = 1234567890;

		// Mock RedirectData.
		$redirect = Mockery::mock( RedirectData::class );
		$redirect->shouldReceive( 'get_order_id' )->times( 3 )->andReturn( $transaction_order_id );
		$redirect->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$redirect->shouldReceive( 'get_type' )->twice()->andReturn( 'webhook' );
		$redirect->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock WC_Order.
		$order = $this->mock_order_for_processing( 'capture', 'success', $order_id, $transaction_id, $timestamp );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'completed' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_completed', $timestamp );
		$order->shouldReceive( 'payment_complete' )->once();
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment successful. Transaction ID: %s.', $transaction_id ) );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );

		// Mock wc_get_order once more in OrderService::confirm_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock Transaction.
		$transaction = $this->mock_transaction_for_processing( $transaction_id, 'success', $timestamp );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( $timestamp );

		// Mock set_additional_order_data.
		$this->mock_set_additional_order_data( $order, $transaction );

		// Mock LoggerService.

		$this->mock_log_for_processing( $order_id );

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment complete for order. Order ID: %d.', $order_id ),
				'debug',
				[]
			);

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'clear_signing_key_issue' )
			->once();

		// Test the method.
		$this->assertSame( $order, $this->service->confirm_order( $redirect ) );
	}

	/**
	 * Test confirm_order method error.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::confirm_order
	 * @return void
	 */
	public function test_confirm_order_error(): void {
		// Mock WebhookData.
		$redirect = Mockery::mock( RedirectData::class );
		$redirect->shouldReceive( 'get_order_id' )->once()->andReturn( 'invalid_id' );
		$redirect->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Error processing order from incoming redirect data. No valid order ID in incoming data.',
				'error',
				[]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No valid order ID in incoming data.' );
		$this->service->confirm_order( $redirect );
	}

	/**
	 * Test can_be_captured method with valid order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_captured
	 * @return void
	 */
	public function test_can_be_captured_with_valid_order(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_total' )->once()->andReturn( '100.00' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'authorised' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'authorisation' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		// Test the method.
		$this->assertTrue( $this->service->can_be_captured( $order ) );
	}

	/**
	 * Test can_be_captured method with other payment method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_captured
	 * @return void
	 */
	public function test_can_be_captured_with_other_payment_method(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'other_payment_method' );

		// Test the method.
		$this->assertFalse( $this->service->can_be_captured( $order ) );
	}

	/**
	 * Test can_be_captured method with missing transaction ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_captured
	 * @return void
	 */
	public function test_can_be_captured_with_missing_transaction_id(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( '' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		// Test the method.
		$this->assertFalse( $this->service->can_be_captured( $order ) );
	}

	/**
	 * Test can_be_captured method with wrong transaction type.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_captured
	 * @return void
	 */
	public function test_can_be_captured_with_wrong_transaction_type(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'authorised' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		// Test the method.
		$this->assertFalse( $this->service->can_be_captured( $order ) );
	}

	/**
	 * Test can_be_captured method with wrong order state.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_captured
	 * @return void
	 */
	public function test_can_be_captured_with_wrong_order_state(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'authorisation' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		// Test the method.
		$this->assertFalse( $this->service->can_be_captured( $order ) );
	}

	/**
	 * Test can_be_captured method with zero total.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_captured
	 * @return void
	 */
	public function test_can_be_captured_with_zero_total(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_total' )->once()->andReturn( '0.00' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'authorised' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'authorisation' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		// Test the method.
		$this->assertFalse( $this->service->can_be_captured( $order ) );
	}

	/**
	 * Test capture_order method with invalid order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::capture_order
	 * @return void
	 */
	public function test_capture_order_with_invalid_order(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'other_payment_method' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Payment capture failed. Capture initiated for an order that can\'t be captured.' );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Payment capture failed. Capture initiated for an order that can\'t be captured. Order ID: 123.',
				'error'
			);

		// Test the method.
		$this->assertEquals( 'error', $this->service->capture_order( $order ) );
	}

	/**
	 * Test capture_order method with success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::capture_order
	 * @return void
	 */
	public function test_capture_order_success(): void {
		// Set test data.
		$order_id       = 123;
		$transaction_id = 'transaction_123';
		$timestamp      = 1234567890;

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );
		$order->shouldReceive( 'get_transaction_id' )->times( 3 )->andReturn( $transaction_id );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'authorisation' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'authorised' );
		$order->shouldReceive( 'get_total' )->times( 2 )->andReturn( '100.00' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'payment_complete' )->once()->with( $transaction_id );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'completed' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_completed', $timestamp );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_updated', $timestamp );
		$order->shouldReceive( 'save' )->once();
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment captured successfully. Transaction ID: %s.', $transaction_id ) );

		// Mock TransactionCapture.
		$transaction_capture = Mockery::mock( TransactionCapture::class );
		$transaction_capture->shouldReceive( 'is_captured' )->once()->andReturn( true );
		$transaction_capture->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $transaction_id );
		$transaction_capture->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( $timestamp );

		// Mock ApiClient.

		$this->get_api_client()
			->shouldReceive( 'capture_transaction' )
			->once()
			->with( $transaction_id, [ 'amount' => 100.0 ] )
			->andReturn( $transaction_capture );

		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( $transaction_id )
			->andReturn( $transaction );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment captured successfully. Order ID: %s.', $order_id ),
				'debug',
				Mockery::any()
			);

		// Test the method.
		$this->assertEquals( 'success', $this->service->capture_order( $order ) );
	}

	/**
	 * Test capture_order method with decline.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::capture_order
	 * @return void
	 */
	public function test_capture_order_decline(): void {
		// Set test data.
		$order_id       = 123;
		$transaction_id = 'transaction_123';

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );
		$order->shouldReceive( 'get_transaction_id' )->times( 3 )->andReturn( $transaction_id );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'authorisation' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'authorised' );
		$order->shouldReceive( 'get_total' )->times( 2 )->andReturn( '100.00' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'update_status' )->once()->with( 'failed' );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment capture declined with status "declined". Transaction ID: %s. Error message: "Insufficient funds".', $transaction_id ) );

		// Mock TransactionCapture.
		$transaction_capture = Mockery::mock( TransactionCapture::class );
		$transaction_capture->shouldReceive( 'is_captured' )->once()->andReturn( false );
		$transaction_capture->shouldReceive( 'get_decline_reason' )->twice()->andReturn( 'declined' );
		$transaction_capture->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction_capture->shouldReceive( 'get_error_message_formatted' )->once()->with( true )->andReturn( 'Error message: "Insufficient funds".' );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'capture_transaction' )
			->once()
			->with( $transaction_id, [ 'amount' => 100.0 ] )
			->andReturn( $transaction_capture );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment capture declined. Order ID: %s.', $order_id ),
				'debug',
				Mockery::any()
			);

		// Test the method.
		$this->assertEquals( 'error', $this->service->capture_order( $order ) );
	}

	/**
	 * Test capture_order method with error.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::capture_order
	 * @return void
	 */
	public function test_capture_order_error(): void {
		// Set test data.
		$order_id       = 123;
		$transaction_id = 'transaction_123';

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );
		$order->shouldReceive( 'get_transaction_id' )->times( 3 )->andReturn( $transaction_id );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'authorisation' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'authorised' );
		$order->shouldReceive( 'get_total' )->times( 2 )->andReturn( '100.00' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment capture failed. Transaction ID: %s. Error message: "Unknown error".', $transaction_id ) );

		// Mock TransactionCapture.
		$transaction_capture = Mockery::mock( TransactionCapture::class );
		$transaction_capture->shouldReceive( 'is_captured' )->once()->andReturn( false );
		$transaction_capture->shouldReceive( 'get_decline_reason' )->once()->andReturn( '' );
		$transaction_capture->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction_capture->shouldReceive( 'get_error_message_formatted' )->once()->with( true )->andReturn( 'Error message: "Unknown error".' );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'capture_transaction' )
			->once()
			->with( $transaction_id, [ 'amount' => 100.0 ] )
			->andReturn( $transaction_capture );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment capture failed. Order ID: %s.', $order_id ),
				'error',
				Mockery::any()
			);

		// Test the method.
		$this->assertEquals( 'error', $this->service->capture_order( $order ) );
	}

	/**
	 * Test can_be_cancelled method with valid order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_cancelled
	 * @return void
	 */
	public function test_can_be_cancelled_with_valid_order(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'authorised' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		// Test the method.
		$this->assertTrue( $this->service->can_be_cancelled( $order ) );
	}

	/**
	 * Test can_be_cancelled method with other payment method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_cancelled
	 * @return void
	 */
	public function test_can_be_cancelled_with_other_payment_method(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'other_payment_method' );

		// Test the method.
		$this->assertFalse( $this->service->can_be_cancelled( $order ) );
	}

	/**
	 * Test can_be_cancelled method with missing transaction ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_cancelled
	 * @return void
	 */
	public function test_can_be_cancelled_with_missing_transaction_id(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( '' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		// Test the method.
		$this->assertFalse( $this->service->can_be_cancelled( $order ) );
	}

	/**
	 * Test can_be_cancelled method with wrong transaction status.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_cancelled
	 * @return void
	 */
	public function test_can_be_cancelled_with_wrong_transaction_status(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'failed' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		// Test the method.
		$this->assertFalse( $this->service->can_be_cancelled( $order ) );
	}

	/**
	 * Test can_be_cancelled method with wrong order state.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_cancelled
	 * @return void
	 */
	public function test_can_be_cancelled_with_wrong_order_state(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'cancelled' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );

		// Test the method.
		$this->assertFalse( $this->service->can_be_cancelled( $order ) );
	}

	/**
	 * Test cancel_order method with invalid order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::cancel_order
	 * @return void
	 */
	public function test_cancel_order_with_invalid_order(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'other_payment_method' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Order cancellation failed. Cancellation initiated for an order that can\'t be cancelled.' );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Order cancellation failed. Cancellation initiated for an order that can\'t be cancelled. Order ID: 123.',
				'error'
			);

		// Test the method.
		$this->assertEquals( 'error', $this->service->cancel_order( $order ) );
	}

	/**
	 * Test cancel_order method with invalid date.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::cancel_order
	 * @return void
	 */
	public function test_cancel_order_with_invalid_date(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->twice()->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Order cancellation failed. Captured orders can be cancelled the next day.' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_time_completed' )->once()->andReturn( strtotime( 'today' ) );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'authorisation' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Order cancellation failed. Captured orders can be cancelled the next day. Order ID: 123.',
				'debug'
			);

		// Test the method.
		$this->assertEquals( 'invalid', $this->service->cancel_order( $order ) );
	}

	/**
	 * Test cancel_order method with success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::cancel_order
	 * @return void
	 */
	public function test_cancel_order_success(): void {
		// Set test data.
		$order_id       = 123;
		$transaction_id = 'transaction_123';
		$timestamp      = 1234567890;

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'authorised' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $transaction_id );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'update_status' )->once();
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'cancelled' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_updated', $timestamp );
		$order->shouldReceive( 'save' )->once();
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Order cancelled successfully. Transaction ID: %s.', $transaction_id ) );

		// Mock TransactionCancel.
		$transaction_cancel = Mockery::mock( TransactionCancel::class );
		$transaction_cancel->shouldReceive( 'is_cancelled' )->once()->andReturn( true );
		$transaction_cancel->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction_cancel->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $transaction_id );

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( $timestamp );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_payment_reference' )
			->once()
			->andReturn( 'Test Store' );

		// Mock ApiClient.

		$this->get_api_client()
			->shouldReceive( 'cancel_transaction' )
			->once()
			->with( $transaction_id, [ 'reference' => 'Test Store' ] )
			->andReturn( $transaction_cancel );

		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( $transaction_id )
			->andReturn( $transaction );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Order cancelled successfully. Order ID: %s.', $order_id ),
				'debug',
				Mockery::any()
			);

		// Test the method.
		$this->assertEquals( 'success', $this->service->cancel_order( $order ) );
	}

	/**
	 * Test cancel_order method with decline.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::cancel_order
	 * @return void
	 */
	public function test_cancel_order_decline(): void {
		// Set test data.
		$order_id       = 123;
		$transaction_id = 'transaction_123';

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'authorised' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $transaction_id );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Order cancellation declined with status "declined". Transaction ID: %s. Error message: "Invalid order".', $transaction_id ) );

		// Mock TransactionCancel.
		$transaction_cancel = Mockery::mock( TransactionCancel::class );
		$transaction_cancel->shouldReceive( 'is_cancelled' )->once()->andReturn( false );
		$transaction_cancel->shouldReceive( 'get_decline_reason' )->twice()->andReturn( 'declined' );
		$transaction_cancel->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction_cancel->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );
		$transaction_cancel->shouldReceive( 'get_error_message_formatted' )->once()->with( true )->andReturn( 'Error message: "Invalid order".' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_payment_reference' )
			->once()
			->andReturn( 'Test Store' );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'cancel_transaction' )
			->once()
			->with( $transaction_id, [ 'reference' => 'Test Store' ] )
			->andReturn( $transaction_cancel );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Order cancellation declined. Order ID: %s.', $order_id ),
				'debug',
				Mockery::any()
			);

		// Test the method.
		$this->assertEquals( 'error', $this->service->cancel_order( $order ) );
	}

	/**
	 * Test cancel_order method with error.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::cancel_order
	 * @return void
	 */
	public function test_cancel_order_error(): void {
		// Set test data.
		$order_id       = 123;
		$transaction_id = 'transaction_123';

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'authorised' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_transaction_id' )->times( 3 )->andReturn( $transaction_id );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Order cancellation failed. Transaction ID: %s. Error message: "Unknown error".', $transaction_id ) );

		// Mock TransactionCancel.
		$transaction_cancel = Mockery::mock( TransactionCancel::class );
		$transaction_cancel->shouldReceive( 'is_cancelled' )->once()->andReturn( false );
		$transaction_cancel->shouldReceive( 'get_decline_reason' )->once()->andReturn( '' );
		$transaction_cancel->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction_cancel->shouldReceive( 'get_error_message_formatted' )->once()->with( true )->andReturn( 'Error message: "Unknown error".' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_payment_reference' )
			->once()
			->andReturn( 'Test Store' );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'cancel_transaction' )
			->once()
			->with( $transaction_id, [ 'reference' => 'Test Store' ] )
			->andReturn( $transaction_cancel );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Order cancellation failed. Order ID: %s.', $order_id ),
				'error',
				Mockery::any()
			);

		// Test the method.
		$this->assertEquals( 'error', $this->service->cancel_order( $order ) );
	}

	/**
	 * Test can_be_refunded method with success transaction status.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_refunded
	 * @return void
	 */
	public function test_can_be_refunded_with_success_transaction_status(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->twice()->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'get_total' )->once()->andReturn( 100 );

		// Test the method.
		$this->assertTrue( $this->get_private_method_value( 'can_be_refunded', $order, 100 ) );
	}

	/**
	 * Test can_be_refunded method with settled transaction status.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_refunded
	 * @return void
	 */
	public function test_can_be_refunded_with_settled_transaction_status(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'settled' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->twice()->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'get_total' )->once()->andReturn( 100 );

		// Test the method.
		$this->assertTrue( $this->get_private_method_value( 'can_be_refunded', $order, 100 ) );
	}

	/**
	 * Test can_be_refunded method with wrong transaction status.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_refunded
	 * @return void
	 */
	public function test_can_be_refunded_with_wrong_transaction_status(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'failed' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Payment refund failed. Order ID: 123. Transaction is not in "success" or "settled" status.',
				'debug'
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Transaction is not in "success" or "settled" status.' );
		$this->get_private_method_value( 'can_be_refunded', $order, 100 );
	}

	/**
	 * Test can_be_refunded method with refunded order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_refunded
	 * @return void
	 */
	public function test_can_be_refunded_with_refunded_order(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->once()->andReturn( 'refunded_full' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Payment refund failed. Order ID: 123. Transaction has already been refunded.',
				'debug'
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Transaction has already been fully refunded.' );
		$this->get_private_method_value( 'can_be_refunded', $order, 100 );
	}

	/**
	 * Test can_be_refunded method with cancelled order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_refunded
	 * @return void
	 */
	public function test_can_be_refunded_with_cancelled_order(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->twice()->andReturn( 'cancelled' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Payment refund failed. Order ID: 123. Order has been cancelled.',
				'debug'
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Order has already been cancelled.' );
		$this->get_private_method_value( 'can_be_refunded', $order, 100 );
	}

	/**
	 * Test can_be_refunded method with authorized and completed order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_refunded
	 * @return void
	 */
	public function test_can_be_refunded_with_authorized_and_completed_order(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'authorisation' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->times( 3 )->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_time_completed' )->once()->andReturn( strtotime( 'today' ) );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Payment refund failed. Order ID: 123. Transaction can\'t be refunded until the next day.',
				'debug'
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Captured orders can be refunded the next day.' );
		$this->get_private_method_value( 'can_be_refunded', $order, 100 );
	}

	/**
	 * Test can_be_refunded method with partial refund.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_refunded
	 * @return void
	 */
	public function test_can_be_refunded_with_partial_refund(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->times( 3 )->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_time_completed' )->once()->andReturn( strtotime( '-1 day' ) );
		$order->shouldReceive( 'get_total' )->once()->andReturn( 100 );

		// Test the method.
		$this->assertTrue( $this->get_private_method_value( 'can_be_refunded', $order, 50 ) );
	}

	/**
	 * Test can_be_refunded method with partial refund error.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_refunded
	 * @return void
	 */
	public function test_can_be_refunded_with_partial_refund_error(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->times( 3 )->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_time_completed' )->once()->andReturn( strtotime( 'today' ) );
		$order->shouldReceive( 'get_total' )->once()->andReturn( 100 );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Payment refund failed. Partial refunds are only available on the next day. Order ID: 123.',
				'debug'
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Partial refunds are only available on the next day.' );
		$this->get_private_method_value( 'can_be_refunded', $order, 50 );
	}

	/**
	 * Test refund_order method with non-existent order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::refund_order
	 * @return void
	 */
	public function test_refund_order_with_non_existent_order(): void {
		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( false );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Failed to find order. Order ID: 123.' );
		$this->service->refund_order( 123, 100 );
	}

	/**
	 * Test refund_order method with invalid order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::refund_order
	 * @return void
	 */
	public function test_refund_order_with_invalid_order(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'failed' );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Payment refund failed. Transaction is not in "success" or "settled" status.' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Payment refund failed. Order ID: 123. Transaction is not in "success" or "settled" status.',
				'debug'
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment refund failed. Transaction is not in "success" or "settled" status.' );
		$this->service->refund_order( 123, 100 );
	}

	/**
	 * Test refund_order method with wrong total.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::refund_order
	 * @return void
	 */
	public function test_refund_order_with_wrong_total(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->twice()->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'get_total' )->twice()->andReturn( 50 );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 123 );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Payment refund failed. Refund amount "100" is greater than order total "50".' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Payment refund failed. Refund amount "100" is greater than order total "50". Order ID: 123.',
				'error'
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment refund failed. Refund amount "100" is greater than order total "50".' );
		$this->service->refund_order( 123, 100 );
	}

	/**
	 * Test refund_order method with success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::refund_order
	 * @return void
	 */
	public function test_refund_order_success(): void {
		// Set test data.
		$order_id                = 123;
		$transaction_id          = 'transaction_123';
		$timestamp               = 1234567890;
		$order_total             = 100;
		$refund_transaction_id   = 'transaction_456';
		$refund_amount           = 100;
		$refund_amount_formatted = '£100.00';

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->twice()->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'get_total' )->twice()->andReturn( $order_total );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'refunded_full' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_updated', $timestamp );
		$order->shouldReceive( 'save' )->once();
		$order->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $transaction_id );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment refunded successfully. Refund amount: %1$s. Transaction ID: %2$s. Refund transaction ID: %3$s.', $refund_amount_formatted, $transaction_id, $refund_transaction_id ) );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock TransactionRefund.
		$transaction_refund = Mockery::mock( TransactionRefund::class );
		$transaction_refund->shouldReceive( 'is_refunded' )->once()->andReturn( true );
		$transaction_refund->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction_refund->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $refund_transaction_id );

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( $timestamp );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_payment_reference' )
			->once()
			->andReturn( 'Test Store' );

		// Mock ApiClient.

		$this->get_api_client()
			->shouldReceive( 'refund_transaction' )
			->once()
			->with(
				$transaction_id,
				[
					'amount'    => $refund_amount,
					'reference' => 'Test Store',
				]
			)
			->andReturn( $transaction_refund );

		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( $refund_transaction_id )
			->andReturn( $transaction );

		// Mock wc_price.
		Functions\expect( 'wc_price' )
			->once()
			->with( $refund_amount )
			->andReturn( $refund_amount_formatted );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment refunded successfully. Order ID: %s.', $order_id ),
				'debug',
				[
					'refund_amount' => $refund_amount,
					'order_total'   => $order_total,
				]
			);

		// Test the method.
		$this->service->refund_order( $order_id, $refund_amount );
	}

	/**
	 * Test refund_order method partial with success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::refund_order
	 * @return void
	 */
	public function test_refund_order_partial_success(): void {
		// Set test data.
		$order_id                = 123;
		$transaction_id          = 'transaction_123';
		$timestamp               = 1234567890;
		$order_total             = 100;
		$refund_transaction_id   = 'transaction_456';
		$refund_amount           = 50;
		$refund_amount_formatted = '£50.00';

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->times( 3 )->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_time_completed' )->once()->andReturn( strtotime( '-1 day' ) );
		$order->shouldReceive( 'get_total' )->twice()->andReturn( $order_total );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'refunded_partial' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_updated', $timestamp );
		$order->shouldReceive( 'save' )->once();
		$order->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $transaction_id );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment refunded successfully. Refund amount: %s. Transaction ID: %s. Refund transaction ID: %s.', $refund_amount_formatted, $transaction_id, $refund_transaction_id ) );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock TransactionRefund.
		$transaction_refund = Mockery::mock( TransactionRefund::class );
		$transaction_refund->shouldReceive( 'is_refunded' )->once()->andReturn( true );
		$transaction_refund->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction_refund->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $refund_transaction_id );

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );
		$transaction->shouldReceive( 'get_created_timestamp' )->once()->andReturn( $timestamp );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_payment_reference' )
			->once()
			->andReturn( 'Test Store' );

		// Mock ApiClient.

		$this->get_api_client()
			->shouldReceive( 'refund_transaction' )
			->once()
			->with(
				$transaction_id,
				[
					'amount'    => $refund_amount,
					'reference' => 'Test Store',
				]
			)
			->andReturn( $transaction_refund );

		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( $refund_transaction_id )
			->andReturn( $transaction );

		// Mock wc_price.
		Functions\expect( 'wc_price' )
			->once()
			->with( $refund_amount )
			->andReturn( $refund_amount_formatted );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment refunded successfully. Order ID: %s.', $order_id ),
				'debug',
				[
					'refund_amount' => $refund_amount,
					'order_total'   => $order_total,
				]
			);

		// Test the method.
		$this->service->refund_order( $order_id, $refund_amount );
	}

	/**
	 * Test refund_order method with decline.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::refund_order
	 * @return void
	 */
	public function test_refund_order_decline(): void {
		// Set test data.
		$order_id                = 123;
		$transaction_id          = 'transaction_123';
		$order_total             = 100;
		$refund_amount           = 100;
		$refund_amount_formatted = '£100.00';

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->twice()->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'get_total' )->twice()->andReturn( $order_total );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );
		$order->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $transaction_id );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment refund declined with status "declined". Refund amount: %s. Transaction ID: %s. Error message: "Invalid order".', $refund_amount_formatted, $transaction_id ) );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock TransactionRefund.
		$transaction_refund = Mockery::mock( TransactionRefund::class );
		$transaction_refund->shouldReceive( 'is_refunded' )->once()->andReturn( false );
		$transaction_refund->shouldReceive( 'get_decline_reason' )->twice()->andReturn( 'declined' );
		$transaction_refund->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction_refund->shouldReceive( 'get_error_message_formatted' )->once()->with( true )->andReturn( 'Error message: "Invalid order".' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_payment_reference' )
			->once()
			->andReturn( 'Test Store' );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'refund_transaction' )
			->once()
			->with(
				$transaction_id,
				[
					'amount'    => $refund_amount,
					'reference' => 'Test Store',
				]
			)
			->andReturn( $transaction_refund );

		// Mock wc_price.
		Functions\expect( 'wc_price' )
			->once()
			->with( $refund_amount )
			->andReturn( $refund_amount_formatted );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment refund declined. Order ID: %s.', $order_id ),
				'debug',
				[
					'refund_amount' => $refund_amount,
					'order_total'   => $order_total,
				]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment refund failed. Check order notes for more details.' );
		$this->service->refund_order( $order_id, $refund_amount );
	}

	/**
	 * Test refund_order method with error.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::refund_order
	 * @return void
	 */
	public function test_refund_order_error(): void {
		// Set test data.
		$order_id                = 123;
		$transaction_id          = 'transaction_123';
		$order_total             = 100;
		$refund_amount           = 100;
		$refund_amount_formatted = '£100.00';

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_state' )->twice()->andReturn( 'completed' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'get_total' )->twice()->andReturn( $order_total );
		$order->shouldReceive( 'get_id' )->once()->andReturn( $order_id );
		$order->shouldReceive( 'get_transaction_id' )->twice()->andReturn( $transaction_id );
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment refund failed. Transaction ID: %s. Error message: "Invalid order".', $transaction_id ) );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock TransactionRefund.
		$transaction_refund = Mockery::mock( TransactionRefund::class );
		$transaction_refund->shouldReceive( 'is_refunded' )->once()->andReturn( false );
		$transaction_refund->shouldReceive( 'get_decline_reason' )->once()->andReturn( '' );
		$transaction_refund->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$transaction_refund->shouldReceive( 'get_error_message_formatted' )->once()->with( true )->andReturn( 'Error message: "Invalid order".' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_payment_reference' )
			->once()
			->andReturn( 'Test Store' );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'refund_transaction' )
			->once()
			->with(
				$transaction_id,
				[
					'amount'    => $refund_amount,
					'reference' => 'Test Store',
				]
			)
			->andReturn( $transaction_refund );

		// Mock wc_price.
		Functions\expect( 'wc_price' )
			->once()
			->with( $refund_amount )
			->andReturn( $refund_amount_formatted );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				sprintf( 'Payment refund failed. Order ID: %s.', $order_id ),
				'error',
				[
					'refund_amount' => $refund_amount,
					'order_total'   => $order_total,
				]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment refund failed. Check order notes for more details.' );
		$this->service->refund_order( $order_id, $refund_amount );
	}

	/**
	 * Test get_fail_notice method with invalid order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_fail_notice
	 * @return void
	 */
	public function test_get_fail_notice_with_invalid_order(): void {
		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( false );

		// Test the method.
		$this->assertNull( $this->service->get_fail_notice( 123 ) );
	}

	/**
	 * Test get_fail_notice method with non ACFW payment method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_fail_notice
	 * @return void
	 */
	public function test_get_fail_notice_with_non_acfw_payment(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'other_payment_method' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Test the method.
		$this->assertNull( $this->service->get_fail_notice( 123 ) );
	}

	/**
	 * Test get_fail_notice method with non-failed order status.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_fail_notice
	 * @return void
	 */
	public function test_get_fail_notice_with_non_failed_status(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'has_status' )->once()->with( 'failed' )->andReturn( false );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Test the method.
		$this->assertNull( $this->service->get_fail_notice( 123 ) );
	}

	/**
	 * Test get_fail_notice method with blocked status.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_fail_notice
	 * @return void
	 */
	public function test_get_fail_notice_with_blocked_status(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'has_status' )->once()->with( 'failed' )->andReturn( true );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'blocked' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Test the method.
		$this->assertEquals( 'Your payment was blocked.', $this->service->get_fail_notice( 123 ) );
	}

	/**
	 * Test get_fail_notice method with TDS error statuses.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_fail_notice
	 * @return void
	 */
	public function test_get_fail_notice_with_tds_error_status(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'has_status' )->once()->with( 'failed' )->andReturn( true );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'tds_error' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Test the method.
		$this->assertEquals( 'Your payment has been declined due to failed authentication with your bank.', $this->service->get_fail_notice( 123 ) );
	}

	/**
	 * Test get_fail_notice method with TDS expired statuses.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_fail_notice
	 * @return void
	 */
	public function test_get_fail_notice_with_tds_expired_status(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'has_status' )->once()->with( 'failed' )->andReturn( true );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'tds_expired' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Test the method.
		$this->assertEquals( 'Your payment has been declined due to failed authentication with your bank.', $this->service->get_fail_notice( 123 ) );
	}

	/**
	 * Test get_fail_notice method with TDS failed statuses.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_fail_notice
	 * @return void
	 */
	public function test_get_fail_notice_with_tds_failed_status(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'has_status' )->once()->with( 'failed' )->andReturn( true );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'tds_failed' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Test the method.
		$this->assertEquals( 'Your payment has been declined due to failed authentication with your bank.', $this->service->get_fail_notice( 123 ) );
	}

	/**
	 * Test get_fail_notice method with default status.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::get_fail_notice
	 * @return void
	 */
	public function test_get_fail_notice_with_default_status(): void {
			// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'has_status' )->once()->with( 'failed' )->andReturn( true );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'declined' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( 123 )
			->andReturn( $order );

		// Test the method.
		$this->assertEquals( 'Your payment was declined.', $this->service->get_fail_notice( 123 ) );
	}

	/**
	 * Test can_be_synced.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::can_be_synced
	 * @return void
	 */
	public function test_can_be_synced(): void {
		// Set test data.
		$order_id = 123;

		// Test pending order paid with Acquired.com.
		$this->assertTrue( $this->get_private_method_value( 'can_be_synced', $this->mock_order_for_status_check( $order_id ) ) );

		// Test order paid with another payment method.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'bacs' );
		$this->assertFalse( $this->get_private_method_value( 'can_be_synced', $order ) );

		// Test order that is already processed.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_payment_method' )->once()->andReturn( 'acfw' );
		$order->shouldReceive( 'has_status' )->once()->with( [ 'pending', 'failed' ] )->andReturn( false );
		$this->assertFalse( $this->get_private_method_value( 'can_be_synced', $order ) );
	}

	/**
	 * Test sync_order processes the successful transaction.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::sync_order
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::process_transaction
	 * @return void
	 */
	public function test_sync_order_success(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';
		$timestamp            = 1234567890;

		// Mock WC_Order.
		$order = $this->mock_order_for_status_check( $order_id );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( null );
		$order->shouldReceive( 'get_transaction_id' )->andReturn( $transaction_id );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_order_time_updated' )->once()->andReturn( '' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_status' )->once()->andReturn( 'success' );
		$order->shouldReceive( 'get_meta' )->with( '_acfw_transaction_type' )->once()->andReturn( 'capture' );
		$order->shouldReceive( 'has_status' )->once()->with( [ 'pending', 'failed', 'on-hold' ] )->andReturn( true );
		$order->shouldReceive( 'set_transaction_id' )->once()->with( $transaction_id );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_transaction_status', 'success' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_updated', $timestamp );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_version', '2.0.0' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'completed' );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_time_completed', $timestamp );
		$order->shouldReceive( 'payment_complete' )->once();
		$order->shouldReceive( 'add_order_note' )->once()->with( sprintf( 'Payment successful. Transaction ID: %s.', $transaction_id ) );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Order processed successfully from incoming API data.' );
		$order->shouldReceive( 'save' )->twice();

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'get_status' )->once()->andReturn( 'success' );
		$transaction->shouldReceive( 'get_created_timestamp' )->andReturn( $timestamp );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );

		// Mock set_additional_order_data.
		$this->mock_set_additional_order_data( $order, $transaction );

		// Mock ApiClient.

		$this->mock_get_transactions( $transaction_order_id, $transaction_id );

		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( $transaction_id )
			->andReturn( $transaction );

		// Mock LoggerService.

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'Transaction found successfully from incoming API data. Order ID: %d.', $order_id ), 'debug', [] );

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'Payment complete for order. Order ID: %d.', $order_id ), 'debug', [] );

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'Order processed successfully from incoming API data. Order ID: %d.', $order_id ), 'debug', [] );

		// Test the method.
		$this->assertTrue( $this->get_private_method_value( 'sync_order', $order ) );
	}

	/**
	 * Test sync_order when no successful transaction exists.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::sync_order
	 * @return void
	 */
	public function test_sync_order_not_found(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';

		// Mock ApiClient.
		$this->mock_get_transactions( $transaction_order_id, null );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'No successful transaction found on Acquired.com. Order ID: %d.', $order_id ), 'debug', [] );

		// Test the method.
		$this->assertFalse( $this->get_private_method_value( 'sync_order', $this->mock_order_for_status_check( $order_id ) ) );
	}

	/**
	 * Test sync_order when the transaction is already on the order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::sync_order
	 * @return void
	 */
	public function test_sync_order_already_processed(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';

		// Mock WC_Order.
		$order = $this->mock_order_for_status_check( $order_id );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );

		// Mock ApiClient.

		$this->mock_get_transactions( $transaction_order_id, $transaction_id );

		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->never();

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'Order transaction already processed. Skipping the processing. Order ID: %d.', $order_id ), 'debug' );

		// Test the method.
		$this->assertTrue( $this->get_private_method_value( 'sync_order', $order ) );
	}

	/**
	 * Test sync_order when the API request fails.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::sync_order
	 * @return void
	 */
	public function test_sync_order_request_error(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';

		// Mock ApiClient.
		$this->mock_get_transactions( $transaction_order_id, null, true );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'Failed to get transactions. Order ID: %d.', $order_id ), 'error', [] );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Failed to get transactions.' );
		$this->get_private_method_value( 'sync_order', $this->mock_order_for_status_check( $order_id ) );
	}

	/**
	 * Test confirm_unverified_order checks the payment status with Acquired.com.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::confirm_unverified_order
	 * @return void
	 */
	public function test_confirm_unverified_order_checks_payment_status(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';

		// Mock RedirectData.
		$redirect = Mockery::mock( RedirectData::class );
		$redirect->shouldReceive( 'get_order_id' )->andReturn( $transaction_order_id );
		$redirect->shouldReceive( 'get_hash_status' )->once()->andReturn( 'invalid' );

		// Mock WC_Order.
		$order = $this->mock_order_for_status_check( $order_id );
		$order->shouldReceive( 'update_meta_data' )->once()->with( '_acfw_order_state', 'failed' );
		$order->shouldReceive( 'update_status' )->once()->with( 'failed' );
		$order->shouldReceive( 'save' )->once();
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Redirect data from Acquired.com couldn\'t be verified with the signing key. Payment status checked with the Acquired.com API.' );
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Payment status checked with Acquired.com. No successful payment found.' );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'set_signing_key_issue' )
			->once()
			->with( 'invalid' );

		// Mock ApiClient.
		$this->mock_get_transactions( $transaction_order_id, null );

		// Mock LoggerService.

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'No successful transaction found on Acquired.com. Order ID: %d.', $order_id ), 'debug', [] );

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'Payment failed for order. Order ID: %d.', $order_id ), 'debug' );

		// Test the method.
		$this->assertSame( $order, $this->service->confirm_unverified_order( $redirect ) );
	}

	/**
	 * Test confirm_unverified_order when the successful payment is found.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::confirm_unverified_order
	 * @return void
	 */
	public function test_confirm_unverified_order_with_successful_payment(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';
		$transaction_id       = 'transaction_123';

		// Mock RedirectData.
		$redirect = Mockery::mock( RedirectData::class );
		$redirect->shouldReceive( 'get_order_id' )->andReturn( $transaction_order_id );
		$redirect->shouldReceive( 'get_hash_status' )->once()->andReturn( 'invalid' );

		// Mock WC_Order.
		$order = $this->mock_order_for_status_check( $order_id );
		$order->shouldReceive( 'update_status' )->never();
		$order->shouldReceive( 'add_order_note' )->once()->with( 'Redirect data from Acquired.com couldn\'t be verified with the signing key. Payment status checked with the Acquired.com API.' );
		$order->shouldReceive( 'get_transaction_id' )->once()->andReturn( $transaction_id );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'set_signing_key_issue' )
			->once()
			->with( 'invalid' );

		// Mock ApiClient.
		$this->mock_get_transactions( $transaction_order_id, $transaction_id );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'Order transaction already processed. Skipping the processing. Order ID: %d.', $order_id ), 'debug' );

		// Test the method.
		$this->assertSame( $order, $this->service->confirm_unverified_order( $redirect ) );
	}

	/**
	 * Test confirm_unverified_order for an order that doesn't need a check.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::confirm_unverified_order
	 * @return void
	 */
	public function test_confirm_unverified_order_for_processed_order(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_key';

		// Mock RedirectData.
		$redirect = Mockery::mock( RedirectData::class );
		$redirect->shouldReceive( 'get_order_id' )->andReturn( $transaction_order_id );

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->andReturn( $order_id );
		$order->shouldReceive( 'get_order_key' )->andReturn( 'wc_order_key' );
		$order->shouldReceive( 'get_payment_method' )->andReturn( 'acfw' );
		$order->shouldReceive( 'has_status' )->with( [ 'pending', 'failed' ] )->andReturn( false );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transactions' )
			->never();

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'Unverified redirect for an order that doesn\'t need a payment status check. Order ID: %d.', $order_id ), 'debug' );

		// Test the method.
		$this->assertSame( $order, $this->service->confirm_unverified_order( $redirect ) );
	}

	/**
	 * Test confirm_unverified_order with an invalid order key.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\OrderService::confirm_unverified_order
	 * @return void
	 */
	public function test_confirm_unverified_order_with_invalid_order_key(): void {
		// Set test data.
		$order_id             = 123;
		$transaction_order_id = '123-wc_order_wrong';

		// Mock RedirectData.
		$redirect = Mockery::mock( RedirectData::class );
		$redirect->shouldReceive( 'get_order_id' )->andReturn( $transaction_order_id );

		// Mock WC_Order.
		$order = $this->mock_order_for_status_check( $order_id );

		// Mock wc_get_order.
		Functions\expect( 'wc_get_order' )
			->once()
			->with( $order_id )
			->andReturn( $order );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transactions' )
			->never();

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( sprintf( 'Error processing order from unverified redirect data. Order key in incoming data is invalid. Order ID: %d.', $order_id ), 'error' );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( sprintf( 'Order key in incoming data is invalid. Order ID: %d.', $order_id ) );
		$this->service->confirm_unverified_order( $redirect );
	}
}
