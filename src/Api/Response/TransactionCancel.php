<?php
/**
 * TransactionCancel.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * TransactionCancel class.
 *
 * @method static self make(ResponseInterface|RequestException|Exception $response, array $request_body = [], ?string $type = null)
 */
class TransactionCancel extends TransactionAction {
	/**
	 * Check if transaction is cancelled.
	 *
	 * @return bool
	 */
	public function is_cancelled(): bool {
		return $this->action_is_successful();
	}
}
