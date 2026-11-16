<?php
/**
 * OrderService.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Services;

use AcquiredComForWooCommerce\Api\ApiClient;
use AcquiredComForWooCommerce\Api\IncomingData\RedirectData;
use AcquiredComForWooCommerce\Api\IncomingData\WebhookData;
use AcquiredComForWooCommerce\Api\Response\Transaction;
use AcquiredComForWooCommerce\Factories\CustomerFactory;
use AcquiredComForWooCommerce\Traits\PaymentLink;
use Exception;
use DateTime;
use DateTimeZone;
use WC_Order;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * OrderService class.
 */
class OrderService {
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
	 * @param PaymentMethodService $payment_method_service
	 * @param ScheduleService $schedule_service
	 * @param SettingsService $settings_service
	 * @param CustomerFactory $customer_factory
	 */
	public function __construct(
		private ApiClient $api_client,
		private CustomerService $customer_service,
		private LoggerService $logger_service,
		private PaymentMethodService $payment_method_service,
		private ScheduleService $schedule_service,
		private SettingsService $settings_service,
		private CustomerFactory $customer_factory,
	) {}

	/**
	 * Check if transaction type is capture.
	 *
	 * @return bool
	 */
	private function is_capture(): bool {
		return 'capture' === $this->settings_service->get_option( 'transaction_type', 'capture' );
	}

	/**
	 * Set transaction type.
	 *
	 * @param WC_Order $order
	 * @return void
	 */
	private function set_transaction_type( WC_Order $order ): void {
		$order->update_meta_data( '_acfw_transaction_type', $this->settings_service->get_option( 'transaction_type', 'capture' ) );
		$order->save();
	}

	/**
	 * Get scheduled action hook.
	 *
	 * @return string
	 */
	public function get_scheduled_action_hook(): string {
		return $this->settings_service->config['plugin_id'] . '_scheduled_process_order';
	}

	/**
	 * Check if order transaction was processed.
	 *
	 * @param string $data_transaction_id
	 * @param string|null $order_transaction_id
	 * @return bool
	 */
	private function order_transaction_id_processed( string $data_transaction_id, string|null $order_transaction_id ): bool {
		if ( ! $order_transaction_id ) {
			return false;
		}

		return $data_transaction_id === $order_transaction_id;
	}

	/**
	 * Check if the order is older than one day.
	 *
	 * @param int $order_timestamp
	 * @return bool
	 */
	public function is_day_older( int $order_timestamp ): bool {
		$time_zone    = new DateTimeZone( 'UTC' );
		$current_date = new DateTime( 'now', $time_zone );
		$order_date   = DateTime::createFromFormat( 'U', (string) $order_timestamp, $time_zone );

		return $current_date->format( 'Ymd' ) > $order_date->format( 'Ymd' );
	}

	/**
	 * Get transaction.
	 *
	 * @param string $transaction_id
	 * @return Transaction
	 * @throws Exception
	 */
	private function get_transaction( string $transaction_id ): Transaction {
		$transaction = $this->api_client->get_transaction( $transaction_id );

		if ( $transaction->request_is_error() ) {
			throw new Exception( 'Failed to get transaction.' );
		}

		return $transaction;
	}

	/**
	 * Get transaction time updated.
	 *
	 * @param string $transaction_id
	 * @return int
	 */
	private function get_transaction_time_updated( string $transaction_id ): int {
		try {
			$transaction = $this->get_transaction( $transaction_id );
			return $transaction->get_created_timestamp();
		} catch ( Exception $exception ) {
			return time();
		}
	}

	/**
	 * Can be processed.
	 *
	 * @param WC_Order $order
	 * @return bool
	 */
	private function can_be_processed( WC_Order $order ): bool {
		return ! in_array( $order->get_meta( '_acfw_order_state' ), [ 'completed', 'cancelled', 'executed', 'refunded_full', 'refunded_partial' ], true );
	}

	/**
	 * Get payment link expiration.
	 *
	 * @return int
	 */
	private function get_payment_link_expiration_time(): int {
		$hold_stock = $this->settings_service->get_wc_hold_stock_time();

		if ( $hold_stock <= 0 ) {
			return $this->settings_service->get_payment_link_expiration_time();
		}

		return min( $hold_stock, $this->settings_service->get_payment_link_max_expiration_time() );
	}

