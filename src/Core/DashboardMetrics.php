<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

use Wpistic\Seoistic\Module\Entitlement;
use Wpistic\Seoistic\Module\ModuleRegistry;

/**
 * Dashboard snapshot. Reads *cached* per-post scores/meta — it never runs a fresh
 * audit on page load (that only happens from the manual "Run Site Audit" action or
 * on save_post). Results are cached for a few minutes so the dashboard stays cheap
 * even on sites with a lot of content.
 */
final class DashboardMetrics {

	private const TRANSIENT = 'seoistic_dashboard_metrics';
	private const TTL       = 15 * MINUTE_IN_SECONDS;

	/**
	 * @return array<string, mixed>
	 */
	public static function snapshot( ModuleRegistry $registry, bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		global $wpdb;
		$public_types = get_post_types( array( 'public' => true ) );
		$placeholders = implode( ',', array_fill( 0, count( $public_types ), '%s' ) );

		$total_published = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$public_types
			)
		);

		$scored = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				 WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders}) AND m.meta_key = '_seoistic_score'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$public_types
			)
		);

		$avg_score = $scored > 0 ? (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT AVG(CAST(m.meta_value AS UNSIGNED)) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				 WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders}) AND m.meta_key = '_seoistic_score'", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$public_types
			)
		) : 0.0;

		$noindex_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				 WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders}) AND ( ( m.meta_key = '_seoistic_noindex' AND m.meta_value = '1' ) OR ( m.meta_key = '_seoistic_robots' AND m.meta_value = 'noindex' ) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$public_types
			)
		);

		$missing_meta = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
				 AND p.ID NOT IN (
					SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_seoistic_description','_seoistic_desc') AND meta_value <> ''
				 )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$public_types
			)
		);

		$missing_title = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
				 AND p.ID NOT IN (
					SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_seoistic_title' AND meta_value <> ''
				 )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$public_types
			)
		);

		$low_score_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID
				 WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders}) AND m.meta_key = '_seoistic_score'
				 AND CAST(m.meta_value AS UNSIGNED) < 50", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$public_types
			)
		);

		$missing_keyword = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} p WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders})
				 AND p.ID NOT IN (
					SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_seoistic_focus_keyword','_seoistic_focus_kw') AND meta_value <> ''
				 )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$public_types
			)
		);

		$log_table = $wpdb->prefix . 'seoistic_log_404';
		$has_404_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $log_table ) ) === $log_table; // phpcs:ignore WordPress.DB
		$errors_404 = $has_404_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$log_table}" ) : null; // phpcs:ignore WordPress.DB

		$redirects_table = $wpdb->prefix . 'seoistic_redirects';
		$has_redirects_table = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $redirects_table ) ) === $redirects_table; // phpcs:ignore WordPress.DB
		$redirects_count = $has_redirects_table ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$redirects_table} WHERE enabled = 1" ) : 0; // phpcs:ignore WordPress.DB

		$ai_options    = get_option( 'seoistic_ai_options', array() );
		$ai_enabled    = ! empty( $ai_options['enabled'] );
		$ai_configured = $ai_enabled && \Wpistic\Seoistic\AI\AiSettings::is_configured();

		$schema_module_active = false;
		$sitemap_extras_active = false;
		foreach ( $registry->all() as $module ) {
			if ( 'schema' === $module->id() ) {
				$schema_module_active = $registry->is_enabled( $module );
			}
			if ( 'sitemap_extras' === $module->id() ) {
				$sitemap_extras_active = $registry->is_enabled( $module );
			}
		}

		$snapshot = array(
			'seo_health'        => $scored > 0 ? (int) round( $avg_score ) : null,
			'scored_count'      => $scored,
			'total_published'   => $total_published,
			'indexed_pages'     => max( 0, $total_published - $noindex_count ),
			'noindex_pages'     => $noindex_count,
			'missing_meta'      => $missing_meta,
			'missing_title'     => $missing_title,
			'missing_keyword'   => $missing_keyword,
			'low_score_count'   => $low_score_count,
			'unscored_count'    => max( 0, $total_published - $scored ),
			'errors_404'        => $errors_404,
			'redirects_count'   => $redirects_count,
			'schema_enabled'    => $schema_module_active,
			'sitemap_enabled'   => (bool) get_option( 'seoistic_sitemaps', 1 ),
			'sitemap_extras'    => $sitemap_extras_active,
			'llms_txt_enabled'  => (bool) get_option( 'seoistic_llms_txt', 1 ),
			'ai_enabled'        => $ai_enabled,
			'ai_configured'     => $ai_configured,
			'plan'              => Entitlement::plan(),
			'generated_at'      => current_time( 'mysql' ),
		);

		set_transient( self::TRANSIENT, $snapshot, self::TTL );

		return $snapshot;
	}

	public static function flush(): void {
		delete_transient( self::TRANSIENT );
	}

	private const HISTORY_OPTION = 'seoistic_health_history';
	private const HISTORY_MAX    = 12;

	/**
	 * Record a completed full-site scan in the health history (the dashboard
	 * hero's "vs previous scan" delta reads from this — real scans only, no
	 * synthesized trend data).
	 */
	public static function record_history( ModuleRegistry $registry ): void {
		$snapshot = self::snapshot( $registry, true );
		if ( null === $snapshot['seo_health'] ) {
			return;
		}
		$history   = self::history();
		$history[] = array(
			'score' => (int) $snapshot['seo_health'],
			'time'  => current_time( 'mysql' ),
		);
		$history = array_slice( $history, -self::HISTORY_MAX );
		update_option( self::HISTORY_OPTION, $history, false );
	}

	/**
	 * @return list<array{score:int, time:string}>
	 */
	public static function history(): array {
		$history = get_option( self::HISTORY_OPTION, array() );
		if ( ! is_array( $history ) ) {
			return array();
		}
		return array_values(
			array_filter(
				$history,
				static fn( $row ): bool => is_array( $row ) && isset( $row['score'], $row['time'] )
			)
		);
	}
}
