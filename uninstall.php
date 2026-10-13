<?php
/**
 * Uninstall.
 *
 * SEOistic owns the entire `seoistic_` option namespace, so uninstall removes
 * it wholesale: settings, license state, encrypted secrets (AI keys, OAuth
 * tokens), cached reports and every transient. Cron events are cleared by
 * hook name. Content data (redirect/404/rank/schema tables + per-post SEO
 * meta) is only dropped when the operator opted into deletion via
 * seoistic_delete_data — license-scoped credentials are the exception and are
 * always purged, since leaving them behind is never the safe default.
 *
 * @package Seoistic
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Cron events the plugin may still have scheduled.
foreach ( array(
	'seoistic_license_cron',
	'seoistic_scheduled_audit',
	'seoistic_rank_tracker_daily',
	'seoistic_rank_tracker_weekly',
	'seoistic_cwv_test',
	'seoistic_cwv_alert',
	'seoistic_business_automator_daily',
	'seoistic_business_automator_weekly',
	'seoistic_uptime_run',
	'seoistic_uptime_prune',
) as $seoistic_hook ) {
	wp_clear_scheduled_hook( $seoistic_hook );
}

// Content data lives in custom tables + post meta — only removed on opt-in.
$seoistic_delete_data = (bool) get_option( 'seoistic_delete_data' );

// Everything the plugin persists starts with the seoistic_ namespace: plain
// options, transients, and their timeout markers.
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'seoistic\\_%'" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_seoistic\\_transient\\_%' OR option_name LIKE '\\_transient\\_seoistic\\_%' OR option_name LIKE '\\_transient\\_timeout\\_seoistic\\_%'" ); // phpcs:ignore WordPress.DB

if ( $seoistic_delete_data ) {
	$seoistic_prefix = $wpdb->prefix . 'seoistic_';
	foreach ( array( 'redirects', 'log_404', 'indexing_log', 'schema_blocks', 'keywords', 'positions' ) as $seoistic_table ) {
		$wpdb->query( "DROP TABLE IF EXISTS `{$seoistic_prefix}{$seoistic_table}`" ); // phpcs:ignore WordPress.DB
	}
	// Orphaned per-post SEO meta: titles, descriptions, keywords, scores, OG images, AEO results.
	$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_seoistic\\_%'" ); // phpcs:ignore WordPress.DB
}
