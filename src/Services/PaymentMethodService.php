<?php
/**
 * PaymentMethodService.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Services;

use AcquiredComForWooCommerce\Api\ApiClient;
use AcquiredComForWooCommerce\Api\IncomingData\RedirectData;
use AcquiredComForWooCommerce\Api\IncomingData\WebhookData;
use AcquiredComForWooCommerce\Api\Response\Card;
use AcquiredComForWooCommerce\Factories\CustomerFactory;
use AcquiredComForWooCommerce\Factories\TokenFactory;
use AcquiredComForWooCommerce\Traits\PaymentLink;
use stdClass;
use Exception;
use WC_Order;
use WC_Customer;
use WC_Payment_Token_CC;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * PaymentMethodService class.
 */
class PaymentMethodService {
	/**
	 * Traits.
	 */
	use PaymentLink;

	/**
	 * Constructor.
	 *
	 * @param ApiClient $api_client
	 * @param CustomerService $customer_service
	 * @param LoggerService $logger_service
	 * @param ScheduleService $schedule_service
	 * @param SettingsService $settings_service
	 * @param TokenService $token_service
	 * @param CustomerFactory $customer_factory
	 * @param TokenFactory $token_factory
	 */
	public function __construct(
		private ApiClient $api_client,
		private CustomerService $customer_service,
		private LoggerService $logger_service,
		private ScheduleService $schedule_service,
		private SettingsService $settings_service,
		private TokenService $token_service,
		private CustomerFactory $customer_factory,
		private TokenFactory $token_factory,
	) {}

	/**
	 * Check if transaction is success.
	 *
	 * @param string $status
	 * @return bool
	 */
	public function is_transaction_success( string $status ): bool {
		return in_array( $status, [ 'success', 'settled', 'executed' ], true );
	}

	/**
	 * Get transaction error key.
	 *
	 * @return string
	 */
	public function get_status_key(): string {
		return $this->settings_service->config['plugin_id'] . '_payment_method_status';
	}

	/**
	 * Get scheduled action hook.
	 *
	 * @return string
	 */
	public function get_scheduled_action_hook(): string {
		return $this->settings_service->config['plugin_id'] . '_scheduled_save_payment_method';
	}

	/**
	 * Set token card data.
	 *
	 * @param WC_Payment_Token_CC $token
	 * @param stdClass $card_data
	 * @return void
	 */
	private function set_token_card_data( WC_Payment_Token_CC $token, stdClass $card_data ): void {
		$token->set_card_type( $card_data->scheme );
		// We have to convert all numbers to strings because the WC_Payment_Token_CC class expects them to be strings.
		$token->set_last4( (string) $card_data->number );
		$token->set_expiry_month( (string) $card_data->expiry_month );
		$token->set_expiry_year( (string) ( 2000 + $card_data->expiry_year ) ); // WC_Payment_Token_CC expects the year to be 4 digits.
	}

	/**
	 * Create token.
	 *
	 * @param string $card_id
	 * @param stdClass $card_data
	 * @param int $user_id
	 * @param WC_Order|null $order
	 * @return void
	 * @throws Exception
	 */
	private function create_token( string $card_id, stdClass $card_data, int $user_id, WC_Order|null $order = null ): void {
		$token = $this->token_factory->get_wc_payment_token();

		$token->set_token( $card_id );
		$this->set_token_card_data( $token, $card_data );
		$token->set_gateway_id( $this->settings_service->config['plugin_id'] );
		$token->set_user_id( $user_id );

		if ( ! $token->validate() ) {
			throw new Exception( 'Failed to validate token.' );
		} else {
			$token->save();
			if ( $order ) {
				$order->add_payment_token( $token );
				$order->save();
			}
		}
	}

	/**
	 * Update token.
	 *
	 * @param WC_Payment_Token_CC $token
	 * @param stdClass $card_data
	 * @return void
	 * @throws Exception
	 */
	private function update_token( WC_Payment_Token_CC $token, stdClass $card_data ): void {
		$this->set_token_card_data( $token, $card_data );

		if ( ! $token->validate() ) {
			throw new Exception( 'Failed to validate token.' );
		} else {
			$token->save();
		}
	}

	/**
	 * Get card.
	 *
	 * @param string $card_id
	 * @return Card
	 * @throws Exception
	 */
	private function get_card( string $card_id ): Card {
		$response = $this->api_client->get_card( $card_id );

		if ( $response->is_active() ) {
			return $response;
		} elseif ( $response->request_is_error() ) {
			throw new Exception( 'Card retrieval failed.' );
		} else {
			throw new Exception( 'Card is not active.' );
		}
	}

