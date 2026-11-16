<?php
/**
 * ObserverService.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Services;

use AcquiredComForWooCommerce\Observers\ObserverInterface;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * ObserverService class.
 */
class ObserverService {
	/**
	 * Constructor.
	 *
	 * @param ObserverInterface[] $observers
	 */
	public function __construct( private array $observers ) {
		$this->init_observers();
	}

	/**
	 * Initialize observers.
	 *
	 * @return void
	 */
	private function init_observers(): void {
		foreach ( $this->observers as $observer ) {
			$observer->init_hooks();
		}
	}
}
