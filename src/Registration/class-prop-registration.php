<?php
/**
 * WordPress & WooCommerce User Registration Email Deliverability Guard.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_Registration
 */
class PROP_Registration {

	/**
	 * Validator engine.
	 *
	 * @var PROP_Phone_Validator|null
	 */
	protected $validator = null;

	/**
	 * Register WordPress core and WooCommerce account registration hooks.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'registration_errors', array( $this, 'validate_wp_registration' ), 10, 3 );
		add_filter( 'woocommerce_registration_errors', array( $this, 'validate_wc_registration' ), 10, 3 );
	}

	/**
	 * Validate user email on WordPress core registration (`wp-login.php?action=register`).
	 *
	 * @param WP_Error $errors               Registration errors.
	 * @param string   $sanitized_user_login Sanitized username.
	 * @param string   $user_email           Submitted user email.
	 * @return WP_Error
	 */
	public function validate_wp_registration( $errors, $sanitized_user_login, $user_email ) {
		if ( '' !== trim( (string) $user_email ) && ! $this->validator->validate_email_address( $user_email ) ) {
			$errors->add( 'proweblook_invalid_email', $this->validator->get_error_message() );
		}
		return $errors;
	}

	/**
	 * Validate customer email on WooCommerce My Account registration.
	 *
	 * @param WP_Error $errors   Registration errors.
	 * @param string   $username Username.
	 * @param string   $email    Customer email.
	 * @return WP_Error
	 */
	public function validate_wc_registration( $errors, $username, $email ) {
		if ( '' !== trim( (string) $email ) && ! $this->validator->validate_email_address( $email ) ) {
			$errors->add( 'proweblook_invalid_email', $this->validator->get_error_message() );
		}
		return $errors;
	}

	/**
	 * Set validator engine.
	 *
	 * @param PROP_Phone_Validator $validator Validator engine.
	 * @return PROP_Phone_Validator
	 */
	public function set_validator( $validator ) {
		$this->validator = $validator;
		return $this->validator;
	}
}
