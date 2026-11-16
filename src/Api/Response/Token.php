<?php
/**
 * Token.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Token class.
 *
 * @method static self make(ResponseInterface|RequestException|Exception $response, array $request_body = [], ?string $type = null)
 */
class Token extends Response {
	/**
	 * Validate data.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function validate_data(): void {
		parent::validate_data();

		if ( ! $this->get_body_field( 'token_type' ) || ! $this->get_body_field( 'access_token' ) ) {
			throw new Exception( 'Access token creation failed.' );
		}
	}

	/**
	 * Set response status.
	 *
	 * @return void
	 */
	protected function set_status() {
		// Since the API doesn't return a status value in the response body when the token request is successful we have to set it.
		if ( $this->request_is_success() && $this->get_body_field( 'token_type' ) && $this->get_body_field( 'access_token' ) ) {
			$this->status = 'success';
		} else {
			parent::set_status();
		}
	}

	/**
	 * Get payment link ID.
	 *
	 * @return string|null
	 */
	public function get_token_formatted(): ?string {
		return $this->request_is_success() ? sprintf( '%s %s', $this->get_body_field( 'token_type' ), $this->get_body_field( 'access_token' ) ) : null;
	}

	/**
	 * Get log data.
	 *
	 * @return array{
	 *     status: string,
	 *     response_code: int,
	 *     reason_phrase: string,
	 *     error_message?: string
	 * }
	 */
	public function get_log_data(): array {
		$data = parent::get_log_data();

		unset( $data['request_body'], $data['response_body'] );

		return $data;
	}
}
