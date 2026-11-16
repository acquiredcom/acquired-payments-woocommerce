<?php
/**
 * ApiClient.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Api;

use AcquiredComForWooCommerce\Services\LoggerService;
use AcquiredComForWooCommerce\Services\SettingsService;
use AcquiredComForWooCommerce\Api\Response\Response;
use AcquiredComForWooCommerce\Api\Response\Card;
use AcquiredComForWooCommerce\Api\Response\Customer;
use AcquiredComForWooCommerce\Api\Response\CustomerCreate;
use AcquiredComForWooCommerce\Api\Response\PaymentLink;
use AcquiredComForWooCommerce\Api\Response\Token;
use AcquiredComForWooCommerce\Api\Response\Transaction;
use AcquiredComForWooCommerce\Api\Response\TransactionCapture;
use AcquiredComForWooCommerce\Api\Response\TransactionRefund;
use AcquiredComForWooCommerce\Api\Response\TransactionCancel;
use AcquiredComForWooCommerce\Api\Response\TransactionList;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * ApiClient class.
 */
class ApiClient {
	/**
	 * Default headers.
	 *
	 * @var array{
	 *     Accept: string,
	 *     Content-Type: string
	 * }
	 */
	private array $default_headers = [
		'Accept'       => 'application/json',
		'Content-Type' => 'application/json',
	];

	/**
	 * Constructor.
	 *
	 * @param Client $client
	 * @param LoggerService $logger_service
	 * @param SettingsService $settings_service
	 */
	public function __construct(
		private Client $client,
		private LoggerService $logger_service,
		private SettingsService $settings_service
	) {}

	/**
	 * Get default headers.
	 *
	 * @return array
	 */
	private function get_default_headers(): array {
		return $this->default_headers;
	}

	/**
	 * Get API URL.
	 *
	 * @param string $slug
	 * @param string $id
	 * @param string $endpoint
	 * @param array $fields
	 * @param array $query
	 * @return string
	 */
	private function get_api_url( string $slug, string $id = '', string $endpoint = '', array $fields = [], array $query = [] ): string {
		$url_parts = [
			'slug'     => $slug,
			'id'       => $id,
			'endpoint' => $endpoint,
		];

		$url_parts = array_filter( $url_parts );

		$url = trailingslashit( join( '/', $url_parts ) );

		if ( ! empty( $query ) ) {
			$url = add_query_arg( $query, $url );
		}

		if ( ! empty( $fields ) ) {
			$url = add_query_arg( 'filter', join( ',', $fields ), $url );
		}

		return $this->settings_service->get_api_url() . $url;
	}

	/**
	 * JSON encode.
	 *
	 * @param array|object $value
	 * @return false|string
	 */
	private function json_encode( array|object $value ): false|string {
		return wp_json_encode( $value );
	}

	/**
	 * Get payment link default body.
	 *
	 * @return array{
	 *     transaction: array{
	 *         currency: string,
	 *         custom1: string
	 *     },
	 *     payment: array{
	 *         reference: string
	 *     },
	 *     count_retry: int,
	 *     tds?: array{
	 *         is_active: bool,
	 *         challenge_preference: string,
	 *         contact_url: string
	 *     }
	 * }
	 */
	public function get_payment_link_default_body(): array {
		$body = [
			'transaction' => [
				'currency' => $this->settings_service->get_shop_currency(),
				'custom1'  => $this->settings_service->config['version'],
			],
			'payment'     => [
				'reference' => $this->settings_service->get_payment_reference(),
			],
			'count_retry' => 1,
		];

		if ( $this->settings_service->is_enabled( '3d_secure' ) ) {
			$body['tds'] = [
				'is_active'            => true,
				'challenge_preference' => $this->settings_service->get_option( 'challenge_preferences', 'no_preference' ),
				'contact_url'          => $this->settings_service->get_option( 'contact_url', get_site_url() ),
			];
		}

		return $body;
	}

