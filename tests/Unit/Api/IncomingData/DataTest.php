<?php
/**
 * Test Data abstract class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\IncomingData;

use AcquiredComForWooCommerce\Tests\Framework\AbstractTestCase;
use AcquiredComForWooCommerce\Tests\Framework\TestClasses\DataTest as TestClass;

/**
 * Test Data class.
 *
 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data
 */
class DataTest extends AbstractTestCase {
	/**
	 * Get the test class instance.
	 *
	 * @return object
	 */
	protected function get_test_class(): object {
		return new TestClass();
	}

	/**
	 * Test type getter and setter.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::set_type
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::get_type
	 * @return void
	 */
	public function test_type_getter_and_setter(): void {
		$this->set_private_method_value( 'set_type', 'test' );
		$this->assertEquals( 'test', $this->test_class->get_type() );
	}

	/**
	 * Test transaction data getters and setter.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::set_transaction_data
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::get_transaction_id
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::get_transaction_status
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::get_order_id
	 * @return void
	 */
	public function test_transaction_data_getters_and_setter(): void {
		$this->set_private_method_value( 'set_transaction_data', 'transaction_123', 'success', 'order_123' );
		$this->assertEquals( 'transaction_123', $this->test_class->get_transaction_id() );
		$this->assertEquals( 'success', $this->test_class->get_transaction_status() );
		$this->assertEquals( 'order_123', $this->test_class->get_order_id() );
	}

	/**
	 * Test timestamp getter and setter.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::set_timestamp
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::get_timestamp
	 * @return void
	 */
	public function test_timestamp_getter_and_setter(): void {
		$this->set_private_method_value( 'set_timestamp', 1234567890 );
		$this->assertEquals( 1234567890, $this->test_class->get_timestamp() );
	}

	/**
	 * Test card ID getter and setter.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::set_card_id
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::get_card_id
	 * @return void
	 */
	public function test_card_id_getter_and_setter(): void {
		$this->test_class->set_card_id( 'card_123' );

		$this->assertEquals( 'card_123', $this->test_class->get_card_id() );
	}

	/**
	 * Test incoming data getter and setter.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::set_incoming_data
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::get_incoming_data
	 * @return void
	 */
	public function test_incoming_data_getter_and_setter(): void {
		// Test with array.
		$this->set_private_method_value( 'set_incoming_data', [ 'test' => 'data' ] );
		$this->assertEquals( [ 'test' => 'data' ], $this->test_class->get_incoming_data() );

		// Test with stdClass.
		$this->set_private_method_value( 'set_incoming_data', (object) [ 'test' => 'data' ] );
		$this->assertEquals( (object) [ 'test' => 'data' ], $this->test_class->get_incoming_data() );
	}

	/**
	 * Test get_log_data data.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\Data::get_log_data
	 * @return void
	 */
	public function test_get_log_data(): void {
		// Test with array data.
		$this->set_private_method_value( 'set_type', 'redirect' );
		$this->set_private_method_value( 'set_incoming_data', [ 'test' => 'array_data' ] );
		$this->assertEquals(
			[ 'incoming-redirect-data' => [ 'test' => 'array_data' ] ],
			$this->test_class->get_log_data()
		);

		// Test with stdClass data.
		$this->set_private_method_value( 'set_type', 'webhook' );
		$this->set_private_method_value( 'set_incoming_data', (object) [ 'test' => 'object_data' ] );
		$this->assertEquals(
			[ 'incoming-webhook-data' => (object) [ 'test' => 'object_data' ] ],
			$this->test_class->get_log_data()
		);
	}
}
