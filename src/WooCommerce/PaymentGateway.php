<?php
/**
 * Payment gateway.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\WooCommerce;

use AcquiredComForWooCommerce\Api\IncomingDataHandler;
use AcquiredComForWooCommerce\Services\AdminService;
use AcquiredComForWooCommerce\Services\LoggerService;
use AcquiredComForWooCommerce\Services\OrderService;
use AcquiredComForWooCommerce\Services\SettingsService;
use AcquiredComForWooCommerce\Services\PaymentMethodService;
use Exception;
use WC_Order;
use WC_Payment_Gateway;
use WC_Payment_Token;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Payment gateway class.
 */
class PaymentGateway extends WC_Payment_Gateway {
	/**
	 * Constructor.
	 *
	 * @param IncomingDataHandler $incoming_data_handler
	 * @param AdminService $admin_service
	 * @param LoggerService $logger_service
	 * @param OrderService $order_service
	 * @param PaymentMethodService $payment_method_service
	 * @param SettingsService $settings_service
	 */
	public function __construct(
		private IncomingDataHandler $incoming_data_handler,
		private AdminService $admin_service,
		private LoggerService $logger_service,
		private OrderService $order_service,
		private PaymentMethodService $payment_method_service,
		private SettingsService $settings_service
	) {
		$this->id                 = $this->settings_service->config['plugin_id'];
		$this->method_title       = __( 'Acquired.com', 'acquired-com-for-woocommerce' );
		$this->method_description = __( 'Securely accept Cards, Apple Pay & Google Pay on your store using Acquired.com.', 'acquired-com-for-woocommerce' );
		$this->has_fields         = false;
		$this->supports           = [ 'products', 'refunds' ];

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->settings_service->get_option( 'title' );
		$this->description = $this->settings_service->get_option( 'description' );

		if ( $this->settings_service->is_enabled( 'tokenization' ) ) {
			$this->supports[] = 'tokenization';
		}

		$this->init_hooks();
	}

	/**
	 * Initialize payment gateway settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields(): void {
		$this->form_fields = $this->settings_service->get_fields();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] ); // @phpstan-ignore return.void
		add_action( 'admin_notices', [ $this, 'display_errors' ] );
		add_action( 'admin_enqueue_scripts', [ $this->admin_service, 'add_order_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this->admin_service, 'add_settings_assets' ] );

		add_action( 'woocommerce_api_' . $this->settings_service->get_wc_api_endpoint( 'webhook' ), [ $this, 'process_webhook' ] );
		add_action( 'woocommerce_api_' . $this->settings_service->get_wc_api_endpoint( 'redirect-new-order' ), [ $this, 'redirect_new_order' ] );
		add_action( 'woocommerce_api_' . $this->settings_service->get_wc_api_endpoint( 'redirect-new-payment-method' ), [ $this, 'redirect_new_payment_method' ] );

		add_filter( 'woocommerce_order_actions', [ $this, 'add_order_actions' ], 10, 2 );
		add_action( 'woocommerce_order_action_' . $this->id . '_capture_payment', [ $this, 'process_capture' ] );
		add_action( 'woocommerce_order_action_' . $this->id . '_cancel_order', [ $this, 'process_cancellation' ] );

		add_filter( 'woocommerce_gateway_description', [ $this, 'show_staging_message' ], 10, 2 );
		add_filter( 'woocommerce_order_fully_refunded_status', [ $this, 'set_order_fully_refunded_status' ], 10, 2 );
	}

	/**
	 * Display payment gateway admin options.
	 *
	 * @return void
	 */
	public function admin_options(): void {
		?>
		<h2><?php esc_html_e( 'Acquired.com', 'acquired-com-for-woocommerce' ); ?></h2>

		<table class="form-table">
			<?php $this->generate_settings_html(); ?>
		</table>
		<?php
	}

