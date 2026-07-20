<?php

declare(strict_types=1);

namespace Wpistic\Seoistic\AI;

/**
 * Builds the OpenRouter chat messages for each generator: knowledge-base guidance
 * + site/business/page context as the system message, a strict "return JSON only"
 * instruction, and the specific ask as the user message.
 */
final class PromptBuilder {

	/**
	 * @param array<string, mixed> $page
	 * @return array<int, array{role:string, content:string}>
	 */
	public static function build( string $type, array $page = array() ): array {
		$kb_topic = self::kb_topic( $type );
		$context  = KnowledgeBase::get_context( $kb_topic, $page );
		$format   = self::format_instructions( $type );

		$system = trim( $context . "\n\n## Output format\n" . $format );

		return array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user', 'content' => self::ask( $type, $page ) ),
		);
	}

	private static function kb_topic( string $type ): string {
		return match ( $type ) {
			'title', 'description', 'keywords', 'full_page_optimization', 'optimize_content' => 'seo-title',
			'schema' => 'schema',
			'alt' => 'image-seo',
			'internal_links' => 'internal-linking',
			'robots' => 'robots-txt',
			'htaccess' => 'htaccess',
			'llms' => 'llms-txt',
			'aeo' => 'aeo',
			'local_schema' => 'local-seo',
			'woocommerce_schema' => 'woocommerce-seo',
			default => 'seo-title',
		};
	}

	private static function format_instructions( string $type ): string {
		return match ( $type ) {
			'title' => 'Return ONLY valid JSON: {"title": "...", "reason": "short explanation"}. Title must be 50-60 characters, include the focus keyword near the start, and never use quotation marks inside the title itself.',
			'description' => 'Return ONLY valid JSON: {"meta_description": "...", "reason": "short explanation"}. 140-160 characters, includes the focus keyword, ends with a clear reason to click.',
			'keywords' => 'Return ONLY valid JSON: {"focus_keywords": ["keyword one", "keyword two", "keyword three"], "reason": "short explanation"}. 3-5 keyword phrases ordered by relevance.',
			'schema' => 'Return ONLY valid JSON: {"schema_type": "FAQPage", "faq": [{"q":"...", "a":"..."}], "reason": "short explanation"}. Only include "faq" when schema_type is FAQPage; otherwise omit it.',
			'alt' => 'Return ONLY valid JSON: {"alt_text": "...", "reason": "short explanation"}. Alt text must be under 125 characters, describe the image literally, and include the focus keyword only if it fits naturally.',
			'internal_links' => 'Return ONLY valid JSON: {"suggestions": [{"anchor_text":"...", "reason":"..."}], "reason": "short explanation"}. 3-6 suggestions describing what internal link anchor text and topic to add — do not invent URLs, only describe the topic to link to.',
			'optimize_content' => 'Return ONLY valid JSON: {"suggestions": ["...", "..."], "reason": "short explanation"}. 3-6 concrete, specific content improvement suggestions (not generic advice).',
			'full_page_optimization' => 'Return ONLY valid JSON: {"title": "...", "meta_description": "...", "focus_keywords": ["..."], "reason": "short explanation"}. Title 50-60 chars, meta description 140-160 chars.',
			'robots' => 'Return ONLY valid JSON: {"robots_txt": "...", "reason": "short explanation"}. robots_txt is the full file content as plain text (use \\n for newlines), must include a Sitemap line, and must never disallow /wp-content/uploads/ or theme/plugin asset paths.',
			'htaccess' => 'Return ONLY valid JSON: {"htaccess": "...", "reason": "short explanation", "warnings": ["..."]}. htaccess is the ADDITIONAL rules only (not the WordPress core block), as plain text with \\n newlines. List any risky rule in "warnings".',
			'llms' => 'Return ONLY valid JSON: {"llms_txt": "...", "reason": "short explanation"}. llms_txt is the full file content in llms.txt markdown format (H1 site name, blockquote summary, H2 sections).',
			'aeo' => 'Return ONLY valid JSON: {"suggestions": ["...", "..."], "reason": "short explanation"}. Suggestions to improve visibility in AI answer engines (ChatGPT, Perplexity, Google AI Overviews).',
			default => 'Return ONLY valid JSON with the requested fields — no prose outside the JSON object.',
		};
	}

	private static function ask( string $type, array $page ): string {
		$title = (string) ( $page['title'] ?? '' );
		return match ( $type ) {
			'title' => "Write an SEO title for this page. Current title: \"{$title}\".",
			'description' => "Write a meta description for this page. Current title: \"{$title}\".",
			'keywords' => 'Suggest focus keywords for this page based on its content.',
			'schema' => 'Suggest the best schema.org type for this page, and FAQ pairs if the content answers common questions.',
			'alt' => "Write alt text for the page's featured/share image based on the page title and content.",
			'internal_links' => 'Suggest internal linking opportunities for this page based on its topic.',
			'optimize_content' => 'Review this page and suggest concrete content improvements for SEO.',
			'full_page_optimization' => 'Generate a complete SEO title, meta description and focus keywords for this page in one pass.',
			'robots' => 'Generate an optimized robots.txt for this WordPress site.',
			'htaccess' => 'Generate SEO/security/performance .htaccess rules for this WordPress site.',
			'llms' => 'Generate an llms.txt file describing this site for AI assistants.',
			'aeo' => 'Suggest how to optimize this page for AI search engines and answer engines.',
			default => 'Generate the requested content for this page.',
		};
	}
}
