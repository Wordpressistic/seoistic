<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

final class AiSearchModule extends AbstractModule {

	public function id(): string {
		return 'ai_search';
	}

	public function name(): string {
		return __( 'AI Search Visibility (AEO)', 'seoistic' );
	}

	public function description(): string {
		return __( 'Optimize for ChatGPT, Perplexity, Gemini and Google AI Overviews — and track where you are cited. No other SEO plugin does this.', 'seoistic' );
	}

	public function tier(): string {
		return 'premium';
	}

	public function status(): string {
		return 'coming_soon';
	}

	public function defaultEnabled(): bool {
		return false;
	}
}
