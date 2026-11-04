<?php
/**
 * Gravity Forms Integration: Phone, WhatsApp & Email field validation.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_GravityForms
 */
class PROP_GravityForms {

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
	 * Register Gravity Forms validation hook.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'gform_field_validation', array( $this, 'validate' ), 10, 4 );
	}

	/**
	 * Validate Gravity Forms `phone` and `email` fields.
	 *
	 * @param array        $result Validation result (`is_valid`, `message`).
	 * @param string|array $value  Submitted field value.
	 * @param array        $form   Gravity Forms form array.
	 * @param object       $field  GF_Field object.
	 * @return array
	 */
	public function validate( $result, $value, $form, $field ) {
		if ( empty( $result['is_valid'] ) || ! is_object( $field ) ) {
			return $result;
		}

		$field_type = isset( $field->type ) ? (string) $field->type : '';

		if ( 'phone' === $field_type && is_string( $value ) && '' !== trim( $value ) ) {
			$this->validator->set_phone( $value );
			if ( ! $this->validator->validate() ) {
				$result['is_valid'] = false;
				$result['message']  = $this->validator->get_error_message();
				return $result;
			}

			if ( ! empty( $this->options['propp_whatsapp_check'] ) && ! $this->validator->validate_whatsapp( $value ) ) {
				$result['is_valid'] = false;
				$result['message']  = $this->validator->get_error_message();
			}
		} elseif ( 'email' === $field_type && ! empty( $this->options['propp_email_check'] ) ) {
			$email = is_array( $value ) ? ( isset( $value[0] ) ? (string) $value[0] : '' ) : (string) $value;
			if ( '' !== trim( $email ) && ! $this->validator->validate_email_address( $email ) ) {
				$result['is_valid'] = false;
				$result['message']  = $this->validator->get_error_message();
			}
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
