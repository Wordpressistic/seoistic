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
        return (string) json_encode($this->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | $flags);
    }
}
