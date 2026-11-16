<?php
/**
 * WebhookData.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\IncomingData;

use stdClass;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * WebhookData class.
 */
class WebhookData extends Data {
	/**
	 * Webhook type.
	 *
	 * @var string
	 */
	private string $webhook_type = '';

	/**
	 * Webhook ID.
	 *
	 * @var string
	 */
	private string $webhook_id = '';

	/**
	 * Set webhook type and ID.
	 *
	 * @param stdClass $incoming_data
	 * @return void
	 */
	private function set_webhook_data( stdClass $incoming_data ): void {
		$this->webhook_type = $incoming_data->webhook_type;
		$this->webhook_id   = $incoming_data->webhook_id;

		if ( in_array( $this->get_webhook_type(), [ 'card_new', 'card_update' ], true ) ) {
			$this->set_card_id( $incoming_data->webhook_body->card_id );
		}
	}

	/**
	 * Constructor.
	 *
	 * @param stdClass $incoming_data
	 */
	public function __construct( stdClass $incoming_data ) {
		$this->set_type( 'webhook' );
		$this->set_incoming_data( $incoming_data );
		$this->set_timestamp( $incoming_data->timestamp );
		$this->set_webhook_data( $incoming_data );

		if ( in_array( $this->get_webhook_type(), [ 'status_update', 'card_new' ], true ) ) {
			$this->set_transaction_data( $incoming_data->webhook_body->transaction_id, $incoming_data->webhook_body->status, $incoming_data->webhook_body->order_id );
		}
	}

	/**
	 * Get webhook type.
	 *
	 * @return string
	 */
	public function get_webhook_type(): string {
		return $this->webhook_type;
	}

	/**
	 * Get webhook ID.
	 *
	 * @return string
	 */
	public function get_webhook_id(): string {
		return $this->webhook_id;
	}
}
