<?php
/**
 * ObserverInterface.
 */

declare(strict_types=1);

namespace AcquiredComForWooCommerce\Observers;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * ObserverInterface interface.
 */
interface ObserverInterface {
	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void;
}
