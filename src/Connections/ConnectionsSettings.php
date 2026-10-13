<?php
/**
 * Settings storage for the Connections module. Encrypts everything sensitive
 * with the shared Core Crypto helper; falls back to the GSC module's client
 * credentials when the admin already configured those, so "one setup" holds.
 *
 * @package Seoistic
 */

declare(strict_types=1);

namespace Wpistic\Seoistic\Connections;

use Wpistic\Seoistic\Core\Crypto;
use Wpistic\Seoistic\Gsc\GscSettings;

final class ConnectionsSettings {

	private const OPTION                = 'seoistic_connections_options';
	private const CLIENT_SECRET_OPTION  = 'seoistic_connections_client_secret_enc';
	private const REFRESH_TOKEN_OPTION  = 'seoistic_connections_refresh_token_enc';
	private const ACCESS_TOKEN_TRANSIENT = 'seoistic_connections_access_token';
	private const STATE_TRANSIENT       = 'seoistic_connections_oauth_state_';

	public static function all(): array {
		$defaults = array(
			'client_id'   => '',
			'property_id' => '',
			'gsc_site'    => '',
			'psi_api_key' => '',
			'site_url'    => home_url( '/' ),
		);
		$saved = get_option( self::OPTION, array() );
		return is_array( $saved ) ? array_merge( $defaults, $saved ) : $defaults;
	}

	public static function client_id(): string {
		$own = (string) self::all()['client_id'];
		if ( '' !== $own ) {
			return $own;
		}
		// Reuse the Search Console module's client when already configured.
		return GscSettings::client_id();
	}

	public static function client_secret(): string {
		$enc = (string) get_option( self::CLIENT_SECRET_OPTION, '' );
		if ( '' !== $enc ) {
			$dec = Crypto::decrypt( $enc, 'seoistic-connections' );
			return is_wp_error( $dec ) ? '' : (string) $dec;
		}
		return GscSettings::client_secret();
	}

	public static function save_client( string $client_id, string $client_secret ): void {
		$all             = self::all();
		$all['client_id'] = sanitize_text_field( $client_id );
		update_option( self::OPTION, $all );
		if ( '' !== trim( $client_secret ) ) {
			update_option( self::CLIENT_SECRET_OPTION, Crypto::encrypt( trim( $client_secret ), 'seoistic-connections' ) );
		}
	}

	public static function property_id(): string {
		return (string) self::all()['property_id'];
	}

	public static function gsc_site(): string {
		return (string) self::all()['gsc_site'];
	}

	public static function psi_api_key(): string {
		$key = (string) self::all()['psi_api_key'];
		if ( '' !== $key ) {
			$dec = Crypto::decrypt( $key, 'seoistic-connections' );
			return is_wp_error( $dec ) ? '' : (string) $dec;
		}
		return '';
	}

	public static function save( array $patch ): void {
		$all = self::all();
		if ( isset( $patch['property_id'] ) ) {
			$all['property_id'] = sanitize_text_field( (string) $patch['property_id'] );
		}
		if ( isset( $patch['gsc_site'] ) ) {
			$all['gsc_site'] = esc_url_raw( (string) $patch['gsc_site'] );
		}
		// The field's placeholder says "saved — leave blank to keep", so an
		// empty submit must never wipe a stored key.
		if ( ! empty( $patch['psi_api_key'] ) ) {
			$all['psi_api_key'] = Crypto::encrypt( trim( (string) $patch['psi_api_key'] ), 'seoistic-connections' );
		}
		update_option( self::OPTION, $all );
	}

	public static function refresh_token(): string {
		$enc = (string) get_option( self::REFRESH_TOKEN_OPTION, '' );
		if ( '' === $enc ) {
			return '';
		}
		$dec = Crypto::decrypt( $enc, 'seoistic-connections' );
		return is_wp_error( $dec ) ? '' : (string) $dec;
	}

	public static function set_refresh_token( string $token ): void {
		update_option( self::REFRESH_TOKEN_OPTION, Crypto::encrypt( $token, 'seoistic-connections' ) );
	}

	public static function access_token(): string {
		return (string) get_transient( self::ACCESS_TOKEN_TRANSIENT );
	}

	public static function set_access_token( string $token, int $ttl ): void {
		set_transient( self::ACCESS_TOKEN_TRANSIENT, $token, max( 60, $ttl - 120 ) );
	}

	public static function clear_tokens(): void {
		delete_transient( self::ACCESS_TOKEN_TRANSIENT );
		delete_option( self::REFRESH_TOKEN_OPTION );
	}

	public static function is_connected(): bool {
		return '' !== self::refresh_token();
	}

	public static function new_state(): string {
		$state = wp_generate_password( 24, false );
		// Hashed transient key — the raw state arrives from an OAuth redirect
		// (a URL the site does not control), so never let it become a lookup key.
		set_transient( self::STATE_TRANSIENT . hash( 'sha256', $state ), 1, 10 * MINUTE_IN_SECONDS );
		return $state;
	}

	public static function valid_state( string $state ): bool {
		$key = self::STATE_TRANSIENT . hash( 'sha256', $state );
		if ( ! get_transient( $key ) ) {
			return false;
		}
		delete_transient( $key );
		return true;
	}
}
