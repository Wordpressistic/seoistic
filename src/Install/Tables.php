<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Install;

/**
	 * Custom tables for redirects, the 404 monitor, indexing logs, schema blocks,
	 * and rank-tracking history.
 */
final class Tables {

	public static function create(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix . 'seoistic_';

		$schemas = array();

		$schemas[] = "CREATE TABLE {$prefix}redirects (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			source VARCHAR(255) NOT NULL,
			target VARCHAR(255) NOT NULL,
			code SMALLINT NOT NULL DEFAULT 301,
			is_regex TINYINT(1) NOT NULL DEFAULT 0,
			hits bigint(20) unsigned NOT NULL DEFAULT 0,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			last_hit datetime NULL,
			PRIMARY KEY  (id),
			KEY source (source(191))
		) {$charset};";

		$schemas[] = "CREATE TABLE {$prefix}log_404 (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url VARCHAR(255) NOT NULL,
			referer VARCHAR(255) NULL,
			hits bigint(20) unsigned NOT NULL DEFAULT 1,
			last_seen datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url (url(191))
		) {$charset};";

		$schemas[] = "CREATE TABLE {$prefix}indexing_log (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			url VARCHAR(255) NOT NULL,
			engine varchar(20) NOT NULL,
			action varchar(20) NOT NULL,
			status varchar(20) NOT NULL,
			response_message TEXT NULL,
			is_manual TINYINT(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY url (url(191)),
			KEY engine (engine)
		) {$charset};";

		$schemas[] = "CREATE TABLE {$prefix}schema_blocks (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(191) NOT NULL,
			type varchar(191) NOT NULL,
			rules LONGTEXT NOT NULL,
			mapping LONGTEXT NOT NULL,
			active TINYINT(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY active (active),
			KEY type (type(191))
		) {$charset};";

		$schemas[] = "CREATE TABLE IF NOT EXISTS {$prefix}keywords (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			keyword varchar(191) NOT NULL,
			locale varchar(20) NOT NULL DEFAULT 'en-US',
			device varchar(10) NOT NULL DEFAULT 'desktop',
			source varchar(20) NOT NULL DEFAULT 'search_api',
			added datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY keyword_context (keyword(140), locale(20), device(10)),
			KEY locale (locale),
			KEY device (device),
			KEY source (source)
		) {$charset};";

		$schemas[] = "CREATE TABLE IF NOT EXISTS {$prefix}positions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			keyword_id bigint(20) unsigned NOT NULL,
			position decimal(6,2) NOT NULL,
			url varchar(500) NOT NULL DEFAULT '',
			checked_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY keyword_id (keyword_id),
			KEY checked_at (checked_at)
		) {$charset};";

		foreach ( $schemas as $schema ) {
			dbDelta( $schema );
		}
	}

	/**
	 * Re-runs dbDelta when SEOISTIC_DB_VERSION has moved on, so sites that upgrade
	 * without deactivating still pick up new/changed columns (e.g. the `last_hit`
	 * column added for the Redirects table).
	 */
	public static function maybe_upgrade(): void {
		if ( get_option( 'seoistic_db_version' ) === SEOISTIC_DB_VERSION ) {
			return;
		}
		self::create();
		update_option( 'seoistic_db_version', SEOISTIC_DB_VERSION );
	}
}
