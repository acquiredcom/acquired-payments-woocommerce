<?php
/**
 * WC_Payment_Gateway stub class.
 */

declare( strict_types = 1 );

/**
 * WC_Payment_Gateway class.
 */
class WC_Payment_Gateway {
	/**
	 * Gateway ID
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Gateway method title.
	 *
	 * @var string
	 */
	public $method_title;

	/**
	 * Gateway method description.
	 *
	 * @var string
	 */
	public $method_description;

	/**
	 * Gateway has fields.
	 *
	 * @var bool
	 */
	public $has_fields;

	/**
	 * Gateway supports.
	 *
	 * @var array
	 */
	public $supports;

	/**
	 * Gateway form fields.
	 *
	 * @var array
	 */
	public $form_fields;

	/**
	 * Gateway title
	 *
	 * @var string
	 */
	public $title;

	/**
	 * Gateway description.
	 *
	 * @var string
	 */
	public $description;

	/**
	 * Tokens.
	 *
	 * @var array
	 */
	protected $tokens = [];

	/**
	 * Init settings.
	 *
	 * @return void
	 */
	public function init_settings(): void {}

	/**
	 * Generate settings HTML.
	 *
	 * @return string
	 */
	public function generate_settings_html(): string {
		return '';
	}

	/**
	 * Check if gateway is available.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return true;
	}

	/**
	 * Get saved payment method option HTML.
	 *
	 * @param WC_Payment_Token $token Payment token.
	 * @return string
	 */
	public function get_saved_payment_method_option_html( $token ) { // phpcs:ignore Squiz.Commenting.FunctionComment.TypeHintMissing, Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return '<div>Test payment method HTML</div>';
	}

	/**
	 * Get new payment method option HTML.
	 *
	 * @return string
	 */
	public function get_new_payment_method_option_html() {
		return '<div>Test payment method HTML</div>';
	}

	/**
	 * Get tokens.
	 *
	 * @return array
	 */
	public function get_tokens(): array {
		return $this->tokens;
	}

	/**
	 * Output payment fields.
	 *
	 * @return void
	 */
	public function payment_fields() {
		echo 'Test payment fields output';
	}

	/**
	 * Output saved payment methods.
	 *
	 * @return void
	 */
	public function saved_payment_methods() {
		echo 'Test saved payment methods output';
	}

	/**
	 * Validate select field.
	 *
	 * @param string $key
	 * @param mixed $value
	 * @return bool
	 */
	public function validate_select_field( $key, $value ) { // phpcs:ignore Squiz.Commenting.FunctionComment.TypeHintMissing, Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		return true;
	}

	/**
	 * Validate text field.
	 *
	 * @param string $key
	 * @param string $value
	 * @return string
	 */
	public function validate_text_field( $key, $value ) {
		return $value;
	}
}
