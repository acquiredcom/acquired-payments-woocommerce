<?php
/**
 * AssetsService.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Services;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * AssetsService class.
 */
class AssetsService {
	/**
	 * Assets directory absolute server path.
	 *
	 * @var string
	 */
	private $assets_directory;

	/**
	 * Assets directory URI.
	 *
	 * @var string
	 */
	private $assets_directory_uri;

	/**
	 * Path of the dist directory relative to plugin root directory.
	 *
	 * @var string
	 */
	private $assets_dist_dir_path = 'assets/dist';

	/**
	 * Constructor.
	 *
	 * @param string $version
	 * @param string $dir_path
	 * @param string $dir_url
	 */
	public function __construct( public string $version, string $dir_path, string $dir_url ) {
		$this->assets_directory     = trailingslashit( $dir_path ) . trailingslashit( $this->assets_dist_dir_path );
		$this->assets_directory_uri = trailingslashit( $dir_url ) . trailingslashit( $this->assets_dist_dir_path );
	}

	/**
	 * Get assets directory.
	 *
	 * @return string
	 */
	public function get_assets_directory(): string {
		return $this->assets_directory;
	}

	/**
	 * Get assets directory URI.
	 *
	 * @return string
	 */
	public function get_assets_directory_uri(): string {
		return $this->assets_directory_uri;
	}

	/**
	 * Get asset URI.
	 *
	 * @param string $asset_path
	 * @return string
	 */
	public function get_asset_uri( string $asset_path ): string {
		return $this->get_assets_directory_uri() . $asset_path;
	}

	/**
	 * Get asset server path.
	 *
	 * @param string $asset_path
	 * @return string
	 */
	public function get_asset_path( string $asset_path ): string {
		return trailingslashit( $this->get_assets_directory() ) . $asset_path;
	}

	/**
	 * Check if asset exists.
	 *
	 * @param string $asset_path
	 * @return bool
	 */
	public function asset_exists( string $asset_path ): bool {
		return file_exists( $this->get_asset_path( $asset_path ) );
	}
}
