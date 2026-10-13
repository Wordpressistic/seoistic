<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Uptime;

/**
 * Pure validation/sanitisation for monitor input. Kept WordPress-free (aside from
 * a couple of small guards) so the URL policy is unit-testable in isolation.
 */
final class UptimeValidator {

	/**
	 * A monitor URL must be http(s) with a host, and must not target the cloud
	 * instance-metadata endpoint (a classic SSRF pivot). Private/LAN hosts are
	 * intentionally allowed: monitoring an internal staging host is a legitimate
	 * use, and the feature is gated to trusted admins. Returns the reason string
	 * on rejection, or '' when the URL is acceptable.
	 */
	public static function url_error( string $url ): string {
		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return 'Enter a full URL including http:// or https://.';
		}
		if ( ! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true ) ) {
			return 'Only http:// and https:// URLs can be monitored.';
		}
		$host = strtolower( (string) $parts['host'] );
		if ( '169.254.169.254' === $host || 'metadata.google.internal' === $host ) {
			return 'That host cannot be monitored.';
		}
		return '';
	}

	/**
	 * Normalise + sanitise a raw monitor payload into a DB-ready column array.
	 * `label`/`url` are required; everything else falls back to safe defaults.
	 * Returns [ 'error' => string ] when validation fails, or [ 'data' => array ].
	 *
	 * @param array<string, mixed> $raw
	 * @return array{error?:string, data?:array<string,mixed>}
	 */
	public static function prepare( array $raw ): array {
		$url = esc_url_raw( trim( (string) ( $raw['url'] ?? '' ) ) );
		if ( '' === $url ) {
			return array( 'error' => 'A URL is required.' );
		}
		$url_error = self::url_error( $url );
		if ( '' !== $url_error ) {
			return array( 'error' => $url_error );
		}

		$label = sanitize_text_field( (string) ( $raw['label'] ?? '' ) );
		if ( '' === $label ) {
			$host  = (string) wp_parse_url( $url, PHP_URL_HOST );
			$label = '' !== $host ? $host : $url;
		}

		$method   = strtoupper( (string) ( $raw['method'] ?? 'GET' ) );
		$method   = in_array( $method, array( 'GET', 'HEAD' ), true ) ? $method : 'GET';
		$expected = (int) ( $raw['expected_code'] ?? 0 );
		$expected = ( $expected >= 100 && $expected <= 599 ) ? $expected : 0;

		$interval = (int) ( $raw['interval_minutes'] ?? 5 );
		$interval = max( 5, min( 1440, $interval ) );
		$timeout  = (int) ( $raw['timeout_seconds'] ?? 10 );
		$timeout  = max( 1, min( 30, $timeout ) );

		$notify = sanitize_email( (string) ( $raw['notify_email'] ?? '' ) );

		return array(
			'data' => array(
				'label'            => mb_substr( $label, 0, 191 ),
				'url'              => $url,
				'method'           => $method,
				'expected_code'    => $expected,
				'keyword'          => mb_substr( sanitize_text_field( (string) ( $raw['keyword'] ?? '' ) ), 0, 191 ),
				'interval_minutes' => $interval,
				'timeout_seconds'  => $timeout,
				'enabled'          => empty( $raw['enabled'] ) ? 0 : 1,
				'notify_email'     => $notify,
			),
		);
	}
}
