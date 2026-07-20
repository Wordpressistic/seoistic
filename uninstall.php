<?php
/**
 * Uninstall. Removes options; keeps redirect/404 tables unless the operator opted into
 * data deletion (seoistic_delete_data).
 *
 * @package Seoistic
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

foreach ( array( 'seoistic_title_sep', 'seoistic_sitemaps', 'seoistic_breadcrumbs', 'seoistic_llms_txt', 'seoistic_modules', 'seoistic_flush', 'seoistic_db_version', 'seoistic_pro' ) as $seoistic_option ) {
	delete_option( $seoistic_option );
}

if ( get_option( 'seoistic_delete_data' ) ) {
	global $wpdb;
	$seoistic_prefix = $wpdb->prefix . 'seoistic_';
	foreach ( array( 'redirects', 'log_404' ) as $seoistic_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$seoistic_prefix}{$seoistic_table}" ); // phpcs:ignore WordPress.DB
	}
	delete_option( 'seoistic_delete_data' );
}
