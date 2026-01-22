<?php
/**
 * TestCase.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Test case abstract.
 */
abstract class TestCase extends PHPUnitTestCase {
	/**
	 * Traits.
	 */
	use MockeryPHPUnitIntegration;

	/**
	 * Site URL.
	 *
	 * @var string
	 */
	protected string $site_url = 'https://example.com';

	/**
	 * Site name.
	 *
	 * @var string
	 */
	protected string $site_name = 'Test Store';

	/**
	 * Config.
	 *
	 * @var array{
	 *     root_file: string,
	 *     dir_path: string,
	 *     dir_url: string,
	 *     basename: string,
	 *     version: string,
	 *     php_version: string,
	 *     wc_version: string,
	 *     plugin_id: string,
	 *     plugin_name: string,
	 *     plugin_slug: string,
	 *     lang_dir: string,
	 *     log_dir_path: string,
	 *     site_name: string
	 * }
	 */
	protected array $config = [];

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp() : void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();

		$this->config = [
			'root_file'    => '/path/to/acquired-com-for-woocommerce/acquired-com-for-woocommerce.php',
			'dir_path'     => '/path/to/acquired-com-for-woocommerce',
			'dir_url'      => $this->site_url . '/wp-content/plugins/acquired-com-for-woocommerce',
			'basename'     => 'acquired-com-for-woocommerce/acquired-com-for-woocommerce.php',
			'version'      => '2.1.0',
			'php_version'  => '8.1',
			'wc_version'   => '8.1',
			'plugin_id'    => 'acfw',
			'plugin_name'  => 'Acquired.com for WooCommerce',
			'plugin_slug'  => 'acquired-com-for-woocommerce',
			'lang_dir'     => 'languages',
			'log_dir_path' => '/path/to/logs',
			'site_name'    => $this->site_name,
		];
	}

	/**
	 * Tear down the test case.
	 *
	 * @return void
	 */
	protected function tearDown() : void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
