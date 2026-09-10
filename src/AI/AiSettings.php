<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI;

use Wpistic\Seoistic\Core\Crypto;
use Wpistic\Seoistic\License\LicenseClient;
use Wpistic\Seoistic\Module\Entitlement;

/**
 * AI preferences and encrypted custom-model credentials. Legacy provider
 * options are not deleted during upgrade, but they are no longer used.
 */
final class AiSettings {

	private const CRYPTO_CONTEXT  = 'seoistic-ai';
	private const OPTION          = 'seoistic_ai_options';
	private const KEY_OPTION_BASE = 'seoistic_ai_key_enc_';
	private const CACHE_GENERATION  = 'seoistic_ai_cache_generation';

	/**
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$defaults = array(
			'enabled'          => false,
			'temperature'      => 0.4,
			'max_tokens'       => 900,
			'business_name'    => get_bloginfo( 'name' ),
			'brand_voice'      => '',
			'target_country'   => '',
			'target_audience'  => '',
			'default_language' => 'en',
			'kb_mode'          => 'balanced',
			'custom_rag_enabled' => false,
			'custom_rag_path'   => '',
			'custom_base_url'   => '',
			'custom_model'      => '',
		);
		$saved = get_option( self::OPTION, array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	public static function is_enabled(): bool {
		return ! empty( self::all()['enabled'] );
	}

	public static function custom_rag_enabled(): bool {
		return ! empty( self::all()['custom_rag_enabled'] );
	}

	public static function custom_rag_path(): string {
		return (string) self::all()['custom_rag_path'];
	}

	public static function temperature(): float {
		return (float) self::all()['temperature'];
	}

	public static function max_tokens(): int {
		return max( 100, min( 4000, (int) self::all()['max_tokens'] ) );
	}

	/**
	 * Whether the current plan may route AI through its own OpenAI-compatible model.
	 */
	public static function custom_models_allowed(): bool {
		$plan = Entitlement::plan();
		return in_array( $plan, array( 'business', 'agency' ), true );
	}

	public static function custom_base_url(): string {
		$base = rtrim( (string) self::all()['custom_base_url'], '/' );
		$host = (string) wp_parse_url( $base, PHP_URL_HOST );
		$port = wp_parse_url( $base, PHP_URL_PORT );
		$path = (string) wp_parse_url( $base, PHP_URL_PATH );
		$path = untrailingslashit( str_replace( array( '/chat/completions', '/completions' ), '', $path ) );
		$scheme = wp_parse_url( $base, PHP_URL_SCHEME );
		if ( '' === $host ) {
			return '';
		}
		return $scheme . '://' . $host . ( $port ? ':' . $port : '' ) . $path;
	}

	public static function custom_model(): string {
		return sanitize_text_field( (string) self::all()['custom_model'] );
	}

	public static function custom_api_key(): string {
		return self::api_key( 'custom' );
	}

	public static function is_custom_configured(): bool {
		return self::custom_models_allowed()
			&& '' !== self::custom_base_url()
			&& '' !== self::custom_model()
			&& self::has_api_key( 'custom' );
	}

	public static function custom_model_signature(): string {
		return self::is_custom_configured() ? self::custom_base_url() . '|' . self::custom_model() : '';
	}

	public static function cache_generation(): int {
		return (int) get_option( self::CACHE_GENERATION, 1 );
	}

	public static function bump_cache_generation(): void {
		update_option( self::CACHE_GENERATION, self::cache_generation() + 1, false );
	}

	/**
	 * A license key is the only credential required for managed AI requests.
	 */
	public static function is_configured(): bool {
		return self::is_gateway_ready();
	}

	public static function is_gateway_ready(): bool {
		return ( new LicenseClient() )->key() !== '';
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	public static function save( array $fields ): void {
		$current = self::all();
		$clean   = array(
			'enabled'          => ! empty( $fields['enabled'] ),
			'temperature'      => max( 0, min( 2, (float) ( $fields['temperature'] ?? $current['temperature'] ) ) ),
			'max_tokens'       => max( 100, min( 4000, (int) ( $fields['max_tokens'] ?? $current['max_tokens'] ) ) ),
			'business_name'    => sanitize_text_field( (string) ( $fields['business_name'] ?? '' ) ),
			'brand_voice'      => sanitize_textarea_field( (string) ( $fields['brand_voice'] ?? '' ) ),
			'target_country'   => sanitize_text_field( (string) ( $fields['target_country'] ?? '' ) ),
			'target_audience'  => sanitize_text_field( (string) ( $fields['target_audience'] ?? '' ) ),
			'default_language' => sanitize_text_field( (string) ( $fields['default_language'] ?? 'en' ) ),
			'kb_mode'          => in_array( $fields['kb_mode'] ?? '', array( 'strict', 'balanced', 'creative' ), true ) ? $fields['kb_mode'] : 'balanced',
			'custom_rag_enabled' => ! empty( $fields['custom_rag_enabled'] ),
			'custom_rag_path'   => sanitize_text_field( (string) ( $fields['custom_rag_path'] ?? '' ) ),
			'custom_base_url'   => esc_url_raw( (string) ( $fields['custom_base_url'] ?? '' ) ),
			'custom_model'      => sanitize_text_field( (string) ( $fields['custom_model'] ?? '' ) ),
		);
		update_option( self::OPTION, $clean );
	}

	/**
	 * Decrypted credential value — never echoed to the browser, only used
	 * server-side by the managed AI transport.
	 */
	public static function api_key( string $key_id ): string {
		$stored = get_option( self::key_option( $key_id ), '' );
		return is_string( $stored ) ? Crypto::decrypt( $stored, self::CRYPTO_CONTEXT ) : '';
	}

	public static function has_api_key( string $key_id ): bool {
		return '' !== self::api_key( $key_id );
	}

	public static function set_api_key( string $key_id, string $key ): void {
		$key = trim( $key );
		if ( '' === $key ) {
			delete_option( self::key_option( $key_id ) );
			return;
		}
		update_option( self::key_option( $key_id ), Crypto::encrypt( $key, self::CRYPTO_CONTEXT ), false );
	}

	public static function clear_api_key( string $key_id ): void {
		delete_option( self::key_option( $key_id ) );
	}

	/**
	 * A masked hint for the settings screen — never the real key.
	 */
	public static function masked_key( string $key_id ): string {
		if ( ! self::has_api_key( $key_id ) ) {
			return '';
		}
		return str_repeat( '•', 20 );
	}

	private static function key_option( string $key_id ): string {
		return self::KEY_OPTION_BASE . sanitize_key( $key_id );
	}
}
