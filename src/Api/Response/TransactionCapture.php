<?php
/**
 * TransactionCapture.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * TransactionCapture class.
 *
 * @method static self make(ResponseInterface|RequestException|Exception $response, array $request_body = [], ?string $type = null)
 */
class TransactionCapture extends TransactionAction {
	/**
	 * Check if transaction is captured.
	 *
	 * @return bool
	 */
	public function is_captured(): bool {
		return $this->action_is_successful();
	}
}
