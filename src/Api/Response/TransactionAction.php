<?php
/**
 * TransactionAction.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * TransactionAction class.
 *
 * @method static self make(ResponseInterface|RequestException|Exception $response, array $request_body = [], ?string $type = null)
 */
class TransactionAction extends Response {
	/**
	 * Action success statuses.
	 *
	 * @var array
	 */
	protected array $success_statuses = [ 'success', 'pending' ];

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
	 * Get transaction ID.
	 *
	 * @return string|null
	 */
	public function get_transaction_id(): ?string {
		return $this->get_body_field( 'transaction_id' );
	}

	/**
	 * Check if action is successful.
	 *
	 * @return bool
	 */
	protected function action_is_successful(): bool {
		return $this->request_is_success() && in_array( $this->get_status(), $this->success_statuses, true );
	}

	/**
	 * Get decline reason.
	 *
	 * @return string|null
	 */
	public function get_decline_reason(): ?string {
		return ! $this->action_is_successful() && $this->get_status() ? $this->get_status() : null;
	}
}
