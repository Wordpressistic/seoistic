<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core\AI;

use Wpistic\Seoistic\License\LicenseClient;
use WP_Error;

/**
 * Small, non-AI transport for WPistic services which authenticate with the
 * site's SEOistic license. Feature code never receives or stores service keys.
 */
final class ProxyClient {

	private const RETRY_DELAY_SECONDS = 2;

	private LicenseClient $license;

	public function __construct( ?LicenseClient $license = null ) {
		$this->license = $license ?? new LicenseClient();
	}

	/**
	 * @param array<string, mixed> $payload
	 * @return array<string, mixed>|WP_Error
	 */
	public function post( string $path, array $payload = array(), int $timeout = 45 ) {
		$key = (string) $this->license->key();
		if ( '' === $key ) {
			return new WP_Error(
				'seoistic_proxy_license_missing',
				__( 'Connect your SEOistic license to use this WPistic service.', 'seoistic' ),
				array( 'status' => 401, 'upgrade_card' => true )
			);
		}

		$body = array_merge(
			array(
				'license_key' => $key,
				'site_url'    => home_url( '/' ),
			),
			$payload
		);

		$response = $this->transport( $path, $body, $timeout );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( 429 === $status && ! defined( 'SEOISTIC_PROXY_TESTS' ) ) {
			sleep( self::RETRY_DELAY_SECONDS );
			$response = $this->transport( $path, $body, $timeout );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
		}
		if ( 429 === $status ) {
			return new WP_Error(
				'seoistic_proxy_rate_limited',
				__( 'The WPistic service is still busy after one retry. Please wait a moment and try again.', 'seoistic' ),
				array( 'status' => 503 )
			);
		}
		if ( in_array( $status, array( 401, 403 ), true ) ) {
			return new WP_Error(
				'seoistic_proxy_license_invalid',
				__( 'Connect a valid SEOistic license to use this WPistic service.', 'seoistic' ),
				array( 'status' => 402, 'upgrade_card' => true )
			);
		}
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		if ( $status < 200 || $status >= 300 || ! is_array( $decoded ) ) {
			return new WP_Error(
				'seoistic_proxy_error',
				__( 'The WPistic service is temporarily unavailable. Please try again shortly.', 'seoistic' ),
				array( 'status' => 502 )
			);
		}
		return $decoded;
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>|WP_Error
	 */
	private function transport( string $path, array $body, int $timeout ) {
		$url = '' !== $path && '/' !== substr( $path, 0, 1 ) ? '/' . $path : $path;
		$response = wp_remote_post(
			(string) apply_filters( 'seoistic/proxy/base_url', 'https://ai.wpistic.com' ) . $url,
			array(
				'timeout' => max( 5, $timeout ),
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'seoistic_proxy_network',
				__( 'Could not reach the WPistic service. Check your connection and try again.', 'seoistic' ),
				array( 'status' => 503 )
			);
		}
		return $response;
	}
}
