<?php
/**
 * IncomingDataHandler.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api;

use AcquiredComForWooCommerce\Services\LoggerService;
use AcquiredComForWooCommerce\Api\IncomingData\RedirectData;
use AcquiredComForWooCommerce\Api\IncomingData\WebhookData;
use Exception;
use stdClass;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * IncomingDataHandler class.
 */
class IncomingDataHandler {
	/**
	 * Constructor.
	 *
	 * @param LoggerService $logger_service
	 * @param string $app_key
	 * @param string $signing_key
	 */
	public function __construct( private LoggerService $logger_service, private string $app_key, private string $signing_key = '' ) {}

	/**
	 * Sanitize data recursively.
	 *
	 * @param mixed $data
	 * @return string|int|array|stdClass
	 */
	private function sanitize_data( mixed $data ): string|int|array|stdClass {
		if ( is_array( $data ) ) {
			return array_map( [ $this, 'sanitize_data' ], $data );
		}

		if ( is_object( $data ) ) {
			foreach ( $data as $key => $value ) :
				$data->{$key} = $this->sanitize_data( $value );
			endforeach;
			return $data;
		}

		return is_int( $data ) ? $data : sanitize_text_field( $data );
	}

	/**
	 * Validate required fields.
	 *
	 * @param array|stdClass $data
	 * @param array $required_fields
	 * @param string $context
	 * @return void
	 * @throws Exception
	 */
	private function validate_required_fields( array|stdClass $data, array $required_fields, string $context = 'webhook' ): void {
		$missing_fields = [];

		foreach ( $required_fields as $field ) :
			if ( is_array( $data ) ) {
				if ( empty( $data[ $field ] ) ) {
					$missing_fields[] = $field;
				}
			} elseif ( empty( $data->{$field} ) ) {
					$missing_fields[] = $field;
			}
		endforeach;

		if ( ! empty( $missing_fields ) ) {
			throw new Exception(
				sprintf(
					'Missing required fields in %s: "%s".',
					$context,
					implode( ', ', $missing_fields )
				)
			);
		}
	}

