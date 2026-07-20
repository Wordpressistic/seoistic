<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * robots.txt — appends the sitemap reference, or serves the user-applied ruleset
 * from the AI Tools robots.txt generator (Core\PostSeo-adjacent option
 * `seoistic_robots_rules`) once they've previewed and confirmed it. Nothing here
 * ever writes a physical file — WordPress's virtual /robots.txt handles both cases.
 */
final class Robots {

	public function register(): void {
		add_filter( 'robots_txt', array( $this, 'robots' ), 10, 2 );
	}

	public function robots( $output, $public ): string {
		if ( ! $public ) {
			return (string) $output;
		}

		$custom = trim( (string) get_option( 'seoistic_robots_rules', '' ) );
		if ( '' !== $custom ) {
			if ( false === stripos( $custom, 'sitemap:' ) ) {
				$custom .= "\nSitemap: " . home_url( '/wp-sitemap.xml' );
			}
			return $custom . "\n";
		}

		return (string) $output . "\nSitemap: " . esc_url( home_url( '/wp-sitemap.xml' ) ) . "\n";
	}
}
