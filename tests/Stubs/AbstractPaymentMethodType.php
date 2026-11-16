<?php
/**
 * AbstractPaymentMethodType stub class.
 */

declare( strict_types = 1 );

namespace Automattic\WooCommerce\Blocks\Payments\Integrations;

// phpcs:disable Squiz.Commenting.FunctionComment.InvalidNoReturn

/**
 * AbstractPaymentMethodType class.
 */
abstract class AbstractPaymentMethodType {
	/**
	 * Payment method name defined by payment methods extending this class.
	 *
	 * @var string
	 */
	protected $name = '';

	/**
	 * Settings from the WP options table
	 *
	 * @var array
	 */
	protected $settings = [];

	/**
	 * Get a setting from the settings array if set.
	 *
	 * @param string $name Setting name.
	 * @param mixed $default Value that is returned if the setting does not exist.
	 * @return mixed
	 */
	protected function get_setting( $name, $default = '' ) {} // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound, Squiz.Commenting.FunctionComment.TypeHintMissing

	/**
	 * Returns the name of the payment method.
	 *
	 * @return string
	 */
	public function get_name() {}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {}

		/**
		 * Returns an array of script handles to enqueue for this payment method in
		 * the frontend context
		 *
		 * @return string[]
		 */
	public function get_payment_method_script_handles() {}

		/**
		 * Returns an array of script handles to enqueue for this payment method in
		 * the admin context
		 *
		 * @return string[]
		 */
	public function get_payment_method_script_handles_for_admin() {}

		/**
		 * Returns an array of supported features.
		 *
		 * @return string[]
		 */
	public function get_supported_features() {}

		/**
		 * An array of key, value pairs of data made available to payment methods
		 * client side.
		 *
		 * @return array
		 */
	public function get_payment_method_data() {}

		/**
		 * Returns an array of script handles to enqueue in the frontend context.
		 *
		 * Alias of get_payment_method_script_handles. Defined by IntegrationInterface.
		 *
		 * @return string[]
		 */
	public function get_script_handles() {}

		/**
		 * Returns an array of script handles to enqueue in the admin context.
		 *
		 * Alias of get_payment_method_script_handles_for_admin. Defined by IntegrationInterface.
		 *
		 * @return string[]
		 */
	public function get_editor_script_handles() {}

		/**
		 * An array of key, value pairs of data made available to the block on the client side.
		 *
		 * Alias of get_payment_method_data. Defined by IntegrationInterface.
		 *
		 * @return array
		 */
	public function get_script_data() {}
}
