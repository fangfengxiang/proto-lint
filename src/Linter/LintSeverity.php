<?php

declare(strict_types=1);

namespace ProtoLint\Linter;

/**
 * Severity levels for lint results.
 */
enum LintSeverity: string
{
    case ERROR = 'ERROR';
    case WARNING = 'WARNING';
}
