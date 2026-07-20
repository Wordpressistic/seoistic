<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI\Providers;

use Wpistic\Seoistic\AI\AiSettings;

/**
 * Groq — free-tier inference on their own LPU hardware (fast, generous free
 * rate limits as of writing). Requires an API key; OpenAI-compatible endpoint.
 */
final class GroqProvider implements ProviderInterface {

	private const ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';

	public function chat( array $messages, string $model, float $temperature, int $max_tokens ): array {
		$key = AiSettings::api_key( 'groq' );
		if ( '' === $key ) {
			return array( 'success' => false, 'content' => '', 'error' => __( 'No Groq API key configured.', 'seoistic' ) );
		}

		return OpenAiCompatibleClient::chat(
			self::ENDPOINT,
			array( 'Authorization' => 'Bearer ' . $key ),
			$this->sanitize_model( $model ),
			$messages,
			$temperature,
			$max_tokens
		);
	}

	private function sanitize_model( string $model ): string {
		return isset( AiSettings::GROQ_MODELS[ $model ] ) ? $model : 'llama-3.1-8b-instant';
	}
}
