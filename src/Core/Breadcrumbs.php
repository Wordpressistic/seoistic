<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

/**
 * Breadcrumb trail. Provides the data for BreadcrumbList JSON-LD (Schema) and a
 * [seoistic_breadcrumbs] shortcode for themes that want the visual trail from us.
 */
final class Breadcrumbs {

	public function register(): void {
		add_shortcode( 'seoistic_breadcrumbs', array( $this, 'shortcode' ) );
	}

	/**
	 * @return array<int, array{label: string, url: string}>
	 */
	public static function trail(): array {
		$trail = array( array( 'label' => __( 'Home', 'seoistic' ), 'url' => home_url( '/' ) ) );

		if ( is_singular() ) {
			$id     = get_queried_object_id();
			$object = get_post_type_object( get_post_type( $id ) );
			if ( $object && $object->has_archive ) {
				$link = get_post_type_archive_link( get_post_type( $id ) );
				if ( $link ) {
					$trail[] = array( 'label' => $object->labels->name, 'url' => (string) $link );
				}
			}
			$trail[] = array( 'label' => get_the_title( $id ), 'url' => '' );
		} elseif ( is_post_type_archive() ) {
			$trail[] = array( 'label' => post_type_archive_title( '', false ), 'url' => '' );
		} elseif ( is_tax() || is_category() || is_tag() ) {
			$trail[] = array( 'label' => single_term_title( '', false ), 'url' => '' );
		} elseif ( is_search() ) {
			$trail[] = array( 'label' => __( 'Search', 'seoistic' ), 'url' => '' );
		}

		return $trail;
	}

	public function shortcode( $atts ): string {
		$trail = self::trail();
		if ( count( $trail ) < 2 ) {
			return '';
		}
		$out  = '<nav class="seoistic-breadcrumbs" aria-label="' . esc_attr__( 'Breadcrumb', 'seoistic' ) . '">';
		$last = count( $trail ) - 1;
		foreach ( $trail as $index => $crumb ) {
			if ( $index === $last || '' === $crumb['url'] ) {
				$out .= '<span>' . esc_html( $crumb['label'] ) . '</span>';
			} else {
				$out .= '<a href="' . esc_url( $crumb['url'] ) . '">' . esc_html( $crumb['label'] ) . '</a> / ';
			}
		}
		return $out . '</nav>';
	}
}
