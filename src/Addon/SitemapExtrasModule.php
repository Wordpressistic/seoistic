<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Addon;

use Wpistic\Seoistic\Core\PostSeo;
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
		$seen = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
			if ( 'attachment' === $type->name || ! is_post_type_viewable( $type->name ) ) {
				continue;
			}
			$posts = get_posts(
				array(
					'post_type'           => $type->name,
					'post_status'         => 'publish',
					'posts_per_page'      => 50000,
					'orderby'             => 'title',
					'order'               => 'ASC',
					'no_found_rows'       => true,
					'ignore_sticky_posts' => true,
				)
			);
			if ( ! $posts ) {
				continue;
			}
			$items = '';
			foreach ( $posts as $post ) {
				$url = get_permalink( $post );
				if ( ! $url || isset( $seen[ $url ] ) || PostSeo::is_noindex( $post->ID ) || ! apply_filters( 'seoistic_html_sitemap_include_post', true, $post ) ) {
					continue;
				}
				$seen[ $url ] = true;
				$items .= '<li><a href="' . esc_url( $url ) . '">' . esc_html( get_the_title( $post ) ) . '</a></li>';
			}
			if ( '' !== $items ) {
				$out .= '<h2>' . esc_html( $type->labels->name ) . '</h2><ul>' . $items . '</ul>';
			}
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
