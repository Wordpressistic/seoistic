<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Install;

use Wpistic\Seoistic\Core\Performance\PerformanceService;

final class Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( \Wpistic\Seoistic\Core\ScheduledAudit::CRON_HOOK );
		wp_clear_scheduled_hook( 'seoistic_license_cron' );
		wp_clear_scheduled_hook( PerformanceService::CRON_TEST_HOOK );
		wp_clear_scheduled_hook( PerformanceService::CRON_ALERT_HOOK );
		flush_rewrite_rules();
	}
}
