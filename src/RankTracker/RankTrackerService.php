<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\RankTracker;

use Wpistic\Seoistic\Core\AI\ProxyClient;
use Wpistic\Seoistic\Core\AI\ProxyRequest;
use Wpistic\Seoistic\Gsc\GscClient;
use WP_Error;

final class RankTrackerService {

	public const SETTINGS_OPTION = 'seoistic_rank_tracker_settings';
	public const CRON_DAILY_HOOK = 'seoistic_rank_tracker_daily';
	public const CRON_REPORT_HOOK = 'seoistic_rank_tracker_weekly_report';
	public const REPORT_SCHEDULE = 'seoistic_rank_tracker_weekly';
	public const LOCK_TRANSIENT = 'seoistic_rank_tracker_lock';
	public const STATE_OPTION = 'seoistic_rank_tracker_state';
	private const BATCH_SIZE = 10;

	private RankTrackerRepository $repository;
	private ProxyClient $proxy;
	/** @var callable(array<string,mixed>): array<string,mixed>|WP_Error */
	private $proxy_transport;
	/** @var callable(): array<string,mixed> */
	private $gsc_loader;

	public function __construct( ?RankTrackerRepository $repository = null, ?ProxyClient $proxy = null ) {
		$this->repository = $repository ?? new RankTrackerRepository();
		$this->proxy = $proxy ?? new ProxyClient();
		$this->proxy_transport = null;
		$this->gsc_loader = null;
	}

	/**
	 * Test seams. Production requests still flow through ProxyRequest/ProxyClient.
	 *
	 * @param callable(array<string,mixed>): array<string,mixed>|WP_Error $transport
	 * @param callable(): array<string,mixed> $gsc_loader
	 */
	public function set_test_dependencies( ?callable $transport = null, ?callable $gsc_loader = null ): void {
		$this->proxy_transport = $transport;
		$this->gsc_loader = $gsc_loader;
	}

	/** @return array{enabled:bool,mode:string,batch_size:int,report_enabled:bool,report_email:string,brand_name:string,logo_url:string,primary_color:string} */
	public static function settings(): array {
		return self::sanitize_settings( (array) get_option( self::SETTINGS_OPTION, array() ) );
	}

	public static function save_settings( array $raw ): void {
		update_option( self::SETTINGS_OPTION, self::sanitize_settings( $raw ), false );
	}

	public static function sanitize_settings( array $raw ): array {
		$email = is_email( (string) ( $raw['report_email'] ?? '' ) );
		$logo = esc_url_raw( (string) ( $raw['logo_url'] ?? '' ) );
		$color = sanitize_hex_color( (string) ( $raw['primary_color'] ?? '' ) );
		return array(
			'enabled' => ! empty( $raw['enabled'] ),
			'mode' => 'gsc' === (string) ( $raw['mode'] ?? '' ) ? 'gsc' : 'search_api',
			'batch_size' => max( 1, min( 50, absint( $raw['batch_size'] ?? 10 ) ) ),
			'report_enabled' => ! empty( $raw['report_enabled'] ),
			'report_email' => $email ? $email : '',
			'brand_name' => sanitize_text_field( (string) ( $raw['brand_name'] ?? get_bloginfo( 'name' ) ) ),
			'logo_url' => $logo,
			'primary_color' => $color ?: '#9472ff',
		);
	}