	/**
	 * Get card ID from transaction.
	 *
	 * @param string $transaction_id
	 * @return string|null
	 * @throws Exception
	 */
	private function get_card_id_from_transaction( string $transaction_id ): ?string {
		$response = $this->api_client->get_transaction( $transaction_id );

		if ( $response->request_is_error() ) {
			throw new Exception( 'Card ID retrieval failed.' );
		} elseif ( $response->get_card_id() ) {
			return $response->get_card_id();
		} else {
			throw new Exception( 'Card ID not found.' );
		}
	}

	/**
	 * Deactivate card.
	 *
	 * @param WC_Payment_Token_CC $token
	 * @return void
	 */
	public function deactivate_card( WC_Payment_Token_CC $token ): void {
		$response = $this->api_client->update_card( $token->get_token(), [ 'is_active' => false ] );

		if ( $response->request_is_success() ) {
			$this->logger_service->log( 'Payment method deletion successful.', 'debug', $response->get_log_data() );
		} else {
			$this->logger_service->log( 'Payment method deletion failed.', 'error', $response->get_log_data() );
		}
	}

	/**
	 * Process payment method.
	 *
	 * @param string $operation
	 * @param callable $process
	 * @param RedirectData|WebhookData $data
	 * @return void
	 * @throws Exception
	 */
	private function process_payment_method( string $operation, callable $process, RedirectData|WebhookData $data ): void {
		if ( ! $this->settings_service->is_enabled( 'tokenization' ) ) {
			$error = sprintf( 'Payment method %s failed. Tokenization is disabled.', $operation );
			$this->logger_service->log( $error, 'error', $data->get_log_data() );
			throw new Exception( $error );
		}

		try {
			$process(
				$data,
				function( string $message ) use ( $data ) {
					$this->logger_service->log( $message, 'debug', $data->get_log_data() );
				}
			);
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log(
				sprintf( 'Payment method %s failed. %s', $operation, $error ),
				'error',
				$data->get_log_data()
			);
			throw new Exception( $error );
		}
	}

	/**
	 * Schedule save payment method.
	 *
	 * @param WebhookData $data
	 * @param string $hash
	 * @return void
	 * @throws Exception
	 */
	public function schedule_save_payment_method( WebhookData $data, string $hash ): void {
		try {
			$customer = $this->get_wc_customer_from_incoming_data( $data->get_order_id() );

			$this->schedule_service->schedule(
				$this->get_scheduled_action_hook(),
				[
					'webhook_data' => wp_json_encode( $data->get_incoming_data() ),
					'hash'         => $hash,
				]
			);

			$this->logger_service->log( sprintf( 'Save payment method scheduled successfully from incoming webhook data. User ID: %s.', $customer->get_id() ), 'debug', $data->get_log_data() );
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log( sprintf( 'Error scheduling save payment method from incoming webhook data. Error: "%s".', $error ), 'error', $data->get_log_data() );
			throw new Exception( $error );
		}
	}

	/**
	 * Save payment method from customer.
	 *
	 * @param RedirectData|WebhookData $data
	 * @return void
	 * @throws Exception
	 */
	public function save_payment_method_from_customer( RedirectData|WebhookData $data ): void {
		$this->process_payment_method(
			'saving',
			function( RedirectData|WebhookData $data, callable $log ): WC_Customer {
				$customer = $this->get_wc_customer_from_incoming_data( $data->get_order_id() );
				$log( sprintf( 'User found successfully from incoming %s data. User ID: %s.', $data->get_type(), $customer->get_id() ) );

				$card = $this->get_card( $data->get_card_id() );
				$log( sprintf( 'Payment method found successfully from incoming %s data. User ID: %s.', $data->get_type(), $customer->get_id() ) );

				$this->create_token( $card->get_card_id(), $card->get_card_data(), $customer->get_id() );
				$log( sprintf( 'Payment method saved successfully from incoming %s data. User ID: %s.', $data->get_type(), $customer->get_id() ) );

				return $customer;
			},
			$data
		);
	}

	/**
	 * Save new payment method from order.
	 *
	 * @param WebhookData $data
	 * @return void
	 * @throws Exception
	 */
	public function save_payment_method_from_order( WebhookData $data ): void {
		$this->process_payment_method(
			'saving',
			function( WebhookData $data, callable $log ): WC_Order {
				$order = $this->get_wc_order_from_incoming_data( $data->get_order_id() );
				$log( sprintf( 'Order found successfully from incoming webhook data. Order ID: %s.', $order->get_id() ) );

				$card = $this->get_card( $data->get_card_id() );
				$log( sprintf( 'Payment method found successfully from incoming webhook data. Order ID: %s.', $order->get_id() ) );

				$this->create_token( $card->get_card_id(), $card->get_card_data(), $order->get_user_id(), $order );
				$log( sprintf( 'Payment method saved successfully from incoming webhook data. Order ID: %s.', $order->get_id() ) );

				return $order;
			},
			$data
		);
	}

