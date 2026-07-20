<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

/**
 * LocalBusiness schema from settings — free (Yoast and RankMath gate this).
 */
final class LocalSeoModule extends AbstractModule {

	public function id(): string {
		return 'local';
	}

	public function name(): string {
		return __( 'Local SEO', 'seoistic' );
	}

	public function description(): string {
		return __( 'LocalBusiness schema, address, opening hours and maps — free.', 'seoistic' );
	}

	public function register(): void {
		add_filter( 'seoistic/schema_nodes', array( $this, 'business' ) );
	}

	/**
	 * @param array<int, array<string, mixed>> $nodes
	 * @return array<int, array<string, mixed>>
	 */
	public function business( $nodes ) {
		$config = get_option( 'seoistic_local', array() );
		if ( ! is_array( $config ) || empty( $config['name'] ) || ! is_front_page() ) {
			return $nodes;
		}

		$node = array(
			'@type' => $config['type'] ?? 'LocalBusiness',
			'name'  => $config['name'],
			'url'   => home_url( '/' ),
		);
		if ( ! empty( $config['phone'] ) ) {
			$node['telephone'] = $config['phone'];
		}
		if ( ! empty( $config['address'] ) ) {
			$node['address'] = array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $config['address'],
				'addressLocality' => $config['city'] ?? '',
				'addressCountry'  => $config['country'] ?? '',
			);
		}
		$nodes[] = $node;
		return $nodes;
	}
}
