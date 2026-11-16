<?php
/**
 * RedirectData.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\IncomingData;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * RedirectData class.
 */
class RedirectData extends Data {
	/**
	 * Hash status.
	 *
	 * @var string
	 */
	private string $hash_status = '';

	/**
	 * Set hash status.
	 *
	 * @param string $hash_status
	 * @return void
	 */
	private function set_hash_status( string $hash_status ): void {
		$this->hash_status = $hash_status;
	}

	/**
	 * Constructor.
	 *
	 * @param array $incoming_data
	 * @param string $hash_status
	 */
	public function __construct( array $incoming_data, string $hash_status ) {
		$this->set_type( 'redirect' );
		$this->set_incoming_data( $incoming_data );
		$this->set_transaction_data( $incoming_data['transaction_id'], $incoming_data['status'], $incoming_data['order_id'] );
		$this->set_timestamp( (int) $incoming_data['timestamp'] );
		$this->set_hash_status( $hash_status );
	}

	/**
	 * Get hash status.
	 *
	 * @return string
	 */
	public function get_hash_status(): string {
		return $this->hash_status;
	}

	/**
	 * Check if the hash is valid.
	 *
	 * @return bool
	 */
	public function is_hash_valid(): bool {
		return 'valid' === $this->hash_status;
	}
}
