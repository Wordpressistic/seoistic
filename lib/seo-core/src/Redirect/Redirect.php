<?php

declare(strict_types=1);

namespace Wpistic\SeoCore\Redirect;

/**
 * A redirect rule. Pure value object — the WP adapter writes these to .htaccess and a
 * DB-driven runtime fallback; the rules themselves are framework-neutral.
 */
final class Redirect
{
    public function __construct(
        public readonly string $source,
        public readonly string $target,
        public readonly int $code = 301,
        public readonly bool $regex = false
    ) {
    }

    public function isGone(): bool
    {
        return 410 === $this->code;
    }

    public function toHtaccess(): string
    {
        if ($this->isGone()) {
            return 'RewriteRule ^' . ltrim($this->source, '/') . '$ - [G,L]';
        }
        if ($this->regex) {
            return 'RewriteRule ' . $this->source . ' ' . $this->target . ' [R=' . $this->code . ',L]';
        }
        return 'Redirect ' . $this->code . ' ' . $this->source . ' ' . $this->target;
    }
}
