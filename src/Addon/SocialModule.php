<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

/**
 * Per-post social share image + a site-wide default, layered onto the core Open
 * Graph output. The share-image field itself is edited in the SEOISTIC panel's
 * Social tab (Admin\SeoMetabox); this module is what makes that field actually
 * take effect — disabling it falls back to the featured image / default only.
 */
final class SocialModule extends AbstractModule {

	public function id(): string {
		return 'social';
	}

	public function name(): string {
		return __( 'Social / Open Graph', 'seoistic' );
	}

	public function description(): string {
		return __( 'Applies your per-post share image (set in the SEOISTIC panel) to Open Graph output, with a site-wide default fallback.', 'seoistic' );
	}

	public function register(): void {
		add_filter( 'seoistic/open_graph', array( $this, 'og' ) );
		add_filter( 'seoistic/twitter', array( $this, 'twitter' ) );
	}

	/**
	 * @param array<string, string> $og
	 * @return array<string, string>
	 */
	public function og( $og ) {
		$image = $this->resolve_image( $og['og:image'] ?? '' );
		if ( '' !== $image ) {
			$og['og:image'] = $image;
		}
		return $og;
	}

	/**
	 * @param array<string, string> $tw
	 * @return array<string, string>
	 */
	public function twitter( $tw ) {
		$image = $this->resolve_image( $tw['twitter:image'] ?? '' );
		if ( '' !== $image ) {
			$tw['twitter:image'] = $image;
		}
		return $tw;
	}

	private function resolve_image( string $current ): string {
		if ( is_singular() ) {
			$custom = (string) get_post_meta( get_queried_object_id(), '_seoistic_og_image', true );
			if ( '' !== $custom ) {
				return $custom;
			}
		}
		if ( '' !== $current ) {
			return $current;
		}
		return (string) get_option( 'seoistic_default_share_image', '' );
	}
}