	/**
	 * Get payment link body.
	 *
	 * @param WC_Order $order
	 * @return array{
	 *     transaction: array{
	 *         currency: string,
	 *         custom1: string,
	 *         order_id: string,
	 *         amount: float,
	 *         capture: bool
	 *     },
	 *     payment: array{
	 *         reference: string,
	 *         card_id?: string
	 *     },
	 *     customer?: array{
	 *         customer_id: string
	 *     }|array{
	 *         first_name: string,
	 *         last_name: string,
	 *         email: string,
	 *         billing: array{
	 *             address: array{
	 *                 line_1: string,
	 *                 line_2: string,
	 *                 city: string,
	 *                 postcode: string,
	 *                 country_code: string,
	 *                 state?: string
	 *             },
	 *             email?: string
	 *         },
	 *         shipping?: array{
	 *             address_match: bool,
	 *             address?: array{
	 *                 line_1: string,
	 *                 line_2: string,
	 *                 city: string,
	 *                 postcode: string,
	 *                 country_code: string,
	 *                 state?: string
	 *             },
	 *             email?: string
	 *         }
	 *     },
	 *     redirect_url: string,
	 *     webhook_url: string,
	 *     submit_type: string,
	 *     expires_in: int,
	 *     count_retry: int,
	 *     tds?: array{
	 *         is_active: bool,
	 *         challenge_preference: string,
	 *         contact_url: string
	 *     }
	 * }
	 */
	private function get_payment_link_body( WC_Order $order ): array {
		$body = $this->api_client->get_payment_link_default_body();

		$body['transaction'] = array_merge(
			$body['transaction'],
			[
				'order_id' => $this->format_order_id_for_payment_link( $order ),
				'amount'   => floatval( $order->get_total() ),
				'currency' => $order->get_currency(),
				'capture'  => $this->is_capture(),
			]
		);

		$body['redirect_url'] = $this->settings_service->get_wc_api_url( 'redirect-new-order' );
		$body['webhook_url']  = $this->settings_service->get_wc_api_url( 'webhook' );
		$body['submit_type']  = $this->settings_service->get_option( 'submit_type', 'pay' );
		$body['expires_in']   = $this->get_payment_link_expiration_time();

		$customer_data = $this->customer_service->get_customer_data_for_checkout( $order );
		if ( $customer_data ) {
			$body['customer'] = $customer_data;
		}

		$card_id = $this->payment_method_service->get_payment_method_for_checkout( $order );
		if ( $card_id ) {
			$body['payment']['card_id'] = $card_id;
		}

		return $body;
	}

	/**
	 * Get payment link.
	 *
	 * @param int $order_id
	 * @return string
	 * @throws Exception
	 */
	public function get_payment_link( int $order_id ): string {
		$order = $this->get_wc_order( $order_id );

		if ( ! $order ) {
			$this->logger_service->log(
				sprintf( 'Failed to find order. Order ID: %s.', $order_id ),
				'error',
			);
			throw new Exception( sprintf( __( 'Failed to find order.', 'acquired-com-for-woocommerce' ), $order_id ) );
		}

		if ( ! $this->can_be_processed( $order ) ) {
			$order->add_order_note(
				__( 'Payment link creation failed. Order has already been processed and can\'t be processed again.', 'acquired-com-for-woocommerce' )
			);

			$this->logger_service->log(
				sprintf( 'Payment link creation failed. Order has already been processed and can\'t be processed again. Order ID: %s.', $order->get_id() ),
				'debug',
				[ 'order_state' => $order->get_meta( '_acfw_order_state' ) ]
			);
			throw new Exception( __( 'This order has already been processed and can\'t be processed again.', 'acquired-com-for-woocommerce' ) );
		}

		$response = $this->api_client->get_payment_link( $this->get_payment_link_body( $order ) );

		if ( $response->request_is_success() ) {
			$this->set_transaction_type( $order );
			$this->logger_service->log(
				sprintf( 'Payment link created successfully. Order ID: %s.', $order->get_id() ),
				'debug',
				$response->get_log_data()
			);

			return $this->get_pay_url( $response->get_link_id() );
		} else {
			$order->add_order_note(
				sprintf(
					'%s %s',
					__( 'Payment link creation failed.', 'acquired-com-for-woocommerce' ),
					$response->get_error_message_formatted( true )
				)
			);

			$this->logger_service->log(
				sprintf( 'Payment link creation failed. Order ID: %s.', $order->get_id() ),
				'error',
				$response->get_log_data()
			);

			throw new Exception( __( 'Payment link creation failed.', 'acquired-com-for-woocommerce' ) );
		}
	}

