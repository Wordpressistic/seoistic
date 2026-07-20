<?php

declare(strict_types=1);

namespace Wpistic\SeoCore\Audit;

/**
 * Brand banned-word scanner. Whole-word, case-insensitive. Used by the audit gate to
 * enforce the brand rules in code (a SEOISTIC differentiator — no competitor does this).
 */
final class BannedWords
{
    /**
     * @param list<string> $words
     */
    public function __construct(private readonly array $words)
    {
    }

    /**
     * @return list<string> The banned words found.
     */
    public function scan(string $text): array
    {
        $found = [];
        foreach ($this->words as $word) {
            $word = trim($word);
            if ('' === $word) {
                continue;
            }
            if (preg_match('/\b' . preg_quote($word, '/') . '\b/i', $text)) {
                $found[] = $word;
            }
        }
        return array_values(array_unique($found));
    }
}
