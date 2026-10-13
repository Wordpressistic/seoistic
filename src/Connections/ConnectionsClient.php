<?php
/**
 * Google OAuth client for the Connections module — one combined read-only
 * grant (Analytics + Search Console). Access tokens are cached as transients
 * and refreshed transparently; every API call retries once on 401 with a
 * freshly refreshed token.
 *
 * @package Seoistic
 */

declare(strict_types=1);

namespace Wpistic\Seoistic\Connections;

final class ConnectionsClient {

	private const ENDPOINT_AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';
	private const ENDPOINT_TOKEN     = 'https://oauth2.googleapis.com/token';

	public const SCOPES = array(
		'https://www.googleapis.com/auth/analytics.readonly',
		'https://www.googleapis.com/auth/webmasters.readonly',
		'https://www.googleapis.com/auth/userinfo.email',
	);

	public static function redirect_uri(): string {
		return admin_url( 'admin-post.php?action=seoistic_connections_callback' );
	}

	public function connect_url(): string {
		$url = add_query_arg(
			array(
				'client_id'     => ConnectionsSettings::client_id(),
				'redirect_uri'  => self::redirect_uri(),
				'response_type' => 'code',
				'access_type'   => 'offline',
				'prompt'        => 'consent',
				'scope'         => implode( ' ', self::SCOPES ),
				'state'         => ConnectionsSettings::new_state(),
			),
			self::ENDPOINT_AUTHORIZE
		);
		return $url;
	}

