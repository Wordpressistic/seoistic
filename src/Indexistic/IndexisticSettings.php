<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Indexistic;

use Wpistic\Seoistic\Core\Crypto;

/**
 * Settings for the Indexistic addon (fast/instant search-engine indexing).
 * The Google service-account key is a real secret and is encrypted the same
 * way as the AI provider keys. The IndexNow key is not a secret by design —
 * the IndexNow protocol requires it to be publicly retrievable at
 * yoursite.com/{key}.txt as proof of domain ownership — so it's stored plain.
 */
final class IndexisticSettings {

	private const CRYPTO_CONTEXT     = 'seoistic-indexistic';
	private const OPTION             = 'seoistic_indexistic_options';
	private const GOOGLE_KEY_OPTION  = 'seoistic_indexistic_google_key_enc';
	private const INDEXNOW_KEY_OPTION = 'seoistic_indexistic_indexnow_key';

	/**
	 * @return array{google_post_types:list<string>, indexnow_post_types:list<string>, auto_submit:bool}
	 */
	public static function all(): array {
		$defaults = array(
			'google_post_types'   => array(),
			'indexnow_post_types' => array( 'post', 'page' ),
			'auto_submit'         => false,
		);
		$saved = get_option( self::OPTION, array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	/**
	 * @param list<string> $google_post_types
	 * @param list<string> $indexnow_post_types
	 */
	public static function save( array $google_post_types, array $indexnow_post_types, bool $auto_submit ): void {
		update_option(
			self::OPTION,
			array(
				'google_post_types'   => array_values( array_unique( array_map( 'sanitize_key', $google_post_types ) ) ),
				'indexnow_post_types' => array_values( array_unique( array_map( 'sanitize_key', $indexnow_post_types ) ) ),
				'auto_submit'         => $auto_submit,
			)
		);
	}

	public static function auto_submit_enabled(): bool {
		return (bool) self::all()['auto_submit'];
	}

	/**
	 * @return list<string>
	 */
	public static function google_post_types(): array {
		return self::all()['google_post_types'];
	}

	/**
	 * @return list<string>
	 */
	public static function indexnow_post_types(): array {
		return self::all()['indexnow_post_types'];
	}

	/**
	 * The Google service-account JSON key, decrypted. Never echoed to the
	 * browser — only used server-side to sign the indexing API JWT.
	 */
	public static function google_key_json(): string {
		return Crypto::decrypt( (string) get_option( self::GOOGLE_KEY_OPTION, '' ), self::CRYPTO_CONTEXT );
	}

	public static function has_google_key(): bool {
		return '' !== self::google_key_json();
	}

	public static function set_google_key( string $json ): void {
		$json = trim( $json );
		if ( '' === $json ) {
			delete_option( self::GOOGLE_KEY_OPTION );
			return;
		}
		update_option( self::GOOGLE_KEY_OPTION, Crypto::encrypt( $json, self::CRYPTO_CONTEXT ) );
	}

	public static function clear_google_key(): void {
		delete_option( self::GOOGLE_KEY_OPTION );
	}

	public static function masked_google_key(): string {
		return self::has_google_key() ? str_repeat( '•', 20 ) : '';
	}

	/**
	 * The IndexNow key — generated once and reused. Deliberately plain-text:
	 * IndexNow requires this exact value to be publicly readable at
	 * /{key}.txt on the site root as the ownership proof.
	 */
	public static function indexnow_key(): string {
		$key = (string) get_option( self::INDEXNOW_KEY_OPTION, '' );
		if ( '' === $key ) {
			$key = self::generate_indexnow_key();
			update_option( self::INDEXNOW_KEY_OPTION, $key );
		}
		return $key;
	}

	public static function regenerate_indexnow_key(): string {
		$key = self::generate_indexnow_key();
		update_option( self::INDEXNOW_KEY_OPTION, $key );
		return $key;
	}

	private static function generate_indexnow_key(): string {
		return bin2hex( random_bytes( 16 ) );
	}
}
