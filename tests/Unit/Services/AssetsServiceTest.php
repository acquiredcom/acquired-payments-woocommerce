<?php
/**
 * AssetsServiceTest.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Unit\Services;

use AcquiredComForWooCommerce\Tests\Framework\TestCase;
use AcquiredComForWooCommerce\Services\AssetsService;
use Brain\Monkey\Functions;

/**
 * AssetsServiceTest class.
 *
 * @covers \AcquiredComForWooCommerce\Services\AssetsService
 */
class AssetsServiceTest extends TestCase {
	/**
	 * AssetsService class.
	 *
	 * @var AssetsService
	 */
	private AssetsService $service;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->service = new AssetsService(
			$this->config['version'],
			$this->config['dir_path'],
			$this->config['dir_url'],
		);
	}

	/**
	 * Test get assets directory.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AssetsService::get_assets_directory
	 * @return void
	 */
	public function test_get_assets_directory(): void {
		// Check if we have the expected path to the assets directory.
		$this->assertEquals(
			'/path/to/acquired-com-for-woocommerce/assets/dist/',
			$this->service->get_assets_directory()
		);
	}

	/**
	 * Test get assets directory URI.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AssetsService::get_assets_directory_uri
	 * @return void
	 */
	public function test_get_assets_directory_uri(): void {
		// Check if we have the expected URI to the assets directory.
		$this->assertEquals(
			'https://example.com/wp-content/plugins/acquired-com-for-woocommerce/assets/dist/',
			$this->service->get_assets_directory_uri()
		);
	}

	/**
	 * Test get asset URI.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AssetsService::get_asset_uri
	 * @return void
	 */
	public function test_get_asset_uri(): void {
		// Check if we have the expected URI for a specific asset.
		$this->assertEquals(
			'https://example.com/wp-content/plugins/acquired-com-for-woocommerce/assets/dist/js/test-file.js',
			$this->service->get_asset_uri( 'js/test-file.js' )
		);
	}

	/**
	 * Test get asset URI.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AssetsService::get_asset_path
	 * @return void
	 */
	public function test_get_path(): void {
		// Check if we have the expected server path for a specific asset.
		$this->assertEquals(
			'/path/to/acquired-com-for-woocommerce/assets/dist/js/test-file.js',
			$this->service->get_asset_path( 'js/test-file.js' )
		);
	}

	/**
	 * Test asset exists.
	 *
	 * @covers \AcquiredComForWooCommerce\Services\AssetsService::asset_exists
	 * @return void
	 */
	public function test_asset_exists(): void {
		// Mock the file_exists function to simulate asset existence.
		Functions\expect( 'file_exists' )->once()->andReturn( true );
		$this->assertTrue( $this->service->asset_exists( 'js/test-file.js' ) );

		// Mock the file_exists function to simulate asset non-existence.
		Functions\expect( 'file_exists' )->once()->andReturn( false );
		$this->assertFalse( $this->service->asset_exists( 'js/test-file.js' ) );
	}
}
