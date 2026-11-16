<?php
/**
 * PaymentLink.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * PaymentLink class.
 *
 * @method static self make(ResponseInterface|RequestException|Exception $response, array $request_body = [], ?string $type = null)
 */
class PaymentLink extends Response {
	/**
	 * Validate data.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function validate_data(): void {
		parent::validate_data();

		if ( ! $this->get_body_field( 'link_id' ) ) {
			throw new Exception( 'Payment link ID not found in response.' );
		}
	}

	/**
	 * Get payment link ID.
	 *
	 * @return string|null
	 */
	public function get_link_id(): ?string {
		return $this->get_body_field( 'link_id' );
	}
}
