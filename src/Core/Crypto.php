<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * Shared AES-256-CBC encrypt/decrypt for secrets stored in wp_options (AI
 * provider keys, the Google service-account key for Instant Indexing) — keyed
 * off wp_salt('auth') so nothing sensitive sits in the database in plaintext.
 * Nothing returned by decrypt() is ever safe to echo back to the browser.
 */
final class Crypto {

	public static function encrypt( string $plaintext, string $context ): string {
		if ( '' === $plaintext ) {
			return '';
		}
		$iv         = openssl_random_pseudo_bytes( 16 );
		$ciphertext = openssl_encrypt( $plaintext, 'aes-256-cbc', self::key( $context ), OPENSSL_RAW_DATA, $iv );
		return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public static function decrypt( string $encoded, string $context ): string {
		if ( '' === $encoded ) {
			return '';
		}
		$decoded = base64_decode( $encoded, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		if ( false === $decoded || strlen( $decoded ) < 17 ) {
			return '';
		}
		$iv         = substr( $decoded, 0, 16 );
		$ciphertext = substr( $decoded, 16 );
		$plain      = openssl_decrypt( $ciphertext, 'aes-256-cbc', self::key( $context ), OPENSSL_RAW_DATA, $iv );
		return false === $plain ? '' : $plain;
	}

	private static function key( string $context ): string {
		return hash( 'sha256', wp_salt( 'auth' ) . $context, true );
	}
}
