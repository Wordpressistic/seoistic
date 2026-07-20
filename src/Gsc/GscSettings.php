<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Gsc;

use Wpistic\Seoistic\Core\Crypto;

/**
 * Settings for the Search Console addon. There is no centralized OAuth proxy
 * here (that requires being an approved Google OAuth partner) — the site
 * owner creates their own Google Cloud OAuth client (same BYO-credential
 * pattern as Indexistic's Google service account) and pastes the Client ID +
 * Secret here. The Client Secret and the refresh token are encrypted the same
 * way as every other secret in this plugin; the Client ID is not a secret —
 * Google's own OAuth docs describe it as public-safe to embed — but is still
 * only ever shown masked on the settings screen for consistency.
 */
final class GscSettings {

	private const CRYPTO_CONTEXT       = 'seoistic-gsc';
	private const OPTION               = 'seoistic_gsc_options';
	private const CLIENT_SECRET_OPTION = 'seoistic_gsc_client_secret_enc';
	private const REFRESH_TOKEN_OPTION = 'seoistic_gsc_refresh_token_enc';
	private const OAUTH_STATE_TRANSIENT = 'seoistic_gsc_oauth_state';

	/**
	 * @return array{client_id:string, site_url:string}
	 */
	public static function all(): array {
		$defaults = array( 'client_id' => '', 'site_url' => '' );
		$saved    = get_option( self::OPTION, array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	public static function client_id(): string {
		return (string) self::all()['client_id'];
	}

	public static function site_url(): string {
		return (string) self::all()['site_url'];
	}

	public static function save_client( string $client_id, string $client_secret ): void {
		$current = self::all();
		update_option( self::OPTION, array( 'client_id' => sanitize_text_field( $client_id ), 'site_url' => $current['site_url'] ) );
		if ( '' !== trim( $client_secret ) ) {
			update_option( self::CLIENT_SECRET_OPTION, Crypto::encrypt( trim( $client_secret ), self::CRYPTO_CONTEXT ) );
		}
	}

	/**
	 * Not run through esc_url_raw() — GSC "Domain" properties are represented as
	 * `sc-domain:example.com`, a pseudo-scheme esc_url_raw()'s protocol whitelist
	 * doesn't include, so it would silently mangle the most common property type.
	 * This value is never echoed unescaped (esc_attr()/esc_html() at render time)
	 * and is rawurlencode()'d before ever going into a request URL.
	 */
	public static function save_site_url( string $site_url ): void {
		$current = self::all();
		update_option( self::OPTION, array( 'client_id' => $current['client_id'], 'site_url' => sanitize_text_field( $site_url ) ) );
	}

	public static function client_secret(): string {
		$stored = get_option( self::CLIENT_SECRET_OPTION, '' );
		return is_string( $stored ) ? Crypto::decrypt( $stored, self::CRYPTO_CONTEXT ) : '';
	}

	public static function has_client(): bool {
		return '' !== self::client_id() && '' !== self::client_secret();
	}

	public static function masked_client_secret(): string {
		return '' !== self::client_secret() ? str_repeat( '•', 20 ) : '';
	}

	public static function refresh_token(): string {
		$stored = get_option( self::REFRESH_TOKEN_OPTION, '' );
		return is_string( $stored ) ? Crypto::decrypt( $stored, self::CRYPTO_CONTEXT ) : '';
	}

	public static function set_refresh_token( string $token ): void {
		update_option( self::REFRESH_TOKEN_OPTION, Crypto::encrypt( $token, self::CRYPTO_CONTEXT ) );
	}

	public static function is_connected(): bool {
		return '' !== self::refresh_token() && '' !== self::site_url();
	}

	public static function disconnect(): void {
		delete_option( self::REFRESH_TOKEN_OPTION );
		$current = self::all();
		update_option( self::OPTION, array( 'client_id' => $current['client_id'], 'site_url' => '' ) );
	}

	/**
	 * A short-lived CSRF token for the OAuth redirect round-trip — Google's
	 * callback carries it back in `state`, checked against this before we ever
	 * exchange the returned `code`.
	 */
	public static function new_oauth_state(): string {
		$state = wp_generate_password( 32, false );
		set_transient( self::OAUTH_STATE_TRANSIENT, $state, 10 * MINUTE_IN_SECONDS );
		return $state;
	}

	public static function consume_oauth_state( string $state ): bool {
		$expected = get_transient( self::OAUTH_STATE_TRANSIENT );
		delete_transient( self::OAUTH_STATE_TRANSIENT );
		return is_string( $expected ) && '' !== $expected && hash_equals( $expected, $state );
	}
}
