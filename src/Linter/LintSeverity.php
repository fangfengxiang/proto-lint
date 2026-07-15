<?php

declare(strict_types=1);

namespace PhpProtoLint\Linter;

/**
 * Severity levels for lint results.
 */
enum LintSeverity: string
{
    case ERROR = 'ERROR';
    case WARNING = 'WARNING';
}
