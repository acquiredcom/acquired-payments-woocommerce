<?php
/**
 * Test WebhookData class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\IncomingData;

use AcquiredComForWooCommerce\Api\IncomingData\WebhookData;
use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\IncomingDataTestData;

/**
 * Test WebhookData class.
 *
 * @covers \AcquiredComForWooCommerce\Api\IncomingData\WebhookData
 */
class WebhookDataTest extends TestCase {
	/**
	 * Traits.
	 */
	use IncomingDataTestData;

	/**
	 * Test constructor properly initializes data and all getters work for status update webhook.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\WebhookData::__construct
	 * @return void
	 */
	public function test_constructor_initializes_data_for_status_update(): void {
		$test_data    = $this->get_test_webhook_data( 'status_update' );
		$webhook_data = new WebhookData( $test_data );

		$this->assertEquals( 'webhook', $webhook_data->get_type() );
		$this->assertEquals( 'test_transaction_456', $webhook_data->get_transaction_id() );
		$this->assertEquals( 'success', $webhook_data->get_transaction_status() );
		$this->assertEquals( 'order_789', $webhook_data->get_order_id() );
		$this->assertEquals( 1621234567, $webhook_data->get_timestamp() );
		$this->assertEquals( $test_data, $webhook_data->get_incoming_data() );
		$this->assertEquals( 'status_update', $webhook_data->get_webhook_type() );
		$this->assertEquals( 'webhook_123', $webhook_data->get_webhook_id() );
	}

	/**
	 * Test constructor properly initializes data and all getters work for card new webhook.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\WebhookData::__construct
	 * @return void
	 */
	public function test_constructor_initializes_data_for_card_new(): void {
		$test_data    = $this->get_test_webhook_data( 'card_new' );
		$webhook_data = new WebhookData( $test_data );

		$this->assertEquals( 'webhook', $webhook_data->get_type() );
		$this->assertEquals( 'test_transaction_456', $webhook_data->get_transaction_id() );
		$this->assertEquals( 'success', $webhook_data->get_transaction_status() );
		$this->assertEquals( 'order_789', $webhook_data->get_order_id() );
		$this->assertEquals( 1621234567, $webhook_data->get_timestamp() );
		$this->assertEquals( $test_data, $webhook_data->get_incoming_data() );
		$this->assertEquals( 'card_new', $webhook_data->get_webhook_type() );
		$this->assertEquals( 'webhook_123', $webhook_data->get_webhook_id() );
		$this->assertEquals( 'card_1011', $webhook_data->get_card_id() );
	}

	/**
	 * Test constructor properly initializes data and all getters work for card update webhook.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\WebhookData::__construct
	 * @return void
	 */
	public function test_constructor_initializes_data_for_card_update(): void {
		$test_data    = $this->get_test_webhook_data( 'card_update' );
		$webhook_data = new WebhookData( $test_data );

		$this->assertEquals( 'webhook', $webhook_data->get_type() );
		$this->assertEquals( 'card_1011', $webhook_data->get_card_id() );
		$this->assertEquals( 1621234567, $webhook_data->get_timestamp() );
		$this->assertEquals( $test_data, $webhook_data->get_incoming_data() );
		$this->assertEquals( 'card_update', $webhook_data->get_webhook_type() );
		$this->assertEquals( 'webhook_123', $webhook_data->get_webhook_id() );
	}
}
