<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Gsc;

use WP_Error;

/**
 * Google Search Console OAuth client. All Google traffic is read-only; access
 * tokens are short-lived and every API call retries once with a newly refreshed
 * token when Google returns HTTP 401.
 */
final class GscClient {

	private const SCOPE              = 'https://www.googleapis.com/auth/webmasters.readonly';
	private const ENDPOINT_AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const ENDPOINT_TOKEN     = 'https://oauth2.googleapis.com/token';
	private const ENDPOINT_SITES     = 'https://www.googleapis.com/webmasters/v3/sites';
	private const ENDPOINT_INSPECT   = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
	private const TOKEN_TRANSIENT    = 'seoistic_gsc_access_token';

	public static function redirect_uri(): string {
		return admin_url( 'admin-post.php?action=seoistic_gsc_oauth_callback' );
	}

	public function authorize_url(): string {
		return add_query_arg(
			array(
				'client_id'     => rawurlencode( GscSettings::client_id() ),
				'redirect_uri'  => rawurlencode( self::redirect_uri() ),
				'response_type' => 'code',
				'scope'         => rawurlencode( self::SCOPE ),
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'state'         => GscSettings::new_oauth_state(),
			),
			self::ENDPOINT_AUTHORIZE
		);
	}

	/**
	 * @return array{success:bool, error?:string, error_code?:string, access_denied?:bool}
	 */
	public function exchange_code( string $code ): array {
		$response = wp_remote_post(
			self::ENDPOINT_TOKEN,
			array(
				'body'    => array(
					'code'          => $code,
					'client_id'     => GscSettings::client_id(),
					'client_secret' => GscSettings::client_secret(),
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}

		$body   = $this->decode_body( wp_remote_retrieve_body( $response ) );
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 || empty( $body['refresh_token'] ) ) {
			if ( $this->is_access_denied( $body ) ) {
				return array(
					'success'       => false,
					'error_code'    => 'access_denied',
					'access_denied' => true,
					'error'         => __( 'Google denied authorization before OAuth could finish.', 'seoistic' ),
				);
			}

			$message = isset( $body['error_description'] ) && is_string( $body['error_description'] )
				? $body['error_description']
				: __( 'Google did not return a refresh token. Disconnect any prior authorization for this app at myaccount.google.com/permissions, then reconnect.', 'seoistic' );
			return array( 'success' => false, 'error' => $message );
		}

		GscSettings::set_refresh_token( (string) $body['refresh_token'] );
		if ( ! empty( $body['access_token'] ) ) {
			$this->store_access_token( (string) $body['access_token'], (int) ( $body['expires_in'] ?? 3500 ) );
		}
		return array( 'success' => true );
	}

	/**
	 * @return array{success:bool, data?:array<int,array{siteUrl:string,permissionLevel:string}>, error?:string, error_code?:string, access_denied?:bool}
	 */
	public function list_sites(): array {
		$response = $this->api_request(
			'GET',
			self::ENDPOINT_SITES,
			array( 'timeout' => 15 )
		);
		$result   = $this->handle_response( $response );
		if ( ! $result['success'] ) {
			return $result;
		}
		return array( 'success' => true, 'data' => (array) ( $result['data']['siteEntry'] ?? array() ) );
	}

	/**
	 * @return array{success:bool, data?:array<string,mixed>, error?:string, error_code?:string, access_denied?:bool}
	 */
	public function test_connection(): array {
		$sites = $this->list_sites();
		if ( ! $sites['success'] ) {
			return $sites;
		}

		$properties = array();
		foreach ( $sites['data'] ?? array() as $site ) {
			$properties[] = (string) ( $site['siteUrl'] ?? '' );
		}
		$selected = GscSettings::site_url();
		$matches  = '' !== $selected && in_array( $selected, $properties, true ) && $this->property_matches_home( $selected );
		GscSettings::set_property_match( $matches );

		return array(
			'success' => $matches,
			'data'    => array(
				'properties'     => $properties,
				'selected'       => $selected,
				'property_match' => $matches,
				'redirect_uri'   => self::redirect_uri(),
				'home_url'       => home_url( '/' ),
			),
			'error'    => $matches ? '' : __( 'OAuth works, but the selected Search Console property does not match this site URL.', 'seoistic' ),
		);
	}

	/**
	 * @param array{start_date?:string, end_date?:string, dimensions?:list<string>, row_limit?:int} $args
	 * @return array{success:bool, data?:array<int,array<string,mixed>>, error?:string, error_code?:string, access_denied?:bool}
	 */
	public function search_analytics( array $args = array() ): array {
		$site_url = GscSettings::site_url();
		if ( '' === $site_url ) {
			return array( 'success' => false, 'error' => __( 'No Search Console property selected.', 'seoistic' ) );
		}

		$body = array(
			'startDate'  => $args['start_date'] ?? gmdate( 'Y-m-d', strtotime( '-28 days' ) ),
			'endDate'    => $args['end_date'] ?? gmdate( 'Y-m-d', strtotime( '-3 days' ) ),
			'dimensions' => $args['dimensions'] ?? array( 'query' ),
			'rowLimit'   => $args['row_limit'] ?? 20,
		);

		$response = $this->api_request(
			'POST',
			self::ENDPOINT_SITES . '/' . rawurlencode( $site_url ) . '/searchAnalytics/query',
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 20,
			)
		);
		$result   = $this->handle_response( $response );
		if ( ! $result['success'] ) {
			return $result;
		}
		return array( 'success' => true, 'data' => (array) ( $result['data']['rows'] ?? array() ) );
	}

