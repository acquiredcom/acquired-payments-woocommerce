<?php
/**
 * SettingsObserver.
 */

declare(strict_types=1);

namespace AcquiredComForWooCommerce\Observers;

use AcquiredComForWooCommerce\Api\ApiClient;
use AcquiredComForWooCommerce\Services\SettingsService;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * SettingsObserver class.
 */
class SettingsObserver implements ObserverInterface {
	/**
	 * Constructor.
	 *
	 * @param ApiClient $api_client
	 * @param SettingsService $settings_service
	 */
	public function __construct( private ApiClient $api_client, private SettingsService $settings_service ) {}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init_hooks(): void {
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->settings_service->config['plugin_id'], [ $this, 'options_updated' ], 20 );
	}

	/**
	 * Options updated.
	 *
	 * @return void
	 */
	public function options_updated(): void {
		$this->settings_service->reload_options();
		$this->settings_service->set_api_credentials_validation_status( $this->api_client->validate_credentials() );
		$this->settings_service->clear_signing_key_issue();
	}
}