	public function register(): void {
		add_filter( 'cron_schedules', array( $this, 'register_weekly_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( self::CRON_DAILY_HOOK, array( $this, 'run_daily' ) );
		add_action( self::CRON_REPORT_HOOK, array( $this, 'send_weekly_report' ) );
		add_action( 'init', array( $this, 'schedule' ) );
	}

	public function register_weekly_schedule( array $schedules ): array {
		$schedules[ self::REPORT_SCHEDULE ] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly (SEOistic Rank Reports)', 'seoistic' ),
		);
		return $schedules;
	}

	public function schedule(): void {
		$settings = self::settings();
		$daily = wp_next_scheduled( self::CRON_DAILY_HOOK );
		if ( $settings['enabled'] ) {
			if ( ! $daily ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_DAILY_HOOK );
			}
		} elseif ( $daily ) {
			wp_clear_scheduled_hook( self::CRON_DAILY_HOOK );
		}

		$report = wp_next_scheduled( self::CRON_REPORT_HOOK );
		if ( $settings['report_enabled'] ) {
			if ( ! $report ) {
				wp_schedule_event( time() + DAY_IN_SECONDS, self::REPORT_SCHEDULE, self::CRON_REPORT_HOOK );
			}
		} elseif ( $report ) {
			wp_clear_scheduled_hook( self::CRON_REPORT_HOOK );
		}
	}

	/** @return array<string,mixed>|WP_Error */
	public function run_daily( bool $force = false ) {
		$settings = self::settings();
		if ( ! $settings['enabled'] && ! $force ) {
			return new WP_Error( 'seoistic_rank_tracker_disabled', __( 'Rank tracking is disabled.', 'seoistic' ), array( 'status' => 400 ) );
		}
		if ( false !== get_transient( self::LOCK_TRANSIENT ) ) {
			return new WP_Error( 'seoistic_rank_tracker_locked', __( 'Another rank-tracking batch is already running.', 'seoistic' ), array( 'status' => 409 ) );
		}
		set_transient( self::LOCK_TRANSIENT, gmdate( 'c' ), 15 * MINUTE_IN_SECONDS );

		try {
			$result = 'gsc' === $settings['mode'] ? $this->import_gsc( $settings['batch_size'] ) : $this->check_search_api_batch( $settings['batch_size'] );
		} finally {
			delete_transient( self::LOCK_TRANSIENT );
		}
		return $result;
	}

	/** @return array<string,mixed>|WP_Error */
	public function check_search_api_batch( int $batch_size = 10 ) {
		$keywords = array_slice( $this->due_keywords( 'search_api' ), 0, max( 1, $batch_size ) );
		$keyword_terms = array_map( static fn( array $keyword ): string => (string) $keyword['keyword'], $keywords );
		if ( array() === $keyword_terms ) {
			return array( 'checked' => 0, 'remaining' => 0, 'mode' => 'search_api' );
		}

		$request = new ProxyRequest(
			'/v1/search',
			array(
				'keywords' => $keyword_terms,
				'locale' => (string) $keywords[0]['locale'],
				'device' => (string) $keywords[0]['device'],
				'site_url' => home_url( '/' ),
			),
			60
		);
		$response = is_callable( $this->proxy_transport ) ? ( $this->proxy_transport )( $request->payload() ) : $request->send( $this->proxy );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$results = $this->normalize_search_results( $response );
		foreach ( $keywords as $keyword ) {
			$result = $results[ (string) $keyword['keyword'] ] ?? null;
			if ( null !== $result ) {
				$this->repository->record_position( (int) $keyword['id'], $result['position'], $result['url'], null, true );
			}
		}
		return array(
			'checked' => count( $results ),
			'remaining' => count( $this->due_keywords( 'search_api' ) ),
			'mode' => 'search_api',
		);
	}

	/** @return array<string,mixed>|WP_Error */
	public function import_gsc( int $limit = 10 ) {
		$result = is_callable( $this->gsc_loader )
			? ( $this->gsc_loader )()
			: ( new GscClient() )->search_analytics( array( 'row_limit' => max( 25, $limit ), 'dimensions' => array( 'query' ) ) );
		if ( empty( $result['success'] ) || ! is_array( $result['data'] ?? null ) ) {
			$message = is_string( $result['error'] ?? null ) ? (string) $result['error'] : __( 'Search Console did not return usable query data.', 'seoistic' );
			return new WP_Error( 'seoistic_rank_tracker_gsc_failed', $message, array( 'status' => 502 ) );
		}

		$imported = 0;
		foreach ( array_slice( $result['data'], 0, $limit ) as $row ) {
			$keyword = (string) ( $row['keys'][0] ?? $row['keyword'] ?? '' );
			$position = (float) ( $row['position'] ?? $row['averagePosition'] ?? 0 );
			if ( '' === $keyword || $position <= 0 ) {
				continue;
			}
			$id = $this->repository->save_keyword( $keyword, 'en-US', 'desktop', 'gsc' );
			$this->repository->record_position( $id, $position, '', gmdate( 'Y-m-d H:i:s', strtotime( '-3 days' ) ), true );
			$imported++;
		}
		return array( 'checked' => $imported, 'remaining' => 0, 'mode' => 'gsc' );
	}

