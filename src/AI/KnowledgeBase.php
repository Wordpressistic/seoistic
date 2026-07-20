<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI;

/**
 * Loads the plugin's knowledge/*.md guidance files and combines them with site,
 * business and page context into the system-prompt text PromptBuilder uses. Content
 * is trimmed safely so a large post never blows past a sane prompt size.
 */
final class KnowledgeBase {

	private const FILES = array(
		'seo-title'        => 'seo-title.md',
		'meta-description' => 'meta-description.md',
		'schema'           => 'schema.md',
		'llms-txt'         => 'llms-txt.md',
		'robots-txt'       => 'robots-txt.md',
		'htaccess'         => 'htaccess.md',
		'aeo'              => 'aeo.md',
		'local-seo'        => 'local-seo.md',
		'woocommerce-seo'  => 'woocommerce-seo.md',
		'internal-linking' => 'internal-linking.md',
		'image-seo'        => 'image-seo.md',
	);

	private const MAX_CONTENT_CHARS = 3000;

	public static function guidance( string $topic ): string {
		if ( ! isset( self::FILES[ $topic ] ) ) {
			return '';
		}
		$path = SEOISTIC_DIR . 'knowledge/' . self::FILES[ $topic ];
		if ( ! is_file( $path ) ) {
			return '';
		}
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		return false === $contents ? '' : trim( $contents );
	}

	/**
	 * Assembles the full system-prompt context for a generator: the topic's
	 * knowledge-base guidance + site identity + business settings + (optionally) the
	 * target page's data. Never sends more than a trimmed content summary.
	 *
	 * @param array<string, mixed> $page Optional keys: title, content, focus_keyword, page_type, url.
	 */
	public static function get_context( string $topic, array $page = array() ): string {
		$ai = AiSettings::all();

		$parts   = array();
		$parts[] = self::guidance( $topic );

		if ( AiSettings::custom_rag_enabled() ) {
			$custom_rag = self::custom_rag_guidance();
			if ( '' !== $custom_rag ) {
				$parts[] = "## Custom Knowledge\n" . $custom_rag;
			}
		}

		$parts[] = "## Site\n"
			. '- Name: ' . get_bloginfo( 'name' ) . "\n"
			. '- URL: ' . home_url( '/' ) . "\n"
			. '- Tagline: ' . get_bloginfo( 'description' );

		$parts[] = "## Business\n"
			. '- Business name: ' . ( $ai['business_name'] ?: get_bloginfo( 'name' ) ) . "\n"
			. '- Brand voice: ' . ( $ai['brand_voice'] ?: 'clear, direct, helpful' ) . "\n"
			. '- Target country: ' . ( $ai['target_country'] ?: 'not specified' ) . "\n"
			. '- Target audience: ' . ( $ai['target_audience'] ?: 'not specified' ) . "\n"
			. '- Language: ' . ( $ai['default_language'] ?: 'en' );

		if ( array() !== $page ) {
			$content_summary = isset( $page['content'] ) ? self::trim_content( (string) $page['content'] ) : '';
			$parts[] = "## Page\n"
				. '- Title: ' . ( $page['title'] ?? '' ) . "\n"
				. '- URL: ' . ( $page['url'] ?? '' ) . "\n"
				. '- Page type: ' . ( $page['page_type'] ?? 'page' ) . "\n"
				. '- Focus keyword: ' . ( $page['focus_keyword'] ?? 'none set' ) . "\n"
				. '- Content summary: ' . ( '' !== $content_summary ? $content_summary : 'not available' );
		}

		return implode( "\n\n", array_filter( $parts ) );
	}

	/**
	 * Loads custom RAG knowledge from the configured path or WordPress media.
	 * Supports both local file paths and uploaded files via settings.
	 */
	private static function custom_rag_guidance(): string {
		$path = AiSettings::custom_rag_path();
		if ( '' === $path ) {
			return '';
		}

		$rag_content = '';

		if ( str_starts_with( $path, '/' ) || str_starts_with( $path, 'http' ) ) {
			$rag_content = @file_get_contents( $path );
		} else {
			$upload_dir = wp_get_upload_dir();
			$full_path  = $upload_dir['basedir'] . '/' . $path;
			if ( is_file( $full_path ) ) {
				$rag_content = @file_get_contents( $full_path );
			}
		}

		if ( false === $rag_content || '' === trim( $rag_content ) ) {
			return '';
		}

		return trim( $rag_content );
	}

	/**
	 * Strips markup and hard-caps content length so we never send an entire post
	 * body to the model — just enough for it to ground the generation in real content.
	 */
	public static function trim_content( string $content ): string {
		$text = trim( (string) preg_replace( '/\s+/', ' ', wp_strip_all_tags( $content ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > self::MAX_CONTENT_CHARS ) {
			return mb_substr( $text, 0, self::MAX_CONTENT_CHARS ) . '…';
		}
		if ( strlen( $text ) > self::MAX_CONTENT_CHARS ) {
			return substr( $text, 0, self::MAX_CONTENT_CHARS ) . '…';
		}
		return $text;
	}
}
