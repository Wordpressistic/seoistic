<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

/**
 * Listing entry only — the real AI generation lives in src/AI (AiGateway,
 * AiService, RestController) and is wired directly from Plugin::boot() so the AI
 * settings/tools pages exist regardless of this module's toggle state. Entitlement
 * (Pro plan required) is enforced once, in AiGateway::chat().
 */
final class AiModule extends AbstractModule {

	public function id(): string {
		return 'ai';
	}

	public function name(): string {
		return __( 'SEOISTIC AI', 'seoistic' );
	}

	public function description(): string {
		return __( 'Generate titles, meta descriptions, focus keywords, schema, alt text and full-page optimizations with OpenRouter, Groq, or your own self-hosted Ollama.', 'seoistic' );
	}

	public function tier(): string {
		return 'premium';
	}

	public function status(): string {
		return 'active';
	}

	public function defaultEnabled(): bool {
		return false;
	}
}
