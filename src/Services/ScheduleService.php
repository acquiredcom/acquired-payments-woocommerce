<?php
/**
 * ScheduleService.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Services;

use Exception;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * ScheduleService class.
 */
class ScheduleService {
	/**
	 * Action delay.
	 *
	 * @var int
	 */
	private int $delay = 30;

	/**
	 * Constructor.
	 *
	 * @param string $group
	 */
	public function __construct( private string $group ) {}

	/**
	 * Schedule an action.
	 *
	 * @param string $hook
	 * @param array $args
	 * @return void
	 * @throws Exception
	 */
	public function schedule( string $hook, array $args ): void {
		$action = as_schedule_single_action(
			time() + $this->delay,
			$hook,
			$args,
			$this->group
		);

		if ( ! $action ) {
			throw new Exception( 'Failed to schedule action.' );
		}
	}
}
