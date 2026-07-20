<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Module;

abstract class AbstractModule implements Module {

	public function tier(): string {
		return 'free';
	}

	public function status(): string {
		return 'active';
	}

	public function defaultEnabled(): bool {
		return true;
	}

	public function register(): void {
		// Coming-soon and premium modules override with real behaviour later.
	}
}
