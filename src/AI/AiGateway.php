<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI;

use Wpistic\Seoistic\AI\Providers\ProviderFactory;
use Wpistic\Seoistic\Module\Entitlement;

/**
 * The single choke point every AI code path funnels through, regardless of
 * which provider (OpenRouter, Groq, or a self-hosted Ollama) is configured.
 * The SEOISTIC AI (Pro) entitlement check lives here — once, not repeated at
 * every call site — same as the old OpenRouterClient this replaces.
 */
final class AiGateway {

	/**
	 * @param array<int, array{role:string, content:string}> $messages
	 * @return array{success:bool, content:string, error:string}
	 */
	public function chat( array $messages, ?string $model = null, ?float $temperature = null, ?int $max_tokens = null ): array {
		if ( ! Entitlement::can( 'ai', 'premium' ) ) {
			return array( 'success' => false, 'content' => '', 'error' => __( 'SEOISTIC AI is a Pro feature. Upgrade your plan to enable AI generation.', 'seoistic' ) );
		}
		if ( ! AiSettings::is_enabled() ) {
			return array( 'success' => false, 'content' => '', 'error' => __( 'AI features are disabled in SEOISTIC → Settings → AI.', 'seoistic' ) );
		}

		return ProviderFactory::make()->chat(
			$messages,
			$model ?? AiSettings::model(),
			$temperature ?? AiSettings::temperature(),
			$max_tokens ?? AiSettings::max_tokens()
		);
	}
}