	/**
	 * Check if everything is setup correctly.
	 *
	 * @return bool
	 */
	public function needs_setup(): bool {
		if ( ! $this->settings_service->is_environment_production() ) {
			return true;
		}

		return ! $this->settings_service->get_api_credentials_for_environment( 'production' );
	}

	/**
	 * Check if the payment gateway is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		if ( ! $this->settings_service->get_api_credentials() ) {
			return false;
		}

		if ( ! $this->settings_service->are_api_credentials_valid() ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Show staging message.
	 *
	 * @param string $description
	 * @param string $id
	 * @return string
	 */
	public function show_staging_message( $description, $id ): string {
		if ( $id !== $this->id ) {
			return $description;
		}

		if ( ! $this->settings_service->is_environment_staging() ) {
			return $description;
		}

		/* translators: %s is a link to the list of test cards. */
		$description .= '<p>' . sprintf( esc_html__( 'Staging mode is enabled. For test card numbers visit %s.', 'acquired-com-for-woocommerce' ), '<a href="https://docs.acquired.com/docs/test-cards" target="_blank">https://docs.acquired.com/docs/test-cards</a>' ) . '</p>';

		return $description;
	}

	/**
	 * Set order fully refunded status.
	 *
	 * @param string $status
	 * @param int $order_id
	 * @return string
	 */
	public function set_order_fully_refunded_status( string $status, int $order_id ): string {
		if ( $this->order_service->is_acfw_payment_method( $order_id ) && $this->settings_service->is_enabled( 'cancel_refunded' ) ) {
			$status = 'cancelled';
		}

		return $status;
	}

	/**
	 * Add order actions.
	 *
	 * @param array $actions
	 * @param WC_Order|null $order
	 * @return array
	 */
	public function add_order_actions( array $actions, WC_Order|null $order ): array {
		if ( ! $order instanceof WC_Order ) {
			return $actions;
		}

		if ( $this->order_service->can_be_captured( $order ) ) {
			$actions[ $this->id . '_capture_payment' ] = __( 'Capture payment', 'acquired-com-for-woocommerce' );
		}

		if ( $this->order_service->can_be_cancelled( $order ) ) {
			$actions[ $this->id . '_cancel_order' ] = __( 'Cancel order', 'acquired-com-for-woocommerce' );
		}

		return $actions;
	}

	/**
	 * Get saved payment method option HTML.
	 *
	 * @param WC_Payment_Token $token
	 * @return string
	 */
	public function get_saved_payment_method_option_html( $token ) { // phpcs:ignore Squiz.Commenting.FunctionComment.TypeHintMissing
		if ( is_add_payment_method_page() ) {
			return '';
		}

		return parent::get_saved_payment_method_option_html( $token );
	}

	/**
	 * Get new payment method option HTML.
	 *
	 * @return string
	 */
	public function get_new_payment_method_option_html() {
		if ( ( is_checkout() && ! $this->get_tokens() ) || is_add_payment_method_page() ) {
			return '';
		}

		return parent::get_new_payment_method_option_html();
	}

	/**
	 * Payment fields.
	 *
	 * @return void
	 */
	public function payment_fields() {
		parent::payment_fields();
		$this->saved_payment_methods();
	}

	/**
	 * Process payment.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment( $order_id ): array {
		try {
			$payment_link = $this->order_service->get_payment_link( $order_id );

			return [
				'result'   => 'success',
				'redirect' => $payment_link,
			];
		} catch ( Exception $exception ) {
			wc_add_notice( $exception->getMessage(), 'error' );
			return [ 'result' => 'failure' ];
		}
	}

	/**
	 * Process refund.
	 *
	 * @param int $order_id
	 * @param float|null $amount
	 * @param string $reason
	 * @return bool
	 * @throws Exception
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ): bool {
		$this->order_service->refund_order( $order_id, floatval( $amount ) );
		return true;
	}

	/**
	 * Process capture payment.
	 *
	 * @param WC_Order|null $order
	 * @return void
	 */
	public function process_capture( WC_Order|null $order ): void {
		if ( ! $order instanceof WC_Order ) {
			$this->logger_service->log( 'Order not found for capture payment action.' );
			return;
		}

		$this->admin_service->add_order_notice( $order->get_id(), 'capture_transaction', $this->order_service->capture_order( $order ) );
	}

