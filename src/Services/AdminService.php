<?php
/**
 * AdminService.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Services;

use AcquiredComForWooCommerce\Traits\Order;
use WP_Screen;
use WC_Order;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * AdminService class.
 */
class AdminService {
	/**
	 * Traits
	 */
	use Order;

	/**
	 * Constructor.
	 *
	 * @param AssetsService $assets_service
	 * @param SettingsService $settings_service
	 */
	public function __construct( private AssetsService $assets_service, private SettingsService $settings_service ) {}

	/**
	 * Add admin notice.
	 *
	 * @param string $message
	 * @param string $type
	 * @param bool $dismissible
	 * @return void
	 */
	private function add_notice( string $message, string $type, bool $dismissible = false ): void {
		wp_admin_notice(
			$message,
			[
				'type'        => $type,
				'dismissible' => $dismissible,
			]
		);
	}

	/**
	 * Get current screen.
	 *
	 * @return WP_Screen|null
	 */
	private function get_current_screen(): ?WP_Screen {
		$screen = get_current_screen();

		return $screen instanceof WP_Screen ? $screen : null;
	}

	/**
	 * Check if the payment gateway is in admin.
	 *
	 * @return bool
	 */
	public function is_payment_gateway_screen(): bool {
		$screen = $this->get_current_screen();

		if ( ! ( $screen && 'woocommerce_page_wc-settings' === $screen->id ) ) {
			return false;
		}

		if ( isset( $_GET['tab'] ) && 'checkout' === sanitize_text_field( wp_unslash( $_GET['tab'] ) ) && isset( $_GET['section'] ) && sanitize_text_field( wp_unslash( $_GET['section'] ) ) === $this->settings_service->config['plugin_id'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}

		return false;
	}

	/**
	 * Get order object from order admin screen.
	 *
	 * @return WC_Order|null
	 */
	private function get_order_from_order_admin_screen(): ?WC_Order {
		$screen = $this->get_current_screen();

		if ( ! ( $screen && 'woocommerce_page_wc-orders' === $screen->id ) ) {
			return null;
		}

		if ( isset( $_GET['id'] ) && is_numeric( $_GET['id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order = $this->get_wc_order( absint( $_GET['id'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		} else {
			$order = null;
		}

		if ( $order && ! $this->is_acfw_payment_method( $order ) ) {
			return null;
		}

		return $order;
	}

	/**
	 * Add order admin notice.
	 *
	 * @param int $order_id
	 * @param string $notice_id
	 * @param string $notice_value
	 * @return void
	 */
	public function add_order_notice( int $order_id, string $notice_id, string $notice_value ): void {
		set_transient(
			sprintf( '%s_order_notice_%s', $this->settings_service->config['plugin_id'], $order_id ),
			[
				'id'    => $notice_id,
				'value' => $notice_value,
			],
			3600
		);
	}

	/**
	 * Get order admin notice.
	 *
	 * @param int $order_id
	 * @return array{
	 *     id: string,
	 *     value: string
	 * }|null
	 */
	private function get_order_notice( int $order_id ): ?array {
		$notice_value = get_transient( sprintf( '%s_order_notice_%s', $this->settings_service->config['plugin_id'], $order_id ) );
		delete_transient( sprintf( '%s_order_notice_%s', $this->settings_service->config['plugin_id'], $order_id ) );

		return $notice_value ?: null;
	}

	/**
	 * Show order admin notice.
	 *
	 * @param array $notice_data
	 * @return void
	 */
	private function show_order_notice( array $notice_data ): void {
		if ( ! $notice_data ) {
			return;
		}

		$notices = [
			'capture_transaction' => [
				'success' => __( 'Payment capture successful. Check order notes for more details.', 'acquired-com-for-woocommerce' ),
				'error'   => __( 'Payment capture failed. Check order notes for more details.', 'acquired-com-for-woocommerce' ),
			],
			'cancel_order'        => [
				'success' => __( 'Order cancellation successful.', 'acquired-com-for-woocommerce' ),
				'invalid' => __( 'Order cancellation failed. Captured orders can be cancelled on the next day.', 'acquired-com-for-woocommerce' ),
				'error'   => __( 'Order cancellation failed. Check order notes for more details.', 'acquired-com-for-woocommerce' ),
			],
		];

		$message = $notices[ $notice_data['id'] ][ $notice_data['value'] ] ?? null;
		$type    = 'success' === $notice_data['value'] ? 'success' : 'error';

		if ( ! $message ) {
			return;
		}

		$this->add_notice( $message, $type, true );
	}

	/**
	 * Admin order notices.
	 *
	 * @return void
	 */
	public function order_notice(): void {
		$order = $this->get_order_from_order_admin_screen();
		if ( ! $order ) {
			return;
		}

		$notice_data = $this->get_order_notice( $order->get_id() );
		if ( $notice_data ) {
			$this->show_order_notice( $notice_data );
		}
	}

	/**
	 * Admin settings notice.
	 *
	 * @return void
	 */
	public function settings_notice(): void {
		if ( ! $this->settings_service->get_api_credentials() ) {
			/* translators: %1$s is the opening <a> tag, %2$s is the closing </a> tag. */
			$message = sprintf( __( 'Acquired.com for WooCommerce is not fully configured. Please enter your API credentials %1$sin the settings page%2$s.', 'acquired-com-for-woocommerce' ), '<a href="' . esc_url( $this->settings_service->get_admin_settings_url() ) . '">', '</a>' );
		} elseif ( ! $this->settings_service->are_api_credentials_valid() ) {
			/* translators: %1$s is the opening <a> tag, %2$s is the closing </a> tag. */
			$message = sprintf( __( 'Your API credentials and configuration are incorrect or incomplete. You are receiving a 401 response. Please check that your API credentials are correct %1$sin the settings page%2$s and that you have whitelisted your IP address within the Acquired Hub. To get your IP address contact your hosting provider.', 'acquired-com-for-woocommerce' ), '<a href="' . esc_url( $this->settings_service->get_admin_settings_url() ) . '">', '</a>' );
		}

		if ( ! empty( $message ) ) {
			$this->add_notice( $message, 'error' );
		}

		$signing_key_message = match ( $this->settings_service->get_signing_key_issue() ) {
			/* translators: %1$s is the opening <a> tag, %2$s is the closing </a> tag. */
			'empty'                     => sprintf( __( 'Acquired.com sent a redirect without a hash, so it couldn\'t be verified. Activate a signing key in the Acquired Hub and enter it %1$sin the settings page%2$s. Until then, payment status is checked with the Acquired.com API.', 'acquired-com-for-woocommerce' ), '<a href="' . esc_url( $this->settings_service->get_admin_settings_url() ) . '">', '</a>' ),
			/* translators: %1$s is the opening <a> tag, %2$s is the closing </a> tag. */
			'using_app_key_for_signing' => sprintf( __( 'Acquired.com is signing redirects with a different key than the signing key %1$sin the settings page%2$s. Check that the signing key is activated in the Acquired Hub. Until then, payment status is checked with the Acquired.com API.', 'acquired-com-for-woocommerce' ), '<a href="' . esc_url( $this->settings_service->get_admin_settings_url() ) . '">', '</a>' ),
			/* translators: %1$s is the opening <a> tag, %2$s is the closing </a> tag. */
			'no_signing_key'            => sprintf( __( 'Acquired.com is signing redirects with a signing key that isn\'t added to the plugin. Add your signing key from the Acquired Hub %1$sin the settings page%2$s. Until then, payment status is checked with the Acquired.com API.', 'acquired-com-for-woocommerce' ), '<a href="' . esc_url( $this->settings_service->get_admin_settings_url() ) . '">', '</a>' ),
			/* translators: %1$s is the opening <a> tag, %2$s is the closing </a> tag. */
			'invalid'                   => sprintf( __( 'Redirects from Acquired.com don\'t match the signing key %1$sin the settings page%2$s. Check the key for typos and that it belongs to the selected environment. Until then, payment status is checked with the Acquired.com API.', 'acquired-com-for-woocommerce' ), '<a href="' . esc_url( $this->settings_service->get_admin_settings_url() ) . '">', '</a>' ),
			default                     => null,
		};

		if ( $signing_key_message ) {
			$this->add_notice( $signing_key_message, 'error' );
		}
	}

	/**
	 * Add order assets.
	 *
	 * @return void
	 */
	public function add_order_assets(): void {
		if ( ! $this->get_order_from_order_admin_screen() ) {
			return;
		}

		wp_enqueue_script(
			'acfw-admin-order',
			$this->assets_service->get_asset_uri( 'js/acfw-admin-order.js' ),
			[ 'wp-i18n' ],
			$this->assets_service->version,
			true
		);

		wp_set_script_translations(
			'acfw-admin-order',
			'acquired-com-for-woocommerce',
			$this->settings_service->config['dir_path'] . $this->settings_service->config['lang_dir']
		);
	}

	/**
	 * Add settings assets.
	 *
	 * @return void
	 */
	public function add_settings_assets(): void {
		if ( ! $this->is_payment_gateway_screen() ) {
			return;
		}

		wp_enqueue_script(
			'acfw-admin-settings',
			$this->assets_service->get_asset_uri( 'js/acfw-admin-settings.js' ),
			[],
			$this->assets_service->version,
			true
		);
	}
}
