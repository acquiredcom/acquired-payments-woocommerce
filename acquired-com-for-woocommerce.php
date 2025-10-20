<?php
/**
 * Plugin Name: Acquired.com for WooCommerce
 * Description: Securely accept Cards, Apple Pay & Google Pay on your store using Acquired.com.
 * Version: 2.0.0
 * Author: Acquired
 * Author URI: https://acquired.com
 *
 * Text Domain: acquired-com-for-woocommerce
 * Domain Path: /languages
 *
 * License:     MIT License
 * License URI: https://opensource.org/license/mit
 *
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  woocommerce
 *
 * WC requires at least: 8.1
 * WC tested up to:      10.2.2
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce;

// @codeCoverageIgnoreStart

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Define the constants.
 */
if ( ! defined( 'ACFW_ROOT_FILE' ) ) {
	define( 'ACFW_ROOT_FILE', __FILE__ );
}
if ( ! defined( 'ACFW_DIR_PATH' ) ) {
	define( 'ACFW_DIR_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'ACFW_DIR_URL' ) ) {
	define( 'ACFW_DIR_URL', plugin_dir_url( __FILE__ ) );
}
if ( ! defined( 'ACFW_PLUGIN_BASENAME' ) ) {
	define( 'ACFW_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}
if ( ! defined( 'ACFW_VERSION' ) ) {
	define( 'ACFW_VERSION', '2.0.0' );
}
if ( ! defined( 'ACFW_PHP_VERSION' ) ) {
	define( 'ACFW_PHP_VERSION', '8.1' );
}
if ( ! defined( 'ACFW_WC_VERSION' ) ) {
	define( 'ACFW_WC_VERSION', '8.1' );
}

/**
 * Load the plugin.
 */
( function () {
	/**
	 * Check PHP version.
	 */
	if ( version_compare( PHP_VERSION, ACFW_PHP_VERSION, '<' ) ) {
		add_action(
			'admin_notices',
			function() {
				wp_admin_notice(
					sprintf(
						/* translators: %s: Required PHP version */
						esc_html__( 'Acquired.com for WooCommerce requires PHP version %s or higher. Please upgrade your PHP version.', 'acquired-com-for-woocommerce' ),
						ACFW_PHP_VERSION
					),
					[ 'type' => 'error' ]
				);
			}
		);
		return;
	}

	/**
	 * Autoload.
	 */
	$autoload_path = __DIR__ . '/vendor/autoload.php';
	if ( file_exists( $autoload_path ) && ! class_exists( '\AcquiredComForWooCommerce\Plugin' ) ) {
		require $autoload_path;
	}

	/**
	 * Initialization.
	 */
	function init() : void {
		/**
		 * Check if WooCommerce is active.
		 */
		if ( ! class_exists( 'woocommerce' ) ) {
			add_action(
				'admin_notices',
				function() {
					wp_admin_notice(
						/* translators: %s is URL link. */
						sprintf( esc_html__( 'Acquired.com for WooCommerce requires WooCommerce to be installed and active. You can download %s here.', 'acquired-com-for-woocommerce' ), '<a href="https://woocommerce.com/" target="_blank">WooCommerce</a>' ),
						[ 'type' => 'error' ]
					);
				}
			);

			return;
		}

		/**
		 * Check if WooCommerce is at the required version.
		 */
		if ( ! defined( '\WC_VERSION' ) || version_compare( constant( 'WC_VERSION' ), ACFW_WC_VERSION, '<' ) ) {
			add_action(
				'admin_notices',
				function() {
					wp_admin_notice(
						sprintf(
							/* translators: %s: Required PHP version */
							esc_html__( 'Acquired.com for WooCommerce requires WooCommerce version %s or higher. Please upgrade your WooCommerce version.', 'acquired-com-for-woocommerce' ),
							ACFW_WC_VERSION
						),
						[ 'type' => 'error' ]
					);
				}
			);
			return;
		}

		static $initialized;

		if ( ! $initialized ) {
			$container = require __DIR__ . '/src/bootstrap.php';
			$plugin    = $container->get( Plugin::class );

			$initialized = true;
			/**
			 * The hook fired after the plugin bootstrap with the plugin as a parameter.
			 */
			do_action( 'acfw_init', $plugin );
		}
	}

	/**
	 * Initialize the plugin.
	 */
	add_action(
		'plugins_loaded',
		function () {
			init();
		}
	);

	/**
	 * Activation hook.
	 */
	register_activation_hook(
		__FILE__,
		function() {
			init();
			/**
			 * The hook fired in register_activation_hook.
			 */
			do_action( 'acfw_activate' );
		}
	);

	/**
	 * Deactivation hook.
	 */
	register_deactivation_hook(
		__FILE__,
		function() {
			init();
			/**
			 * The hook fired in register_deactivation_hook.
			 */
			do_action( 'acfw_deactivate' );
		}
	);
} )();

// @codeCoverageIgnoreEnd