	/**
	 * Set additional order data.
	 *
	 * @param WC_Order $order
	 * @param Transaction $transaction
	 * @return void
	 */
	private function set_additional_order_data( WC_Order $order, Transaction $transaction ): void {
		$order->update_meta_data( '_acfw_transaction_payment_method', $transaction->get_payment_method() );
		$order->add_order_note(
			sprintf(
				/* translators: %1$s is transaction ID, %2$s is payment method ID. */
				__( 'Transaction (ID: %1$s) payment method: "%2$s".', 'acquired-com-for-woocommerce' ),
				$transaction->get_transaction_id(),
				$transaction->get_payment_method()
			)
		);

		$order->delete_meta_data( '_acfw_transaction_decline_reason' );

		if ( $transaction->get_decline_reason() ) {
			$order->update_meta_data( '_acfw_transaction_decline_reason', $transaction->get_decline_reason() );
			$order->add_order_note(
				sprintf(
					/* translators: %1$s is transaction ID, %2$s is transaction decline reason. */
					__( 'Transaction (ID: %1$s) decline reason: "%2$s".', 'acquired-com-for-woocommerce' ),
					$transaction->get_transaction_id(),
					$transaction->get_decline_reason()
				)
			);
		}

		$order->save();

		$this->logger_service->log(
			sprintf( 'Order additional data set successfully. Order ID: %s.', $order->get_id() ),
			'debug',
			$transaction->get_log_data()
		);
	}

	/**
	 * Schedule process order.
	 *
	 * @param WebhookData $data
	 * @param string $hash
	 * @return void
	 * @throws Exception
	 */
	public function schedule_process_order( WebhookData $data, string $hash ): void {
		// The API sends webhook status_update notifications for adding payment methods so we check if this is for an order and schedule only those webhooks.
		if ( ! $this->is_for_order( $data->get_order_id() ) ) {
			return;
		}

		try {
			$order = $this->get_wc_order_from_incoming_data( $data->get_order_id() );

			$this->schedule_service->schedule(
				$this->get_scheduled_action_hook(),
				[
					'webhook_data' => wp_json_encode( $data->get_incoming_data() ),
					'hash'         => $hash,
				]
			);

			$this->logger_service->log( sprintf( 'Order processing scheduled successfully from incoming webhook data. Order ID: %s.', $order->get_id() ), 'debug', $data->get_log_data() );
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log( sprintf( 'Error scheduling order processing from incoming webhook data. %s', $error ), 'error', $data->get_log_data() );
			throw new Exception( $error );
		}
	}