	/**
	 * Update payment method.
	 *
	 * @param WebhookData $data
	 * @return void
	 * @throws Exception
	 */
	public function update_payment_method( WebhookData $data ): void {
		$this->process_payment_method(
			'updating',
			function( WebhookData $data, callable $log ): WC_Customer {
				$card = $this->get_card( $data->get_card_id() );
				$log( 'Payment method found successfully from incoming webhook data.' );

				$customer = $this->customer_service->get_customer_from_customer_id( $card->get_customer_id() );
				$log( sprintf( 'User found successfully from incoming webhook data. User ID: %s.', $customer->get_id() ) );

				$token = $this->token_service->get_token_by_user_and_card_id( $customer->get_id(), $card->get_card_id() );
				$this->update_token( $token, $card->get_card_data() );
				$log( sprintf( 'Payment method updated successfully. User ID: %s.', $customer->get_id() ) );

				return $customer;
			},
			$data
		);
	}

	/**
	 * Process scheduled save payment method.
	 *
	 * @param WebhookData $data
	 * @return void
	 * @throws Exception
	 */
	public function process_scheduled_save_payment_method( WebhookData $data ): void {
		try {
			$customer = $this->get_wc_customer_from_incoming_data( $data->get_order_id() );
			$this->logger_service->log( sprintf( 'Customer found successfully from scheduled webhook data. User ID: %s.', $customer->get_id() ), 'debug', $data->get_log_data() );

			// Since this action can be performed from a redirect and a scheduled webhook we need to check if the payment token was already added. We do this by checking if the payment token already exists.
			if ( $this->token_service->payment_token_exists( $customer->get_id(), $data->get_card_id() ) ) {
				$this->logger_service->log( sprintf( 'Skipping payment method saving. Payment method already saved from redirect data. User ID: %s.', $customer->get_id() ), 'debug', $data->get_log_data() );
				return;
			}

			$this->save_payment_method_from_customer( $data );
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log( sprintf( 'Error saving payment method from scheduled webhook data. Error: "%s".', $error ), 'error', $data->get_log_data() );
			throw new Exception( $error );
		}
	}

	/**
	 * Get payment method for checkout.
	 *
	 * @param WC_Order $order
	 * @return string|null
	 */
	public function get_payment_method_for_checkout( WC_Order $order ): ?string {
		if ( ! $order->get_user_id() ) {
			return null;
		}

		$payment_token_field_name = sprintf( 'wc-%s-payment-token', $this->settings_service->config['plugin_id'] );

		$token_id = isset( $_POST[ $payment_token_field_name ] ) ? (int) $_POST[ $payment_token_field_name ] : null; // phpcs:ignore WordPress.Security.NonceVerification.Missing

		if ( ! $token_id ) {
			return null;
		}

		$token = $this->token_service->get_token( $token_id );

		if ( ! $token || $token->get_user_id() !== $order->get_user_id() ) {
			$this->logger_service->log( sprintf( 'Payment method retrieval failed. User token invalid. Order ID: %s.', $order->get_id() ), 'error' );
			return null;
		}

		try {
			$card = $this->get_card( $token->get_token() );
			$this->logger_service->log( sprintf( 'Payment method retrieval for checkout successful. Order ID: %s.', $order->get_id() ), 'debug' );

			return $card->get_card_id();
		} catch ( Exception $exception ) {
			$this->logger_service->log( sprintf( 'Payment method retrieval for checkout failed. Order ID: %s. Error: "%s".', $order->get_id(), $exception->getMessage() ), 'error' );
			return null;
		}
	}

