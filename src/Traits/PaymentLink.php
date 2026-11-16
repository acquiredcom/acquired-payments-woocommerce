<?php
/**
 * PaymentLink.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Traits;

use Exception;
use WC_Customer;
use WC_Order;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * PaymentLink trait.
 */
trait PaymentLink {
	/**
	 * Traits.
	 */
	use Order;

	/**
	 * Format order ID for payment link.
	 *
	 * @param WC_Order|WC_Customer $object
	 * @return string
	 */
	protected function format_order_id_for_payment_link( WC_Order|WC_Customer $object ): string { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound
		$key = match ( true ) {
			$object instanceof WC_Order    => $object->get_order_key(),
			$object instanceof WC_Customer => 'add_payment_method_' . wp_generate_password( 13, false ),
		};

		return sprintf( '%s-%s', $object->get_id(), $key );
	}

	/**
	 * Get ID from order ID in incoming data.
	 *
	 * @param string $incoming_data_order_id
	 * @return int|null
	 */
	protected function get_id_from_incoming_data_order_id( string $incoming_data_order_id ): ?int {
		$order_data = explode( '-', $incoming_data_order_id );

		return 2 === count( $order_data ) && isset( $order_data[0] ) ? intval( $order_data[0] ) : null;
	}

	/**
	 * Get WooCommerce order key from order ID in incoming data.
	 *
	 * @param string $incoming_data_order_id
	 * @return string|null
	 */
	protected function get_key_from_incoming_data_order_id( string $incoming_data_order_id ): ?string {
		$order_data = explode( '-', $incoming_data_order_id );

		return 2 === count( $order_data ) && isset( $order_data[1] ) ? $order_data[1] : null;
	}

	/**
	 * Get WooCommerce order from incoming data.
	 *
	 * @param string $incoming_data_order_id
	 * @return WC_Order
	 * @throws Exception
	 */
	protected function get_wc_order_from_incoming_data( string $incoming_data_order_id ): WC_Order {
		$order_id = $this->get_id_from_incoming_data_order_id( $incoming_data_order_id );

		if ( ! $order_id ) {
			throw new Exception( 'No valid order ID in incoming data.' );
		}

		$order = $this->get_wc_order( $order_id );

		if ( ! $order ) {
			throw new Exception( sprintf( 'Failed to find order. Order ID: %s.', $order_id ) );
		}

		if ( $order->get_order_key() !== $this->get_key_from_incoming_data_order_id( $incoming_data_order_id ) ) {
			throw new Exception( sprintf( 'Order key in incoming data is invalid. Order ID: %s.', $order_id ) );
		}

		return $order;
	}

	/**
	 * Get WooCommerce customer from incoming data.
	 *
	 * @param string $incoming_data_order_id
	 * @return WC_Customer
	 * @throws Exception
	 */
	protected function get_wc_customer_from_incoming_data( string $incoming_data_order_id ): WC_Customer {
		$customer_id = $this->get_id_from_incoming_data_order_id( $incoming_data_order_id );

		if ( ! $customer_id ) {
			throw new Exception( 'No valid customer ID in incoming data.' );
		}

		$customer = $this->customer_factory->get_wc_customer( $customer_id );

		if ( ! $customer ) {
			throw new Exception( sprintf( 'Failed to find customer. Customer ID: %s.', $customer_id ) );
		}

		return $customer;
	}

	/**
	 * Check if the payment link was for a payment method.
	 *
	 * @param string $incoming_data_order_id
	 * @return bool
	 */
	public function is_for_payment_method( string $incoming_data_order_id ): bool {
		$key = $this->get_key_from_incoming_data_order_id( $incoming_data_order_id );

		return $key && str_starts_with( $key, 'add_payment_method' );
	}

	/**
	 * Check if the payment link was for an order.
	 *
	 * @param string $incoming_data_order_id
	 * @return bool
	 */
	public function is_for_order( string $incoming_data_order_id ): bool {
		return ! $this->is_for_payment_method( $incoming_data_order_id );
	}

	/**
	 * Get pay URL.
	 *
	 * @param string $link_id
	 * @return string
	 */
	protected function get_pay_url( string $link_id ): string {
		return $this->settings_service->get_pay_url() . $link_id;
	}
}