	/**
	 * Process transaction for order.
	 *
	 * @param WC_Order $order
	 * @param string $transaction_id
	 * @param string $data_type
	 * @return void
	 * @throws Exception
	 */
	private function process_transaction( WC_Order $order, string $transaction_id, string $data_type ): void {
		// Since this method can be triggered from a redirect and a scheduled webhook, we need to check if the order is already processed. We do this by checking if the transaction ID is already set in the order.
		if ( $this->order_transaction_id_processed( $transaction_id, $order->get_transaction_id() ) ) {
			$this->logger_service->log( sprintf( 'Order transaction already processed. Skipping the processing. Order ID: %s.', $order->get_id() ), 'debug' );
			return;
		}

		$transaction = $this->get_transaction( $transaction_id );
		$log_data    = $transaction->get_log_data();
		$this->logger_service->log( sprintf( 'Transaction found successfully from incoming %s data. Order ID: %s.', $data_type, $order->get_id() ), 'debug', $log_data );

		// We need to do the timestamp check also because we might have multiple webhooks scheduled for the same order and we need to make sure that we are processing the latest transaction.
		if ( (int) $order->get_meta( '_acfw_order_time_updated' ) >= $transaction->get_created_timestamp() ) {
			$this->logger_service->log( sprintf( 'Incoming %s time created is not newer than the order time updated. Skipping the processing. Order ID: %s.', $data_type, $order->get_id() ), 'debug', $log_data );
			return;
		}

		if ( ! $order->has_status( [ 'pending', 'failed', 'on-hold' ] ) ) {
			$error = sprintf( 'Received incoming %s data for an order that can\'t be processed again. Order ID: %s, order status: %s.', $data_type, $order->get_id(), $order->get_status() );
			throw new Exception( $error );
		}

		$order->set_transaction_id( $transaction->get_transaction_id() );
		$order->update_meta_data( '_acfw_transaction_status', $transaction->get_status() );
		$order->update_meta_data( '_acfw_order_time_updated', $transaction->get_created_timestamp() );
		$order->update_meta_data( '_acfw_version', $this->settings_service->config['version'] );
		$order->save();

		switch ( $order->get_meta( '_acfw_transaction_status' ) ) {
			case 'success':
			case 'settled':
				if ( 'authorisation' === $order->get_meta( '_acfw_transaction_type' ) ) {
					$order->update_meta_data( '_acfw_order_state', 'authorised' );
					$order->update_status( 'on-hold', );
					$order->add_order_note(
						sprintf(
							/* translators: %s is transaction ID. */
							__( 'Payment authorised. Transaction ID: %s.', 'acquired-com-for-woocommerce' ),
							$order->get_transaction_id()
						)
					);

					$this->logger_service->log( sprintf( 'Payment authorised for order. Order ID: %s.', $order->get_id() ), 'debug', $log_data );
				} else {
					$order->payment_complete();
					$order->update_meta_data( '_acfw_order_state', 'completed' );
					$order->update_meta_data( '_acfw_order_time_completed', $transaction->get_created_timestamp() );
					$order->add_order_note(
						sprintf(
							/* translators: %s is transaction ID. */
							__( 'Payment successful. Transaction ID: %s.', 'acquired-com-for-woocommerce' ),
							$order->get_transaction_id()
						)
					);

					$this->logger_service->log( sprintf( 'Payment complete for order. Order ID: %s.', $order->get_id() ), 'debug', $log_data );
				}
				break;
			case 'executed':
				$order->update_meta_data( '_acfw_order_state', 'executed' );
				$order->update_status( 'on-hold' );
				$order->add_order_note(
					sprintf(
						/* translators: %s is transaction ID. */
						__( 'Bank payment executed. Transaction ID: %s.', 'acquired-com-for-woocommerce' ),
						$order->get_transaction_id()
					)
				);

				$this->logger_service->log( sprintf( 'Bank payment executed for order. Order ID: %s.', $order->get_id() ), 'debug', $log_data );
				break;
			default:
				$order->update_meta_data( '_acfw_order_state', 'failed' );
				$order->update_status( 'failed' );
				$order->add_order_note(
					sprintf(
						/* translators: %1$s is order status, %2$s is transaction ID. */
						__( 'Payment failed with status "%1$s". Transaction ID: %2$s.', 'acquired-com-for-woocommerce' ),
						$order->get_meta( '_acfw_transaction_status' ),
						$order->get_transaction_id()
					)
				);

				$this->logger_service->log( sprintf( 'Payment failed for order. Order ID: %s.', $order->get_id() ), 'debug', $log_data );
				break;
		}

		$order->save();
		$this->logger_service->log( sprintf( 'Order processed successfully from incoming %s data. Order ID: %s.', $data_type, $order->get_id() ), 'debug', $log_data );

		$this->set_additional_order_data( $order, $transaction );

		$order->add_order_note(
			sprintf(
				/* translators: %s is data type. */
				__( 'Order processed successfully from incoming %s data.', 'acquired-com-for-woocommerce' ),
				$data_type
			)
		);
	}

	/**
	 * Process order.
	 *
	 * @param RedirectData|WebhookData $data
	 * @return void
	 * @throws Exception
	 */
	public function process_order( RedirectData|WebhookData $data ): void {
		if ( ! $this->is_for_order( $data->get_order_id() ) ) {
			return;
		}

		try {
			$order = $this->get_wc_order_from_incoming_data( $data->get_order_id() );
			$this->logger_service->log( sprintf( 'Order found successfully from incoming %s data. Order ID: %s.', $data->get_type(), $order->get_id() ), 'debug', $data->get_log_data() );

			$this->process_transaction( $order, $data->get_transaction_id(), $data->get_type() );
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log( sprintf( 'Error processing order from incoming %s data. %s', $data->get_type(), $error ), 'error', $data->get_log_data() );
			throw new Exception( $error );
		}
	}

	/**
	 * Process scheduled order.
	 *
	 * @param WebhookData $data
	 * @return void
	 * @throws Exception
	 */
	public function process_scheduled_order( WebhookData $data ): void {
		try {
			$this->process_order( $data );
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log( sprintf( 'Error processing order from scheduled webhook data. Error: "%s".', $error ), 'error', $data->get_log_data() );
			throw new Exception( $error );
		}
	}

