<?php
/**
 * Test IncomingDataHandler class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api;

use AcquiredComForWooCommerce\Api\IncomingDataHandler;
use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\LoggerServiceMock;
use AcquiredComForWooCommerce\Api\IncomingData\RedirectData;
use AcquiredComForWooCommerce\Api\IncomingData\WebhookData;
use AcquiredComForWooCommerce\Tests\Framework\Traits\IncomingDataTestData;
use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;
use Brain\Monkey\Functions;
use Exception;
use stdClass;

/**
 * Test IncomingDataHandler class.
 *
 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler
 */
class IncomingDataHandlerTest extends TestCase {
	/**
	 * Traits.
	 */
	use LoggerServiceMock;
	use IncomingDataTestData;
	use Reflection;

	/**
	 * Test app key.
	 *
	 * @var string
	 */
	private string $test_app_key = '123456789';

	/**
	 * Test required fields.
	 *
	 * @var array
	 */
	private array $test_required_fields = [ 'field1', 'field2' ];

	/**
	 * Test class.
	 *
	 * @var IncomingDataHandler
	 */
	private IncomingDataHandler $test_class;

	/**
	 * Set up the test case.
	 */
	protected function setUp() : void {
		parent::setUp();

		Functions\stubs(
			[
				'sanitize_text_field' => function ( $text ) {
					// Mock sanitize_text_field to only strip HTML tags.
					// This is a simplified version for testing purposes.
					return strip_tags( $text ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags
				},
				'wp_unslash'          => function ( $data ) {
					return $data;
				},
			]
		);

		$this->mock_logger_service();
		$this->set_hash_key( $this->test_app_key );
		$this->test_class = new IncomingDataHandler( $this->get_logger_service(), $this->test_app_key );
		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Test sanitize_data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::sanitize_data
	 */
	public function test_sanitize_data() : void {
		// Test string with HTML.
		$this->assertEquals(
			'Hello World',
			$this->get_private_method_value( 'sanitize_data', '<script>Hello World</script>' )
		);

		// Test integer preservation.
		$this->assertEquals(
			42,
			$this->get_private_method_value( 'sanitize_data', 42 )
		);

		// Test array with mixed content.

		$dirty_array = [
			'clean'  => 'Clean Data',
			'string' => '<p>Test</p>',
			'number' => 123,
			'nested' => [
				'dirty' => '<script>bad</script>Good',
				'clean' => 456,
			],
		];

		$result = $this->get_private_method_value( 'sanitize_data', $dirty_array );

		$this->assertEquals( 'Clean Data', $result['clean'] );
		$this->assertEquals( 'Test', $result['string'] );
		$this->assertEquals( 123, $result['number'] );
		$this->assertEquals( 'badGood', $result['nested']['dirty'] );
		$this->assertEquals( 456, $result['nested']['clean'] );

		// Test object with mixed content.

		$dirty_object         = new stdClass();
		$dirty_object->clean  = 'Clean Data';
		$dirty_object->string = '<p>Test Object</p>';
		$dirty_object->number = 789;
		$nested_object        = new stdClass();
		$nested_object->dirty = '<script>bad</script>Good';
		$nested_object->clean = 1011;
		$dirty_object->nested = $nested_object;

		$result = $this->get_private_method_value( 'sanitize_data', $dirty_object );
		$this->assertEquals( 'Clean Data', $result->clean );
		$this->assertEquals( 'Test Object', $result->string );
		$this->assertEquals( 789, $result->number );
		$this->assertEquals( 'badGood', $result->nested->dirty );
		$this->assertEquals( 1011, $result->nested->clean );
	}

	/**
	 * Test validate_required_fields.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_required_fields
	 */
	public function test_validate_required_fields_with_valid_fields() : void {
		$array_data = [
			'field1' => 'value1',
			'field2' => 'value2',
			'field3' => 'value3',
		];

		$object_data         = new stdClass();
		$object_data->field1 = 'value1';
		$object_data->field2 = 'value2';
		$object_data->field3 = 'value3';

		$this->assertNull( $this->get_private_method_value( 'validate_required_fields', $array_data, $this->test_required_fields ) );
		$this->assertNull( $this->get_private_method_value( 'validate_required_fields', $object_data, $this->test_required_fields ) );

	}

	/**
	 * Test validate_required_fields with missing fields.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_required_fields
	 */
	public function test_validate_required_fields_array_with_missing_fields() : void {
		$incomplete_array = [
			'field1' => 'value1',
			'field3' => 'value3',
		];

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Missing required fields in test_context: "field2".' );
		$this->set_private_method_value( 'validate_required_fields', $incomplete_array, $this->test_required_fields, 'test_context' );
	}

	/**
	 * Test validate_required_fields with object missing fields.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_required_fields
	 */
	public function test_validate_required_fields_object_with_missing_fields() : void {
		$incomplete_object         = new stdClass();
		$incomplete_object->field1 = 'value1';
		$incomplete_object->field3 = 'value3';

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Missing required fields in test_context: "field2".' );
		$this->set_private_method_value( 'validate_required_fields', $incomplete_object, $this->test_required_fields, 'test_context' );
	}

	/**
	 * Test validate_required_fields with empty array data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_required_fields
	 */
	public function test_validate_required_fields_array_with_empty_data() : void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Missing required fields in webhook: "field1, field2".' );
		$this->set_private_method_value( 'validate_required_fields', [], $this->test_required_fields, );
	}

	/**
	 * Test validate_required_fields with empty object data.
	 *
	 * @return void
	 */
	public function test_validate_required_fields_object_with_empty_data() : void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Missing required fields in webhook: "field1, field2".' );
		$this->set_private_method_value( 'validate_required_fields', new stdClass(), $this->test_required_fields, );
	}

