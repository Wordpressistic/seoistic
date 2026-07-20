<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Indexistic;

use WP_Error;

/**
 * Google's Indexing API — service-account JWT auth, signed with openssl_sign()
 * directly (no vendor Google API client library; this is the same lightweight
 * approach WP Indexistic Pro used, just with encrypted key storage and
 * structured error returns instead of string-concatenated messages).
 */
final class GoogleIndexingClient {

	private const TOKEN_TRANSIENT   = 'seoistic_indexistic_google_token';
	private const ENDPOINT_PUBLISH  = 'https://indexing.googleapis.com/v3/urlNotifications:publish';
	private const ENDPOINT_METADATA = 'https://indexing.googleapis.com/v3/urlNotifications/metadata';
	private const ENDPOINT_TOKEN    = 'https://oauth2.googleapis.com/token';

	public function is_configured(): bool {
		return IndexisticSettings::has_google_key();
	}

	/**
	 * @return array{success:bool, data?:array<string,mixed>, error?:string, code?:int}
	 */
	public function submit( string $url, string $type = 'URL_UPDATED' ): array {
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'error' => $token->get_error_message() );
		}

		$response = wp_remote_post(
			self::ENDPOINT_PUBLISH,
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'url' => $url, 'type' => $type ) ),
				'timeout' => 15,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * @return array{success:bool, data?:array<string,mixed>, error?:string, code?:int}
	 */
	public function get_status( string $url ): array {
		$token = $this->access_token();
		if ( is_wp_error( $token ) ) {
			return array( 'success' => false, 'error' => $token->get_error_message() );
		}

		$response = wp_remote_get(
			self::ENDPOINT_METADATA . '?url=' . rawurlencode( $url ),
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $token ),
				'timeout' => 15,
			)
		);

		return $this->handle_response( $response );
	}

	/**
	 * @param array<string, mixed>|\WP_Error $response
	 * @return array{success:bool, data?:array<string,mixed>, error?:string, code?:int}
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
				__( 'Google Indexing API request failed (HTTP %d).', 'seoistic' ),
				$code
			);
			return array( 'success' => false, 'error' => $message, 'code' => $code );
		}

		return array( 'success' => true, 'data' => is_array( $body ) ? $body : array(), 'code' => $code );
	}

	/**
	 * @return string|WP_Error
	 */
	private function access_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$key_json = IndexisticSettings::google_key_json();
		if ( '' === $key_json ) {
			return new WP_Error( 'seoistic_no_key', __( 'No Google service-account key configured.', 'seoistic' ) );
		}

		$key_data = json_decode( $key_json, true );
		if ( ! is_array( $key_data ) || empty( $key_data['client_email'] ) || empty( $key_data['private_key'] ) ) {
			return new WP_Error( 'seoistic_bad_key', __( 'The Google service-account key is not valid JSON, or is missing client_email/private_key.', 'seoistic' ) );
		}

		$now     = time();
		$header  = (string) wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) );
		$claims  = (string) wp_json_encode(
			array(
				'iss'   => $key_data['client_email'],
				'scope' => 'https://www.googleapis.com/auth/indexing',
				'aud'   => self::ENDPOINT_TOKEN,
				'exp'   => $now + 3600,
				'iat'   => $now,
			)
		);

		$segments  = self::b64( $header ) . '.' . self::b64( $claims );
		$signature = '';
		$signed    = openssl_sign( $segments, $signature, (string) $key_data['private_key'], 'SHA256' );
		if ( ! $signed ) {
			return new WP_Error( 'seoistic_sign_failed', __( 'Could not sign the Google API request — check the private key in your service-account JSON.', 'seoistic' ) );
		}
		$jwt = $segments . '.' . self::b64( $signature );

		$response = wp_remote_post(
			self::ENDPOINT_TOKEN,
			array(
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$message = is_array( $body ) && isset( $body['error_description'] ) ? (string) $body['error_description'] : __( 'Failed to obtain a Google access token.', 'seoistic' );
			return new WP_Error( 'seoistic_auth_failed', $message );
		}

		$token = (string) $body['access_token'];
		set_transient( self::TOKEN_TRANSIENT, $token, 3500 );
		return $token;
	}

	private static function b64( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}
}
