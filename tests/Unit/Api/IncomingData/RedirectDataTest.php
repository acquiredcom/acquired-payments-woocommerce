<?php
/**
 * Test RedirectData class.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Api\IncomingData;

use AcquiredComForWooCommerce\Api\IncomingData\RedirectData;
use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Tests\Framework\Traits\IncomingDataTestData;

/**
 * Test RedirectData class.
 *
 * @covers \AcquiredComForWooCommerce\Api\IncomingData\RedirectData
 */
class RedirectDataTest extends TestCase {
	/**
	 * Traits.
	 */
	use IncomingDataTestData;

	/**
	 * Test constructor properly initializes data and all getters work.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\RedirectData::__construct
	 * @return void
	 */
	public function test_constructor_initializes_data(): void {
		$test_data     = $this->get_test_redirect_data();
		$redirect_data = new RedirectData( $test_data, 'valid' );

		// Test that all getter methods return expected values.
		$this->assertEquals( 'redirect', $redirect_data->get_type() );
		$this->assertEquals( 'transaction_123', $redirect_data->get_transaction_id() );
		$this->assertEquals( 'success', $redirect_data->get_transaction_status() );
		$this->assertEquals( 'order_456', $redirect_data->get_order_id() );
		$this->assertEquals( 1621234567, $redirect_data->get_timestamp() );
		$this->assertEquals( $test_data, $redirect_data->get_incoming_data() );
		$this->assertEquals( 'valid', $redirect_data->get_hash_status() );
		$this->assertTrue( $redirect_data->is_hash_valid() );
	}

	/**
	 * Test hash status getters with an unverified hash.
	 *
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\RedirectData::get_hash_status
	 * @covers \AcquiredComForWooCommerce\Api\IncomingData\RedirectData::is_hash_valid
	 * @return void
	 */
	public function test_hash_status_unverified(): void {
		$redirect_data = new RedirectData( $this->get_test_redirect_data(), 'using_app_key_for_signing' );

		// Test that the hash status getters return expected values.
		$this->assertEquals( 'using_app_key_for_signing', $redirect_data->get_hash_status() );
		$this->assertFalse( $redirect_data->is_hash_valid() );
	}
}