	/**
	 * Test validate_redirect_hash.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_redirect_hash
	 */
	public function test_validate_redirect_hash() : void {
		$redirect_data = $this->get_test_redirect_data();

		// Test with valid data and hash.
		$this->assertTrue( $this->get_private_method_value( 'validate_redirect_hash', $redirect_data ) );

		// Test with invalid hash.
		$invalid_data         = $redirect_data;
		$invalid_data['hash'] = 'invalid_hash';
		$this->assertFalse( $this->get_private_method_value( 'validate_redirect_hash', $invalid_data ) );

		// Test with missing app key.
		$this->set_private_property_value( 'app_key', '' );
		$this->assertFalse( $this->get_private_method_value( 'validate_redirect_hash', $redirect_data ) );
	}

	/**
	 * Test validate_webhook_hash.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_webhook_hash
	 */
	public function test_validate_webhook_hash() : void {
		$webhook_data = $this->get_test_webhook_data( 'status_update' );
		$webhook_json = json_encode( $webhook_data );
		$hash_valid   = $this->calculate_test_webhook_hash( $webhook_data );
		$hash_invalid = 'invalid_hash';

		// Test with valid data and hash.
		$this->assertTrue( $this->get_private_method_value( 'validate_webhook_hash', $webhook_json, $hash_valid ) );

		// Test with invalid hash.
		$this->assertFalse( $this->get_private_method_value( 'validate_webhook_hash', $webhook_json, $hash_invalid ) );

		// Test with whitespace in json.
		$this->assertTrue( $this->get_private_method_value( 'validate_webhook_hash', json_encode( $webhook_data, JSON_PRETTY_PRINT ), $hash_valid ) );

		// Test with missing app key.
		$this->set_private_property_value( 'app_key', '' );
		$this->assertFalse( $this->get_private_method_value( 'validate_webhook_hash', $webhook_json, $hash_valid ) );
	}

	/**
	 * Test format_redirect_data with valid data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::format_redirect_data
	 */
	public function test_format_redirect_data_with_valid_data() : void {
		$this->assertInstanceOf(
			RedirectData::class,
			$this->get_private_method_value( 'format_redirect_data', $this->get_test_redirect_data() )
		);
	}

