<?php
/**
 * PaymentMethodObserverTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Observers;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Observers\PaymentMethodObserver;
use AcquiredComForWooCommerce\Tests\Framework\Traits\IncomingDataHandlerMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\LoggerServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\PaymentMethodServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Api\IncomingData\WebhookData;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use Mockery;
use Exception;

/**
 * Test case for PaymentMethodObserver.
 *
 * @covers AcquiredComForWooCommerce\Observers\PaymentMethodObserver
 */
class PaymentMethodObserverTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use IncomingDataHandlerMock;
	use LoggerServiceMock;
	use PaymentMethodServiceMock;

	/**
	 * Test class.
	 *
	 * @var PaymentMethodObserver
	 */
	private PaymentMethodObserver $test_class;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		// Clear $_GET before each test.
		$_GET = [];

		$this->mock_incoming_data_handler();
		$this->mock_logger_service();
		$this->mock_payment_method_service();

		$this->test_class = new PaymentMethodObserver(
			$this->get_incoming_data_handler(),
			$this->get_logger_service(),
			$this->get_payment_method_service()
		);

		$this->initialize_reflection( $this->test_class );
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
	 * Test constructor.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\PaymentMethodObserver::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertSame( $this->get_incoming_data_handler(), $this->get_private_property_value( 'incoming_data_handler' ) );
		$this->assertSame( $this->get_logger_service(), $this->get_private_property_value( 'logger_service' ) );
		$this->assertSame( $this->get_payment_method_service(), $this->get_private_property_value( 'payment_method_service' ) );
	}

	/**
	 * Test init_hooks.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\PaymentMethodObserver::init_hooks
	 * @return void
	 */
	public function test_init_hooks(): void {
		// Test template_redirect action.
		Actions\expectAdded( 'template_redirect' )
			->once()
			->whenHappen(
				function ( $callback ) {
					$this->assertSame( $this->test_class, $callback[0] );
					$this->assertEquals( 'add_notice', $callback[1] );
				}
			);

		// Test woocommerce_payment_token_deleted action.
		Actions\expectAdded( 'woocommerce_payment_token_deleted' )
			->once()
			->whenHappen(
				function ( $callback, $priority, $accepted_args ) {
					$this->assertSame( $this->test_class, $callback[0] );
					$this->assertEquals( 'payment_token_deleted', $callback[1] );
					$this->assertEquals( 2, $accepted_args );
				}
			);

		// Test get_scheduled_action_hook action.

		$this->get_payment_method_service()
			->shouldReceive( 'get_scheduled_action_hook' )
			->once()
			->andReturn( 'acfw_scheduled_save_payment_method' );

		Actions\expectAdded( 'acfw_scheduled_save_payment_method' )
			->once()
			->whenHappen(
				function ( $callback, $priority, $accepted_args ) {
					$this->assertSame( $this->test_class, $callback[0] );
					$this->assertEquals( 'run_process_scheduled_save_payment_method', $callback[1] );
					$this->assertEquals( 2, $accepted_args );
				}
			);

		// Test the method.
		$this->test_class->init_hooks();
	}

	/**
	 * Test payment_token_deleted.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\PaymentMethodObserver::payment_token_deleted
	 * @return void
	 */
	public function test_payment_token_deleted(): void {
		$token_id = 123;

		// Mock WC_Payment_Token.
		$token = Mockery::mock( 'WC_Payment_Token_CC' );

		// Mock PaymentMethodService.
		$this->get_payment_method_service()
			->shouldReceive( 'deactivate_card' )
			->once()
			->with( $token );

		// Test the method.
		$this->test_class->payment_token_deleted( $token_id, $token );
	}

	/**
	 * Test add_notice with notice data.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\PaymentMethodObserver::add_notice
	 * @return void
	 */
	public function test_add_notice_with_data(): void {
		// Mock is_add_payment_method_page to return true.
		Functions\expect( 'is_add_payment_method_page' )
			->once()
			->andReturn( true );

		// Mock status key.
		$this->get_payment_method_service()
			->shouldReceive( 'get_status_key' )
			->twice()
			->andReturn( 'acfw_payment_method_status' );

		// Mock $_GET data.
		$_GET['acfw_payment_method_status'] = 'success';

		// Mock WordPress wp_unslash function.
		Functions\expect( 'wp_unslash' )
			->once()
			->with( 'success' )
			->andReturn( 'success' );

		// Mock WordPress sanitize_text_field function.
		Functions\expect( 'sanitize_text_field' )
			->once()
			->with( 'success' )
			->andReturn( 'success' );

		// Mock notice data.
		$notice_data = [
			'message' => 'Payment method added successfully',
			'type'    => 'success',
		];

		// Mock PaymentMethodService.
		$this->get_payment_method_service()
			->shouldReceive( 'get_notice_data' )
			->once()
			->with( 'success' )
			->andReturn( $notice_data );

		// Mock WooCommerce notice function.
		Functions\expect( 'wc_add_notice' )
			->once()
			->with( $notice_data['message'], $notice_data['type'] );

		// Test the method.
		$this->test_class->add_notice();
	}

	/**
	 * Test add_notice when not on add payment method page.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\PaymentMethodObserver::add_notice
	 * @return void
	 */
	public function test_add_notice_not_payment_page(): void {
		// Mock is_add_payment_method_page to return false.
		Functions\expect( 'is_add_payment_method_page' )
			->once()
			->andReturn( false );

		// Methods should not be called.
		$this->get_payment_method_service()->shouldNotReceive( 'get_status_key' );
		$this->get_payment_method_service()->shouldNotReceive( 'get_notice_data' );

		// Test the method.
		$this->test_class->add_notice();
	}

	/**
	 * Test add_notice without notice data.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\PaymentMethodObserver::add_notice
	 * @return void
	 */
	public function test_add_notice_without_notice_data(): void {
		// Mock is_add_payment_method_page to return true.
		Functions\expect( 'is_add_payment_method_page' )
			->once()
			->andReturn( true );

		// Mock PaymentMethodService.
		$this->get_payment_method_service()
			->shouldReceive( 'get_status_key' )
			->once()
			->andReturn( 'payment_status' );

		// Empty the $_GET.
		$_GET = [];

		// WooCommerce notice function should not be called.
		Functions\expect( 'wc_add_notice' )->never();

		// Test the method.
		$this->test_class->add_notice();
	}

	/**
	 * Test run_process_scheduled_save_payment_method success.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\PaymentMethodObserver::run_process_scheduled_save_payment_method
	 * @return void
	 */
	public function test_run_process_scheduled_save_payment_method_success(): void {
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

		// Mock process_scheduled_save_payment_method.
		$this->get_payment_method_service()
			->shouldReceive( 'process_scheduled_save_payment_method' )
			->once()
			->with( $webhook );

		// Test the method.
		$this->test_class->run_process_scheduled_save_payment_method( $webhook_data, $hash );
	}

	/**
	 * Test run_process_scheduled_order failure.
	 *
	 * @covers AcquiredComForWooCommerce\Observers\PaymentMethodObserver::run_process_scheduled_save_payment_method
	 * @return void
	 */
	public function test_run_process_scheduled_save_payment_method_failure(): void {
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
			->with( 'Scheduled saving payment method failed.', 'error' );

		// Test the method.
		$this->test_class->run_process_scheduled_save_payment_method( $webhook_data, $hash );
	}
}
