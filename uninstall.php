<?php
/**
 * Uninstall routine for ProWebLook Phone, WhatsApp & Email Validator.
 *
 * @package ProWebLook_Validator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'prop-api-key-invalid' );
delete_option( 'propp_settings' );

global $wpdb;
if ( isset( $wpdb ) && isset( $wpdb->options ) ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_prop_v2_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_prop_v2_' ) . '%'
		)
	);
}
