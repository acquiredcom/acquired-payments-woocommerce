<?php
/**
 * Bootstrap the container.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce;

use AcquiredComForWooCommerce\Services\AssetsService;
use AcquiredComForWooCommerce\Services\CustomerService;
use AcquiredComForWooCommerce\Services\LoggerService;
use AcquiredComForWooCommerce\Services\ObserverService;
use AcquiredComForWooCommerce\Services\OrderService;
use AcquiredComForWooCommerce\Services\SettingsService;
use AcquiredComForWooCommerce\Services\PaymentMethodService;
use AcquiredComForWooCommerce\Api\ApiClient;
use AcquiredComForWooCommerce\Api\IncomingDataHandler;
use AcquiredComForWooCommerce\Observers\AdminObserver;
use AcquiredComForWooCommerce\Observers\CustomerObserver;
use AcquiredComForWooCommerce\Observers\OrderObserver;
use AcquiredComForWooCommerce\Observers\PaymentMethodObserver;
use AcquiredComForWooCommerce\Observers\SettingsObserver;
use AcquiredComForWooCommerce\Services\AdminService;
use AcquiredComForWooCommerce\Services\ScheduleService;
use AcquiredComForWooCommerce\Services\TokenService;
use AcquiredComForWooCommerce\WooCommerce\PaymentGateway;
use AcquiredComForWooCommerce\WooCommerce\PaymentMethod;
use DI\ContainerBuilder;
use function DI\autowire;
use \Automattic\WooCommerce\Utilities\LoggingUtil;

// @codeCoverageIgnoreStart

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * Initialize the container.
 */
$builder = new ContainerBuilder();

$builder->addDefinitions(
	[
		'config'                    => [
			'root_file'    => ACFW_ROOT_FILE,
			'dir_path'     => ACFW_DIR_PATH,
			'dir_url'      => ACFW_DIR_URL,
			'basename'     => ACFW_PLUGIN_BASENAME,
			'version'      => ACFW_VERSION,
			'php_version'  => ACFW_PHP_VERSION,
			'wc_version'   => ACFW_WC_VERSION,
			'plugin_id'    => 'acfw',
			'plugin_name'  => 'Acquired.com for WooCommerce',
			'plugin_slug'  => 'acquired-com-for-woocommerce',
			'lang_dir'     => 'languages',
			'log_dir_path' => LoggingUtil::get_log_directory(),
			'site_name'    => get_bloginfo( 'name' ),
		],
		Plugin::class               => autowire(),
		ApiClient::class            => autowire(),
		AdminService::class         => autowire(),
		AssetsService::class        => function( $container ) {
			return new AssetsService(
				$container->get( SettingsService::class )->config['version'],
				$container->get( SettingsService::class )->config['dir_path'],
				$container->get( SettingsService::class )->config['dir_url']
			);
		},
		CustomerService::class      => autowire(),
		IncomingDataHandler::class  => function( $container ) {
			return new IncomingDataHandler( $container->get( LoggerService::class ), $container->get( SettingsService::class )->get_app_key(), $container->get( SettingsService::class )->get_signing_key() );
		},
		LoggerService::class        => function( $container ) {
			return new LoggerService( $container->get( SettingsService::class ), wc_get_logger() );
		},
		ObserverService::class      => function( $container ) {
			return new ObserverService(
				[
					new AdminObserver( $container->get( AdminService::class ) ),
					new CustomerObserver( $container->get( CustomerService::class ) ),
					new OrderObserver( $container->get( IncomingDataHandler::class ), $container->get( LoggerService::class ), $container->get( OrderService::class ), $container->get( SettingsService::class ) ),
					new PaymentMethodObserver( $container->get( IncomingDataHandler::class ), $container->get( LoggerService::class ), $container->get( PaymentMethodService::class ) ),
					new SettingsObserver( $container->get( ApiClient::class ), $container->get( SettingsService::class ) ),
				]
			);
		},
		OrderService::class         => autowire(),
		PaymentGateway::class       => autowire(),
		PaymentMethod::class        => autowire(),
		PaymentMethodService::class => autowire(),
		ScheduleService::class      => function( $container ) {
			return new ScheduleService( $container->get( SettingsService::class )->config['plugin_id'] );
		},
		SettingsService::class      => function( $container ) {
			return new SettingsService( $container->get( 'config' ) );
		},
		TokenService::class         => function( $container ) {
			return new TokenService( $container->get( SettingsService::class )->config['plugin_id'] );
		},
	]
);

return $builder->build();

// @codeCoverageIgnoreEnd
