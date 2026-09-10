<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core\Performance;

use Wpistic\Seoistic\Core\AI\ProxyClient;
use Wpistic\Seoistic\Core\HtaccessManager;
use WP_Error;

/**
 * PageSpeed transport, normalized Core Web Vitals history, thresholds, and
 * deterministic Quick Wins previews/applies. History is an option (not a table)
 * and is capped at one snapshot per strategy per week.
 */
final class PerformanceService {

	public const HISTORY_OPTION = 'seoistic_cwv_history';
	public const SETTINGS_OPTION = 'seoistic_performance_settings';
	public const DEFAULT_LCP = 2.5;
	public const DEFAULT_CLS = 0.1;
	public const DEFAULT_INP = 0.2;
	public const CRON_TEST_HOOK = 'seoistic_cwv_test';
	public const CRON_ALERT_HOOK = 'seoistic_cwv_alert';

	/**
	 * One entry per strategy per ISO-adjacent week; roughly one year of history.
	 */
	private const HISTORY_LIMIT = 52;

	private ProxyClient $proxy;

	public function __construct( ?ProxyClient $proxy = null ) {
		$this->proxy = $proxy ?? new ProxyClient();
	}

	/** @return array{enabled:bool,strategy:string,notify_email:string,hero_url:string,lcp:float,cls:float,inp:float} */
	public static function settings(): array {
		$defaults = array(
			'enabled'      => false,
			'strategy'     => 'mobile',
			'notify_email' => '',
			'hero_url'     => '',
			'lcp'          => self::DEFAULT_LCP,
			'cls'          => self::DEFAULT_CLS,
			'inp'          => self::DEFAULT_INP,
		);
		$saved = get_option( self::SETTINGS_OPTION, array() );
		return self::sanitize_settings( array_merge( $defaults, is_array( $saved ) ? $saved : array() ) );
	}

	public static function save_settings( array $raw ): void {
		update_option( self::SETTINGS_OPTION, self::sanitize_settings( $raw ), false );
	}

	public static function sanitize_settings( array $raw ): array {
		$email = is_email( (string) ( $raw['notify_email'] ?? '' ) );
		return array(
			'enabled'      => ! empty( $raw['enabled'] ),
			'strategy'     => in_array( (string) ( $raw['strategy'] ?? '' ), array( 'mobile', 'desktop' ), true ) ? (string) $raw['strategy'] : 'mobile',
			'notify_email' => $email ? $email : '',
			'hero_url'     => esc_url_raw( (string) ( $raw['hero_url'] ?? '' ) ),
			'lcp'          => self::metric( $raw['lcp'] ?? self::DEFAULT_LCP, 0.5, 10, self::DEFAULT_LCP ),
			'cls'          => self::metric( $raw['cls'] ?? self::DEFAULT_CLS, 0, 1, self::DEFAULT_CLS ),
			'inp'          => self::metric( $raw['inp'] ?? self::DEFAULT_INP, 0.05, 2, self::DEFAULT_INP ),
		);
	}

	public function register(): void {
		$this->register_cron();
		$this->register_frontend();
	}

