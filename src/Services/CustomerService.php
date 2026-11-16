<?php
/**
 * CustomerService.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Services;

use AcquiredComForWooCommerce\Api\ApiClient;
use AcquiredComForWooCommerce\Factories\CustomerFactory;
use Exception;
use WC_Customer;
use WC_Order;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * CustomerService class.
 */
class CustomerService {
	/**
	 * Constructor.
	 *
	 * @param ApiClient $api_client
	 * @param LoggerService $logger_service
	 * @param CustomerFactory $customer_factory
	 */
	public function __construct(
		private ApiClient $api_client,
		private LoggerService $logger_service,
		private CustomerFactory $customer_factory
	) {}

	/**
	 * Truncate string to specified length.
	 *
	 * @param string $value
	 * @param int $max_length
	 * @return string
	 */
	private function truncate_to_length( string $value, int $max_length ): string {
		return strlen( $value ) > $max_length ? substr( $value, 0, $max_length ) : $value;
	}

	/**
	 * Validate email.
	 *
	 * @param string $value
	 * @return bool
	 */
	private function validate_email( string $value ): bool {
		return (bool) filter_var( $value, FILTER_VALIDATE_EMAIL );
	}

	/**
	 * Validate name.
	 *
	 * @param string $value
	 * @return bool
	 */
	private function validate_name( string $value ): bool {
		return (bool) preg_match( '/^[\p{L}\.\- `\']++$/u', $value );
	}

	/**
	 * Validate address.
	 *
	 * @param string $value
	 * @return bool
	 */
	private function validate_address( string $value ): bool {
		return (bool) preg_match( '/^[\p{L}\p{N}\.,\/\-\& ]++$/u', $value );
	}

	/**
	 * Format address basic data.
	 *
	 * @param array $address_data
	 * @return array{
	 *     first_name: string,
	 *     last_name: string,
	 *     email: string
	 * }|null
	 */
	private function format_basic_address_data( array $address_data ): ?array {
		$customer_data = [
			'first_name' => $address_data['first_name'] ?? '',
			'last_name'  => $address_data['last_name'] ?? '',
			'email'      => $address_data['email'] ?? '',
		];

		foreach ( $customer_data as $field => $value ) :
			$is_valid = false;

			switch ( $field ) {
				case 'first_name':
				case 'last_name':
					$is_valid = $this->validate_name( $value );
					if ( $is_valid ) {
						$customer_data[ $field ] = $this->truncate_to_length( $value, 22 );
					}
					break;
				case 'email':
					$is_valid = $this->validate_email( $value );
					break;
			}

			if ( ! $is_valid ) {
				$customer_data[ $field ] = '';
			}
		endforeach;

		if ( empty( $customer_data['email'] ) ) {
			return null;
		}

		return $customer_data;
	}

	/**
	 * Format address data.
	 *
	 * @param array $address_data
	 * @return array{
	 *     line_1: string,
	 *     line_2: string,
	 *     city: string,
	 *     postcode: string,
	 *     country_code: string,
	 *     state?: string
	 * }
	 */
	private function format_address_data( array $address_data ): array {
		$formatted_address = [
			'line_1'       => $address_data['address_1'] ?? '',
			'line_2'       => $address_data['address_2'] ?? '',
			'city'         => $address_data['city'] ?? '',
			'postcode'     => $address_data['postcode'] ?? '',
			'country_code' => strtolower( $address_data['country'] ?? '' ),
		];

		if ( 'us' === $formatted_address['country_code'] && ! empty( $address_data['state'] ) ) {
			$formatted_address['state'] = strtolower( $address_data['state'] );
		}

		foreach ( $formatted_address as $field => $value ) :
			$is_valid = false;

			switch ( $field ) {
				case 'line_1':
				case 'line_2':
				case 'city':
					$is_valid = $this->validate_address( $value );
					if ( $is_valid ) {
						$formatted_address[ $field ] = $this->truncate_to_length( $value, 'city' === $field ? 40 : 50 );
					}
					break;
				case 'postcode':
				case 'country_code':
				case 'state':
					$is_valid = true;
					if ( 'postcode' === $field ) {
						$formatted_address[ $field ] = $this->truncate_to_length( $value, 40 );
					}
					break;
			}

			if ( ! $is_valid ) {
				$formatted_address[ $field ] = '';
			}
		endforeach;

		return $formatted_address;
	}

