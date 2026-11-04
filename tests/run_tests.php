<?php
/**
 * Standalone Contract & Integration Test Suite for ProWebLook WordPress Plugin v2.0.0.
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
	return new WP_Error( 'http_err', 'No mock matched' );
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
		public $meta  = array();
		public $notes = array();
		public $phone = '';
		public $email = '';
		public $country = 'IN';
		public $pm = 'cod';
		public function get_billing_phone() { return $this->phone; }
		public function get_billing_country() { return $this->country; }
		public function get_billing_email() { return $this->email; }
		public function get_payment_method() { return $this->pm; }
		public function update_meta_data( $k, $v ) { $this->meta[ $k ] = $v; }
		public function get_meta( $k, $single = true ) { return isset( $this->meta[ $k ] ) ? $this->meta[ $k ] : ''; }
		public function add_order_note( $note ) { $this->notes[] = $note; }
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

	echo "=== Running ProWebLook WordPress Plugin v2.0.0 Test Suite ===\n\n";

	// Configure HTTP Mocks.
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
	$calls_before = $GLOBALS['_http_calls'];
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

	// 3. Silent WhatsApp Validation.
	assert_true( true === $validator->validate_whatsapp( '9876543210', 'IN' ), 'Active WhatsApp Business number passes' );
	assert_true( 'business' === $validator->get_last_whatsapp_result()->account_type, 'Detects WhatsApp Business account type' );
	assert_true( false === $validator->validate_whatsapp( '+19990000000' ), 'Non-WhatsApp phone fails WhatsApp check' );

	// 4. Email Deliverability & Disposable Email Blocking.
	assert_true( true === $validator->validate_email_address( 'buyer@proweblook.com' ), 'Deliverable business email passes' );
	assert_true( false === $validator->validate_email_address( 'temp@10minutemail.com' ), 'Disposable 10minutemail blocked' );
	assert_true( false === $validator->validate_email_address( 'bounce@example.com' ), 'Undeliverable mailbox blocked' );

	// 5. WooCommerce Classic Checkout COD RTO Guard & Prepaid Bypass.
	$wc = new PROP_WC();
	$wc->set_validator( $validator );
	$wc->set_options(
		array(
			'propp_auto_check'       => 1,
			'propp_wc_whatsapp_mode' => 'cod_only',
			'propp_email_check'      => 1,
		)
	);

	// 5a: Prepaid Stripe order with non-WhatsApp valid mobile should PASS!
	$GLOBALS['_http_mock']['phonevalidation_prepaid'] = true;
	$validator->set_block_voip( false );
	$errs_stripe = new WP_Error();
	$wc->validate_checkout(
		array(
			'billing_phone'   => '+15005550006', // valid phone, NOT on WhatsApp
			'billing_country' => 'US',
			'billing_email'   => 'buyer@proweblook.com',
			'payment_method'  => 'stripe',
		),
		$errs_stripe
	);
	assert_true( empty( $errs_stripe->errors ), 'COD-only WhatsApp Guard skips WhatsApp check on prepaid Stripe orders' );

	// 5b: COD order with non-WhatsApp phone MUST BE BLOCKED!
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

	// 5c: COD order with active WhatsApp phone + deliverable email PASSES and writes HPOS Order Meta & Note!
	$errs_cod_good = new WP_Error();
	$wc->validate_checkout(
		array(
			'billing_phone'   => '9876543210',
			'billing_country' => 'IN',
			'billing_email'   => 'buyer@proweblook.com',
			'payment_method'  => 'cod',
		),
		$errs_cod_good
	);
	assert_true( empty( $errs_cod_good->errors ), 'Valid COD order with active WhatsApp + deliverable email passes' );

	$order = new Mock_WC_Order();
	$wc->attach_order_meta_classic( $order );
	assert_true( 'VALID_CONFIRMED' === $order->get_meta( '_proweblook_phone_status' ), 'HPOS meta _proweblook_phone_status saved' );
	assert_true( 'Reliance Jio' === $order->get_meta( '_proweblook_phone_carrier' ), 'HPOS meta _proweblook_phone_carrier saved' );
	assert_true( 'active' === $order->get_meta( '_proweblook_whatsapp_status' ), 'HPOS meta _proweblook_whatsapp_status saved' );
	assert_true( 'business' === $order->get_meta( '_proweblook_whatsapp_type' ), 'HPOS meta _proweblook_whatsapp_type saved' );
	assert_true( 'deliverable' === $order->get_meta( '_proweblook_email_verdict' ), 'HPOS meta _proweblook_email_verdict saved' );
	assert_true( count( $order->notes ) === 1 && false !== strpos( $order->notes[0], 'WhatsApp: Active (Business)' ), 'Merchant order note attached cleanly' );

	// 6. WooCommerce Block Checkout (Store API) throws RouteException on bad COD WhatsApp.
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

	// 7. Elementor Pro Forms validation.
	$el = new PROP_Elementor();
	$el->set_validator( $validator );
	$el->set_options( array( 'propp_email_check' => 1 ) );
	$el_ajax = new Mock_Elementor_Ajax();
	$el->validate_email( array( 'id' => 'work_email', 'value' => 'temp@10minutemail.com' ), null, $el_ajax );
	assert_true( isset( $el_ajax->errors['work_email'] ), 'Elementor Pro Form blocks disposable email with inline field error' );

	// 8. WordPress Registration Guard.
	$reg    = new PROP_Registration();
	$reg->set_validator( $validator );
	$reg_er = $reg->validate_wp_registration( new WP_Error(), 'spammer', 'temp@10minutemail.com' );
	assert_true( isset( $reg_er->errors['proweblook_invalid_email'] ), 'WP Core Registration blocks disposable signup email' );

	// 9. Unified Credit Balance Fetch & Settings Sanitizer.
	$bals = $api->get_balances( true );
	assert_true( $bals['ok'] && 1250 === $bals['phone'] && 3400 === $bals['whatsapp'] && 5000 === $bals['email'], 'Unified 3-product credit balance parsed accurately' );

	$admin     = PROP_Admin::get_instance();
	$sanitized = $admin->sanitize_settings(
		array(
			'propp_api_key'          => ' pwl_key_abc!<script> ',
			'propp_country'          => 'in',
			'propp_wc_whatsapp_mode' => 'cod_only',
			'propp_auto_check'       => '1',
			'propp_block_voip'       => '',
		)
	);
	assert_true( 'pwl_key_abcscript' === $sanitized['propp_api_key'], 'Sanitizer strips unsafe characters from API key' );
	assert_true( 'IN' === $sanitized['propp_country'], 'Sanitizer normalizes valid ISO country code' );
	assert_true( 'cod_only' === $sanitized['propp_wc_whatsapp_mode'], 'Sanitizer preserves valid WhatsApp COD Guard mode' );
	assert_true( 1 === $sanitized['propp_auto_check'] && 0 === $sanitized['propp_block_voip'], 'Sanitizer normalizes checkboxes to 0/1 ints' );

	echo "\nSummary: {$passed} passed, {$failed} failed.\n";
	exit( $failed > 0 ? 1 : 0 );
}
