<?php
/**
 * Data.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\IncomingData;

use stdClass;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Data class.
 */
abstract class Data {
	/**
	 * Type
	 *
	 * @var string
	 */
	protected string $type = '';

	/**
	 * Transaction ID.
	 *
	 * @var string
	 */
	private string $transaction_id = '';

	/**
	 * Transaction status.
	 *
	 * @var string
	 */
	private string $transaction_status = '';

	/**
	 * Order ID.
	 *
	 * @var string
	 */
	private string $order_id = '';

	/**
	 * Timestamp.
	 *
	 * @var int
	 */
	private int $timestamp = 0;

	/**
	 * Card ID.
	 *
	 * @var string
	 */
	private string $card_id = '';

	/**
	 * Incoming data.
	 *
	 * @var array|stdClass
	 */
	private array|stdClass $incoming_data;

	/**
	 * Set data type.
	 *
	 * @param string $type
	 * @return void
	 */
	protected function set_type( string $type ): void {
		$this->type = $type;
	}

	/**
	 * Set transaction data.
	 *
	 * @param string $transaction_id
	 * @param string $transaction_status
	 * @param string $order_id
	 * @return void
	 */
	protected function set_transaction_data( string $transaction_id, string $transaction_status, string $order_id ): void {
		$this->transaction_id     = $transaction_id;
		$this->transaction_status = $transaction_status;
		$this->order_id           = $order_id;
	}

	/**
	 * Set timestamp.
	 *
	 * @param int $timestamp
	 * @return void
	 */
	protected function set_timestamp( int $timestamp ): void {
		$this->timestamp = $timestamp;
	}

	/**
	 * Set incoming data.
	 *
	 * @param array|stdClass $incoming_data
	 * @return void
	 */
	protected function set_incoming_data( array|stdClass $incoming_data ): void {
		$this->incoming_data = $incoming_data;
	}

	/**
	 * Set card ID.
	 *
	 * @param string $card_id
	 * @return void
	 */
	public function set_card_id( string $card_id ): void {
		$this->card_id = $card_id;
	}

	/**
	 * Get data type.
	 *
	 * @return string
	 */
	public function get_type(): string {
		return $this->type;
	}

	/**
	 * Get transaction ID.
	 *
	 * @return string
	 */
	public function get_transaction_id(): string {
		return $this->transaction_id;
	}

	/**
	 * Get transaction status.
	 *
	 * @return string
	 */
	public function get_transaction_status(): string {
		return $this->transaction_status;
	}

	/**
	 * Get order ID.
	 *
	 * @return string
	 */
	public function get_order_id(): string {
		return $this->order_id;
	}

	/**
	 * Get timestamp.
	 *
	 * @return int
	 */
	public function get_timestamp(): int {
		return $this->timestamp;
	}

	/**
	 * Get incoming data.
	 *
	 * @return array|stdClass
	 */
	public function get_incoming_data(): array|stdClass {
		return $this->incoming_data;
	}

	/**
	 * Get card ID.
	 *
	 * @return string
	 */
	public function get_card_id(): string {
		return $this->card_id;
	}

	/**
	 * Get log data.
	 *
	 * @return array
	 */
	public function get_log_data(): array {
		return [ sprintf( 'incoming-%s-data', $this->get_type() ) => $this->get_incoming_data() ];
	}
}
