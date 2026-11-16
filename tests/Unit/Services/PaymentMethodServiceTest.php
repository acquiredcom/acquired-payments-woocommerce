<?php
/**
 * PaymentMethodServiceTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Services;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\ApiClientMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\LoggerServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\Traits\TokenFactoryMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\TokenServiceMock;
use AcquiredComForWooCommerce\Services\PaymentMethodService;
use AcquiredComForWooCommerce\Tests\Framework\Traits\CustomerFactoryMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\CustomerServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\ScheduleServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\SettingsServiceMock;
use AcquiredComForWooCommerce\Api\Response\Card;
use AcquiredComForWooCommerce\Api\Response\Transaction;
use AcquiredComForWooCommerce\Api\Response\PaymentLink;
use AcquiredComForWooCommerce\Api\IncomingData\WebhookData;
use AcquiredComForWooCommerce\Api\IncomingData\RedirectData;
use Mockery;
use Mockery\MockInterface;
use Brain\Monkey\Functions;
use Exception;
use stdClass;

/**
 * PaymentMethodServiceTest class.
 *
 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService
 */
class PaymentMethodServiceTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use ApiClientMock;
	use CustomerServiceMock;
	use LoggerServiceMock;
	use ScheduleServiceMock;
	use SettingsServiceMock;
	use TokenServiceMock;
	use CustomerFactoryMock;
	use TokenFactoryMock;

	/**
	 * PaymentMethodService class.
	 *
	 * @var PaymentMethodService
	 */
	private PaymentMethodService $service;

	/**
	 * Get test card data.
	 *
	 * @param string $status
	 * @return stdClass
	 */
	private function get_test_card_data( string $status ): stdClass {
		$cards = [
			'valid'   => (object) [
				'scheme'       => 'visa',
				'number'       => 1234,
				'expiry_month' => 6,
				'expiry_year'  => 25,
			],
			'expired' => (object) [
				'scheme'       => 'visa',
				'number'       => 4567,
				'expiry_month' => 12,
				'expiry_year'  => 20,
			],
		];

		return $cards[ $status ] ?? $cards['valid'];
	}

	/**
	 * Mock tokenization setting.
	 *
	 * @param bool $setting
	 * @return void
	 */
	private function mock_tokenization_setting( bool $setting ): void {
		$this->get_settings_service()
			->shouldReceive( 'is_enabled' )
			->once()
			->with( 'tokenization' )
			->andReturn( $setting );
	}

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Clear $_POST before each test.
		$_POST = [];

		$this->mock_api_client();
		$this->mock_logger_service();
		$this->mock_customer_service();
		$this->mock_schedule_service();
		$this->mock_settings_service();
		$this->mock_token_service();
		$this->mock_customer_factory();
		$this->mock_token_factory();

		$this->service = new PaymentMethodService(
			$this->get_api_client(),
			$this->get_customer_service(),
			$this->get_logger_service(),
			$this->get_schedule_service(),
			$this->get_settings_service(),
			$this->get_token_service(),
			$this->get_customer_factory(),
			$this->get_token_factory(),
		);

		$this->initialize_reflection( $this->service );
	}

	/**
	 * Tear down the test case.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		// Clear $_POST after each test.
		$_POST = [];

		parent::tearDown();
	}

	/**
	 * Test constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertSame( $this->get_api_client(), $this->get_private_property_value( 'api_client' ) );
		$this->assertSame( $this->get_customer_service(), $this->get_private_property_value( 'customer_service' ) );
		$this->assertSame( $this->get_logger_service(), $this->get_private_property_value( 'logger_service' ) );
		$this->assertSame( $this->get_schedule_service(), $this->get_private_property_value( 'schedule_service' ) );
		$this->assertSame( $this->get_settings_service(), $this->get_private_property_value( 'settings_service' ) );
		$this->assertSame( $this->get_token_service(), $this->get_private_property_value( 'token_service' ) );
		$this->assertSame( $this->get_customer_factory(), $this->get_private_property_value( 'customer_factory' ) );
		$this->assertSame( $this->get_token_factory(), $this->get_private_property_value( 'token_factory' ) );
	}

	/**
	 * Test is_transaction_success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::is_transaction_success
	 * @return void
	 */
	public function test_is_transaction_success(): void {
		$this->assertTrue( $this->service->is_transaction_success( 'success' ) );
		$this->assertTrue( $this->service->is_transaction_success( 'settled' ) );
		$this->assertTrue( $this->service->is_transaction_success( 'executed' ) );
		$this->assertFalse( $this->service->is_transaction_success( 'failed' ) );
	}

	/**
	 * Test get_status_key returns correct key.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_status_key
	 * @return void
	 */
	public function test_get_status_key_returns_correct_key(): void {
		$this->assertEquals( 'acfw_payment_method_status', $this->service->get_status_key() );
	}

	/**
	 * Test get_scheduled_action_hook returns correct hook.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_scheduled_action_hook
	 * @return void
	 */
	public function test_get_scheduled_action_hook_returns_correct_hook(): void {
		$this->assertEquals( 'acfw_scheduled_save_payment_method', $this->service->get_scheduled_action_hook() );
	}

	/**
	 * Test set_token_card_data sets card data correctly.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::set_token_card_data
	 * @return void
	 */
	public function test_set_token_card_data(): void {
		// Mock WC_Payment_Token_CC.

		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_card_type' )->once();

		$token->shouldReceive( 'set_last4' )
			->once()
			->with( '1234' )
			->andReturnUsing(
				function( $value ) {
					$this->assertIsString( $value );
				}
			);

		$token->shouldReceive( 'set_expiry_month' )
			->once()
			->with( '06' )
			->andReturnUsing(
				function( $value ) {
					$this->assertIsString( $value );
				}
			);

		$token->shouldReceive( 'set_expiry_year' )
			->once()
			->with( '2025' )
			->andReturnUsing(
				function( $value ) {
					$this->assertIsString( $value );
				}
			);

		// Test the method.
		$this->get_private_method_value( 'set_token_card_data', $token, $this->get_test_card_data( 'valid' ) );
	}

	/**
	 * Test create_token success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::create_token
	 * @return void
	 */
	public function test_create_token_success(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_token' )->once()->with( 'token_123' );
		$token->shouldReceive( 'set_card_type' )->once()->with( 'visa' );
		$token->shouldReceive( 'set_last4' )->once()->with( '1234' );
		$token->shouldReceive( 'set_expiry_month' )->once()->with( '06' );
		$token->shouldReceive( 'set_expiry_year' )->once()->with( '2025' );
		$token->shouldReceive( 'set_gateway_id' )->once()->with( 'acfw' );
		$token->shouldReceive( 'set_user_id' )->once()->with( 456 );
		$token->shouldReceive( 'validate' )->once()->andReturn( true );
		$token->shouldReceive( 'save' )->once();

		// Mock TokenFactory.
		$this->get_token_factory()
			->shouldReceive( 'get_wc_payment_token' )
			->once()
			->andReturn( $token );

		// Test the method.
		$this->get_private_method_value( 'create_token', 'token_123', $this->get_test_card_data( 'valid' ), 456 );
	}

	/**
	 * Test create_token success with order.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::create_token
	 * @return void
	 */
	public function test_create_token_success_with_order(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_token' )->once()->with( 'token_123' );
		$token->shouldReceive( 'set_card_type' )->once()->with( 'visa' );
		$token->shouldReceive( 'set_last4' )->once()->with( '1234' );
		$token->shouldReceive( 'set_expiry_month' )->once()->with( '06' );
		$token->shouldReceive( 'set_expiry_year' )->once()->with( '2025' );
		$token->shouldReceive( 'set_gateway_id' )->once()->with( 'acfw' );
		$token->shouldReceive( 'set_user_id' )->once()->with( 456 );
		$token->shouldReceive( 'validate' )->once()->andReturn( true );
		$token->shouldReceive( 'save' )->once();

		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'add_payment_token' )
			->once()
			->withArgs(
				function( $token ) {
					return $token instanceof MockInterface;
				}
			);
		$order->shouldReceive( 'save' )->once();

		// Mock TokenFactory.
		$this->get_token_factory()
			->shouldReceive( 'get_wc_payment_token' )
			->once()
			->andReturn( $token );

		// Test the method.
		$this->get_private_method_value( 'create_token', 'token_123', $this->get_test_card_data( 'valid' ), 456, $order );
	}

	/**
	 * Test create_token throws exception on validation failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::create_token
	 * @return void
	 */
	public function test_create_token_throws_exception_on_validation_failure(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_token' )->once()->with( 'token_123' );
		$token->shouldReceive( 'set_card_type' )->once()->with( 'visa' );
		$token->shouldReceive( 'set_last4' )->once()->with( '4567' );
		$token->shouldReceive( 'set_expiry_month' )->once()->with( '12' );
		$token->shouldReceive( 'set_expiry_year' )->once()->with( '2020' );
		$token->shouldReceive( 'set_gateway_id' )->once()->with( 'acfw' );
		$token->shouldReceive( 'set_user_id' )->once()->with( 456 );
		$token->shouldReceive( 'validate' )->once()->andReturn( false );
		$token->shouldNotReceive( 'save' );

		// Mock TokenFactory.
		$this->get_token_factory()
			->shouldReceive( 'get_wc_payment_token' )
			->once()
			->andReturn( $token );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Failed to validate token.' );
		$this->get_private_method_value( 'create_token', 'token_123', $this->get_test_card_data( 'expired' ), 456 );
	}

	/**
	 * Test update_token success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::update_token
	 * @return void
	 */
	public function test_update_token_success(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_card_type' )->once()->with( 'visa' );
		$token->shouldReceive( 'set_last4' )->once()->with( '1234' );
		$token->shouldReceive( 'set_expiry_month' )->once()->with( '06' );
		$token->shouldReceive( 'set_expiry_year' )->once()->with( '2025' );
		$token->shouldReceive( 'validate' )->once()->andReturn( true );
		$token->shouldReceive( 'save' )->once();

		// Test the method.
		$this->get_private_method_value( 'update_token', $token, $this->get_test_card_data( 'valid' ) );
	}

	/**
	 * Test update_token throws exception on validation failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::update_token
	 * @return void
	 */
	public function test_update_token_throws_exception_on_validation_failure(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_card_type' )->once()->with( 'visa' );
		$token->shouldReceive( 'set_last4' )->once()->with( '4567' );
		$token->shouldReceive( 'set_expiry_month' )->once()->with( '12' );
		$token->shouldReceive( 'set_expiry_year' )->once()->with( '2020' );
		$token->shouldReceive( 'validate' )->once()->andReturn( false );
		$token->shouldNotReceive( 'save' );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Failed to validate token.' );
		$this->get_private_method_value( 'update_token', $token, $this->get_test_card_data( 'expired' ) );
	}

	/**
	 * Test get_card success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_card
	 * @return void
	 */
	public function test_get_card_success(): void {
		// Mock Card.
		$card = Mockery::mock( Card::class );
		$card->shouldReceive( 'is_active' )->once()->andReturn( true );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andReturn( $card );

		// Test the method.
		$this->assertInstanceOf( Card::class, $this->get_private_method_value( 'get_card', 'token_123' ) );
	}

	/**
	 * Test get_card throws exception when request fails.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_card
	 * @return void
	 */
	public function test_get_card_throws_exception_when_request_fails(): void {
		// Mock Card.
		$card = Mockery::mock( Card::class );
		$card->shouldReceive( 'is_active' )->once()->andReturn( false );
		$card->shouldReceive( 'request_is_error' )->once()->andReturn( true );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andReturn( $card );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Card retrieval failed.' );
		$this->get_private_method_value( 'get_card', 'token_123' );
	}

	/**
	 * Test get_card throws exception when card is not active.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_card
	 * @return void
	 */
	public function test_get_card_throws_exception_when_card_not_active(): void {
		// Mock Card.
		$card = Mockery::mock( Card::class );
		$card->shouldReceive( 'is_active' )->once()->andReturn( false );
		$card->shouldReceive( 'request_is_error' )->once()->andReturn( false );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andReturn( $card );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Card is not active.' );
		$this->get_private_method_value( 'get_card', 'token_123' );
	}

	/**
	 * Test get_card_id_from_transaction success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_card_id_from_transaction
	 * @return void
	 */
	public function test_get_card_id_from_transaction_success(): void {
		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );
		$transaction->shouldReceive( 'get_card_id' )->twice()->andReturn( 'token_123' );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( 'transaction_123' )
			->andReturn( $transaction );

		// Test the method.
		$this->assertEquals( 'token_123', $this->get_private_method_value( 'get_card_id_from_transaction', 'transaction_123' ) );
	}

	/**
	 * Test get_card_id_from_transaction throws exception when request fails.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_card_id_from_transaction
	 * @return void
	 */
	public function test_get_card_id_from_transaction_throws_exception_when_request_fails(): void {
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
		$this->expectExceptionMessage( 'Card ID retrieval failed.' );
		$this->get_private_method_value( 'get_card_id_from_transaction', 'transaction_123' );
	}

	/**
	 * Test get_card_id_from_transaction throws exception when card ID not found.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_card_id_from_transaction
	 * @return void
	 */
	public function test_get_card_id_from_transaction_throws_exception_when_card_id_not_found(): void {
		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );
		$transaction->shouldReceive( 'get_card_id' )->once()->andReturn( null );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( 'transaction_123' )
			->andReturn( $transaction );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Card ID not found.' );
		$this->get_private_method_value( 'get_card_id_from_transaction', 'transaction_123' );
	}

	/**
	 * Test deactivate_card success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::deactivate_card
	 * @return void
	 */
	public function test_deactivate_card_success(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'get_token' )->once()->andReturn( 'token_123' );

		// Mock Card.
		$response = Mockery::mock( Card::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( true );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'update_card' )
			->once()
			->with( 'token_123', [ 'is_active' => false ] )
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment method deletion successful.', 'debug', [] );

		// Test the method.
		$this->service->deactivate_card( $token );
	}

	/**
	 * Test deactivate_card failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::deactivate_card
	 * @return void
	 */
	public function test_deactivate_card_failure(): void {
		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'get_token' )->once()->andReturn( 'token_123' );

		// Mock Card.
		$response = Mockery::mock( Card::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( false );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'update_card' )
			->once()
			->with( 'token_123', [ 'is_active' => false ] )
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment method deletion failed.', 'error', [] );

		// Test the method.
		$this->service->deactivate_card( $token );
	}

	/**
	 * Test process_payment_method success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::process_payment_method
	 * @return void
	 */
	public function test_process_payment_method_success(): void {
		// Mock tokenization setting.
		$this->mock_tokenization_setting( true );

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Test success message', 'debug', [] );

		// Test the method.
		$this->get_private_method_value(
			'process_payment_method',
			'saving',
			function( $webhook, $log ) {
				$log( 'Test success message' );
			},
			$webhook
		);
	}

	/**
	 * Test process_payment_method throws exception when tokenization disabled.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::process_payment_method
	 * @return void
	 */
	public function test_process_payment_method_throws_exception_when_tokenization_disabled(): void {
		// Mock tokenization setting.
		$this->mock_tokenization_setting( false );

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment method saving failed. Tokenization is disabled.', 'error', [] );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment method saving failed. Tokenization is disabled.' );
		$this->get_private_method_value(
			'process_payment_method',
			'saving',
			function() {},
			$webhook
		);
	}

	/**
	 * Test process_payment_method throws exception when process fails.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::process_payment_method
	 * @return void
	 */
	public function test_process_payment_method_throws_exception_when_process_fails(): void {
		// Mock tokenization setting.
		$this->mock_tokenization_setting( true );

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment method saving failed. Test error message.', 'error', [] );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Test error message.' );
		$this->get_private_method_value(
			'process_payment_method',
			'saving',
			function() {
				throw new Exception( 'Test error message.' );
			},
			$webhook
		);
	}

	/**
	 * Test schedule_save_payment_method success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::schedule_save_payment_method
	 * @return void
	 */
	public function test_schedule_save_payment_method_success(): void {
		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->once()->andReturn( '456-add_payment_method_key' );
		$webhook->shouldReceive( 'get_incoming_data' )->once()->andReturn( [ 'test_data' ] );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->once()->andReturn( 456 );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( 456 )
			->andReturn( $customer );

		// Mock ScheduleService.
		$this->get_schedule_service()
			->shouldReceive( 'schedule' )
			->once()
			->with(
				'acfw_scheduled_save_payment_method',
				[
					'webhook_data' => json_encode( [ 'test_data' ] ),
					'hash'         => 'test_hash',
				]
			);

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Save payment method scheduled successfully from incoming webhook data. User ID: 456.',
				'debug',
				[]
			);

		// Test the method.
		$this->service->schedule_save_payment_method( $webhook, 'test_hash' );
	}

	/**
	 * Test schedule_save_payment_method throws exception when fails.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::schedule_save_payment_method
	 * @return void
	 */
	public function test_schedule_save_payment_method_throws_exception_when_fails(): void {
		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->once()->andReturn( 'invalid_order_id' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Error scheduling save payment method from incoming webhook data. Error: "No valid customer ID in incoming data.".',
				'error',
				[]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No valid customer ID in incoming data.' );
		$this->service->schedule_save_payment_method( $webhook, 'test_hash' );
	}

	/**
	 * Test save_payment_method_from_customer success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::save_payment_method_from_customer
	 * @return void
	 */
	public function test_save_payment_method_from_customer_success(): void {
		// Mock tokenization setting.
		$this->mock_tokenization_setting( true );

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->once()->andReturn( '456-add_payment_method_key' );
		$webhook->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$webhook->shouldReceive( 'get_type' )->times( 3 )->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->times( 3 )->andReturn( [] );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->times( 4 )->andReturn( 456 );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( 456 )
			->andReturn( $customer );

		// Mock Card.
		$card = Mockery::mock( Card::class );
		$card->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$card->shouldReceive( 'get_card_data' )->once()->andReturn( $this->get_test_card_data( 'valid' ) );
		$card->shouldReceive( 'is_active' )->once()->andReturn( true );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andReturn( $card );

		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_token' )->once()->with( 'token_123' );
		$token->shouldReceive( 'set_gateway_id' )->once();
		$token->shouldReceive( 'set_card_type' )->once()->with( 'visa' );
		$token->shouldReceive( 'set_last4' )->once()->with( '1234' );
		$token->shouldReceive( 'set_expiry_month' )->once()->with( '06' );
		$token->shouldReceive( 'set_expiry_year' )->once()->with( '2025' );
		$token->shouldReceive( 'set_user_id' )->once()->with( 456 );
		$token->shouldReceive( 'validate' )->once()->andReturn( true );
		$token->shouldReceive( 'save' )->once();

		// Mock TokenFactory.
		$this->get_token_factory()
			->shouldReceive( 'get_wc_payment_token' )
			->once()
			->andReturn( $token );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->times( 3 )
			->withArgs(
				function( $message, $level ) {
					return in_array(
						$message,
						[
							'User found successfully from incoming webhook data. User ID: 456.',
							'Payment method found successfully from incoming webhook data. User ID: 456.',
							'Payment method saved successfully from incoming webhook data. User ID: 456.',
						],
						true
					) && 'debug' === $level;
				}
			);

		// Test the method.
		$this->service->save_payment_method_from_customer( $webhook );
	}

	/**
	 * Test save_payment_method_from_order success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::save_payment_method_from_order
	 * @return void
	 */
	public function test_save_payment_method_from_order_success(): void {
		// Mock tokenization setting.
		$this->mock_tokenization_setting( true );

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->once()->andReturn( '789-wc_order_key' );
		$webhook->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$webhook->shouldReceive( 'get_log_data' )->times( 3 )->andReturn( [] );

		// Mock WC_Order.

		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_id' )->times( 3 )->andReturn( 789 );
		$order->shouldReceive( 'get_order_key' )->once()->andReturn( 'wc_order_key' );
		$order->shouldReceive( 'get_user_id' )->once()->andReturn( 456 );
		$order->shouldReceive( 'add_payment_token' )->once();
		$order->shouldReceive( 'save' )->once();

		Functions\expect( 'wc_get_order' )->once()->with( '789' )->andReturn( $order );

		// Mock Card.
		$card = Mockery::mock( Card::class );
		$card->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$card->shouldReceive( 'get_card_data' )->once()->andReturn( $this->get_test_card_data( 'valid' ) );
		$card->shouldReceive( 'is_active' )->once()->andReturn( true );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andReturn( $card );

		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_token' )->once()->with( 'token_123' );
		$token->shouldReceive( 'set_gateway_id' )->once();
		$token->shouldReceive( 'set_card_type' )->once()->with( 'visa' );
		$token->shouldReceive( 'set_last4' )->once()->with( '1234' );
		$token->shouldReceive( 'set_expiry_month' )->once()->with( '06' );
		$token->shouldReceive( 'set_expiry_year' )->once()->with( '2025' );
		$token->shouldReceive( 'set_user_id' )->once()->with( 456 );
		$token->shouldReceive( 'validate' )->once()->andReturn( true );
		$token->shouldReceive( 'save' )->once();

		// Mock TokenFactory.
		$this->get_token_factory()
			->shouldReceive( 'get_wc_payment_token' )
			->once()
			->andReturn( $token );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->times( 3 )
			->withArgs(
				function( $message, $level ) {
					return in_array(
						$message,
						[
							'Order found successfully from incoming webhook data. Order ID: 789.',
							'Payment method found successfully from incoming webhook data. Order ID: 789.',
							'Payment method saved successfully from incoming webhook data. Order ID: 789.',
						],
						true
					) && 'debug' === $level;
				}
			);

		// Test the method.
		$this->service->save_payment_method_from_order( $webhook );
	}

	/**
	 * Test save_payment_method_from_order throws exception when order not found.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::save_payment_method_from_order
	 * @return void
	 */
	public function test_save_payment_method_from_order_throws_exception_when_order_not_found(): void {
		// Mock tokenization setting.
		$this->mock_tokenization_setting( true );

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->once()->andReturn( 'invalid_order' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment method saving failed. No valid order ID in incoming data.', 'error', [] );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No valid order ID in incoming data.' );
		$this->service->save_payment_method_from_order( $webhook );
	}

	/**
	 * Test update_payment_method success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::update_payment_method
	 * @return void
	 */
	public function test_update_payment_method_success(): void {
		// Mock tokenization setting.
		$this->mock_tokenization_setting( true );

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$webhook->shouldReceive( 'get_log_data' )->times( 3 )->andReturn( [] );

		// Mock Card.
		$card = Mockery::mock( Card::class );
		$card->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$card->shouldReceive( 'get_customer_id' )->once()->andReturn( 'customer_456' );
		$card->shouldReceive( 'get_card_data' )->once()->andReturn( $this->get_test_card_data( 'valid' ) );
		$card->shouldReceive( 'is_active' )->once()->andReturn( true );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andReturn( $card );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->times( 3 )->andReturn( 456 );

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_from_customer_id' )
			->once()
			->with( 'customer_456' )
			->andReturn( $customer );

		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_card_type' )->once()->with( 'visa' );
		$token->shouldReceive( 'set_last4' )->once()->with( '1234' );
		$token->shouldReceive( 'set_expiry_month' )->once()->with( '06' );
		$token->shouldReceive( 'set_expiry_year' )->once()->with( '2025' );
		$token->shouldReceive( 'validate' )->once()->andReturn( true );
		$token->shouldReceive( 'save' )->once();

		// Mock TokenService.
		$this->get_token_service()
			->shouldReceive( 'get_token_by_user_and_card_id' )
			->once()
			->with( 456, 'token_123' )
			->andReturn( $token );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->times( 3 )
			->withArgs(
				function( $message, $level ) {
					return in_array(
						$message,
						[
							'Payment method found successfully from incoming webhook data.',
							'User found successfully from incoming webhook data. User ID: 456.',
							'Payment method updated successfully. User ID: 456.',
						],
						true
					) && 'debug' === $level;
				}
			);

		// Test the method.
		$this->service->update_payment_method( $webhook );
	}

	/**
	 * Test update_payment_method throws exception when token not found.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::update_payment_method
	 * @return void
	 */
	public function test_update_payment_method_throws_exception_when_token_not_found(): void {
		// Mock tokenization setting.
		$this->mock_tokenization_setting( true );

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$webhook->shouldReceive( 'get_log_data' )->times( 3 )->andReturn( [] );

		// Mock Card.
		$card = Mockery::mock( Card::class );
		$card->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$card->shouldReceive( 'get_customer_id' )->once()->andReturn( 'customer_456' );
		$card->shouldReceive( 'is_active' )->once()->andReturn( true );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andReturn( $card );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->twice()->andReturn( 456 );

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_from_customer_id' )
			->once()
			->with( 'customer_456' )
			->andReturn( $customer );

		// Mock TokenService.
		$this->get_token_service()
			->shouldReceive( 'get_token_by_user_and_card_id' )
			->once()
			->with( 456, 'token_123' )
			->andThrow( new Exception( 'Token not found.' ) );

		// Mock LoggerService.

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->twice()
			->withArgs(
				function( $message, $level ) {
					return in_array(
						$message,
						[
							'Payment method found successfully from incoming webhook data.',
							'User found successfully from incoming webhook data. User ID: 456.',
						],
						true
					) && 'debug' === $level;
				}
			);

		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Payment method updating failed. Token not found.',
				'error',
				[]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Token not found.' );
		$this->service->update_payment_method( $webhook );
	}

	/**
	 * Test process_scheduled_save_payment_method success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::process_scheduled_save_payment_method
	 * @return void
	 */
	public function test_process_scheduled_save_payment_method_success(): void {
		// Mock tokenization setting.
		$this->mock_tokenization_setting( true );

		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->times( 2 )->andReturn( '456-add_payment_method_key' );
		$webhook->shouldReceive( 'get_card_id' )->times( 2 )->andReturn( 'token_123' );
		$webhook->shouldReceive( 'get_type' )->times( 3 )->andReturn( 'webhook' );
		$webhook->shouldReceive( 'get_log_data' )->times( 4 )->andReturn( [] );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->times( 6 )->andReturn( 456 );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->twice()
			->with( 456 )
			->andReturn( $customer );

		// Mock TokenService.
		$this->get_token_service()
			->shouldReceive( 'payment_token_exists' )
			->once()
			->with( 456, 'token_123' )
			->andReturn( false );

		// Mock Card.
		$card = Mockery::mock( Card::class );
		$card->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$card->shouldReceive( 'get_card_data' )->once()->andReturn( $this->get_test_card_data( 'valid' ) );
		$card->shouldReceive( 'is_active' )->once()->andReturn( true );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andReturn( $card );

		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_token' )->once()->with( 'token_123' );
		$token->shouldReceive( 'set_gateway_id' )->once();
		$token->shouldReceive( 'set_card_type' )->once()->with( 'visa' );
		$token->shouldReceive( 'set_last4' )->once()->with( '1234' );
		$token->shouldReceive( 'set_expiry_month' )->once()->with( '06' );
		$token->shouldReceive( 'set_expiry_year' )->once()->with( '2025' );
		$token->shouldReceive( 'set_user_id' )->once()->with( 456 );
		$token->shouldReceive( 'validate' )->once()->andReturn( true );
		$token->shouldReceive( 'save' )->once();

		// Mock TokenFactory.
		$this->get_token_factory()
			->shouldReceive( 'get_wc_payment_token' )
			->once()
			->andReturn( $token );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->times( 4 )
			->withArgs(
				function( $message, $level ) {
					return in_array(
						$message,
						[
							'Customer found successfully from scheduled webhook data. User ID: 456.',
							'User found successfully from incoming webhook data. User ID: 456.',
							'Payment method found successfully from incoming webhook data. User ID: 456.',
							'Payment method saved successfully from incoming webhook data. User ID: 456.',
						],
						true
					) && 'debug' === $level;
				}
			);

		// Test the method.
		$this->service->process_scheduled_save_payment_method( $webhook );
	}

	/**
	 * Test process_scheduled_save_payment_method when token exists.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::process_scheduled_save_payment_method
	 * @return void
	 */
	public function test_process_scheduled_save_payment_method_success_token_exists(): void {
		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->once()->andReturn( '456-add_payment_method_key' );
		$webhook->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$webhook->shouldReceive( 'get_log_data' )->times( 2 )->andReturn( [] );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->times( 3 )->andReturn( 456 );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( 456 )
			->andReturn( $customer );

		// Mock TokenService.
		$this->get_token_service()
			->shouldReceive( 'payment_token_exists' )
			->once()
			->with( 456, 'token_123' )
			->andReturn( true );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->times( 2 )
			->withArgs(
				function( $message, $level ) {
					return in_array(
						$message,
						[
							'Customer found successfully from scheduled webhook data. User ID: 456.',
							'Skipping payment method saving. Payment method already saved from redirect data. User ID: 456.',
						],
						true
					) && 'debug' === $level;
				}
			);

		// Test the method.
		$this->service->process_scheduled_save_payment_method( $webhook );
	}

	/**
	 * Test process_scheduled_save_payment_method throws exception when fails.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::process_scheduled_save_payment_method
	 * @return void
	 */
	public function test_process_scheduled_save_payment_method_throws_exception_when_fails(): void {
		// Mock WebhookData.
		$webhook = Mockery::mock( WebhookData::class );
		$webhook->shouldReceive( 'get_order_id' )->once()->andReturn( 'invalid_order_id' );
		$webhook->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with(
				'Error saving payment method from scheduled webhook data. Error: "No valid customer ID in incoming data.".',
				'error',
				[]
			);

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No valid customer ID in incoming data.' );
		$this->service->process_scheduled_save_payment_method( $webhook, 'test_hash' );
	}

	/**
	 * Test get_payment_method_for_checkout success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_method_for_checkout
	 * @return void
	 */
	public function test_get_payment_method_for_checkout_success(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_user_id' )->twice()->andReturn( 456 );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 789 );

		// Mock POST data.
		$_POST['wc-acfw-payment-token'] = 123;

		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'get_user_id' )->once()->andReturn( 456 );
		$token->shouldReceive( 'get_token' )->once()->andReturn( 'token_123' );

		// Mock TokenService.
		$this->get_token_service()
			->shouldReceive( 'get_token' )
			->once()
			->with( 123 )
			->andReturn( $token );

		// Mock Card.
		$card = Mockery::mock( Card::class );
		$card->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$card->shouldReceive( 'is_active' )->once()->andReturn( true );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andReturn( $card );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment method retrieval for checkout successful. Order ID: 789.', 'debug' );

		// Test the method.
		$this->assertEquals( 'token_123', $this->service->get_payment_method_for_checkout( $order ) );
	}

	/**
	 * Test get_payment_method_for_checkout returns null for invalid token.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_method_for_checkout
	 * @return void
	 */
	public function test_get_payment_method_for_checkout_returns_null_for_invalid_token(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_user_id' )->twice()->andReturn( 456 );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 789 );

		// Mock POST data.
		$_POST['wc-acfw-payment-token'] = 123;

		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'get_user_id' )->once()->andReturn( 666 );

		// Mock TokenService.
		$this->get_token_service()
			->shouldReceive( 'get_token' )
			->once()
			->with( 123 )
			->andReturn( $token );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment method retrieval failed. User token invalid. Order ID: 789.', 'error' );

		// Test the method.
		$this->assertNull( $this->service->get_payment_method_for_checkout( $order ) );
	}

	/**
	 * Test get_payment_method_for_checkout returns null for exception.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_method_for_checkout
	 * @return void
	 */
	public function test_get_payment_method_for_checkout_returns_null_for_exception(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_user_id' )->twice()->andReturn( 456 );
		$order->shouldReceive( 'get_id' )->once()->andReturn( 789 );

		// Mock POST data.
		$_POST['wc-acfw-payment-token'] = 123;

		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'get_user_id' )->once()->andReturn( 456 );
		$token->shouldReceive( 'get_token' )->once()->andReturn( 'token_123' );

		// Mock TokenService.
		$this->get_token_service()
			->shouldReceive( 'get_token' )
			->once()
			->with( 123 )
			->andReturn( $token );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andThrow( new Exception( 'Card is not active.' ) );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment method retrieval for checkout failed. Order ID: 789. Error: "Card is not active.".', 'error' );

		// Test the method.
		$this->assertNull( $this->service->get_payment_method_for_checkout( $order ) );
	}

	/**
	 * Test get_payment_method_for_checkout returns null for guest user.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_method_for_checkout
	 * @return void
	 */
	public function test_get_payment_method_for_checkout_returns_null_for_guest(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_user_id' )->once()->andReturn( 0 );

		// Test the method.
		$this->assertNull( $this->service->get_payment_method_for_checkout( $order ) );
	}

	/**
	 * Test get_payment_method_for_checkout returns null when no token in POST.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_method_for_checkout
	 * @return void
	 */
	public function test_get_payment_method_for_checkout_returns_null_without_token(): void {
		// Mock WC_Order.
		$order = Mockery::mock( 'WC_Order' );
		$order->shouldReceive( 'get_user_id' )->once()->andReturn( 123 );

		// Test the method.
		$this->assertNull( $this->service->get_payment_method_for_checkout( $order ) );
	}

	/**
	 * Test get_payment_link_body with customer data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_link_body
	 * @return void
	 */
	public function test_get_payment_link_body_with_customer_data(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->twice()->andReturn( 456 );

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

		Functions\expect( 'wp_generate_password' )
			->once()
			->with( 13, false )
			->andReturn( 'random123abcdef' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'redirect-new-payment-method' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-payment-method' );

		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'webhook' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook' );

		$this->get_settings_service()
			->shouldReceive( 'get_payment_link_expiration_time' )
			->once()
			->andReturn( 3600 );

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_data_for_new_payment_method' )
			->once()
			->with( 456 )
			->andReturn( [ 'customer_id' => '789' ] );

		// Set expected response.
		$expected = [
			'transaction'     => [
				'currency' => 'GBP',
				'custom1'  => '2.0.0',
				'order_id' => '456-add_payment_method_random123abcdef',
				'amount'   => 0,
				'capture'  => false,
			],
			'payment'         => [
				'reference' => 'Test Store',
			],
			'count_retry'     => 1,
			'redirect_url'    => 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-payment-method',
			'webhook_url'     => 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook',
			'submit_type'     => 'register',
			'expires_in'      => 3600,
			'is_recurring'    => true,
			'payment_methods' => [ 'card' ],
			'customer'        => [ 'customer_id' => '789' ],
		];

		$this->assertEquals( $expected, $this->get_private_method_value( 'get_payment_link_body', $customer ) );
	}

	/**
	 * Test get_payment_link_body without customer data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_link_body
	 * @return void
	 */
	public function test_get_payment_link_body_without_customer_data(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->twice()->andReturn( 456 );

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

		Functions\expect( 'wp_generate_password' )
			->once()
			->with( 13, false )
			->andReturn( 'random123abcdef' );

		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'redirect-new-payment-method' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-payment-method' );

		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'webhook' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook' );

		$this->get_settings_service()
			->shouldReceive( 'get_payment_link_expiration_time' )
			->once()
			->andReturn( 3600 );

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_data_for_new_payment_method' )
			->once()
			->with( 456 )
			->andReturn( [] );

		// Set expected response.
		$expected = [
			'transaction'     => [
				'currency' => 'GBP',
				'custom1'  => '2.0.0',
				'order_id' => '456-add_payment_method_random123abcdef',
				'amount'   => 0,
				'capture'  => false,
			],
			'payment'         => [
				'reference' => 'Test Store',
			],
			'count_retry'     => 1,
			'redirect_url'    => 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-payment-method',
			'webhook_url'     => 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook',
			'submit_type'     => 'register',
			'expires_in'      => 3600,
			'is_recurring'    => true,
			'payment_methods' => [ 'card' ],
		];

		$this->assertEquals( $expected, $this->get_private_method_value( 'get_payment_link_body', $customer ) );
	}

	/**
	 * Test get_payment_link success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_link
	 * @return void
	 */
	public function test_get_payment_link_success(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->twice()->andReturn( 456 );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( 456 )
			->andReturn( $customer );

		// Mock PaymentLink.
		$response = Mockery::mock( PaymentLink::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( true );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );
		$response->shouldReceive( 'get_link_id' )->once()->andReturn( 'test-link-id' );

		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'redirect-new-payment-method' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-payment-method' );

		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'webhook' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook' );

		$this->get_settings_service()
			->shouldReceive( 'get_payment_link_expiration_time' )
			->once()
			->andReturn( 3600 );

		$this->get_settings_service()
			->shouldReceive( 'get_pay_url' )
			->once()
			->andReturn( 'https://pay.acquired.com/v1/' );

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_data_for_new_payment_method' )
			->once()
			->with( 456 )
			->andReturn( [ 'customer_id' => '789' ] );

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

		Functions\expect( 'wp_generate_password' )
			->once()
			->with( 13, false )
			->andReturn( 'random123abcdef' );

		$this->get_api_client()
			->shouldReceive( 'get_payment_link' )
			->with(
				[
					'transaction'     => [
						'currency' => 'GBP',
						'custom1'  => '2.0.0',
						'order_id' => '456-add_payment_method_random123abcdef',
						'amount'   => 0,
						'capture'  => false,
					],
					'payment'         => [
						'reference' => 'Test Store',
					],
					'count_retry'     => 1,
					'redirect_url'    => 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-payment-method',
					'webhook_url'     => 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook',
					'submit_type'     => 'register',
					'expires_in'      => 3600,
					'is_recurring'    => true,
					'payment_methods' => [ 'card' ],
					'customer'        => [ 'customer_id' => '789' ],
				]
			)
			->once()
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment link created successfully. User ID: 456.', 'debug', [] );

		// Test the method.
		$this->assertEquals( 'https://pay.acquired.com/v1/test-link-id', $this->service->get_payment_link( 456 ) );
	}

	/**
	 * Test get_payment_link throws exception when user ID is not set.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_link
	 * @return void
	 */
	public function test_get_payment_link_throws_exception_when_user_id_not_set(): void {
		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment link creation failed. User ID is not set.', 'error' );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment link creation failed. User ID is not set.' );
		$this->service->get_payment_link( 0 );
	}

	/**
	 * Test get_payment_link throws exception when customer not found.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_link
	 * @return void
	 */
	public function test_get_payment_link_throws_exception_when_customer_not_found(): void {
		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( 456 )
			->andReturn( null );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment link creation failed. Customer not found. User ID: 456.', 'error' );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment link creation failed. Customer not found. User ID: 456.' );
		$this->service->get_payment_link( 456 );
	}

	/**
	 * Test get_payment_link throws exception when API request fails.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_payment_link
	 * @return void
	 */
	public function test_get_payment_link_throws_exception_when_request_fails(): void {
		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->twice()->andReturn( 456 );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( 456 )
			->andReturn( $customer );

		// Mock PaymentLink.
		$response = Mockery::mock( PaymentLink::class );
		$response->shouldReceive( 'request_is_success' )->once()->andReturn( false );
		$response->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock SettingsService.

		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'redirect-new-payment-method' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-payment-method' );

		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'webhook' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook' );

		$this->get_settings_service()
			->shouldReceive( 'get_payment_link_expiration_time' )
			->once()
			->andReturn( 3600 );

		// Mock CustomerService.
		$this->get_customer_service()
			->shouldReceive( 'get_customer_data_for_new_payment_method' )
			->once()
			->with( 456 )
			->andReturn( [ 'customer_id' => '789' ] );

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

		Functions\expect( 'wp_generate_password' )
			->once()
			->with( 13, false )
			->andReturn( 'random123abcdef' );

		$this->get_api_client()
			->shouldReceive( 'get_payment_link' )
			->with(
				[
					'transaction'     => [
						'currency' => 'GBP',
						'custom1'  => '2.0.0',
						'order_id' => '456-add_payment_method_random123abcdef',
						'amount'   => 0,
						'capture'  => false,
					],
					'payment'         => [
						'reference' => 'Test Store',
					],
					'count_retry'     => 1,
					'redirect_url'    => 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-payment-method',
					'webhook_url'     => 'https://example.com/wc-api/acquired-com-for-woocommerce-webhook',
					'submit_type'     => 'register',
					'expires_in'      => 3600,
					'is_recurring'    => true,
					'payment_methods' => [ 'card' ],
					'customer'        => [ 'customer_id' => '789' ],
				]
			)
			->once()
			->andReturn( $response );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Payment link creation failed. User ID: 456.', 'error', [] );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Payment link creation failed.' );
		$this->service->get_payment_link( 456 );
	}

	/**
	 * Test confirm_payment_method success.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::confirm_payment_method
	 * @return void
	 */
	public function test_confirm_payment_method_success(): void {
		// Mock tokenization setting.
		$this->mock_tokenization_setting( true );

		// Mock RedirectData.
		$redirect_data = Mockery::mock( RedirectData::class );
		$redirect_data->shouldReceive( 'is_hash_valid' )->once()->andReturn( true );
		$redirect_data->shouldReceive( 'get_order_id' )->twice()->andReturn( '456-add_payment_method_key' );
		$redirect_data->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$redirect_data->shouldReceive( 'set_card_id' )->once()->with( 'token_123' );
		$redirect_data->shouldReceive( 'get_card_id' )->twice()->andReturn( 'token_123' );
		$redirect_data->shouldReceive( 'get_type' )->times( 3 )->andReturn( 'redirect' );
		$redirect_data->shouldReceive( 'get_log_data' )->times( 3 )->andReturn( [] );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->times( 5 )->andReturn( 456 );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->twice()
			->with( 456 )
			->andReturn( $customer );

		// Mock TokenService.
		$this->get_token_service()
			->shouldReceive( 'payment_token_exists' )
			->once()
			->with( 456, 'token_123' )
			->andReturn( false );

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );
		$transaction->shouldReceive( 'get_card_id' )->twice()->andReturn( 'token_123' );

		// Mock Card.
		$card = Mockery::mock( Card::class );
		$card->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );
		$card->shouldReceive( 'get_card_data' )->once()->andReturn( $this->get_test_card_data( 'valid' ) );
		$card->shouldReceive( 'is_active' )->once()->andReturn( true );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( 'transaction_123' )
			->andReturn( $transaction );

		$this->get_api_client()
			->shouldReceive( 'get_card' )
			->once()
			->with( 'token_123' )
			->andReturn( $card );

		// Mock WC_Payment_Token_CC.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );
		$token->shouldReceive( 'set_token' )->once()->with( 'token_123' );
		$token->shouldReceive( 'set_gateway_id' )->once();
		$token->shouldReceive( 'set_card_type' )->once()->with( 'visa' );
		$token->shouldReceive( 'set_last4' )->once()->with( '1234' );
		$token->shouldReceive( 'set_expiry_month' )->once()->with( '06' );
		$token->shouldReceive( 'set_expiry_year' )->once()->with( '2025' );
		$token->shouldReceive( 'set_user_id' )->once()->with( 456 );
		$token->shouldReceive( 'validate' )->once()->andReturn( true );
		$token->shouldReceive( 'save' )->once();

		// Mock TokenFactory.
		$this->get_token_factory()
			->shouldReceive( 'get_wc_payment_token' )
			->once()
			->andReturn( $token );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->times( 3 )
			->withArgs(
				function( $message, $level ) {
					return in_array(
						$message,
						[
							'User found successfully from incoming redirect data. User ID: 456.',
							'Payment method found successfully from incoming redirect data. User ID: 456.',
							'Payment method saved successfully from incoming redirect data. User ID: 456.',
						],
						true
					) && 'debug' === $level;
				}
			);

		// Test the method.
		$this->assertInstanceOf( 'WC_Customer', $this->service->confirm_payment_method( $redirect_data ) );
	}

	/**
	 * Test confirm_payment_method when token already exists.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::confirm_payment_method
	 * @return void
	 */
	public function test_confirm_payment_method_token_exists(): void {
		// Mock RedirectData.
		$redirect_data = Mockery::mock( RedirectData::class );
		$redirect_data->shouldReceive( 'is_hash_valid' )->once()->andReturn( true );
		$redirect_data->shouldReceive( 'get_order_id' )->once()->andReturn( '456-add_payment_method_key' );
		$redirect_data->shouldReceive( 'get_transaction_id' )->once()->andReturn( 'transaction_123' );
		$redirect_data->shouldReceive( 'set_card_id' )->once()->with( 'token_123' );
		$redirect_data->shouldReceive( 'get_card_id' )->once()->andReturn( 'token_123' );

		// Mock WC_Customer.
		$customer = Mockery::mock( 'WC_Customer' );
		$customer->shouldReceive( 'get_id' )->once()->andReturn( 456 );

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->once()
			->with( 456 )
			->andReturn( $customer );

		// Mock TokenService.
		$this->get_token_service()
			->shouldReceive( 'payment_token_exists' )
			->once()
			->with( 456, 'token_123' )
			->andReturn( true );

		// Mock Transaction.
		$transaction = Mockery::mock( Transaction::class );
		$transaction->shouldReceive( 'request_is_error' )->once()->andReturn( false );
		$transaction->shouldReceive( 'get_card_id' )->twice()->andReturn( 'token_123' );

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->once()
			->with( 'transaction_123' )
			->andReturn( $transaction );

		// Test the method.
		$this->assertInstanceOf( 'WC_Customer', $this->service->confirm_payment_method( $redirect_data ) );
	}

	/**
	 * Test confirm_payment_method failure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::confirm_payment_method
	 * @return void
	 */
	public function test_confirm_payment_method_failure(): void {
		// Mock RedirectData.
		$redirect_data = Mockery::mock( RedirectData::class );
		$redirect_data->shouldReceive( 'is_hash_valid' )->once()->andReturn( true );
		$redirect_data->shouldReceive( 'get_order_id' )->once()->andReturn( 'invalid_order_id' );
		$redirect_data->shouldReceive( 'get_log_data' )->once()->andReturn( [] );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Error saving payment method from incoming redirect data. Error: "No valid customer ID in incoming data.".', 'error', [] );

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'No valid customer ID in incoming data.' );
		$this->service->confirm_payment_method( $redirect_data );
	}

	/**
	 * Test confirm_payment_method rejects redirect data with an unverified hash.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::confirm_payment_method
	 * @return void
	 */
	public function test_confirm_payment_method_unverified_hash(): void {
		// Mock RedirectData.
		$redirect_data = Mockery::mock( RedirectData::class );
		$redirect_data->shouldReceive( 'is_hash_valid' )->once()->andReturn( false );
		$redirect_data->shouldReceive( 'get_hash_status' )->once()->andReturn( 'invalid' );
		$redirect_data->shouldReceive( 'get_log_data' )->never();
		$redirect_data->shouldReceive( 'get_order_id' )->never();

		// Mock CustomerFactory.
		$this->get_customer_factory()
			->shouldReceive( 'get_wc_customer' )
			->never();

		// Mock ApiClient.
		$this->get_api_client()
			->shouldReceive( 'get_transaction' )
			->never();

		// Mock TokenService.
		$this->get_token_service()
			->shouldReceive( 'payment_token_exists' )
			->never();

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->never();

		// Test the method.
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Redirect data hash is not valid. Hash status: invalid.' );
		$this->service->confirm_payment_method( $redirect_data );
	}

	/**
	 * Test get_notice_data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\PaymentMethodService::get_notice_data
	 * @return void
	 */
	public function test_get_notice_data(): void {
		// Test success message.
		$expected = [
			'message' => 'Payment method successfully added.',
			'type'    => 'success',
		];
		$this->assertEquals( $expected, $this->service->get_notice_data( 'success' ) );

		// Test error message.
		$expected = [
			'message' => 'Unable to add payment method to your account.',
			'type'    => 'error',
		];
		$this->assertEquals( $expected, $this->service->get_notice_data( 'error' ) );

		// Test blocked message.
		$expected = [
			'message' => 'Your payment method was blocked.',
			'type'    => 'error',
		];
		$this->assertEquals( $expected, $this->service->get_notice_data( 'blocked' ) );

		// Test TDS error messages.
		$expected = [
			'message' => 'Your payment method has been declined due to failed authentication with your bank.',
			'type'    => 'error',
		];
		$this->assertEquals( $expected, $this->service->get_notice_data( 'tds_error' ) );
		$this->assertEquals( $expected, $this->service->get_notice_data( 'tds_expired' ) );
		$this->assertEquals( $expected, $this->service->get_notice_data( 'tds_failed' ) );

		// Test default error message.
		$expected = [
			'message' => 'Your payment method was declined.',
			'type'    => 'error',
		];
		$this->assertEquals( $expected, $this->service->get_notice_data( 'unknown_status' ) );
	}
}
