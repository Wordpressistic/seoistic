<?php

declare(strict_types=1);

namespace Wpistic\SeoCore\Meta;

/**
 * Resolved meta for a page. Pure value object — the WP adapter fills it from post
 * data + settings; a Laravel adapter would fill it from Eloquent. The renderer is
 * the same either way.
 */
final class MetaTags
{
    /**
     * @param array<string, string> $openGraph
     * @param array<string, string> $twitter
     */
    public function __construct(
        public readonly string $title,
        public readonly string $description = '',
        public readonly string $canonical = '',
        public readonly string $robots = 'index, follow',
        public readonly array $openGraph = [],
        public readonly array $twitter = []
    ) {
    }

    public function isIndexable(): bool
    {
        return ! str_contains(strtolower($this->robots), 'noindex');
    }
}
