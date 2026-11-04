<?php
/**
 * Unified Phone, WhatsApp & Email Validator Engine.
 * Preserves 100% backward compatibility with PROP_Phone_Validator v1.x.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_Phone_Validator
 */
class PROP_Phone_Validator {

	/**
	 * Whether the last checked target is valid.
	 *
	 * @var bool
	 */
	protected $is_valid = true;

	/**
	 * Phone number to validate.
	 *
	 * @var string
	 */
	protected $phone = '';

	/**
	 * Phone number status code.
	 *
	 * @var string
	 */
	protected $phone_status = '';

	/**
	 * Last validation error message suitable for displaying to end users.
	 *
	 * @var string
	 */
	protected $error_message = '';

	/**
	 * Whether VoIP numbers should be rejected.
	 *
	 * @var bool
	 */
	protected $block_voip = false;

	/**
	 * Whether disposable emails should be rejected.
	 *
	 * @var bool
	 */
	protected $block_disposable_email = true;

	/**
	 * API client instance.
	 *
	 * @var PROP_API|null
	 */
	protected $api = null;

	/**
	 * Last phone API response object.
	 *
	 * @var object|null
	 */
	protected $last_phone_result = null;

	/**
	 * Last WhatsApp API response object.
	 *
	 * @var object|null
	 */
	protected $last_whatsapp_result = null;

	/**
	 * Last Email API response object.
	 *
	 * @var object|null
	 */
	protected $last_email_result = null;

	/**
	 * Validate a phone number (preserves v1.x signature and PROP_phone_valid filter).
	 *
	 * @param string $country_code Optional 2-letter ISO country code hint.
	 * @return bool
	 */
	public function validate( $country_code = '' ) {
		$this->error_message     = '';
		$this->phone_status      = '';
		$this->last_phone_result = null;

		$api = $this->get_api();
		if ( ! $api ) {
			$this->set_is_valid( true );
			return true;
		}

		$api->set_phone( $this->get_phone() );
		$result   = $api->request( $country_code );
		$response = $api->get_response();

		if ( ! empty( $result->status ) ) {
			$this->set_phone_status( $result->status );
		}

		$this->last_phone_result = $response;
		$this->set_is_valid( true );

		// Fail-open on transient network failures so customer checkouts are never blocked by upstream downtime.
		if ( null === $result || null === $response ) {
			$this->set_is_valid( true );
			return (bool) apply_filters( 'PROP_phone_valid', $this->get_is_valid(), $response );
		}

		$valid_statuses = array(
			'VALID_CONFIRMED',
			'VALID_UNCONFIRMED',
			'DELAYED',
			'API_KEY_INVALID_OR_DEPLETED',
			'RATE_LIMIT_EXCEEDED',
		);

		if ( ! in_array( $this->get_phone_status(), $valid_statuses, true ) ) {
			$this->set_is_valid( false );
			$this->error_message = __( 'Please enter a valid, callable phone number.', 'proweblook-phone-validator' );
		}

		if ( 'API_KEY_INVALID_OR_DEPLETED' === $this->get_phone_status() ) {
			update_option( 'prop-api-key-invalid', 1 );
		}

		// Optional VoIP / virtual number blocking.
		if ( $this->get_is_valid() && $this->block_voip && ! empty( $response->linetype ) ) {
			$linetype = strtolower( trim( (string) $response->linetype ) );
			if ( 'voip' === $linetype ) {
				$this->set_is_valid( false );
				$this->set_phone_status( 'VOIP_BLOCKED' );
				$this->error_message = __( 'Virtual or VoIP phone numbers are not accepted. Please enter a mobile or landline number.', 'proweblook-phone-validator' );
			}
		}

		/**
		 * Filters whether the phone number is valid.
		 *
		 * @param bool        $is_valid Whether the number is valid.
		 * @param object|null $response Raw API response object.
		 */
		return (bool) apply_filters( 'PROP_phone_valid', $this->get_is_valid(), $response );
	}

	/**
	 * Silently check whether a phone number is registered on WhatsApp.
	 * Fails open if upstream network/credits fail so store checkouts never break unexpectedly.
	 *
	 * @param string $phone        Phone number to check.
	 * @param string $country_code Optional 2-letter ISO country code hint.
	 * @return bool
	 */
	public function validate_whatsapp( $phone, $country_code = '' ) {
		$this->error_message        = '';
		$this->last_whatsapp_result = null;

		$api = $this->get_api();
		if ( ! $api ) {
			return true;
		}

		$res                        = $api->check_whatsapp( $phone, $country_code );
		$this->last_whatsapp_result = $res;

		if ( ! empty( $res->network_error ) || empty( $res->ok ) ) {
			return (bool) apply_filters( 'prop_whatsapp_valid', true, $res );
		}

		$is_valid = ! empty( $res->on_whatsapp );
		if ( ! $is_valid ) {
			$this->error_message = __( 'Please enter an active WhatsApp phone number so we can confirm your order.', 'proweblook-phone-validator' );
		}

		/**
		 * Filters whether the WhatsApp check passed.
		 *
		 * @param bool   $is_valid Whether the phone number has an active WhatsApp account.
		 * @param object $res      Normalized WhatsApp response object.
		 */
		return (bool) apply_filters( 'prop_whatsapp_valid', $is_valid, $res );
	}

