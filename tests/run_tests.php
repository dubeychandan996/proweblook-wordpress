<?php
/**
 * Standalone Contract & Integration Test Suite for ProWebLook WordPress Plugin v2.1.0.
 * Run: php tests/run_tests.php
 */

namespace {
error_reporting( E_ALL );
ini_set( 'display_errors', '1' );

define( 'ABSPATH', dirname( __DIR__ ) . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

// Minimal WordPress mock environment.
$GLOBALS['_wp_options']    = array();
$GLOBALS['_wp_transients'] = array();
$GLOBALS['_wp_cache']      = array();
$GLOBALS['_wp_filters']    = array();
$GLOBALS['_wp_actions']    = array();
$GLOBALS['_http_mock']     = array();
$GLOBALS['_http_calls']    = 0;
$GLOBALS['_wc_notices']    = array();

function plugin_basename( $file ) { return 'proweblook-phone-validator/' . basename( $file ); }
function plugin_dir_path( $file ) { return rtrim( dirname( $file ), '/' ) . '/'; }
function plugins_url( $path = '', $plugin = '' ) { return 'https://example.test/wp-content/plugins/proweblook-phone-validator/'; }
function home_url() { return 'https://example.test'; }
function admin_url( $path = '' ) { return 'https://example.test/wp-admin/' . ltrim( $path, '/' ); }
function load_plugin_textdomain( $d, $f, $p ) { return true; }
function __( $text, $domain = '' ) { return $text; }
function esc_html__( $text, $domain = '' ) { return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' ); }
function esc_html_e( $text, $domain = '' ) { echo esc_html__( $text, $domain ); }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $url ) { return $url; }
function wp_kses_post( $html ) { return $html; }
function wp_strip_all_tags( $str ) { return strip_tags( (string) $str ); }
function sanitize_text_field( $str ) { return trim( strip_tags( (string) $str ) ); }
function sanitize_key( $key ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) ); }
function sanitize_email( $email ) { return filter_var( trim( (string) $email ), FILTER_SANITIZE_EMAIL ); }
function wp_unslash( $val ) { return $val; }
function is_email( $email ) { return false !== filter_var( $email, FILTER_VALIDATE_EMAIL ); }
function rawurlencode_deep( $value ) {
	return is_array( $value ) ? array_map( 'rawurlencode_deep', $value ) : rawurlencode( (string) $value );
}
function add_query_arg( $args, $url ) {
	$sep = ( false === strpos( $url, '?' ) ) ? '?' : '&';
	$pairs = array();
	foreach ( $args as $k => $v ) {
		$pairs[] = $k . '=' . $v;
	}
	return $url . $sep . implode( '&', $pairs );
}
function wp_parse_args( $args, $defaults = array() ) { return array_merge( $defaults, (array) $args ); }
function get_option( $key, $default = false ) { return array_key_exists( $key, $GLOBALS['_wp_options'] ) ? $GLOBALS['_wp_options'][ $key ] : $default; }
function update_option( $key, $val ) { $GLOBALS['_wp_options'][ $key ] = $val; return true; }
function delete_option( $key ) { unset( $GLOBALS['_wp_options'][ $key ] ); return true; }
function get_transient( $key ) { return array_key_exists( $key, $GLOBALS['_wp_transients'] ) ? $GLOBALS['_wp_transients'][ $key ] : false; }
function set_transient( $key, $val, $ttl = 0 ) { $GLOBALS['_wp_transients'][ $key ] = $val; return true; }
function wp_cache_get( $key, $group = '' ) { return isset( $GLOBALS['_wp_cache'][ $group . ':' . $key ] ) ? $GLOBALS['_wp_cache'][ $group . ':' . $key ] : false; }
function wp_cache_set( $key, $val, $group = '', $ttl = 0 ) { $GLOBALS['_wp_cache'][ $group . ':' . $key ] = $val; return true; }
function add_action( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_wp_actions'][ $tag ][] = $cb; }
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_wp_filters'][ $tag ][] = $cb; }
function apply_filters( $tag, $value ) {
	$args = func_get_args();
	array_shift( $args );
	if ( ! empty( $GLOBALS['_wp_filters'][ $tag ] ) ) {
		foreach ( $GLOBALS['_wp_filters'][ $tag ] as $cb ) {
			$value   = call_user_func_array( $cb, $args );
			$args[0] = $value;
		}
	}
	return $value;
}
function is_admin() { return false; }
function current_user_can( $cap ) { return true; }
function wp_create_nonce( $action = '' ) { return 'nonce_' . $action; }
function wp_verify_nonce( $nonce, $action = '' ) { return $nonce === 'nonce_' . $action; }
function number_format_i18n( $num ) { return number_format( (float) $num ); }

class WP_Error {
	public $errors = array();
	private $msg;
	public function __construct( $code = '', $message = '' ) {
		if ( $code ) {
			$this->errors[ $code ][] = $message;
			$this->msg               = $message;
		}
	}
	public function add( $code, $message ) { $this->errors[ $code ][] = $message; }
	public function get_error_message() { return $this->msg; }
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }

function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['_http_calls']++;
	foreach ( $GLOBALS['_http_mock'] as $pattern => $responder ) {
		if ( false !== strpos( $url, $pattern ) ) {
			return call_user_func( $responder, $url, $args );
		}
	}
	return new WP_Error( 'http_err', 'No mock matched: ' . $url );
}
function wp_remote_get( $url, $args = array() ) {
	return wp_remote_post( $url, $args );
}
function wp_remote_retrieve_response_code( $res ) { return isset( $res['response']['code'] ) ? $res['response']['code'] : 200; }
function wp_remote_retrieve_body( $res ) { return isset( $res['body'] ) ? $res['body'] : ''; }