	/**
	 * @return array{success:bool, data?:array<string,mixed>, error?:string, error_code?:string, access_denied?:bool}
	 */
	public function inspect_url( string $url ): array {
		$site_url = GscSettings::site_url();
		if ( '' === $site_url ) {
			return array( 'success' => false, 'error' => __( 'No Search Console property selected.', 'seoistic' ) );
		}

		$response = $this->api_request(
			'POST',
			self::ENDPOINT_INSPECT,
			array(
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'inspectionUrl' => $url, 'siteUrl' => $site_url ) ),
				'timeout' => 20,
			)
		);
		$result   = $this->handle_response( $response );
		if ( ! $result['success'] ) {
			return $result;
		}
		return array( 'success' => true, 'data' => (array) ( $result['data']['inspectionResult'] ?? array() ) );
	}

	/**
	 * @return string|WP_Error
	 */
	private function access_token( bool $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( self::TOKEN_TRANSIENT );
			if ( is_string( $cached ) && '' !== $cached ) {
				return $cached;
			}
		}

		$refresh_token = GscSettings::refresh_token();
		if ( '' === $refresh_token ) {
			return new WP_Error( 'seoistic_gsc_not_connected', __( 'Search Console is not connected.', 'seoistic' ) );
		}

		delete_transient( self::TOKEN_TRANSIENT );
		$response = wp_remote_post(
			self::ENDPOINT_TOKEN,
			array(
				'body'    => array(
					'refresh_token' => $refresh_token,
					'client_id'     => GscSettings::client_id(),
					'client_secret' => GscSettings::client_secret(),
					'grant_type'    => 'refresh_token',
				),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body   = $this->decode_body( wp_remote_retrieve_body( $response ) );
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 || empty( $body['access_token'] ) ) {
			$message = isset( $body['error_description'] ) && is_string( $body['error_description'] )
				? $body['error_description']
				: __( 'Failed to refresh the Search Console access token.', 'seoistic' );
			return new WP_Error( 'seoistic_gsc_refresh_failed', $message );
		}

		if ( ! empty( $body['refresh_token'] ) ) {
			GscSettings::set_refresh_token( (string) $body['refresh_token'] );
		}
		$this->store_access_token( (string) $body['access_token'], (int) ( $body['expires_in'] ?? 3500 ) );
		return (string) $body['access_token'];
	}

	/**
	 * @param array<string,mixed> $args
	 * @return array<string,mixed>|WP_Error
	 */
	private function api_request( string $method, string $url, array $args ) {
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args['headers']['Authorization'] = 'Bearer ' . $token;
		$response = 'GET' === $method ? wp_remote_get( $url, $args ) : wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) || 401 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return $response;
		}

		$token = $this->access_token( true );
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$args['headers']['Authorization'] = 'Bearer ' . $token;
		return 'GET' === $method ? wp_remote_get( $url, $args ) : wp_remote_post( $url, $args );
	}

	private function store_access_token( string $token, int $expires_in ): void {
		set_transient( self::TOKEN_TRANSIENT, $token, max( 60, $expires_in - 60 ) );
	}

	private function property_matches_home( string $property ): bool {
		$property = strtolower( untrailingslashit( trim( $property ) ) );
		if ( 0 === strpos( $property, 'sc-domain:' ) ) {
			$domain = substr( $property, strlen( 'sc-domain:' ) );
			return $domain === strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		}

		$property_url = $property;
		$home_url     = strtolower( untrailingslashit( home_url( '/' ) ) );
		return 0 === strpos( $home_url . '/', $property_url . '/' );
	}

	/**
	 * @param array<string,mixed>|WP_Error $response
	 * @return array{success:bool, data?:array<string,mixed>, error?:string, error_code?:string, access_denied?:bool}
	 */
	private function handle_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array(
				'success'    => false,
				'error'      => $response->get_error_message(),
				'error_code' => $response->get_error_code(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = $this->decode_body( wp_remote_retrieve_body( $response ) );
		if ( $code >= 200 && $code < 300 ) {
			return array( 'success' => true, 'data' => $body );
		}

		if ( $this->is_access_denied( $body ) ) {
			return array(
				'success'       => false,
				'error'         => __( 'Google rejected access. If this OAuth app is in Testing mode, add the connecting Google account as a Test User, then publish the app or reconnect after granting access.', 'seoistic' ),
				'error_code'    => 'access_denied',
				'access_denied' => true,
			);
		}

		$message = isset( $body['error']['message'] ) && is_string( $body['error']['message'] )
			? $body['error']['message']
			: sprintf(
				/* translators: %d: HTTP status code. */
				__( 'Search Console API request failed (HTTP %d).', 'seoistic' ),
				$code
			);
		return array(
			'success'    => false,
			'error'      => $message,
			'error_code' => $this->google_error_code( $body ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function decode_body( string $body ): array {
		$decoded = json_decode( $body, true );
		return is_array( $decoded ) ? $decoded : array();
	}

	private function is_access_denied( array $body ): bool {
		return 'access_denied' === $this->google_error_code( $body );
	}

	private function google_error_code( array $body ): string {
		if ( isset( $body['error'] ) && is_string( $body['error'] ) ) {
			return $body['error'];
		}

		$errors = $body['error']['errors'] ?? array();
		if ( is_array( $errors ) ) {
			foreach ( $errors as $error ) {
				if ( ! is_array( $error ) ) {
					continue;
				}
				$reason = $error['reason'] ?? '';
				if ( 'accessDenied' === $reason || 'access_denied' === $reason ) {
					return 'access_denied';
				}
			}
		}

		return isset( $body['error']['code'] ) && is_string( $body['error']['code'] )
			? $body['error']['code']
			: '';
	}
}
