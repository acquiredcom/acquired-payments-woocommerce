<?php
/**
 * SettingsService.
 */

declare( strict_types = 1 );

namespace AcquiredComForWooCommerce\Services;

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * SettingsService class.
 */
class SettingsService {
	/**
	 * Option ID used to store options from the admin.
	 *
	 * @var string
	 */
	private string $option_id;

	/**
	 * Options from the database.
	 *
	 * @var array
	 */
	private array $options = [];

	/**
	 * Acquired.com domain.
	 *
	 * @var string
	 */
	private string $domain = 'acquired.com';

	/**
	 * API credentials validation option key.
	 *
	 * @var string
	 */
	private string $api_credentials_validation_option = '';

	/**
	 * Constructor.
	 *
	 * @param array{
	 *     root_file: string,
	 *     dir_path: string,
	 *     dir_url: string,
	 *     basename: string,
	 *     version: string,
	 *     php_version: string,
	 *     wc_version: string,
	 *     plugin_id: string,
	 *     plugin_name: string,
	 *     plugin_slug: string,
	 *     lang_dir: string,
	 *     log_dir_path: string,
	 *     site_name: string
	 * } $config
	 */
	public function __construct( public array $config ) {
		$this->api_credentials_validation_option = $this->config['plugin_id'] . '_api_credentials_valid';
		$this->option_id                         = 'woocommerce_' . $this->config['plugin_id'] . '_settings';
	}

	/**
	 * Set API credentials validation status.
	 *
	 * @param bool $is_valid
	 * @return void
	 */
	public function set_api_credentials_validation_status( bool $is_valid ) : void {
		update_option( $this->api_credentials_validation_option, $is_valid );
	}

	/**
	 * Check if API credentials are valid.
	 *
	 * @return bool
	 */
	public function are_api_credentials_valid() : bool {
		return (bool) get_option( $this->api_credentials_validation_option, false );
	}

	/**
	 * Load options from the database.
	 *
	 * @return void
	 */
	private function load_options() : void {
		$this->options = get_option( $this->option_id, [] );
	}

	/**
	 * Reload options from the database when updating them.
	 *
	 * @return void
	 */
	public function reload_options() : void {
		$this->load_options();
	}

	/**
	 * Get options from the database.
	 *
	 * @return array
	 */
	public function get_options() : array {
		if ( ! $this->options ) {
			$this->load_options();
		}

		return $this->options;
	}

	/**
	 * Get option.
	 *
	 * @param string $option_key
	 * @param mixed $default_value
	 * @return mixed
	 */
	public function get_option( string $option_key, mixed $default_value = false ) : mixed {
		$options = $this->get_options();

		return ! empty( $options[ $option_key ] ) ? $options[ $option_key ] : $default_value;
	}

	/**
	 * Check if option is enabled.
	 *
	 * @param string $option_key
	 * @return bool
	 */
	public function is_enabled( string $option_key ) : bool {
		return 'yes' === $this->get_option( $option_key );
	}

	/**
	 * Check the environment.
	 *
	 * @param string $environment
	 * @return bool
	 */
	private function is_environment( string $environment ) : bool {
		return $this->get_option( 'environment' ) === $environment;
	}

	/**
	 * Check if it's a staging environment.
	 *
	 * @return bool
	 */
	public function is_environment_staging() : bool {
		return $this->is_environment( 'staging' );
	}

	/**
	 * Check if it's a production environment.
	 *
	 * @return bool
	 */
	public function is_environment_production() : bool {
		return $this->is_environment( 'production' );
	}

	/**
	 * Get Acquired.com URL.
	 *
	 * @param string $subdomain
	 * @return string
	 */
	private function get_acquired_url( string $subdomain ) : string {
		$domain = sprintf( '%s.%s/%s', $subdomain, $this->domain, 'v1' );

		if ( ! $this->is_environment_production() ) {
			$domain = sprintf( '%s-%s', 'test', $domain );
		}

		return trailingslashit( 'https://' . $domain );
	}

	/**
	 * Get API URL.
	 *
	 * @return string
	 */
	public function get_api_url() : string {
		return $this->get_acquired_url( 'api' );
	}