function wc_add_notice( $msg, $type = 'error' ) { $GLOBALS['_wc_notices'][] = array( 'msg' => $msg, 'type' => $type ); }
}

namespace Automattic\WooCommerce\StoreApi\Exceptions {
	class RouteException extends \Exception {
		public $error_code;
		public function __construct( $code, $message, $http_status = 400 ) {
			parent::__construct( $message, $http_status );
			$this->error_code = $code;
		}
	}
}

namespace {
	class Mock_WC_Order {
		public $meta       = array();
		public $notes      = array();
		public $phone      = '';
		public $email      = '';
		public $country    = 'IN';
		public $pm         = 'cod';
		public $first_name = '';
		public $last_name  = '';
		public $company    = '';
		public $ip         = '';
		public $status     = 'processing';

		public function get_billing_phone() { return $this->phone; }
		public function get_billing_country() { return $this->country; }
		public function get_billing_email() { return $this->email; }
		public function get_billing_first_name() { return $this->first_name; }
		public function get_billing_last_name() { return $this->last_name; }
		public function get_billing_company() { return $this->company; }
		public function get_customer_ip_address() { return $this->ip; }
		public function get_payment_method() { return $this->pm; }
		public function get_status() { return $this->status; }
		public function update_status( $new_status, $note = '' ) {
			$this->status = $new_status;
			if ( '' !== $note ) {
				$this->notes[] = $note;
			}
		}
		public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
		public function get_meta( $k, $single = true ) { return isset( $this->meta[ $k ] ) ? $this->meta[ $k ] : ''; }
		public function add_order_note( $note ) { $this->notes[] = $note; }
		public function save() { return true; }
	}

	class Mock_Elementor_Ajax {
		public $errors = array();
		public function add_error( $id, $msg ) { $this->errors[ $id ] = $msg; }
	}

	require_once dirname( __DIR__ ) . '/proweblook-phone-validator.php';
	require_once dirname( __DIR__ ) . '/src/API/class-prop-countries.php';
	require_once dirname( __DIR__ ) . '/src/API/class-prop-api.php';
	require_once dirname( __DIR__ ) . '/src/API/class-prop-phone-validator.php';
	require_once dirname( __DIR__ ) . '/src/WC/class-prop-wc.php';
	require_once dirname( __DIR__ ) . '/src/Elementor/class-prop-elementor.php';
	require_once dirname( __DIR__ ) . '/src/CF7/class-prop-contactform7.php';
	require_once dirname( __DIR__ ) . '/src/Gravity/class-prop-gravity-forms.php';
	require_once dirname( __DIR__ ) . '/src/Ninja/class-prop-ninja-forms.php';
	require_once dirname( __DIR__ ) . '/src/Registration/class-prop-registration.php';
	require_once dirname( __DIR__ ) . '/src/class-prop-admin.php';

