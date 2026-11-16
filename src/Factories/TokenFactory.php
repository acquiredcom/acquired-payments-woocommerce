<?php
/**
 * TokenFactory.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Factories;

use WC_Payment_Token_CC;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * TokenFactory class.
 */
class TokenFactory {
	/**
	 * Get an instance of WooCommerce payment token object.
	 *
	 * @return WC_Payment_Token_CC WooCommerce payment token instance.
	 */
	public function get_wc_payment_token(): WC_Payment_Token_CC {
		return new WC_Payment_Token_CC();
	}
}
