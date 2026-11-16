<?php
/**
 * LoggerServiceTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Services;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\Traits\SettingsServiceMock;
use AcquiredComForWooCommerce\Services\LoggerService;
use Mockery;
use Mockery\MockInterface;
use WC_Logger_Interface;

/**
 * LoggerServiceTest class.
 *
 * @covers \AcquiredComForWooCommerce\Services\LoggerService
 */
class LoggerServiceTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;
	use SettingsServiceMock;

	/**
	 * LoggerService instance.
	 *
	 * @var LoggerService
	 */
	private LoggerService $service;

	/**
	 * WC Logger mock.
	 *
	 * @var MockInterface|WC_Logger_Interface
	 */
	private $logger;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mock_settings_service();
		$this->logger = Mockery::mock( WC_Logger_Interface::class );

		$this->service = new LoggerService( $this->get_settings_service(), $this->logger );
		$this->initialize_reflection( $this->service );
	}

	/**
	 * Test constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\LoggerService::__construct
	 * @return void
	 */
	public function test_constructor(): void {
		// Test if SettingsService is set correctly.
		$this->assertSame( $this->get_settings_service(), $this->get_private_property_value( 'settings_service' ) );

		// Test if all sensitive fields are set.

		$sensitive_fields = $this->get_private_property_value( 'sensitive_fields' );

		$this->assertIsArray( $sensitive_fields );

		$expected_sensitive_fields = [
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

		foreach ( $expected_sensitive_fields as $field ) :
			$this->assertContains( $field, $sensitive_fields );
		endforeach;

		// Test if log levels are set.

		$log_levels = $this->get_private_property_value( 'log_levels' );

		$this->assertIsArray( $log_levels );

		$expected_log_levels = [
			'emergency',
			'alert',
			'critical',
			'error',
			'warning',
			'notice',
			'info',
			'debug',
		];

		foreach ( $expected_log_levels as $level ) :
			$this->assertContains( $level, $log_levels );
		endforeach;
	}

	/**
	 * Test redact sensitive data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\LoggerService::redact_sensitive_data
	 * @return void
	 */
	public function test_redact_sensitive_data(): void {
		// Test redacting array data.

		$array_data = [
			'app_id'         => 'test_id',
			'public_key'     => 'public-123',
			'transaction_id' => 'test-transaction-id',
			'timestamp'      => 1712345678,
			'customer'       => [
				'email'      => 'test@example.com',
				'first_name' => 'John',
				'last_name'  => 'Doe',
			],
		];

		$redacted_array = $this->get_private_method_value( 'redact_sensitive_data', $array_data );

		$this->assertEquals( '[REDACTED]', $redacted_array['app_id'] );
		$this->assertEquals( 'public-123', $redacted_array['public_key'] );
		$this->assertEquals( 'test-transaction-id', $redacted_array['transaction_id'] );
		$this->assertEquals( '[REDACTED]', $redacted_array['customer']['email'] );
		$this->assertEquals( '[REDACTED]', $redacted_array['customer']['first_name'] );
		$this->assertEquals( '[REDACTED]', $redacted_array['customer']['last_name'] );

		// Test redacting object data.

		$obj_data                       = new \stdClass();
		$obj_data->app_key              = 'secret-key';
		$obj_data->public_value         = 'public-val';
		$obj_data->customer             = new \stdClass();
		$obj_data->customer->email      = 'test@example.com';
		$obj_data->customer->first_name = 'John';
		$obj_data->customer->last_name  = 'Doe';

		$redacted_obj = $this->get_private_method_value( 'redact_sensitive_data', $obj_data );

		$this->assertEquals( '[REDACTED]', $redacted_obj->app_key );
		$this->assertEquals( 'public-val', $redacted_obj->public_value );
		$this->assertEquals( '[REDACTED]', $redacted_obj->customer->email );
		$this->assertEquals( '[REDACTED]', $redacted_obj->customer->first_name );
		$this->assertEquals( '[REDACTED]', $redacted_obj->customer->last_name );

		// Test scalar value.

		$scalar_value    = 'test-value';
		$redacted_scalar = $this->get_private_method_value( 'redact_sensitive_data', $scalar_value );
		$this->assertEquals( 'test-value', $redacted_scalar );
	}

	/**
	 * Test log with debug disabled.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\LoggerService::log
	 * @return void
	 */
	public function test_log_with_debug_disabled(): void {
		// When the log is disabled the log method should not be called.

		$this->get_settings_service()
			->shouldReceive( 'is_enabled' )
			->once()
			->with( 'debug_log' )
			->andReturn( false );

		$this->logger->shouldNotReceive( 'log' );

		$this->service->log( 'Test message' );
	}

	/**
	 * Test log with debug enabled.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\LoggerService::log
	 * @return void
	 */
	public function test_log_with_debug_enabled(): void {
		// When the log is enabled the log method should be called with info level.

		$this->get_settings_service()
			->shouldReceive( 'is_enabled' )
			->once()
			->with( 'debug_log' )
			->andReturn( true );

		$this->logger->shouldReceive( 'log' )
			->once()
			->with( 'info', 'Test message', [ 'source' => 'acquired-com-for-woocommerce' ] );

		$this->service->log( 'Test message' );
	}

	/**
	 * Test log with invalid level.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\LoggerService::log
	 * @return void
	 */
	public function test_log_with_invalid_level(): void {
		// test if we get info for log level when the log level is invalid.

		$this->get_settings_service()
			->shouldReceive( 'is_enabled' )
			->once()
			->with( 'debug_log' )
			->andReturn( true );

		$this->logger->shouldReceive( 'log' )
			->once()
			->with( 'info', 'Test message', [ 'source' => 'acquired-com-for-woocommerce' ] );

		$this->service->log( 'Test message', 'invalid_level_level', [] );
	}

	/**
	 * Test log with context and sensitive data.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\LoggerService::log
	 * @return void
	 */
	public function test_log_with_context_and_sensitive_data(): void {
		// Test is log context returns redacted data for all the private field names.

		$this->get_settings_service()
		->shouldReceive( 'is_enabled' )
			->once()
			->with( 'debug_log' )
			->andReturn( true );

		$context = [
			'customer' => [
				'email'      => 'test@example.com',
				'first_name' => 'John',
				'last_name'  => 'Doe',
				'billing'    => [
					'line_1'       => '123 Main St',
					'line_2'       => 'Apt 4B',
					'city'         => 'New York',
					'postcode'     => '10001',
					'country_code' => 'US',
				],
				'payment'    => [
					'access_token' => 'secret-token',
					'hash'         => 'card-hash',
					'holder_name'  => 'John Doe',
					'scheme'       => 'visa',
					'number'       => '4111111111111111',
				],
				'app_id'     => 'test_id',
				'app_key'    => 'test_key',
				'dob'        => '1990-01-01',
				'ip'         => '192.168.1.1',
			],
		];

		$expected_context = [
			'source'   => 'acquired-com-for-woocommerce',
			'customer' => [
				'email'      => '[REDACTED]',
				'first_name' => '[REDACTED]',
				'last_name'  => '[REDACTED]',
				'billing'    => [
					'line_1'       => '[REDACTED]',
					'line_2'       => '[REDACTED]',
					'city'         => '[REDACTED]',
					'postcode'     => '[REDACTED]',
					'country_code' => '[REDACTED]',
				],
				'payment'    => [
					'access_token' => '[REDACTED]',
					'hash'         => '[REDACTED]',
					'holder_name'  => '[REDACTED]',
					'scheme'       => '[REDACTED]',
					'number'       => '[REDACTED]',
				],
				'app_id'     => '[REDACTED]',
				'app_key'    => '[REDACTED]',
				'dob'        => '[REDACTED]',
				'ip'         => '[REDACTED]',
			],
		];

		$this->logger->shouldReceive( 'log' )
			->once()
			->with( 'debug', 'Test message', $expected_context );

		$this->service->log( 'Test message', 'debug', $context );
	}
}
