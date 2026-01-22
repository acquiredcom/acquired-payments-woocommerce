<?php
/**
 * IncomingDataTestData.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework\Traits;

use stdClass;

/**
 * IncomingDataTestData.
 */
trait IncomingDataTestData {
	/**
	 * Hash key for redirect and webhook calculations.
	 *
	 * @var string
	 */
	private string $hash_key = '123456789';

	/**
	 * Set hash key.
	 *
	 * @param string $hash_key
	 * @return void
	 */
	protected function set_hash_key( string $hash_key ) : void {
		$this->hash_key = $hash_key;
	}

	/**
	 * Convert array to stdClass recursively.
	 *
	 * @param array $array Array to convert.
	 * @return stdClass
	 */
	private function array_to_object( array $array ) : stdClass {
		$object = new stdClass();

		foreach ( $array as $key => $value ) :
			$object->$key = is_array( $value ) ? $this->array_to_object( $value ) : $value;
		endforeach;

		return $object;
	}

	/**
	 * Calculate test redirect hash.
	 *
	 * @param array{
	 *     status: string,
	 *     transaction_id: string,
	 *     order_id: string,
	 *     timestamp: string
	 * }
	 * @return string
	 */
	protected function calculate_test_redirect_hash( array $data ) : string {
		$first_hash = hash( 'sha256', $data['status'] . $data['transaction_id'] . $data['order_id'] . $data['timestamp'] );

		return hash( 'sha256', $first_hash . $this->hash_key );
	}

	/**
	 * Calculate test webhook hash.
	 *
	 * @param stdClass $data
	 * @return string
	 */
	protected function calculate_test_webhook_hash( stdClass $data ) : string {
		return hash_hmac( 'sha256', preg_replace( '/\s+/', '', json_encode( $data ) ), $this->hash_key );
	}

	/**
	 * Get test redirect data.
	 *
	 * @return array{
	 *     status: string,
	 *     transaction_id: string,
	 *     order_id: string,
	 *     order_active: string,
	 *     timestamp: string,
	 *     hash: string,
	 * }
	 */
	protected function get_test_redirect_data() : array {
		$data = [
			'status'         => 'success',
			'transaction_id' => 'transaction_123',
			'order_id'       => 'order_456',
			'order_active'   => 'false',
			'timestamp'      => '1621234567',
		];

		$data['hash'] = $this->calculate_test_redirect_hash( $data );

		return $data;
	}

	/**
	 * Get test webhook data.
	 *
	 * @param string $type
	 * @return stdClass{
	 *     webhook_type: string,
	 *     webhook_id: string,
	 *     timestamp: int,
	 *     webhook_body: stdClass{
	 *         transaction_id?: string,
	 *         status?: string,
	 *         order_id?: string,
	 *         card_id?: string,
	 *         update_type?: string,
	 *         update_detail?: string,
	 *         card?: stdClass{
	 *             holder_name: string,
	 *             scheme: string,
	 *             number: string,
	 *             expiry_month: int,
	 *             expiry_year: int
	 *         }
	 *     }
	 * }
	 */
	protected function get_test_webhook_data( string $type ) : stdClass {
		$data = [
			'status_update' => [
				'webhook_type' => 'status_update',
				'webhook_id'   => 'webhook_123',
				'timestamp'    => 1621234567,
				'webhook_body' => [
					'transaction_id' => 'test_transaction_456',
					'status'         => 'success',
					'order_id'       => 'order_789',
				],
			],
			'card_new'      => [
				'webhook_type' => 'card_new',
				'webhook_id'   => 'webhook_123',
				'timestamp'    => 1621234567,
				'webhook_body' => [
					'transaction_id' => 'test_transaction_456',
					'status'         => 'success',
					'order_id'       => 'order_789',
					'card_id'        => 'card_1011',
				],
			],
			'card_update'   => [
				'webhook_type' => 'card_update',
				'webhook_id'   => 'webhook_123',
				'timestamp'    => 1621234567,
				'webhook_body' => [
					'card_id'       => 'card_1011',
					'update_type'   => 'account_updater',
					'update_detail' => 'card_updated',
					'card'          => [
						'holder_name'  => 'John Doe',
						'scheme'       => 'visa',
						'number'       => '1234',
						'expiry_month' => 12,
						'expiry_year'  => 66,
					],
				],
			],
		];

		$webhook_data = $data[ $type ] ?? $data['status_update'];

		return $this->array_to_object( $webhook_data );
	}

	/**
	 * Calculate test redirect hash with optional alternative key for multi-hash testing.
	 *
	 * @param array{
	 *     status: string,
	 *     transaction_id: string,
	 *     order_id: string,
	 *     timestamp: string
	 * } $data
	 * @param string|null $alt_key Optional alternative key to generate secondary hash
	 * @return string Single hash or comma-delimited hashes if alt_key provided
	 */
	protected function calculate_test_redirect_hash_multi( array $data, ?string $alt_key = null ) : string {
		$first_hash = hash( 'sha256', $data['status'] . $data['transaction_id'] . $data['order_id'] . $data['timestamp'] );
		$primary    = hash( 'sha256', $first_hash . $this->hash_key );

		if ( $alt_key ) {
			$secondary = hash( 'sha256', $first_hash . $alt_key );
			return "{$primary},{$secondary}";
		}

		return $primary;
	}

	/**
	 * Calculate test webhook hash with optional alternative key for multi-hash testing.
	 *
	 * @param stdClass $data
	 * @param string|null $alt_key Optional alternative key to generate secondary hash
	 * @return string Single hash or comma-delimited hashes if alt_key provided
	 */
	protected function calculate_test_webhook_hash_multi( stdClass $data, ?string $alt_key = null ) : string {
		$sanitized_data = preg_replace( '/\s+/', '', json_encode( $data ) );
		$primary        = hash_hmac( 'sha256', $sanitized_data, $this->hash_key );

		if ( $alt_key ) {
			$secondary = hash_hmac( 'sha256', $sanitized_data, $alt_key );
			return "{$primary},{$secondary}";
		}

		return $primary;
	}

	/**
	 * Get test redirect data with optional multi-hash for key rotation testing.
	 *
	 * @param string|null $alt_key Optional alternative key for multi-hash
	 * @return array{
	 *     status: string,
	 *     transaction_id: string,
	 *     order_id: string,
	 *     order_active: string,
	 *     timestamp: string,
	 *     hash: string,
	 * }
	 */
	protected function get_test_redirect_data_multi( ?string $alt_key = null ) : array {
		$data = [
			'status'         => 'success',
			'transaction_id' => 'transaction_123',
			'order_id'       => 'order_456',
			'order_active'   => 'false',
			'timestamp'      => '1621234567',
		];

		$data['hash'] = $this->calculate_test_redirect_hash_multi( $data, $alt_key );

		return $data;
	}
}
