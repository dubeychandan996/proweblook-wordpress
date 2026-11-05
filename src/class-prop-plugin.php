<?php
/**
 * Main plugin bootstrap & dependency coordinator.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_Plugin
 */
class PROP_Plugin {

	/**
	 * Whether WordPress debug mode is enabled.
	 *
	 * @var bool|null
	 */
	protected static $debug = null;

	/**
	 * Singleton instance.
	 *
	 * @var PROP_Plugin|null
	 */
	protected static $instance = null;

	/**
	 * Plugin base URL (with trailing slash).
	 *
	 * @var string
	 */
	public $plugin_url = '';

	/**
	 * Plugin relative directory path.
	 *
	 * @var string
	 */
	public $plugin_path = '';

	/**
	 * API client instance.
	 *
	 * @var PROP_API|null
	 */
	private $api = null;

	/**
	 * Validator engine instance.
	 *
	 * @var PROP_Phone_Validator|null
	 */
	private $validator = null;

	/**
	 * Plugin options array.
	 *
	 * @var array
	 */
	private $options = array();

	/**
	 * Retrieve singleton instance.
	 *
	 * @return PROP_Plugin
	 */
	public static function get_instance() {
		if ( null === self::$debug ) {
			self::$debug = ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		}

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Wire up API, AJAX, form integrations, WooCommerce, and Admin UI.
	 *
	 * @return void
	 */
	public function plugin_setup() {
		$this->plugin_url  = plugins_url( '/', dirname( __FILE__ ) );
		$this->plugin_path = dirname( plugin_basename( dirname( __FILE__ ) ) ) . '/';
		$this->load_language( 'proweblook-phone-validator' );

		$this->set_options( get_option( 'propp_settings', array() ) );

		// Load API & Validator Engine.
		require_once dirname( __FILE__ ) . '/API/class-prop-countries.php';
		require_once dirname( __FILE__ ) . '/API/class-prop-api.php';
		require_once dirname( __FILE__ ) . '/API/class-prop-phone-validator.php';

		PROP_Countries::populate();

		$this->api = new PROP_API();
		$this->api->set_apikey( (string) $this->get_option( 'propp_api_key' ) );
		$this->api->set_country_code( (string) $this->get_option( 'propp_country' ) );

		$this->validator = new PROP_Phone_Validator();
		$this->validator->set_api( $this->api );
		$this->validator->set_block_voip( 1 === (int) $this->get_option( 'propp_block_voip' ) );
		$this->validator->set_block_disposable_email( 1 === (int) $this->get_option( 'propp_block_disposable' ) );
		$this->validator->set_check_dnsbl( 1 === (int) $this->get_option( 'propp_dnsbl_check' ) );
		$this->validator->set_block_dnsbl( 1 === (int) $this->get_option( 'propp_block_dnsbl' ) );

		// Load AJAX Handlers (frontend .propp-phone + WP Admin live test).
		require_once dirname( __FILE__ ) . '/AJAX/class-prop-ajax-handler.php';
		require_once dirname( __FILE__ ) . '/AJAX/class-prop-ajax-validate-phone.php';

		$ajax_handler = new PROP_Ajax_Handler();
		$ajax         = new PROP_Ajax_Validate_Phone();
		$ajax->set_validator( $this->validator );
		$ajax->set_handler( $ajax_handler );

		/**
		 * Filters whether the `.propp-phone` AJAX action is restricted to logged-in users.
		 * Default: TRUE (call `PROP_activate_third_party()` to enable public frontend forms).
		 *
		 * @param bool $is_private Whether `wp_ajax_nopriv_` is disabled.
		 */
		$ajax_is_private = (bool) apply_filters( 'PROP_api_is_private', true );
		$ajax->set_private( $ajax_is_private );
		$ajax->register();

		$opts = $this->get_options();

		// WooCommerce Integration (Classic Checkout, Block Checkout, HPOS Meta, COD WhatsApp Guard, Caller ID, GeoIP, Auto-Hold).
		$wc_active_checks = ( 1 === (int) $this->get_option( 'propp_auto_check' ) )
			|| ( 'off' !== (string) $this->get_option( 'propp_wc_whatsapp_mode' ) && '' !== (string) $this->get_option( 'propp_wc_whatsapp_mode' ) )
			|| ( 'off' !== (string) $this->get_option( 'propp_wc_caller_id_mode' ) && '' !== (string) $this->get_option( 'propp_wc_caller_id_mode' ) )
			|| ( 1 === (int) $this->get_option( 'propp_wc_ip_geo_check' ) )
			|| ( 1 === (int) $this->get_option( 'propp_dnsbl_check' ) )
			|| ( 1 === (int) $this->get_option( 'propp_whatsapp_check' ) )
			|| ( 1 === (int) $this->get_option( 'propp_email_check' ) );

		if ( class_exists( 'WooCommerce' ) && ( $wc_active_checks || is_admin() ) ) {
			require_once dirname( __FILE__ ) . '/WC/class-prop-wc.php';
			$wc = new PROP_WC();
			$wc->set_validator( $this->validator );
			$wc->set_options( $opts );
			$wc->setup();
		}

		// Elementor Pro Forms Integration.
		if ( 1 === (int) $this->get_option( 'propp_elementor_check' ) ) {
			require_once dirname( __FILE__ ) . '/Elementor/class-prop-elementor.php';
			$elementor = new PROP_Elementor();
			$elementor->set_validator( $this->validator );
			$elementor->set_options( $opts );
			$elementor->setup();
		}

		// Contact Form 7 Integration.
		if ( 1 === (int) $this->get_option( 'propp_cf7_check' ) && defined( 'WPCF7_VERSION' ) ) {
			require_once dirname( __FILE__ ) . '/CF7/class-prop-contactform7.php';
			$cf7 = new PROP_ContactForm7();
			$cf7->set_validator( $this->validator );
			$cf7->set_options( $opts );
			$cf7->setup();
		}

		// Gravity Forms Integration.
		if ( 1 === (int) $this->get_option( 'propp_gravity_check' ) ) {
			require_once dirname( __FILE__ ) . '/Gravity/class-prop-gravity-forms.php';
			$gf = new PROP_GravityForms();
			$gf->set_validator( $this->validator );
			$gf->set_options( $opts );
			$gf->setup();
		}

		// WPForms Integration.
		if ( 1 === (int) $this->get_option( 'propp_wpforms_check' ) ) {
			require_once dirname( __FILE__ ) . '/WPForms/class-prop-wpforms.php';
			$wpf = new PROP_WPForms();
			$wpf->set_validator( $this->validator );
			$wpf->set_options( $opts );
			$wpf->setup();
		}

		// Ninja Forms Integration.
		if ( 1 === (int) $this->get_option( 'propp_ninja_check' ) ) {
			require_once dirname( __FILE__ ) . '/Ninja/class-prop-ninja-forms.php';
			$nf = new PROP_NinjaForms();
			$nf->set_validator( $this->validator );
			$nf->set_options( $opts );
			$nf->setup();
		}

		// WordPress & WooCommerce User Registration Email Guard.
		if ( 1 === (int) $this->get_option( 'propp_registration_check' ) ) {
			require_once dirname( __FILE__ ) . '/Registration/class-prop-registration.php';
			$reg = new PROP_Registration();
			$reg->set_validator( $this->validator );
			$reg->setup();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'register_script' ) );

		if ( is_admin() ) {
			require_once dirname( __FILE__ ) . '/class-prop-admin.php';
			$admin = PROP_Admin::get_instance();
			$admin->load();
		}
	}

