<?php
/**
 * Response.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api\Response;

use Exception;
use stdClass;
use RuntimeException;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Response class.
 */
class Response {
	/**
	 * Request is success.
	 *
	 * @var bool
	 */
	private bool $request_is_success = false;

	/**
	 * Error message.
	 *
	 * @var string
	 */
	private string $error_message = '';

	/**
	 * Invalid parameters in response.
	 *
	 * @var array
	 */
	private array $invalid_parameters = [];

	/**
	 * Response status code.
	 *
	 * @var int
	 */
	private int $status_code = 0;

	/**
	 * Response reason phrase
	 *
	 * @var string
	 */
	private string $reason_phrase = '';

	/**
	 * Response body.
	 *
	 * @var stdClass|null
	 */
	private stdClass|null $body = null;

	/**
	 * Response status.
	 *
	 * @var string
	 */
	protected string $status = '';

	/**
	 * Constructor.
	 *
	 * @param ResponseInterface|RequestException|Exception $response
	 * @param array $request_body
	 */
	public function __construct( ResponseInterface|RequestException|Exception $response, private array $request_body = [] ) {
		$this->handle_response( $response );
	}

	/**
	 * Make response class instance.
	 *
	 * @param ResponseInterface|RequestException|Exception $response
	 * @param array $request_body
	 * @param string|null $response_class_name
	 * @return self
	 */
	public static function make( ResponseInterface|RequestException|Exception $response, array $request_body = [], string|null $response_class_name = null ): self {
		$class = new \ReflectionClass( __NAMESPACE__ . '\\' . ( $response_class_name ?? 'Response' ) );

		return $class->newInstanceArgs( [ $response, $request_body ] );
	}

	/**
	 * Set request status.
	 *
	 * @param bool $is_success
	 * @return void
	 */
	private function set_request_status( bool $is_success ): void {
		$this->request_is_success = $is_success;
	}

	/**
	 * Check if request is successful.
	 *
	 * @return bool
	 */
	public function request_is_success(): bool {
		return $this->request_is_success;
	}

	/**
	 * Check if request is an error.
	 *
	 * @return bool
	 */
	public function request_is_error(): bool {
		return ! $this->request_is_success();
	}

	/**
	 * Set error message.
	 *
	 * @param string $error_message
	 * @return void
	 */
	private function set_error_message( string $error_message ): void {
		$this->error_message = $error_message;
	}

	/**
	 * Get error message.
	 *
	 * @return string
	 */
	private function get_error_message(): string {
		return $this->error_message;
	}

	/**
	 * Get error message formatted.
	 *
	 * @param bool $with_invalid_params
	 * @return string
	 */
	public function get_error_message_formatted( bool $with_invalid_params = false ): string {
		if ( ! $this->get_error_message() ) {
			return '';
		}

		/* translators: %s is error title. */
		$error_message = sprintf( __( 'Error message: "%s".', 'acquired-com-for-woocommerce' ), $this->get_error_message() );

		if ( $with_invalid_params && ! empty( $this->invalid_parameters ) ) {
			$error_message .= sprintf(
				/* translators: %s is invalid parameters. */
				__( ' Invalid parameters: "%s".', 'acquired-com-for-woocommerce' ),
				join( ', ', $this->invalid_parameters )
			);
		}

		return $error_message;
	}

	/**
	 * Get response status code.
	 *
	 * @return int
	 */
	private function get_status_code(): int {
		return $this->status_code;
	}

	/**
	 * Get response reason phrase.
	 *
	 * @return string
	 */
	private function get_reason_phrase(): string {
		return $this->reason_phrase;
	}

	/**
	 * Set response body.
	 *
	 * @param string $body
	 * @return void
	 */
	private function set_body( string $body ): void {
		$this->body = json_decode( $body );
	}

	/**
	 * Get response body.
	 *
	 * @return stdClass|null
	 */
	private function get_body(): ?stdClass {
		return $this->body;
	}

