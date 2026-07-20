<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI\Providers;

use Wpistic\Seoistic\AI\AiSettings;

/**
 * Resolves the currently configured AI provider. This is the only place that
 * needs to know about every ProviderInterface implementation.
 */
final class ProviderFactory {

	public static function make(): ProviderInterface {
		return match ( AiSettings::provider() ) {
			'groq' => new GroqProvider(),
			'ollama' => new OllamaProvider(),
			default => new OpenRouterProvider(),
		};
	}
}
