<?php
/**
 * LoggerService.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Services;

use WC_Logger_Interface;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * LoggerService class.
 */
class LoggerService {
	/**
	 * Log levels.
	 *
	 * @var array
	 */
	private array $log_levels = [
		'emergency',
		'alert',
		'critical',
		'error',
		'warning',
		'notice',
		'info',
		'debug',
	];

	/**
	 * List of sensitive field names to redact
	 *
	 * @var array
	 */
	private array $sensitive_fields = [
		'app_id',
		'app_key',
		'access_token',
		'first_name',
		'last_name',
		'email',
		'line_1',
		'line_2',
		'city',
		'postcode',
		'country_code',
		'hash',
		'holder_name',
		'scheme',
		'number',
		'dob',
		'ip',
	];

	/**
	 * Constructor.
	 *
	 * @param SettingsService $settings_service
	 * @param WC_Logger_Interface $logger
	 */
	public function __construct( private SettingsService $settings_service, private WC_Logger_Interface $logger ) {}

	/**
	 * Redact sensitive data recursively.
	 *
	 * @param mixed $data
	 * @return mixed
	 */
	private function redact_sensitive_data( mixed $data ): mixed {
		if ( ! is_array( $data ) && ! is_object( $data ) ) {
			return $data;
		}

		$is_object = is_object( $data );
		$redacted  = $is_object ? clone $data : [];
		$items     = $is_object ? $redacted : $data;

		foreach ( $items as $key => $value ) {
			$new_value = in_array( strtolower( (string) $key ), $this->sensitive_fields, true ) ? '[REDACTED]' : ( is_array( $value ) || is_object( $value ) ? $this->redact_sensitive_data( $value ) : $value );

			if ( $is_object ) {
				$redacted->$key = $new_value;
			} else {
				$redacted[ $key ] = $new_value;
			}
		}

		return $redacted;
	}

	/**
	 * Log a message if debug is enabled in the options.
	 *
	 * @param string $message
	 * @param string $level
	 * @param array $context
	 * @return void
	 */
	public function log( string $message, string $level = 'info', array $context = [] ): void {
		if ( ! $this->settings_service->is_enabled( 'debug_log' ) ) {
			return;
		}

		if ( ! in_array( $level, $this->log_levels, true ) ) {
			$level = 'info';
		}

		$default_context = [ 'source' => $this->settings_service->config['plugin_slug'] ];

		if ( $context ) {
			$default_context = array_merge( $default_context, $this->redact_sensitive_data( $context ) );
		}

		$this->logger->log( $level, $message, $default_context );
	}
}
