<?php
/**
 * CustomerObserver.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Observers;

use AcquiredComForWooCommerce\Services\CustomerService;
use WC_Customer;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * CustomerObserver class.
 */
class CustomerObserver implements ObserverInterface {
	/**
	 * Constructor.
	 *
	 * @param CustomerService $customer_service
	 */
	public function __construct( private CustomerService $customer_service ) {}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'woocommerce_customer_object_updated_props', [ $this, 'customer_updated' ] );
	}

	/**
	 * Update customer.
	 *
	 * @param WC_Customer $customer
	 * @return void
	 */
	public function customer_updated( WC_Customer $customer ): void {
		if ( is_account_page() && $customer->get_changes() && $customer->get_meta( '_acfw_customer_id' ) ) {
			$this->customer_service->update_customer_in_my_account( $customer );
		}
	}
}
