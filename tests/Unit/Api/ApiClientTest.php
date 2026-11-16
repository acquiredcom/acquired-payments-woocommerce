<?php
/**
 * Test ApiClient class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api;

use AcquiredComForWooCommerce\Api\ApiClient;
use AcquiredComForWooCommerce\Api\Response\Response;
use AcquiredComForWooCommerce\Api\Response\Token;
use AcquiredComForWooCommerce\Api\Response\PaymentLink;
use AcquiredComForWooCommerce\Api\Response\Transaction;
use AcquiredComForWooCommerce\Api\Response\TransactionCapture;
use AcquiredComForWooCommerce\Api\Response\TransactionRefund;
use AcquiredComForWooCommerce\Api\Response\TransactionCancel;
use AcquiredComForWooCommerce\Api\Response\TransactionList;
use AcquiredComForWooCommerce\Api\Response\Customer;
use AcquiredComForWooCommerce\Api\Response\CustomerCreate;
use AcquiredComForWooCommerce\Api\Response\Card;
use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\LoggerServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\SettingsServiceMock;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\Traits\ResponseMock;
use GuzzleHttp\Client;
use Mockery;
use Brain\Monkey\Functions;
use Mockery\MockInterface;
use Exception;


/**
 * Test ApiClient class.
 *
 * @covers \AcquiredComForWooCommerce\Api\ApiClient
 */
class ApiClientTest extends TestCase {
	/**
	 * Traits.
	 */
	use LoggerServiceMock;
	use SettingsServiceMock;
	use Reflection;
	use ResponseMock;

	/**
	 * Client mock.
	 *
	 * @var MockInterface&Client
	 */
	private MockInterface $client;

	/**
	 * Test class.
	 *
	 * @var ApiClient
	 */
	private ApiClient $test_class;

	/**
	 * Mock get_api_credentials process.
	 *
	 * @return void
	 */
	private function mock_get_api_credentials(): void {
		$this->get_settings_service()
			->shouldReceive( 'get_api_credentials' )
			->once()
			->andReturn(
				[
					'app_id'  => 'test_id',
					'app_key' => 'test_key',
				]
			);
	}

	/**
	 * Mock get_api_url creation.
	 *
	 * @param string $value
	 * @return void
	 */
	private function mock_get_api_url_creation( string $value ): void {
		$this->get_settings_service()
			->shouldReceive( 'get_api_url' )
			->once()
			->andReturn( 'https://api.acquired.com/v1/' );

		Functions\expect( 'trailingslashit' )
			->once()
			->with( $value )
			->andReturn( $value . '/' );
	}

	/**
	 * Mock get_payment_link_default_body creation.
	 *
	 * @param bool $enabled_3d_secure
	 * @return void
	 */
	private function mock_get_payment_link_default_body_creation( bool $enabled_3d_secure = true ): void {
		$this->get_settings_service()
			->shouldReceive( 'get_shop_currency' )
			->once()
			->andReturn( 'GBP' );

		$this->get_settings_service()
			->shouldReceive( 'get_shop_currency' )
			->with( 'config' )
			->andReturn( [ 'version' => '2.0.0' ] );

		$this->get_settings_service()
			->shouldReceive( 'get_payment_reference' )
			->once()
			->andReturn( 'Test Store' );

		if ( $enabled_3d_secure ) {
			$this->get_settings_service()
				->shouldReceive( 'is_enabled' )
				->once()
				->with( '3d_secure' )
				->andReturn( true );

			$this->get_settings_service()
				->shouldReceive( 'get_option' )
				->once()
				->with( 'challenge_preferences', 'no_preference' )
				->andReturn( 'no_preference' );

			$this->get_settings_service()
				->shouldReceive( 'get_option' )
				->once()
				->with( 'contact_url', Mockery::any() )
				->andReturn( 'https://example.com/contact' );

			Functions\expect( 'get_site_url' )
				->once()
				->andReturn( 'https://example.com/' );
		} else {
			$this->get_settings_service()
				->shouldReceive( 'is_enabled' )
				->once()
				->with( '3d_secure' )
				->andReturn( false );
		}
	}

