<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Module\AbstractModule;

/**
 * Sitemap extras: an HTML sitemap shortcode and search-engine ping on publish.
 * News & video XML sitemaps extend this module.
 */
final class SitemapExtrasModule extends AbstractModule {

	public function id(): string {
		return 'sitemap_extras';
	}

	public function name(): string {
		return __( 'Sitemap Extras', 'seoistic' );
	}

	public function description(): string {
		return __( 'HTML sitemap shortcode and search-engine ping on publish. News & video sitemaps on the roadmap.', 'seoistic' );
	}

	public function register(): void {
		add_shortcode( 'seoistic_html_sitemap', array( $this, 'html_sitemap' ) );
		add_action( 'transition_post_status', array( $this, 'ping' ), 10, 3 );
	}

	public function html_sitemap( $atts ): string {
		$out = '<div class="seoistic-html-sitemap">';
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name ) {
				continue;
			}
			$posts = get_posts( array( 'post_type' => $type->name, 'numberposts' => 300, 'orderby' => 'title', 'order' => 'ASC' ) );
			if ( ! $posts ) {
				continue;
			}
			$out .= '<h2>' . esc_html( $type->labels->name ) . '</h2><ul>';
			foreach ( $posts as $post ) {
				$out .= '<li><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
			}
			$out .= '</ul>';
		}
		return $out . '</div>';
	}

	/**
	 * Google retired its public sitemap-ping endpoint in 2023 (search engines
	 * discover sitemaps via robots.txt / Search Console submission instead) —
	 * only Bing's still does anything, same as Core\Sitemaps::ping().
	 */
	public function ping( $new_status, $old_status, $post ): void {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		$sitemap = rawurlencode( home_url( '/wp-sitemap.xml' ) );
		wp_remote_get( 'https://www.bing.com/ping?sitemap=' . $sitemap, array( 'blocking' => false, 'timeout' => 5 ) );
	}
}
