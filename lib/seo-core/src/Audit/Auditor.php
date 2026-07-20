<?php

declare(strict_types=1);

namespace Wpistic\SeoCore\Audit;

/**
 * The pre-publish SEO audit gate. Runs the 13-element checks (title, meta, H1,
 * keyword, links, schema, OG, images, word count) + the banned-word scan. Pure logic,
 * unit-tested, reused under WP and Laravel.
 */
final class Auditor
{
    /**
     * @param list<string> $bannedWords
     */
    public function run(PageInput $page, array $bannedWords = []): AuditReport
    {
        $findings = [];

        $titleLength = self::length($page->title);
        $findings[] = new Finding(
            'title_length',
            'Title length',
            ($titleLength > 0 && $titleLength <= 60) ? Severity::Pass : Severity::Fail,
            $titleLength . ' chars (target ≤ 60)'
        );

        $metaLength = self::length($page->metaDescription);
        $findings[] = new Finding(
            'meta_length',
            'Meta description length',
            ($metaLength >= 140 && $metaLength <= 160) ? Severity::Pass : Severity::Warning,
            $metaLength . ' chars (target 140–160)'
        );

        $findings[] = new Finding(
            'one_h1',
            'Single H1',
            1 === $page->h1Count ? Severity::Pass : Severity::Fail,
            $page->h1Count . ' found'
        );

        $findings[] = new Finding(
            'keyword_first_100',
            'Focus keyword in first 100 words',
            $page->keywordInFirst100() ? Severity::Pass : Severity::Warning
        );

        $findings[] = new Finding(
            'internal_links',
            'Internal links (3–5)',
            $page->internalLinks >= 3 ? Severity::Pass : Severity::Warning,
            $page->internalLinks . ' found'
        );

        $findings[] = new Finding(
            'schema',
            'Structured data present',
            $page->hasSchema ? Severity::Pass : Severity::Warning
        );

        $findings[] = new Finding(
            'open_graph',
            'Open Graph tags',
            $page->hasOpenGraph ? Severity::Pass : Severity::Warning
        );

        $findings[] = new Finding(
            'images_alt',
            'Image alt text',
            0 === $page->imagesMissingAlt ? Severity::Pass : Severity::Fail,
            $page->imagesMissingAlt . ' of ' . $page->imagesTotal . ' missing alt'
        );

        $words = $page->wordCount();
        $findings[] = new Finding(
            'word_count',
            'Word count',
            $words >= $page->minWords ? Severity::Pass : Severity::Warning,
            $words . ' words (min ' . $page->minWords . ')'
        );

        $banned = (new BannedWords($bannedWords))->scan($page->content . ' ' . $page->title);
        $findings[] = new Finding(
            'banned_words',
            'Banned words',
            [] === $banned ? Severity::Pass : Severity::Fail,
            [] === $banned ? '' : 'Found: ' . implode(', ', $banned)
        );

        return new AuditReport($findings);
    }

    /**
     * Character count, multibyte-aware when mbstring is available.
     */
    private static function length(string $text): int
    {
        return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
    }
}
