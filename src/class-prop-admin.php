<?php
/**
 * WordPress Admin Settings Page, Sanitizer, Credit Balance Dashboard & Live Test Tool.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PROP_Admin
 */
class PROP_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var PROP_Admin|null
	 */
	protected static $instance = null;

	/**
	 * Retrieve singleton instance.
	 *
	 * @return PROP_Admin
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
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
	 * Register WP Admin hooks.
	 *
	 * @return void
	 */
	public function load() {
		if (
			current_user_can( 'manage_options' ) &&
			! empty( $_GET['prop-api-key-notice'] ) &&
			wp_verify_nonce( sanitize_key( wp_unslash( $_GET['prop-api-key-notice'] ) ), 'remove-api-key-notice' )
		) {
			update_option( 'prop-api-key-invalid', 0 );
		}

		if ( 1 === (int) get_option( 'prop-api-key-invalid' ) ) {
			add_action( 'admin_notices', array( $this, 'show_notice' ) );
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		if ( defined( 'PROP_PLUGIN_BASENAME' ) ) {
			add_filter( 'plugin_action_links_' . PROP_PLUGIN_BASENAME, array( $this, 'add_plugin_action_links' ) );
		}
	}

	/**
	 * Add quick Settings link on Plugins page.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_plugin_action_links( $links ) {
		$settings_url  = admin_url( 'options-general.php?page=Proweblook_phone_validator' );
		$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'proweblook-phone-validator' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	/**
	 * Display admin alert if API key was rejected or ran out of credits.
	 *
	 * @return void
	 */
	public function show_notice() {
		$url = add_query_arg(
			array(
				'page'                => 'Proweblook_phone_validator',
				'prop-api-key-notice' => wp_create_nonce( 'remove-api-key-notice' ),
			),
			admin_url( 'options-general.php' )
		);
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'ProWebLook Validator:', 'proweblook-phone-validator' ); ?></strong>
				<?php esc_html_e( 'Your ProWebLook API Key appears invalid or out of credits. Checkouts are failing open so customers are not blocked.', 'proweblook-phone-validator' ); ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Open Settings & Dismiss Notice', 'proweblook-phone-validator' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Register Settings submenu item (keeps legacy slug `Proweblook_phone_validator` so bookmarks work).
	 *
	 * @return void
	 */
	public function admin_menu() {
		add_options_page(
			__( 'ProWebLook Phone, WhatsApp & Email Validator', 'proweblook-phone-validator' ),
			__( 'ProWebLook Validator', 'proweblook-phone-validator' ),
			'manage_options',
			'Proweblook_phone_validator',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Enqueue Admin CSS & JS only on our settings screen.
	 *
	 * @param string $hook Current admin screen hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( 'settings_page_Proweblook_phone_validator' !== $hook ) {
			return;
		}

		$plugin     = PROP_Plugin::get_instance();
		$plugin_url = $plugin->get_plugin_url();
		$min        = ( ! $plugin->is_debug() ) ? '.min' : '';
		$ver        = defined( 'PROP_PLUGIN_CURRENT_VERSION' ) ? PROP_PLUGIN_CURRENT_VERSION : '2.0.0';

		wp_register_style(
			'propp_main_style',
			$plugin_url . 'assets/css/propp_style' . $min . '.css',
			array(),
			$ver
		);

		wp_register_script(
			'propp_main_script',
			$plugin_url . 'assets/js/propp_script' . $min . '.js',
			array( 'jquery' ),
			$ver,
			true
		);

		wp_localize_script( 'propp_main_script', 'propp', $plugin->js_localization() );
		wp_enqueue_style( 'propp_main_style' );
		wp_enqueue_script( 'propp_main_script' );
	}

	/**
	 * Register settings, sanitization callback, sections, and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'Proweblook_phone_validator',
			'propp_settings',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);

		// Section 1: API & Global Rules.
		add_settings_section(
			'propp_section_api',
			__( '1. API Connection & Protection Rules', 'proweblook-phone-validator' ),
			array( $this, 'render_section_api' ),
			'Proweblook_phone_validator'
		);

		add_settings_field(
			'propp_api_key',
			__( 'ProWebLook API Key', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_api',
			array(
				'type'        => 'password_text',
				'key'         => 'propp_api_key',
				'default'     => '',
				'placeholder' => __( 'Paste your API Key from proweblook.com/user/my_profile', 'proweblook-phone-validator' ),
				'desc'        => sprintf(
					/* translators: %s: signup URL */
					__( 'Free account includes 50 Phone + 50 WhatsApp + 50 Email credits (never expire). <a href="%s" target="_blank" rel="noopener noreferrer">Get your free API key &rarr;</a>', 'proweblook-phone-validator' ),
					'https://proweblook.com/auth/signup'
				),
			)
		);

		add_settings_field(
			'propp_country',
			__( 'Default Country Hint', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_api',
			array(
				'type'    => 'select_country',
				'key'     => 'propp_country',
				'default' => '',
				'desc'    => __( 'Used when visitors type a local phone number without a +country code (WooCommerce automatically uses the customer\'s Billing Country).', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_block_voip',
			__( 'Block VoIP & Virtual Numbers', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_api',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_block_voip',
				'default' => 0,
				'label'   => __( 'Reject burner / VoIP phone numbers (accept real Mobile & Fixed-Line carriers only)', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_email_check',
			__( 'Verify Email Deliverability', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_api',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_email_check',
				'default' => 0,
				'label'   => __( 'Validate email MX & mailbox existence across enabled forms and WooCommerce Checkout', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_block_disposable',
			__( 'Block Disposable Emails', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_api',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_block_disposable',
				'default' => 1,
				'label'   => __( 'Reject temporary / throwaway 10-minute email domains when email verification runs', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_dnsbl_check',
			__( 'Domain DNSBL Blacklist Check', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_api',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_dnsbl_check',
				'default' => 0,
				'label'   => __( 'Screen corporate & custom email domains against real-time DNSBL spam/malware blocklists (skips Gmail, Yahoo, Outlook & consumer webmail)', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_block_dnsbl',
			__( 'Block Blacklisted Domains', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_api',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_block_dnsbl',
				'default' => 0,
				'label'   => __( 'Reject form & checkout submissions when an email domain is listed on DNSBL blacklists (leave unchecked to flag in Trust Score only)', 'proweblook-phone-validator' ),
			)
		);

		// Section 2: WooCommerce & Form Integrations.
		add_settings_section(
			'propp_section_integrations',
			__( '2. WooCommerce COD Guard & Form Integrations', 'proweblook-phone-validator' ),
			array( $this, 'render_section_integrations' ),
			'Proweblook_phone_validator'
		);

		add_settings_field(
			'propp_auto_check',
			__( 'WooCommerce Phone Check', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_auto_check',
				'default' => 0,
				'label'   => __( 'Validate billing phone numbers on WooCommerce Checkout (Classic & Block Checkout + HPOS Order Meta)', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_wc_whatsapp_mode',
			__( 'WooCommerce WhatsApp COD Guard', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'select_wa_mode',
				'key'     => 'propp_wc_whatsapp_mode',
				'default' => 'off',
				'desc'    => __( 'Silently verifies that the customer\'s phone number is active on WhatsApp without sending any OTP or message — slashes fake COD orders and RTO returns.', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_wc_caller_id_mode',
			__( 'WooCommerce Caller ID (CNAM) Guard', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'select_wa_mode',
				'key'     => 'propp_wc_caller_id_mode',
				'default' => 'off',
				'desc'    => __( 'Fetches the telecom-registered Caller ID (CNAM) subscriber name and compares it against the customer\'s Billing Name & Company to flag spoofed orders.', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_wc_ip_geo_check',
			__( 'WooCommerce Edge GeoIP Risk Check', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_wc_ip_geo_check',
				'default' => 0,
				'label'   => __( 'Detect Billing-Country vs. IP-Country mismatches and VPN/Datacenter proxies via Edge GeoIP (uses your 20,000/mo free GeoIP quota)', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_wc_autohold_mode',
			__( 'WooCommerce Fraud Auto-Hold', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'select_autohold_mode',
				'key'     => 'propp_wc_autohold_mode',
				'default' => 'off',
				'desc'    => __( 'Computes a 0–100 Composite Trust Score across enabled signals and automatically places risky orders On Hold so your team can review before fulfillment.', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_whatsapp_check',
			__( 'Require WhatsApp on Lead Forms', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_whatsapp_check',
				'default' => 0,
				'label'   => __( 'Also require phone numbers on enabled lead forms (Elementor, CF7, WPForms, Gravity, Ninja) to be active on WhatsApp', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_elementor_check',
			__( 'Elementor Pro Forms', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_elementor_check',
				'default' => 0,
				'label'   => __( 'Validate tel (and email) fields in Elementor Pro Forms', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_cf7_check',
			__( 'Contact Form 7', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_cf7_check',
				'default' => 0,
				'label'   => __( 'Validate [tel] and [tel*] (plus [email] when enabled) in Contact Form 7', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_wpforms_check',
			__( 'WPForms', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_wpforms_check',
				'default' => 0,
				'label'   => __( 'Validate Phone (and Email) fields in WPForms', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_gravity_check',
			__( 'Gravity Forms', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_gravity_check',
				'default' => 0,
				'label'   => __( 'Validate Phone (and Email) fields in Gravity Forms', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_ninja_check',
			__( 'Ninja Forms', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_ninja_check',
				'default' => 0,
				'label'   => __( 'Validate Phone (and Email) fields in Ninja Forms', 'proweblook-phone-validator' ),
			)
		);

		add_settings_field(
			'propp_registration_check',
			__( 'User Registration Guard', 'proweblook-phone-validator' ),
			array( $this, 'render_settings_field' ),
			'Proweblook_phone_validator',
			'propp_section_integrations',
			array(
				'type'    => 'checkbox',
				'key'     => 'propp_registration_check',
				'default' => 0,
				'label'   => __( 'Block disposable & undeliverable emails on WordPress and WooCommerce account signups', 'proweblook-phone-validator' ),
			)
		);
	}

	/**
	 * Sanitize settings array before saving to `wp_options`.
	 *
	 * @param mixed $input Raw submitted settings array.
	 * @return array Sanitized settings.
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();
		$clean = array();

		$clean['propp_api_key'] = isset( $input['propp_api_key'] ) ? preg_replace( '/[^a-zA-Z0-9_\-]/', '', trim( (string) $input['propp_api_key'] ) ) : '';

		$country      = isset( $input['propp_country'] ) ? strtoupper( sanitize_text_field( (string) $input['propp_country'] ) ) : '';
		$country_list = PROP_Countries::get_list();
		$clean['propp_country'] = ( '' !== $country && isset( $country_list[ $country ] ) ) ? $country : '';

		$wa_mode                         = isset( $input['propp_wc_whatsapp_mode'] ) ? sanitize_key( (string) $input['propp_wc_whatsapp_mode'] ) : 'off';
		$clean['propp_wc_whatsapp_mode'] = in_array( $wa_mode, array( 'off', 'cod_only', 'all' ), true ) ? $wa_mode : 'off';

		$cnam_mode                        = isset( $input['propp_wc_caller_id_mode'] ) ? sanitize_key( (string) $input['propp_wc_caller_id_mode'] ) : 'off';
		$clean['propp_wc_caller_id_mode'] = in_array( $cnam_mode, array( 'off', 'cod_only', 'all' ), true ) ? $cnam_mode : 'off';

		$hold_mode                        = isset( $input['propp_wc_autohold_mode'] ) ? sanitize_key( (string) $input['propp_wc_autohold_mode'] ) : 'off';
		$clean['propp_wc_autohold_mode'] = in_array( $hold_mode, array( 'off', 'high_risk', 'cod_review', 'all_review' ), true ) ? $hold_mode : 'off';

		$checkbox_keys = array(
			'propp_block_voip',
			'propp_whatsapp_check',
			'propp_email_check',
			'propp_block_disposable',
			'propp_dnsbl_check',
			'propp_block_dnsbl',
			'propp_wc_ip_geo_check',
			'propp_registration_check',
			'propp_auto_check',
			'propp_elementor_check',
			'propp_cf7_check',
			'propp_gravity_check',
			'propp_wpforms_check',
			'propp_ninja_check',
		);

		foreach ( $checkbox_keys as $cb_key ) {
			$clean[ $cb_key ] = ! empty( $input[ $cb_key ] ) ? 1 : 0;
		}

		update_option( 'prop-api-key-invalid', 0 );

		$plugin = PROP_Plugin::get_instance();
		if ( $plugin->get_api() ) {
			$plugin->get_api()->set_apikey( $clean['propp_api_key'] );
			$plugin->get_api()->get_balances( true );
		}

		return $clean;
	}

	/**
	 * Render Section 1 description.
	 *
	 * @return void
	 */
	public function render_section_api() {
		echo '<p class="description">' . esc_html__( 'Connect your ProWebLook API key. All checks are cached in WordPress transients for 12 hours so repeat submissions never double-bill your credits, and checkouts automatically fail open if a network timeout occurs.', 'proweblook-phone-validator' ) . '</p>';
	}

	/**
	 * Render Section 2 description.
	 *
	 * @return void
	 */
	public function render_section_integrations() {
		echo '<p class="description">' . esc_html__( 'Choose where real-time verification runs. For custom HTML forms, call PROP_activate_third_party() in your theme and add class="propp-phone" to phone inputs.', 'proweblook-phone-validator' ) . '</p>';
	}

	/**
	 * Render individual settings field controls.
	 *
	 * @param array $args Field definition arguments.
	 * @return void
	 */
	public function render_settings_field( $args ) {
		$defaults = array(
			'type'        => 'text',
			'key'         => '',
			'default'     => '',
			'label'       => '',
			'placeholder' => '',
			'desc'        => '',
		);
		$args     = wp_parse_args( $args, $defaults );
		$key      = (string) $args['key'];
		$field_id = 'PROP_field_' . $key;

		$plugin = PROP_Plugin::get_instance();
		$stored = $plugin->get_option( $key );
		$value  = ( null !== $stored ) ? $stored : $args['default'];

		if ( 'password_text' === $args['type'] ) :
			?>
			<div class="propp-apikey-row">
				<input
					id="<?php echo esc_attr( $field_id ); ?>"
					type="password"
					class="regular-text code"
					autocomplete="off"
					placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
					name="propp_settings[<?php echo esc_attr( $key ); ?>]"
					value="<?php echo esc_attr( (string) $value ); ?>"
				/>
				<button type="button" class="button button-secondary" id="propp-toggle-key">
					<?php esc_html_e( 'Show', 'proweblook-phone-validator' ); ?>
				</button>
			</div>
			<?php
		elseif ( 'checkbox' === $args['type'] ) :
			?>
			<label for="<?php echo esc_attr( $field_id ); ?>">
				<input
					id="<?php echo esc_attr( $field_id ); ?>"
					type="checkbox"
					name="propp_settings[<?php echo esc_attr( $key ); ?>]"
					value="1"
					<?php checked( (int) $value, 1 ); ?>
				/>
				<?php echo esc_html( $args['label'] ); ?>
			</label>
			<?php
		elseif ( 'select_country' === $args['type'] ) :
			$countries = PROP_Countries::get_list();
			?>
			<select id="<?php echo esc_attr( $field_id ); ?>" name="propp_settings[<?php echo esc_attr( $key ); ?>]">
				<option value=""><?php esc_html_e( '— Auto-detect International (+E.164) —', 'proweblook-phone-validator' ); ?></option>
				<?php foreach ( $countries as $code => $name ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" <?php selected( (string) $value, (string) $code ); ?>>
						<?php echo esc_html( $name . ' (' . $code . ')' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php
		elseif ( 'select_wa_mode' === $args['type'] ) :
			?>
			<select id="<?php echo esc_attr( $field_id ); ?>" name="propp_settings[<?php echo esc_attr( $key ); ?>]">
				<option value="off" <?php selected( (string) $value, 'off' ); ?>>
					<?php esc_html_e( 'Disabled', 'proweblook-phone-validator' ); ?>
				</option>
				<option value="cod_only" <?php selected( (string) $value, 'cod_only' ); ?>>
					<?php esc_html_e( 'Cash on Delivery (COD) Orders Only — Recommended for RTO Protection', 'proweblook-phone-validator' ); ?>
				</option>
				<option value="all" <?php selected( (string) $value, 'all' ); ?>>
					<?php esc_html_e( 'All WooCommerce Checkout Orders (COD & Prepaid)', 'proweblook-phone-validator' ); ?>
				</option>
			</select>
			<?php
		elseif ( 'select_autohold_mode' === $args['type'] ) :
			?>
			<select id="<?php echo esc_attr( $field_id ); ?>" name="propp_settings[<?php echo esc_attr( $key ); ?>]">
				<option value="off" <?php selected( (string) $value, 'off' ); ?>>
					<?php esc_html_e( 'Disabled — Record 0–100 Trust Score & Order Notes Only', 'proweblook-phone-validator' ); ?>
				</option>
				<option value="high_risk" <?php selected( (string) $value, 'high_risk' ); ?>>
					<?php esc_html_e( 'Auto-Hold High-Risk Orders (Trust Score < 45)', 'proweblook-phone-validator' ); ?>
				</option>
				<option value="cod_review" <?php selected( (string) $value, 'cod_review' ); ?>>
					<?php esc_html_e( 'Auto-Hold Risky Cash on Delivery Orders (COD with Trust Score < 65) — Recommended', 'proweblook-phone-validator' ); ?>
				</option>
				<option value="all_review" <?php selected( (string) $value, 'all_review' ); ?>>
					<?php esc_html_e( 'Auto-Hold All Orders Needing Review (Trust Score < 65)', 'proweblook-phone-validator' ); ?>
				</option>
			</select>
			<?php
		endif;

		if ( ! empty( $args['desc'] ) ) {
			echo '<p class="description">' . wp_kses_post( $args['desc'] ) . '</p>';
		}
	}

	/**
	 * Render full WP Admin Settings screen.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$plugin   = PROP_Plugin::get_instance();
		$api      = $plugin->get_api();
		$api_key  = trim( (string) $plugin->get_option( 'propp_api_key' ) );
		$balances = ( '' !== $api_key && $api ) ? $api->get_balances( false ) : array( 'ok' => false );
		?>
		<div class="wrap propp-admin-wrap">
			<div class="propp-header">
				<div>
					<h1><?php esc_html_e( 'ProWebLook Phone, WhatsApp & Email Validator', 'proweblook-phone-validator' ); ?> <span class="propp-version">v<?php echo esc_html( PROP_PLUGIN_CURRENT_VERSION ); ?></span></h1>
					<p class="propp-subtitle"><?php esc_html_e( 'Stop fake leads, burner VoIP numbers, disposable emails, and costly WooCommerce Cash-on-Delivery RTO returns in real time.', 'proweblook-phone-validator' ); ?></p>
				</div>
				<div class="propp-header-actions">
					<a href="https://proweblook.com/pricing" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php esc_html_e( 'Top Up Credits', 'proweblook-phone-validator' ); ?></a>
					<a href="https://docs.proweblook.com/" target="_blank" rel="noopener noreferrer" class="button button-secondary"><?php esc_html_e( 'API Docs', 'proweblook-phone-validator' ); ?></a>
				</div>
			</div>

			<!-- Credit Balance Overview -->
			<div class="propp-balance-panel">
				<div class="propp-balance-top">
					<div>
						<strong><?php esc_html_e( 'ProWebLook Account & Credit Balances', 'proweblook-phone-validator' ); ?></strong>
						<?php if ( ! empty( $balances['ok'] ) && ! empty( $balances['account_email'] ) ) : ?>
							<span class="propp-badge propp-badge-ok"><?php echo esc_html( $balances['account_email'] ); ?></span>
						<?php elseif ( ! empty( $balances['ok'] ) ) : ?>
							<span class="propp-badge propp-badge-ok"><?php esc_html_e( 'Connected', 'proweblook-phone-validator' ); ?></span>
						<?php elseif ( '' === $api_key ) : ?>
							<span class="propp-badge propp-badge-warn"><?php esc_html_e( 'API Key Required', 'proweblook-phone-validator' ); ?></span>
						<?php else : ?>
							<span class="propp-badge propp-badge-err"><?php esc_html_e( 'Check API Key', 'proweblook-phone-validator' ); ?></span>
						<?php endif; ?>
					</div>
					<div class="propp-balance-btns">
						<button type="button" class="button button-small" id="propp-refresh-balances"><?php esc_html_e( 'Refresh Balances', 'proweblook-phone-validator' ); ?></button>
						<button type="button" class="button button-small" id="propp-clear-cache"><?php esc_html_e( 'Clear 12h Cache', 'proweblook-phone-validator' ); ?></button>
					</div>
				</div>

				<div class="propp-metrics-grid">
					<div class="propp-metric-card">
						<span class="propp-metric-label"><?php esc_html_e( 'Phone Validation Credits', 'proweblook-phone-validator' ); ?></span>
						<span class="propp-metric-val" id="propp-bal-phone">
							<?php echo ( ! empty( $balances['ok'] ) && null !== $balances['phone'] ) ? esc_html( number_format_i18n( (int) $balances['phone'] ) ) : '—'; ?>
						</span>
					</div>
					<div class="propp-metric-card">
						<span class="propp-metric-label"><?php esc_html_e( 'WhatsApp Checker Credits', 'proweblook-phone-validator' ); ?></span>
						<span class="propp-metric-val" id="propp-bal-whatsapp">
							<?php echo ( ! empty( $balances['ok'] ) && null !== $balances['whatsapp'] ) ? esc_html( number_format_i18n( (int) $balances['whatsapp'] ) ) : '—'; ?>
						</span>
					</div>
					<div class="propp-metric-card">
						<span class="propp-metric-label"><?php esc_html_e( 'Email Verification Credits', 'proweblook-phone-validator' ); ?></span>
						<span class="propp-metric-val" id="propp-bal-email">
							<?php echo ( ! empty( $balances['ok'] ) && null !== $balances['email'] ) ? esc_html( number_format_i18n( (int) $balances['email'] ) ) : '—'; ?>
						</span>
					</div>
					<div class="propp-metric-card">
						<span class="propp-metric-label"><?php esc_html_e( 'Caller ID (CNAM) Credits', 'proweblook-phone-validator' ); ?></span>
						<span class="propp-metric-val" id="propp-bal-caller">
							<?php echo ( ! empty( $balances['ok'] ) && isset( $balances['caller'] ) && null !== $balances['caller'] ) ? esc_html( number_format_i18n( (int) $balances['caller'] ) ) : '—'; ?>
						</span>
					</div>
					<div class="propp-metric-card">
						<span class="propp-metric-label"><?php esc_html_e( 'Domain DNSBL Credits', 'proweblook-phone-validator' ); ?></span>
						<span class="propp-metric-val" id="propp-bal-blacklist">
							<?php echo ( ! empty( $balances['ok'] ) && isset( $balances['blacklist'] ) && null !== $balances['blacklist'] ) ? esc_html( number_format_i18n( (int) $balances['blacklist'] ) ) : '—'; ?>
						</span>
					</div>
					<div class="propp-metric-card">
						<span class="propp-metric-label"><?php esc_html_e( 'Edge GeoIP Quota', 'proweblook-phone-validator' ); ?></span>
						<span class="propp-metric-val" style="font-size:17px;">
							<?php esc_html_e( '20,000 / mo Free', 'proweblook-phone-validator' ); ?>
						</span>
					</div>
				</div>
				<?php if ( empty( $balances['ok'] ) && ! empty( $balances['error'] ) && '' !== $api_key ) : ?>
					<p class="propp-inline-err"><?php echo esc_html( $balances['error'] ); ?></p>
				<?php endif; ?>
				<p id="propp-cache-feedback" class="propp-cache-feedback" style="display:none;"></p>
			</div>

			<div class="propp-grid-layout">
				<!-- Left Column: Settings Form -->
				<div class="propp-main-col">
					<div class="propp-box">
						<form action="options.php" method="post">
							<?php
							settings_fields( 'Proweblook_phone_validator' );
							do_settings_sections( 'Proweblook_phone_validator' );
							submit_button( __( 'Save Validator Settings', 'proweblook-phone-validator' ) );
							?>
						</form>
					</div>
				</div>

				<!-- Right Column: Interactive Live Test Validator -->
				<div class="propp-side-col">
					<div class="propp-box">
						<h2><?php esc_html_e( 'Live Test Validator (6 Signals)', 'proweblook-phone-validator' ); ?></h2>
						<p class="description"><?php esc_html_e( 'Test your API key and inspect real-time Carrier, Silent WhatsApp, Caller ID (CNAM), Email, Domain DNSBL, or Edge GeoIP signals right from WordPress Admin.', 'proweblook-phone-validator' ); ?></p>

						<div class="propp-test-field">
							<label for="propp-test-type"><strong><?php esc_html_e( 'Verification Service', 'proweblook-phone-validator' ); ?></strong></label>
							<select id="propp-test-type">
								<option value="phone"><?php esc_html_e( 'Phone Carrier & Line Type Check', 'proweblook-phone-validator' ); ?></option>
								<option value="whatsapp"><?php esc_html_e( 'Silent WhatsApp Presence Check (No OTP)', 'proweblook-phone-validator' ); ?></option>
								<option value="caller_id"><?php esc_html_e( 'Caller ID (CNAM) Subscriber Name Lookup', 'proweblook-phone-validator' ); ?></option>
								<option value="email"><?php esc_html_e( 'Email Deliverability & Disposable Check', 'proweblook-phone-validator' ); ?></option>
								<option value="dnsbl"><?php esc_html_e( 'Domain / IP DNSBL Blacklist Check', 'proweblook-phone-validator' ); ?></option>
								<option value="ip_geo"><?php esc_html_e( 'Edge IP Geolocation & VPN/Proxy Check', 'proweblook-phone-validator' ); ?></option>
							</select>
						</div>

						<div class="propp-test-field">
							<label for="propp-phone"><strong><?php esc_html_e( 'Phone (+E.164), Email, Domain, or IP', 'proweblook-phone-validator' ); ?></strong></label>
							<input id="propp-phone" type="text" class="regular-text" placeholder="+14155552671, user@company.com, stripe.com, or 8.8.8.8" value="" />
						</div>

						<p class="submit" style="margin:12px 0 0;padding:0;">
							<button type="button" id="propp-button-validate" class="button button-primary">
								<?php esc_html_e( 'Run Live Check', 'proweblook-phone-validator' ); ?>
							</button>
						</p>

						<div id="propp-message" class="propp-test-result" style="display:none;"></div>
						<ul id="propp-list" class="propp-history-list"></ul>
					</div>

					<div class="propp-box propp-box-muted">
						<h3><?php esc_html_e( 'WooCommerce HPOS & Block Checkout Ready', 'proweblook-phone-validator' ); ?></h3>
						<p class="description">
							<?php esc_html_e( 'Verified carrier, line type, WhatsApp status (Personal vs Business), and email deliverability verdicts are automatically attached to every WooCommerce order under Billing Details and Order Notes.', 'proweblook-phone-validator' ); ?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