	/**
	 * Process cancel order.
	 *
	 * @param WC_Order|null $order
	 * @return void
	 */
	public function process_cancellation( WC_Order|null $order ): void {
		if ( ! $order instanceof WC_Order ) {
			$this->logger_service->log( 'Order not found for cancel order action.' );
			return;
		}

		$this->admin_service->add_order_notice( $order->get_id(), 'cancel_order', $this->order_service->cancel_order( $order ) );
	}

	/**
	 * Add payment method.
	 *
	 * @return array
	 */
	public function add_payment_method(): array {
		try {
			$payment_link = $this->payment_method_service->get_payment_link( get_current_user_id() );

			return [
				'result'   => '', // We have to add this as an empty string, otherwise WooCommerce will throw a success notice. We can't use 'success' here because we don't know the outcome of the action since it happens offsite.
				'redirect' => $payment_link,
			];
		} catch ( Exception $exception ) {
			return [
				'result'   => 'failure',
				'redirect' => wc_get_endpoint_url( 'payment-methods' ),
			];
		}
	}

	/**
	 * Process webhook.
	 *
	 * @return void
	 */
	public function process_webhook(): void {
		try {
			if ( $webhook_data = file_get_contents( 'php://input' ) ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.Found, Squiz.PHP.DisallowMultipleAssignments.FoundInControlStructure, Generic.CodeAnalysis.AssignmentInCondition.Found
				// The hash sanitization is handled by Incoming_Data_Handler::get_webhook_data().
				$hash = wp_unslash( $_SERVER['HTTP_HASH'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$data = $this->incoming_data_handler->get_webhook_data( $webhook_data, $hash );

				switch ( $data->get_webhook_type() ) {
					case 'status_update':
						// Schedule order processing from webhook far a later time.
						$this->order_service->schedule_process_order( $data, $hash );
						break;
					case 'card_new':
						if ( $this->payment_method_service->is_for_payment_method( $data->get_order_id() ) ) {
							// Schedule save payment method from webhook for a later time.
							$this->payment_method_service->schedule_save_payment_method( $data, $hash );
						} else {
							$this->payment_method_service->save_payment_method_from_order( $data );
						}
						break;
					case 'card_update':
						$this->payment_method_service->update_payment_method( $data );
						break;
				}

				wp_send_json_success( 'Webhook processed successfully.' );
			}
		} catch ( Exception $exception ) {
			status_header( 400, 'Webhook processing failed.' );
			wp_send_json_error( sprintf( 'Webhook processing failed. Error: "%s".', $exception->getMessage() ) );
		}
	}

	/**
	 * Redirect new order.
	 *
	 * @codeCoverageIgnore
	 * @return void
	 */
	public function redirect_new_order(): void {
		try {
			$data = $this->incoming_data_handler->get_redirect_data( $_POST );  // phpcs:ignore WordPress.Security.NonceVerification.Missing

			if ( $data->is_hash_valid() ) {
				$order = $this->order_service->confirm_order( $data );
			} else {
				$order = $this->order_service->confirm_unverified_order( $data );
			}

			$redirect_url = $this->get_return_url( $order );
		} catch ( Exception $exception ) {
			$redirect_url = wc_get_checkout_url();
		}

		wp_safe_redirect( $redirect_url, 303 );
		exit;
	}

	/**
	 * Redirect new payment method.
	 *
	 * @codeCoverageIgnore
	 * @return void
	 */
	public function redirect_new_payment_method(): void {
		$redirect_url = wc_get_endpoint_url( 'payment-methods', '', wc_get_page_permalink( 'myaccount' ) );

		try {
			$data = $this->incoming_data_handler->get_redirect_data( $_POST );  // phpcs:ignore WordPress.Security.NonceVerification.Missing
			if ( $this->payment_method_service->is_transaction_success( $data->get_transaction_status() ) ) {
				$this->payment_method_service->confirm_payment_method( $data );
			}
			$status = $data->get_transaction_status();
		} catch ( Exception $exception ) {
			$status = 'error';
		}

		$redirect_url = add_query_arg(
			$this->payment_method_service->get_status_key(),
			$status,
			$redirect_url
		);

		wp_safe_redirect( $redirect_url, 303 );
		exit;
	}

	/**
	 * Validate select field.
	 *
	 * @param string $key
	 * @param string $value
	 * @return string
	 * @throws Exception
	 */
	public function validate_select_field( $key, $value ) {
		$field = $this->settings_service->get_field( $key );

		if ( empty( $field['options'] ) ) {
			throw new Exception( __( 'Invalid field.', 'acquired-com-for-woocommerce' ) );
		}

		if ( ! array_key_exists( $value, $field['options'] ) ) {
			/* translators: %1$s is the field title, %2$s are the field options. */
			throw new Exception( sprintf( __( 'Invalid value for "%1$s" field. Accepted values are: %2$s.', 'acquired-com-for-woocommerce' ), $field['title'], implode( ', ', $field['options'] ) ) );
		}

		return parent::validate_select_field( $key, $value );
	}

	/**
	 * Validate URL field.
	 *
	 * @param string $key
	 * @param string $value
	 * @return string
	 * @throws Exception
	 */
	public function validate_url_field( $key, $value ) {
		$field = $this->settings_service->get_field( $key );

		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			/* translators: %s is the field title. */
			throw new Exception( sprintf( __( 'Invalid value for "%s" field. Field value has to be URL.', 'acquired-com-for-woocommerce' ), $field['title'] ) );
		}

		return $this->validate_text_field( $key, $value );
	}

	/**
	 * Validate payment reference field.
	 *
	 * @param string $key
	 * @param string $value
	 * @return string
	 * @throws Exception
	 */
	public function validate_payment_reference_field( $key, $value ): string {
		$field = $this->settings_service->get_field( $key );
		$value = trim( $value );

		if ( ! preg_match( '/^[\w \-]{1,18}$/', $value ) ) {
			/* translators: %s is the field title. */
			throw new Exception( sprintf( __( 'Invalid value for "%s". Field can\'t be empty and must contain only letters, numbers, spaces, hyphens and be between 1-18 characters.', 'acquired-com-for-woocommerce' ), $field['title'] ) );
		}

		return $this->validate_text_field( $key, $value );
	}

	/**
	 * Validate company id field.
	 *
	 * @param string $key
	 * @param string $value
	 * @return string
	 * @throws Exception
	 */
	public function validate_company_id_field( $key, $value ): string {
		$field = $this->settings_service->get_field( $key );
		$value = trim( $value );

		if ( $value && ! preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value ) ) {
			/* translators: %s is the field title. */
			throw new Exception( sprintf( __( 'Invalid format for "%s" field.', 'acquired-com-for-woocommerce' ), $field['title'] ) );
		}

		return $this->validate_text_field( $key, $value );
	}

	/**
	 * Validate staging company ID field.
	 *
	 * @param string $key
	 * @param string $value
	 * @return string
	 * @throws Exception
	 */
	public function validate_company_id_staging_field( $key, $value ): string {
		return $this->validate_company_id_field( $key, $value );
	}

	/**
	 * Validate production company ID field.
	 *
	 * @param string $key
	 * @param string $value
	 * @return string
	 * @throws Exception
	 */
	public function validate_company_id_production_field( $key, $value ): string {
		return $this->validate_company_id_field( $key, $value );
	}
}
