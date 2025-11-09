<?php
/**
 * SettingsServiceTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Services;

use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Services\SettingsService;
use Brain\Monkey\Functions;
use Mockery;

/**
 * SettingsServiceTest class.
 *
 * @covers \AcquiredComForWooCommerce\Services\SettingsService
 */
class SettingsServiceTest extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;

	/**
	 * SettingsService class.
	 *
	 * @var SettingsService
	 */
	private SettingsService $service;

	/**
	 * Expected fields.
	 *
	 * @var array
	 */
	private array $expected_fields = [
		'enabled',
		'title',
		'description',
		'environment',
		'company_id_production',
		'company_id_staging',
		'api_credentials',
		'app_id_production',
		'app_key_production',
		'app_id_staging',
		'app_key_staging',
		'additional',
		'transaction_type',
		'payment_reference',
		'debug_log',
		'tokenization',
		'3d_secure',
		'challenge_preferences',
		'contact_url',
		'submit_type',
		'update_card_webhook_url',
		'webhook_signing_keys',
		'signing_key_production',
		'signing_key_staging',
		'order',
		'cancel_refunded',
		'woo_wallet_refund',
	];

	/**
	 * Set test options.
	 *
	 * @param string $environment Environment.
	 * @param array  $modified_options Modified options.
	 *
	 * @return void
	 */
	private function set_test_options( string $environment = 'staging', array $modified_options = [] ) : void {
		$options = [
			'enabled'                 => 'no',
			'title'                   => 'Acquired.com for WooCommerce',
			'description'             => 'Test description',
			'environment'             => $environment,
			'company_id_production'   => 'production-company-id',
			'company_id_staging'      => 'staging-company-id',
			'app_id_production'       => 'production-app-id',
			'app_key_production'      => 'production-app-key',
			'app_id_staging'          => 'staging-app-id',
			'app_key_staging'         => 'staging-app-key',
			'transaction_type'        => 'capture',
			'payment_reference'       => $this->site_name,
			'debug_log'               => 'no',
			'tokenization'            => 'no',
			'3d_secure'               => 'yes',
			'challenge_preferences'   => 'no_preference',
			'contact_url'             => $this->site_url . '/contact',
			'submit_type'             => 'pay',
			'update_card_webhook_url' => $this->site_url . '/webhook',
			'cancel_refunded'         => 'yes',
			'woo_wallet_refund'       => 'yes',
		];

		if ( $modified_options ) {
			$options = array_merge( $options, $modified_options );
		}

		Functions\expect( 'get_option' )
		->once()
		->with( 'woocommerce_acfw_settings', [] )
		->andReturn( $options );
	}

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp() : void {
		parent::setUp();

		Functions\stubs(
			[
				'get_site_url' => $this->site_url,
				'WC'           => function() {
					$wc_mock = Mockery::mock( 'WC' );
					$wc_mock->shouldReceive( 'api_request_url' )->andReturnUsing(
						function( $endpoint ) {
							return $this->site_url . '/wc-api/' . $endpoint;
						}
					);
					return $wc_mock;
				},
			]
		);

		$this->service = new SettingsService( $this->config );
		$this->initialize_reflection( $this->service );
	}

	/**
	 * Test constructor.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::__construct
	 * @return void
	 */
	public function test_constructor() : void {
		// Test if constructor sets the expected properties to the right values.

		$this->assertEquals(
			'acfw_api_credentials_valid',
			$this->get_private_property_value( 'api_credentials_validation_option' )
		);

		$this->assertEquals(
			'woocommerce_acfw_settings',
			$this->get_private_property_value( 'option_id' )
		);
	}

	/**
	 * Test set API credentials validation status.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::set_api_credentials_validation_status
	 * @return void
	 */
	public function test_set_api_credentials_validation_status() : void {
		// Test setting API credentials validation status to valid.

		Functions\expect( 'update_option' )
			->once()
			->with( 'acfw_api_credentials_valid', true )
			->andReturn( true );

		$this->service->set_api_credentials_validation_status( true );

		// Test setting API credentials validation status to invalid.

		Functions\expect( 'update_option' )
			->once()
			->with( 'acfw_api_credentials_valid', false )
			->andReturn( true );

		$this->service->set_api_credentials_validation_status( false );
	}

	/**
	 * Test API credentials validation status.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::are_api_credentials_valid
	 * @return void
	 */
	public function test_api_credentials_validation() : void {
		// Test when API credentials are not valid.

		Functions\expect( 'get_option' )
			->once()
			->with( 'acfw_api_credentials_valid', false )
			->andReturn( '' );

		$this->assertFalse( $this->service->are_api_credentials_valid() );

		// Test when API credentials are valid.

		Functions\expect( 'update_option' )
			->once()
			->with( 'acfw_api_credentials_valid', true )
			->andReturn( '1' );

		$this->service->set_api_credentials_validation_status( true );

		Functions\expect( 'get_option' )
			->once()
			->with( 'acfw_api_credentials_valid', false )
			->andReturn( '1' );

		$this->assertTrue( $this->service->are_api_credentials_valid() );
	}

	/**
	 * Test option enabled check.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::is_enabled
	 * @return void
	 */
	public function test_is_enabled() : void {
		// Test fields that return true or false.
		$this->set_test_options( 'staging' );
		$this->assertFalse( $this->service->is_enabled( 'enabled' ) );
		$this->assertTrue( $this->service->is_enabled( '3d_secure' ) );
	}

	/**
	 * Test environment checks.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::is_environment_staging
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::is_environment_production
	 * @return void
	 */
	public function test_environment_checks() : void {
		// Test staging environment.
		$this->set_test_options( 'staging' );
		$this->assertTrue( $this->service->is_environment_staging() );
		$this->assertFalse( $this->service->is_environment_production() );

		// Test production environment.
		$this->set_test_options( 'production' );
		$this->service->reload_options();
		$this->assertTrue( $this->service->is_environment_production() );
		$this->assertFalse( $this->service->is_environment_staging() );

		// Test non-existing environment returns staging.
		// This is a fallback to staging if the environment is not recognized.
		$this->set_test_options( 'non-existing-env' );
		$this->service->reload_options();
		$this->assertFalse( $this->service->is_environment_staging() );
		$this->assertFalse( $this->service->is_environment_production() );
	}

	/**
	 * Test get API URLs.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_api_url
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_pay_url
	 * @return void
	 */
	public function test_get_api_urls() : void {
		// Test staging environment URL's.
		$this->set_test_options( 'staging' );
		$this->assertEquals( 'https://test-api.acquired.com/v1/', $this->service->get_api_url() );
		$this->assertEquals( 'https://test-pay.acquired.com/v1/', $this->service->get_pay_url(), );

		// Test production environment URL's.
		$this->set_test_options( 'production' );
		$this->service->reload_options();
		$this->assertEquals( 'https://api.acquired.com/v1/', $this->service->get_api_url() );
		$this->assertEquals( 'https://pay.acquired.com/v1/', $this->service->get_pay_url(), );

		// Test non-existing environment returns staging URLs.
		// This is a fallback to staging if the environment is not recognized.
		$this->set_test_options( 'non-existing-env' );
		$this->service->reload_options();
		$this->assertEquals( 'https://test-api.acquired.com/v1/', $this->service->get_api_url() );
		$this->assertEquals( 'https://test-pay.acquired.com/v1/', $this->service->get_pay_url(), );
	}

	/**
	 * Test get_hub_url method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_hub_url
	 * @return void
	 */
	public function test_get_hub_url() : void {

		$this->assertEquals( 'https://qahub.acquired.com/', $this->get_private_method_value( 'get_hub_url', 'staging' ) );
		$this->assertEquals( 'https://hub.acquired.com/', $this->get_private_method_value( 'get_hub_url', 'production' ) );
	}

	/**
	 * Test get company ID.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_company_id
	 * @return void
	 */
	public function test_get_company_id() : void {
		// Test staging environment.
		$this->set_test_options( 'staging' );
		$this->assertEquals( 'staging-company-id', $this->service->get_company_id() );

		// Test production environment.
		$this->set_test_options( 'production' );
		$this->service->reload_options();
		$this->assertEquals( 'production-company-id', $this->service->get_company_id() );

		// Test non-existing environment returns staging.
		// This is a fallback to staging if the environment is not recognized.
		$this->set_test_options( 'non-existing-env' );
		$this->service->reload_options();
		$this->assertEquals( 'staging-company-id', $this->service->get_company_id() );
	}

	/**
	 * Test get API credentials.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_api_credentials
	 * @return void
	 */
	public function test_get_api_credentials() : void {
		// Test staging environment credentials.
		$this->set_test_options( 'staging' );
		$credentials = $this->service->get_api_credentials();
		$this->assertIsArray( $credentials );
		$this->assertEquals( 'staging-app-id', $credentials['app_id'] );
		$this->assertEquals( 'staging-app-key', $credentials['app_key'] );

		// Test production environment credentials.
		$this->set_test_options( 'production' );
		$this->service->reload_options();
		$credentials = $this->service->get_api_credentials();
		$this->assertIsArray( $credentials );
		$this->assertEquals( 'production-app-id', $credentials['app_id'] );
		$this->assertEquals( 'production-app-key', $credentials['app_key'] );

		// Test missing app_id returns empty array.
		$this->set_test_options(
			'staging',
			[
				'app_id_staging'  => '',
				'app_key_staging' => 'test-key',
			]
		);
		$this->service->reload_options();
		$credentials = $this->service->get_api_credentials();
		$this->assertEmpty( $credentials );

		// Test missing app_key returns empty array.
		$this->set_test_options(
			'staging',
			[
				'app_id_staging'  => 'test-id',
				'app_key_staging' => '',
			]
		);
		$this->service->reload_options();
		$credentials = $this->service->get_api_credentials();
		$this->assertEmpty( $credentials );
	}

	/**
	 * Test get_app_key method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_app_key
	 * @return void
	 */
	public function test_get_app_key() : void {
		// Test staging environment app key.
		$this->set_test_options( 'staging' );
		$this->assertEquals( 'staging-app-key', $this->service->get_app_key() );

		// Test staging environment app key when it is empty.
		$this->set_test_options( 'staging', [ 'app_key_staging' => '' ] );
		$this->service->reload_options();
		$this->assertEquals( '', $this->service->get_app_key() );

		// Test production environment app key.
		$this->set_test_options( 'production' );
		$this->service->reload_options();
		$this->assertEquals( 'production-app-key', $this->service->get_app_key() );

		// Test production environment app key when it is empty.
		$this->set_test_options( 'production', [ 'app_key_production' => '' ] );
		$this->service->reload_options();
		$this->assertEquals( '', $this->service->get_app_key() );
	}

	/**
	 * Test get payment reference.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_payment_reference
	 * @return void
	 */
	public function test_get_payment_reference() : void {
		// Test default payment reference.
		$this->set_test_options( 'staging' );
		$this->assertEquals( 'Test Store', $this->service->get_payment_reference() );

		// Test if preg_replace removes characters and keeps the length to 18 characters.
		$this->set_test_options( 'staging', [ 'payment_reference' => '!@#$%^&*()+Test-Store Ref_!@#$%^&*()+12345678901234567890' ] );
		$this->service->reload_options();
		$this->assertEquals( 'Test-Store Ref_123', $this->service->get_payment_reference() );
		$this->assertEquals( 18, strlen( $this->service->get_payment_reference() ) );
	}

	/**
	 * Test get WooCommerce API endpoint.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_wc_api_endpoint
	 * @return void
	 */
	public function test_get_wc_api_endpoint() : void {
		// Test WooCommerce API endpoints.
		$this->assertEquals(
			'acquired-com-for-woocommerce-webhook',
			$this->service->get_wc_api_endpoint( 'webhook' )
		);
		$this->assertEquals(
			'acquired-com-for-woocommerce-redirect-new-order',
			$this->service->get_wc_api_endpoint( 'redirect-new-order' )
		);
		$this->assertEquals(
			'acquired-com-for-woocommerce-redirect-new-payment-method',
			$this->service->get_wc_api_endpoint( 'redirect-new-payment-method' )
		);
	}

	/**
	 * Test get_wc_api_url method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_wc_api_url
	 * @return void
	 */
	public function test_get_wc_api_url() : void {
		// Test WooCommerce API URL's return the site URL with the path to WooCommerce API and plugin endpoints.
		$this->assertEquals(
			'https://example.com/wc-api/acquired-com-for-woocommerce-webhook',
			$this->service->get_wc_api_url( 'webhook' )
		);
		$this->assertEquals(
			'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-order',
			$this->service->get_wc_api_url( 'redirect-new-order' )
		);
		$this->assertEquals(
			'https://example.com/wc-api/acquired-com-for-woocommerce-redirect-new-payment-method',
			$this->service->get_wc_api_url( 'redirect-new-payment-method' )
		);
	}

	/**
	 * Test get_shop_currency method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_shop_currency
	 * @return void
	 */
	public function test_get_shop_currency() : void {
		// Test WooCommerce shop currency.
		Functions\expect( 'get_woocommerce_currency' )
			->once()
			->andReturn( 'GBP' );

		$this->assertEquals( 'GBP', $this->service->get_shop_currency() );
	}

	/**
	 * Test get_payment_link_expiration_time method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_payment_link_expiration_time
	 * @return void
	 */
	public function test_get_payment_link_expiration_time() : void {
		// Test default link expiration time.
		$this->assertEquals( 300, $this->service->get_payment_link_expiration_time() );
	}

	/**
	 * Test get_payment_link_max_expiration_time method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_payment_link_max_expiration_time
	 * @return void
	 */
	public function test_get_payment_link_max_expiration_time() : void {
		// Test maximum link expiration time.
		$this->assertEquals( 2678400, $this->service->get_payment_link_max_expiration_time() );
	}

	/**
	 * Test get_admin_settings_url method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_admin_settings_url
	 * @return void
	 */
	public function test_get_admin_settings_url() : void {
		// Test admin settings URL has the right structure.
		Functions\expect( 'admin_url' )
			->once()
			->with( 'admin.php?page=wc-settings&tab=checkout&section=acfw' )
			->andReturn( 'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw' );

		$this->assertEquals(
			'https://example.com/wp-admin/admin.php?page=wc-settings&tab=checkout&section=acfw',
			$this->service->get_admin_settings_url()
		);
	}

	/**
	 * Test get WooCommerce hold stock time.
	 *
	 * @return void
	 */
	public function test_get_wc_hold_stock_time() : void {
		// Test WooCommerce hold stock time when stick management is enabled.

		Functions\expect( 'get_option' )
			->once()
			->with( 'woocommerce_hold_stock_minutes' )
			->andReturn( '60' ); // 3600 seconds.

		Functions\expect( 'get_option' )
			->once()
			->with( 'woocommerce_manage_stock' )
			->andReturn( 'yes' );

		$this->assertEquals( 3600, $this->service->get_wc_hold_stock_time() );

		// Test WooCommerce hold stock time when hold stock minutes is missing.

		Functions\expect( 'get_option' )
			->once()
			->with( 'woocommerce_hold_stock_minutes' )
			->andReturn( '' );

		Functions\expect( 'get_option' )
			->once()
			->with( 'woocommerce_manage_stock' )
			->andReturn( 'yes' );

		$this->assertEquals( 0, $this->service->get_wc_hold_stock_time() );

		// Test WooCommerce hold stock time when stock management is disabled.

		Functions\expect( 'get_option' )
			->once()
			->with( 'woocommerce_hold_stock_minutes' )
			->andReturn( '60' ); // 3600 seconds.

		Functions\expect( 'get_option' )
			->once()
			->with( 'woocommerce_manage_stock' )
			->andReturn( 'no' );

		$this->assertEquals( 0, $this->service->get_wc_hold_stock_time() );
	}

	/**
	 * Test get_hub_link method.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_hub_link
	 * @return void
	 */
	public function test_get_hub_link() : void {
		$result = $this->get_private_method_value( 'get_hub_link' );

		// Test if we have the expected link structure.
		$this->assertStringContainsString( 'class="acfw-env-link"', $result );
		$this->assertStringContainsString( 'href=" https://hub.acquired.com/"', $result );
		$this->assertStringContainsString( 'data-env-href-production="https://hub.acquired.com/"', $result );
		$this->assertStringContainsString( 'data-env-href-staging="https://qahub.acquired.com/"', $result );
		$this->assertStringContainsString( 'target="_blank"', $result );
		$this->assertStringContainsString( '>Acquired.com</a>', $result );
	}

	/**
	 * Test get fields.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_fields
	 * @return void
	 */
	public function test_get_fields() : void {
		$this->set_test_options( 'staging' );
		$fields = $this->service->get_fields();

		// Test if fields is an array.
		$this->assertIsArray( $fields );

		// Test if each field is an array.
		foreach ( $fields as $field ) :
			$this->assertIsArray( $field );
		endforeach;

		// Test if we have the expected fields.
		foreach ( $this->expected_fields as $key ) :
			$this->assertArrayHasKey( $key, $fields );
		endforeach;

		// Test if we have no unexpected fields.
		foreach ( array_keys( $fields ) as $key ) :
			$this->assertContains( $key, $this->expected_fields );
		endforeach;
	}

	/**
	 * Test fields structure.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_fields
	 * @return void
	 */
	public function test_fields_structure() : void {
		$this->set_test_options( 'staging' );
		$fields = $this->service->get_fields();

		// Test enabled field.
		$this->assertArrayHasKey( 'type', $fields['enabled'] );
		$this->assertEquals( 'checkbox', $fields['enabled']['type'] );
		$this->assertEquals( 'no', $fields['enabled']['default'] );

		// Test environment field.
		$this->assertArrayHasKey( 'type', $fields['environment'] );
		$this->assertEquals( 'select', $fields['environment']['type'] );
		$this->assertArrayHasKey( 'options', $fields['environment'] );
		$this->assertIsArray( $fields['environment']['options'] );
		$this->assertArrayHasKey( 'staging', $fields['environment']['options'] );
		$this->assertArrayHasKey( 'production', $fields['environment']['options'] );
		$this->assertArrayHasKey( 'default', $fields['environment'] );
		$this->assertEquals( 'staging', $fields['environment']['default'] );

		// Test credentials fields.
		$this->assertArrayHasKey( 'type', $fields['app_id_production'] );
		$this->assertEquals( 'text', $fields['app_id_production']['type'] );
		$this->assertArrayHasKey( 'type', $fields['app_key_production'] );
		$this->assertEquals( 'password', $fields['app_key_production']['type'] );
		$this->assertArrayHasKey( 'type', $fields['app_id_staging'] );
		$this->assertEquals( 'text', $fields['app_id_staging']['type'] );
		$this->assertArrayHasKey( 'type', $fields['app_key_staging'] );
		$this->assertEquals( 'password', $fields['app_key_staging']['type'] );

		// Test transaction type field.
		$this->assertArrayHasKey( 'type', $fields['transaction_type'] );
		$this->assertEquals( 'select', $fields['transaction_type']['type'] );
		$this->assertArrayHasKey( 'options', $fields['transaction_type'] );
		$this->assertIsArray( $fields['transaction_type']['options'] );
		$this->assertArrayHasKey( 'capture', $fields['transaction_type']['options'] );
		$this->assertArrayHasKey( 'authorisation', $fields['transaction_type']['options'] );
		$this->assertArrayHasKey( 'default', $fields['transaction_type'] );
		$this->assertEquals( 'capture', $fields['transaction_type']['default'] );

		// Test payment_reference field
		$this->assertArrayHasKey( 'type', $fields['payment_reference'] );
		$this->assertEquals( 'text', $fields['payment_reference']['type'] );
		$this->assertArrayHasKey( 'custom_attributes', $fields['payment_reference'] );
		$this->assertArrayHasKey( 'maxlength', $fields['payment_reference']['custom_attributes'] );
		$this->assertEquals( '18', $fields['payment_reference']['custom_attributes']['maxlength'] );

		// Test debug_log field
		$this->assertArrayHasKey( 'type', $fields['debug_log'] );
		$this->assertEquals( 'checkbox', $fields['debug_log']['type'] );
		$this->assertEquals( 'no', $fields['debug_log']['default'] );

		// Test tokenization field
		$this->assertArrayHasKey( 'type', $fields['tokenization'] );
		$this->assertEquals( 'checkbox', $fields['tokenization']['type'] );
		$this->assertEquals( 'no', $fields['tokenization']['default'] );

		// Test 3d_secure field
		$this->assertArrayHasKey( 'type', $fields['3d_secure'] );
		$this->assertEquals( 'checkbox', $fields['3d_secure']['type'] );
		$this->assertEquals( 'yes', $fields['3d_secure']['default'] );

		// Test challenge_preferences field
		$this->assertArrayHasKey( 'type', $fields['challenge_preferences'] );
		$this->assertEquals( 'select', $fields['challenge_preferences']['type'] );
		$this->assertArrayHasKey( 'options', $fields['challenge_preferences'] );
		$this->assertIsArray( $fields['challenge_preferences']['options'] );
		$this->assertArrayHasKey( 'challenge_mandated', $fields['challenge_preferences']['options'] );
		$this->assertArrayHasKey( 'challenge_preferred', $fields['challenge_preferences']['options'] );
		$this->assertArrayHasKey( 'no_challenge_requested', $fields['challenge_preferences']['options'] );
		$this->assertArrayHasKey( 'no_preference', $fields['challenge_preferences']['options'] );
		$this->assertEquals( 'no_preference', $fields['challenge_preferences']['default'] );

		// Test contact_url field
		$this->assertArrayHasKey( 'type', $fields['contact_url'] );
		$this->assertEquals( 'url', $fields['contact_url']['type'] );
		$this->assertArrayHasKey( 'default', $fields['contact_url'] );

		// Test submit_type field
		$this->assertArrayHasKey( 'type', $fields['submit_type'] );
		$this->assertEquals( 'select', $fields['submit_type']['type'] );
		$this->assertArrayHasKey( 'options', $fields['submit_type'] );
		$this->assertIsArray( $fields['submit_type']['options'] );
		$this->assertArrayHasKey( 'pay', $fields['submit_type']['options'] );
		$this->assertArrayHasKey( 'buy', $fields['submit_type']['options'] );
		$this->assertArrayHasKey( 'checkout', $fields['submit_type']['options'] );
		$this->assertArrayHasKey( 'donate', $fields['submit_type']['options'] );
		$this->assertArrayHasKey( 'register', $fields['submit_type']['options'] );
		$this->assertArrayHasKey( 'subscribe', $fields['submit_type']['options'] );
		$this->assertEquals( 'pay', $fields['submit_type']['default'] );

		// Test update_card_webhook_url field
		$this->assertArrayHasKey( 'type', $fields['update_card_webhook_url'] );
		$this->assertEquals( 'url', $fields['update_card_webhook_url']['type'] );
		$this->assertArrayHasKey( 'custom_attributes', $fields['update_card_webhook_url'] );
		$this->assertArrayHasKey( 'readonly', $fields['update_card_webhook_url']['custom_attributes'] );
		$this->assertTrue( $fields['update_card_webhook_url']['custom_attributes']['readonly'] );
	}

	/**
	 * Test get single field.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\SettingsService::get_field
	 * @return void
	 */
	public function test_get_field() : void {
		// Test if single field is an array.
		$this->set_test_options( 'staging' );
		$field = $this->service->get_field( 'environment' );
		$this->assertIsArray( $field );
	}
}
