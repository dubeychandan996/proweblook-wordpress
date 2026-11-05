<?php
/**
 * Contact Form 7 Integration: Phone, WhatsApp & Email validation.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_ContactForm7
 */
class PROP_ContactForm7 {

	/**
	 * Validator engine.
	 *
	 * @var PROP_Phone_Validator|null
	 */
	protected $validator = null;

	/**
	 * Plugin options.
	 *
	 * @var array
	 */
	protected $options = array();

	/**
	 * Set options.
	 *
	 * @param array $options Plugin settings.
	 * @return void
	 */
	public function set_options( $options ) {
		$this->options = (array) $options;
	}

	/**
	 * Register Contact Form 7 validation filters.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'wpcf7_validate_tel', array( $this, 'validate_tel_tag' ), 20, 2 );
		add_filter( 'wpcf7_validate_tel*', array( $this, 'validate_tel_tag' ), 20, 2 );

		if ( ! empty( $this->options['propp_email_check'] ) || ( ! empty( $this->options['propp_dnsbl_check'] ) && ! empty( $this->options['propp_block_dnsbl'] ) ) ) {
			add_filter( 'wpcf7_validate_email', array( $this, 'validate_email_tag' ), 20, 2 );
			add_filter( 'wpcf7_validate_email*', array( $this, 'validate_email_tag' ), 20, 2 );
		}
	}

	/**
	 * Legacy boolean filter method preserved for backward compatibility.
	 *
	 * @param bool   $is_valid Initial CF7 validity.
	 * @param string $phone    Phone string.
	 * @return bool
	 */
	public function validate( $is_valid, $phone ) {
		if ( ! $is_valid ) {
			return false;
		}
		$this->validator->set_phone( $phone );
		if ( ! $this->validator->validate() ) {
			return false;
		}
		if ( ! empty( $this->options['propp_whatsapp_check'] ) && ! $this->validator->validate_whatsapp( $phone ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Validate CF7 `tel` and `tel*` form tags with rich inline error messages.
	 *
	 * @param WPCF7_Validation|object $result CF7 validation result container.
	 * @param WPCF7_FormTag|object    $tag    CF7 form tag.
	 * @return object
	 */
	public function validate_tel_tag( $result, $tag ) {
		$name = is_object( $tag ) && isset( $tag->name ) ? $tag->name : '';
		if ( '' === $name || ! is_object( $result ) || ! method_exists( $result, 'invalidate' ) ) {
			return $result;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 handles submission verification.
		$value = isset( $_POST[ $name ] ) ? sanitize_text_field( wp_unslash( $_POST[ $name ] ) ) : '';
		if ( '' === $value ) {
			return $result;
		}

		$this->validator->set_phone( $value );
		if ( ! $this->validator->validate() ) {
			$result->invalidate( $tag, $this->validator->get_error_message() );
			return $result;
		}

		if ( ! empty( $this->options['propp_whatsapp_check'] ) && ! $this->validator->validate_whatsapp( $value ) ) {
			$result->invalidate( $tag, $this->validator->get_error_message() );
		}

		return $result;
	}

	/**
	 * Validate CF7 `email` and `email*` form tags.
	 *
	 * @param WPCF7_Validation|object $result CF7 validation result container.
	 * @param WPCF7_FormTag|object    $tag    CF7 form tag.
	 * @return object
	 */
	public function validate_email_tag( $result, $tag ) {
		$name = is_object( $tag ) && isset( $tag->name ) ? $tag->name : '';
		if ( '' === $name || ! is_object( $result ) || ! method_exists( $result, 'invalidate' ) ) {
			return $result;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Contact Form 7 handles submission verification.
		$value = isset( $_POST[ $name ] ) ? sanitize_email( wp_unslash( $_POST[ $name ] ) ) : '';
		if ( '' === $value ) {
			return $result;
		}

		if ( ! empty( $this->options['propp_email_check'] ) || empty( $this->options['propp_dnsbl_check'] ) ) {
			if ( ! $this->validator->validate_email_address( $value ) ) {
				$result->invalidate( $tag, $this->validator->get_error_message() );
			}
		} elseif ( ! $this->validator->validate_domain_blacklist( $value, ! empty( $this->options['propp_block_dnsbl'] ), true ) ) {
			$result->invalidate( $tag, $this->validator->get_error_message() );
		}

		return $result;
	}

	/**
	 * Set validator engine.
	 *
	 * @param PROP_Phone_Validator $validator Validator engine.
	 * @return PROP_Phone_Validator
	 */
	public function set_validator( $validator ) {
		$this->validator = $validator;
		return $this->get_validator();
	}

	/**
	 * Get validator engine.
	 *
	 * @return PROP_Phone_Validator|null
	 */
	public function get_validator() {
		return $this->validator;
	}
}
