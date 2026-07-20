<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Install;

final class Deactivator {

	public static function deactivate(): void {
		wp_clear_scheduled_hook( \Wpistic\Seoistic\Core\ScheduledAudit::CRON_HOOK );
		wp_clear_scheduled_hook( 'seoistic_license_cron' );
		flush_rewrite_rules();
	}
}
