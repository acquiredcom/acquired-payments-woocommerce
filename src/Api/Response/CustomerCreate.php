<?php
/**
 * CustomerCreate.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * CustomerCreate class.
 *
 * @method static self make(ResponseInterface|RequestException|Exception $response, array $request_body = [], ?string $type = null)
 */
class CustomerCreate extends Response {
	/**
	 * Validate data.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function validate_data(): void {
		parent::validate_data();

		if ( ! $this->get_body_field( 'customer_id' ) ) {
			throw new Exception( 'Required customer data not found.' );
		}
	}

	/**
	 * Set response status.
	 *
	 * @return void
	 */
	protected function set_status() {
		// Since the API doesn't return a status value in the response body when the customer request is successful we have to set it.
		if ( $this->request_is_success() && $this->get_body_field( 'customer_id' ) ) {
			$this->status = 'success';
		} else {
			parent::set_status();
		}
	}

	/**
	 * Check if customer is created.
	 *
	 * @return bool
	 */
	public function is_created(): bool {
		return $this->request_is_success() && 'success' === $this->get_status();
	}

	/**
	 * Get customer ID.
	 *
	 * @return string|null
	 */
	public function get_customer_id(): ?string {
		return $this->get_body_field( 'customer_id' );
	}
}
