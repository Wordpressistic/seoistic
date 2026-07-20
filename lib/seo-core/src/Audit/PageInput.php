<?php

declare(strict_types=1);

namespace Wpistic\SeoCore\Audit;

/**
 * The page facts the auditor needs. The WP adapter builds this from the post; the
 * audit rules never touch WordPress.
 */
final class PageInput
{
    public function __construct(
        public readonly string $title = '',
        public readonly string $metaDescription = '',
        public readonly string $content = '',
        public readonly string $focusKeyword = '',
        public readonly int $h1Count = 0,
        public readonly int $internalLinks = 0,
        public readonly int $imagesTotal = 0,
        public readonly int $imagesMissingAlt = 0,
        public readonly bool $hasSchema = false,
        public readonly bool $hasOpenGraph = false,
        public readonly int $minWords = 300
    ) {
    }

    public function wordCount(): int
    {
        return str_word_count(self::normalize($this->content));
    }

    public function keywordInFirst100(): bool
    {
        if ('' === $this->focusKeyword) {
            return false;
        }
        $words = preg_split('/\s+/', self::normalize($this->content)) ?: [];
        $first = implode(' ', array_slice($words, 0, 100));
        return false !== stripos($first, $this->focusKeyword);
    }

    /**
     * Strip markup and collapse whitespace for text analysis — no WordPress dependency.
     */
    private static function normalize(string $html): string
    {
        return trim((string) preg_replace('/\s+/', ' ', strip_tags($html)));
    }
}
