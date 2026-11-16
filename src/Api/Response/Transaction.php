<?php
/**
 * Transaction.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Exception;
use DateTime;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Transaction class.
 *
 * @method static self make(ResponseInterface|RequestException|Exception $response, array $request_body = [], ?string $type = null)
 */
class Transaction extends Response {
	/**
	 * Validate data.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function validate_data(): void {
		parent::validate_data();

		if ( ! $this->get_body_field( 'transaction_id' ) || ! $this->get_body_field( 'status' ) ) {
			throw new Exception( 'Required transaction data not found.' );
		}
	}

	/**
	 * Check if transaction is success.
	 *
	 * @return bool
	 */
	private function is_transaction_success() {
		return $this->request_is_success() && 'success' === $this->get_status();
	}

	/**
	 * Get transaction ID.
	 *
	 * @return string|null
	 */
	public function get_transaction_id(): ?string {
		return $this->get_body_field( 'transaction_id' );
	}

	/**
	 * Get payment method.
	 *
	 * @return string|null
	 */
	public function get_payment_method(): ?string {
		return $this->get_body_field( 'payment_method' );
	}

	/**
	 * Get card id.
	 *
	 * @return string|null
	 */
	public function get_card_id(): ?string {
		return $this->get_body_field( 'card_id' );
	}

	/**
	 * Get reason.
	 *
	 * @return string|null
	 */
	public function get_decline_reason(): ?string {
		return ! $this->is_transaction_success() && $this->get_body_field( 'reason' ) ? $this->get_body_field( 'reason' ) : null;
	}

	/**
	 * Get created timestamp.
	 *
	 * @return int|null
	 */
	public function get_created_timestamp(): ?int {
		$created = $this->get_body_field( 'created' );

		if ( ! $created ) {
			return null;
		}

		return ( new DateTime( $created ) )->getTimestamp();
	}
}
