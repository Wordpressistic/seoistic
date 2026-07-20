<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

final class SchemaProModule extends AbstractModule {

	public function id(): string {
		return 'schema_pro';
	}

	public function name(): string {
		return __( 'Schema Pro / Custom Builder', 'seoistic' );
	}

	public function description(): string {
		return __( 'Visual schema builder, templates with display conditions, 800+ types, and import schema from any URL.', 'seoistic' );
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
