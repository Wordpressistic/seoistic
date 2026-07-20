<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Gsc;

use WP_Error;

/**
 * Google Search Console — OAuth 2.0 (authorization-code + refresh-token flow,
 * BYO Client ID/Secret from the site owner's own Google Cloud project — see
 * GscSettings' docblock for why there's no centralized proxy) plus the two
 * read-only APIs that make the dashboard useful: Search Analytics (clicks,
 * impressions, CTR, position) and URL Inspection (per-URL index/coverage
 * status). Scope is read-only throughout; this addon never modifies GSC data.
 */
final class GscClient {

	private const SCOPE               = 'https://www.googleapis.com/auth/webmasters.readonly';
	private const ENDPOINT_AUTHORIZE   = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const ENDPOINT_TOKEN       = 'https://oauth2.googleapis.com/token';
	private const ENDPOINT_SITES       = 'https://www.googleapis.com/webmasters/v3/sites';
	private const ENDPOINT_INSPECT     = 'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect';
	private const TOKEN_TRANSIENT      = 'seoistic_gsc_access_token';

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
	 * @return array{success:bool, error?:string}
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

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['refresh_token'] ) ) {
			$message = is_array( $body ) && isset( $body['error_description'] ) ? (string) $body['error_description'] : __( 'Google did not return a refresh token. Try disconnecting any prior authorization for this app at myaccount.google.com/permissions, then reconnect.', 'seoistic' );
			return array( 'success' => false, 'error' => $message );
		}

		GscSettings::set_refresh_token( (string) $body['refresh_token'] );
		if ( ! empty( $body['access_token'] ) ) {
			set_transient( self::TOKEN_TRANSIENT, (string) $body['access_token'], (int) ( $body['expires_in'] ?? 3500 ) - 60 );
		}
		return array( 'success' => true );
	}

	/**
	 * @return string|WP_Error
	 */
	private function access_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$refresh_token = GscSettings::refresh_token();
		if ( '' === $refresh_token ) {
			return new WP_Error( 'seoistic_gsc_not_connected', __( 'Search Console is not connected.', 'seoistic' ) );
		}

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

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$message = is_array( $body ) && isset( $body['error_description'] ) ? (string) $body['error_description'] : __( 'Failed to refresh the Search Console access token.', 'seoistic' );
			return new WP_Error( 'seoistic_gsc_refresh_failed', $message );
		}

		$token = (string) $body['access_token'];
		set_transient( self::TOKEN_TRANSIENT, $token, (int) ( $body['expires_in'] ?? 3500 ) - 60 );
		return $token;
	}

	/**
	 * @return array{success:bool, data?:array<int,array{siteUrl:string,permissionLevel:string}>, error?:string}
	 */
	public function list_sites(): array {
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'error' => $token->get_error_message() );
		}

		$response = wp_remote_get( self::ENDPOINT_SITES, array( 'headers' => array( 'Authorization' => 'Bearer ' . $token ), 'timeout' => 15 ) );
		$result   = $this->handle_response( $response );
		if ( ! $result['success'] ) {
			return $result;
		}
		return array( 'success' => true, 'data' => (array) ( $result['data']['siteEntry'] ?? array() ) );
	}

	/**
	 * @param array{start_date?:string, end_date?:string, dimensions?:list<string>, row_limit?:int} $args
	 * @return array{success:bool, data?:array<int,array<string,mixed>>, error?:string}
	 */
	public function search_analytics( array $args = array() ): array {
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'error' => $token->get_error_message() );
		}
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

		$response = wp_remote_post(
			self::ENDPOINT_SITES . '/' . rawurlencode( $site_url ) . '/searchAnalytics/query',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( $body ),
				'timeout' => 20,
			)
		);
		$result = $this->handle_response( $response );
		if ( ! $result['success'] ) {
			return $result;
		}
		return array( 'success' => true, 'data' => (array) ( $result['data']['rows'] ?? array() ) );
	}

	/**
	 * @return array{success:bool, data?:array<string,mixed>, error?:string}
	 */
	public function inspect_url( string $url ): array {
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'error' => $token->get_error_message() );
		}
		$site_url = GscSettings::site_url();
		if ( '' === $site_url ) {
			return array( 'success' => false, 'error' => __( 'No Search Console property selected.', 'seoistic' ) );
		}

		$response = wp_remote_post(
			self::ENDPOINT_INSPECT,
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'inspectionUrl' => $url, 'siteUrl' => $site_url ) ),
				'timeout' => 20,
			)
		);
		$result = $this->handle_response( $response );
		if ( ! $result['success'] ) {
			return $result;
		}
		return array( 'success' => true, 'data' => (array) ( $result['data']['inspectionResult'] ?? array() ) );
	}

	/**
	 * @param array<string, mixed>|\WP_Error $response
	 * @return array{success:bool, data?:array<string,mixed>, error?:string}
	 */
	private function handle_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $body ) && isset( $body['error']['message'] ) ? (string) $body['error']['message'] : sprintf(
				/* translators: %d: HTTP status code. */
				__( 'Search Console API request failed (HTTP %d).', 'seoistic' ),
				$code
			);
			return array( 'success' => false, 'error' => $message );
		}
		return array( 'success' => true, 'data' => is_array( $body ) ? $body : array() );
	}
}
