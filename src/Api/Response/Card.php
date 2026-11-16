<?php
/**
 * Card.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Card class.
 *
 * @method static self make(ResponseInterface|RequestException|Exception $response, array $request_body = [], ?string $type = null)
 */
class Card extends Response {
	/**
	 * Validate data.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function validate_data(): void {
		parent::validate_data();

		if ( ! $this->get_body_field( 'card' ) || ! $this->get_body_field( 'customer_id' ) ) {
			throw new Exception( 'Required card data not found.' );
		}

		foreach ( [ 'holder_name', 'scheme', 'number', 'expiry_month', 'expiry_year' ] as $field ) :
			if ( empty( $this->get_body_field( 'card' )->{$field} ) ) {
				throw new Exception( sprintf( 'Required card field "%s" not found.', $field ) );
			}
		endforeach;
	}

	/**
	 * Set response status.
	 *
	 * @return void
	 */
	protected function set_status() {
		// Since the API doesn't return a status value in the response body when the card request is successful we have to set it.
		if ( $this->request_is_success() && $this->get_body_field( 'card' ) ) {
			$this->status = 'success';
		} else {
			parent::set_status();
		}
	}

	/**
	 * Get card data.
	 *
	 * @return object|null
	 */
	public function get_card_data(): ?object {
		return $this->get_body_field( 'card' );
	}

	/**
	 * Get card ID.
	 *
	 * @return string|null
	 */
	public function get_card_id(): ?string {
		return $this->get_body_field( 'card_id' );
	}

	/**
	 * Get customer ID.
	 *
	 * @return string|null
	 */
	public function get_customer_id(): ?string {
		return $this->get_body_field( 'customer_id' );
	}

	/**
	 * Check if card is active.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return $this->request_is_success() && $this->get_body_field( 'is_active' );
	}
}