	/**
	 * Check if the redirect hash matches a key.
	 *
	 * @param array $data
	 * @param string $key
	 * @return bool
	 */
	private function redirect_hash_matches_key( array $data, string $key ): bool {
		if ( ! $key ) {
			return false;
		}

		$first_hash    = hash( 'sha256', $data['status'] . $data['transaction_id'] . $data['order_id'] . $data['timestamp'] );
		$expected_hash = hash( 'sha256', $first_hash . $key );

		// Support comma-delimited hashes for key rotation. If ANY hash matches, validation passes.
		$hashes = array_map( 'trim', explode( ',', $data['hash'] ) );
		foreach ( $hashes as $candidate_hash ) {
			if ( hash_equals( $expected_hash, $candidate_hash ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Validate redirect hash.
	 *
	 * @param array $data
	 * @return bool
	 */
	private function validate_redirect_hash( array $data ): bool {
		// Determine which key to use: signing_key takes precedence, fallback to app_key for backward compatibility.
		$key = ( ! empty( $this->signing_key ) ) ? $this->signing_key : $this->app_key;

		return $this->redirect_hash_matches_key( $data, $key );
	}

	/**
	 * Validate webhook hash.
	 *
	 * @param string $data
	 * @param string $hash
	 * @return bool
	 */
	private function validate_webhook_hash( string $data, string $hash ): bool {
		// Determine which key to use: signing_key takes precedence, fallback to app_key for backward compatibility.
		$key = ( ! empty( $this->signing_key ) ) ? $this->signing_key : $this->app_key;

		if ( ! $key ) {
			return false;
		}

		$sanitized_data = preg_replace( '/\s+/', '', $data );
		$expected_hash  = hash_hmac( 'sha256', $sanitized_data, $key );

		// Support comma-delimited hashes for key rotation. If ANY hash matches, validation passes.
		$hashes = array_map( 'trim', explode( ',', $hash ) );
		foreach ( $hashes as $candidate_hash ) {
			if ( hash_equals( $expected_hash, $candidate_hash ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get redirect hash status.
	 *
	 * @param array $data
	 * @return string
	 */
	private function get_redirect_hash_status( array $data ): string {
		return match ( true ) {
			empty( $data['hash'] )                                    => 'empty',
			$this->validate_redirect_hash( $data )                    => 'valid',
			! $this->signing_key                                      => 'no_signing_key',
			$this->redirect_hash_matches_key( $data, $this->app_key ) => 'using_app_key_for_signing',
			default                                                   => 'invalid',
		};
	}

	/**
	 * Format redirect data.
	 *
	 * @param array $data
	 * @return RedirectData
	 * @throws Exception
	 */
	private function format_redirect_data( array $data ): RedirectData {
		$data = $this->sanitize_data( array_map( 'wp_unslash', $data ) );

		$this->validate_required_fields( $data, [ 'status', 'transaction_id', 'order_id', 'timestamp' ], 'redirect_data' );

		return new RedirectData( $data, $this->get_redirect_hash_status( $data ) );
	}

	/**
	 * Get webhook body requirements.
	 *
	 * @param string $webhook_type
	 * @return array
	 */
	private function get_webhook_body_requirements( string $webhook_type ): array {
		$requirements = [
			'status_update' => [
				'required' => [ 'transaction_id', 'status', 'order_id' ],
			],
			'card_new'      => [
				'required' => [ 'transaction_id', 'status', 'order_id', 'card_id' ],
			],
			'card_update'   => [
				'required' => [ 'card_id', 'update_type', 'update_detail', 'card' ],
				'nested'   => [
					'card' => [ 'holder_name', 'scheme', 'number', 'expiry_month', 'expiry_year' ],
				],
			],
		];

		return $requirements[ $webhook_type ] ?? [];
	}

	/**
	 * Validate webhook body.
	 *
	 * @param stdClass $body
	 * @param string $webhook_type
	 * @return void
	 * @throws Exception
	 */
	private function validate_webhook_body( stdClass $body, string $webhook_type ): void {
		$requirements = $this->get_webhook_body_requirements( $webhook_type );

		if ( ! $requirements ) {
			throw new Exception( sprintf( 'Invalid webhook type: %s.', $webhook_type ) );
		}

		$this->validate_required_fields( $body, $requirements['required'], 'webhook_body' );

		if ( isset( $requirements['nested'] ) ) {
			foreach ( $requirements['nested'] as $nested_field => $nested_field_requirements ) :
				$this->validate_required_fields( $body->{$nested_field}, $nested_field_requirements, 'webhook_body' );
			endforeach;
		}
	}

	/**
	 * Format webhook data.
	 *
	 * @param string $data
	 * @param string $hash
	 * @return WebhookData
	 * @throws Exception
	 */
	private function format_webhook_data( string $data, string $hash ): WebhookData {
		if ( ! $this->validate_webhook_hash( $data, $hash ) ) {
			throw new Exception( 'Webhook hash is invalid.' );
		}

		$data = json_decode( $data );

		if ( ! $data ) {
			throw new Exception( 'Webhook data is invalid.' );
		}

		$data = $this->sanitize_data( $data );

		$this->validate_required_fields( $data, [ 'webhook_type', 'webhook_id', 'timestamp', 'webhook_body' ] );

		if ( ! in_array( $data->webhook_type, [ 'status_update', 'card_new', 'card_update' ], true ) ) {
			throw new Exception( sprintf( 'Wrong webhook type sent. Webhook type "%s". Webhook ID: %s.', $data->webhook_type, $data->webhook_id ) );
		}

		$this->validate_webhook_body( $data->webhook_body, $data->webhook_type );

		return new WebhookData( $data );
	}

	/**
	 * Get redirect data.
	 *
	 * @param array $data
	 * @return RedirectData
	 * @throws Exception
	 */
	public function get_redirect_data( array $data ): RedirectData {
		try {
			$data = $this->format_redirect_data( $data );

			if ( $data->is_hash_valid() ) {
				$this->logger_service->log( 'Incoming redirect data received successfully.', 'debug', $data->get_log_data() );
			} else {
				$this->logger_service->log( sprintf( 'Incoming redirect data received with an unverified hash. Hash status: %s.', $data->get_hash_status() ), 'error' );
			}

			return $data;
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log( $error, 'error' );
			throw new Exception( $error );
		}
	}

	/**
	 * Get webhook data.
	 *
	 * @param string $data
	 * @param string $hash
	 * @return WebhookData
	 * @throws Exception
	 */
	public function get_webhook_data( string $data, string $hash ): WebhookData {
		try {
			$data = $this->format_webhook_data( $data, $hash );
			$this->logger_service->log( 'Incoming webhook data received successfully.', 'debug', $data->get_log_data() );
			return $data;
		} catch ( Exception $exception ) {
			$error = $exception->getMessage();
			$this->logger_service->log( $error, 'error' );
			throw new Exception( $error );
		}
	}
}