	/**
	 * Register frontend `.propp-phone` validation script.
	 *
	 * @return void
	 */
	public function register_script() {
		$min = ( ! self::$debug ) ? '.min' : '';
		wp_register_script(
			'propp_frontend_script',
			$this->get_plugin_url() . 'assets/js/propp_form_script' . $min . '.js',
			array( 'jquery', 'underscore' ),
			PROP_PLUGIN_CURRENT_VERSION,
			true
		);

		$js_vars        = $this->js_localization();
		$js_vars['tpl'] = '<span class="propp-error"><%- status %> <%- phone %></span>';

		wp_localize_script( 'propp_frontend_script', 'propp', $js_vars );
	}

	/**
	 * Enqueue frontend script (`PROP_activate_third_party()`).
	 *
	 * @return void
	 */
	public function enqueue_frontend() {
		wp_enqueue_script( 'propp_frontend_script' );
	}

	/**
	 * Render inline error style on custom `.propp-phone` forms.
	 *
	 * @return void
	 */
	public function footer_styles() {
		echo '<style>.propp-error{color:#d63638;display:block;font-size:0.875em;margin-top:4px;}</style>';
	}

	/**
	 * Return API client instance.
	 *
	 * @return PROP_API|null
	 */
	public function get_api() {
		return $this->api;
	}

