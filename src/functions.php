<?php
/**
 * Helper functions for 3rd-party theme and custom form integrations.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Call this function in your theme or plugin to activate frontend AJAX phone validation
 * on any custom HTML form input carrying class="propp-phone".
 *
 * @return void
 */
function PROP_activate_third_party() {
	add_filter( 'PROP_api_is_private', '__return_false' );
	add_action( 'wp_enqueue_scripts', array( PROP_Plugin::get_instance(), 'enqueue_frontend' ), 11 );
	add_action( 'wp_footer', array( PROP_Plugin::get_instance(), 'footer_styles' ) );
}
