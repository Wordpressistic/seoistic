<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AiSearch;

/**
 * Compact option-based AI crawler analytics. No table is needed: the bounded
 * 30-day buckets keep storage predictable even on high-traffic sites.
 */
final class AiCrawlerStore {

	public const OPTION = 'seoistic_ai_crawler_stats';
	private const WINDOW = 30;
	private const MAX_URLS = 1000;

	private const BOTS = array(
		'gptbot'          => 'GPTBot',
		'claudebot'       => 'ClaudeBot',
		'perplexitybot'   => 'PerplexityBot',
		'google-extended' => 'Google-Extended',
		'ccbot'           => 'CCBot',
	);

	public function register(): void {
		add_action( 'template_redirect', array( $this, 'record_frontend_request' ), 1 );
		add_action( 'rest_api_init', array( $this, 'register_beacon' ) );
	}

	public function register_beacon(): void {
		register_rest_route(
			'seoistic/v1',
			'/aeo/beacon',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_beacon' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'bot' => array(
						'required'          => true,
						'type'              => 'string',
						'enum'              => array_keys( self::BOTS ),
						'sanitize_callback' => 'sanitize_key',
					),
					'url' => array(
						'required'          => true,
						'type'              => 'string',
						'format'            => 'uri',
						'sanitize_callback' => 'esc_url_raw',
					),
				),
			)
		);
	}

	public function handle_beacon( $request ) {
		$url = (string) $request->get_param( 'url' );
		$bot = (string) $request->get_param( 'bot' );
		$detected = $this->bot_from_user_agent();
		if ( '' === $url || '' === $bot || ! $this->is_local_url( $url ) || null === $detected || $detected !== $bot ) {
			return new \WP_Error( 'seoistic_invalid_beacon', __( 'Invalid crawler beacon.', 'seoistic' ), array( 'status' => 400 ) );
		}

		$this->record( $bot, $url );
		return rest_ensure_response( array( 'success' => true ) );
	}

	public function record_frontend_request(): void {
		$bot = $this->bot_from_user_agent();
		if ( null === $bot || is_admin() ) {
			return;
		}
		$this->record( $bot, home_url( (string) ( $_SERVER['REQUEST_URI'] ?? '/' ) ) );
	}

	/**
	 * @return array{total:int, days:list<array{date:string,bot:string,visits:int}>, bots:list<array{bot:string,visits:int}>, urls:list<array{url:string,visits:int,last_visit:string}>}
	 */
	public function dashboard( int $days = 30 ): array {
		$data = $this->normalized();
		$this->compact( $data );
		update_option( self::OPTION, $data, false );

		$cutoff = gmdate( 'Y-m-d', (int) current_time( 'timestamp', true ) - ( max( 1, min( self::WINDOW, $days ) ) - 1 ) * DAY_IN_SECONDS );
		$total = 0;
		$by_day = array();
		$by_bot = array();
		$by_url = array();
		$cutoff_date = $cutoff;

		foreach ( $data['days'] as $day => $bots ) {
			if ( $day < $cutoff ) {
				continue;
			}
			foreach ( $bots as $bot => $visits ) {
				$visits = (int) $visits;
				$total += $visits;
				$by_day[ $day ][ $bot ] = $visits;
				$by_bot[ $bot ] = ( $by_bot[ $bot ] ?? 0 ) + $visits;
			}
		}
		foreach ( $data['urls'] as $url => $row ) {
			$visits = 0;
			foreach ( $row['days'] as $day => $count ) {
				if ( $day >= $cutoff_date ) {
					$visits += (int) $count;
				}
			}
			if ( $visits > 0 ) {
				$by_url[] = array(
					'url' => $url,
					'visits' => $visits,
					'last_visit' => (string) ( $row['last_visit'] ?? '' ),
				);
			}
		}

		$bots = array();
		foreach ( $by_bot as $bot => $visits ) {
			$bots[] = array( 'bot' => $bot, 'visits' => $visits );
		}
		usort( $bots, static fn( array $left, array $right ): int => $right['visits'] <=> $left['visits'] ?: strcmp( $left['bot'], $right['bot'] ) );
		usort( $by_url, static fn( array $left, array $right ): int => $right['visits'] <=> $left['visits'] ?: strcmp( $left['url'], $right['url'] ) );

		$days = array();
		foreach ( $by_day as $day => $day_bots ) {
			foreach ( $day_bots as $bot => $visits ) {
				$days[] = array(
					'date' => $day,
					'bot' => $bot,
					'visits' => $visits,
				);
			}
		}
		usort( $days, static fn( array $left, array $right ): int => strcmp( $left['date'], $right['date'] ) ?: strcmp( $left['bot'], $right['bot'] ) );

		return array(
			'total' => $total,
			'days' => $days,
			'bots' => $bots,
			'urls' => array_slice( $by_url, 0, 10 ),
		);
	}

	public function bot_from_user_agent( ?string $user_agent = null ): ?string {
		$user_agent = strtolower( (string) ( $user_agent ?? ( $_SERVER['HTTP_USER_AGENT'] ?? '' ) ) );
		foreach ( self::BOTS as $needle => $bot ) {
			if ( false !== strpos( $user_agent, $needle ) ) {
				return $bot;
			}
		}
		return null;
	}

	private function record( string $bot, string $url ): void {
		if ( ! in_array( $bot, self::BOTS, true ) || ! $this->is_local_url( $url ) ) {
			return;
		}

		$data = $this->normalized();
		$day = gmdate( 'Y-m-d', (int) current_time( 'timestamp', true ) );
		$path = $this->normalize_url( $url );
		$now = current_time( 'mysql', true );

		$data['days'][ $day ][ $bot ] = (int) ( $data['days'][ $day ][ $bot ] ?? 0 ) + 1;
		if ( ! isset( $data['urls'][ $path ] ) ) {
			$data['urls'][ $path ] = array( 'days' => array(), 'last_visit' => $now );
		}
		$data['urls'][ $path ]['days'][ $day ] = (int) ( $data['urls'][ $path ]['days'][ $day ] ?? 0 ) + 1;
		$data['urls'][ $path ]['last_visit'] = $now;

		$this->compact( $data );
		update_option( self::OPTION, $data, false );
	}

	private function is_local_url( string $url ): bool {
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$home = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		return '' !== $host && ( $host === $home || 'www.' . $host === $home || $host === 'www.' . $home );
	}

	private function normalize_url( string $url ): string {
		$parts = (array) wp_parse_url( $url );
		$path = '/' . trim( (string) ( $parts['path'] ?? '' ), '/' );
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		return esc_url_raw( home_url( $path . $query ) );
	}

	private function normalized(): array {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();
		return array(
			'days' => isset( $stored['days'] ) && is_array( $stored['days'] ) ? $stored['days'] : array(),
			'urls' => isset( $stored['urls'] ) && is_array( $stored['urls'] ) ? $stored['urls'] : array(),
		);
	}

	private function compact( array &$data ): void {
		$cutoff = gmdate( 'Y-m-d', (int) current_time( 'timestamp', true ) - ( self::WINDOW - 1 ) * DAY_IN_SECONDS );
		foreach ( array_keys( $data['days'] ) as $day ) {
			if ( $day < $cutoff ) {
				unset( $data['days'][ $day ] );
			}
		}
		foreach ( $data['urls'] as $url => $row ) {
			if ( ! is_array( $row ) ) {
				unset( $data['urls'][ $url ] );
				continue;
			}
			$days = is_array( $row['days'] ?? null ) ? $row['days'] : array();
			foreach ( array_keys( $days ) as $day ) {
				if ( $day < $cutoff ) {
					unset( $data['urls'][ $url ]['days'][ $day ] );
				}
			}
			if ( array() === $data['urls'][ $url ]['days'] ) {
				unset( $data['urls'][ $url ] );
			}
		}
		if ( count( $data['urls'] ) > self::MAX_URLS ) {
			uasort(
				$data['urls'],
				static function ( array $left, array $right ): int {
					return strcmp( (string) ( $right['last_visit'] ?? '' ), (string) ( $left['last_visit'] ?? '' ) );
				}
			);
			$data['urls'] = array_slice( $data['urls'], 0, self::MAX_URLS, true );
		}
	}
}
