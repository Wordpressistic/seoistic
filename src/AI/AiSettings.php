<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI;

use Wpistic\Seoistic\Core\Crypto;

/**
 * The seoistic_ai_options option + the per-provider API keys. Keys are stored
 * encrypted (Core\Crypto, AES-256-CBC keyed off wp_salt('auth')) so they never
 * live in the database in plaintext and are never sent to the browser —
 * settings screens only ever render a masked placeholder, never the real value.
 *
 * Three providers are supported: OpenRouter and Groq (both need an API key,
 * both speak the same OpenAI-compatible chat-completions format), and a
 * self-hosted Ollama instance (no key — just a base URL on the site owner's
 * own network/VPS, also OpenAI-compatible via its /v1 endpoint).
 */
final class AiSettings {

	private const CRYPTO_CONTEXT  = 'seoistic-ai';
	private const OPTION          = 'seoistic_ai_options';
	private const KEY_OPTION_BASE = 'seoistic_ai_key_enc_';

	public const PROVIDERS = array(
		'openrouter' => 'OpenRouter',
		'groq'       => 'Groq (free tier)',
		'ollama'     => 'Ollama (self-hosted, local models)',
	);

	public const OPENROUTER_MODELS = array(
		'openai/gpt-4.1-nano'        => 'GPT-4.1 nano (default, most affordable)',
		'openai/gpt-4.1-mini'        => 'GPT-4.1 mini',
		'google/gemini-flash-1.5'    => 'Gemini 1.5 Flash',
		'anthropic/claude-3.5-haiku' => 'Claude 3.5 Haiku',
		'qwen/qwen3-coder'           => 'Qwen3 Coder',
	);

	public const GROQ_MODELS = array(
		'llama-3.1-8b-instant'      => 'Llama 3.1 8B Instant (default, fastest)',
		'llama-3.3-70b-versatile'   => 'Llama 3.3 70B Versatile',
		'gemma2-9b-it'              => 'Gemma 2 9B',
		'mixtral-8x7b-32768'        => 'Mixtral 8x7B',
	);

	public const OLLAMA_MODELS = array(
		'hermes2-theta:latest'      => 'Hermes 2 Theta (default, recommended)',
		'mistral:latest'            => 'Mistral',
		'neural-chat:latest'        => 'Neural Chat',
		'dolphin-mixtral:latest'    => 'Dolphin Mixtral',
		'llama2:latest'             => 'Llama 2',
		'openchat:latest'           => 'OpenChat',
	);

	/**
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$defaults = array(
			'enabled'          => false,
			'provider'         => 'openrouter',
			'model_openrouter' => 'openai/gpt-4.1-nano',
			'model_groq'       => 'llama-3.1-8b-instant',
			'ollama_base_url'  => 'http://127.0.0.1:11434',
			'ollama_model'     => 'hermes2-theta:latest',
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
		);
		$saved = get_option( self::OPTION, array() );
		return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
	}

	public static function is_enabled(): bool {
		return ! empty( self::all()['enabled'] );
	}

	public static function provider(): string {
		$provider = (string) self::all()['provider'];
		return isset( self::PROVIDERS[ $provider ] ) ? $provider : 'openrouter';
	}

	/**
	 * The model for the currently selected provider — each provider remembers
	 * its own last-picked model so switching providers doesn't lose the choice.
	 */
	public static function model(): string {
		$all = self::all();
		return match ( self::provider() ) {
			'groq' => isset( self::GROQ_MODELS[ (string) $all['model_groq'] ] ) ? (string) $all['model_groq'] : 'llama-3.1-8b-instant',
			'ollama' => (string) $all['ollama_model'],
			default => isset( self::OPENROUTER_MODELS[ (string) $all['model_openrouter'] ] ) ? (string) $all['model_openrouter'] : 'openai/gpt-4.1-nano',
		};
	}

	public static function ollama_base_url(): string {
		return rtrim( (string) self::all()['ollama_base_url'], '/' );
	}

	public static function ollama_model(): string {
		return (string) self::all()['ollama_model'];
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
	 * Whether the currently selected provider has what it needs to run — an
	 * API key for OpenRouter/Groq, or a configured base URL + model for Ollama.
	 * Never makes a network call; this is a cheap, page-load-safe check.
	 */
	public static function is_configured(): bool {
		return match ( self::provider() ) {
			'groq' => self::has_api_key( 'groq' ),
			'ollama' => '' !== self::ollama_base_url() && '' !== self::ollama_model(),
			default => self::has_api_key( 'openrouter' ),
		};
	}

	/**
	 * @param array<string, mixed> $fields
	 */
	public static function save( array $fields ): void {
		$current = self::all();
		$clean   = array(
			'enabled'          => ! empty( $fields['enabled'] ),
			'provider'         => isset( self::PROVIDERS[ (string) ( $fields['provider'] ?? '' ) ] ) ? (string) $fields['provider'] : $current['provider'],
			'model_openrouter' => isset( self::OPENROUTER_MODELS[ (string) ( $fields['model_openrouter'] ?? '' ) ] ) ? (string) $fields['model_openrouter'] : $current['model_openrouter'],
			'model_groq'       => isset( self::GROQ_MODELS[ (string) ( $fields['model_groq'] ?? '' ) ] ) ? (string) $fields['model_groq'] : $current['model_groq'],
			'ollama_base_url'  => esc_url_raw( (string) ( $fields['ollama_base_url'] ?? $current['ollama_base_url'] ) ),
			'ollama_model'     => sanitize_text_field( (string) ( $fields['ollama_model'] ?? $current['ollama_model'] ) ),
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
		);
		update_option( self::OPTION, $clean );
	}

	/**
	 * Decrypted API key for a given provider — never echoed to the browser,
	 * only used server-side to build that provider's Authorization header.
	 */
	public static function api_key( string $provider ): string {
		$stored = get_option( self::key_option( $provider ), '' );
		return is_string( $stored ) ? Crypto::decrypt( $stored, self::CRYPTO_CONTEXT ) : '';
	}

	public static function has_api_key( string $provider ): bool {
		return '' !== self::api_key( $provider );
	}

	public static function set_api_key( string $provider, string $key ): void {
		$key = trim( $key );
		if ( '' === $key ) {
			delete_option( self::key_option( $provider ) );
			return;
		}
		update_option( self::key_option( $provider ), Crypto::encrypt( $key, self::CRYPTO_CONTEXT ) );
	}

	public static function clear_api_key( string $provider ): void {
		delete_option( self::key_option( $provider ) );
	}

	/**
	 * A masked hint for the settings screen — never the real key.
	 */
	public static function masked_key( string $provider ): string {
		if ( ! self::has_api_key( $provider ) ) {
			return '';
		}
		return str_repeat( '•', 20 );
	}

	private static function key_option( string $provider ): string {
		return self::KEY_OPTION_BASE . sanitize_key( $provider );
	}
}
