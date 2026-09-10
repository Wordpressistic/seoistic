<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;
use Wpistic\Seoistic\Admin\SchemaBuilderPage;

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
		return 'active';
	}

	public function defaultEnabled(): bool {
		return false;
	}

	public function register(): void {
		( new SchemaBuilderPage() )->register();
		add_filter( 'seoistic/schema_nodes', array( $this, 'schema_nodes' ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $nodes
	 * @return array<int, array<string, mixed>>
	 */
	public function schema_nodes( array $nodes ): array {
		$existing = array();
		foreach ( $nodes as $node ) {
			if ( isset( $node['@id'] ) && is_string( $node['@id'] ) ) {
				$existing[ $node['@id'] ] = true;
			}
		}
		foreach ( ( new SchemaBlockRepository() )->nodes_for_current_request() as $node ) {
			if ( isset( $node['@id'], $existing[ $node['@id'] ] ) ) {
				continue;
			}
			if ( isset( $node['@id'] ) && is_string( $node['@id'] ) ) {
				$existing[ $node['@id'] ] = true;
			}
			$nodes[] = $node;
		}
		return $nodes;
	}
}
