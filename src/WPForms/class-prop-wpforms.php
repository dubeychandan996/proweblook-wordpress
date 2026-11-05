<?php
/**
 * WPForms Integration: Phone, WhatsApp & Email field validation.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_WPForms
 */
class PROP_WPForms {

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
	 * Register WPForms post-process validation filter.
	 *
	 * @return void
	 */
	public function setup() {
		add_filter( 'wpforms_process_after_filter', array( $this, 'validate' ), 10, 3 );
	}

	/**
	 * Validate WPForms phone and email fields.
	 *
	 * @param array $fields    Sanitized entry fields.
	 * @param array $entry     Raw entry submission.
	 * @param array $form_data Form configuration.
	 * @return array
	 */
	public function validate( $fields, $entry, $form_data ) {
		$form_id = isset( $form_data['id'] ) ? $form_data['id'] : 0;

		foreach ( (array) $fields as $field_id => $field ) {
			$type  = isset( $field['type'] ) ? (string) $field['type'] : '';
			$value = isset( $field['value'] ) ? trim( (string) $field['value'] ) : '';

			if ( '' === $value ) {
				continue;
			}

			if ( 'phone' === $type ) {
				$phone = sanitize_text_field( $value );
				$this->validator->set_phone( $phone );

				if ( ! $this->validator->validate() ) {
					if ( function_exists( 'wpforms' ) && isset( wpforms()->process ) ) {
						wpforms()->process->errors[ $form_id ][ $field_id ] = esc_html( $this->validator->get_error_message() );
					}
					continue;
				}

				if ( ! empty( $this->options['propp_whatsapp_check'] ) && ! $this->validator->validate_whatsapp( $phone ) ) {
					if ( function_exists( 'wpforms' ) && isset( wpforms()->process ) ) {
						wpforms()->process->errors[ $form_id ][ $field_id ] = esc_html( $this->validator->get_error_message() );
					}
				}
			} elseif ( 'email' === $type && ! empty( $this->options['propp_email_check'] ) ) {
				if ( ! $this->validator->validate_email_address( $value ) ) {
					if ( function_exists( 'wpforms' ) && isset( wpforms()->process ) ) {
						wpforms()->process->errors[ $form_id ][ $field_id ] = esc_html( $this->validator->get_error_message() );
					}
				}
			} elseif ( 'email' === $type && ! empty( $this->options['propp_dnsbl_check'] ) && ! empty( $this->options['propp_block_dnsbl'] ) ) {
				if ( ! $this->validator->validate_domain_blacklist( $value, true, true ) ) {
					if ( function_exists( 'wpforms' ) && isset( wpforms()->process ) ) {
						wpforms()->process->errors[ $form_id ][ $field_id ] = esc_html( $this->validator->get_error_message() );
					}
				}
			}
		}

		return $fields;
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
