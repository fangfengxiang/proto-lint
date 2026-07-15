<?php

declare(strict_types=1);

namespace PhpProtoLint\Linter;

/**
 * Collects lint results and provides aggregated reporting.
 *
 * Task 4.5: 校验结果收集器
 */
final class LintReport
{
    /** @var LintResult[] */
    private array $results = [];

    /** @var string[] Info messages (e.g., scanned directories) */
    private array $infoMessages = [];

    /** @var string[] OK messages (methods that passed all rules) */
    private array $okMessages = [];

    public function addResult(LintResult $result): void
    {
        $this->results[] = $result;
    }

    /**
     * @param LintResult[] $results
     */
    public function addResults(array $results): void
    {
        foreach ($results as $result) {
            $this->results[] = $result;
        }
    }

    public function addInfo(string $message): void
    {
        $this->infoMessages[] = $message;
    }

    public function addOk(string $message): void
    {
        $this->okMessages[] = $message;
    }

    public function hasErrors(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isError()) {
                return true;
            }
        }

        return false;
    }

    public function hasWarnings(): bool
    {
        foreach ($this->results as $result) {
            if ($result->isWarning()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return LintResult[]
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * @return LintResult[]
     */
    public function getErrors(): array
    {
        return array_filter($this->results, fn($r) => $r->isError());
    }

    /**
     * @return LintResult[]
     */
    public function getWarnings(): array
    {
        return array_filter($this->results, fn($r) => $r->isWarning());
    }

    public function getErrorCount(): int
    {
        return count($this->getErrors());
    }

    public function getWarningCount(): int
    {
        return count($this->getWarnings());
    }

    /**
     * Get the exit code (0 = pass, 1 = errors).
     * Task 4.7
     */
    public function getExitCode(): int
    {
        return $this->hasErrors() ? 1 : 0;
    }

    /**
     * Format the report for CLI output.
     * Task 4.6
     */
    public function format(): string
    {
        $lines = [];

        // Info messages
        foreach ($this->infoMessages as $info) {
            $lines[] = '[INFO] ' . $info;
        }

        // OK messages
        foreach ($this->okMessages as $ok) {
            $lines[] = '[OK] ' . $ok;
        }

        // Results (errors first, then warnings)
        foreach ($this->getErrors() as $result) {
            $lines[] = $result->format();
        }
        foreach ($this->getWarnings() as $result) {
            $lines[] = $result->format();
        }

        // Summary
        $errorCount = $this->getErrorCount();
        $warningCount = $this->getWarningCount();

        if ($errorCount > 0) {
            $lines[] = sprintf('[FATAL] Detected %d error(s) and %d warning(s)', $errorCount, $warningCount);
        } elseif ($warningCount > 0) {
            $lines[] = sprintf('[INFO] %d warning(s) detected, no errors', $warningCount);
        }

        return implode("\n", $lines);
    }
}