	/**
	 * Get Acquired.com hub URL.
	 *
	 * @param string $environment
	 * @return string
	 */
	private function get_hub_url( string $environment ) : string {
		return trailingslashit( 'https://' . sprintf( '%s.%s', 'staging' === $environment ? 'qahub' : 'hub', $this->domain ) );
	}

	/**
	 * Get pay URL.
	 *
	 * @return string
	 */
	public function get_pay_url() : string {
		return $this->get_acquired_url( 'pay' );
	}

	/**
	 * Get company ID for specific environment.
	 *
	 * @param string $environment
	 * @return string
	 */
	public function get_company_id() : string {
		return $this->get_option( 'company_id_' . ( $this->is_environment_production() ? 'production' : 'staging' ), '' );
	}

	/**
	 * Get API credentials for specific environment.
	 *
	 * @param string $environment
	 * @return array{
	 *     app_id: string,
	 *     app_key: string
	 * }|array<empty>
	 */
	public function get_api_credentials_for_environment( string $environment ) : array {
		$credentials = [
			'app_id'  => $this->get_option( 'app_id_' . $environment ),
			'app_key' => $this->get_option( 'app_key_' . $environment ),
		];

		if ( empty( $credentials['app_id'] ) || empty( $credentials['app_key'] ) ) {
			return [];
		}

		return $credentials;
	}

	/**
	 * Get API credentials.
	 *
	 * @return array
	 */
	public function get_api_credentials() : array {
		return $this->get_api_credentials_for_environment( $this->is_environment_production() ? 'production' : 'staging' );
	}

	/**
	 * Get API key.
	 *
	 * @return string
	 */
	public function get_app_key() : string {
		$credentials = $this->get_api_credentials();

		return $credentials['app_key'] ?? '';
	}

	/**
	 * Get signing key for specific environment.
	 *
	 * @param string $environment
	 * @return string
	 */
	public function get_signing_key_for_environment( string $environment ) : string {
		return $this->get_option( 'signing_key_' . $environment, '' );
	}

	/**
	 * Get signing key for current environment.
	 *
	 * @return string
	 */
	public function get_signing_key() : string {
		return $this->get_signing_key_for_environment( $this->is_environment_production() ? 'production' : 'staging' );
	}

	/**
	 * Get payment reference.
	 *
	 * @return string
	 */
	public function get_payment_reference() : string {
		$payment_reference = $this->get_option( 'payment_reference', $this->config['site_name'] );

		return substr( preg_replace( '/[^\w \-]/', '', $payment_reference ), 0, 18 );
	}

	/**
	 * Get WooCommerce API endpoint.
	 *
	 * @param string $endpoint
	 * @return string
	 */
	public function get_wc_api_endpoint( string $endpoint ) : string {
		return $this->config['plugin_slug'] . '-' . $endpoint;
	}

	/**
	 * Get WooCommerce API URL.
	 *
	 * @param string $endpoint
	 * @return string
	 */
	public function get_wc_api_url( string $endpoint ) : string {
		return WC()->api_request_url( $this->get_wc_api_endpoint( $endpoint ) );
	}

	/**
	 * Get shop currency.
	 *
	 * @return string
	 */
	public function get_shop_currency() : string {
		return get_woocommerce_currency();
	}

	/**
	 * Get payment link expiration time in seconds.
	 *
	 * @return int
	 */
	public function get_payment_link_expiration_time() : int {
		return 300;
	}

	/**
	 * Get payment link max expiration time in seconds.
	 *
	 * @return int
	 */
	public function get_payment_link_max_expiration_time() : int {
		return 2678400;
	}