	public function register_cron(): void {
		add_filter( 'cron_schedules', array( $this, 'register_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval
		add_action( self::CRON_TEST_HOOK, array( $this, 'run_weekly_test' ) );
		add_action( self::CRON_ALERT_HOOK, array( $this, 'run_alert_check' ) );
		$this->maybe_schedule();
	}

	public function register_frontend(): void {
		add_filter( 'wp_lazy_loading_enabled', array( $this, 'maybe_enable_lazy_loading' ), 999 );
		add_action( 'wp_head', array( $this, 'preload_hero' ), 2 );
	}

	public function maybe_enable_lazy_loading( bool $enabled ): bool {
		return self::quick_win_enabled( 'lazy_load' ) ? true : $enabled;
	}

	public function preload_hero(): void {
		if ( ! self::quick_win_enabled( 'preload_hero' ) ) {
			return;
		}
		$url = self::hero_url();
		if ( '' !== $url ) {
			echo '<link rel="preload" href="' . esc_url( $url ) . '" as="image">' . "\n";
		}
	}

	public static function hero_url(): string {
		$settings = self::settings();
		if ( '' !== $settings['hero_url'] ) {
			return $settings['hero_url'];
		}
		if ( function_exists( 'wp_get_attachment_image_src' ) && current_theme_supports( 'custom-logo' ) ) {
			$logo_id = get_theme_mod( 'custom_logo' );
			$logo = is_numeric( $logo_id ) ? wp_get_attachment_image_src( (int) $logo_id, 'full' ) : false;
			if ( $logo && ! empty( $logo[0] ) ) {
				return esc_url_raw( (string) $logo[0] );
			}
		}
		return '';
	}

	public static function quick_win_enabled( string $action ): bool {
		$state = get_option( 'seoistic_quick_wins', array() );
		return is_array( $state ) && ! empty( $state[ $action ] );
	}

	/**
	 * @param array<string, array{interval:int, display:string}> $schedules
	 * @return array<string, array{interval:int, display:string}>
	 */
	public function register_schedule( $schedules ) {
		$schedules['seoistic_weekly_cwv'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly (SEOISTIC Core Web Vitals)', 'seoistic' ),
		);
		return $schedules;
	}

	public function maybe_schedule(): void {
		$settings = self::settings();
		$next = wp_next_scheduled( self::CRON_TEST_HOOK );
		if ( $settings['enabled'] ) {
			if ( ! $next ) {
				wp_schedule_event( time() + HOUR_IN_SECONDS, 'seoistic_weekly_cwv', self::CRON_TEST_HOOK );
			}
		} elseif ( $next ) {
			wp_clear_scheduled_hook( self::CRON_TEST_HOOK );
		}

		if ( ! wp_next_scheduled( self::CRON_ALERT_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_ALERT_HOOK );
		}
	}

	public function run_weekly_test(): void {
		$settings = self::settings();
		$result = $this->run( home_url( '/' ), $settings['strategy'], true );
		if ( ! is_wp_error( $result ) ) {
			$this->send_threshold_alert( $result );
		}
	}

	public function run_alert_check(): void {
		$latest = self::latest();
		if ( null !== $latest ) {
			$this->send_threshold_alert( $latest );
		}
	}

	/**
	 * @return array<string, mixed>|WP_Error normalized array on success
	 */
	public function run( string $url, string $strategy = 'mobile', bool $save = true ) {
		$strategy = in_array( $strategy, array( 'mobile', 'desktop' ), true ) ? $strategy : 'mobile';
		$response = $this->proxy->post(
			'/v1/psi',
			array(
				'url'       => esc_url_raw( $url ),
				'strategy'  => $strategy,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$metrics = self::extract_metrics( $response );
		if ( null === $metrics ) {
			return new WP_Error(
				'seoistic_psi_invalid_response',
				__( 'PageSpeed Insights did not return usable Core Web Vitals data for this URL.', 'seoistic' ),
				array( 'status' => 502 )
			);
		}

		$normalized = array(
			'time'          => gmdate( 'c' ),
			'timestamp'     => time(),
			'url'           => esc_url_raw( $url ),
			'strategy'      => $strategy,
			'lcp'           => $metrics['lcp'],
			'cls'           => $metrics['cls'],
			'inp'           => $metrics['inp'],
			'score'         => isset( $response['score'] ) ? self::bound_int( $response['score'], 0, 100 ) : null,
			'lab'           => isset( $response['lab'] ) && is_array( $response['lab'] ) ? map_deep( $response['lab'], 'sanitize_text_field' ) : array(),
			'opportunities' => isset( $response['opportunities'] ) && is_array( $response['opportunities'] ) ? self::sanitize_opportunities( $response['opportunities'] ) : array(),
		);
		if ( $save ) {
			self::record( $normalized );
		}
		return $normalized;
	}

	/**
	 * Supports both a compact proxy response (`metrics`) and PSI's raw
	 * `loadingExperience.metrics` audit shape.
	 *
	 * @param array<string, mixed> $response
	 * @return array{lcp:float,cls:float,inp:float}|null
	 */
	public static function extract_metrics( array $response ): ?array {
		$compact = $response['metrics'] ?? null;
		if ( is_array( $compact ) ) {
			$lcp = $compact['lcp'] ?? null;
			$cls = $compact['cls'] ?? null;
			$inp = $compact['inp'] ?? null;
			if ( is_numeric( $lcp ) && is_numeric( $cls ) && is_numeric( $inp ) ) {
				return array(
					'lcp' => round( (float) $lcp, 3 ),
					'cls' => round( (float) $cls, 3 ),
					'inp' => round( (float) $inp, 3 ),
				);
			}
		}

		$experience = $response['loadingExperience'] ?? null;
		if ( ! is_array( $experience ) || ! is_array( $experience['metrics'] ?? null ) ) {
			return null;
		}
		$values = array();
		foreach ( $experience['metrics'] as $key => $detail ) {
			if ( is_array( $detail ) && isset( $detail['percentile'] ) && is_numeric( $detail['percentile'] ) ) {
				$values[ strtolower( (string) $key ) ] = (float) $detail['percentile'];
			}
		}
		$raw_lcp = $values['largest_contentful_paint'] ?? null;
		$raw_cls = $values['cumulative_layout_shift'] ?? null;
		$raw_inp = $values['interaction_to_next_paint'] ?? null;
		if ( null === $raw_lcp || null === $raw_cls || null === $raw_inp ) {
			return null;
		}
		return array(
			'lcp' => round( $raw_lcp / 1000, 3 ),
			'cls' => round( $raw_cls / 1000, 3 ),
			'inp' => round( $raw_inp / 1000, 3 ),
		);
	}

	/** @return array<int, array<string, mixed>> */
	public static function history(): array {
		$history = get_option( self::HISTORY_OPTION, array() );
		return is_array( $history ) ? array_values( $history ) : array();
	}

	/** @param array<string, mixed> $entry */
	public static function record( array $entry ): void {
		$entry['timestamp'] = time();
		$entry['week']      = self::week_number( (int) $entry['timestamp'] );
		$history = self::history();
		foreach ( $history as $index => $existing ) {
			if ( ( $existing['strategy'] ?? '' ) === ( $entry['strategy'] ?? '' ) && (int) ( $existing['week'] ?? 0 ) === $entry['week'] ) {
				$history[ $index ] = array_merge( $existing, $entry );
				self::save_history( $history );
				return;
			}
		}
		$history[] = $entry;
		usort( $history, static fn( array $left, array $right ): int => (int) ( $right['timestamp'] ?? 0 ) <=> (int) ( $left['timestamp'] ?? 0 ) );
		self::save_history( $history );
	}

	public static function latest(): ?array {
		$settings = self::settings();
		foreach ( self::history() as $entry ) {
			if ( ( $entry['strategy'] ?? '' ) === $settings['strategy'] ) {
				return $entry;
			}
		}
		return null;
	}

	/** @return array<string, array{value:?float,change:?float,tone:string,label:string}> */
	public static function dashboard_cards(): array {
		$history = array_values( array_filter(
			self::history(),
			static fn( array $entry ): bool => ( $entry['strategy'] ?? '' ) === self::settings()['strategy']
		) );
		$current = $history[0] ?? null;
		$previous = $history[1] ?? null;
		$cards = array();
		foreach ( array( 'lcp', 'cls', 'inp' ) as $metric ) {
			$value = null === $current ? null : ( is_numeric( $current[ $metric ] ?? null ) ? round( (float) $current[ $metric ], 3 ) : null );
			$previous_value = null === $previous ? null : ( is_numeric( $previous[ $metric ] ?? null ) ? round( (float) $previous[ $metric ], 3 ) : null );
			$change = null === $value || null === $previous_value || 0.0 === $previous_value ? null : round( ( ( $value - $previous_value ) / $previous_value ) * 100, 1 );
			$cards[ $metric ] = array(
				'value' => $value,
				'change' => $change,
				'tone' => null === $value ? 'neutral' : self::metric_tone( $metric, $value ),
				'label' => self::metric_label( $metric ),
			);
		}
		return $cards;
	}

	public static function metric_tone( string $metric, float $value ): string {
		if ( 'cls' === $metric ) {
			return $value <= 0.1 ? 'good' : ( $value <= 0.25 ? 'warn' : 'bad' );
		}
		$good = 'lcp' === $metric ? 2.5 : 0.2;
		$warn = 'lcp' === $metric ? 4 : 0.5;
		return $value <= $good ? 'good' : ( $value <= $warn ? 'warn' : 'bad' );
	}

	public static function metric_label( string $metric ): string {
		return match ( $metric ) {
			'lcp' => __( 'Largest Contentful Paint', 'seoistic' ),
			'cls' => __( 'Cumulative Layout Shift', 'seoistic' ),
			default => __( 'Interaction to Next Paint', 'seoistic' ),
		};
	}

	public static function format_metric( string $metric, float $value ): string {
		if ( 'cls' === $metric ) {
			return number_format_i18n( $value, 3 );
		}
		/* translators: %s: duration in seconds. */
		return sprintf( __( '%s s', 'seoistic' ), number_format_i18n( $value, 3 ) );
	}

	/** @return array<int, array{metric:string,value:float,threshold:float}> */
	private function threshold_exceeded( array $entry ): array {
		$settings = self::settings();
		$exceeded = array();
		foreach ( array( 'lcp', 'cls', 'inp' ) as $metric ) {
			$value = (float) ( $entry[ $metric ] ?? 0 );
			if ( $value > (float) $settings[ $metric ] ) {
				$exceeded[] = array( 'metric' => $metric, 'value' => $value, 'threshold' => (float) $settings[ $metric ] );
			}
		}
		return $exceeded;
	}

	private function send_threshold_alert( array $entry ): bool {
		$settings = self::settings();
		if ( ! $settings['enabled'] ) {
			return false;
		}
		$exceeded = $this->threshold_exceeded( $entry );
		if ( array() === $exceeded ) {
			return false;
		}
		$lines = array();
		foreach ( $exceeded as $item ) {
			$lines[] = sprintf(
				'%1$s: %2$s (threshold: %3$s)',
				self::metric_label( $item['metric'] ),
				self::format_metric( $item['metric'], $item['value'] ),
				self::format_metric( $item['metric'], $item['threshold'] )
			);
		}
		$to = '' !== $settings['notify_email'] ? $settings['notify_email'] : (string) get_option( 'admin_email' );
		return (bool) wp_mail(
			$to,
			sprintf(
				/* translators: %s: site name. */
				__( '[%s] SEOistic Core Web Vitals alert', 'seoistic' ),
				get_bloginfo( 'name' )
			),
			__( "The latest Core Web Vitals check exceeded your thresholds:\n\n", 'seoistic' ) . implode( "\n", $lines ) . "\n\n" . __( 'URL: ', 'seoistic' ) . (string) $entry['url']
		);
	}

	/**
	 * @return array{action:string,title:string,description:string,enabled:bool,rules:string,conflict:string}|array<never,never>
	 */
	public static function quick_win( string $action ): array {
		$definitions = self::quick_win_definitions();
		if ( ! isset( $definitions[ $action ] ) ) {
			return array();
		}
		$definition = $definitions[ $action ];
		$state      = get_option( 'seoistic_quick_wins', array() );
			$enabled    = self::quick_win_enabled( $action );
		return array(
			'action'       => $action,
			'title'        => $definition['title'],
			'description'  => $definition['description'],
			'enabled'      => $enabled,
			'rules'        => $definition['rules'],
			'conflict'     => $definition['conflict'],
		);
	}

	/** @return array<string, array{title:string,description:string,rules:string,conflict:string}> */
	private static function quick_win_definitions(): array {
		return array(
			'lazy_load' => array(
				'title' => __( 'Lazy-load images', 'seoistic' ),
				'description' => __( 'Adds browser lazy-loading to theme content images without JavaScript.', 'seoistic' ),
				'rules' => __( 'Adds loading="lazy" to eligible content images through WordPress native image markup.', 'seoistic' ),
				'conflict' => __( 'Some themes and optimization plugins may already add lazy-loading.', 'seoistic' ),
			),
			'preload_hero' => array(
				'title' => __( 'Preload the hero image', 'seoistic' ),
				'description' => __( 'Adds a preloaded resource hint for the site front page hero image.', 'seoistic' ),
				'rules' => '<link rel="preload" href="' . self::hero_url() . '" as="image">',
				'conflict' => __( 'Set a custom hero image URL below; otherwise the site custom logo is preloaded.', 'seoistic' ),
			),
			'render_blocking' => array(
				'title' => __( 'Reduce render-blocking assets', 'seoistic' ),
				'description' => __( 'Enables server-side script deferral when available, plus compression and long-lived asset caching.', 'seoistic' ),
				'rules' => "<IfModule mod_pagespeed.c>\nModPagespeed on\nModPagespeedEnableFilters defer_javascript\n</IfModule>\n<IfModule mod_deflate.c>\nAddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript application/json image/svg+xml\n</IfModule>\n<IfModule mod_expires.c>\nExpiresActive On\nExpiresByType image/jpeg \"access plus 1 year\"\nExpiresByType image/png \"access plus 1 year\"\nExpiresByType image/webp \"access plus 1 year\"\nExpiresByType image/svg+xml \"access plus 1 year\"\nExpiresByType text/css \"access plus 1 year\"\nExpiresByType application/javascript \"access plus 1 year\"\n</IfModule>",
				'conflict' => __( 'Other caching plugins may overwrite these headers or duplicate compression rules.', 'seoistic' ),
			),
		);
	}

	public static function quick_win_actions(): array {
		return array_keys( self::quick_win_definitions() );
	}

	public static function quick_wins(): array {
		$out = array();
		foreach ( self::quick_win_actions() as $action ) {
			$out[] = self::quick_win( $action );
		}
		return $out;
	}

	public static function quick_win_preview_token( string $action ): string {
		$definition = self::quick_win_definitions()[ $action ] ?? null;
		if ( null === $definition ) {
			return '';
		}
		return wp_hash( $action . '|' . $definition['rules'] . '|' . wp_salt( 'nonce' ), 'nonce' );
	}

	public static function set_quick_win( string $action, bool $enabled, ?HtaccessManager $htaccess = null ): array {
		if ( ! in_array( $action, self::quick_win_actions(), true ) ) {
			return array( 'success' => false, 'error' => __( 'Unknown performance optimization.', 'seoistic' ) );
		}
		$state = get_option( 'seoistic_quick_wins', array() );
		$state = is_array( $state ) ? $state : array();
		$state[ $action ] = $enabled ? 1 : 0;
		update_option( 'seoistic_quick_wins', $state, false );
		if ( ! $enabled || in_array( $action, array( 'lazy_load', 'preload_hero' ), true ) ) {
			return array( 'success' => true );
		}
		$definition = self::quick_win_definitions()[ $action ];
		return ( $htaccess ?? new HtaccessManager() )->apply(
			$definition['rules'],
			'seoistic_performance_' . $action
		);
	}

	private static function save_history( array $history ): void {
		update_option( self::HISTORY_OPTION, array_slice( array_values( $history ), 0, self::HISTORY_LIMIT ), false );
	}

	private static function week_number( int $timestamp ): int {
		return (int) floor( $timestamp / WEEK_IN_SECONDS );
	}

	private static function metric( mixed $value, float $min, float $max, float $default ): float {
		$value = is_numeric( $value ) ? round( (float) $value, 3 ) : $default;
		return round( min( $max, max( $min, $value ) ), 3 );
	}

	private static function bound_int( mixed $value, int $min, int $max ): int {
		return is_numeric( $value ) ? max( $min, min( $max, (int) $value ) ) : $min;
	}

	private static function sanitize_opportunities( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$out[] = array(
				'title' => sanitize_text_field( (string) ( $item['title'] ?? '' ) ),
				'description' => sanitize_text_field( (string) ( $item['description'] ?? '' ) ),
				'savings' => sanitize_text_field( (string) ( $item['savings'] ?? '' ) ),
			);
		}
		return $out;
	}
}
