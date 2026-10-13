<?php
/**
 * Connections — one Google connection for GA4 + Search Console + PageSpeed.
 *
 * Premium module: requires an active WPistic license. Once licensed, the user
 * clicks "Connect Google" once; the combined read-only OAuth grant covers
 * Analytics (GA4) and Search Console, while PageSpeed runs keyless (optional
 * own key for heavy use). Everything is managed from this single screen.
 *
 * @package Seoistic
 */

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Connections\ConnectionsPage;
use Wpistic\Seoistic\Module\AbstractModule;

final class ConnectionsModule extends AbstractModule {

	public function id(): string {
		return 'connections';
	}

	public function name(): string {
		return __( 'Connections (GA4 · GSC · PageSpeed)', 'seoistic' );
	}

	public function description(): string {
		return __( 'One license key, one Connect Google click — GA4, Search Console and PageSpeed data, all inside SEOISTIC. No separate plugins, no per-service setup.', 'seoistic' );
	}

	public function tier(): string {
		return 'premium';
	}

	public function register(): void {
		( new ConnectionsPage() )->register();
	}
}