	/**
	 * Return validator engine instance.
	 *
	 * @return PROP_Phone_Validator|null
	 */
	public function get_validator() {
		return $this->validator;
	}

	/**
	 * Check debug mode.
	 *
	 * @return bool
	 */
	public function is_debug() {
		return (bool) self::$debug;
	}

	/**
	 * Return plugin base URL.
	 *
	 * @return string
	 */
	public function get_plugin_url() {
		return $this->plugin_url;
	}

	/**
	 * Return plugin relative path.
	 *
	 * @return string
	 */
	public function get_plugin_path() {
		return $this->plugin_path;
	}

	/**
	 * Localized JS variables.
	 *
	 * @return array
	 */
	public function js_localization() {
		return array(
			'AJAX_URL'                    => admin_url( 'admin-ajax.php' ),
			'nonce'                       => wp_create_nonce( 'validate-phone' ),
			'admin_nonce'                 => current_user_can( 'manage_options' ) ? wp_create_nonce( 'prop_admin_action' ) : '',
			'VALID_CONFIRMED'             => __( 'Valid Confirmed', 'proweblook-phone-validator' ),
			'VALID_UNCONFIRMED'           => __( 'Valid Unconfirmed', 'proweblook-phone-validator' ),
			'VOIP_BLOCKED'                => __( 'VoIP Blocked', 'proweblook-phone-validator' ),
			'INVALID'                     => __( 'Invalid', 'proweblook-phone-validator' ),
			'DELAYED'                     => __( 'Validation Delayed', 'proweblook-phone-validator' ),
			'RATE_LIMIT_EXCEEDED'         => __( 'Rate Limit Exceeded', 'proweblook-phone-validator' ),
			'API_KEY_INVALID_OR_DEPLETED' => __( 'API Key Invalid or Depleted', 'proweblook-phone-validator' ),
			'UNKNOWN'                     => __( 'Phone Number Missing', 'proweblook-phone-validator' ),
			800                           => __( 'Please enter a value to validate.', 'proweblook-phone-validator' ),
			801                           => __( 'Service temporarily unavailable.', 'proweblook-phone-validator' ),
		);
	}

	/**
	 * Return options array.
	 *
	 * @return array
	 */
	public function get_options() {
		return $this->options;
	}

	/**
	 * Populate options merged with defaults.
	 *
	 * @param array $options Stored option array.
	 * @return array
	 */
	public function set_options( $options ) {
		$defaults      = array(
			'propp_api_key'            => '',
			'propp_country'            => '',
			'propp_block_voip'         => 0,
			'propp_whatsapp_check'     => 0,
			'propp_wc_whatsapp_mode'   => 'off',
			'propp_email_check'        => 0,
			'propp_block_disposable'   => 1,
			'propp_dnsbl_check'        => 0,
			'propp_block_dnsbl'        => 0,
			'propp_wc_caller_id_mode'  => 'off',
			'propp_wc_ip_geo_check'    => 0,
			'propp_wc_autohold_mode'   => 'off',
			'propp_registration_check' => 0,
			'propp_auto_check'         => 0,
			'propp_elementor_check'    => 0,
			'propp_cf7_check'          => 0,
			'propp_gravity_check'      => 0,
			'propp_wpforms_check'      => 0,
			'propp_ninja_check'        => 0,
		);
		$this->options = wp_parse_args( (array) $options, $defaults );
		return $this->get_options();
	}

	/**
	 * Retrieve a single option value.
	 *
	 * @param string $option_key Option key.
	 * @return mixed|null
	 */
	public function get_option( $option_key ) {
		return isset( $this->options[ $option_key ] ) ? $this->options[ $option_key ] : null;
	}

	/**
	 * Protected constructor.
	 */
	protected function __construct() {}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Load plugin translations.
	 *
	 * @param string $domain Text domain.
	 * @return void
	 */
	public function load_language( $domain ) {
		load_plugin_textdomain(
			$domain,
			false,
			$this->plugin_path . 'languages/'
		);
	}
}
