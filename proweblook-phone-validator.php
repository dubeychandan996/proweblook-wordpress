<?php
/**
 * Plugin Name:       ProWebLook Phone, WhatsApp & Email Validator — COD & Lead Guard
 * Plugin URI:        https://proweblook.com/phone-number-validator
 * Description:       Real-time Phone Carrier, Silent WhatsApp COD RTO Guard, Caller ID (CNAM), Email Deliverability, Domain DNSBL & Edge GeoIP Fraud Protection for WooCommerce (Classic & Block Checkout + HPOS), Elementor, CF7, WPForms, Gravity Forms & Ninja Forms.
 * Version:           2.1.0
 * Requires at least: 5.8
 * Tested up to:      7.1
 * Requires PHP:      7.4
 * WC requires at least: 7.0
 * WC tested up to:   9.8
 * Author:            ProWebLook
 * Author URI:        https://proweblook.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       proweblook-phone-validator
 * Domain Path:       /languages
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'PROP_PLUGIN_CURRENT_VERSION', '2.1.0' );
define( 'PROP_PLUGIN_FILE', __FILE__ );
define( 'PROP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'PROP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once PROP_PLUGIN_DIR . 'src/functions.php';
require_once PROP_PLUGIN_DIR . 'src/class-prop-plugin.php';

/**
 * Declare compatibility with WooCommerce High-Performance Order Storage (HPOS)
 * and WooCommerce Cart & Checkout Blocks.
 */
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'after_setup_theme', 'PROP_load', 11 );

/**
 * Initialize the plugin.
 *
 * @return void
 */
function PROP_load() {
	$plugin = PROP_Plugin::get_instance();
	$plugin->plugin_setup();
}
