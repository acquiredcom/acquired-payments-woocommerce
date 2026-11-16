<?php
/**
 * CustomerFactory.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Factories;

use WC_Customer;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * CustomerFactory class.
 */
class CustomerFactory {
	/**
	 * Get an instance of WooCommerce customer object.
	 *
	 * @param int $customer_id
	 * @return WC_Customer|null
	 */
	public function get_wc_customer( int $customer_id ): ?WC_Customer {
		try {
			return new WC_Customer( $customer_id );
		} catch ( Exception $exception ) {
			return null;
		}
	}
}
