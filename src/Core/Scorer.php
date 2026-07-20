<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\Core;

use WP_Post;

/**
 * Lightweight per-post SEO score (0–100), cheap enough to run on every save_post
 * without a page-load penalty. Ten equally-weighted checks; each returns pass/fail
 * plus a short human label so the Audit tab and the list-table ring can share it.
 *
 * This is deliberately simpler than the seo-core Auditor (used by the Content Audit
 * Gate addon for its stricter pass/warn/fail publish gate) — the Scorer is the fast
 * "at a glance" number shown everywhere else.
 */
final class Scorer {

	/**
	 * Version of the scoring formula. Bump whenever a check is added, removed
	 * or reweighted — /analyze reports it so clients can detect drift.
	 */
	public const VERSION = '2026.07';

	private const DEFAULT_MIN_WORDS = 300;

	/**
	 * @return array{score:int, checks: array<int, array{id:string,label:string,pass:bool,message:string}>}
	 */
	public static function score( WP_Post $post ): array {
		return self::evaluate( $post, array() );
	}

	/**
	 * Live analysis: the exact same deterministic checks as score(), with draft
	 * (unsaved) values overriding the stored ones. Never persists anything.
	 *
	 * @param array{title?:string, description?:string, focus_keyword?:string, content?:string} $overrides
	 * @return array{score:int, checks: array<int, array{id:string,label:string,pass:bool,message:string}>}
	 */
	public static function analyze( WP_Post $post, array $overrides = array() ): array {
		return self::evaluate( $post, $overrides );
	}

	/**
	 * @param array{title?:string, description?:string, focus_keyword?:string, content?:string} $overrides
	 * @return array{score:int, checks: array<int, array{id:string,label:string,pass:bool,message:string}>}
	 */
	private static function evaluate( WP_Post $post, array $overrides ): array {
		$id      = (int) $post->ID;
		$title   = ( array_key_exists( 'title', $overrides ) ? (string) $overrides['title'] : PostSeo::title( $id ) ) ?: $post->post_title;
		$desc    = array_key_exists( 'description', $overrides ) ? (string) $overrides['description'] : PostSeo::description( $id );
		$keyword = array_key_exists( 'focus_keyword', $overrides ) ? (string) $overrides['focus_keyword'] : PostSeo::focus_keyword( $id );
		$content = array_key_exists( 'content', $overrides ) ? (string) $overrides['content'] : (string) $post->post_content;
		$text    = wp_strip_all_tags( $content );
		$words   = str_word_count( $text );
		$min     = (int) apply_filters( 'seoistic/score_min_words', self::DEFAULT_MIN_WORDS, $post );

		$checks   = array();
		$checks[] = self::check( 'title', __( 'SEO title', 'seoistic' ), '' !== trim( $title ), self::length_message( $title, 10, 60 ) );
		$checks[] = self::check( 'description', __( 'Meta description', 'seoistic' ), '' !== trim( $desc ), self::length_message( $desc, 50, 160 ) );
		$checks[] = self::check( 'keyword', __( 'Focus keyword set', 'seoistic' ), '' !== trim( $keyword ), '' !== trim( $keyword ) ? $keyword : __( 'No focus keyword set.', 'seoistic' ) );

		$kw_in_title = '' !== $keyword && false !== stripos( $title, $keyword );
		$checks[]    = self::check( 'keyword_in_title', __( 'Focus keyword in title', 'seoistic' ), '' === $keyword || $kw_in_title, $kw_in_title ? __( 'Found in title.', 'seoistic' ) : __( 'Keyword is missing from the SEO title.', 'seoistic' ) );

		$has_h1   = (bool) preg_match( '/<h1[\s>]/i', $content );
		$checks[] = self::check( 'h1', __( 'Heading structure (H1)', 'seoistic' ), true, $has_h1 ? __( 'Content H1 detected.', 'seoistic' ) : __( 'The page title is rendered as H1 by the theme.', 'seoistic' ) );

		$internal_links = self::count_internal_links( $content );
		$checks[]       = self::check( 'internal_links', __( 'Internal links', 'seoistic' ), $internal_links > 0 || $words < 100, sprintf(
			/* translators: %d: number of internal links found. */
			_n( '%d internal link found.', '%d internal links found.', $internal_links, 'seoistic' ),
			$internal_links
		) );

		list( $img_total, $img_missing ) = self::image_alt_stats( $content );
		$checks[] = self::check( 'image_alt', __( 'Image alt text', 'seoistic' ), 0 === $img_missing, 0 === $img_total ? __( 'No images in content.', 'seoistic' ) : sprintf(
			/* translators: 1: images missing alt text, 2: total images. */
			__( '%1$d of %2$d images missing alt text.', 'seoistic' ),
			$img_missing,
			$img_total
		) );

		$checks[] = self::check( 'word_count', __( 'Content length', 'seoistic' ), $words >= $min, sprintf(
			/* translators: 1: word count, 2: minimum recommended words. */
			__( '%1$d words (recommended min %2$d).', 'seoistic' ),
			$words,
			$min
		) );

		$checks[] = self::check( 'schema', __( 'Structured data', 'seoistic' ), true, __( 'Organization, WebSite and page schema are emitted automatically.', 'seoistic' ) );

		$og_image = self::resolve_og_image( $id );
		$checks[] = self::check( 'og_image', __( 'Open Graph image', 'seoistic' ), '' !== $og_image, '' !== $og_image ? __( 'Share image resolved.', 'seoistic' ) : __( 'No share image or featured image set.', 'seoistic' ) );

		$passed = count(
			array_filter(
				$checks,
				static fn( array $c ): bool => $c['pass']
			)
		);
		$score = (int) round( ( $passed / count( $checks ) ) * 100 );

		return array( 'score' => $score, 'checks' => $checks );
	}

