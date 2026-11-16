<?php
/**
 * Static analysis declarations for the TeraWallet (woo-wallet) plugin.
 *
 * Only the symbols this plugin uses are declared. Signatures match TeraWallet 1.7.2.
 */

declare( strict_types = 1 );

/**
 * Main TeraWallet class.
 */
final class Woo_Wallet {
	/**
	 * Wallet instance.
	 *
	 * @var Woo_Wallet_Wallet|null
	 */
	public $wallet = null;
}

/**
 * TeraWallet wallet class.
 */
class Woo_Wallet_Wallet {
	/**
	 * Credit the wallet when an order is cancelled.
	 *
	 * @param int $order_id
	 * @return void
	 */
	public function process_cancelled_order( $order_id ) {}
}

/**
 * Returns the main instance of Woo_Wallet.
 *
 * @return Woo_Wallet
 */
function woo_wallet() {}
