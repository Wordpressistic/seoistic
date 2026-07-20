<?php

declare(strict_types=1);

namespace Wpistic\SeoCore\Audit;

final class AuditReport
{
    /**
     * @param list<Finding> $findings
     */
    public function __construct(public readonly array $findings)
    {
    }

    /**
     * @return list<Finding>
     */
    public function fails(): array
    {
        return array_values(array_filter($this->findings, static fn (Finding $f): bool => Severity::Fail === $f->severity));
    }

    public function passed(): bool
    {
        return [] === $this->fails();
    }

    public function score(): int
    {
        $total = count($this->findings);
        if (0 === $total) {
            return 100;
        }
        $passed = count(array_filter($this->findings, static fn (Finding $f): bool => Severity::Pass === $f->severity));
        return (int) round($passed / $total * 100);
    }
}