	/**
	 * Get admin settings URL.
	 *
	 * @return string
	 */
	public function get_admin_settings_url() : string {
		return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $this->config['plugin_id'] );
	}

	/**
	 * Get WooCommerce hold stock time in seconds.
	 *
	 * @return int
	 */
	public function get_wc_hold_stock_time() : int {
		$duration     = (int) get_option( 'woocommerce_hold_stock_minutes' );
		$manage_stock = 'yes' === get_option( 'woocommerce_manage_stock' );

		if ( $manage_stock && $duration > 1 ) {
			return ( $duration * 60 ); // Convert minutes to seconds.
		} else {
			return 0;
		}
	}

	/**
	 * Get Acquired.com hub link.
	 *
	 * @return string
	 */
	private function get_hub_link() : string {
		return '<a class="acfw-env-link" href=" ' . esc_url( $this->get_hub_url( 'production' ) ) . '" data-env-href-production="' . esc_url( $this->get_hub_url( 'production' ) ) . '" data-env-href-staging="' . esc_url( $this->get_hub_url( 'staging' ) ) . '" target="_blank">Acquired.com</a>';
	}

	/**
	 * Get fields.
	 *
	 * @return array
	 */
	public function get_fields() : array {
		return [
			'enabled'                 => [
				'title'   => __( 'Enable Acquired.com payment gateway', 'acquired-com-for-woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable/Disable', 'acquired-com-for-woocommerce' ),
				'default' => 'no',
			],
			'title'                   => [
				'title'       => __( 'Title', 'acquired-com-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during the checkout.', 'acquired-com-for-woocommerce' ),
				'default'     => __( 'Cards, Apple Pay, Google Pay', 'acquired-com-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'description'             => [
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'acquired-com-for-woocommerce' ),
				'default'     => __( 'Checkout with Apple Pay, Google Pay, Card and Pay by Bank.', 'acquired-com-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'environment'             => [
				'title'       => __( 'Environment', 'acquired-com-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select the environment you want to use. Staging is used to test transactions. Production is the live environment.', 'acquired-com-for-woocommerce' ),
				'default'     => 'staging',
				'desc_tip'    => true,
				'options'     => [
					'staging'    => __( 'Staging', 'acquired-com-for-woocommerce' ),
					'production' => __( 'Production', 'acquired-com-for-woocommerce' ),
				],
			],
			'company_id_production'   => [
				'title'       => __( 'Live Company-Id', 'acquired-com-for-woocommerce' ),
				'type'        => 'text',
				// translators: %s is the Acquired hub link.
				'description' => sprintf( __( 'You can find your Company-Id in your %s account. Login to the Acquired Hub -> Go to SETTINGS -> Go to REFERENCES IDS -> Retrieve your Company-Id.', 'acquired-com-for-woocommerce' ), '<a href=" ' . esc_url( $this->get_hub_url( 'production' ) ) . '" target="_blank">Acquired.com</a>' ),
				'desc_tip'    => __( 'Enter your Acquired.com Company-Id.', 'acquired-com-for-woocommerce' ),
			],
			'company_id_staging'      => [
				'title'       => __( 'Staging Company-Id', 'acquired-com-for-woocommerce' ),
				'type'        => 'text',
				// translators: %s is the Acquired hub link.
				'description' => sprintf( __( 'You can find your Company-Id in your %s account. Login to the Acquired Hub -> Go to SETTINGS -> Go to REFERENCES IDS -> Retrieve your Company-Id.', 'acquired-com-for-woocommerce' ), '<a href=" ' . esc_url( $this->get_hub_url( 'staging' ) ) . '" target="_blank">Acquired.com</a>' ),
				'desc_tip'    => __( 'Enter your Acquired.com staging Company-Id.', 'acquired-com-for-woocommerce' ),
			],
			'api_credentials'         => [
				'title'       => __( 'Acquired.com API settings', 'acquired-com-for-woocommerce' ),
				// translators: %s is the Acquired hub link.
				'description' => sprintf( __( 'You can find your API credentials in your %s account. Login to the Acquired Hub -> Go to SETTINGS -> Go to API ACCESS -> Retrieve your credentials.', 'acquired-com-for-woocommerce' ), $this->get_hub_link() ),
				'type'        => 'title',
			],
			'app_id_production'       => [
				'title'       => __( 'Live App ID', 'acquired-com-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your Acquired.com App ID.', 'acquired-com-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'app_key_production'      => [
				'title'       => __( 'Live App Key', 'acquired-com-for-woocommerce' ),
				'type'        => 'password',
				'description' => __( 'Enter your Acquired.com App Key.', 'acquired-com-for-woocommerce' ),
				'desc_tip'    => true,
			],
			'app_id_staging'          => [
				'title'       => __( 'Staging App ID', 'acquired-com-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Enter your Acquired.com staging App ID.', 'acquired-com-for-woocommerce' ),
				'desc_tip'    => true,
			],
		'app_key_staging'         => [
			'title'       => __( 'Staging App Key', 'acquired-com-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Enter your Acquired.com staging App Key.', 'acquired-com-for-woocommerce' ),
			'desc_tip'    => true,
		],
		'webhook_signing_keys'    => [
			'title'       => __( 'Webhook Signing Keys', 'acquired-com-for-woocommerce' ),
			'description' => __( 'Optional signing keys for webhook and redirect hash validation. If not provided, the App Key will be used instead.', 'acquired-com-for-woocommerce' ),
			'type'        => 'title',
		],
		'signing_key_production'  => [
			'title'       => __( 'Live Signing Key', 'acquired-com-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Enter your Acquired.com production signing key (format: sk_{32-bit hex}).', 'acquired-com-for-woocommerce' ),
			'desc_tip'    => true,
		],
		'signing_key_staging'     => [
			'title'       => __( 'Staging Signing Key', 'acquired-com-for-woocommerce' ),
			'type'        => 'password',
			'description' => __( 'Enter your Acquired.com staging signing key (format: sk_{32-bit hex}).', 'acquired-com-for-woocommerce' ),
			'desc_tip'    => true,
		],
		'additional'              => [
			'title' => __( 'Additional settings', 'acquired-com-for-woocommerce' ),
			'type'  => 'title',
		],
			'transaction_type'        => [
				'title'       => __( 'Transaction type', 'acquired-com-for-woocommerce' ),
				'type'        => 'select',
				'description' => sprintf(
					'%s<br>%s',
					__( 'Capture - Charges the customer immediately. Typically used to complete orders and collect payment right away.', 'acquired-com-for-woocommerce' ),
					__( 'Authorise - Places a hold on the customer\'s funds without charging them immediately. Commonly used by merchants who fulfil orders at a later time.', 'acquired-com-for-woocommerce' )
				),
				'default'     => 'capture',
				'desc_tip'    => __( 'Select the transaction type you want to use.', 'acquired-com-for-woocommerce' ),
				'options'     => [
					'capture'       => __( 'Capture', 'acquired-com-for-woocommerce' ),
					'authorisation' => __( 'Authorise', 'acquired-com-for-woocommerce' ),
				],
			],
			'payment_reference'       => [
				'title'             => __( 'Payment reference', 'acquired-com-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Payment reference visible on bank account statement. We recommend to input your Business Name.', 'acquired-com-for-woocommerce' ),
				'default'           => $this->get_payment_reference(),
				'desc_tip'          => true,
				'custom_attributes' => [
					'pattern'   => '[\w \-]{1,18}',
					'maxlength' => '18',
					'title'     => __( 'Only letters, numbers, spaces and hyphens allowed (max 18 characters)', 'acquired-com-for-woocommerce' ),
				],
			],
			'debug_log'               => [
				'title'       => __( 'Debug log', 'acquired-com-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf(
					'%s<br><small>%s</small>',
					// translators: %s is the path to log file.
					sprintf( __( 'Log events, stored in %s.', 'acquired-com-for-woocommerce' ), '<code>' . $this->config['log_dir_path'] . '</code>' ),
					__( 'Note: this may log personal information. We recommend using this for debugging purposes only and deleting the logs when finished.', 'acquired-com-for-woocommerce' ),
				),
				'label'       => __( 'Enable/Disable', 'acquired-com-for-woocommerce' ),
				'default'     => 'no',
			],
			'tokenization'            => [
				'title'       => __( 'Save payment methods', 'acquired-com-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf(
					'%s<br>%s<br>%s',
					// translators: %s is the Acquired hub link.
					sprintf( __( 'For this feature to work you also have to enable this in your %s account.', 'acquired-com-for-woocommerce' ), $this->get_hub_link() ),
					__( 'Login to the Acquired Hub -> Go to SETTINGS -> Click on HOSTED CHECKOUT -> Click on "SETTINGS" button for Cards payment method.', 'acquired-com-for-woocommerce' ),
					__( 'Turn on "SAVE CARDS" option and click the "SAVE" button.', 'acquired-com-for-woocommerce' )
				),
				'label'       => __( 'Enable/Disable', 'acquired-com-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => __( 'Allow customers to securely save their payment details for future checkout.', 'acquired-com-for-woocommerce' ),
			],
			'3d_secure'               => [
				'title'       => __( '3D-Secure', 'acquired-com-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Enable 3D-Secure authentication.', 'acquired-com-for-woocommerce' ),
				'label'       => __( 'Enable/Disable', 'acquired-com-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			],
			'challenge_preferences'   => [
				'title'       => __( 'Challenge Preferences', 'acquired-com-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select your challenge preferences.', 'acquired-com-for-woocommerce' ),
				'default'     => 'no_preference',
				'desc_tip'    => true,
				'options'     => [
					'challenge_mandated'     => __( 'Challenge mandated', 'acquired-com-for-woocommerce' ),
					'challenge_preferred'    => __( 'Challenge preferred', 'acquired-com-for-woocommerce' ),
					'no_challenge_requested' => __( 'No challenge requested', 'acquired-com-for-woocommerce' ),
					'no_preference'          => __( 'No preference', 'acquired-com-for-woocommerce' ),
				],
			],
			'contact_url'             => [
				'title'       => __( 'Contact URL', 'acquired-com-for-woocommerce' ),
				'type'        => 'url',
				'description' => __( 'Enter your contact URL.', 'acquired-com-for-woocommerce' ),
				'default'     => get_site_url(),
				'desc_tip'    => true,
			],
			'submit_type'             => [
				'title'       => __( 'Submit Type', 'acquired-com-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select your button text. The value you select will be presented to the customer when checking out.', 'acquired-com-for-woocommerce' ),
				'default'     => 'pay',
				'desc_tip'    => true,
				'options'     => [
					'pay'       => __( 'Pay', 'acquired-com-for-woocommerce' ),
					'buy'       => __( 'Buy', 'acquired-com-for-woocommerce' ),
					'checkout'  => __( 'Checkout', 'acquired-com-for-woocommerce' ),
					'donate'    => __( 'Donate', 'acquired-com-for-woocommerce' ),
					'register'  => __( 'Register', 'acquired-com-for-woocommerce' ),
					'subscribe' => __( 'Subscribe', 'acquired-com-for-woocommerce' ),
				],
			],
			'update_card_webhook_url' => [
				'title'             => __( 'Update card webhook', 'acquired-com-for-woocommerce' ),
				'type'              => 'url',
				'description'       => sprintf(
					'%s<br>%s<br>%s',
					// translators: %s is the Acquired hub link.
					sprintf( __( 'Webhook URL for card update notifications for saved payment methods. Copy this value to your %s account.', 'acquired-com-for-woocommerce' ), $this->get_hub_link() ),
					__( 'Login to the Acquired Hub -> Go to SETTINGS -> Go to WEBHOOKS -> Click on "+ADD ENDPOINT" button and add the value to Endpoint URL field.', 'acquired-com-for-woocommerce' ),
					__( 'Check "card_update" checkbox and click on the "+ADD ENDPOINT" button.', 'acquired-com-for-woocommerce' )
				),
				'default'           => $this->get_wc_api_url( 'webhook' ),
				'custom_attributes' => [ 'readonly' => true ],
				'css'               => 'width:100%;',
			],
			'order'                   => [
				'title' => __( 'Order settings', 'acquired-com-for-woocommerce' ),
				'type'  => 'title',
			],
			'cancel_refunded'         => [
				'title'       => __( 'Cancel refunded orders', 'acquired-com-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Automatically switch fully refunded order status to canceled.', 'acquired-com-for-woocommerce' ),
				'label'       => __( 'Enable/Disable', 'acquired-com-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => false,
			],
			'woo_wallet_refund'       => [
				'title'       => __( 'Wallet for WooCommerce failed orders.', 'acquired-com-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => __( 'Refund wallet credit for failed orders  paid partially with Wallet for WooCommerce.', 'acquired-com-for-woocommerce' ),
				'label'       => __( 'Enable/Disable', 'acquired-com-for-woocommerce' ),
				'default'     => 'no',
				'desc_tip'    => false,
			],
		];
	}

	/**
	 * Get field.
	 *
	 * @param string $field_key
	 * @return array
	 */
	public function get_field( string $field_key ) : array {
		$fields = $this->get_fields();

		return $fields[ $field_key ] ?? [];
	}
}
