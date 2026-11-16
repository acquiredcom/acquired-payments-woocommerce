<?php
/**
 * Payment method.
 */

declare(strict_types=1);

namespace AcquiredComForWooCommerce\WooCommerce;

use AcquiredComForWooCommerce\Services\AssetsService;
use AcquiredComForWooCommerce\Services\SettingsService;
use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * PaymentMethod class.
 */
class PaymentMethod extends AbstractPaymentMethodType {
	/**
	 * Script handle.
	 *
	 * @var string
	 */
	private string $script_handle = '';

	/**
	 * Constructor.
	 *
	 * @param PaymentGateway $gateway
	 * @param AssetsService $assets_service
	 * @param SettingsService $settings_service
	 */
	public function __construct(
		private PaymentGateway $gateway,
		private AssetsService $assets_service,
		private SettingsService $settings_service
	) {
		$this->name          = $this->gateway->id;
		$this->script_handle = sprintf( '%s-block-checkout', $this->name );
	}

	/**
	 * Settings.
	 *
	 * @return void
	 */
	public function initialize(): void {
		$this->settings = $this->settings_service->get_options();
	}

	/**
	 * Check if the payment gateway is available.
	 *
	 * @return boolean
	 */
	public function is_active(): bool {
		return $this->gateway->is_available();
	}

	/**
	 * Get the payment method script handles.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles(): array {
		wp_register_script(
			$this->script_handle,
			$this->assets_service->get_asset_uri( sprintf( 'js/%s.js', $this->script_handle ) ),
			[ 'wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities' ],
			$this->assets_service->version,
			true
		);

		return [ $this->script_handle ];
	}

	/**
	 * Get the payment method data.
	 *
	 * @return array{
	 *     title: string,
	 *     description: string,
	 *     supports: string[]
	 * }
	 */
	public function get_payment_method_data(): array {
		return [
			'title'       => $this->gateway->title,
			'description' => $this->gateway->description,
			'supports'    => $this->gateway->supports,
		];
	}
}