	/**
	 * @return array{success:bool, error?:string, email?:string}
	 */
	public function exchange_code( string $code ): array {
		$response = wp_remote_post(
			self::ENDPOINT_TOKEN,
			array(
				'body'    => array(
					'code'          => $code,
					'client_id'     => ConnectionsSettings::client_id(),
					'client_secret' => ConnectionsSettings::client_secret(),
					'redirect_uri'  => self::redirect_uri(),
					'grant_type'    => 'authorization_code',
				),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'error' => $response->get_error_message() );
		}
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 || empty( $body['access_token'] ) ) {
			$message = isset( $body['error_description'] ) && is_string( $body['error_description'] )
				? $body['error_description']
				: __( 'Google did not return an access token. Check the OAuth client configuration, then reconnect.', 'seoistic' );
			return array( 'success' => false, 'error' => $message );
		}
		if ( ! empty( $body['refresh_token'] ) ) {
			ConnectionsSettings::set_refresh_token( (string) $body['refresh_token'] );
		} elseif ( '' === ConnectionsSettings::refresh_token() ) {
			return array(
				'success' => false,
				'error'   => __( 'Google did not return a refresh token. Revoke previous SEOistic authorizations at myaccount.google.com/permissions, then reconnect.', 'seoistic' ),
			);
		}
		ConnectionsSettings::set_access_token( (string) $body['access_token'], (int) ( $body['expires_in'] ?? 3500 ) );
		$email = $this->user_email( (string) $body['access_token'] );
		return array( 'success' => true, 'email' => $email );
	}

	private function user_email( string $access_token ): string {
		$response = wp_remote_get(
			'https://www.googleapis.com/oauth2/v2/userinfo',
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ), 'timeout' => 10 )
		);
		if ( is_wp_error( $response ) ) {
			return '';
		}
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		return is_string( $body['email'] ?? null ) ? (string) $body['email'] : '';
	}

	/**
	 * Valid access token, refreshing when expired.
	 *
	 * @return string|\WP_Error
	 */
	public function token() {
		$token = ConnectionsSettings::access_token();
		if ( '' !== $token ) {
			return $token;
		}
		$refresh = ConnectionsSettings::refresh_token();
		if ( '' === $refresh ) {
			return new \WP_Error( 'seoistic_connections_not_connected', __( 'Google is not connected yet.', 'seoistic' ) );
		}
		$result = $this->refresh( $refresh );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result;
	}

	/**
	 * @return string|\WP_Error
	 */
	private function refresh( string $refresh_token ) {
		$response = wp_remote_post(
			self::ENDPOINT_TOKEN,
			array(
				'body'    => array(
					'refresh_token' => $refresh_token,
					'client_id'     => ConnectionsSettings::client_id(),
					'client_secret' => ConnectionsSettings::client_secret(),
					'grant_type'    => 'refresh_token',
				),
				'timeout' => 15,
			)
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$body   = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 || empty( $body['access_token'] ) ) {
			$message = isset( $body['error_description'] ) && is_string( $body['error_description'] )
				? $body['error_description']
				: __( 'Could not refresh the Google connection. Reconnect Google.', 'seoistic' );
			return new \WP_Error( 'seoistic_connections_refresh_failed', $message );
		}
		ConnectionsSettings::set_access_token( (string) $body['access_token'], (int) ( $body['expires_in'] ?? 3500 ) );
		return (string) $body['access_token'];
	}

	/**
	 * Authenticated GET/POST with one 401 retry.
	 *
	 * @return array|\WP_Error Parsed JSON body.
	 */
	public function api( string $method, string $url, array $args = array() ) {
		foreach ( array( 1, 2 ) as $attempt ) {
			$token = $this->token();
			if ( is_wp_error( $token ) ) {
				return $token;
			}
			$http        = array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $token ) );
			$is_jsonBody = isset( $args['body'] ) && is_array( $args['body'] );
			if ( $is_jsonBody ) {
				$http['headers']['Content-Type'] = 'application/json';
				$http['body']                    = wp_json_encode( $args['body'] );
			} elseif ( isset( $args['body'] ) ) {
				$http['body'] = $args['body'];
			}
			$response = 'GET' === strtoupper( $method )
				? wp_remote_get( $url, $http )
				: wp_remote_request( $url, array_merge( $http, array( 'method' => strtoupper( $method ) ) ) );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$status = (int) wp_remote_retrieve_response_code( $response );
			if ( 401 === $status && 1 === $attempt ) {
				delete_transient( 'seoistic_connections_access_token' );
				continue;
			}
			$parsed = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			if ( $status < 200 || $status >= 300 ) {
				$message = is_string( $parsed['error']['message'] ?? null )
					? (string) $parsed['error']['message']
					: sprintf( /* translators: %d: HTTP status */ __( 'Google API returned HTTP %d.', 'seoistic' ), $status );
				return new \WP_Error( 'seoistic_connections_api', $message, array( 'status' => $status ) );
			}
			return $parsed;
		}
		return new \WP_Error( 'seoistic_connections_api', __( 'Google API request failed after retry.', 'seoistic' ) );
	}

	/**
	 * GA4 properties visible to the connected account.
	 *
	 * @return array[]|\WP_Error
	 */
	public function list_ga4_properties() {
		$body = $this->api( 'GET', 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries?pageSize=200' );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$out = array();
		foreach ( (array) ( $body['accountSummaries'] ?? array() ) as $account ) {
			foreach ( (array) ( $account['propertySummaries'] ?? array() ) as $property ) {
				$display = is_string( $property['displayName'] ?? null ) ? (string) $property['displayName'] : '';
				$ref     = is_string( $property['property'] ?? null ) ? (string) $property['property'] : '';
				if ( '' === $ref ) {
					continue;
				}
				$out[] = array(
					'property' => $ref, // properties/123456
					'name'     => trim( $display . ' — ' . (string) ( $account['displayName'] ?? '' ), ' —' ),
					'id'       => str_replace( 'properties/', '', $ref ),
				);
			}
		}
		return $out;
	}

	/**
	 * Verified GSC sites.
	 *
	 * @return array[]|\WP_Error
	 */
	public function list_gsc_sites() {
		$body = $this->api( 'GET', 'https://www.googleapis.com/webmasters/v3/sites' );
		if ( is_wp_error( $body ) ) {
			return $body;
		}
		$out = array();
		foreach ( (array) ( $body['siteEntry'] ?? array() ) as $site ) {
			if ( isset( $site['siteUrl'] ) ) {
				$out[] = array(
					'siteUrl' => (string) $site['siteUrl'],
					'permission' => (string) ( $site['permissionLevel'] ?? '' ),
				);
			}
		}
		return $out;
	}
}
