<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Module;

/**
 * Discovers and boots addon modules. Free modules toggle on/off (RankMath-style);
 * premium modules are listed with badges and only register once active + entitled.
 */
final class ModuleRegistry {

	private const OPTION = 'seoistic_modules';

	/** @var array<string, Module> */
	private array $modules = array();

	public function discover(): void {
		$classes = array(
			// 10 free addons.
			\Wpistic\Seoistic\Addon\SchemaModule::class,
			\Wpistic\Seoistic\Addon\LocalSeoModule::class,
			\Wpistic\Seoistic\Addon\WooCommerceModule::class,
			\Wpistic\Seoistic\Addon\ImageSeoModule::class,
			\Wpistic\Seoistic\Addon\RedirectsModule::class,
			\Wpistic\Seoistic\Addon\LinkManagerModule::class,
			\Wpistic\Seoistic\Addon\SitemapExtrasModule::class,
			\Wpistic\Seoistic\Addon\SocialModule::class,
			\Wpistic\Seoistic\Addon\MigrationModule::class,
			\Wpistic\Seoistic\Addon\AuditGateModule::class,
			\Wpistic\Seoistic\Addon\IndexisticModule::class,
			// Real premium addons (listing entries — AiModule's actual generation is
			// wired outside the registry so its settings/tools pages always exist;
			// GscModule wires its own admin page normally, gated by entitlement).
			\Wpistic\Seoistic\Addon\AiModule::class,
			\Wpistic\Seoistic\Addon\GscModule::class,
			\Wpistic\Seoistic\Addon\BusinessAutomatorModule::class,
			// 4 premium addons (coming soon — listed, not yet built).
			\Wpistic\Seoistic\Addon\AiSearchModule::class,
			\Wpistic\Seoistic\Addon\RankTrackerModule::class,
			\Wpistic\Seoistic\Addon\SchemaProModule::class,
			\Wpistic\Seoistic\Addon\PerformanceModule::class,
		);

		foreach ( $classes as $class ) {
			if ( class_exists( $class ) ) {
				$module = new $class();
				$this->modules[ $module->id() ] = $module;
			}
		}

		/**
		 * Filter the registered modules (third-party addons can add their own).
		 *
		 * @param array<string, Module> $modules
		 */
		$this->modules = apply_filters( 'seoistic/modules', $this->modules );
	}

	public function boot(): void {
		foreach ( $this->modules as $module ) {
			if ( 'active' !== $module->status() ) {
				continue;
			}
			if ( ! $this->is_enabled( $module ) ) {
				continue;
			}
			if ( ! Entitlement::can( $module->id(), $module->tier() ) ) {
				continue;
			}
			$module->register();
		}
	}

	/**
	 * @return array<string, Module>
	 */
	public function all(): array {
		return $this->modules;
	}

	public function is_enabled( Module $module ): bool {
		$state = get_option( self::OPTION, array() );
		if ( isset( $state[ $module->id() ] ) ) {
			return (bool) $state[ $module->id() ];
		}
		return $module->defaultEnabled();
	}

	public function set_enabled( string $id, bool $enabled ): void {
		$state        = get_option( self::OPTION, array() );
		$state[ $id ] = $enabled ? 1 : 0;
		update_option( self::OPTION, $state );
	}
}