	/**
	 * Compare billing and shipping addresses.
	 *
	 * @param array $billing_address
	 * @param array $shipping_address
	 * @return bool
	 */
	private function addresses_match( array $billing_address, array $shipping_address ): bool {
		unset( $billing_address['email'], $billing_address['phone'], $shipping_address['email'], $shipping_address['phone'] );

		ksort( $billing_address );
		ksort( $shipping_address );

		return $billing_address === $shipping_address;
	}

	/**
	 * Get address data formatted.
	 *
	 * @param array $billing_address
	 * @param array $shipping_address
	 * @param bool $add_email_to_address
	 * @return array{
	 *     first_name?: string,
	 *     last_name?: string,
	 *     email: string,
	 *     billing: array{
	 *         address?: array{
	 *             line_1: string,
	 *             line_2: string,
	 *             city: string,
	 *             postcode: string,
	 *             country_code: string,
	 *             state?: string
	 *         },
	 *         email?: string
	 *     },
	 *     shipping?: array{
	 *         address_match?: bool,
	 *         address?: array{
	 *             line_1: string,
	 *             line_2: string,
	 *             city: string,
	 *             postcode: string,
	 *             country_code: string,
	 *             state?: string
	 *         },
	 *         email?: string
	 *     }
	 * }
	 * @throws Exception
	 */
	private function get_address_data_formatted( array $billing_address, array $shipping_address = [], bool $add_email_to_address = false ): array {
		if ( ! $billing_address ) {
			throw new Exception( 'Billing address is empty.' );
		}

		$customer_data = $this->format_basic_address_data( $billing_address );

		if ( ! $customer_data ) {
			throw new Exception( 'Customer data is not valid.' );
		}

		$billing_address = $this->format_address_data( $billing_address );

		$customer_data['billing']['address'] = $billing_address;
		if ( $add_email_to_address ) {
			$customer_data['billing']['email'] = $customer_data['email'];
		}

		$customer_data['shipping']['address_match'] = true;

		if ( $shipping_address ) {
			$shipping_address = $this->format_address_data( $shipping_address );

			if ( $shipping_address && ! $this->addresses_match( $billing_address, $shipping_address ) ) {
				$customer_data['shipping']['address']       = $shipping_address;
				$customer_data['shipping']['address_match'] = false;
				if ( $add_email_to_address ) {
					$customer_data['shipping']['email'] = $customer_data['email'];
				}
			}
		}

		return $customer_data;
	}

	/**
	 * Get customer address data.
	 *
	 * @param WC_Customer $customer
	 * @return array{
	 *     billing: array{
	 *         email: string
	 *     },
	 *     shipping: array{
	 *         address_match: bool
	 *     }
	 * }|array{
	 *     first_name: string,
	 *     last_name: string,
	 *     email: string,
	 *     billing: array{
	 *         address: array{
	 *             line_1: string,
	 *             line_2: string,
	 *             city: string,
	 *             postcode: string,
	 *             country_code: string,
	 *             state?: string
	 *         },
	 *         email: string
	 *     },
	 *     shipping: array{
	 *         address_match: bool,
	 *         address?: array{
	 *             line_1: string,
	 *             line_2: string,
	 *             city: string,
	 *             postcode: string,
	 *             country_code: string,
	 *             state?: string
	 *         },
	 *         email?: string
	 *     }
	 * }
	 * @throws Exception
	 */
	private function get_customer_address_data( WC_Customer $customer ): array {
		$billing_address  = $customer->get_billing();
		$shipping_address = $customer->has_shipping_address() ? $customer->get_shipping() : [];

		// In some cases users can register with just their email and not set their billing address. For those cases add the user email to the billing address.
		if ( empty( $billing_address['email'] ) && $customer->get_email() ) {
			$billing_address['email'] = $customer->get_email();
		}

		return $this->get_address_data_formatted(
			$billing_address,
			$shipping_address,
			true
		);
	}

