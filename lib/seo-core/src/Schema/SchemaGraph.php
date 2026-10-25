<?php

declare(strict_types=1);

namespace Wpistic\SeoCore\Schema;

/**
 * Builds a JSON-LD @graph. SEOISTIC emits ALL structured data through this — the
 * Tour Manager (and any source) only supplies node arrays via filters; this assembles
 * and serializes the single graph.
 */
final class SchemaGraph
{
    /** @var array<int, array<string, mixed>> */
    private array $nodes = [];

    /**
     * @param array<string, mixed> $node
     */
    public function add(array $node): self
    {
        if ([] !== $node) {
            $this->nodes[] = $node;
        }
        return $this;
    }

    public function isEmpty(): bool
    {
        return [] === $this->nodes;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array(
            '@context' => 'https://schema.org',
            '@graph'   => array_values($this->nodes),
        );
    }

    public function toJson(int $flags = 0): string
    {
        // phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- seo-core is a WordPress-independent library by design; wp_json_encode() cannot be used here.
        return (string) json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | $flags);
    }
}
