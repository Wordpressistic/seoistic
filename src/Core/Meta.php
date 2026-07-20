<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

use Wpistic\SeoCore\Meta\MetaTags;

/**
 * Titles, meta descriptions, canonicals, robots, Open Graph and Twitter — resolved
 * from Core\PostSeo and rendered in <head>. The editor UI for these fields lives in
 * Admin\SeoMetabox; this class only ever reads.
 */
final class Meta {

	public function register(): void {
		add_filter( 'pre_get_document_title', array( $this, 'title' ), 20 );
		remove_action( 'wp_head', 'rel_canonical' );
		add_action( 'wp_head', array( $this, 'output' ), 1 );
	}

	public function title( $title ) {
		$resolved = $this->resolve_title();
		return '' !== $resolved ? $resolved : $title;
	}

	private function sep(): string {
		return (string) get_option( 'seoistic_title_sep', '–' );
	}

	private function resolve_title(): string {
		$name = get_bloginfo( 'name' );

		if ( is_singular() ) {
			$id     = get_queried_object_id();
			$custom = PostSeo::title( $id );
			if ( '' !== $custom ) {
				return $this->vars( $custom, $id );
			}
			return get_the_title( $id ) . ' ' . $this->sep() . ' ' . $name;
		}
		if ( is_front_page() ) {
			$tagline = get_bloginfo( 'description' );
			return $tagline ? $name . ' ' . $this->sep() . ' ' . $tagline : $name;
		}
		if ( is_post_type_archive() ) {
			return post_type_archive_title( '', false ) . ' ' . $this->sep() . ' ' . $name;
		}
		if ( is_tax() || is_category() || is_tag() ) {
			return single_term_title( '', false ) . ' ' . $this->sep() . ' ' . $name;
		}
		if ( is_search() ) {
			/* translators: %s: search query. */
			return sprintf( __( 'Search results for "%s"', 'seoistic' ), get_search_query() ) . ' ' . $this->sep() . ' ' . $name;
		}
		return '';
	}

	private function vars( string $template, int $id ): string {
		return strtr(
			$template,
			array(
				'%title%'    => get_the_title( $id ),
				'%sitename%' => get_bloginfo( 'name' ),
				'%sep%'      => $this->sep(),
			)
		);
	}

	private function tags(): MetaTags {
		$title     = $this->resolve_title();
		$desc      = $this->resolve_desc();
		$canonical = $this->canonical();
		$image     = $this->image();

		$og_title = is_singular() ? PostSeo::og_title( get_queried_object_id() ) : '';
		$og_desc  = is_singular() ? PostSeo::og_description( get_queried_object_id() ) : '';

		$og = array(
			'og:title'       => '' !== $og_title ? $og_title : $title,
			'og:description' => '' !== $og_desc ? $og_desc : $desc,
			'og:url'         => $canonical,
			'og:type'        => is_singular() ? 'article' : 'website',
			'og:site_name'   => get_bloginfo( 'name' ),
		);
		$tw = array(
			'twitter:card'        => 'summary_large_image',
			'twitter:title'       => $og['og:title'],
			'twitter:description' => $og['og:description'],
		);
		if ( '' !== $image ) {
			$og['og:image']      = $image;
			$tw['twitter:image'] = $image;
		}

		/** @param array<string,string> $og */
		$og = apply_filters( 'seoistic/open_graph', $og );
		/** @param array<string,string> $tw */
		$tw = apply_filters( 'seoistic/twitter', $tw );

		return new MetaTags( $title, $desc, $canonical, $this->robots(), $og, $tw );
	}

	public function output(): void {
		if ( is_admin() ) {
			return;
		}
		$m = $this->tags();

		echo "\n<!-- SEOISTIC -->\n";
		if ( '' !== $m->description ) {
			echo '<meta name="description" content="' . esc_attr( $m->description ) . '">' . "\n";
		}
		if ( '' !== $m->canonical ) {
			echo '<link rel="canonical" href="' . esc_url( $m->canonical ) . '">' . "\n";
		}
		echo '<meta name="robots" content="' . esc_attr( $m->robots ) . '">' . "\n";
		foreach ( $m->openGraph as $property => $value ) {
			if ( '' !== $value ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $value ) . '">' . "\n";
			}
		}
		foreach ( $m->twitter as $name => $value ) {
			if ( '' !== $value ) {
				echo '<meta name="' . esc_attr( $name ) . '" content="' . esc_attr( $value ) . '">' . "\n";
			}
		}
		echo "<!-- /SEOISTIC -->\n";
	}

	private function resolve_desc(): string {
		if ( is_singular() ) {
			$id     = get_queried_object_id();
			$custom = PostSeo::description( $id );
			if ( '' !== $custom ) {
				return $custom;
			}
			$source = has_excerpt( $id ) ? get_the_excerpt( $id ) : wp_strip_all_tags( (string) get_post_field( 'post_content', $id ) );
			return wp_trim_words( $source, 30, '' );
		}
		if ( is_tax() || is_category() || is_tag() ) {
			$desc = term_description();
			if ( $desc ) {
				return wp_trim_words( wp_strip_all_tags( $desc ), 30, '' );
			}
		}
		return (string) get_bloginfo( 'description' );
	}

	private function canonical(): string {
		if ( is_singular() ) {
			$id       = get_queried_object_id();
			$override = PostSeo::canonical( $id );
			return '' !== $override ? $override : (string) get_permalink( $id );
		}
		if ( is_front_page() ) {
			return home_url( '/' );
		}
		if ( is_post_type_archive() ) {
			return (string) get_post_type_archive_link( get_post_type() );
		}
		if ( is_tax() || is_category() || is_tag() ) {
			$link = get_term_link( get_queried_object() );
			return is_wp_error( $link ) ? '' : (string) $link;
		}
		return '';
	}

	private function robots(): string {
		if ( is_search() || is_404() ) {
			return 'noindex, follow';
		}
		if ( is_singular() ) {
			$id       = get_queried_object_id();
			$robots   = array( PostSeo::is_noindex( $id ) ? 'noindex' : 'index', PostSeo::is_nofollow( $id ) ? 'nofollow' : 'follow' );
			return implode( ', ', $robots );
		}
		return 'index, follow';
	}

	/**
	 * The per-post `_seoistic_og_image` override is deliberately NOT read here — that
	 * is the Social / Open Graph addon's job (Addon\SocialModule::og(), hooked on
	 * `seoistic/open_graph`), so disabling that module correctly turns the override
	 * off. This only resolves the always-on fallbacks: featured image, then the
	 * site-wide default share image.
	 */
	private function image(): string {
		if ( is_singular() ) {
			$id = get_queried_object_id();
			if ( has_post_thumbnail( $id ) ) {
				$src = wp_get_attachment_image_src( get_post_thumbnail_id( $id ), 'large' );
				if ( $src ) {
					return (string) $src[0];
				}
			}
		}
		$default = (string) get_option( 'seoistic_default_share_image', '' );
		return $default;
	}
}