	/**
	 * Get customer address data from WC_Order.
	 *
	 * @param WC_Order $order
	 * @param null|bool $include_email
	 * @return array
	 * @throws Exception
	 */
	private function get_customer_address_data_from_wc_order( WC_Order $order, null|bool $include_email = null ): array {
		return $this->get_address_data_formatted(
			$order->get_address( 'billing' ),
			$order->has_shipping_address() ? $order->get_address( 'shipping' ) : [],
			$include_email ?? (bool) $order->get_user_id()
		);
	}

	/**
	 * Create customer.
	 *
	 * @param WC_Customer $customer
	 * @param array $customer_data
	 * @return WC_Customer|null
	 */
	private function create_customer( WC_Customer $customer, array $customer_data ): ?WC_Customer {
		$response = $this->api_client->create_customer( $customer_data );

		if ( $response->is_created() ) {
			$customer->update_meta_data( '_acfw_customer_id', $response->get_customer_id() );
			$customer->save();

			$this->logger_service->log( 'Customer creation successful.', 'debug', $response->get_log_data() );
			return $customer;
		} else {
			$this->logger_service->log( 'Customer creation failed.', 'error', $response->get_log_data() );
			return null;
		}
	}

	/**
	 * Update customer.
	 *
	 * @param WC_Customer $customer
	 * @param array $customer_data
	 * @return WC_Customer|null
	 */
	private function update_customer( WC_Customer $customer, array $customer_data ): ?WC_Customer {
		$customer_id = $customer->get_meta( '_acfw_customer_id' );

		if ( ! $customer_id ) {
			$this->logger_service->log( 'Customer ID not found.', 'error' );
			return null;
		}

		$response = $this->api_client->update_customer( $customer_id, $customer_data );

		if ( $response->request_is_success() ) {
			$this->logger_service->log( 'Customer update successful.', 'debug', $response->get_log_data() );
			return $customer;
		} else {
			$this->logger_service->log( 'Customer update failed.', 'error', $response->get_log_data() );
			return null;
		}
	}

	/**
	 * Create or update customer for checkout.
	 *
	 * @param WC_Order $order
	 * @return WC_Customer|null
	 */
	private function create_or_update_customer_for_checkout( WC_Order $order ): ?WC_Customer {
		try {
			$customer      = $this->customer_factory->get_wc_customer( $order->get_user_id() );
			$customer_data = $this->get_customer_address_data_from_wc_order( $order );
		} catch ( Exception $exception ) {
			$this->logger_service->log( sprintf( 'Creating/updating customer data for checkout failed. Order ID: %s. Error: "%s".', $order->get_id(), $exception->getMessage() ), 'error' );
			return null;
		}

		$customer_id = $customer->get_meta( '_acfw_customer_id' );

		if ( $customer_id ) {
			$customer = $this->update_customer( $customer, $customer_data );
		} else {
			$customer = $this->create_customer( $customer, $customer_data );
		}

		return $customer ?? null;
	}

	/**
	 * Get customer data for quest checkout.
	 *
	 * @param WC_Order $order
	 * @return array
	 */
	private function get_customer_data_for_guest_checkout( WC_Order $order ): array {
		$customer_data = [];

		try {
			$customer_data = $this->get_customer_address_data_from_wc_order( $order, false );
			$this->logger_service->log( sprintf( 'Creating customer data for guest checkout successful. Order ID: %s.', $order->get_id() ), 'debug' );
		} catch ( Exception $exception ) {
			$this->logger_service->log( sprintf( 'Creating customer data for guest checkout failed. Order ID: %s. Error: "%s".', $order->get_id(), $exception->getMessage() ), 'error' );
		}

		return $customer_data;
	}

