<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI\Providers;

use Wpistic\Seoistic\AI\AiSettings;

/**
 * openrouter.ai — pay-per-token, but ships free-tier models too (its own
 * ":free" model variants). Requires an API key.
 */
final class OpenRouterProvider implements ProviderInterface {

	private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

	public function chat( array $messages, string $model, float $temperature, int $max_tokens ): array {
		$key = AiSettings::api_key( 'openrouter' );
		if ( '' === $key ) {
			return array( 'success' => false, 'content' => '', 'error' => __( 'No OpenRouter API key configured.', 'seoistic' ) );
		}

		return OpenAiCompatibleClient::chat(
			self::ENDPOINT,
			array(
				'Authorization' => 'Bearer ' . $key,
				'HTTP-Referer'  => home_url( '/' ),
				'X-Title'       => 'SEOISTIC',
			),
			$this->sanitize_model( $model ),
			$messages,
			$temperature,
			$max_tokens
		);
	}

	/**
	 * Only ever allow one of the models we actually list — never pass client
	 * input straight through to the provider.
	 */
	private function sanitize_model( string $model ): string {
		return isset( AiSettings::OPENROUTER_MODELS[ $model ] ) ? $model : 'openai/gpt-4.1-nano';
	}
}
