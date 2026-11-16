<?php
/**
 * TransactionList.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * TransactionList class.
 *
 * @method static self make(ResponseInterface|RequestException|Exception $response, array $request_body = [], ?string $type = null)
 */
class TransactionList extends Response {
	/**
	 * Validate data.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function validate_data(): void {
		parent::validate_data();

		if ( ! $this->has_body_field( 'data' ) ) {
			throw new Exception( 'Required transaction list data not found.' );
		}
	}

	/**
	 * Set status.
	 *
	 * @return void
	 */
	protected function set_status() {
		// Since the API doesn't return a status value in the response body when the transaction list request is successful we have to set it.
		if ( $this->request_is_success() && $this->has_body_field( 'data' ) ) {
			$this->status = 'success';
		} else {
			parent::set_status();
		}
	}

	/**
	 * Get the first transaction ID.
	 *
	 * @return string|null
	 */
	public function get_first_transaction_id(): ?string {
		$transactions = $this->get_body_field( 'data' );

		return $transactions ? $transactions[0]->transaction_id : null;
	}
}
