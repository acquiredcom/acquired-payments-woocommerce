<?php
/**
 * Customer.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Customer class.
 *
 * @method static self make(ResponseInterface|RequestException|Exception $response, array $request_body = [], ?string $type = null)
 */
class Customer extends Response {
	/**
	 * Validate data.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function validate_data(): void {
		parent::validate_data();

		if ( ! $this->get_body_field( 'reference' ) ) {
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
		if ( $this->request_is_success() && $this->get_body_field( 'reference' ) ) {
			$this->status = 'success';
		} else {
			parent::set_status();
		}
	}
}
