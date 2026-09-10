<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Install;

use Wpistic\Seoistic\Addon\BusinessAutomatorModule;
use Wpistic\Seoistic\Core\Performance\PerformanceService;
use Wpistic\Seoistic\RankTracker\RankTrackerService;

final class Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( \Wpistic\Seoistic\Core\ScheduledAudit::CRON_HOOK );
		wp_clear_scheduled_hook( 'seoistic_license_cron' );
		wp_clear_scheduled_hook( PerformanceService::CRON_TEST_HOOK );
		wp_clear_scheduled_hook( PerformanceService::CRON_ALERT_HOOK );
		wp_clear_scheduled_hook( RankTrackerService::CRON_DAILY_HOOK );
		wp_clear_scheduled_hook( RankTrackerService::CRON_REPORT_HOOK );
		wp_clear_scheduled_hook( BusinessAutomatorModule::CRON_DAILY_HOOK );
		wp_clear_scheduled_hook( BusinessAutomatorModule::CRON_WEEKLY_HOOK );
		flush_rewrite_rules();
	}
}