	/**
	 * Confirm order.
	 *
	 * @param RedirectData $data
	 * @return WC_Order
	 * @throws Exception
	 */
	public function confirm_order( RedirectData $data ): WC_Order {
		try {
			$order = $this->get_wc_order_from_incoming_data( $data->get_order_id() );
			$this->process_order( $data );
			$this->settings_service->clear_signing_key_issue();
			return $order;
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log( sprintf( 'Error processing order from incoming redirect data. %s', $error ), 'error', $data->get_log_data() );
			throw new Exception( $error );
		}
	}

	/**
	 * Check if order can be synced.
	 *
	 * @param WC_Order $order
	 * @return bool
	 */
	private function can_be_synced( WC_Order $order ): bool {
		return $this->is_acfw_payment_method( $order ) && $order->has_status( [ 'pending', 'failed' ] );
	}

	/**
	 * Sync order with the successful transaction for it on Acquired.
	 *
	 * @param WC_Order $order
	 * @return bool
	 * @throws Exception
	 */
	private function sync_order( WC_Order $order ): bool {
		$response = $this->api_client->get_transactions(
			[
				'order_id' => $this->format_order_id_for_payment_link( $order ),
				'status'   => 'success',
				'limit'    => 1,
			],
			[ 'transaction_id' ]
		);

		if ( $response->request_is_error() ) {
			$this->logger_service->log( sprintf( 'Failed to get transactions. Order ID: %s.', $order->get_id() ), 'error', $response->get_log_data() );
			throw new Exception( 'Failed to get transactions.' );
		}

		$transaction_id = $response->get_first_transaction_id();

		if ( ! $transaction_id ) {
			$this->logger_service->log( sprintf( 'No successful transaction found on Acquired.com. Order ID: %s.', $order->get_id() ), 'debug', $response->get_log_data() );
			return false;
		}

		$this->process_transaction( $order, $transaction_id, 'API' );

		return true;
	}

	/**
	 * Confirm order from redirect data with an unverified hash, by checking the payment status with Acquired.
	 *
	 * @param RedirectData $data
	 * @return WC_Order
	 * @throws Exception
	 */
	public function confirm_unverified_order( RedirectData $data ): WC_Order {
		try {
			$order = $this->get_wc_order_from_incoming_data( $data->get_order_id() );

			if ( ! $this->can_be_synced( $order ) ) {
				$this->logger_service->log( sprintf( 'Unverified redirect for an order that doesn\'t need a payment status check. Order ID: %s.', $order->get_id() ), 'debug' );
				return $order;
			}

			$this->settings_service->set_signing_key_issue( $data->get_hash_status() );

			$order->add_order_note(
				__( 'Redirect data from Acquired.com couldn\'t be verified with the signing key. Payment status checked with the Acquired.com API.', 'acquired-com-for-woocommerce' )
			);

			if ( ! $this->sync_order( $order ) ) {
				$order->update_meta_data( '_acfw_order_state', 'failed' );
				$order->update_status( 'failed' );
				$order->add_order_note( __( 'Payment status checked with Acquired.com. No successful payment found.', 'acquired-com-for-woocommerce' ) );
				$order->save();

				$this->logger_service->log( sprintf( 'Payment failed for order. Order ID: %s.', $order->get_id() ), 'debug' );
			}

			return $order;
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log( sprintf( 'Error processing order from unverified redirect data. %s', $error ), 'error' );
			throw new Exception( $error );
		}
	}

	/**
	 * Check if order can be captured.
	 *
	 * @param WC_Order $order
	 * @return bool
	 */
	public function can_be_captured( WC_Order $order ): bool {
		return $this->is_acfw_payment_method( $order ) && $order->get_transaction_id() && 'authorisation' === $order->get_meta( '_acfw_transaction_type' ) && 'authorised' === $order->get_meta( '_acfw_order_state' ) && floatval( $order->get_total() ) > 0;
	}

