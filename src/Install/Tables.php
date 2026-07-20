<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Install;

/**
 * Custom tables for redirects and the 404 monitor (the Redirects addon), and
 * the indexing submission log (the Indexistic addon).
 */
final class Tables {

	public static function create(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix . 'seoistic_';

		$schemas = array();

		$schemas[] = "CREATE TABLE {$prefix}redirects (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			source VARCHAR(255) NOT NULL,
			target VARCHAR(255) NOT NULL,
			code SMALLINT NOT NULL DEFAULT 301,
			is_regex TINYINT(1) NOT NULL DEFAULT 0,
			hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
			enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL,
			last_hit DATETIME NULL,
			PRIMARY KEY  (id),
			KEY source (source(191))
		) {$charset};";

		$schemas[] = "CREATE TABLE {$prefix}log_404 (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			url VARCHAR(255) NOT NULL,
			referer VARCHAR(255) NULL,
			hits BIGINT UNSIGNED NOT NULL DEFAULT 1,
			last_seen DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY url (url(191))
		) {$charset};";

		$schemas[] = "CREATE TABLE {$prefix}indexing_log (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			url VARCHAR(255) NOT NULL,
			engine VARCHAR(20) NOT NULL,
			action VARCHAR(20) NOT NULL,
			status VARCHAR(20) NOT NULL,
			response_message TEXT NULL,
			is_manual TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY url (url(191)),
			KEY engine (engine)
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
