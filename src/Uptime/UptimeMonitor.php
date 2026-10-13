<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Uptime;

/**
 * Domain / uptime monitor engine. Runs entirely inside WordPress (WP-Cron) with no
 * external service required — each due monitor is fetched with wp_remote_request,
 * the response is evaluated, a check row is recorded, and a state change (up↔down)
 * triggers an email. A single failure does not flip a monitor to "down"; it takes
 * CONFIRMATIONS consecutive failures, which debounces transient network blips.
 *
 * The pure evaluation/transition helpers are static and WordPress-free so they can
 * be unit-tested directly.
 */
final class UptimeMonitor {

	public const CRON_HOOK    = 'seoistic_uptime_run';
	public const PRUNE_HOOK   = 'seoistic_uptime_prune';
	private const SCHEDULE_ID = 'seoistic_five_minutes';
	private const OPTION      = 'seoistic_uptime_settings';

	/** Consecutive failed checks required before a monitor is marked down. */
	public const CONFIRMATIONS = 2;

	/** Hard cap on monitors processed per cron tick (protects large fleets). */
	private const MAX_PER_RUN = 50;

	private UptimeStore $store;

	public function __construct( ?UptimeStore $store = null ) {
		$this->store = $store ?? new UptimeStore();
	}

	public function register(): void {
		// The interval must be registered on every request so WP-Cron knows it
		// (cheap, no DB). The schedule/unschedule reconciliation only needs to run
		// where monitors are managed — admin — plus explicitly after a REST
		// create/delete (see UptimeRestController), so the hot front-end path
		// never issues a scheduling query.
		add_filter( 'cron_schedules', array( $this, 'register_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( 'admin_init', array( $this, 'maybe_schedule' ) );
		add_action( self::CRON_HOOK, array( $this, 'run' ) );
		add_action( self::PRUNE_HOOK, array( $this, 'prune' ) );
	}

	/**
	 * @param array<string, array{interval:int, display:string}> $schedules
	 * @return array<string, array{interval:int, display:string}>
	 */
	public function register_schedule( $schedules ) {
		$schedules[ self::SCHEDULE_ID ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			// See Core\ScheduledAudit::register_schedule() — translating here before
			// `init` is _doing_it_wrong on WordPress 6.7+, and the label is cosmetic.
			'display'  => did_action( 'init' ) ? __( 'Every Five Minutes (SEOISTIC Uptime)', 'seoistic' ) : 'Every Five Minutes (SEOISTIC Uptime)',
		);
		return $schedules;
	}

	/**
	 * @return array{retention_days:int}
	 */
	public static function settings(): array {
		$saved = get_option( self::OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'retention_days' => isset( $saved['retention_days'] ) ? max( 1, (int) $saved['retention_days'] ) : 30,
		);
	}

	/**
	 * Schedules the recurring cron only while at least one monitor exists, and the
	 * daily prune alongside it — mirrors ScheduledAudit's "no work, no cron" policy
	 * so a site that never adds a monitor carries zero uptime overhead.
	 */
	public function maybe_schedule(): void {
		$has_monitors = $this->store->count_monitors() > 0;

		if ( ! $has_monitors ) {
			if ( wp_next_scheduled( self::CRON_HOOK ) ) {
				wp_clear_scheduled_hook( self::CRON_HOOK );
			}
			if ( wp_next_scheduled( self::PRUNE_HOOK ) ) {
				wp_clear_scheduled_hook( self::PRUNE_HOOK );
			}
			return;
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, self::SCHEDULE_ID, self::CRON_HOOK );
		}
		if ( ! wp_next_scheduled( self::PRUNE_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::PRUNE_HOOK );
		}
	}

	/**
	 * Cron entry point: check every monitor whose interval has elapsed.
	 */
	public function run(): void {
		$now = gmdate( 'Y-m-d H:i:s' );
		foreach ( $this->store->due_monitors( self::MAX_PER_RUN, $now ) as $monitor ) {
			$this->check_monitor( $monitor );
		}
	}

	public function prune(): void {
		$this->store->prune_checks( self::settings()['retention_days'] );
	}

	/**
	 * Runs one HTTP check for a monitor, records the result, updates the monitor
	 * row, and emails on a confirmed state change. Returns the resolved check
	 * outcome so callers (e.g. the "Check now" REST action) can surface it.
	 *
	 * @param array<string, mixed> $monitor
	 * @return array{status:string, code:int, response_ms:int, error:string, changed:bool}
	 */
	public function check_monitor( array $monitor ): array {
		$id      = (int) $monitor['id'];
		$timeout = max( 1, (int) ( $monitor['timeout_seconds'] ?? 10 ) );
		$method  = 'HEAD' === strtoupper( (string) ( $monitor['method'] ?? 'GET' ) ) ? 'HEAD' : 'GET';
		$url      = (string) $monitor['url'];

		$start    = microtime( true );
		$response = wp_remote_request(
			$url,
			array(
				'method'      => $method,
				'timeout'     => $timeout,
				'redirection' => 5,
				'sslverify'   => true,
				'user-agent'  => 'SEOistic-Uptime/' . SEOISTIC_VERSION . '; ' . home_url( '/' ),
				'headers'     => array( 'Accept' => '*/*' ),
			)
		);
		$elapsed_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

		if ( is_wp_error( $response ) ) {
			$outcome = array(
				'status'      => 'down',
				'code'        => 0,
				'response_ms' => $elapsed_ms,
				'error'       => $this->truncate( $response->get_error_message() ),
			);
		} else {
			$code    = (int) wp_remote_retrieve_response_code( $response );
			$body    = 'GET' === $method ? (string) wp_remote_retrieve_body( $response ) : '';
			$keyword = trim( (string) ( $monitor['keyword'] ?? '' ) );
			$eval    = self::evaluate( $code, (int) ( $monitor['expected_code'] ?? 0 ), $keyword, $body );
			$outcome = array(
				'status'      => $eval['ok'] ? 'up' : 'down',
				'code'        => $code,
				'response_ms' => $elapsed_ms,
				'error'       => $eval['ok'] ? '' : $eval['reason'],
			);
		}

		$this->store->insert_check(
			array(
				'monitor_id'  => $id,
				'status'      => $outcome['status'],
				'code'        => $outcome['code'],
				'response_ms' => $outcome['response_ms'],
				'error'       => '' !== $outcome['error'] ? $outcome['error'] : null,
			)
		);

		$transition = self::next_state(
			(string) ( $monitor['current_status'] ?? 'unknown' ),
			(int) ( $monitor['fails'] ?? 0 ),
			$outcome['status']
		);

		$update = array(
			'fails'            => $transition['fails'],
			'last_checked'    => gmdate( 'Y-m-d H:i:s' ),
			'last_code'       => $outcome['code'],
			'last_response_ms' => $outcome['response_ms'],
			'last_error'      => '' !== $outcome['error'] ? $this->truncate( $outcome['error'] ) : null,
		);
		if ( $transition['changed'] ) {
			$update['current_status']     = $transition['status'];
			$update['last_status_change'] = gmdate( 'Y-m-d H:i:s' );
		}
		if ( 0 === strpos( $url, 'https://' ) && ! is_wp_error( $response ) ) {
			$ssl = $this->ssl_expiry( $url );
			if ( null !== $ssl ) {
				$update['ssl_expires'] = $ssl;
			}
		}
		$this->store->update_monitor( $id, $update );

		if ( $transition['changed'] ) {
			$this->notify( $monitor, $transition['status'], $outcome );
			/**
			 * Fires when a monitor's confirmed up/down state changes. Lets the
			 * Business Automator integration (or any addon) mirror the event.
			 *
			 * @param array<string,mixed> $monitor    Monitor row.
			 * @param string              $new_status 'up' | 'down'.
			 * @param array<string,mixed> $outcome    Check outcome.
			 */
			do_action( 'seoistic/uptime_status_changed', $monitor, $transition['status'], $outcome );
		}

		return $outcome + array( 'changed' => $transition['changed'] );
	}

	/**
	 * Pure check evaluation. `$expected` of 0 means "any 2xx or 3xx is healthy";
	 * a specific code requires an exact match. An optional keyword must appear in
	 * the response body (GET only).
	 *
	 * @return array{ok:bool, reason:string}
	 */
	public static function evaluate( int $code, int $expected, string $keyword, string $body ): array {
		if ( 0 === $expected ) {
			if ( $code < 200 || $code >= 400 ) {
				return array( 'ok' => false, 'reason' => sprintf( 'HTTP %d', $code ) );
			}
		} elseif ( $code !== $expected ) {
			return array( 'ok' => false, 'reason' => sprintf( 'HTTP %d (expected %d)', $code, $expected ) );
		}

		if ( '' !== $keyword && false === stripos( $body, $keyword ) ) {
			return array( 'ok' => false, 'reason' => 'Keyword not found' );
		}

		return array( 'ok' => true, 'reason' => '' );
	}

	/**
	 * Pure state machine. A monitor only flips to "down" after CONFIRMATIONS
	 * consecutive failures; it recovers to "up" on the first success. `changed`
	 * reports whether the confirmed status actually transitioned this check.
	 *
	 * @return array{status:string, fails:int, changed:bool}
	 */
	public static function next_state( string $current, int $fails, string $result ): array {
		if ( 'up' === $result ) {
			return array(
				'status'  => 'up',
				'fails'   => 0,
				'changed' => 'up' !== $current,
			);
		}

		$fails = $fails + 1;
		if ( $fails >= self::CONFIRMATIONS ) {
			return array(
				'status'  => 'down',
				'fails'   => $fails,
				'changed' => 'down' !== $current,
			);
		}

		// Failing but not yet confirmed — keep the current visible status.
		return array(
			'status'  => 'up' === $current ? 'up' : $current,
			'fails'   => $fails,
			'changed' => false,
		);
	}

	/**
	 * Best-effort SSL certificate expiry (GMT datetime) for an https URL. Returns
	 * null when the socket can't be opened or the peer certificate is unreadable —
	 * uptime checking never depends on this.
	 */
	private function ssl_expiry( string $url ): ?string {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return null;
		}
		$port    = (int) ( wp_parse_url( $url, PHP_URL_PORT ) ?: 443 );
		$context = stream_context_create( array( 'ssl' => array( 'capture_peer_cert' => true, 'verify_peer' => false, 'verify_peer_name' => false ) ) );
		$client  = @stream_socket_client( "ssl://{$host}:{$port}", $errno, $errstr, 8, STREAM_CLIENT_CONNECT, $context ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $client ) {
			return null;
		}
		$params = stream_context_get_params( $client );
		fclose( $client );
		$cert = $params['options']['ssl']['peer_certificate'] ?? null;
		if ( ! $cert ) {
			return null;
		}
		$parsed = openssl_x509_parse( $cert );
		if ( ! is_array( $parsed ) || empty( $parsed['validTo_time_t'] ) ) {
			return null;
		}
		return gmdate( 'Y-m-d H:i:s', (int) $parsed['validTo_time_t'] );
	}

