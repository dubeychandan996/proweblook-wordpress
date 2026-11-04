<?php
/**
 * WooCommerce Integration: Classic Checkout, Cart & Checkout Blocks (Store API),
 * HPOS Order Meta & Order Notes, Silent WhatsApp COD RTO Guard, and Email Verifier.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_WC
 */
class PROP_WC {

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
	 * Cached verification snapshot from the current request to enrich order meta without re-querying.
	 *
	 * @var array
	 */
	protected $checkout_snapshot = array();

	/**
	 * Set options array.
	 *
	 * @param array $options Plugin settings.
	 * @return void
	 */
	public function set_options( $options ) {
		$this->options = (array) $options;
	}

	/**
	 * Register WooCommerce hooks for Classic Checkout, Block Checkout, My Account, and Admin Order UI.
	 *
	 * @return void
	 */
	public function setup() {
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'attach_order_meta_classic' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_and_enrich_block_checkout' ), 10, 2 );
		add_filter( 'woocommerce_process_myaccount_field_billing_phone', array( $this, 'validate_edit' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin_order_badge' ), 10, 1 );
	}

	/**
	 * Validate Classic WooCommerce Checkout submission.
	 *
	 * @param array         $fields Checkout fields.
	 * @param WP_Error|null $errors Checkout WP_Error instance.
	 * @return void
	 */
	public function validate_checkout( $fields, $errors = null ) {
		$phone          = isset( $fields['billing_phone'] ) ? trim( (string) $fields['billing_phone'] ) : '';
		$country        = isset( $fields['billing_country'] ) ? trim( (string) $fields['billing_country'] ) : '';
		$email          = isset( $fields['billing_email'] ) ? trim( (string) $fields['billing_email'] ) : '';
		$payment_method = isset( $fields['payment_method'] ) ? sanitize_key( (string) $fields['payment_method'] ) : '';

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates checkout nonce prior to firing woocommerce_after_checkout_validation.
		if ( '' === $payment_method && isset( $_POST['payment_method'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$payment_method = sanitize_key( wp_unslash( $_POST['payment_method'] ) );
		}

		$messages = $this->run_checkout_checks( $phone, $country, $email, $payment_method );

		foreach ( $messages as $code => $msg ) {
			if ( is_object( $errors ) && method_exists( $errors, 'add' ) ) {
				$errors->add( $code, $msg );
			} else {
				wc_add_notice( $msg, 'error' );
			}
		}
	}

	/**
	 * Validate and enrich WooCommerce Block Checkout (Store API).
	 *
	 * @param WC_Order        $order   Order being updated/placed.
	 * @param WP_REST_Request $request Store API checkout REST request.
	 * @return void
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When phone, WhatsApp COD, or email check fails.
	 */
	public function validate_and_enrich_block_checkout( $order, $request ) {
		if ( ! is_object( $order ) ) {
			return;
		}

		$phone   = method_exists( $order, 'get_billing_phone' ) ? trim( (string) $order->get_billing_phone() ) : '';
		$country = method_exists( $order, 'get_billing_country' ) ? trim( (string) $order->get_billing_country() ) : '';
		$email   = method_exists( $order, 'get_billing_email' ) ? trim( (string) $order->get_billing_email() ) : '';

		$payment_method = '';
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$req_pm = $request->get_param( 'payment_method' );
			if ( ! empty( $req_pm ) ) {
				$payment_method = sanitize_key( (string) $req_pm );
			}
		}
		if ( '' === $payment_method && method_exists( $order, 'get_payment_method' ) ) {
			$payment_method = sanitize_key( (string) $order->get_payment_method() );
		}

		$messages = $this->run_checkout_checks( $phone, $country, $email, $payment_method );

		if ( ! empty( $messages ) ) {
			$first_error = wp_strip_all_tags( reset( $messages ) );
			if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
				throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
					'proweblook_checkout_validation_error',
					esc_html( $first_error ),
					400
				);
			}
			wc_add_notice( esc_html( $first_error ), 'error' );
			return;
		}

		$this->persist_snapshot_to_order( $order );
	}

	/**
	 * Run configured Phone, Silent WhatsApp COD Guard, and Email checks.
	 *
	 * @param string $phone          Billing phone.
	 * @param string $country        Billing ISO country.
	 * @param string $email          Billing email.
	 * @param string $payment_method Selected WooCommerce payment method slug (e.g. 'cod').
	 * @return array Associative array of error_code => html_message.
	 */
	protected function run_checkout_checks( $phone, $country, $email, $payment_method ) {
		$errors                  = array();
		$this->checkout_snapshot = array();

		$check_phone = ! empty( $this->options['propp_auto_check'] );
		$wa_mode     = isset( $this->options['propp_wc_whatsapp_mode'] ) ? (string) $this->options['propp_wc_whatsapp_mode'] : 'off';
		if ( 'off' === $wa_mode && ! empty( $this->options['propp_whatsapp_check'] ) ) {
			$wa_mode = 'all';
		}
		$check_email = ! empty( $this->options['propp_email_check'] );

		$country_hint = '';
		if ( '' !== $phone && strlen( $phone ) > 2 && '00' !== substr( $phone, 0, 2 ) && '+' !== substr( $phone, 0, 1 ) ) {
			$country_hint = $country;
		}

		$is_valid_wc_phone = ( '' !== $phone ) && ( ! class_exists( 'WC_Validation' ) || WC_Validation::is_phone( $phone ) );

		// 1. Phone Carrier & VoIP Check.
		if ( $check_phone && $is_valid_wc_phone ) {
			$this->validator->set_phone( $phone );
			$phone_ok = $this->validator->validate( $country_hint );
			$p_res    = $this->validator->get_last_phone_result();

			$this->checkout_snapshot['phone'] = array(
				'status'    => $this->validator->get_phone_status(),
				'carrier'   => isset( $p_res->carrier ) ? (string) $p_res->carrier : '',
				'linetype'  => isset( $p_res->linetype ) ? (string) $p_res->linetype : '',
				'formatted' => isset( $p_res->formatted_number ) ? (string) $p_res->formatted_number : $phone,
			);

			if ( ! $phone_ok ) {
				$errors['proweblook_invalid_phone'] = sprintf(
					'<strong>%s</strong> %s',
					esc_html__( 'Billing Phone:', 'proweblook-phone-validator' ),
					esc_html( $this->validator->get_error_message( __( 'is not a valid phone number.', 'proweblook-phone-validator' ) ) )
				);
			}
		}

		// 2. Silent WhatsApp COD RTO Guard.
		$should_check_wa = ( 'all' === $wa_mode ) || ( 'cod_only' === $wa_mode && 'cod' === strtolower( $payment_method ) );
		if ( $should_check_wa && empty( $errors['proweblook_invalid_phone'] ) ) {
			if ( '' === $phone ) {
				$errors['proweblook_missing_whatsapp_phone'] = sprintf(
					'<strong>%s</strong> %s',
					esc_html__( 'Billing Phone:', 'proweblook-phone-validator' ),
					esc_html__( 'An active WhatsApp phone number is required for Cash on Delivery orders.', 'proweblook-phone-validator' )
				);
			} elseif ( $is_valid_wc_phone ) {
				$wa_ok  = $this->validator->validate_whatsapp( $phone, $country_hint );
				$wa_res = $this->validator->get_last_whatsapp_result();

				if ( $wa_res && empty( $wa_res->network_error ) ) {
					$this->checkout_snapshot['whatsapp'] = array(
						'status' => ! empty( $wa_res->on_whatsapp ) ? 'active' : 'inactive',
						'type'   => isset( $wa_res->account_type ) ? (string) $wa_res->account_type : 'none',
					);
				}

				if ( ! $wa_ok ) {
					$wa_msg = ( 'cod_only' === $wa_mode )
						? __( 'Cash on Delivery requires an active WhatsApp number so we can confirm dispatch. Please enter a WhatsApp-enabled number or choose a prepaid payment method.', 'proweblook-phone-validator' )
						: $this->validator->get_error_message();

					$errors['proweblook_whatsapp_required'] = sprintf(
						'<strong>%s</strong> %s',
						esc_html__( 'WhatsApp Verification:', 'proweblook-phone-validator' ),
						esc_html( $wa_msg )
					);
				}
			}
		}

		// 3. Email Deliverability & Disposable Email Blocker.
		if ( $check_email && '' !== $email ) {
			$email_ok = $this->validator->validate_email_address( $email );
			$e_res    = $this->validator->get_last_email_result();

			if ( $e_res && empty( $e_res->network_error ) ) {
				$this->checkout_snapshot['email'] = array(
					'verdict' => isset( $e_res->verdict ) ? (string) $e_res->verdict : '',
					'reason'  => isset( $e_res->reason_code ) ? (string) $e_res->reason_code : '',
				);
			}

			if ( ! $email_ok ) {
				$errors['proweblook_invalid_email'] = sprintf(
					'<strong>%s</strong> %s',
					esc_html__( 'Billing Email:', 'proweblook-phone-validator' ),
					esc_html( $this->validator->get_error_message() )
				);
			}
		}

		return $errors;
	}

	/**
	 * Attach verification metadata & merchant order note on Classic Checkout (HPOS-compatible).
	 *
	 * @param WC_Order $order Order instance.
	 * @param array    $data  Posted checkout data.
	 * @return void
	 */
	public function attach_order_meta_classic( $order, $data = array() ) {
		if ( ! is_object( $order ) ) {
			return;
		}
		$this->persist_snapshot_to_order( $order );
	}

	/**
	 * Persist verification snapshot onto a WC_Order using HPOS-safe CRUD methods.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	protected function persist_snapshot_to_order( $order ) {
		if ( empty( $this->checkout_snapshot ) || ! method_exists( $order, 'update_meta_data' ) ) {
			return;
		}

		if ( method_exists( $order, 'get_meta' ) && 'yes' === $order->get_meta( '_proweblook_verified', true ) ) {
			return;
		}

		$note_parts = array();

		if ( ! empty( $this->checkout_snapshot['phone'] ) ) {
			$p = $this->checkout_snapshot['phone'];
			$order->update_meta_data( '_proweblook_phone_status', sanitize_text_field( $p['status'] ) );
			$order->update_meta_data( '_proweblook_phone_carrier', sanitize_text_field( $p['carrier'] ) );
			$order->update_meta_data( '_proweblook_phone_linetype', sanitize_text_field( $p['linetype'] ) );
			$order->update_meta_data( '_proweblook_phone_formatted', sanitize_text_field( $p['formatted'] ) );

			$meta_desc    = trim( $p['linetype'] . ( $p['carrier'] ? ' - ' . $p['carrier'] : '' ) );
			$note_parts[] = 'Phone: ' . $p['status'] . ( $meta_desc ? ' (' . $meta_desc . ')' : '' );
		}

		if ( ! empty( $this->checkout_snapshot['whatsapp'] ) ) {
			$w = $this->checkout_snapshot['whatsapp'];
			$order->update_meta_data( '_proweblook_whatsapp_status', sanitize_text_field( $w['status'] ) );
			$order->update_meta_data( '_proweblook_whatsapp_type', sanitize_text_field( $w['type'] ) );
			$note_parts[] = 'WhatsApp: ' . ucfirst( $w['status'] ) . ( 'none' !== $w['type'] ? ' (' . ucfirst( $w['type'] ) . ')' : '' );
		}

		if ( ! empty( $this->checkout_snapshot['email'] ) ) {
			$e = $this->checkout_snapshot['email'];
			$order->update_meta_data( '_proweblook_email_verdict', sanitize_text_field( $e['verdict'] ) );
			$order->update_meta_data( '_proweblook_email_reason', sanitize_text_field( $e['reason'] ) );
			$note_parts[] = 'Email: ' . $e['verdict'] . ( $e['reason'] ? ' (' . $e['reason'] . ')' : '' );
		}

		if ( ! empty( $note_parts ) ) {
			$order->update_meta_data( '_proweblook_verified', 'yes' );
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( 'ProWebLook Verification — ' . implode( ' | ', $note_parts ) );
			}
		}
	}

	/**
	 * Render verification summary badge inside WP Admin -> WooCommerce -> Order Details.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	public function render_admin_order_badge( $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}

		$phone_status = (string) $order->get_meta( '_proweblook_phone_status', true );
		$wa_status    = (string) $order->get_meta( '_proweblook_whatsapp_status', true );
		$email_verd   = (string) $order->get_meta( '_proweblook_email_verdict', true );

		if ( '' === $phone_status && '' === $wa_status && '' === $email_verd ) {
			return;
		}

		$carrier  = (string) $order->get_meta( '_proweblook_phone_carrier', true );
		$linetype = (string) $order->get_meta( '_proweblook_phone_linetype', true );
		$wa_type  = (string) $order->get_meta( '_proweblook_whatsapp_type', true );

		echo '<div class="proweblook-order-meta" style="margin-top:12px;padding:10px 12px;background:#f6f7f7;border-left:4px solid #00a32a;border-radius:3px;">';
		echo '<strong style="display:block;margin-bottom:4px;">' . esc_html__( 'ProWebLook Trust Signals', 'proweblook-phone-validator' ) . '</strong>';

		if ( '' !== $phone_status ) {
			$extra = trim( $linetype . ( $carrier ? ' / ' . $carrier : '' ) );
			echo '<div style="font-size:12px;line-height:1.6;">' . esc_html__( 'Phone:', 'proweblook-phone-validator' ) . ' <code>' . esc_html( $phone_status ) . '</code>' . ( $extra ? ' (' . esc_html( $extra ) . ')' : '' ) . '</div>';
		}
		if ( '' !== $wa_status ) {
			echo '<div style="font-size:12px;line-height:1.6;">' . esc_html__( 'WhatsApp:', 'proweblook-phone-validator' ) . ' <code>' . esc_html( strtoupper( $wa_status ) ) . '</code>' . ( $wa_type && 'none' !== $wa_type ? ' (' . esc_html( ucfirst( $wa_type ) ) . ')' : '' ) . '</div>';
		}
		if ( '' !== $email_verd ) {
			echo '<div style="font-size:12px;line-height:1.6;">' . esc_html__( 'Email:', 'proweblook-phone-validator' ) . ' <code>' . esc_html( strtoupper( $email_verd ) ) . '</code></div>';
		}
		echo '</div>';
	}

	/**
	 * Validate phone number edits under My Account -> Edit Billing Address.
	 *
	 * @param string $phone Submitted billing phone.
	 * @return string
	 */
	public function validate_edit( $phone ) {
		if ( ! empty( $this->options['propp_auto_check'] ) && ! empty( $phone ) && ( ! class_exists( 'WC_Validation' ) || WC_Validation::is_phone( $phone ) ) ) {
			$this->validator->set_phone( $phone );
			if ( ! $this->validator->validate() ) {
				wc_add_notice(
					sprintf(
						'<strong>%s</strong> %s',
						esc_html__( 'Phone:', 'proweblook-phone-validator' ),
						esc_html( $this->validator->get_error_message( __( 'is not a valid phone number.', 'proweblook-phone-validator' ) ) )
					),
					'error'
				);
			}
		}
		return $phone;
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