	/**
	 * Recalculate and persist. Called from save_post (after meta save) and from the
	 * manual "Run Audit" action — never on a plain admin page load.
	 */
	public static function recalculate( int $post_id ): int {
		$post = get_post( $post_id );
		if ( ! $post instanceof WP_Post ) {
			return 0;
		}
		$result = self::score( $post );
		PostSeo::save_score( $post_id, $result['score'], (string) wp_json_encode( $result['checks'] ) );
		return $result['score'];
	}

	/**
	 * @return array{id:string,label:string,pass:bool,message:string}
	 */
	private static function check( string $id, string $label, bool $pass, string $message ): array {
		return array( 'id' => $id, 'label' => $label, 'pass' => $pass, 'message' => $message );
	}

	private static function length_message( string $value, int $min, int $max ): string {
		$len = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		if ( 0 === $len ) {
			return __( 'Not set.', 'seoistic' );
		}
		/* translators: 1: character count, 2: min length, 3: max length. */
		return sprintf( __( '%1$d characters (target %2$d–%3$d).', 'seoistic' ), $len, $min, $max );
	}

	private static function count_internal_links( string $content ): int {
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		preg_match_all( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', $content, $links );
		$count = 0;
		foreach ( $links[1] as $href ) {
			$link_host = wp_parse_url( $href, PHP_URL_HOST );
			if ( ! $link_host || $link_host === $host ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * @return array{0:int,1:int} [total images, images missing alt]
	 */
	private static function image_alt_stats( string $content ): array {
		preg_match_all( '/<img\b[^>]*>/i', $content, $images );
		$total   = count( $images[0] );
		$missing = 0;
		foreach ( $images[0] as $img ) {
			if ( ! preg_match( '/\balt=["\'][^"\']*["\']/i', $img ) ) {
				++$missing;
			}
		}
		return array( $total, $missing );
	}

	private static function resolve_og_image( int $post_id ): string {
		$custom = PostSeo::og_image( $post_id );
		if ( '' !== $custom ) {
			return $custom;
		}
		if ( has_post_thumbnail( $post_id ) ) {
			$src = wp_get_attachment_image_src( (int) get_post_thumbnail_id( $post_id ), 'large' );
			if ( $src ) {
				return (string) $src[0];
			}
		}
		return (string) get_option( 'seoistic_default_share_image', '' );
	}
}
