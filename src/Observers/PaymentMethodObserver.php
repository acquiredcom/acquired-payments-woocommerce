<?php
/**
 * PaymentMethodObserver.
 */

declare(strict_types=1);

namespace AcquiredComForWooCommerce\Observers;

use AcquiredComForWooCommerce\Api\IncomingDataHandler;
use AcquiredComForWooCommerce\Services\LoggerService;
use AcquiredComForWooCommerce\Services\PaymentMethodService;
use WC_Payment_Token;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * PaymentMethodObserver class.
 */
class PaymentMethodObserver implements ObserverInterface {
	/**
	 * Constructor.
	 *
	 * @param IncomingDataHandler $incoming_data_handler
	 * @param LoggerService $logger_service
	 * @param PaymentMethodService $payment_method_service
	 */
	public function __construct(
		private IncomingDataHandler $incoming_data_handler,
		private LoggerService $logger_service,
		private PaymentMethodService $payment_method_service
	) {}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'template_redirect', [ $this, 'add_notice' ] );
		add_action( 'woocommerce_payment_token_deleted', [ $this, 'payment_token_deleted' ], 10, 2 );
		add_action( $this->payment_method_service->get_scheduled_action_hook(), [ $this, 'run_process_scheduled_save_payment_method' ], 10, 2 );
	}

	/**
	 * Deactivate card on token deletion.
	 *
	 * @param int $token_id
	 * @param WC_Payment_Token $token
	 * @return void
	 */
	public function payment_token_deleted( $token_id, $token ): void { // phpcs:ignore Squiz.Commenting.FunctionComment.TypeHintMissing
		$this->payment_method_service->deactivate_card( $token );
	}

	/**
	 * Add fail notice.
	 *
	 * @return void
	 */
	public function add_notice(): void {
		if ( ! is_add_payment_method_page() ) {
			return;
		}

		$notice_data = ! empty( $_GET[ $this->payment_method_service->get_status_key() ] ) ? $this->payment_method_service->get_notice_data( sanitize_text_field( wp_unslash( $_GET[ $this->payment_method_service->get_status_key() ] ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $notice_data ) {
			wc_add_notice( $notice_data['message'], $notice_data['type'] );
		}
	}

	/**
	 * Process scheduled save payment method.
	 *
	 * @param string $webhook_data
	 * @param string $hash
	 * @return void
	 */
	public function run_process_scheduled_save_payment_method( string $webhook_data, string $hash ): void {
		try {
			$data = $this->incoming_data_handler->get_webhook_data( $webhook_data, $hash );
			$this->payment_method_service->process_scheduled_save_payment_method( $data );
		} catch ( Exception $exception ) {
			$this->logger_service->log( 'Scheduled saving payment method failed.', 'error' );
		}
	}
}
