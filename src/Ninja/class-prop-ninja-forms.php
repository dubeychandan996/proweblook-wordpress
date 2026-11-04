<?php
/**
 * Ninja Forms Integration: Phone, WhatsApp & Email field validation.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_NinjaForms
 */
class PROP_NinjaForms {

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
	 * Register Ninja Forms submission validation filter.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'ninja_forms_submit_data', array( $this, 'validate' ), 10, 1 );
	}

	/**
	 * Validate Ninja Forms phone and email fields.
	 *
	 * @param array $form_data Ninja Forms submission payload.
	 * @return array
	 */
	public function validate( $form_data ) {
		if ( empty( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) {
			return $form_data;
		}

		foreach ( $form_data['fields'] as $field ) {
			$value    = isset( $field['value'] ) ? trim( (string) $field['value'] ) : '';
			$key      = isset( $field['key'] ) ? (string) $field['key'] : '';
			$type     = isset( $field['type'] ) ? (string) $field['type'] : '';
			$field_id = isset( $field['id'] ) ? $field['id'] : null;

			if ( '' === $value || null === $field_id ) {
				continue;
			}

			$is_phone = ( 'phone' === $type ) || ( false !== stripos( $key, 'phone' ) );
			$is_email = ( 'email' === $type ) || ( ! $is_phone && false !== stripos( $key, 'email' ) );

			if ( $is_phone ) {
				$this->validator->set_phone( $value );
				if ( ! $this->validator->validate() ) {
					$form_data['errors']['fields'][ $field_id ] = $this->validator->get_error_message();
					continue;
				}
				if ( ! empty( $this->options['propp_whatsapp_check'] ) && ! $this->validator->validate_whatsapp( $value ) ) {
					$form_data['errors']['fields'][ $field_id ] = $this->validator->get_error_message();
				}
			} elseif ( $is_email && ! empty( $this->options['propp_email_check'] ) ) {
				if ( ! $this->validator->validate_email_address( $value ) ) {
					$form_data['errors']['fields'][ $field_id ] = $this->validator->get_error_message();
				}
			}
		}

		return $form_data;
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
