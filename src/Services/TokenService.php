<?php
/**
 * TokenService.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Services;

use WC_Payment_Tokens;
use WC_Payment_Token_CC;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * TokenService class.
 */
class TokenService {
	/**
	 * Constructor.
	 *
	 * @param string $gateway_id
	 */
	public function __construct( private string $gateway_id ) {}

	/**
	 * Get a payment token by it's ID.
	 *
	 * @param int $token_id Token ID.
	 * @return WC_Payment_Token_CC|null
	 */
	public function get_token( int $token_id ): ?WC_Payment_Token_CC {
		$token = WC_Payment_Tokens::get( $token_id );

		if ( ! $token || $token->get_gateway_id() !== $this->gateway_id ) {
			return null;
		}

		return $token;
	}

	/**
	 * Get all payment tokens for a user.
	 *
	 * @param int $user_id User ID.
	 * @return WC_Payment_Token_CC[]|array
	 */
	public function get_user_tokens( int $user_id ): array {
		return WC_Payment_Tokens::get_tokens(
			[
				'user_id'    => $user_id,
				'gateway_id' => $this->gateway_id,
			]
		);
	}

	/**
	 * Get token by card ID.
	 *
	 * @param int $user_id
	 * @param string $card_id
	 * @return WC_Payment_Token_CC
	 * @throws Exception
	 */
	public function get_token_by_user_and_card_id( int $user_id, string $card_id ): WC_Payment_Token_CC {
		foreach ( $this->get_user_tokens( $user_id ) as $token ) :
			if ( $token->get_token() === $card_id ) {
				return $token;
			}
		endforeach;

		throw new Exception( 'Token not found.' );
	}

	/**
	 * Check if payment token exists.
	 *
	 * @param int $user_id
	 * @param string $card_id
	 * @return bool
	 */
	public function payment_token_exists( int $user_id, string $card_id ): bool {
		try {
			$this->get_token_by_user_and_card_id( $user_id, $card_id );
			return true;
		} catch ( Exception $exception ) {
			return false;
		}
	}
}
