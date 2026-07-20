<?php

declare(strict_types=1);

namespace Wpistic\SeoCore\Audit;

enum Severity: string
{
    case Pass = 'pass';
    case Warning = 'warning';
    case Fail = 'fail';
}