	/** @return array<int, array<string,mixed>> */
	public function due_keywords( string $source ): array {
		global $wpdb;
		$repository = $this->repository;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT k.* FROM ' . $repository->keyword_table() . ' k
				LEFT JOIN ' . $repository->position_table() . ' p
					ON p.keyword_id = k.id AND DATE(p.checked_at) = %s
				WHERE k.source = %s AND p.id IS NULL ORDER BY k.id ASC',
				current_time( 'Y-m-d', true ),
				$source
			),
			ARRAY_A
		);
		return array_map( array( $repository, 'hydrate_keyword' ), is_array( $rows ) ? $rows : array() );
	}

	/**
	 * @param array<string, mixed> $response
	 * @return array<string, array{position:float,url:string}>
	 */
	public function normalize_search_results( array $response ): array {
		$rows = $response['results'] ?? ( $response['data']['results'] ?? array() );
		$normalized = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$keyword = (string) ( $row['keyword'] ?? $row['query'] ?? '' );
			$position = (float) ( $row['position'] ?? $row['rank'] ?? 0 );
			if ( '' === $keyword || $position <= 0 ) {
				continue;
			}
			$normalized[ $keyword ] = array(
				'position' => min( 100.0, $position ),
				'url' => (string) ( $row['url'] ?? '' ),
			);
		}
		return $normalized;
	}

	/** @return array<int, array<string,mixed>> */
	public function table_rows( string $locale = '', string $device = '' ): array {
		$rows = array();
		foreach ( $this->repository->keywords( $locale, $device ) as $keyword ) {
			$history = array_reverse( $this->repository->history( (int) $keyword['id'], 30 ) );
			$current = count( $history ) ? (float) end( $history )['position'] : null;
			$previous = count( $history ) > 1 ? (float) $history[ count( $history ) - 2 ]['position'] : null;
			$rows[] = array(
				'keyword' => $keyword,
				'current' => $current,
				'change' => null === $current || null === $previous ? null : $previous - $current,
				'history' => $history,
				'source_label' => 'gsc' === (string) $keyword['source'] ? __( 'Search Console data (delayed)', 'seoistic' ) : __( 'WPistic Search API', 'seoistic' ),
			);
		}
		return $rows;
	}

	public function send_weekly_report( string $to = '' ): bool {
		$settings = self::settings();
		$recipient = '' !== $to ? $to : $settings['report_email'];
		if ( ! is_email( $recipient ) ) {
			update_option( self::STATE_OPTION, array( 'last_report_at' => '', 'last_report_status' => __( 'No valid report recipient is configured.', 'seoistic' ) ), false );
			return false;
		}
		$sent = wp_mail(
			$recipient,
			sprintf( __( '%s — SEO Rank Report', 'seoistic' ), $settings['brand_name'] ),
			( new RankTrackerReport( $this->table_rows(), $settings ) )->html( false ),
			array( 'Content-Type: text/html; charset=UTF-8' )
		);
		update_option( self::STATE_OPTION, array(
			'last_report_at' => gmdate( 'c' ),
			'last_report_status' => $sent ? __( 'Sent', 'seoistic' ) : __( 'Email delivery failed.', 'seoistic' ),
			'last_recipient' => $sent ? $recipient : '',
		), false );
		return $sent;
	}
}
