<?php

declare(strict_types=1);

namespace Wpistic\SeoCore\Audit;

final class Finding
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly Severity $severity,
        public readonly string $message = ''
    ) {
    }
}