	/**
	 * @param array<string, mixed> $monitor
	 * @param array<string, mixed> $outcome
	 */
	private function notify( array $monitor, string $status, array $outcome ): void {
		$to = trim( (string) ( $monitor['notify_email'] ?? '' ) );
		$to = '' !== $to ? $to : (string) get_option( 'admin_email' );
		if ( '' === $to ) {
			return;
		}

		$label = (string) $monitor['label'];
		$url   = (string) $monitor['url'];

		if ( 'down' === $status ) {
			/* translators: 1: monitor label, 2: site name. */
			$subject = sprintf( __( '[%2$s] SEOISTIC Uptime: %1$s is DOWN', 'seoistic' ), $label, get_bloginfo( 'name' ) );
			$body    = sprintf(
				/* translators: 1: URL, 2: reason/error, 3: HTTP code. */
				__( "%1\$s appears to be down.\n\nReason: %2\$s\nHTTP status: %3\$d\n\nSEOISTIC will email you again when it recovers.", 'seoistic' ),
				$url,
				'' !== $outcome['error'] ? $outcome['error'] : __( 'Unknown', 'seoistic' ),
				(int) $outcome['code']
			);
		} else {
			/* translators: 1: monitor label, 2: site name. */
			$subject = sprintf( __( '[%2$s] SEOISTIC Uptime: %1$s is back UP', 'seoistic' ), $label, get_bloginfo( 'name' ) );
			$body    = sprintf(
				/* translators: 1: URL, 2: response time in ms. */
				__( "%1\$s is responding normally again.\n\nResponse time: %2\$d ms", 'seoistic' ),
				$url,
				(int) $outcome['response_ms']
			);
		}

		wp_mail( $to, $subject, $body );
	}

	private function truncate( string $value, int $length = 255 ): string {
		$value = trim( $value );
		return strlen( $value ) > $length ? substr( $value, 0, $length ) : $value;
	}
}