	/**
	 * Mock client request.
	 *
	 * @param string $method
	 * @param string $url
	 * @param array $request_headers
	 * @param array $request_body
	 * @param int $response_status
	 * @param array $response_data
	 * @return void
	 */
	private function mock_client_request(
		string $method,
		string $url,
		array $request_headers,
		array $request_body = [],
		int $response_status = 200,
		array $response_data = []
	): void {
		// Mock response.
		$response = $this->mock_response(
			$response_status,
			'OK',
			(object) $response_data
		);

		// Mock client request.
		$this->client
			->shouldReceive( 'request' )
			->once()
			->with(
				$method,
				$url,
				[
					'headers' => $request_headers,
					'body'    => json_encode( $request_body ),
				]
			)
			->andReturn( $response );
	}

	/**
	 * Mock get_access_token.
	 *
	 * @param string $result
	 * @return void
	 */
	private function mock_make_token_request( string $result ): void {
		$data = [
			'success' => [
				'data' => [
					'token_type'   => 'Bearer',
					'expires_in'   => 3600,
					'access_token' => 'token_1234567890',
				],
				'code' => 200,
			],
			'error'   => [
				'data' => [
					'status'     => 'error',
					'error_type' => 'validation',
					'title'      => 'Your request parameters did not pass our validation.',
				],
				'code' => 400,
			],
		];

		$response_data = $data[ $result ];

		// Mock SettingsService.
		$this->mock_get_api_credentials();

		// Mock get_api_url for login endpoint.
		$this->mock_get_api_url_creation( 'login' );

		// Mock client request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/login/',
			[
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			],
			[
				'app_id'  => 'test_id',
				'app_key' => 'test_key',
			],
			$response_data['code'],
			$response_data['data']
		);
	}

	/**
	 * Mock get_access_token.
	 *
	 * @param string $result
	 * @return void
	 */
	private function mock_get_access_token( string $result ): void {
		$this->mock_make_token_request( $result );

		if ( 'success' === $result ) {
			$this->get_logger_service()
				->shouldReceive( 'log' )
				->once()
				->with( 'Access token retrieved successfully.', 'debug', Mockery::any() );
		} else {
			$this->get_logger_service()
				->shouldReceive( 'log' )
				->once()
				->with( 'Access token creation failed.', 'error', Mockery::any() );
		}
	}

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_logger_service();
		$this->mock_settings_service();
		$this->client = Mockery::mock( Client::class );

		$this->test_class = new ApiClient( $this->client, $this->get_logger_service(), $this->get_settings_service() );
		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Test constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		$this->assertSame( $this->client, $this->get_private_property_value( 'client' ) );
		$this->assertSame( $this->get_logger_service(), $this->get_private_property_value( 'logger_service' ) );
		$this->assertSame( $this->get_settings_service(), $this->get_private_property_value( 'settings_service' ) );
	}

	/**
	 * Test get_default_headers.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_default_headers
	 * @return void
	 */
	public function test_get_default_headers(): void {
		$result = $this->get_private_method_value( 'get_default_headers' );

		$this->assertIsArray( $result );
		$this->assertEquals(
			[
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			],
			$result
		);
	}

	/**
	 * Test get_api_url.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_api_url
	 * @return void
	 */
	public function test_get_api_url(): void {
		// With slug.
		$this->mock_get_api_url_creation( 'payment-links' );
		$result = $this->get_private_method_value( 'get_api_url', 'payment-links' );
		$this->assertEquals( 'https://api.acquired.com/v1/payment-links/', $result );

		// With slug and id.
		$this->mock_get_api_url_creation( 'transactions/12345' );
		$result = $this->get_private_method_value( 'get_api_url', 'transactions', '12345' );
		$this->assertEquals( 'https://api.acquired.com/v1/transactions/12345/', $result );

		// With slug and endpoint.
		$this->mock_get_api_url_creation( 'transactions/capture' );
		$result = $this->get_private_method_value( 'get_api_url', 'transactions', '', 'capture' );
		$this->assertEquals( 'https://api.acquired.com/v1/transactions/capture/', $result );

		// With slug, id, and endpoint.
		$this->mock_get_api_url_creation( 'transactions/12345/capture' );
		$result = $this->get_private_method_value( 'get_api_url', 'transactions', '12345', 'capture' );
		$this->assertEquals( 'https://api.acquired.com/v1/transactions/12345/capture/', $result );
	}

	/**
	 * Test get_api_url with fields.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_api_url
	 * @return void
	 */
	public function test_get_api_url_with_fields(): void {
		// Mock get_api_url creation.
		$this->mock_get_api_url_creation( 'transactions/12345' );

		// Mock add_query_arg.
		Functions\expect( 'add_query_arg' )
			->once()
			->with( 'filter', 'id,status', 'transactions/12345/' )
			->andReturn( 'transactions/12345/?filter=id,status' );

		// Test response data.
		$result = $this->get_private_method_value( 'get_api_url', 'transactions', '12345', '', [ 'id', 'status' ] );
		$this->assertEquals( 'https://api.acquired.com/v1/transactions/12345/?filter=id,status', $result );
	}

	/**
	 * Test get_api_url with query parameters.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_api_url
	 * @return void
	 */
	public function test_get_api_url_with_query(): void {
		// Mock get_api_url creation.
		$this->mock_get_api_url_creation( 'transactions' );

		// Mock add_query_arg.
		Functions\expect( 'add_query_arg' )
			->once()
			->with(
				[
					'order_id' => '518-wc_order_test',
					'status'   => 'success',
					'limit'    => 1,
				],
				'transactions/'
			)
			->andReturn( 'transactions/?order_id=518-wc_order_test&status=success&limit=1' );

		// Test response data.
		$result = $this->get_private_method_value(
			'get_api_url',
			'transactions',
			'',
			'',
			[],
			[
				'order_id' => '518-wc_order_test',
				'status'   => 'success',
				'limit'    => 1,
			]
		);
		$this->assertEquals( 'https://api.acquired.com/v1/transactions/?order_id=518-wc_order_test&status=success&limit=1', $result );
	}

	/**
	 * Test json_encode with array.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::json_encode
	 * @return void
	 */
	public function test_json_encode_array(): void {
		$test_array = [ 'test' => 'value' ];

		// Mock wp_json_encode.
		Functions\expect( 'wp_json_encode' )
			->once()
			->with( $test_array )
			->andReturn( '{"test":"value"}' );

		// Test response data.

		$result = $this->get_private_method_value(
			'json_encode',
			$test_array
		);

		$this->assertEquals( '{"test":"value"}', $result );
	}

	/**
	 * Test json_encode with object.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::json_encode
	 * @return void
	 */
	public function test_json_encode_object(): void {
		$test_object = (object) [ 'test' => 'value' ];

		// Mock wp_json_encode.
		Functions\expect( 'wp_json_encode' )
			->once()
			->with( $test_object )
			->andReturn( '{"test":"value"}' );

		// Test response data.

		$result = $this->get_private_method_value(
			'json_encode',
			$test_object
		);

		$this->assertEquals( '{"test":"value"}', $result );
	}

	/**
	 * Test json_encode with invalid data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::json_encode
	 * @return void
	 */
	public function test_json_encode_invalid_data(): void {
		$test_array = [ 'test' => 'value' ];

		// Mock wp_json_encode.
		Functions\expect( 'wp_json_encode' )
			->once()
			->with( $test_array )
			->andReturn( false );

		// Test response data.

		$result = $this->get_private_method_value(
			'json_encode',
			$test_array
		);

		$this->assertFalse( $result );
	}

	/**
	 * Test get_payment_link_default_body.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_payment_link_default_body
	 * @return void
	 */
	public function test_get_payment_link_default_body(): void {
		// Mock link body creation.
		$this->mock_get_payment_link_default_body_creation();

		// Test response data.
		$result = $this->test_class->get_payment_link_default_body();
		$this->assertArrayHasKey( 'tds', $result );
		$this->assertIsArray( $result['tds'] );
		$this->assertArrayHasKey( 'is_active', $result['tds'] );
		$this->assertArrayHasKey( 'challenge_preference', $result['tds'] );
		$this->assertArrayHasKey( 'contact_url', $result['tds'] );
		$this->assertTrue( $result['tds']['is_active'] );
		$this->assertEquals( 'no_preference', $result['tds']['challenge_preference'] );
		$this->assertEquals( 'https://example.com/contact', $result['tds']['contact_url'] );
	}

	/**
	 * Test get_payment_link_default_body with 3D Secure disabled.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_payment_link_default_body
	 * @return void
	 */
	public function test_get_payment_link_default_body_with_3d_secure_disabled(): void {
		// Mock link body creation.
		$this->mock_get_payment_link_default_body_creation( false );

		// Test response data.
		$result = $this->test_class->get_payment_link_default_body();
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'transaction', $result );
		$this->assertArrayHasKey( 'payment', $result );
		$this->assertArrayHasKey( 'count_retry', $result );
		$this->assertArrayNotHasKey( 'tds', $result );
		$this->assertEquals( 'GBP', $result['transaction']['currency'] );
		$this->assertEquals( '2.0.0', $result['transaction']['custom1'] );
		$this->assertEquals( 'Test Store', $result['payment']['reference'] );
		$this->assertEquals( 1, $result['count_retry'] );
	}

	/**
	 * Test make_request.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::make_request
	 * @return void
	 */
	public function test_make_request(): void {
		// Mock client request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			[
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			]
		);

		// Test response data.

		$result = $this->get_private_method_value(
			'make_request',
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			[
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			],
		);

		$this->assertInstanceOf( Response::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
	}

	/**
	 * Test make_request.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::make_request
	 * @return void
	 */
	public function test_make_request_with_request_exception(): void {
		$exception = $this->mock_request_exception(
			400,
			'Bad Request',
			(object) [
				'status'     => 'error',
				'error_type' => 'validation',
				'title'      => 'Your request parameters did not pass our validation.',
			]
		);

		// Mock client.
		$this->client
			->shouldReceive( 'request' )
			->once()
			->andThrow( $exception );

		// Test response data.

		$result = $this->get_private_method_value(
			'make_request',
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			[
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			],
		);

		$this->assertInstanceOf( Response::class, $result );
		$this->assertFalse( $result->request_is_success() );
		$this->assertTrue( $result->request_is_error() );
		$this->assertEquals( 'error', $result->get_status() );
	}

	/**
	 * Test make_request with exception.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::make_request
	 * @return void
	 */
	public function test_make_request_with_exception(): void {
		$exception = new Exception( 'Test exception' );

		$this->client
			->shouldReceive( 'request' )
			->once()
			->andThrow( $exception );

		// Test response data.

		$result = $this->get_private_method_value(
			'make_request',
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			[
				'Accept'       => 'application/json',
				'Content-Type' => 'application/json',
			],
		);

		$this->assertInstanceOf( Response::class, $result );
		$this->assertFalse( $result->request_is_success() );
		$this->assertTrue( $result->request_is_error() );
		$this->assertEquals( 'error_unknown', $result->get_status() );
	}

	/**
	 * Test make_token_request.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::make_token_request
	 * @return void
	 */
	public function test_make_token_request(): void {
		$this->mock_make_token_request( 'success' );
		$result = $this->get_private_method_value( 'make_token_request' );
		$this->assertInstanceOf( Token::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
	}

	/**
	 * Test get_access_token when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_access_token
	 * @return void
	 */
	public function test_get_access_token_success(): void {
		$this->mock_get_access_token( 'success' );
		$result = $this->get_private_method_value( 'get_access_token' );
		$this->assertEquals( 'Bearer token_1234567890', $result );
	}

	/**
	 * Test get_access_token when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_access_token
	 * @return void
	 */
	public function test_get_access_token_error(): void {
		$this->mock_get_access_token( 'error' );
		$result = $this->get_private_method_value( 'get_access_token' );
		$this->assertNull( $result );
	}

	/**
	 * Test get_authorization_header.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_authorization_header
	 * @return void
	 */
	public function test_get_authorization_header(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Test response data.

		$result = $this->get_private_method_value( 'get_authorization_header' );

		$this->assertEquals(
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			],
			$result
		);
	}

	/**
	 * Test get_authorization_header with company ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_authorization_header
	 * @return void
	 */
	public function test_get_authorization_header_with_company_id(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_company_id' )
			->twice()
			->andReturn( 'company_123' );

		// Test response data.

		$result = $this->get_private_method_value( 'get_authorization_header', true );

		$this->assertEquals(
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Company-Id'    => 'company_123',
				'Authorization' => 'Bearer token_1234567890',
			],
			$result
		);
	}

	/**
	 * Test get_authorization_header when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_authorization_header
	 * @return void
	 */
	public function test_get_authorization_header_error(): void {
		$this->mock_get_access_token( 'error' );
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Access token in authorization header doesn\'t exist.' );
		$this->get_private_method_value( 'get_authorization_header' );
	}

	/**
	 * Test make_request_with_auth when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::make_request_with_auth
	 * @return void
	 */
	public function test_make_request_with_auth_success(): void {
		// Mock get access token request.
		$this->mock_get_access_token( 'success' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			]
		);

		// Test response data.

		$result = $this->get_private_method_value(
			'make_request_with_auth',
			'POST',
			'https://api.acquired.com/v1/payment-links/',
		);

		$this->assertInstanceOf( Response::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
	}

	/**
	 * Test make_request_with_auth with company header when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::make_request_with_auth
	 * @return void
	 */
	public function test_make_request_with_auth_and_header_success(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_company_id' )
			->twice()
			->andReturn( 'company_123' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Company-Id'    => 'company_123',
				'Authorization' => 'Bearer token_1234567890',
			]
		);

		// Test response data.

		$result = $this->get_private_method_value(
			'make_request_with_auth',
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			null,
			[],
			true
		);

		$this->assertInstanceOf( Response::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
	}

	/**
	 * Test make_request_with_auth when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::make_request_with_auth
	 * @return void
	 */
	public function test_make_request_with_error(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock client request that happens in make_request.

		$exception = $this->mock_request_exception(
			400,
			'Bad Request',
			(object) [
				'status'     => 'error',
				'error_type' => 'validation',
				'title'      => 'Your request parameters did not pass our validation.',
			]
		);

		$this->client
			->shouldReceive( 'request' )
			->once()
			->andThrow( $exception );

		// Test response data.

		$result = $this->get_private_method_value(
			'make_request_with_auth',
			'POST',
			'https://api.acquired.com/v1/payment-links/',
		);

		$this->assertInstanceOf( Response::class, $result );
		$this->assertFalse( $result->request_is_success() );
		$this->assertTrue( $result->request_is_error() );
	}

	/**
	 * Test make_request_with_auth with company header when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::make_request_with_auth
	 * @return void
	 */
	public function test_make_request_with_auth_and_header_error(): void {
		// Mock token request.
		$this->mock_get_access_token( 'error' );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->with( 'Access token in authorization header doesn\'t exist.', 'error' )
			->once();

		// Test response data.

		$result = $this->get_private_method_value(
			'make_request_with_auth',
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			null,
			[],
			true
		);

		$this->assertInstanceOf( Response::class, $result );
		$this->assertFalse( $result->request_is_success() );
		$this->assertTrue( $result->request_is_error() );
	}

	/**
	 * Test get_payment_link.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_payment_link
	 * @return void
	 */
	public function test_get_payment_link(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_company_id' )
			->twice()
			->andReturn( 'company_123' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'payment-links' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
				'Company-Id'    => 'company_123',
			],
			[],
			200,
			[
				'status'  => 'success',
				'link_id' => 'link_1234567890',
			]
		);

		$result = $this->test_class->get_payment_link( [] );
		$this->assertInstanceOf( PaymentLink::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test get_transaction.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_transaction
	 * @return void
	 */
	public function test_get_transaction(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'transactions/transaction_123456' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'GET',
			'https://api.acquired.com/v1/transactions/transaction_123456/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			],
			[],
			200,
			[
				'status'         => 'success',
				'transaction_id' => 'transaction_123456',
			]
		);

		$result = $this->test_class->get_transaction( 'transaction_123456' );
		$this->assertInstanceOf( Transaction::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test get_transactions.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_transactions
	 * @return void
	 */
	public function test_get_transactions(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'transactions' );

		// Mock add_query_arg.
		Functions\expect( 'add_query_arg' )
			->once()
			->with(
				[
					'order_id' => '518-wc_order_test',
					'status'   => 'success',
					'limit'    => 1,
				],
				'transactions/'
			)
			->andReturn( 'transactions/?order_id=518-wc_order_test&status=success&limit=1' );

		Functions\expect( 'add_query_arg' )
			->once()
			->with( 'filter', 'transaction_id', 'transactions/?order_id=518-wc_order_test&status=success&limit=1' )
			->andReturn( 'transactions/?order_id=518-wc_order_test&status=success&limit=1&filter=transaction_id' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'GET',
			'https://api.acquired.com/v1/transactions/?order_id=518-wc_order_test&status=success&limit=1&filter=transaction_id',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			],
			[],
			200,
			[
				'meta' => [
					'count' => 0,
					'total' => 0,
				],
				'data' => null,
			]
		);

		$result = $this->test_class->get_transactions(
			[
				'order_id' => '518-wc_order_test',
				'status'   => 'success',
				'limit'    => 1,
			],
			[ 'transaction_id' ]
		);

		$this->assertInstanceOf( TransactionList::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertNull( $result->get_first_transaction_id() );
	}

	/**
	 * Test capture_transaction.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::capture_transaction
	 * @return void
	 */
	public function test_capture_transaction(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'transactions/transaction_123456/capture' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/transactions/transaction_123456/capture/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			],
			[],
			200,
			[
				'status'         => 'success',
				'transaction_id' => 'transaction_123456',
			]
		);

		$result = $this->test_class->capture_transaction( 'transaction_123456', [] );
		$this->assertInstanceOf( TransactionCapture::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test refund_transaction.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::refund_transaction
	 * @return void
	 */
	public function test_refund_transaction(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'transactions/transaction_123456/reversal' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/transactions/transaction_123456/reversal/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			],
			[],
			200,
			[
				'status'         => 'success',
				'transaction_id' => 'transaction_123456',
			]
		);

		$result = $this->test_class->refund_transaction( 'transaction_123456', [] );
		$this->assertInstanceOf( TransactionRefund::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test cancel_transaction.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::cancel_transaction
	 * @return void
	 */
	public function test_cancel_transaction(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'transactions/transaction_123456/reversal' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/transactions/transaction_123456/reversal/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			],
			[],
			200,
			[
				'status'         => 'success',
				'transaction_id' => 'transaction_123456',
			]
		);

		$result = $this->test_class->cancel_transaction( 'transaction_123456', [] );
		$this->assertInstanceOf( TransactionCancel::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test get_customer.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_customer
	 * @return void
	 */
	public function test_get_customer(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'customers/customer_123456' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'GET',
			'https://api.acquired.com/v1/customers/customer_123456/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			],
			[],
			200,
			[
				'reference' => 'reference_1234567890',
			]
		);

		$result = $this->test_class->get_customer( 'customer_123456' );
		$this->assertInstanceOf( Customer::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test create_customer.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::create_customer
	 * @return void
	 */
	public function test_create_customer(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_company_id' )
			->twice()
			->andReturn( 'company_123' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'customers' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/customers/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
				'Company-Id'    => 'company_123',
			],
			[],
			200,
			[
				'customer_id' => 'customer_123456',
			]
		);

		$result = $this->test_class->create_customer( [] );
		$this->assertInstanceOf( CustomerCreate::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test update_customer.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::update_customer
	 * @return void
	 */
	public function test_update_customer(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'customers/customer_123456' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'PUT',
			'https://api.acquired.com/v1/customers/customer_123456/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			],
			[],
			200,
			[
				'status' => 'success',
			]
		);

		$result = $this->test_class->update_customer( 'customer_123456', [] );
		$this->assertInstanceOf( Response::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test get_card.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::get_card
	 * @return void
	 */
	public function test_get_card(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'cards/card_123456' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'GET',
			'https://api.acquired.com/v1/cards/card_123456/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			],
			[],
			200,
			[
				'card_id'     => 'card_123456',
				'customer_id' => 'customer_123456',
				'card'        => (object) [
					'holder_name'  => 'E Johnson',
					'scheme'       => 'visa',
					'number'       => '8710',
					'expiry_month' => 12,
					'expiry_year'  => 40,
				],
			]
		);

		$result = $this->test_class->get_card( 'card_123456' );
		$this->assertInstanceOf( Card::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test update_card.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::update_card
	 * @return void
	 */
	public function test_update_card(): void {
		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'cards/card_123456' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'PUT',
			'https://api.acquired.com/v1/cards/card_123456/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
			],
			[],
			200,
			[
				'status' => 'success',
			]
		);

		$result = $this->test_class->update_card( 'card_123456', [] );
		$this->assertInstanceOf( Response::class, $result );
		$this->assertTrue( $result->request_is_success() );
		$this->assertFalse( $result->request_is_error() );
		$this->assertEquals( 'success', $result->get_status() );
	}

	/**
	 * Test validate_credentials with company id when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::validate_credentials
	 * @return void
	 */
	public function test_validate_credentials_success(): void {
		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_company_id' )
			->once()
			->andReturn( false );

		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Validating credentials with token successful.', 'debug' );

		// Test response data.
		$this->assertTrue( $this->test_class->validate_credentials() );
	}

	/**
	 * Test validate_credentials with company id when error.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::validate_credentials
	 * @return void
	 */
	public function test_validate_credentials_error(): void {
		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_company_id' )
			->once()
			->andReturn( false );

		// Mock token request.
		$this->mock_get_access_token( 'error' );

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Validating credentials with token failed.', 'error' );

		// Test response data.
		$this->assertFalse( $this->test_class->validate_credentials() );
	}

	/**
	 * Test validate_credentials with company id when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::validate_credentials
	 * @return void
	 */
	public function test_validate_credentials_with_company_id_success(): void {
		// Mock link body creation.
		$this->mock_get_payment_link_default_body_creation();

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_company_id' )
			->times( 3 )
			->andReturn( 'company_123' );

		// Mock the time function to return a fixed timestamp.
		Functions\expect( 'time' )
			->once()
			->andReturn( 1234567890 );

		// Mock redirect_url for payment link request.
		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'redirect-new-order' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-order' );

		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'payment-links' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
				'Company-Id'    => 'company_123',
			],
			[
				'transaction'  => [
					'currency' => 'GBP',
					'custom1'  => '2.0.0',
					'order_id' => 1234567890,
					'amount'   => 0,
					'capture'  => false,
				],
				'payment'      => [
					'reference' => 'Test Store',
				],
				'count_retry'  => 1,
				'tds'          => [
					'is_active'            => true,
					'challenge_preference' => 'no_preference',
					'contact_url'          => 'https://example.com/contact',
				],
				'redirect_url' => 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-order',
				'expires_in'   => 60,
			],
			200,
			[
				'status'  => 'success',
				'link_id' => 'link_1234567890',
			]
		);

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Validating credentials with company ID successful.', 'debug', Mockery::any() );

		// Test response data.
		$this->assertTrue( $this->test_class->validate_credentials() );
	}

	/**
	 * Test validate_credentials with company id when success.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\ApiClient::validate_credentials
	 * @return void
	 */
	public function test_validate_credentials_with_company_id_error(): void {
		// Mock link body creation.
		$this->mock_get_payment_link_default_body_creation();

		// Mock SettingsService.
		$this->get_settings_service()
			->shouldReceive( 'get_company_id' )
			->times( 3 )
			->andReturn( 'company_123' );

		// Mock the time function to return a fixed timestamp.
		Functions\expect( 'time' )
			->once()
			->andReturn( 1234567890 );

		// Mock redirect_url for payment link request.
		$this->get_settings_service()
			->shouldReceive( 'get_wc_api_url' )
			->once()
			->with( 'redirect-new-order' )
			->andReturn( 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-order' );

		// Mock token request.
		$this->mock_get_access_token( 'success' );

		// Mock API URL creation.
		$this->mock_get_api_url_creation( 'payment-links' );

		// Mock client request that happens in make_request.
		$this->mock_client_request(
			'POST',
			'https://api.acquired.com/v1/payment-links/',
			[
				'Accept'        => 'application/json',
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer token_1234567890',
				'Company-Id'    => 'company_123',
			],
			[
				'transaction'  => [
					'currency' => 'GBP',
					'custom1'  => '2.0.0',
					'order_id' => 1234567890,
					'amount'   => 0,
					'capture'  => false,
				],
				'payment'      => [
					'reference' => 'Test Store',
				],
				'count_retry'  => 1,
				'tds'          => [
					'is_active'            => true,
					'challenge_preference' => 'no_preference',
					'contact_url'          => 'https://example.com/contact',
				],
				'redirect_url' => 'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-order',
				'expires_in'   => 60,
			],
			200,
			[
				'status' => 'error',
			]
		);

		// Mock LoggerService.
		$this->get_logger_service()
			->shouldReceive( 'log' )
			->once()
			->with( 'Validating credentials with company ID failed.', 'error', Mockery::any() );

		// Test response data.
		$this->assertFalse( $this->test_class->validate_credentials() );
	}
}
