<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

final class PerformanceModule extends AbstractModule {

	public function id(): string {
		return 'performance';
	}

	public function name(): string {
		return __( 'Performance & Core Web Vitals', 'seoistic' );
	}

	public function description(): string {
		return __( 'Real Core Web Vitals monitoring (CrUX + lab), per-page PageSpeed, and actionable fixes.', 'seoistic' );
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
