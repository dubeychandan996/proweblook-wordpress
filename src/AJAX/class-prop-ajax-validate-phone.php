<?php
/**
 * Processes frontend (.propp-phone) and admin live-test AJAX validation requests.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_Ajax_Validate_Phone
 */
class PROP_Ajax_Validate_Phone {

	/**
	 * Handler wrapper.
	 *
	 * @var PROP_Ajax_Handler|null
	 */
	protected $handler = null;

	/**
	 * Whether the frontend phone action is private.
	 *
	 * @var bool
	 */
	protected $private = true;

	/**
	 * Legacy action slug preserved for backward compatibility.
	 *
	 * @var string
	 */
	protected $action = 'bppy-verify-phone';

	/**
	 * Validator engine.
	 *
	 * @var PROP_Phone_Validator|null
	 */
	protected $validator = null;

	/**
	 * Handle legacy & frontend `.propp-phone` AJAX validation.
	 * Includes transient per-IP rate limiting when public (`nopriv`) mode is active.
	 *
	 * @return void
	 */
	public function callback() {
		$return = array(
			'is_valid' => null,
			'status'   => '',
			'message'  => '',
			'error'    => true,
		);

		$nonce = isset( $_POST['prop-nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['prop-nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'validate-phone' ) ) {
			wp_send_json( $return );
		}

		if ( ! current_user_can( 'manage_options' ) && $this->is_rate_limited() ) {
			wp_send_json(
				array(
					'is_valid' => true,
					'status'   => 'RATE_LIMIT_EXCEEDED',
					'message'  => __( 'Rate limit reached. Proceeding with form submission.', 'proweblook-phone-validator' ),
					'error'    => false,
				)
			);
		}

		$phone = isset( $_POST['number'] ) ? sanitize_text_field( wp_unslash( $_POST['number'] ) ) : '';
		if ( '' === $phone ) {
			wp_send_json( $return );
		}

		$this->validator->set_phone( $phone );
		$is_valid = $this->validator->validate();
		$raw      = $this->validator->get_last_phone_result();

		wp_send_json(
			array(
				'is_valid'         => $is_valid,
				'status'           => $this->validator->get_phone_status(),
				'message'          => $is_valid ? '' : $this->validator->get_error_message(),
				'carrier'          => isset( $raw->carrier ) ? sanitize_text_field( (string) $raw->carrier ) : '',
				'linetype'         => isset( $raw->linetype ) ? sanitize_text_field( (string) $raw->linetype ) : '',
				'formatted_number' => isset( $raw->formatted_number ) ? sanitize_text_field( (string) $raw->formatted_number ) : $phone,
				'countrycode'      => isset( $raw->countrycode ) ? sanitize_text_field( (string) $raw->countrycode ) : '',
				'error'            => false,
			)
		);
	}