	/**
	 * Test format_redirect_data with invalid data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::format_redirect_data
	 */
	public function test_format_redirect_data_with_invalid_data() : void {
		$data = $this->get_test_redirect_data();
		unset( $data['status'] );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Missing required fields in redirect_data: "status".' );
		$this->set_private_method_value( 'format_redirect_data', $data );
	}

	/**
	 * Test format_redirect_data with invalid hash.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::format_redirect_data
	 */
	public function test_format_redirect_data_with_invalid_hash() : void {
		$data         = $this->get_test_redirect_data();
		$data['hash'] = 'invalid_hash';

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Redirect data hash is invalid.' );
		$this->set_private_method_value( 'format_redirect_data', $data );
	}

	/**
	 * Test that get_webhook_body_requirements returns correct array for status_update type.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::get_webhook_body_requirements
	 */
	public function test_get_webhook_body_requirements_for_status_update() : void {
		$result = $this->get_private_method_value( 'get_webhook_body_requirements', 'status_update' );
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'required', $result );
		$this->assertEquals(
			[ 'transaction_id', 'status', 'order_id' ],
			$result['required']
		);
	}

	/**
	 * Test that get_webhook_body_requirements returns correct array for card_new type.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::get_webhook_body_requirements
	 */
	public function test_get_webhook_body_requirements_for_card_new() : void {
		$result = $this->get_private_method_value( 'get_webhook_body_requirements', 'card_new' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'required', $result );
		$this->assertEquals(
			[ 'transaction_id', 'status', 'order_id', 'card_id' ],
			$result['required']
		);
	}

	/**
	 * Test that get_webhook_body_requirements returns correct array for card_update type.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::get_webhook_body_requirements
	 */
	public function test_get_webhook_body_requirements_for_card_update() : void {
		$result = $this->get_private_method_value( 'get_webhook_body_requirements', 'card_update' );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'required', $result );
		$this->assertArrayHasKey( 'nested', $result );
		$this->assertEquals(
			[ 'card_id', 'update_type', 'update_detail', 'card' ],
			$result['required']
		);
		$this->assertEquals(
			[ 'holder_name', 'scheme', 'number', 'expiry_month', 'expiry_year' ],
			$result['nested']['card']
		);
	}

	/**
	 * Test that get_webhook_body_requirements returns empty array for invalid type.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::get_webhook_body_requirements
	 */
	public function test_get_webhook_body_requirements_returns_empty_array_for_invalid_type() : void {
		$result = $this->get_private_method_value( 'get_webhook_body_requirements', 'invalid_type' );

		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test validate_webhook_body with valid status_update data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_webhook_body
	 */
	public function test_validate_webhook_body_with_valid_status_update_data() : void {
		$this->assertNull( $this->get_private_method_value( 'validate_webhook_body', $this->get_test_webhook_data( 'status_update' )->webhook_body, 'status_update' ) );
	}

	/**
	 * Test validate_webhook_body with valid card_new data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_webhook_body
	 */
	public function test_validate_webhook_body_with_valid_card_new_data() : void {
		$this->assertNull( $this->get_private_method_value( 'validate_webhook_body', $this->get_test_webhook_data( 'card_new' )->webhook_body, 'card_new' ) );
	}

	/**
	 * Test validate_webhook_body with valid card_update data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_webhook_body
	 */
	public function test_validate_webhook_body_with_valid_card_update_data() : void {
		$this->assertNull( $this->get_private_method_value( 'validate_webhook_body', $this->get_test_webhook_data( 'card_update' )->webhook_body, 'card_update' ) );
	}

	/**
	 * Test validate_webhook_body with invalid webhook type.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_webhook_body
	 */
	public function test_validate_webhook_body_throws_exception_for_invalid_type() : void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Invalid webhook type: invalid_type.' );
		$this->set_private_method_value( 'validate_webhook_body', $this->get_test_webhook_data( 'status_update' )->webhook_body, 'invalid_type' );
	}

	/**
	 * Test validate_webhook_body with missing required fields.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_webhook_body
	 */
	public function test_validate_webhook_body_throws_exception_for_missing_fields() : void {
		$body                 = new stdClass();
		$body->transaction_id = 'test_transaction_456';

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Missing required fields in webhook_body: "status, order_id".' );
		$this->set_private_method_value( 'validate_webhook_body', $body, 'status_update' );
	}

	/**
	 * Test validate_webhook_body with missing nested fields.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_webhook_body
	 */
	public function test_validate_webhook_body_throws_exception_for_missing_nested_fields() : void {
		$data                     = $this->get_test_webhook_data( 'card_update' )->webhook_body;
		$data->card->number       = null;
		$data->card->expiry_month = null;

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Missing required fields in webhook_body: "number, expiry_month".' );
		$this->set_private_method_value( 'validate_webhook_body', $data, 'card_update' );
	}

	/**
	 * Test format_webhook_data with valid data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::format_webhook_data
	 */
	public function test_format_webhook_data_with_valid_data() : void {
		$data = $this->get_test_webhook_data( 'status_update' );
		$hash = $this->calculate_test_webhook_hash( $data );

		$this->assertInstanceOf(
			WebhookData::class,
			$this->get_private_method_value( 'format_webhook_data', json_encode( $data ), $hash )
		);
	}

	/**
	 * Test format_webhook_data with invalid hash.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::format_webhook_data
	 */
	public function test_format_webhook_data_with_invalid_hash() : void {
		$data = $this->get_test_webhook_data( 'status_update' );
		$hash = 'invalid_hash';

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Webhook hash is invalid.' );
		$this->set_private_method_value( 'format_webhook_data', json_encode( $data ), $hash );
	}

	/**
	 * Test format_webhook_data with invalid data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::format_webhook_data
	 */
	public function test_format_webhook_data_with_invalid_data() : void {
		$data = $this->get_test_webhook_data( 'status_update' );
		$hash = $this->calculate_test_webhook_hash( $data );

		Functions\expect( 'json_decode' )->once()->andReturn( false );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Webhook data is invalid.' );
		$this->set_private_method_value( 'format_webhook_data', json_encode( $data ), $hash );
	}

	/**
	 * Test format_webhook_data with invalid fields.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::format_webhook_data
	 */
	public function test_format_webhook_data_with_invalid_fields() : void {
		$data               = $this->get_test_webhook_data( 'status_update' );
		$data->webhook_type = '';
		$hash               = $this->calculate_test_webhook_hash( $data );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Missing required fields in webhook: "webhook_type".' );
		$this->set_private_method_value( 'format_webhook_data', json_encode( $data ), $hash );
	}

	/**
	 * Test format_webhook_data with invalid webhook type.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::format_webhook_data
	 */
	public function test_format_webhook_data_with_invalid_webhook_type() : void {
		$data               = $this->get_test_webhook_data( 'status_update' );
		$data->webhook_type = 'invalid_type';
		$hash               = $this->calculate_test_webhook_hash( $data );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Wrong webhook type sent. Webhook type "invalid_type". Webhook ID: webhook_123.' );
		$this->set_private_method_value( 'format_webhook_data', json_encode( $data ), $hash );
	}

	/**
	 * Test format_webhook_data with invalid webhook body.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::format_webhook_data
	 */
	public function test_format_webhook_data_with_invalid_webhook_body() : void {
		$data                               = $this->get_test_webhook_data( 'status_update' );
		$data->webhook_body->transaction_id = '';
		$hash                               = $this->calculate_test_webhook_hash( $data );

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Missing required fields in webhook_body: "transaction_id".' );
		$this->set_private_method_value( 'format_webhook_data', json_encode( $data ), $hash );
	}

	/**
	 * Test get_redirect_data with valid data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::get_redirect_data
	 */
	public function test_get_redirect_data_with_valid_data() : void {
		$data = $this->get_test_redirect_data();

		$this->get_logger_service()->expects( 'log' )
			->once()
			->with(
				'Incoming redirect data received successfully.',
				'debug',
				[ 'incoming-redirect-data' => $data ]
			);

		$result = $this->test_class->get_redirect_data( $data );

		$this->assertInstanceOf( RedirectData::class, $result );
	}

	/**
	 * Test get_redirect_data with invalid data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::get_redirect_data
	 */
	public function test_get_redirect_data_with_invalid_data() : void {
		$this->get_logger_service()->expects( 'log' )
			->once()
			->with(
				'Missing required fields in redirect_data: "status, transaction_id, order_id, timestamp, hash".',
				'error'
			);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Missing required fields in redirect_data: "status, transaction_id, order_id, timestamp, hash".' );

		$this->test_class->get_redirect_data( [] );
	}

	/**
	 * Test get_webhook_data with valid data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::get_webhook_data
	 */
	public function test_get_webhook_data_with_valid_data() : void {
		$data = $this->get_test_webhook_data( 'status_update' );
		$hash = $this->calculate_test_webhook_hash( $data );

		$this->get_logger_service()->expects( 'log' )
			->once()
			->with(
				'Incoming webhook data received successfully.',
				'debug',
				[ 'incoming-webhook-data' => $data ]
			);

		$result = $this->test_class->get_webhook_data( json_encode( $data ), $hash );

		$this->assertInstanceOf( WebhookData::class, $result );
	}

	/**
	 * Test get_webhook_data with invalid data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::get_webhook_data
	 */
	public function test_get_webhook_data_with_invalid_data() : void {
		$data = $this->get_test_webhook_data( 'status_update' );
		$hash = 'invalid_hash';

		$this->get_logger_service()->expects( 'log' )
			->once()
			->with(
				'Webhook hash is invalid.',
				'error'
			);

		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'Webhook hash is invalid.' );

		$this->test_class->get_webhook_data( json_encode( $data ), $hash );
	}

	/**
	 * Test validate_redirect_hash with comma-delimited hashes (key rotation).
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_redirect_hash
	 */
	public function test_validate_redirect_hash_with_comma_delimited_hashes() : void {
		// Test: comma-delimited hash where first hash matches (primary key).
		$redirect_data = $this->get_test_redirect_data_multi();
		$this->assertTrue( $this->get_private_method_value( 'validate_redirect_hash', $redirect_data ) );

		// Test: comma-delimited hash where second hash matches (alternate key).
		$alt_key            = 'old_key_987654321';
		$redirect_data_alt  = $this->get_test_redirect_data_multi( $alt_key );
		$this->assertTrue( $this->get_private_method_value( 'validate_redirect_hash', $redirect_data_alt ) );

		// Test: comma-delimited hash with whitespace.
		$hashes = array_filter( explode( ',', $redirect_data['hash'] ) );
		if ( count( $hashes ) === 2 ) {
			$redirect_data_ws = $redirect_data;
			$redirect_data_ws['hash'] = $hashes[0] . ' , ' . $hashes[1];
			$this->assertTrue( $this->get_private_method_value( 'validate_redirect_hash', $redirect_data_ws ) );
		}
	}

	/**
	 * Test validate_webhook_hash with comma-delimited hashes (key rotation).
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_webhook_hash
	 */
	public function test_validate_webhook_hash_with_comma_delimited_hashes() : void {
		$webhook_data  = $this->get_test_webhook_data( 'status_update' );
		$webhook_json  = json_encode( $webhook_data );

		// Test: comma-delimited hash where first hash matches (primary key).
		$hash_multi = $this->calculate_test_webhook_hash_multi( $webhook_data );
		$this->assertTrue( $this->get_private_method_value( 'validate_webhook_hash', $webhook_json, $hash_multi ) );

		// Test: comma-delimited hash where second hash matches (alternate key).
		$alt_key   = 'old_key_987654321';
		$hash_alt  = $this->calculate_test_webhook_hash_multi( $webhook_data, $alt_key );
		$this->assertTrue( $this->get_private_method_value( 'validate_webhook_hash', $webhook_json, $hash_alt ) );

		// Test: comma-delimited hash with whitespace.
		$hashes = array_filter( explode( ',', $hash_multi ) );
		if ( count( $hashes ) === 2 ) {
			$hash_ws = $hashes[0] . ' , ' . $hashes[1];
			$this->assertTrue( $this->get_private_method_value( 'validate_webhook_hash', $webhook_json, $hash_ws ) );
		}
	}

	/**
	 * Test validate_redirect_hash with signing_key takes precedence over app_key.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_redirect_hash
	 */
	public function test_validate_redirect_hash_signing_key_precedence() : void {
		// Create handler with both app_key and signing_key
		$signing_key = 'sk_new_signing_key_123';
		$handler = new IncomingDataHandler( $this->get_logger_service(), $this->test_app_key, $signing_key );
		$this->initialize_reflection( $handler );

		// Generate redirect data using signing_key
		$this->set_hash_key( $signing_key );
		$redirect_data = $this->get_test_redirect_data();

		// Validation should pass (uses signing_key, not app_key)
		$this->assertTrue( $this->get_private_method_value( 'validate_redirect_hash', $redirect_data, $handler ) );
	}

	/**
	 * Test validate_webhook_hash with signing_key takes precedence over app_key.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_webhook_hash
	 */
	public function test_validate_webhook_hash_signing_key_precedence() : void {
		// Create handler with both app_key and signing_key
		$signing_key = 'sk_new_signing_key_123';
		$handler = new IncomingDataHandler( $this->get_logger_service(), $this->test_app_key, $signing_key );
		$this->initialize_reflection( $handler );

		// Generate webhook data using signing_key
		$this->set_hash_key( $signing_key );
		$webhook_data = $this->get_test_webhook_data( 'status_update' );
		$webhook_json = json_encode( $webhook_data );
		$hash = $this->calculate_test_webhook_hash( $webhook_data );

		// Validation should pass (uses signing_key, not app_key)
		$this->assertTrue( $this->get_private_method_value( 'validate_webhook_hash', $webhook_json, $hash, $handler ) );
	}

	/**
	 * Test validate_redirect_hash with signing_key empty falls back to app_key.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_redirect_hash
	 */
	public function test_validate_redirect_hash_fallback_to_app_key() : void {
		// Create handler with app_key but empty signing_key (backward compatibility)
		$handler = new IncomingDataHandler( $this->get_logger_service(), $this->test_app_key, '' );
		$this->initialize_reflection( $handler );

		// Generate redirect data using app_key
		$this->set_hash_key( $this->test_app_key );
		$redirect_data = $this->get_test_redirect_data();

		// Validation should pass (falls back to app_key since signing_key is empty)
		$this->assertTrue( $this->get_private_method_value( 'validate_redirect_hash', $redirect_data, $handler ) );
	}

	/**
	 * Test validate_webhook_hash with signing_key empty falls back to app_key.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingDataHandler::validate_webhook_hash
	 */
	public function test_validate_webhook_hash_fallback_to_app_key() : void {
		// Create handler with app_key but empty signing_key (backward compatibility)
		$handler = new IncomingDataHandler( $this->get_logger_service(), $this->test_app_key, '' );
		$this->initialize_reflection( $handler );

		// Generate webhook data using app_key
		$this->set_hash_key( $this->test_app_key );
		$webhook_data = $this->get_test_webhook_data( 'status_update' );
		$webhook_json = json_encode( $webhook_data );
		$hash = $this->calculate_test_webhook_hash( $webhook_data );

		// Validation should pass (falls back to app_key since signing_key is empty)
		$this->assertTrue( $this->get_private_method_value( 'validate_webhook_hash', $webhook_json, $hash, $handler ) );
	}
}
