<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

final class RankTrackerModule extends AbstractModule {

	public function id(): string {
		return 'rank_tracker';
	}

	public function name(): string {
		return __( 'Rank Tracker & Reports', 'seoistic' );
	}

	public function description(): string {
		return __( 'Track keyword positions and generate scheduled white-label client reports. Pairs with the Search Console addon for indexing/query data.', 'seoistic' );
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