	/**
	 * Get customer data for checkout.
	 *
	 * @param WC_Order $order
	 * @return array{
	 *     customer_id: string
	 * }|array{
	 *     first_name: string,
	 *     last_name: string,
	 *     email: string,
	 *     billing: array{
	 *         address: array{
	 *             line_1: string,
	 *             line_2: string,
	 *             city: string,
	 *             postcode: string,
	 *             country_code: string,
	 *             state?: string
	 *         },
	 *         email?: string
	 *     },
	 *     shipping?: array{
	 *         address_match: bool,
	 *         address?: array{
	 *             line_1: string,
	 *             line_2: string,
	 *             city: string,
	 *             postcode: string,
	 *             country_code: string,
	 *             state?: string
	 *         },
	 *         email?: string
	 *     }
	 * }|array{}
	 */
	public function get_customer_data_for_checkout( WC_Order $order ): array {
		if ( ! $order->get_customer_id() ) {
			return $this->get_customer_data_for_guest_checkout( $order );
		}

		$customer = $this->create_or_update_customer_for_checkout( $order );

		if ( $customer ) {
			return [ 'customer_id' => $customer->get_meta( '_acfw_customer_id' ) ];
		} else {
			return $this->get_customer_data_for_guest_checkout( $order );
		}
	}

	/**
	 * Update customer in my account.
	 *
	 * @param WC_Customer $customer
	 * @return void
	 */
	public function update_customer_in_my_account( WC_Customer $customer ): void {
		try {
			$customer_data = $this->get_customer_address_data( $customer );
			$this->update_customer( $customer, $customer_data );
		} catch ( Exception $exception ) {
			$this->logger_service->log( sprintf( 'Updating customer data in my account failed. User ID: %s. Error: "%s".', $customer->get_id(), $exception->getMessage() ), 'error' );
		}
	}

	/**
	 * Get or create customer for new payment method.
	 *
	 * @param int $user_id
	 * @return WC_Customer|null
	 */
	private function get_or_create_customer_for_new_payment_method( int $user_id ): ?WC_Customer {
		try {
			$customer      = $this->customer_factory->get_wc_customer( $user_id );
			$customer_data = $this->get_customer_address_data( $customer );
		} catch ( Exception $exception ) {
			$this->logger_service->log( sprintf( 'Getting customer for new payment method failed. User ID: %s. Error: "%s".', $user_id, $exception->getMessage() ), 'error' );
			return null;
		}

		$customer_id = $customer->get_meta( '_acfw_customer_id' );

		if ( ! $customer_id ) {
			$customer = $this->create_customer( $customer, $customer_data );
		}

		return $customer ?? null;
	}

	/**
	 * Get customer data for new payment method.
	 *
	 * @param int $user_id
	 * @return array{customer_id: string}|array{}
	 */
	public function get_customer_data_for_new_payment_method( int $user_id ): array {
		$customer = $this->get_or_create_customer_for_new_payment_method( $user_id );

		return $customer ? [ 'customer_id' => $customer->get_meta( '_acfw_customer_id' ) ] : [];
	}

	/**
	 * Get customer from customer ID.
	 *
	 * @param string $customer_id
	 * @return WC_Customer
	 * @throws Exception
	 */
	public function get_customer_from_customer_id( string $customer_id ): WC_Customer {
		$user_data = get_users(
			[
				'meta_key'   => '_acfw_customer_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $customer_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'number'     => 1,
				'fields'     => 'ID',
			]
		);

		if ( empty( $user_data ) ) {
			throw new Exception( 'User not found.' );
		}

		return $this->customer_factory->get_wc_customer( (int) $user_data[0] );
	}
}
