<?php
/**
 * Plugin class to initialize plugin functionality.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce;

use Psr\Container\ContainerInterface;
use AcquiredComForWooCommerce\Services\ObserverService;
use AcquiredComForWooCommerce\Services\SettingsService;
use AcquiredComForWooCommerce\WooCommerce\PaymentGateway;
use AcquiredComForWooCommerce\WooCommerce\PaymentMethod;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Plugin class.
 */
final class Plugin {
	/**
	 * Root file.
	 *
	 * @var string
	 */
	private string $root_file;

	/**
	 * Plugin basename.
	 *
	 * @var string
	 */
	private string $basename;

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface $container
	 */
	public function __construct( private ContainerInterface $container ) {
		$this->root_file = $this->container->get( SettingsService::class )->config['root_file'];
		$this->basename  = $this->container->get( SettingsService::class )->config['basename'];
		$this->container->get( ObserverService::class );
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	private function init_hooks(): void {
		add_action( 'init', [ $this, 'load_textdomain' ], 0 );
		add_action( 'before_woocommerce_init', [ $this, 'custom_order_tables_support' ] );
		add_filter( 'woocommerce_payment_gateways', [ $this, 'register_gateway' ] );
		add_action( 'woocommerce_blocks_loaded', [ $this, 'register_block_checkout' ] );
		add_filter( 'plugin_action_links_' . $this->basename, [ $this, 'add_settings_link' ] );
	}

	/**
	 * Load text domain.
	 *
	 * @return void
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'acquired-com-for-woocommerce', false, trailingslashit( dirname( $this->basename ) ) . $this->container->get( SettingsService::class )->config['lang_dir'] );
	}

	/**
	 * Declare custom tables support.
	 *
	 * @return void
	 */
	public function custom_order_tables_support(): void {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			/**
			 * Declare compatibility with custom order tables.
			 */
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', $this->root_file, true );
		}
	}

	/**
	 * Register payment gateway.
	 *
	 * @param array $methods
	 * @return array
	 */
	public function register_gateway( $methods ): array { // phpcs:ignore Squiz.Commenting.FunctionComment.TypeHintMissing
		$methods[] = $this->container->get( PaymentGateway::class );

		return $methods;
	}

	/**
	 * Register block checkout.
	 *
	 * @return void
	 */
	public function register_block_checkout(): void {
		if ( ! class_exists( '\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			return;
		}

		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( \Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( $this->container->get( PaymentMethod::class ) );
			}
		);
	}

	/**
	 * Add settings link to plugin action links.
	 *
	 * @param array $links
	 * @return array
	 */
	public function add_settings_link( $links ): array { // phpcs:ignore Squiz.Commenting.FunctionComment.TypeHintMissing
		$links['settings'] = sprintf(
			'<a href="%s" aria-label="%s">%s</a>',
			esc_url( $this->container->get( SettingsService::class )->get_admin_settings_url() ),
			esc_attr__( 'View Acquired.com for WooCommerce settings', 'acquired-com-for-woocommerce' ),
			esc_html__( 'Settings', 'acquired-com-for-woocommerce' )
		);

		return $links;
	}
}
