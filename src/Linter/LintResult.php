<?php

declare(strict_types=1);

namespace ProtoLint\Linter;

/**
 * A single lint result (error or warning) from a rule validation.
 */
final readonly class LintResult
{
    public function __construct(
        public LintSeverity $severity,
        public string $rule,
        public string $message,
        public string $path,
    ) {}

    public function isError(): bool
    {
        return $this->severity === LintSeverity::ERROR;
    }

    public function isWarning(): bool
    {
        return $this->severity === LintSeverity::WARNING;
    }

    public function format(): string
    {
        $tag = $this->severity === LintSeverity::ERROR ? '[ERROR]' : '[WARNING]';

        return sprintf('%s %s - %s: %s', $tag, $this->path, $this->rule, $this->message);
    }
}
