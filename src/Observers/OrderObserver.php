<?php
/**
 * OrderObserver.
 */

declare(strict_types=1);

namespace AcquiredComForWooCommerce\Observers;

use AcquiredComForWooCommerce\Api\IncomingDataHandler;
use AcquiredComForWooCommerce\Services\LoggerService;
use AcquiredComForWooCommerce\Services\OrderService;
use AcquiredComForWooCommerce\Services\SettingsService;
use Exception;
use WC_Order;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * OrderObserver class.
 */
class OrderObserver implements ObserverInterface {
	/**
	 * Constructor.
	 *
	 * @param IncomingDataHandler $incoming_data_handler
	 * @param LoggerService $logger_service
	 * @param OrderService $order_service
	 * @param SettingsService $settings_service
	 */
	public function __construct(
		private IncomingDataHandler $incoming_data_handler,
		private LoggerService $logger_service,
		private OrderService $order_service,
		private SettingsService $settings_service,
	) {}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'woocommerce_before_thankyou', [ $this, 'add_fail_notice' ] );
		add_action( $this->order_service->get_scheduled_action_hook(), [ $this, 'run_process_scheduled_order' ], 10, 2 );
		add_action( 'woocommerce_order_status_failed', [ $this, 'refund_woo_wallet' ], 10, 2 );
	}

	/**
	 * Add fail notice.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public function add_fail_notice( int $order_id ): void {
		$error_notice = $this->order_service->get_fail_notice( $order_id );
		if ( $error_notice ) {
			echo wp_kses_post( apply_filters( 'acfw_order_fail_notice', sprintf( '<div class="woocommerce-error">%s</div>', esc_html( $error_notice ) ), $error_notice, $order_id ) );
		}
	}

	/**
	 * Run process scheduled order.
	 *
	 * @param string $webhook_data
	 * @param string $hash
	 * @return void
	 */
	public function run_process_scheduled_order( string $webhook_data, string $hash ): void {
		try {
			$data = $this->incoming_data_handler->get_webhook_data( $webhook_data, $hash );
			$this->order_service->process_scheduled_order( $data );
		} catch ( Exception $exception ) {
			$this->logger_service->log( 'Scheduled order processing failed.', 'error' );
		}
	}

	/**
	 * Refund for Wallet for WooCommerce (TeraWallet).
	 *
	 * @param int $order_id
	 * @param WC_Order $order
	 * @return void
	 */
	public function refund_woo_wallet( int $order_id, WC_Order $order ): void {
		if ( $this->settings_service->is_enabled( 'woo_wallet_refund' ) && $this->order_service->is_acfw_payment_method( $order ) && class_exists( 'Woo_Wallet' ) ) {
			woo_wallet()->wallet->process_cancelled_order( $order->get_id() );
		}
	}
}