	/**
	 * Get payment link body.
	 *
	 * @param WC_Customer $customer
	 * @return array{
	 *     transaction: array{
	 *         currency: string,
	 *         custom1: string,
	 *         order_id: string,
	 *         amount: int,
	 *         capture: bool
	 *     },
	 *     payment: array{
	 *         reference: string
	 *     },
	 *     count_retry: int,
	 *     tds?: array{
	 *         is_active: bool,
	 *         challenge_preference: string,
	 *         contact_url: string
	 *     },
	 *     redirect_url: string,
	 *     webhook_url: string,
	 *     submit_type: string,
	 *     expires_in: int,
	 *     is_recurring: bool,
	 *     payment_methods: array<string>,
	 *     customer?: array{
	 *         customer_id: string
	 *     }
	 * }
	 */
	private function get_payment_link_body( WC_Customer $customer ): array {
		$body = $this->api_client->get_payment_link_default_body();

		$body['transaction'] = array_merge(
			$body['transaction'],
			[
				'order_id' => $this->format_order_id_for_payment_link( $customer ),
				'amount'   => 0,
				'capture'  => false,
			]
		);

		$body['redirect_url']    = $this->settings_service->get_wc_api_url( 'redirect-new-payment-method' );
		$body['webhook_url']     = $this->settings_service->get_wc_api_url( 'webhook' );
		$body['submit_type']     = 'register';
		$body['expires_in']      = $this->settings_service->get_payment_link_expiration_time();
		$body['is_recurring']    = true;
		$body['payment_methods'] = [ 'card' ];

		$customer_data = $this->customer_service->get_customer_data_for_new_payment_method( $customer->get_id() );
		if ( $customer_data ) {
			$body['customer'] = $customer_data;
		}

		return $body;
	}

	/**
	 * Get payment link URL.
	 *
	 * @param int $user_id
	 * @return string
	 * @throws Exception
	 */
	public function get_payment_link( int $user_id ): string {
		if ( ! $user_id ) {
			$error = 'Payment link creation failed. User ID is not set.';
			$this->logger_service->log( $error, 'error' );
			throw new Exception( $error );
		}

		$customer = $this->customer_factory->get_wc_customer( $user_id );

		if ( ! $customer ) {
			$error = sprintf( 'Payment link creation failed. Customer not found. User ID: %s.', $user_id );
			$this->logger_service->log( $error, 'error' );
			throw new Exception( $error );
		}

		$response = $this->api_client->get_payment_link( $this->get_payment_link_body( $customer ) );

		if ( $response->request_is_success() ) {
			$this->logger_service->log(
				sprintf( 'Payment link created successfully. User ID: %s.', $user_id ),
				'debug',
				$response->get_log_data()
			);

			return $this->get_pay_url( $response->get_link_id() );
		} else {
			$this->logger_service->log(
				sprintf( 'Payment link creation failed. User ID: %s.', $user_id ),
				'error',
				$response->get_log_data()
			);

			throw new Exception( 'Payment link creation failed.' );
		}
	}

	/**
	 * Confirm payment method.
	 *
	 * @param RedirectData $data
	 * @return WC_Customer
	 * @throws Exception
	 */
	public function confirm_payment_method( RedirectData $data ): WC_Customer {
		// Since redirect data with an unverified hash is accepted for orders, we need to make sure it's never used to save a payment method.
		if ( ! $data->is_hash_valid() ) {
			throw new Exception( sprintf( 'Redirect data hash is not valid. Hash status: %s.', $data->get_hash_status() ) );
		}

		try {
			$customer = $this->get_wc_customer_from_incoming_data( $data->get_order_id() );
			$card_id  = $this->get_card_id_from_transaction( $data->get_transaction_id() );
			$data->set_card_id( $card_id );

			// Since this action can be performed from a redirect and a scheduled webhook we need to check if the payment token was already added. We do this by checking if the payment token already exists.
			if ( ! $this->token_service->payment_token_exists( $customer->get_id(), $data->get_card_id() ) ) {
				$this->save_payment_method_from_customer( $data );
			}

			return $customer;
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log( sprintf( 'Error saving payment method from incoming redirect data. Error: "%s".', $error ), 'error', $data->get_log_data() );
			throw new Exception( $error );
		}
	}

	/**
	 * Get notice data for save payment method.
	 *
	 * @param string $status
	 * @return array{
	 *     message: string,
	 *     type: string
	 * }
	 */
	public function get_notice_data( string $status ): array {
		if ( $this->is_transaction_success( $status ) ) {
			return [
				'message' => __( 'Payment method successfully added.', 'acquired-com-for-woocommerce' ),
				'type'    => 'success',
			];
		}

		switch ( $status ) {
			case 'error':
				$message = __( 'Unable to add payment method to your account.', 'acquired-com-for-woocommerce' );
				break;
			case 'blocked':
				$message = __( 'Your payment method was blocked.', 'acquired-com-for-woocommerce' );
				break;
			case 'tds_error':
			case 'tds_expired':
			case 'tds_failed':
				$message = __( 'Your payment method has been declined due to failed authentication with your bank.', 'acquired-com-for-woocommerce' );
				break;
			default:
				$message = __( 'Your payment method was declined.', 'acquired-com-for-woocommerce' );
				break;
		}

		return [
			'message' => $message,
			'type'    => 'error',
		];
	}
}
