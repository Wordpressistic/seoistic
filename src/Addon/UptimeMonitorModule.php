<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;
use Wpistic\Seoistic\Uptime\UptimeMonitor;
use Wpistic\Seoistic\Uptime\UptimeRestController;

/**
 * Domain / uptime monitor addon. Fully self-contained: checks run in WP-Cron
 * against the monitored URLs directly — no external service involved. Unlocks
 * with a Pro license like the other premium addons.
 */
final class UptimeMonitorModule extends AbstractModule {

	public function id(): string {
		return 'uptime';
	}

	public function name(): string {
		return __( 'Domain & Uptime Monitor', 'seoistic' );
	}

	public function description(): string {
		return __( 'Monitor your site and other domains for downtime, response time and SSL expiry — with email alerts when something goes down or recovers.', 'seoistic' );
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
		// Cron engine + schedule (front-end and admin — WP-Cron fires on the front end).
		( new UptimeMonitor() )->register();

		// REST API.
		( new UptimeRestController() )->register();

		// Admin page.
		if ( is_admin() ) {
			( new \Wpistic\Seoistic\Admin\UptimeMonitorPage() )->register();
		}
	}
}