	/**
	 * Make request.
	 *
	 * @param string $method
	 * @param string $url
	 * @param array $headers
	 * @param string|null $response_class_name
	 * @param array $body
	 * @return Response
	 */
	private function make_request( string $method, string $url, array $headers, string|null $response_class_name = null, array $body = [] ): Response {
		try {
			$response = $this->client->request(
				$method,
				$url,
				[
					'headers' => $headers,
					'body'    => $this->json_encode( $body ),
				]
			);

			return Response::make( $response, $body, $response_class_name );
		} catch ( RequestException $client_exception ) {
			return Response::make( $client_exception, $body, $response_class_name );
		} catch ( Exception $exception ) {
			return Response::make( $exception, $body, $response_class_name );
		}
	}

	/**
	 * Make request for the token.
	 *
	 * @return Token
	 */
	private function make_token_request(): Token {
		return $this->make_request(
			'POST',
			$this->get_api_url( 'login' ),
			$this->get_default_headers(),
			'Token',
			$this->settings_service->get_api_credentials()
		);
	}

	/**
	 * Get access token.
	 *
	 * @return string|null
	 */
	private function get_access_token(): ?string {
		$response = $this->make_token_request();

		if ( $response->request_is_success() ) {
			$this->logger_service->log( 'Access token retrieved successfully.', 'debug', $response->get_log_data() );
			return $response->get_token_formatted();
		} else {
			$this->logger_service->log( 'Access token creation failed.', 'error', $response->get_log_data() );
			return null;
		}
	}

	/**
	 * Get authorization header.
	 *
	 * @param bool $add_company_id_header
	 * @return array{
	 *     Accept: string,
	 *     Content-Type: string,
	 *     Authorization: string,
	 *     Company-Id?: string
	 * }
	 * @throws Exception
	 */
	private function get_authorization_header( bool $add_company_id_header = false ): array {
		$token = $this->get_access_token();

		if ( ! $token ) {
			throw new Exception( 'Access token in authorization header doesn\'t exist.' );
		}

		$default_headers = $this->get_default_headers();

		if ( $add_company_id_header && $this->settings_service->get_company_id() ) {
			$default_headers['Company-Id'] = $this->settings_service->get_company_id();
		}

		return array_merge( $default_headers, [ 'Authorization' => $token ] );
	}

	/**
	 * Make request with authorization.
	 *
	 * @param string $method
	 * @param string $url
	 * @param string|null $response_class_name
	 * @param array $body
	 * @param bool $add_company_id_header
	 * @return Response
	 */
	private function make_request_with_auth( string $method, string $url, string|null $response_class_name = null, array $body = [], bool $add_company_id_header = false ): Response {
		try {
			return $this->make_request( $method, $url, $this->get_authorization_header( $add_company_id_header ), $response_class_name, $body );
		} catch ( Exception $exception ) {
			$this->logger_service->log( $exception->getMessage(), 'error' );
			return Response::make( $exception, $body, $response_class_name );
		}
	}

	/**
	 * Get payment link.
	 *
	 * @param array $body
	 * @return PaymentLink
	 */
	public function get_payment_link( array $body ): PaymentLink {
		return $this->make_request_with_auth(
			'POST',
			$this->get_api_url( 'payment-links' ),
			'PaymentLink',
			$body,
			true
		);
	}

	/**
	 * Get transaction.
	 *
	 * @param string $transaction_id
	 * @param array $fields
	 * @return Transaction
	 */
	public function get_transaction( string $transaction_id, array $fields = [] ): Transaction {
		return $this->make_request_with_auth(
			'GET',
			$this->get_api_url( 'transactions', $transaction_id, '', $fields ),
			'Transaction'
		);
	}

	/**
	 * Get transactions.
	 *
	 * @param array $query
	 * @param array $fields
	 * @return TransactionList
	 */
	public function get_transactions( array $query, array $fields = [] ): TransactionList {
		return $this->make_request_with_auth(
			'GET',
			$this->get_api_url( 'transactions', '', '', $fields, $query ),
			'TransactionList'
		);
	}

