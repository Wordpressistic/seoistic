<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * An unattended WP-Cron audit pass (daily or weekly) that re-scores every
 * published page and emails the site owner a digest of pages that just
 * dropped below the score threshold — as opposed to the manual, browser-
 * triggered "Run Site Audit" button (Admin::ajax_audit_batch()), which exists
 * for an immediate on-demand re-score. Off by default; the site owner opts in
 * from Settings → Automation.
 */
final class ScheduledAudit {

	private const OPTION      = 'seoistic_scheduled_audit';
	public const CRON_HOOK    = 'seoistic_scheduled_audit_run';
	private const SCHEDULE_ID = 'seoistic_weekly';

	/**
	 * Hard cap per run so a single wp-cron request can't run indefinitely on a
	 * very large site. If a site has more published pages than this, later ones
	 * are simply picked up on the next scheduled run.
	 */
	private const MAX_POSTS_PER_RUN = 1000;

	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'register_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( 'init', array( $this, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
	}

	/**
	 * @param array<string, array{interval:int, display:string}> $schedules
	 * @return array<string, array{interval:int, display:string}>
	 */
	public function register_schedule( $schedules ) {
		$schedules[ self::SCHEDULE_ID ] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly (SEOISTIC)', 'seoistic' ),
		);
		return $schedules;
	}

	/**
	 * @return array{enabled:bool, frequency:string, score_threshold:int, notify_email:string}
	 */
	public static function settings(): array {
		$defaults = array(
			'enabled'         => false,
			'frequency'       => 'weekly',
			'score_threshold' => 70,
			'notify_email'    => '',
		);
		$saved = get_option( self::OPTION, array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	public static function save( bool $enabled, string $frequency, int $score_threshold, string $notify_email ): void {
		update_option(
			self::OPTION,
			array(
				'enabled'         => $enabled,
				'frequency'       => in_array( $frequency, array( 'daily', 'weekly' ), true ) ? $frequency : 'weekly',
				'score_threshold' => max( 0, min( 100, $score_threshold ) ),
				'notify_email'    => sanitize_email( $notify_email ),
			)
		);
	}

	/**
	 * Keeps the scheduled cron event in sync with the saved settings — reschedules
	 * on a frequency change, unschedules when disabled. Cheap (one option read, one
	 * timestamp check) so it's safe to run on every `init`.
	 */
	public function maybe_schedule(): void {
		$settings   = self::settings();
		$schedule   = 'daily' === $settings['frequency'] ? 'daily' : self::SCHEDULE_ID;
		$next       = wp_next_scheduled( self::CRON_HOOK );
		$event      = $next ? wp_get_scheduled_event( self::CRON_HOOK ) : false;
		$current_schedule = $event ? $event->schedule : '';

		if ( ! $settings['enabled'] ) {
			if ( $next ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
			return;
		}

		if ( ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, $schedule, self::CRON_HOOK );
			return;
		}

		if ( $current_schedule !== $schedule ) {
			wp_clear_scheduled_hook( self::CRON_HOOK );
			wp_schedule_event( time() + HOUR_IN_SECONDS, $schedule, self::CRON_HOOK );
		}
	}

	/**
	 * Re-scores every published page (capped at MAX_POSTS_PER_RUN) and emails a
	 * digest of pages that just crossed below the threshold this run — i.e. either
	 * never scored before, or scored at/above the threshold last time. Chronically
	 * low-scoring pages that haven't changed aren't re-mentioned every run; the
	 * dashboard's SEO Health card is where those live on an ongoing basis.
	 */
	public function run(): void {
		$settings  = self::settings();
		$threshold = $settings['score_threshold'];

		$ids = get_posts(
			array(
				'post_type'      => get_post_types( array( 'public' => true ) ),
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'posts_per_page' => self::MAX_POSTS_PER_RUN,
			)
		);

		$newly_low = array();
		foreach ( $ids as $post_id ) {
			$post_id  = (int) $post_id;
			$previous = PostSeo::score( $post_id );
			$score    = Scorer::recalculate( $post_id );

			if ( $score < $threshold && ( -1 === $previous || $previous >= $threshold ) ) {
				$newly_low[] = array( 'id' => $post_id, 'title' => get_the_title( $post_id ), 'score' => $score );
			}
		}

		DashboardMetrics::flush();

		if ( array() !== $newly_low ) {
			$this->send_digest( $newly_low, $threshold );
		}
	}

	/**
	 * @param array<int, array{id:int, title:string, score:int}> $pages
	 */
	private function send_digest( array $pages, int $threshold ): void {
		$to = self::settings()['notify_email'];
		$to = '' !== $to ? $to : get_option( 'admin_email' );

		$lines = array();
		foreach ( $pages as $page ) {
			$lines[] = sprintf(
				'%1$d/100 — %2$s — %3$s',
				$page['score'],
				$page['title'],
				get_edit_post_link( $page['id'], 'raw' )
			);
		}

		$subject = sprintf(
			/* translators: 1: site name, 2: number of pages. */
			__( '[%1$s] SEOISTIC: %2$d page(s) just dropped below your SEO score threshold', 'seoistic' ),
			get_bloginfo( 'name' ),
			count( $pages )
		);
		$body = sprintf(
			/* translators: %d: score threshold. */
			__( "These pages scored below %d/100 in today's scheduled SEOISTIC audit, having been at or above it last time:\n\n", 'seoistic' ),
			$threshold
		) . implode( "\n", $lines );

		wp_mail( $to, $subject, $body );
	}
}
