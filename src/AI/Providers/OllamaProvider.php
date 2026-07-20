<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI\Providers;

use Wpistic\Seoistic\AI\AiSettings;

/**
 * A self-hosted Ollama instance (e.g. running on the site owner's own VPS).
 * No API key — Ollama's OpenAI-compatible /v1 endpoint is reached directly at
 * whatever base URL the admin configured. Free and fully private: nothing
 * ever leaves their own infrastructure.
 */
final class OllamaProvider implements ProviderInterface {

	public function chat( array $messages, string $model, float $temperature, int $max_tokens ): array {
		$base = AiSettings::ollama_base_url();
		if ( '' === $base ) {
			return array( 'success' => false, 'content' => '', 'error' => __( 'No Ollama base URL configured.', 'seoistic' ) );
		}

		$model = '' !== $model ? $model : AiSettings::ollama_model();
		if ( '' === $model ) {
			return array( 'success' => false, 'content' => '', 'error' => __( 'No Ollama model configured — enter the model name exactly as it appears in `ollama list`.', 'seoistic' ) );
		}

		return OpenAiCompatibleClient::chat(
			$base . '/v1/chat/completions',
			array(),
			$model,
			$messages,
			$temperature,
			$max_tokens
		);
	}
}