	PROP_Countries::populate();

	$passed = 0;
	$failed = 0;

	function assert_true( $cond, $label ) {
		global $passed, $failed;
		if ( $cond ) {
			$passed++;
			echo "  [PASS] {$label}\n";
		} else {
			$failed++;
			echo "  [FAIL] {$label}\n";
		}
	}

	echo "=== Running ProWebLook WordPress Plugin v2.1.0 Test Suite ===\n\n";

	assert_true( '2.1.0' === PROP_PLUGIN_CURRENT_VERSION, 'Plugin version constant bumped to 2.1.0' );

	// Configure HTTP Mocks across all 6 ProWebLook services + Balance API.
	$GLOBALS['_http_mock'] = array(
		'phonevalidation' => function ( $url, $args ) {
			$num = $args['body']['phone_number'];
			if ( '+14155552671' === $num || '9876543210' === $num ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status'           => 'VALID_CONFIRMED',
							'linetype'         => 'Mobile',
							'carrier'          => 'Reliance Jio',
							'countrycode'      => 'IN',
							'formatted_number' => '+91 98765 43210',
						)
					),
				);
			}
			if ( '+15005550006' === $num ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status'           => 'VALID_CONFIRMED',
							'linetype'         => 'VoIP',
							'carrier'          => 'Twilio VoIP',
							'countrycode'      => 'US',
							'formatted_number' => '+1 500 555 0006',
						)
					),
				);
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode( array( 'status' => 'INVALID' ) ),
			);
		},
		'wavalidation' => function ( $url, $args ) {
			if ( false !== strpos( $url, '9876543210' ) || false !== strpos( $url, '14155552671' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status'         => true,
							'numberstatus'   => true,
							'businessnumber' => true,
							'countrycode'    => 'IN',
						)
					),
				);
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'status'         => true,
						'numberstatus'   => false,
						'businessnumber' => false,
					)
				),
			);
		},
		'emailvalidation' => function ( $url, $args ) {
			if ( false !== strpos( $url, 'temp%4010minutemail.com' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status'      => true,
							'emailformat' => true,
							'emailexist'  => true,
							'verdict'     => 'risky',
							'reason_code' => 'disposable_email',
						)
					),
				);
			}
			if ( false !== strpos( $url, 'bounce%40example.com' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status'      => true,
							'emailformat' => true,
							'emailexist'  => false,
							'verdict'     => 'undeliverable',
							'reason_code' => 'mailbox_not_found',
						)
					),
				);
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'status'      => true,
						'emailformat' => true,
						'emailexist'  => true,
						'verdict'     => 'deliverable',
						'reason_code' => 'mailbox_accepted',
					)
				),
			);
		},
		'calleridvalidation' => function ( $url, $args ) {
			if ( false !== strpos( $url, '9876543210' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status'      => true,
							'caller_name' => 'CHANDAN DUBEY',
							'caller_type' => 'CONSUMER',
							'carrier'     => 'Reliance Jio',
							'linetype'    => 'Mobile',
							'countrycode' => 'IN',
						)
					),
				);
			}
			if ( false !== strpos( $url, '15005550006' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status'      => true,
							'caller_name' => 'ACME LOGISTICS LLC',
							'caller_type' => 'BUSINESS',
							'carrier'     => 'Twilio VoIP',
							'linetype'    => 'VoIP',
							'countrycode' => 'US',
						)
					),
				);
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode( array( 'status' => true, 'caller_name' => '' ) ),
			);
		},
		'ipapi.proweblook.com' => function ( $url, $args ) {
			if ( false !== strpos( $url, '159.89.10.20' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'ip'           => '159.89.10.20',
							'country_code' => 'US',
							'country_name' => 'United States',
							'city'         => 'New York',
							'isp'          => 'DigitalOcean, LLC',
							'asn'          => 'AS14061',
						)
					),
				);
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'ip'           => '49.36.120.10',
						'country_code' => 'IN',
						'country_name' => 'India',
						'city'         => 'Bengaluru',
						'isp'          => 'Reliance Jio Infocomm Limited',
						'asn'          => 'AS55836',
					)
				),
			);
		},
		'blacklistchecker' => function ( $url, $args ) {
			if ( false !== strpos( $url, 'spamcorp.biz' ) ) {
				return array(
					'response' => array( 'code' => 200 ),
					'body'     => json_encode(
						array(
							'status'        => true,
							'target'        => 'spamcorp.biz',
							'listed'        => true,
							'listed_count'  => 3,
							'total_checked' => 52,
							'listed_on'     => array( 'zen.spamhaus.org', 'bl.spamcop.net', 'b.barracudacentral.org' ),
						)
					),
				);
			}
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'status'        => true,
						'target'        => 'proweblook.com',
						'listed'        => false,
						'listed_count'  => 0,
						'total_checked' => 52,
						'listed_on'     => array(),
					)
				),
			);
		},
		'zapier/get_balance' => function ( $url, $args ) {
			return array(
				'response' => array( 'code' => 200 ),
				'body'     => json_encode(
					array(
						'ok'   => true,
						'data' => array(
							'account_email' => 'info@proweblook.com',
							'phone'         => array( 'amount' => 1250 ),
							'whatsapp'      => array( 'amount' => 3400 ),
							'email'         => array( 'amount' => 5000 ),
							'caller'        => array( 'amount' => 800 ),
							'blacklist'     => array( 'amount' => 1500 ),
						),
					)
				),
			);
		},
	);

	$api = new PROP_API();
	$api->set_apikey( 'pwl_live_test_key_123' );
	$validator = new PROP_Phone_Validator();
	$validator->set_api( $api );

	// 1. Phone Validation + 12h Transient Cache (zero double-billing).
	$validator->set_phone( '+14155552671' );
	assert_true( true === $validator->validate(), 'Valid E.164 mobile phone passes validation' );
	$calls_after_first = $GLOBALS['_http_calls'];
	$validator->set_phone( '+14155552671' );
	assert_true( true === $validator->validate(), 'Cached repeat phone validation passes' );
	assert_true( $calls_after_first === $GLOBALS['_http_calls'], 'Transient cache prevents duplicate HTTP call / double-billing' );

	// 2. VoIP Blocking.
	$validator->set_block_voip( false );
	$validator->set_phone( '+15005550006' );
	assert_true( true === $validator->validate(), 'VoIP number passes when VoIP blocking is disabled' );
	$validator->set_block_voip( true );
	$validator->set_phone( '+15005550006' );
	assert_true( false === $validator->validate(), 'VoIP number rejected when propp_block_voip is enabled' );
	assert_true( 'VOIP_BLOCKED' === $validator->get_phone_status(), 'Status set to VOIP_BLOCKED' );
	$validator->set_block_voip( false );

	// 3. Silent WhatsApp Validation.
	assert_true( true === $validator->validate_whatsapp( '9876543210', 'IN' ), 'Active WhatsApp Business number passes' );
	assert_true( 'business' === $validator->get_last_whatsapp_result()->account_type, 'Detects WhatsApp Business account type' );
	assert_true( false === $validator->validate_whatsapp( '+19990000000' ), 'Non-WhatsApp phone fails WhatsApp check' );

	// 4. Email Deliverability & Disposable Email Blocking.
	assert_true( true === $validator->validate_email_address( 'buyer@proweblook.com' ), 'Deliverable business email passes' );
	assert_true( false === $validator->validate_email_address( 'temp@10minutemail.com' ), 'Disposable 10minutemail blocked' );
	assert_true( false === $validator->validate_email_address( 'bounce@example.com' ), 'Undeliverable mailbox blocked' );

	// 5. v2.1.0: Caller ID (CNAM) Lookup & Fuzzy Subscriber Name-Match.
	$cnam_match = $validator->lookup_caller_id_signal( '9876543210', 'IN', 'Chandan Dubey', '' );
	assert_true( $cnam_match->ok && $cnam_match->found && 'CHANDAN DUBEY' === $cnam_match->caller_name, 'Caller ID CNAM resolves subscriber name' );
	assert_true( 'match' === $cnam_match->name_match, 'Caller ID fuzzy matcher returns match for exact subscriber name' );
	assert_true( 'match' === $validator->compare_caller_name( 'ACME LOGISTICS LLC', 'John Smith', 'Acme Logistics Inc.' ), 'Caller ID fuzzy matcher matches billing company ignoring LLC/Inc stop-words' );
	assert_true( 'mismatch' === $validator->compare_caller_name( 'CHANDAN DUBEY', 'Rahul Verma', '' ), 'Caller ID fuzzy matcher flags mismatch on completely different subscriber' );
	assert_true( 'unavailable' === $validator->compare_caller_name( '', 'Rahul Verma', '' ), 'Caller ID fuzzy matcher returns unavailable when CNAM record is empty' );

	// 6. v2.1.0: Edge GeoIP Country-Mismatch, VPN/Hosting Detection & Private IP Skip.
	$calls_before_priv_ip = $GLOBALS['_http_calls'];
	$priv_geo             = $validator->lookup_ip_geo_signal( '127.0.0.1', 'IN' );
	assert_true( ! empty( $priv_geo->skipped_private ) && $calls_before_priv_ip === $GLOBALS['_http_calls'], 'Edge GeoIP automatically skips loopback/private IPs with zero HTTP calls' );

	$clean_geo = $validator->lookup_ip_geo_signal( '49.36.120.10', 'IN' );
	assert_true( $clean_geo->ok && 'IN' === $clean_geo->country_code && false === $clean_geo->country_mismatch && false === $clean_geo->is_hosting_or_proxy, 'Edge GeoIP confirms matching residential Indian ISP' );

	$vpn_geo = $validator->lookup_ip_geo_signal( '159.89.10.20', 'IN' );
	assert_true( $vpn_geo->ok && 'US' === $vpn_geo->country_code && true === $vpn_geo->country_mismatch && true === $vpn_geo->is_hosting_or_proxy, 'Edge GeoIP detects US vs IN billing mismatch AND DigitalOcean datacenter/VPN ISP' );

	// 7. v2.1.0: Corporate Domain DNSBL Blacklist Check & Free Webmail Credit-Saver Skip.
	$calls_before_gmail = $GLOBALS['_http_calls'];
	assert_true( true === $validator->validate_domain_blacklist( 'shopper@gmail.com', true, true ), 'Consumer webmail (gmail.com) passes DNSBL check' );
	assert_true( ! empty( $validator->get_last_blacklist_result()->skipped_free_webmail ) && $calls_before_gmail === $GLOBALS['_http_calls'], 'Free webmail domain skips DNSBL API call to save merchant credits' );

	assert_true( true === $validator->validate_domain_blacklist( 'ceo@proweblook.com', true, true ), 'Clean corporate domain (proweblook.com) passes DNSBL check' );
	assert_true( true === $validator->validate_domain_blacklist( 'lead@spamcorp.biz', false, true ), 'Listed corporate domain passes when propp_block_dnsbl is disabled (flag-only mode)' );
	assert_true( false === $validator->validate_domain_blacklist( 'lead@spamcorp.biz', true, true ), 'Listed corporate domain (spamcorp.biz) is rejected when propp_block_dnsbl is enabled' );

	// 8. WooCommerce Classic Checkout: Trusted COD Order (95+ Trust Score) vs Prepaid Bypass.
	$wc = new PROP_WC();
	$wc->set_validator( $validator );
	$wc->set_options(
		array(
			'propp_auto_check'        => 1,
			'propp_wc_whatsapp_mode'  => 'cod_only',
			'propp_email_check'       => 1,
			'propp_dnsbl_check'       => 1,
			'propp_block_dnsbl'       => 0,
			'propp_wc_caller_id_mode' => 'cod_only',
			'propp_wc_ip_geo_check'   => 1,
			'propp_wc_autohold_mode'  => 'cod_review',
		)
	);

	// 8a: Prepaid Stripe order with non-WhatsApp valid mobile should PASS!
	$errs_stripe = new WP_Error();
	$wc->validate_checkout(
		array(
			'billing_phone'      => '+15005550006',
			'billing_country'    => 'US',
			'billing_email'      => 'buyer@proweblook.com',
			'billing_first_name' => 'John',
			'billing_last_name'  => 'Smith',
			'customer_ip'        => '159.89.10.20',
			'payment_method'     => 'stripe',
		),
		$errs_stripe
	);
	assert_true( empty( $errs_stripe->errors ), 'COD-only WhatsApp Guard skips WhatsApp check on prepaid Stripe orders' );

	// 8b: COD order with non-WhatsApp phone MUST BE BLOCKED!
	$errs_cod_bad = new WP_Error();
	$wc->validate_checkout(
		array(
			'billing_phone'   => '+15005550006',
			'billing_country' => 'US',
			'billing_email'   => 'buyer@proweblook.com',
			'payment_method'  => 'cod',
		),
		$errs_cod_bad
	);
	assert_true( isset( $errs_cod_bad->errors['proweblook_whatsapp_required'] ), 'COD order with non-WhatsApp phone is blocked by RTO Guard' );

	// 8c: Trusted COD order (Mobile + Active WhatsApp Business + Deliverable Email + Clean Corp DNSBL + Caller ID Match + Matching IN Residential IP).
	$errs_cod_trusted = new WP_Error();
	$wc->validate_checkout(
		array(
			'billing_phone'      => '9876543210',
			'billing_country'    => 'IN',
			'billing_email'      => 'buyer@proweblook.com',
			'billing_first_name' => 'Chandan',
			'billing_last_name'  => 'Dubey',
			'customer_ip'        => '49.36.120.10',
			'payment_method'     => 'cod',
		),
		$errs_cod_trusted
	);
	assert_true( empty( $errs_cod_trusted->errors ), 'Trusted 6-signal COD order passes validation' );

	$trusted_order = new Mock_WC_Order();
	$wc->attach_order_meta_classic( $trusted_order );
	assert_true( (int) $trusted_order->get_meta( '_proweblook_trust_score' ) >= 95, 'Trusted 6-signal COD order achieves >= 95/100 Trust Score' );
	assert_true( 'trusted' === $trusted_order->get_meta( '_proweblook_risk_level' ), 'Trusted order marked risk_level = trusted' );
	assert_true( '' === $trusted_order->get_meta( '_proweblook_autohold_pending' ), 'Trusted COD order is NOT flagged for Auto-Hold' );
	assert_true( 'CHANDAN DUBEY' === $trusted_order->get_meta( '_proweblook_caller_name' ) && 'match' === $trusted_order->get_meta( '_proweblook_caller_match' ), 'HPOS meta _proweblook_caller_name & _proweblook_caller_match saved' );
	assert_true( 'IN' === $trusted_order->get_meta( '_proweblook_ip_country' ) && 'no' === $trusted_order->get_meta( '_proweblook_ip_mismatch' ), 'HPOS meta _proweblook_ip_country & _proweblook_ip_mismatch saved' );
	assert_true( 'no' === $trusted_order->get_meta( '_proweblook_dnsbl_listed' ), 'HPOS meta _proweblook_dnsbl_listed = no saved for clean corporate domain' );
	assert_true( 'processing' === $wc->filter_cod_order_status( 'processing', $trusted_order ), 'Trusted COD order retains default processing status' );

	// 9. v2.1.0: WooCommerce Fraud Auto-Hold (`cod_review`) on Risky COD Order!
	// Phone + WhatsApp + Email pass, BUT Caller ID mismatches ('Rahul Fraudster' vs 'CHANDAN DUBEY'),
	// shopper IP is a US DigitalOcean VPN while Billing Country is IN, AND email domain is listed on 3 DNSBLs!
	$errs_cod_risky = new WP_Error();
	$wc->validate_checkout(
		array(
			'billing_phone'      => '9876543210',
			'billing_country'    => 'IN',
			'billing_email'      => 'lead@spamcorp.biz',
			'billing_first_name' => 'Rahul',
			'billing_last_name'  => 'Fraudster',
			'customer_ip'        => '159.89.10.20',
			'payment_method'     => 'cod',
		),
		$errs_cod_risky
	);
	assert_true( empty( $errs_cod_risky->errors ), 'Risky COD order with non-blocking DNSBL passes checkout gate for Auto-Hold capture' );

	$risky_order     = new Mock_WC_Order();
	$risky_order->pm = 'cod';
	$wc->attach_order_meta_classic( $risky_order );
	$risky_score = (int) $risky_order->get_meta( '_proweblook_trust_score' );
	assert_true( $risky_score < 45 && 'high_risk' === $risky_order->get_meta( '_proweblook_risk_level' ), "Risky COD order receives High Risk Trust Score ({$risky_score}/100)" );
	assert_true( 'yes' === $risky_order->get_meta( '_proweblook_autohold_pending' ), 'Risky COD order flagged with _proweblook_autohold_pending = yes' );
	assert_true( 'yes' === $risky_order->get_meta( '_proweblook_ip_mismatch' ) && 'yes' === $risky_order->get_meta( '_proweblook_ip_proxy' ), 'IP country mismatch + VPN/Hosting meta persisted' );
	assert_true( 'yes' === $risky_order->get_meta( '_proweblook_dnsbl_listed' ) && 3 === (int) $risky_order->get_meta( '_proweblook_dnsbl_count' ), 'DNSBL listed count (3) persisted to HPOS meta' );
	assert_true( 'on-hold' === $wc->filter_cod_order_status( 'processing', $risky_order ), 'filter_cod_order_status() intercepts COD gateway status to on-hold' );

	// Also verify maybe_apply_autohold() transitions a processing order to on-hold and writes Fraud Guard note.
	$risky_order->status = 'processing';
	$wc->maybe_apply_autohold( $risky_order );
	assert_true( 'on-hold' === $risky_order->get_status(), 'maybe_apply_autohold() transitions risky order status to on-hold' );
	assert_true( false !== strpos( end( $risky_order->notes ), 'ProWebLook Fraud Guard placed this order On Hold' ), 'Fraud Guard explanation note attached to order' );

	// 10. v2.1.0: WooCommerce HPOS Orders Column & Admin Order Badge HTML rendering.
	$cols = $wc->register_orders_trust_column( array( 'order_number' => 'Order', 'order_status' => 'Status', 'order_total' => 'Total' ) );
	assert_true( isset( $cols['proweblook_trust'] ), 'ProWebLook Trust column registered in WooCommerce Orders table' );
	ob_start();
	$wc->render_orders_trust_column_hpos( 'proweblook_trust', $risky_order );
	$pill_html = ob_get_clean();
	assert_true( false !== strpos( $pill_html, 'High Risk' ) && false !== strpos( $pill_html, '/100' ), 'HPOS Orders column renders High Risk Trust Score pill' );

	ob_start();
	$wc->render_admin_order_badge( $trusted_order );
	$badge_html = ob_get_clean();
	assert_true( false !== strpos( $badge_html, 'CHANDAN DUBEY' ) && false !== strpos( $badge_html, 'Shopper IP Geo:' ), 'Admin Order Details Trust Signals card renders Caller ID & IP Geo lines' );

	// 11. WooCommerce Block Checkout (Store API) throws RouteException on bad COD WhatsApp.
	$block_order          = new Mock_WC_Order();
	$block_order->phone   = '+15005550006';
	$block_order->country = 'US';
	$block_order->email   = 'buyer@proweblook.com';
	$block_order->pm      = 'cod';
	$threw_route_ex       = false;
	try {
		$wc->validate_and_enrich_block_checkout( $block_order, null );
	} catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $ex ) {
		$threw_route_ex = ( 'proweblook_checkout_validation_error' === $ex->error_code );
	}
	assert_true( $threw_route_ex, 'WooCommerce Block Checkout throws Store API RouteException on failed COD WhatsApp check' );

	// 12. Elementor Pro Forms validation (Disposable Email + Standalone Corporate DNSBL Block).
	$el = new PROP_Elementor();
	$el->set_validator( $validator );
	$el->set_options( array( 'propp_email_check' => 1 ) );
	$el_ajax = new Mock_Elementor_Ajax();
	$el->validate_email( array( 'id' => 'work_email', 'value' => 'temp@10minutemail.com' ), null, $el_ajax );
	assert_true( isset( $el_ajax->errors['work_email'] ), 'Elementor Pro Form blocks disposable email with inline field error' );

	$el->set_options( array( 'propp_email_check' => 0, 'propp_dnsbl_check' => 1, 'propp_block_dnsbl' => 1 ) );
	$el_ajax_dnsbl = new Mock_Elementor_Ajax();
	$el->validate_email( array( 'id' => 'corp_email', 'value' => 'sales@spamcorp.biz' ), null, $el_ajax_dnsbl );
	assert_true( isset( $el_ajax_dnsbl->errors['corp_email'] ), 'Elementor Pro Form blocks DNSBL-blacklisted corporate domain in standalone DNSBL mode' );

	// 13. WordPress Registration Guard.
	$validator->set_check_dnsbl( true );
	$validator->set_block_dnsbl( true );
	$reg    = new PROP_Registration();
	$reg->set_validator( $validator );
	$reg_er = $reg->validate_wp_registration( new WP_Error(), 'spammer', 'temp@10minutemail.com' );
	assert_true( isset( $reg_er->errors['proweblook_invalid_email'] ), 'WP Core Registration blocks disposable signup email' );
	$reg_dnsbl_er = $reg->validate_wp_registration( new WP_Error(), 'b2bspam', 'bot@spamcorp.biz' );
	assert_true( isset( $reg_dnsbl_er->errors['proweblook_invalid_email'] ), 'WP Core Registration blocks DNSBL-listed corporate domain signup' );

	// 14. Unified 5-Product Credit Balance Fetch & v2.1.0 Settings Sanitizer.
	$bals = $api->get_balances( true );
	assert_true(
		$bals['ok'] && 1250 === $bals['phone'] && 3400 === $bals['whatsapp'] && 5000 === $bals['email'] && 800 === $bals['caller'] && 1500 === $bals['blacklist'],
		'Unified 5-product credit balance (Phone, WhatsApp, Email, Caller ID, Blacklist) parsed accurately'
	);

	$admin     = PROP_Admin::get_instance();
	$sanitized = $admin->sanitize_settings(
		array(
			'propp_api_key'           => ' pwl_key_abc!<script> ',
			'propp_country'           => 'in',
			'propp_wc_whatsapp_mode'  => 'cod_only',
			'propp_wc_caller_id_mode' => 'cod_only',
			'propp_wc_autohold_mode'  => 'cod_review',
			'propp_wc_ip_geo_check'   => '1',
			'propp_dnsbl_check'       => '1',
			'propp_block_dnsbl'       => '1',
			'propp_auto_check'        => '1',
			'propp_block_voip'        => '',
		)
	);
	assert_true( 'pwl_key_abcscript' === $sanitized['propp_api_key'], 'Sanitizer strips unsafe characters from API key' );
	assert_true( 'IN' === $sanitized['propp_country'], 'Sanitizer normalizes valid ISO country code' );
	assert_true( 'cod_only' === $sanitized['propp_wc_whatsapp_mode'], 'Sanitizer preserves valid WhatsApp COD Guard mode' );
	assert_true( 'cod_only' === $sanitized['propp_wc_caller_id_mode'] && 'cod_review' === $sanitized['propp_wc_autohold_mode'], 'Sanitizer preserves Caller ID CNAM mode and Fraud Auto-Hold mode' );
	assert_true( 1 === $sanitized['propp_wc_ip_geo_check'] && 1 === $sanitized['propp_dnsbl_check'] && 1 === $sanitized['propp_block_dnsbl'], 'Sanitizer normalizes v2.1.0 GeoIP and DNSBL checkboxes to 0/1 ints' );
	assert_true( 1 === $sanitized['propp_auto_check'] && 0 === $sanitized['propp_block_voip'], 'Sanitizer normalizes core checkboxes to 0/1 ints' );

	echo "\nSummary: {$passed} passed, {$failed} failed.\n";
	exit( $failed > 0 ? 1 : 0 );
}
