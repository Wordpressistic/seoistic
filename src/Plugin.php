<?php

declare(strict_types=1);

namespace Wpistic\Seoistic;

/**
 * SEOISTIC bootstrap. Always-on core SEO services + the toggleable addon modules.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		load_plugin_textdomain( 'seoistic', false, dirname( plugin_basename( SEOISTIC_FILE ) ) . '/languages' );

		if ( is_admin() ) {
			Install\Tables::maybe_upgrade();
		}

		// Core engine — always on, free.
		( new Core\Meta() )->register();
		( new Core\Schema() )->register();
		( new Core\Sitemaps() )->register();
		( new Core\Robots() )->register();
		( new Core\Breadcrumbs() )->register();
		( new Core\LlmsTxt() )->register();
		( new Core\ScoreRecalculator() )->register();
		( new Core\ScheduledAudit() )->register();

		// Licensing (entitlement gate for premium addons) — registers before modules boot.
		( new License\License() )->register();

		// Addon modules.
		$registry = new Module\ModuleRegistry();
		$registry->discover();
		$registry->boot();

		if ( is_admin() ) {
			( new Admin\Admin( $registry ) )->register();
			( new Admin\SeoColumns() )->register();
			( new Admin\SeoMetabox() )->register();
			( new Admin\AiSettingsPage() )->register();
			( new Admin\AiToolsPage() )->register();
			( new Admin\AutomationSettingsPage() )->register();
			( new Admin\ContentHealthPage() )->register();
			( new Admin\ContentInventoryPage() )->register();
		}

		( new AI\RestController() )->register();

		add_action( 'init', array( $this, 'maybe_flush' ), 999 );
	}

	public function maybe_flush(): void {
		if ( get_option( 'seoistic_flush' ) ) {
			flush_rewrite_rules();
			delete_option( 'seoistic_flush' );
		}
	}
}
