<?php
/**
 * WooCommerce Integration: Classic Checkout, Cart & Checkout Blocks (Store API),
 * HPOS Order Meta & Order Notes, Silent WhatsApp COD RTO Guard, Caller ID (CNAM),
 * Edge GeoIP Country-Mismatch, Domain DNSBL, 0–100 Trust Score & Fraud Auto-Hold.
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
	 * Register WooCommerce hooks for Classic Checkout, Block Checkout, Auto-Hold, My Account, and Admin Order UI.
	 *
	 * @return void
	 */
	public function setup() {
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate_checkout' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'attach_order_meta_classic' ), 20, 2 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_and_enrich_block_checkout' ), 10, 2 );

		// Auto-Hold hooks after payment gateway sets initial status (Classic + Store API Block Checkout).
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'maybe_apply_autohold' ), 99, 1 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'maybe_apply_autohold' ), 99, 1 );
		add_filter( 'woocommerce_cod_process_payment_order_status', array( $this, 'filter_cod_order_status' ), 99, 2 );

		// My Account phone edit & Admin Order UI (Order Details card + HPOS & Legacy Orders list column).
		add_filter( 'woocommerce_process_myaccount_field_billing_phone', array( $this, 'validate_edit' ) );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'render_admin_order_badge' ), 10, 1 );

		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'register_orders_trust_column' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_orders_trust_column_hpos' ), 20, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'register_orders_trust_column' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'render_orders_trust_column_legacy' ), 20, 2 );
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
		$first_name     = isset( $fields['billing_first_name'] ) ? trim( (string) $fields['billing_first_name'] ) : '';
		$last_name      = isset( $fields['billing_last_name'] ) ? trim( (string) $fields['billing_last_name'] ) : '';
		$customer_name  = trim( $first_name . ' ' . $last_name );
		$company        = isset( $fields['billing_company'] ) ? trim( (string) $fields['billing_company'] ) : '';
		$client_ip      = isset( $fields['customer_ip'] ) ? trim( (string) $fields['customer_ip'] ) : $this->detect_customer_ip();

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce validates checkout nonce prior to firing woocommerce_after_checkout_validation.
		if ( '' === $payment_method && isset( $_POST['payment_method'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing
			$payment_method = sanitize_key( wp_unslash( $_POST['payment_method'] ) );
		}

		$messages = $this->run_checkout_checks( $phone, $country, $email, $payment_method, $customer_name, $company, $client_ip );

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
	 * @throws \Automattic\WooCommerce\StoreApi\Exceptions\RouteException When phone, WhatsApp COD, email, or DNSBL check fails.
	 */
	public function validate_and_enrich_block_checkout( $order, $request ) {
		if ( ! is_object( $order ) ) {
			return;
		}

		$phone      = method_exists( $order, 'get_billing_phone' ) ? trim( (string) $order->get_billing_phone() ) : '';
		$country    = method_exists( $order, 'get_billing_country' ) ? trim( (string) $order->get_billing_country() ) : '';
		$email      = method_exists( $order, 'get_billing_email' ) ? trim( (string) $order->get_billing_email() ) : '';
		$first_name = method_exists( $order, 'get_billing_first_name' ) ? trim( (string) $order->get_billing_first_name() ) : '';
		$last_name  = method_exists( $order, 'get_billing_last_name' ) ? trim( (string) $order->get_billing_last_name() ) : '';
		$cust_name  = trim( $first_name . ' ' . $last_name );
		$company    = method_exists( $order, 'get_billing_company' ) ? trim( (string) $order->get_billing_company() ) : '';
		$client_ip  = method_exists( $order, 'get_customer_ip_address' ) ? trim( (string) $order->get_customer_ip_address() ) : '';
		if ( '' === $client_ip ) {
			$client_ip = $this->detect_customer_ip();
		}

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

		$messages = $this->run_checkout_checks( $phone, $country, $email, $payment_method, $cust_name, $company, $client_ip );

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
	 * Run configured Phone, Silent WhatsApp COD Guard, Email, Domain DNSBL, Caller ID (CNAM), and Edge GeoIP checks.
	 *
	 * @param string $phone          Billing phone.
	 * @param string $country        Billing ISO country.
	 * @param string $email          Billing email.
	 * @param string $payment_method Selected WooCommerce payment method slug (e.g. 'cod').
	 * @param string $customer_name  Billing full name for Caller ID CNAM comparison.
	 * @param string $company        Billing company name for Caller ID CNAM comparison.
	 * @param string $client_ip      Shopper IP address for Edge GeoIP comparison.
	 * @return array Associative array of error_code => html_message.
	 */
	protected function run_checkout_checks( $phone, $country, $email, $payment_method, $customer_name = '', $company = '', $client_ip = '' ) {
		$errors                  = array();
		$this->checkout_snapshot = array();

		$check_phone = ! empty( $this->options['propp_auto_check'] );
		$wa_mode     = isset( $this->options['propp_wc_whatsapp_mode'] ) ? (string) $this->options['propp_wc_whatsapp_mode'] : 'off';
		if ( 'off' === $wa_mode && ! empty( $this->options['propp_whatsapp_check'] ) ) {
			$wa_mode = 'all';
		}
		$check_email = ! empty( $this->options['propp_email_check'] );
		$check_dnsbl = ! empty( $this->options['propp_dnsbl_check'] );
		$block_dnsbl = ! empty( $this->options['propp_block_dnsbl'] );
		$cnam_mode   = isset( $this->options['propp_wc_caller_id_mode'] ) ? (string) $this->options['propp_wc_caller_id_mode'] : 'off';
		$check_ipgeo = ! empty( $this->options['propp_wc_ip_geo_check'] );

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

		// 3. Email Deliverability & Disposable Email Blocker (+ Domain DNSBL if enabled).
		if ( $check_email && '' !== $email ) {
			$this->validator->set_check_dnsbl( $check_dnsbl );
			$this->validator->set_block_dnsbl( $block_dnsbl );

			$email_ok = $this->validator->validate_email_address( $email );
			$e_res    = $this->validator->get_last_email_result();
			$b_res    = $this->validator->get_last_blacklist_result();

			if ( $e_res && empty( $e_res->network_error ) ) {
				$this->checkout_snapshot['email'] = array(
					'verdict'       => isset( $e_res->verdict ) ? (string) $e_res->verdict : '',
					'reason'        => isset( $e_res->reason_code ) ? (string) $e_res->reason_code : '',
					'is_disposable' => ! empty( $e_res->is_disposable ),
				);
			}

			if ( $b_res && empty( $b_res->network_error ) ) {
				$this->checkout_snapshot['blacklist'] = array(
					'target'               => isset( $b_res->target ) ? (string) $b_res->target : '',
					'listed'               => ! empty( $b_res->listed ),
					'listed_count'         => isset( $b_res->listed_count ) ? (int) $b_res->listed_count : 0,
					'skipped_free_webmail' => ! empty( $b_res->skipped_free_webmail ),
				);
			}

			if ( ! $email_ok ) {
				$errors['proweblook_invalid_email'] = sprintf(
					'<strong>%s</strong> %s',
					esc_html__( 'Billing Email:', 'proweblook-phone-validator' ),
					esc_html( $this->validator->get_error_message() )
				);
			}
		} elseif ( $check_dnsbl && '' !== $email ) {
			// Standalone Domain DNSBL check when Email Deliverability is disabled.
			$dnsbl_ok = $this->validator->validate_domain_blacklist( $email, $block_dnsbl, true );
			$b_res    = $this->validator->get_last_blacklist_result();

			if ( $b_res && empty( $b_res->network_error ) ) {
				$this->checkout_snapshot['blacklist'] = array(
					'target'               => isset( $b_res->target ) ? (string) $b_res->target : '',
					'listed'               => ! empty( $b_res->listed ),
					'listed_count'         => isset( $b_res->listed_count ) ? (int) $b_res->listed_count : 0,
					'skipped_free_webmail' => ! empty( $b_res->skipped_free_webmail ),
				);
			}

			if ( ! $dnsbl_ok ) {
				$errors['proweblook_blacklisted_domain'] = sprintf(
					'<strong>%s</strong> %s',
					esc_html__( 'Billing Email:', 'proweblook-phone-validator' ),
					esc_html( $this->validator->get_error_message() )
				);
			}
		}

		// Only run post-validation enrichment signals (Caller ID CNAM & Edge GeoIP) when hard blockers passed.
		if ( empty( $errors ) ) {
			// 4. Caller ID (CNAM) Subscriber Name-Match Signal.
			$should_check_cnam = ( 'all' === $cnam_mode ) || ( 'cod_only' === $cnam_mode && 'cod' === strtolower( $payment_method ) );
			if ( $should_check_cnam && $is_valid_wc_phone ) {
				$c_res = $this->validator->lookup_caller_id_signal( $phone, $country_hint, $customer_name, $company );
				if ( $c_res && empty( $c_res->network_error ) && ! empty( $c_res->ok ) ) {
					$this->checkout_snapshot['caller_id'] = array(
						'found'       => ! empty( $c_res->found ),
						'caller_name' => isset( $c_res->caller_name ) ? (string) $c_res->caller_name : '',
						'caller_type' => isset( $c_res->caller_type ) ? (string) $c_res->caller_type : '',
						'name_match'  => isset( $c_res->name_match ) ? (string) $c_res->name_match : 'unavailable',
					);
				}
			}

			// 5. Edge GeoIP Country-Mismatch & VPN/Datacenter Proxy Signal (Free 20k/mo quota).
			if ( $check_ipgeo && '' !== $client_ip ) {
				$g_res = $this->validator->lookup_ip_geo_signal( $client_ip, $country );
				if ( $g_res && empty( $g_res->network_error ) && ! empty( $g_res->ok ) ) {
					$this->checkout_snapshot['ip_geo'] = array(
						'ip'                  => isset( $g_res->ip ) ? (string) $g_res->ip : $client_ip,
						'country_code'        => isset( $g_res->country_code ) ? (string) $g_res->country_code : '',
						'expected_country'    => isset( $g_res->expected_country ) ? (string) $g_res->expected_country : $country,
						'country_mismatch'    => ! empty( $g_res->country_mismatch ),
						'isp'                 => isset( $g_res->isp ) ? (string) $g_res->isp : '',
						'is_hosting_or_proxy' => ! empty( $g_res->is_hosting_or_proxy ),
					);
				}
			}

			// 6. Composite 0–100 Trust Score & Fraud Auto-Hold Decision.
			if ( ! empty( $this->checkout_snapshot ) ) {
				$trust                                     = $this->validator->calculate_trust_score( $this->checkout_snapshot );
				$this->checkout_snapshot['trust']          = $trust;
				$this->checkout_snapshot['payment_method'] = strtolower( $payment_method );
			}
		}

		return $errors;
	}

	/**
	 * Determine whether an order's Trust Score should place it On Hold automatically.
	 *
	 * @param array  $trust          Calculated trust score array (`score`, `risk_level`).
	 * @param string $payment_method Selected payment method slug.
	 * @return bool
	 */
	protected function should_autohold_order( $trust, $payment_method ) {
		$mode  = isset( $this->options['propp_wc_autohold_mode'] ) ? (string) $this->options['propp_wc_autohold_mode'] : 'off';
		$score = isset( $trust['score'] ) ? (int) $trust['score'] : 100;
		$pm    = strtolower( (string) $payment_method );

		if ( 'high_risk' === $mode ) {
			return ( $score < 45 );
		}
		if ( 'cod_review' === $mode ) {
			return ( 'cod' === $pm && $score < 65 );
		}
		if ( 'all_review' === $mode ) {
			return ( $score < 65 );
		}

		return false;
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

		if ( ! empty( $this->checkout_snapshot['trust'] ) ) {
			$t = $this->checkout_snapshot['trust'];
			$order->update_meta_data( '_proweblook_trust_score', (int) $t['score'] );
			$order->update_meta_data( '_proweblook_risk_level', sanitize_key( (string) $t['risk_level'] ) );
			if ( ! empty( $t['reasons'] ) && is_array( $t['reasons'] ) ) {
				$order->update_meta_data( '_proweblook_risk_reasons', sanitize_text_field( implode( '; ', $t['reasons'] ) ) );
			}
			$note_parts[] = sprintf( 'Trust Score: %d/100 (%s)', (int) $t['score'], strtoupper( str_replace( '_', ' ', (string) $t['risk_level'] ) ) );

			$pm = isset( $this->checkout_snapshot['payment_method'] ) ? (string) $this->checkout_snapshot['payment_method'] : ( method_exists( $order, 'get_payment_method' ) ? (string) $order->get_payment_method() : '' );
			if ( $this->should_autohold_order( $t, $pm ) ) {
				$order->update_meta_data( '_proweblook_autohold_pending', 'yes' );
			}
		}

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

		if ( ! empty( $this->checkout_snapshot['caller_id'] ) ) {
			$c = $this->checkout_snapshot['caller_id'];
			$order->update_meta_data( '_proweblook_caller_name', sanitize_text_field( $c['caller_name'] ) );
			$order->update_meta_data( '_proweblook_caller_match', sanitize_key( $c['name_match'] ) );
			if ( ! empty( $c['found'] ) ) {
				$note_parts[] = 'Caller ID: ' . $c['caller_name'] . ' (' . strtoupper( $c['name_match'] ) . ')';
			}
		}

		if ( ! empty( $this->checkout_snapshot['ip_geo'] ) ) {
			$g = $this->checkout_snapshot['ip_geo'];
			$order->update_meta_data( '_proweblook_ip_country', sanitize_text_field( $g['country_code'] ) );
			$order->update_meta_data( '_proweblook_ip_isp', sanitize_text_field( $g['isp'] ) );
			$order->update_meta_data( '_proweblook_ip_mismatch', ! empty( $g['country_mismatch'] ) ? 'yes' : 'no' );
			$order->update_meta_data( '_proweblook_ip_proxy', ! empty( $g['is_hosting_or_proxy'] ) ? 'yes' : 'no' );

			$ip_flags = array();
			if ( ! empty( $g['country_mismatch'] ) ) {
				$ip_flags[] = 'Mismatch vs ' . $g['expected_country'];
			}
			if ( ! empty( $g['is_hosting_or_proxy'] ) ) {
				$ip_flags[] = 'VPN/Hosting';
			}
			$note_parts[] = 'IP Geo: ' . ( $g['country_code'] ? $g['country_code'] : 'Unknown' ) . ( ! empty( $ip_flags ) ? ' [' . implode( ', ', $ip_flags ) . ']' : '' );
		}

		if ( ! empty( $this->checkout_snapshot['blacklist'] ) && empty( $this->checkout_snapshot['blacklist']['skipped_free_webmail'] ) ) {
			$b = $this->checkout_snapshot['blacklist'];
			$order->update_meta_data( '_proweblook_dnsbl_listed', ! empty( $b['listed'] ) ? 'yes' : 'no' );
			$order->update_meta_data( '_proweblook_dnsbl_count', (int) $b['listed_count'] );
			$note_parts[] = 'DNSBL: ' . ( ! empty( $b['listed'] ) ? 'LISTED (' . (int) $b['listed_count'] . ')' : 'Clean' );
		}

		if ( ! empty( $note_parts ) ) {
			$order->update_meta_data( '_proweblook_verified', 'yes' );
			if ( method_exists( $order, 'add_order_note' ) ) {
				$order->add_order_note( 'ProWebLook Verification — ' . implode( ' | ', $note_parts ) );
			}
		}
	}

	/**
	 * Filter WooCommerce Cash on Delivery initial status to `on-hold` when flagged by ProWebLook Fraud Guard.
	 *
	 * @param string   $status Default COD status (usually `processing`).
	 * @param WC_Order $order  Order object.
	 * @return string
	 */
	public function filter_cod_order_status( $status, $order = null ) {
		if ( is_object( $order ) && method_exists( $order, 'get_meta' ) && 'yes' === $order->get_meta( '_proweblook_autohold_pending', true ) ) {
			return 'on-hold';
		}
		return $status;
	}

	/**
	 * Place risky orders On Hold (`on-hold`) after checkout completes if flagged by ProWebLook Fraud Guard.
	 *
	 * @param int|WC_Order $order_or_id Order ID or WC_Order object.
	 * @return void
	 */
	public function maybe_apply_autohold( $order_or_id ) {
		$order = is_object( $order_or_id ) ? $order_or_id : ( function_exists( 'wc_get_order' ) ? wc_get_order( $order_or_id ) : null );
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_meta' ) ) {
			return;
		}

		if ( 'yes' !== $order->get_meta( '_proweblook_autohold_pending', true ) ) {
			return;
		}

		if ( 'yes' === $order->get_meta( '_proweblook_autohold_applied', true ) ) {
			return;
		}

		$order->update_meta_data( '_proweblook_autohold_applied', 'yes' );

		$score   = (int) $order->get_meta( '_proweblook_trust_score', true );
		$reasons = (string) $order->get_meta( '_proweblook_risk_reasons', true );
		$note    = sprintf(
			/* translators: 1: Trust score 0-100, 2: Risk reasons */
			__( 'ProWebLook Fraud Guard placed this order On Hold for manual review (Trust Score: %1$d/100%2$s).', 'proweblook-phone-validator' ),
			$score,
			'' !== $reasons ? ' — ' . $reasons : ''
		);

		$current_status = method_exists( $order, 'get_status' ) ? (string) $order->get_status() : '';
		if ( 'on-hold' !== $current_status && method_exists( $order, 'update_status' ) ) {
			$order->update_status( 'on-hold', $note );
		} elseif ( method_exists( $order, 'add_order_note' ) ) {
			$order->add_order_note( $note );
			if ( method_exists( $order, 'save' ) ) {
				$order->save();
			}
		}
	}

	/**
	 * Register `ProWebLook Trust` column on WooCommerce Orders list table (HPOS + Legacy).
	 *
	 * @param array $columns Existing table columns.
	 * @return array
	 */
	public function register_orders_trust_column( $columns ) {
		$new_cols = array();
		foreach ( (array) $columns as $key => $label ) {
			$new_cols[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$new_cols['proweblook_trust'] = __( 'ProWebLook Trust', 'proweblook-phone-validator' );
			}
		}
		if ( ! isset( $new_cols['proweblook_trust'] ) ) {
			$new_cols['proweblook_trust'] = __( 'ProWebLook Trust', 'proweblook-phone-validator' );
		}
		return $new_cols;
	}

	/**
	 * Render `ProWebLook Trust` column cell for HPOS Orders table.
	 *
	 * @param string   $column Column slug.
	 * @param WC_Order $order  Order object.
	 * @return void
	 */
	public function render_orders_trust_column_hpos( $column, $order ) {
		if ( 'proweblook_trust' !== $column || ! is_object( $order ) ) {
			return;
		}
		$this->output_trust_pill( $order );
	}

	/**
	 * Render `ProWebLook Trust` column cell for Legacy CPT Orders table.
	 *
	 * @param string $column  Column slug.
	 * @param int    $post_id Order Post ID.
	 * @return void
	 */
	public function render_orders_trust_column_legacy( $column, $post_id ) {
		if ( 'proweblook_trust' !== $column || ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( is_object( $order ) ) {
			$this->output_trust_pill( $order );
		}
	}

	/**
	 * Output compact color-coded Trust Score pill for WooCommerce Orders table.
	 *
	 * @param WC_Order $order Order object.
	 * @return void
	 */
	protected function output_trust_pill( $order ) {
		if ( ! method_exists( $order, 'get_meta' ) ) {
			return;
		}
		$score_raw = $order->get_meta( '_proweblook_trust_score', true );
		if ( '' === (string) $score_raw ) {
			echo '<span style="color:#8c8f94;">&mdash;</span>';
			return;
		}

		$score   = (int) $score_raw;
		$risk    = (string) $order->get_meta( '_proweblook_risk_level', true );
		$reasons = (string) $order->get_meta( '_proweblook_risk_reasons', true );

		$bg    = '#edfaef';
		$color = '#007017';
		$label = __( 'Trusted', 'proweblook-phone-validator' );

		if ( 'high_risk' === $risk || $score < 45 ) {
			$bg    = '#fcf0f1';
			$color = '#b32d2e';
			$label = __( 'High Risk', 'proweblook-phone-validator' );
		} elseif ( 'review' === $risk || $score < 75 ) {
			$bg    = '#fcf9e8';
			$color = '#996800';
			$label = __( 'Review', 'proweblook-phone-validator' );
		}

		printf(
			'<span title="%s" style="display:inline-block;padding:2px 8px;border-radius:999px;font-size:11.5px;font-weight:600;background:%s;color:%s;">%d/100 &middot; %s</span>',
			esc_attr( $reasons ),
			esc_attr( $bg ),
			esc_attr( $color ),
			(int) $score,
			esc_html( $label )
		);
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

		$score_raw    = (string) $order->get_meta( '_proweblook_trust_score', true );
		$phone_status = (string) $order->get_meta( '_proweblook_phone_status', true );
		$wa_status    = (string) $order->get_meta( '_proweblook_whatsapp_status', true );
		$email_verd   = (string) $order->get_meta( '_proweblook_email_verdict', true );
		$caller_name  = (string) $order->get_meta( '_proweblook_caller_name', true );
		$ip_country   = (string) $order->get_meta( '_proweblook_ip_country', true );
		$dnsbl_listed = (string) $order->get_meta( '_proweblook_dnsbl_listed', true );

		if ( '' === $score_raw && '' === $phone_status && '' === $wa_status && '' === $email_verd && '' === $caller_name && '' === $ip_country && '' === $dnsbl_listed ) {
			return;
		}

		$risk_level   = (string) $order->get_meta( '_proweblook_risk_level', true );
		$risk_reasons = (string) $order->get_meta( '_proweblook_risk_reasons', true );
		$carrier      = (string) $order->get_meta( '_proweblook_phone_carrier', true );
		$linetype     = (string) $order->get_meta( '_proweblook_phone_linetype', true );
		$wa_type      = (string) $order->get_meta( '_proweblook_whatsapp_type', true );
		$caller_match = (string) $order->get_meta( '_proweblook_caller_match', true );
		$ip_isp       = (string) $order->get_meta( '_proweblook_ip_isp', true );
		$ip_mismatch  = (string) $order->get_meta( '_proweblook_ip_mismatch', true );
		$ip_proxy     = (string) $order->get_meta( '_proweblook_ip_proxy', true );
		$dnsbl_count  = (int) $order->get_meta( '_proweblook_dnsbl_count', true );

		$border_color = '#00a32a';
		if ( 'high_risk' === $risk_level ) {
			$border_color = '#d63638';
		} elseif ( 'review' === $risk_level ) {
			$border_color = '#dba617';
		}

		echo '<div class="proweblook-order-meta" style="margin-top:12px;padding:10px 12px;background:#f6f7f7;border-left:4px solid ' . esc_attr( $border_color ) . ';border-radius:3px;">';
		echo '<strong style="display:block;margin-bottom:4px;">' . esc_html__( 'ProWebLook Trust Signals', 'proweblook-phone-validator' );
		if ( '' !== $score_raw ) {
			echo ' &mdash; <code>' . esc_html( (int) $score_raw . '/100 ' . strtoupper( str_replace( '_', ' ', $risk_level ) ) ) . '</code>';
		}
		echo '</strong>';

		if ( '' !== $phone_status ) {
			$extra = trim( $linetype . ( $carrier ? ' / ' . $carrier : '' ) );
			echo '<div style="font-size:12px;line-height:1.6;">' . esc_html__( 'Phone:', 'proweblook-phone-validator' ) . ' <code>' . esc_html( $phone_status ) . '</code>' . ( $extra ? ' (' . esc_html( $extra ) . ')' : '' ) . '</div>';
		}
		if ( '' !== $wa_status ) {
			echo '<div style="font-size:12px;line-height:1.6;">' . esc_html__( 'WhatsApp:', 'proweblook-phone-validator' ) . ' <code>' . esc_html( strtoupper( $wa_status ) ) . '</code>' . ( $wa_type && 'none' !== $wa_type ? ' (' . esc_html( ucfirst( $wa_type ) ) . ')' : '' ) . '</div>';
		}
		if ( '' !== $caller_name ) {
			echo '<div style="font-size:12px;line-height:1.6;">' . esc_html__( 'Caller ID (CNAM):', 'proweblook-phone-validator' ) . ' <code>' . esc_html( $caller_name ) . '</code>' . ( $caller_match ? ' (' . esc_html( strtoupper( $caller_match ) ) . ')' : '' ) . '</div>';
		}
		if ( '' !== $email_verd ) {
			echo '<div style="font-size:12px;line-height:1.6;">' . esc_html__( 'Email:', 'proweblook-phone-validator' ) . ' <code>' . esc_html( strtoupper( $email_verd ) ) . '</code></div>';
		}
		if ( '' !== $dnsbl_listed ) {
			$dnsbl_txt = ( 'yes' === $dnsbl_listed ) ? sprintf( 'LISTED (%d)', max( 1, $dnsbl_count ) ) : 'CLEAN';
			echo '<div style="font-size:12px;line-height:1.6;">' . esc_html__( 'Domain DNSBL:', 'proweblook-phone-validator' ) . ' <code>' . esc_html( $dnsbl_txt ) . '</code></div>';
		}
		if ( '' !== $ip_country ) {
			$ip_notes = array();
			if ( '' !== $ip_isp ) {
				$ip_notes[] = $ip_isp;
			}
			if ( 'yes' === $ip_mismatch ) {
				$ip_notes[] = __( 'Country Mismatch', 'proweblook-phone-validator' );
			}
			if ( 'yes' === $ip_proxy ) {
				$ip_notes[] = __( 'VPN/Hosting', 'proweblook-phone-validator' );
			}
			echo '<div style="font-size:12px;line-height:1.6;">' . esc_html__( 'Shopper IP Geo:', 'proweblook-phone-validator' ) . ' <code>' . esc_html( $ip_country ) . '</code>' . ( ! empty( $ip_notes ) ? ' (' . esc_html( implode( ' · ', $ip_notes ) ) . ')' : '' ) . '</div>';
		}
		if ( '' !== $risk_reasons ) {
			echo '<div style="font-size:11.5px;line-height:1.5;margin-top:4px;color:#b32d2e;"><strong>' . esc_html__( 'Flags:', 'proweblook-phone-validator' ) . '</strong> ' . esc_html( $risk_reasons ) . '</div>';
		}
		echo '</div>';
	}

	/**
	 * Detect shopper IP address using WooCommerce Geolocation helper when available.
	 *
	 * @return string
	 */
	protected function detect_customer_ip() {
		if ( class_exists( 'WC_Geolocation' ) && method_exists( 'WC_Geolocation', 'get_ip_address' ) ) {
			$wc_ip = trim( (string) WC_Geolocation::get_ip_address() );
			if ( '' !== $wc_ip ) {
				return $wc_ip;
			}
		}
		if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
		}
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return '';
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
