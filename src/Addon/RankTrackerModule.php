<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Admin\RankTrackerPage;
use Wpistic\Seoistic\RankTracker\RankTrackerService;
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
		return 'active';
	}

	public function defaultEnabled(): bool {
		return false;
	}

	public function register(): void {
		( new RankTrackerService() )->register();
		if ( is_admin() ) {
			( new RankTrackerPage() )->register();
		}
	}
}