	/**
	 * Check if response body has field.
	 *
	 * @param string $field
	 * @return bool
	 */
	protected function has_body_field( string $field ): bool {
		$body = $this->get_body();

		return $body && property_exists( $body, $field );
	}

	/**
	 * Get response body field.
	 *
	 * @param string $field
	 * @return mixed
	 */
	public function get_body_field( string $field ): mixed {
		return $this->has_body_field( $field ) ? $this->get_body()->{$field} : null;
	}

	/**
	 * Set response status.
	 *
	 * @return void
	 */
	protected function set_status() {
		$this->status = $this->get_body_field( 'status' ) ?? 'error_unknown';
	}

	/**
	 * Get response status.
	 *
	 * @return string
	 */
	public function get_status(): string {
		return $this->status;
	}

	/**
	 * Validate data.
	 *
	 * @return void
	 * @throws Exception
	 */
	protected function validate_data(): void {
		if ( ! $this->body ) {
			throw new Exception( 'Invalid response body' );
		}

		$this->set_request_status( true );
	}

	/**
	 * Read response content.
	 *
	 * @param ResponseInterface|null $response
	 * @return void
	 * @throws Exception
	 */
	private function read_content( ResponseInterface|null $response ): void {
		if ( ! $response ) {
			throw new Exception( 'Empty response.' );
		}

		$this->status_code   = $response->getStatusCode();
		$this->reason_phrase = $response->getReasonPhrase();

		try {
			$body = $response->getBody()->getContents();
			$this->set_body( $body );
		} catch ( RuntimeException $exception ) {
			throw new Exception( $exception->getMessage() );
		}
	}

	/**
	 * Handle request exception.
	 *
	 * @return void
	 */
	private function handle_request_exception(): void {
		$error_message = $this->get_body_field( 'error' ) ?? $this->get_reason_phrase();
		$this->set_error_message( $error_message ?: 'Unknown error' );

		$invalid_parameters = $this->get_body_field( 'invalid_parameters' );

		if ( $invalid_parameters && is_array( $invalid_parameters ) ) {
			$this->invalid_parameters = array_map(
				function( $parameter ) {
					return sprintf( '%s - %s', $parameter->parameter, $parameter->reason );
				},
				$invalid_parameters
			);
		}
	}

	/**
	 * Handle response.
	 *
	 * @param ResponseInterface|RequestException|Exception $response
	 * @return void
	 */
	private function handle_response( ResponseInterface|RequestException|Exception $response ): void {
		try {
			if ( $response instanceof RequestException ) {
				$this->read_content( $response->getResponse() );
				$this->handle_request_exception();
				$this->set_request_status( false );
			} elseif ( $response instanceof Exception ) {
				$this->set_error_message( $response->getMessage() );
				$this->set_request_status( false );
			} else {
				$this->read_content( $response );
				$this->validate_data();
			}
		} catch ( Exception $exception ) {
			$this->set_error_message( $exception->getMessage() );
			$this->set_request_status( false );
		} finally {
			$this->set_status();
		}
	}

	/**
	 * Get request body.
	 *
	 * @return array
	 */
	private function get_request_body(): array {
		return $this->request_body;
	}

	/**
	 * Get log data.
	 *
	 * @return array{
	 *     status: string,
	 *     response_code: int,
	 *     reason_phrase: string,
	 *     request_body: array,
	 *     response_body: ?stdClass,
	 *     error_message?: string
	 * }
	 */
	public function get_log_data(): array {
		$data = [
			'status'        => $this->get_status(),
			'response_code' => $this->get_status_code(),
			'reason_phrase' => $this->get_reason_phrase(),
			'request_body'  => $this->get_request_body(),
			'response_body' => $this->get_body(),
		];

		if ( $this->request_is_error() ) {
			$data['error_message'] = $this->get_error_message();
		}

		return $data;
	}
}
