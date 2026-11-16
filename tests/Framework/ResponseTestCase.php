<?php
/**
 * ResponseTestCase.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework;

use AcquiredComForWooCommerce\Tests\Framework\Traits\ResponseMock;
use stdClass;

/**
 * Abstract test case for testing traits.
 */
abstract class ResponseTestCase extends TestCase {
	/**
	 * Traits
	 */
	use ResponseMock;


	/**
	 * Response test data.
	 *
	 * @var array
	 */
	protected array $response_test_data = [];

	/**
	 * Get response test data invalid parameters.
	 *
	 * @return array
	 */
	private function get_test_data_invalid_parameters(): array {
		return [
			(object) [
				'parameter' => 'amount',
				'reason'    => 'The amount must be a positive number.',
			],
			(object) [
				'parameter' => 'currency',
				'reason'    => 'The currency code is invalid.',
			],
		];
	}

	/**
	 * Get response test data errors.
	 *
	 * @return array
	 */
	private function get_test_data_errors(): array {
		return [
			'error_validation'    => [
				'status'             => 'error',
				'error_type'         => 'validation',
				'title'              => 'Your request parameters did not pass our validation.',
				'invalid_parameters' => $this->get_test_data_invalid_parameters(),
			],
			'error_authorization' => [
				'status'     => 'error',
				'error_type' => 'unauthorized',
				'title'      => 'Authentication with the API failed, please check your details and try again.',
			],
		];
	}

	/**
	 * Set test response data.
	 *
	 * @param array $data
	 * @return void
	 */
	protected function set_test_response_data( array $data ): void {
		$this->response_test_data = $data;
	}

	/**
	 * Get test response data.
	 *
	 * @param string $status
	 * @return stdClass
	 */
	protected function get_test_response_data( string $status ): stdClass {
		$data = array_merge( $this->response_test_data, $this->get_test_data_errors() );

		return (object) $data[ $status ] ?? (object) $data['error_authorization'];
	}
}
