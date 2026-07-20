<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Admin\GscPage;
use Wpistic\Seoistic\Module\AbstractModule;

/**
 * Google Search Console — real indexing/coverage status and query/click data
 * pulled into the dashboard via OAuth (read-only scope). Business-tier, per
 * docs/pricing-strategy.md ("Rank tracker + GSC & GA4 dashboard" is a listed
 * Business feature) — unlike Indexistic, this wasn't a blank slate, so it
 * follows the plan the pricing doc already committed to rather than
 * defaulting free. See docs/indexistic-roadmap.md for the reasoning.
 */
final class GscModule extends AbstractModule {

	public function id(): string {
		return 'gsc';
	}

	public function name(): string {
		return __( 'Search Console', 'seoistic' );
	}

	public function description(): string {
		return __( 'Connect Google Search Console for real indexing/coverage status and query/click data, right in your SEOISTIC dashboard.', 'seoistic' );
	}

	public function tier(): string {
		return 'premium';
	}

	public function register(): void {
		( new GscPage() )->register();
	}
}
