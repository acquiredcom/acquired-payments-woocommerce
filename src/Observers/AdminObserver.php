<?php
/**
 * AdminObserver.
 */

declare(strict_types=1);

namespace AcquiredComForWooCommerce\Observers;

use AcquiredComForWooCommerce\Services\AdminService;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * AdminObserver class.
 */
class AdminObserver implements ObserverInterface {
	/**
	 * Constructor.
	 *
	 * @param AdminService $admin_service
	 */
	public function __construct( private AdminService $admin_service ) {}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'admin_notices', [ $this->admin_service, 'settings_notice' ] );
		add_action( 'admin_notices', [ $this->admin_service, 'order_notice' ] );
	}
}
