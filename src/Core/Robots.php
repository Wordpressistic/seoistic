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

		$output = (string) $output;

		$custom = trim( (string) get_option( 'seoistic_robots_rules', '' ) );
		if ( '' !== $custom ) {
			if ( false === stripos( $custom, 'sitemap:' ) ) {
				$custom .= "\nSitemap: " . home_url( '/wp-sitemap.xml' );
			}
			return $custom . "\n";
		}

		/*
		 * WP core already appends its own "Sitemap:" line while sitemaps are
		 * enabled (5.5+), so only add ours when one isn't present — otherwise
		 * crawlers see the same sitemap listed twice.
		 */
		if ( false === stripos( $output, 'sitemap:' ) ) {
			$output .= "\nSitemap: " . esc_url( home_url( '/wp-sitemap.xml' ) );
		}

		return $output . "\n";
	}
}
