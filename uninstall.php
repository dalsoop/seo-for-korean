<?php
/**
 * Fired when the plugin is uninstalled. Removes all plugin options and meta.
 *
 * @package SEOForKorean
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'sfk_settings' );
delete_site_option( 'sfk_settings' );

global $wpdb;
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", 'sfk\\_%' ) );
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE %s", 'sfk\\_%' ) );
