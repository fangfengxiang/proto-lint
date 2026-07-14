<?php

declare(strict_types=1);

namespace ProtoLint\Linter;

use ProtoLint\Domain\PhpMetadata;
use ProtoLint\Domain\ProtoMetadata;

/**
 * Shadow traffic audit engine — orchestrates the three-way intersection
 * audit: JSON payload × PHP DTO annotations × .proto contract.
 *
 * Task 5.6: shadow-lint 命令的 CLI 输出格式与退出码
 */
final class ShadowLintEngine
{
    private PayloadParser $payloadParser;
    private DiffEngine $diffEngine;

    public function __construct()
    {
        $this->payloadParser = new PayloadParser();
        $this->diffEngine = new DiffEngine();
    }

    /**
     * Run the full shadow-lint audit pipeline.
     *
     * @param array $jsonPayload JSON-decoded request payload
     * @param array<string, string> $fieldClassMappings JSON key → PHP FQCN (for diff) and proto type name (for boundary)
     * @param PhpMetadata $phpMetadata PHP-side metadata with DTO classes
     * @param ProtoMetadata $protoMetadata Proto-side metadata
     * @return LintReport
     */
    public function audit(
        array $jsonPayload,
        array $fieldClassMappings,
        PhpMetadata $phpMetadata,
        ProtoMetadata $protoMetadata,
    ): LintReport {
        $report = new LintReport();
        $report->addInfo('Shadow-lint: auditing traffic payload against contract');

        // Phase 1: Boundary validation (JSON payload vs .proto contract)
        $boundaryResults = $this->payloadParser->validateBoundary(
            $jsonPayload,
            $fieldClassMappings,
            $protoMetadata,
        );
        $report->addResults($boundaryResults);

        // Phase 2: Field deficit detection (JSON paths vs PHP DTO ownership)
        $diffResults = $this->diffEngine->computeDiff(
            $jsonPayload,
            $fieldClassMappings,
            $phpMetadata,
        );
        $report->addResults($diffResults);

        // Summary
        if (!$report->hasErrors() && !$report->hasWarnings()) {
            $report->addOk('Shadow-lint: audit passed, all traffic fields have PHP ownership');
        }

        return $report;
    }

    /**
     * Parse a JSON string payload.
     */
    public function parsePayload(string $jsonStr): array
    {
        return $this->payloadParser->parseJson($jsonStr);
    }
}