	/**
	 * Capture order.
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	public function capture_order( WC_Order $order ): string {
		if ( ! $this->can_be_captured( $order ) ) {
			$order->add_order_note( __( 'Payment capture failed. Capture initiated for an order that can\'t be captured.', 'acquired-com-for-woocommerce' ) );

			$this->logger_service->log( sprintf( 'Payment capture failed. Capture initiated for an order that can\'t be captured. Order ID: %s.', $order->get_id() ), 'error', );
			return 'error';
		}

		$response = $this->api_client->capture_transaction( $order->get_transaction_id(), [ 'amount' => floatval( $order->get_total() ) ] );

		$result    = 'error';
		$captured  = $response->is_captured();
		$log_level = 'debug';

		if ( $captured ) {
			$time_updated = $this->get_transaction_time_updated( $response->get_transaction_id() );

			$order->payment_complete( $response->get_transaction_id() );
			$order->update_meta_data( '_acfw_order_state', 'completed' );
			$order->update_meta_data( '_acfw_order_time_completed', $time_updated );
			$order->update_meta_data( '_acfw_order_time_updated', $time_updated );
			$order->save();

			$order_note = sprintf(
				/* translators: %s is transaction ID. */
				__( 'Payment captured successfully. Transaction ID: %s.', 'acquired-com-for-woocommerce' ),
				$order->get_transaction_id()
			);

			$log_message = sprintf( 'Payment captured successfully. Order ID: %s.', $order->get_id() );
			$result      = 'success';
		} else {
			if ( $response->get_decline_reason() ) {
				$order->update_status( 'failed' );

				$order_note = sprintf(
					/* translators: %1$s is capture status, %2$s is transaction ID. */
					__( 'Payment capture declined with status "%1$s". Transaction ID: %2$s.', 'acquired-com-for-woocommerce' ),
					$response->get_decline_reason(),
					$order->get_transaction_id()
				);

				$log_message = sprintf( 'Payment capture declined. Order ID: %s.', $order->get_id() );
			} else {
				$order_note = sprintf(
					/* translators: %s is transaction ID. */
					__( 'Payment capture failed. Transaction ID: %s.', 'acquired-com-for-woocommerce' ),
					$order->get_transaction_id()
				);

				$log_message = sprintf( 'Payment capture failed. Order ID: %s.', $order->get_id() );
				$log_level   = 'error';
			}

			$order_note = sprintf(
				'%s %s',
				$order_note,
				$response->get_error_message_formatted( true )
			);
		}

		$order->add_order_note( $order_note );

		$this->logger_service->log(
			$log_message,
			$log_level,
			$response->get_log_data()
		);

		return $result;
	}

	/**
	 * Check if order can be cancelled.
	 *
	 * @param WC_Order $order
	 * @return bool
	 */
	public function can_be_cancelled( WC_Order $order ): bool {
		return $this->is_acfw_payment_method( $order ) && $order->get_transaction_id() && in_array( $order->get_meta( '_acfw_transaction_status' ), [ 'success', 'settled' ], true ) && in_array( $order->get_meta( '_acfw_order_state' ), [ 'authorised', 'executed', 'completed' ], true );
	}

	/**
	 * Cancel order.
	 *
	 * @param WC_Order $order
	 * @return string
	 */
	public function cancel_order( WC_Order $order ): string {
		if ( ! $this->can_be_cancelled( $order ) ) {
			$order->add_order_note( __( 'Order cancellation failed. Cancellation initiated for an order that can\'t be cancelled.', 'acquired-com-for-woocommerce' ) );

			$this->logger_service->log( sprintf( 'Order cancellation failed. Cancellation initiated for an order that can\'t be cancelled. Order ID: %s.', $order->get_id() ), 'error', );
			return 'error';
		}

		if ( 'authorisation' === $order->get_meta( '_acfw_transaction_type' ) && 'completed' === $order->get_meta( '_acfw_order_state' ) && ! $this->is_day_older( (int) $order->get_meta( '_acfw_order_time_completed' ) ) ) {
			$order->add_order_note( __( 'Order cancellation failed. Captured orders can be cancelled the next day.', 'acquired-com-for-woocommerce' ) );

			$this->logger_service->log( sprintf( 'Order cancellation failed. Captured orders can be cancelled the next day. Order ID: %s.', $order->get_id() ), 'debug' );
			return 'invalid';
		}

		$response = $this->api_client->cancel_transaction(
			$order->get_transaction_id(),
			[
				'reference' => $this->settings_service->get_payment_reference(),
			]
		);

		$result    = 'error';
		$cancelled = $response->is_cancelled();
		$log_level = 'debug';

		if ( $cancelled ) {
			$order->update_status( 'cancelled' );
			$order->update_meta_data( '_acfw_order_state', 'cancelled' );
			$order->update_meta_data( '_acfw_order_time_updated', $this->get_transaction_time_updated( $response->get_transaction_id() ) );
			$order->save();

			$order_note = sprintf(
				/* translators: %s is transaction ID. */
				__( 'Order cancelled successfully. Transaction ID: %s.', 'acquired-com-for-woocommerce' ),
				$response->get_transaction_id()
			);

			$log_message = sprintf( 'Order cancelled successfully. Order ID: %s.', $order->get_id() );
			$result      = 'success';
		} else {
			if ( $response->get_decline_reason() ) {
				$order_note = sprintf(
					/* translators: %1$s is cancellation status, %2$s is transaction ID. */
					__( 'Order cancellation declined with status "%1$s". Transaction ID: %2$s.', 'acquired-com-for-woocommerce' ),
					$response->get_decline_reason(),
					$response->get_transaction_id()
				);

				$log_message = sprintf( 'Order cancellation declined. Order ID: %s.', $order->get_id() );
			} else {
				$order_note = sprintf(
					/* translators: %s is transaction ID. */
					__( 'Order cancellation failed. Transaction ID: %s.', 'acquired-com-for-woocommerce' ),
					$order->get_transaction_id()
				);

				$log_message = sprintf( 'Order cancellation failed. Order ID: %s.', $order->get_id() );
				$log_level   = 'error';
			}

			$order_note = sprintf(
				'%s %s',
				$order_note,
				$response->get_error_message_formatted( true )
			);
		}

		$order->add_order_note( $order_note );

		$this->logger_service->log(
			$log_message,
			$log_level,
			$response->get_log_data()
		);

		return $result;
	}

	/**
	 * Check if order can be refunded.
	 *
	 * @param WC_Order $order
	 * @return bool
	 * @param float $amount
	 * @throws Exception
	 */
	private function can_be_refunded( WC_Order $order, float $amount ): bool {
		switch ( true ) {
			case ! in_array( $order->get_meta( '_acfw_transaction_status' ), [ 'success', 'settled' ], true ):
				$error     = __( 'Transaction is not in "success" or "settled" status.', 'acquired-com-for-woocommerce' );
				$log_error = 'Transaction is not in "success" or "settled" status.';
				break;
			case 'refunded_full' === $order->get_meta( '_acfw_order_state' ):
				$error     = __( 'Transaction has already been fully refunded.', 'acquired-com-for-woocommerce' );
				$log_error = 'Transaction has already been refunded.';
				break;
			case 'cancelled' === $order->get_meta( '_acfw_order_state' ):
				$error     = __( 'Order has already been cancelled.', 'acquired-com-for-woocommerce' );
				$log_error = 'Order has been cancelled.';
				break;
			case 'authorisation' === $order->get_meta( '_acfw_transaction_type' ) && 'completed' === $order->get_meta( '_acfw_order_state' ) && ! $this->is_day_older( (int) $order->get_meta( '_acfw_order_time_completed' ) ):
				$error     = __( 'Captured orders can be refunded the next day.', 'acquired-com-for-woocommerce' );
				$log_error = 'Transaction can\'t be refunded until the next day.';
				break;
			default:
				$error     = false;
				$log_error = '';
				break;
		}

		if ( $error ) {
			$this->logger_service->log( sprintf( 'Payment refund failed. Order ID: %s. %s', $order->get_id(), $log_error ), 'debug' );
			throw new Exception( $error );
		}

		if ( $amount < floatval( $order->get_total() ) ) {
			$date = 'authorised' === $order->get_meta( '_acfw_order_state' ) ? $order->get_meta( '_acfw_order_time_updated' ) : $order->get_meta( '_acfw_order_time_completed' );

			if ( ! $this->is_day_older( (int) $date ) ) {
				$this->logger_service->log( sprintf( 'Payment refund failed. Partial refunds are only available on the next day. Order ID: %s.', $order->get_id() ), 'debug' );

				throw new Exception( __( 'Partial refunds are only available on the next day.', 'acquired-com-for-woocommerce' ) );
			}
		}

		return true;
	}

	/**
	 * Refund order.
	 *
	 * @param int $order_id
	 * @param float $amount
	 * @return void
	 * @throws Exception
	 */
	public function refund_order( int $order_id, float $amount ): void {
		$order = $this->get_wc_order( $order_id );

		if ( ! $order ) {
			throw new Exception( sprintf( 'Failed to find order. Order ID: %s.', $order_id ) );
		}

		try {
			$this->can_be_refunded( $order, $amount );
		} catch ( Exception $exception ) {
			$order_note = sprintf(
				'%s %s',
				__( 'Payment refund failed.', 'acquired-com-for-woocommerce' ),
				$exception->getMessage()
			);

			$order->add_order_note( $order_note );

			throw new Exception( $order_note );
		}

		$order_total = floatval( $order->get_total() );

		if ( $amount > $order_total ) {
			$order_note = sprintf(
				/* translators: %1$s is refund amount, %2$s is order total. */
				__( 'Payment refund failed. Refund amount "%1$s" is greater than order total "%2$s".', 'acquired-com-for-woocommerce' ),
				$amount,
				$order_total
			);
			$order->add_order_note( $order_note );

			$this->logger_service->log( sprintf( 'Payment refund failed. Refund amount "%s" is greater than order total "%s". Order ID: %s.', $amount, $order_total, $order->get_id() ), 'error', );
			throw new Exception( $order_note );
		}

		$response = $this->api_client->refund_transaction(
			$order->get_transaction_id(),
			[
				'amount'    => $amount,
				'reference' => $this->settings_service->get_payment_reference(),
			]
		);

		$refunded = $response->is_refunded();

		$log_level        = 'debug';
		$amount_formatted = wc_price( $amount );

		if ( $refunded ) {
			$order->update_meta_data( '_acfw_order_state', $order_total - $amount > 0 ? 'refunded_partial' : 'refunded_full' );
			$order->update_meta_data( '_acfw_order_time_updated', $this->get_transaction_time_updated( $response->get_transaction_id() ) );
			$order->save();

			$order_note = sprintf(
				/* translators: %1$s is the refund amount, %2$s is transaction ID, %3$s is refund transaction ID. */
				__( 'Payment refunded successfully. Refund amount: %1$s. Transaction ID: %2$s. Refund transaction ID: %3$s.', 'acquired-com-for-woocommerce' ),
				$amount_formatted,
				$order->get_transaction_id(),
				$response->get_transaction_id()
			);

			$log_message = sprintf( 'Payment refunded successfully. Order ID: %s.', $order->get_id() );
		} else {
			if ( $response->get_decline_reason() ) {
				$order_note = sprintf(
					/* translators: %1$s is refund status, %2$s is the refund amount, %3$s is transaction ID. */
					__( 'Payment refund declined with status "%1$s". Refund amount: %2$s. Transaction ID: %3$s.', 'acquired-com-for-woocommerce' ),
					$response->get_decline_reason(),
					$amount_formatted,
					$order->get_transaction_id()
				);

				$log_message = sprintf( 'Payment refund declined. Order ID: %s.', $order->get_id() );
			} else {
				$order_note = sprintf(
					/* translators: %s is transaction ID. */
					__( 'Payment refund failed. Transaction ID: %s.', 'acquired-com-for-woocommerce' ),
					$order->get_transaction_id()
				);

				$log_message = sprintf( 'Payment refund failed. Order ID: %s.', $order->get_id() );
				$log_level   = 'error';
			}

			$order_note = sprintf(
				'%s %s',
				$order_note,
				$response->get_error_message_formatted( true )
			);
		}

		$order->add_order_note( $order_note );

		$this->logger_service->log(
			$log_message,
			$log_level,
			array_merge(
				$response->get_log_data(),
				[
					'refund_amount' => $amount,
					'order_total'   => $order_total,
				]
			)
		);

		if ( ! $refunded ) {
			throw new Exception( __( 'Payment refund failed. Check order notes for more details.', 'acquired-com-for-woocommerce' ) );
		}
	}

	/**
	 * Get message for failed order.
	 *
	 * @param int $order_id
	 * @return string|null
	 */
	public function get_fail_notice( int $order_id ): ?string {
		$order = $this->get_wc_order( $order_id );

		if ( ! $order || ! $this->is_acfw_payment_method( $order ) || ! $order->has_status( 'failed' ) ) {
			return null;
		}

		switch ( $order->get_meta( '_acfw_transaction_status' ) ) {
			case 'blocked':
				return __( 'Your payment was blocked.', 'acquired-com-for-woocommerce' );
			case 'tds_error':
			case 'tds_expired':
			case 'tds_failed':
				return __( 'Your payment has been declined due to failed authentication with your bank.', 'acquired-com-for-woocommerce' );
			default:
				return __( 'Your payment was declined.', 'acquired-com-for-woocommerce' );
		}
	}
}
