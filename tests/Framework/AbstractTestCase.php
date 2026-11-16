<?php
/**
 * AbstractTestCase.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Tests\Framework;

use AcquiredComForWooCommerce\Tests\Framework\Traits\Reflection;

/**
 * Abstract test case for testing abstracts.
 */
abstract class AbstractTestCase extends TestCase {
	/**
	 * Traits.
	 */
	use Reflection;

	/**
	 * Test class instance.
	 *
	 * @var object
	 */
	protected object $test_class;

	/**
	 * Set up the test case.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->test_class = $this->get_test_class();
		$this->initialize_reflection( $this->test_class );
	}

	/**
	 * Get the test class instance.
	 *
	 * @return object
	 */
	abstract protected function get_test_class(): object;
}