	/**
	 * Capture transaction.
	 *
	 * @param string $transaction_id
	 * @param array $body
	 * @return TransactionCapture
	 */
	public function capture_transaction( string $transaction_id, array $body ): TransactionCapture {
		return $this->make_request_with_auth(
			'POST',
			$this->get_api_url( 'transactions', $transaction_id, 'capture' ),
			'TransactionCapture',
			$body
		);
	}

	/**
	 * Refund a transaction.
	 *
	 * @param string $transaction_id
	 * @param array $body
	 * @return TransactionRefund
	 */
	public function refund_transaction( string $transaction_id, array $body ): TransactionRefund {
		return $this->make_request_with_auth(
			'POST',
			$this->get_api_url( 'transactions', $transaction_id, 'reversal' ),
			'TransactionRefund',
			$body
		);
	}

	/**
	 * Cancel a transaction.
	 *
	 * @param string $transaction_id
	 * @param array $body
	 * @return TransactionCancel
	 */
	public function cancel_transaction( string $transaction_id, array $body ): TransactionCancel {
		return $this->make_request_with_auth(
			'POST',
			$this->get_api_url( 'transactions', $transaction_id, 'reversal' ),
			'TransactionCancel',
			$body
		);
	}

	/**
	 * Get customer.
	 *
	 * @param string $customer_id
	 * @return Customer
	 */
	public function get_customer( string $customer_id ): Customer {
		return $this->make_request_with_auth(
			'GET',
			$this->get_api_url( 'customers', $customer_id ),
			'Customer'
		);
	}

	/**
	 * Create customer.
	 *
	 * @param array $body
	 * @return CustomerCreate
	 */
	public function create_customer( array $body ): CustomerCreate {
		return $this->make_request_with_auth(
			'POST',
			$this->get_api_url( 'customers' ),
			'CustomerCreate',
			$body,
			true
		);
	}

	/**
	 * Update customer.
	 *
	 * @param string $customer_id
	 * @param array $body
	 * @return Response
	 */
	public function update_customer( string $customer_id, array $body ): Response {
		return $this->make_request_with_auth(
			'PUT',
			$this->get_api_url( 'customers', $customer_id ),
			null,
			$body
		);
	}

	/**
	 * Get card.
	 *
	 * @param string $card_id
	 * @return Card
	 */
	public function get_card( string $card_id ): Card {
		return $this->make_request_with_auth(
			'GET',
			$this->get_api_url( 'cards', $card_id ),
			'Card'
		);
	}

	/**
	 * Update card.
	 *
	 * @param string $card_id
	 * @param array $body
	 * @return Response
	 */
	public function update_card( string $card_id, array $body ): Response {
		return $this->make_request_with_auth(
			'PUT',
			$this->get_api_url( 'cards', $card_id ),
			null,
			$body
		);
	}

	/**
	 * Validate credentials.
	 *
	 * @return bool
	 */
	public function validate_credentials(): bool {
		if ( $this->settings_service->get_company_id() ) {
			$body = $this->get_payment_link_default_body();

			$body['transaction'] = array_merge(
				$body['transaction'],
				[
					'order_id' => time(),
					'amount'   => 0,
					'capture'  => false,
				]
			);

			$body['redirect_url'] = $this->settings_service->get_wc_api_url( 'redirect-new-order' );
			$body['expires_in']   = 60;

			$response = $this->get_payment_link( $body );

			if ( $response->request_is_success() ) {
				$this->logger_service->log( 'Validating credentials with company ID successful.', 'debug', $response->get_log_data() );
				return true;
			} else {
				$this->logger_service->log( 'Validating credentials with company ID failed.', 'error', $response->get_log_data() );
				return false;
			}
		} else {
			$token = $this->get_access_token();
			if ( $token ) {
				$this->logger_service->log( 'Validating credentials with token successful.', 'debug' );
				return true;
			} else {
				$this->logger_service->log( 'Validating credentials with token failed.', 'error' );
				return false;
			}
		}
	}
}
