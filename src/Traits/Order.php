<?php
/**
 * Order.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Traits;

use WC_Order;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Order trait.
 */
trait Order {
	/**
	 * Get WooCommerce order object.
	 *
	 * @param int $order_id
	 * @return WC_Order|null
	 */
	protected function get_wc_order( int $order_id ): ?WC_Order {
		$order = wc_get_order( $order_id );

		return $order instanceof WC_Order ? $order : null;
	}

	/**
	 * Check if the order is made with Acquired.com for WooCommerce payment method.
	 *
	 * @param int|WC_Order $order
	 * @return bool
	 */
	public function is_acfw_payment_method( int|WC_Order $order ): bool {
		$order = $order instanceof WC_Order ? $order : $this->get_wc_order( $order );

		if ( ! $order ) {
			return false;
		}

		return $order->get_payment_method() === $this->settings_service->config['plugin_id'];
	}
}
