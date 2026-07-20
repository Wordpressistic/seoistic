<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

/**
 * Adds rel="nofollow noopener" to external links in content automatically. Internal-link
 * and orphan tools extend this module.
 */
final class LinkManagerModule extends AbstractModule {

	public function id(): string {
		return 'links';
	}

	public function name(): string {
		return __( 'Link Manager', 'seoistic' );
	}

	public function description(): string {
		return __( 'Automatic nofollow on external links; internal-link and orphan tools.', 'seoistic' );
	}

	public function register(): void {
		add_filter( 'the_content', array( $this, 'external_nofollow' ), 20 );
	}

	public function external_nofollow( $content ) {
		if ( ! is_singular() || false === stripos( (string) $content, '<a ' ) ) {
			return $content;
		}
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		return (string) preg_replace_callback(
			'/<a\s[^>]*href=["\']([^"\']+)["\'][^>]*>/i',
			static function ( $matches ) use ( $host ) {
				$link_host = wp_parse_url( $matches[1], PHP_URL_HOST );
				if ( $link_host && $link_host !== $host && false === stripos( $matches[0], 'rel=' ) ) {
					return rtrim( $matches[0], '>' ) . ' rel="nofollow noopener">';
				}
				return $matches[0];
			},
			(string) $content
		);
	}
}