	/**
	 * Handle WP Admin interactive Live Test Validator & Cache/Balance actions.
	 * Strictly restricted to administrators (`manage_options`).
	 *
	 * @return void
	 */
	public function admin_test_callback() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized request.', 'proweblook-phone-validator' ) ), 403 );
		}

		check_ajax_referer( 'prop_admin_action', 'nonce' );

		$test_type = isset( $_POST['test_type'] ) ? sanitize_key( wp_unslash( $_POST['test_type'] ) ) : '';
		$target    = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';
		$country   = isset( $_POST['country'] ) ? sanitize_text_field( wp_unslash( $_POST['country'] ) ) : '';
		$api       = $this->validator ? $this->validator->get_api() : null;

		if ( ! $api ) {
			wp_send_json_error( array( 'message' => __( 'API client not initialized.', 'proweblook-phone-validator' ) ) );
		}

		if ( 'clear_cache' === $test_type ) {
			$deleted  = $api->clear_cache();
			$balances = $api->get_balances( true );
			wp_send_json_success(
				array(
					'message'  => sprintf(
						/* translators: %d: number of cached entries cleared */
						__( 'Cleared %d cached validation entries and refreshed credit balances.', 'proweblook-phone-validator' ),
						(int) ( $deleted / 2 )
					),
					'balances' => $balances,
				)
			);
		}

		if ( 'refresh_balances' === $test_type ) {
			$balances = $api->get_balances( true );
			if ( empty( $balances['ok'] ) ) {
				wp_send_json_error( array( 'message' => $balances['error'] ) );
			}
			wp_send_json_success( array( 'balances' => $balances ) );
		}

		if ( '' === trim( (string) $api->get_apikey() ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please save your ProWebLook API Key above before running a live test.', 'proweblook-phone-validator' ),
				)
			);
		}

		if ( '' === $target ) {
			wp_send_json_error(
				array(
					'message' => __( 'Please enter a phone number or email address to test.', 'proweblook-phone-validator' ),
				)
			);
		}

		if ( 'phone' === $test_type ) {
			$this->validator->set_phone( $target );
			$is_valid = $this->validator->validate( $country );
			$res      = $this->validator->get_last_phone_result();
			$balances = $api->get_balances( true );

			wp_send_json_success(
				array(
					'type'     => 'phone',
					'is_valid' => $is_valid,
					'verdict'  => $this->validator->get_phone_status(),
					'summary'  => $is_valid
						? __( 'Valid callable phone number', 'proweblook-phone-validator' )
						: $this->validator->get_error_message(),
					'details'  => array(
						'Formatted' => isset( $res->formatted_number ) ? (string) $res->formatted_number : $target,
						'Line Type' => isset( $res->linetype ) ? (string) $res->linetype : '—',
						'Carrier'   => ! empty( $res->carrier ) ? (string) $res->carrier : '—',
						'Country'   => isset( $res->countryname ) ? (string) $res->countryname . ' (' . (string) $res->countrycode . ')' : ( isset( $res->countrycode ) ? (string) $res->countrycode : '—' ),
						'Location'  => ! empty( $res->location ) ? (string) $res->location : '—',
					),
					'balances' => $balances,
				)
			);
		}

		if ( 'whatsapp' === $test_type ) {
			$res      = $api->check_whatsapp( $target, $country );
			$balances = $api->get_balances( true );

			if ( ! empty( $res->network_error ) || empty( $res->ok ) ) {
				wp_send_json_error(
					array(
						'message' => isset( $res->error ) ? (string) $res->error : __( 'WhatsApp lookup failed.', 'proweblook-phone-validator' ),
					)
				);
			}

			$on_wa = ! empty( $res->on_whatsapp );
			wp_send_json_success(
				array(
					'type'     => 'whatsapp',
					'is_valid' => $on_wa,
					'verdict'  => $on_wa ? 'ON_WHATSAPP' : 'NOT_ON_WHATSAPP',
					'summary'  => $on_wa
						? ( ! empty( $res->is_business )
							? __( 'Active on WhatsApp (Business Account)', 'proweblook-phone-validator' )
							: __( 'Active on WhatsApp (Personal Account)', 'proweblook-phone-validator' ) )
						: __( 'Not registered on WhatsApp (would be blocked by COD Guard)', 'proweblook-phone-validator' ),
					'details'  => array(
						'Phone'        => isset( $res->formatted_number ) ? (string) $res->formatted_number : $target,
						'WhatsApp'     => $on_wa ? 'Active' : 'Not Registered',
						'Account Type' => ucfirst( (string) $res->account_type ),
						'Country'      => ! empty( $res->countrycode ) ? (string) $res->countrycode : '—',
					),
					'balances' => $balances,
				)
			);
		}

		if ( 'email' === $test_type ) {
			$is_valid = $this->validator->validate_email_address( $target );
			$res      = $this->validator->get_last_email_result();
			$balances = $api->get_balances( true );

			if ( null === $res || ! empty( $res->network_error ) ) {
				wp_send_json_error(
					array(
						'message' => isset( $res->error ) ? (string) $res->error : $this->validator->get_error_message( __( 'Email lookup failed.', 'proweblook-phone-validator' ) ),
					)
				);
			}

			wp_send_json_success(
				array(
					'type'     => 'email',
					'is_valid' => $is_valid,
					'verdict'  => strtoupper( (string) $res->verdict ),
					'summary'  => $is_valid
						? __( 'Email passed deliverability check', 'proweblook-phone-validator' )
						: $this->validator->get_error_message(),
					'details'  => array(
						'Email'          => (string) $res->email,
						'Verdict'        => (string) $res->verdict,
						'Reason Code'    => ! empty( $res->reason_code ) ? (string) $res->reason_code : '—',
						'Mailbox Exists' => ! empty( $res->emailexist ) ? 'Yes' : 'No',
						'Disposable'     => ! empty( $res->is_disposable ) ? 'Yes (Blocked)' : 'No',
					),
					'balances' => $balances,
				)
			);
		}

		wp_send_json_error( array( 'message' => __( 'Unknown test type.', 'proweblook-phone-validator' ) ) );
	}

	/**
	 * Protect public AJAX endpoint from unauthenticated credit-draining bots (max 15 calls / 5 min / IP).
	 *
	 * @return bool True when rate limit is exceeded.
	 */
	protected function is_rate_limited() {
		$ip      = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$rl_key  = 'prop_v2_rl_' . substr( hash( 'sha256', $ip ), 0, 20 );
		$current = (int) get_transient( $rl_key );
		$limit   = (int) apply_filters( 'prop_frontend_ajax_rate_limit', 15 );

		if ( $current >= $limit ) {
			return true;
		}
		set_transient( $rl_key, $current + 1, 5 * MINUTE_IN_SECONDS );
		return false;
	}

	/**
	 * Register both legacy/frontend AJAX and admin test AJAX hooks.
	 *
	 * @return void
	 */
	public function register() {
		$this->handler->register( $this->get_action(), array( $this, 'callback' ), $this->get_private() );
		add_action( 'wp_ajax_prop_admin_test', array( $this, 'admin_test_callback' ) );
	}

	/**
	 * Set handler.
	 *
	 * @param PROP_Ajax_Handler $handler Handler object.
	 * @return object
	 */
	public function set_handler( $handler ) {
		$this->handler = $handler;
		return $this->get_handler();
	}

	/**
	 * Get handler.
	 *
	 * @return PROP_Ajax_Handler|null
	 */
	public function get_handler() {
		return $this->handler;
	}

	/**
	 * Set action slug.
	 *
	 * @param string $action Action slug.
	 * @return string
	 */
	public function set_action( $action ) {
		$this->action = sanitize_key( (string) $action );
		return $this->get_action();
	}

	/**
	 * Get action slug.
	 *
	 * @return string
	 */
	public function get_action() {
		return $this->action;
	}

	/**
	 * Set private flag.
	 *
	 * @param bool $private Private flag.
	 * @return bool
	 */
	public function set_private( $private ) {
		$this->private = (bool) $private;
		return $this->get_private();
	}

	/**
	 * Get private flag.
	 *
	 * @return bool
	 */
	public function get_private() {
		return $this->private;
	}

	/**
	 * Set validator.
	 *
	 * @param PROP_Phone_Validator $validator Validator engine.
	 * @return PROP_Phone_Validator
	 */
	public function set_validator( $validator ) {
		$this->validator = $validator;
		return $this->get_validator();
	}

	/**
	 * Get validator.
	 *
	 * @return PROP_Phone_Validator|null
	 */
	public function get_validator() {
		return $this->validator;
	}
}