	/**
	 * Validate an email address for deliverability and disposable domain risk.
	 * Fails open on upstream network/credit errors.
	 *
	 * @param string    $email            Email address to check.
	 * @param bool|null $block_disposable Override disposable blocking setting.
	 * @return bool
	 */
	public function validate_email_address( $email, $block_disposable = null ) {
		$this->error_message     = '';
		$this->last_email_result = null;

		$cleaned = trim( (string) $email );
		if ( '' === $cleaned ) {
			return true;
		}

		if ( ! is_email( $cleaned ) ) {
			$this->error_message = __( 'Please enter a valid email address.', 'proweblook-phone-validator' );
			return false;
		}

		$api = $this->get_api();
		if ( ! $api ) {
			return true;
		}

		$res                     = $api->verify_email( $cleaned );
		$this->last_email_result = $res;

		if ( ! empty( $res->network_error ) || empty( $res->ok ) ) {
			return (bool) apply_filters( 'prop_email_valid', true, $res );
		}

		$reject_disposable = ( null !== $block_disposable ) ? (bool) $block_disposable : $this->block_disposable_email;
		$is_valid          = true;

		if ( false === $res->emailformat || 'invalid_format' === $res->verdict ) {
			$is_valid            = false;
			$this->error_message = __( 'Please enter a properly formatted email address.', 'proweblook-phone-validator' );
		} elseif ( $reject_disposable && ! empty( $res->is_disposable ) ) {
			$is_valid            = false;
			$this->error_message = __( 'Disposable or temporary email addresses are not accepted. Please use a permanent email address.', 'proweblook-phone-validator' );
		} elseif ( 'undeliverable' === $res->verdict || ( false === $res->emailexist && 'unknown' !== $res->verdict ) ) {
			$is_valid            = false;
			$this->error_message = __( 'This email address could not be verified as deliverable. Please check for typos.', 'proweblook-phone-validator' );
		}

		/**
		 * Filters whether the email address passed ProWebLook verification.
		 *
		 * @param bool   $is_valid Whether the email address is accepted.
		 * @param object $res      Normalized email verification response object.
		 */
		return (bool) apply_filters( 'prop_email_valid', $is_valid, $res );
	}

	/**
	 * Configure whether VoIP phone numbers should be blocked.
	 *
	 * @param bool $block True to reject VoIP numbers.
	 * @return void
	 */
	public function set_block_voip( $block ) {
		$this->block_voip = (bool) $block;
	}

	/**
	 * Check whether VoIP blocking is enabled.
	 *
	 * @return bool
	 */
	public function get_block_voip() {
		return $this->block_voip;
	}

	/**
	 * Configure whether disposable emails should be blocked.
	 *
	 * @param bool $block True to reject disposable emails.
	 * @return void
	 */
	public function set_block_disposable_email( $block ) {
		$this->block_disposable_email = (bool) $block;
	}

	/**
	 * Get the last user-facing validation error message.
	 *
	 * @param string $fallback Fallback error string if none set.
	 * @return string
	 */
	public function get_error_message( $fallback = '' ) {
		if ( ! empty( $this->error_message ) ) {
			return $this->error_message;
		}
		if ( ! empty( $fallback ) ) {
			return $fallback;
		}
		return __( 'Please enter a valid phone number.', 'proweblook-phone-validator' );
	}

	/**
	 * Get last phone enrichment response.
	 *
	 * @return object|null
	 */
	public function get_last_phone_result() {
		return $this->last_phone_result;
	}

	/**
	 * Get last WhatsApp enrichment response.
	 *
	 * @return object|null
	 */
	public function get_last_whatsapp_result() {
		return $this->last_whatsapp_result;
	}

	/**
	 * Get last Email enrichment response.
	 *
	 * @return object|null
	 */
	public function get_last_email_result() {
		return $this->last_email_result;
	}

	/**
	 * Get validity of last phone check.
	 *
	 * @return bool
	 */
	public function get_is_valid() {
		return $this->is_valid;
	}

	/**
	 * Set validity flag.
	 *
	 * @param bool $is_valid Validity state.
	 * @return bool
	 */
	protected function set_is_valid( $is_valid ) {
		$this->is_valid = (bool) $is_valid;
		return $this->get_is_valid();
	}

	/**
	 * Get phone number.
	 *
	 * @return string
	 */
	public function get_phone() {
		return $this->phone;
	}

	/**
	 * Set phone number.
	 *
	 * @param string $phone Phone number.
	 * @return string
	 */
	public function set_phone( $phone ) {
		$this->phone = (string) $phone;
		return $this->get_phone();
	}

	/**
	 * Set phone status code.
	 *
	 * @param string $status Status code.
	 * @return string
	 */
	public function set_phone_status( $status ) {
		$this->phone_status = (string) $status;
		return $this->get_phone_status();
	}

	/**
	 * Get phone status code.
	 *
	 * @return string
	 */
	public function get_phone_status() {
		return $this->phone_status;
	}

	/**
	 * Get API client.
	 *
	 * @return PROP_API|null
	 */
	public function get_api() {
		return $this->api;
	}

	/**
	 * Set API client.
	 *
	 * @param object $api API client object.
	 * @return object
	 */
	public function set_api( $api ) {
		$this->api = $api;
		return $this->get_api();
	}
}
