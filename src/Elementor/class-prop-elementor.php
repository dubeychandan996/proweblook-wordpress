<?php
/**
 * Elementor Pro Forms Integration: Phone, WhatsApp & Email field validation.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_Elementor
 */
class PROP_Elementor {

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
	 * Register Elementor Pro Form field validation hooks.
	 *
	 * @return void
	 */
	public function setup() {
		add_action( 'elementor_pro/forms/validation/tel', array( $this, 'validate_tel' ), 10, 3 );
		if ( ! empty( $this->options['propp_email_check'] ) || ( ! empty( $this->options['propp_dnsbl_check'] ) && ! empty( $this->options['propp_block_dnsbl'] ) ) ) {
			add_action( 'elementor_pro/forms/validation/email', array( $this, 'validate_email' ), 10, 3 );
		}
	}

	/**
	 * Validate Elementor `tel` field.
	 *
	 * @param array  $field        Submitted field array (`id`, `value`, `raw_value`).
	 * @param object $record       Elementor Form_Record object.
	 * @param object $ajax_handler Elementor Ajax_Handler object.
	 * @return void
	 */
	public function validate_tel( $field, $record, $ajax_handler ) {
		$value = isset( $field['value'] ) ? trim( (string) $field['value'] ) : '';
		if ( '' === $value || ! is_object( $ajax_handler ) || ! method_exists( $ajax_handler, 'add_error' ) ) {
			return;
		}

		$this->validator->set_phone( $value );
		if ( ! $this->validator->validate() ) {
			$ajax_handler->add_error( $field['id'], $this->validator->get_error_message() );
			return;
		}

		if ( ! empty( $this->options['propp_whatsapp_check'] ) && ! $this->validator->validate_whatsapp( $value ) ) {
			$ajax_handler->add_error( $field['id'], $this->validator->get_error_message() );
		}
	}

	/**
	 * Validate Elementor `email` field.
	 *
	 * @param array  $field        Submitted field array.
	 * @param object $record       Elementor Form_Record object.
	 * @param object $ajax_handler Elementor Ajax_Handler object.
	 * @return void
	 */
	public function validate_email( $field, $record, $ajax_handler ) {
		$value = isset( $field['value'] ) ? trim( (string) $field['value'] ) : '';
		if ( '' === $value || ! is_object( $ajax_handler ) || ! method_exists( $ajax_handler, 'add_error' ) ) {
			return;
		}

		if ( ! empty( $this->options['propp_email_check'] ) || empty( $this->options['propp_dnsbl_check'] ) ) {
			if ( ! $this->validator->validate_email_address( $value ) ) {
				$ajax_handler->add_error( $field['id'], $this->validator->get_error_message() );
			}
		} elseif ( ! $this->validator->validate_domain_blacklist( $value, ! empty( $this->options['propp_block_dnsbl'] ), true ) ) {
			$ajax_handler->add_error( $field['id'], $this->validator->get_error_message() );
		}
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
