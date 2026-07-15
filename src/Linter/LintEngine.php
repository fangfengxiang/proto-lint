<?php

declare(strict_types=1);

namespace PhpProtoLint\Linter;

use PhpProtoLint\Domain\MethodInfo;
use PhpProtoLint\Domain\PhpMetadata;
use PhpProtoLint\Domain\ProtoMetadata;
use PhpProtoLint\Domain\ServiceInfo;

/**
 * Contract consistency check engine.
 *
 * Loads proto-bulk.json config, traverses services → methods, and triggers
 * the three-stage validation pipeline (Rule-01, Rule-02, Rule-03).
 *
 * Tasks 4.1, 4.6, 4.7
 */
final class LintEngine
{
    private Rule01PositionalAlignment $rule01;
    private Rule02CascadeRecursion $rule02;
    private Rule03StrictType $rule03;

    /**
     * @param array<string, array<string, array<string, string>>> $ruleOverrides e.g. ["UserService" => ["updateUser" => ["rule_03" => "warning"]]]
     */
    public function __construct(array $ruleOverrides = [])
    {
        $this->rule01 = new Rule01PositionalAlignment();
        $this->rule02 = new Rule02CascadeRecursion();
        $this->rule03 = new Rule03StrictType($ruleOverrides);
    }

    /**
     * Run the full check pipeline on all proto services and methods.
     *
     * @param ProtoMetadata $protoMetadata Proto-side metadata
     * @param PhpMetadata $phpMetadata PHP-side metadata (with recursive descent)
     * @param string $sourceDir Source directory (for info output)
     * @return LintReport
     */
    public function check(ProtoMetadata $protoMetadata, PhpMetadata $phpMetadata, string $sourceDir = ''): LintReport
    {
        $report = new LintReport();

        if ($sourceDir !== '') {
            $report->addInfo('Scanning directory: ' . $sourceDir);
        }

        foreach ($protoMetadata->services as $protoService) {
            $phpService = $phpMetadata->findService($protoService->name);

            if ($phpService === null) {
                $report->addResult(new LintResult(
                    LintSeverity::ERROR,
                    'Engine',
                    sprintf("PHP service '%s' not found in source code", $protoService->name),
                    $protoService->name,
                ));

                continue;
            }

            foreach ($protoService->methods as $protoMethod) {
                $phpMethod = $phpService->findMethod($protoMethod->name);

                // Try case-insensitive match (proto PascalCase → PHP camelCase)
                if ($phpMethod === null) {
                    $phpMethod = $this->findMethodIgnoreCase($phpService, $protoMethod->name);
                }

                if ($phpMethod === null) {
                    $report->addResult(new LintResult(
                        LintSeverity::ERROR,
                        'Engine',
                        sprintf("PHP method '%s' not found in service '%s'", $protoMethod->name, $protoService->name),
                        $protoService->name . '::' . $protoMethod->name,
                    ));

                    continue;
                }

                $this->checkMethod(
                    $protoService,
                    $protoMethod,
                    $phpMethod,
                    $protoMetadata,
                    $phpMetadata,
                    $report,
                );
            }
        }

        return $report;
    }

    /**
     * Run all three rules on a single method.
     */
    private function checkMethod(
        ServiceInfo $protoService,
        MethodInfo $protoMethod,
        MethodInfo $phpMethod,
        ProtoMetadata $protoMetadata,
        PhpMetadata $phpMetadata,
        LintReport $report,
    ): void {
        $contextPath = $protoService->name . '::' . $protoMethod->name;
        $errorCountBefore = count($report->getErrors());
        $warningCountBefore = count($report->getWarnings());

        // Find the proto Request message for this method
        $protoRequestMessage = null;
        if ($protoMethod->inputDataType !== null) {
            $protoRequestMessage = $protoMetadata->findMessage($protoMethod->inputDataType);
        }

        // Rule-01: Positional parameter alignment
        $results = $this->rule01->validate($phpMethod, $protoRequestMessage, $contextPath);
        $report->addResults($results);

        // Rule-02: Cascade recursion
        $results = $this->rule02->validate($phpMethod, $protoRequestMessage, $phpMetadata, $protoMetadata, $contextPath);
        $report->addResults($results);

        // Rule-03: Strict type checking
        $results = $this->rule03->validate($phpMethod, $protoService->name, $contextPath);
        $report->addResults($results);

        // Check DTO properties for Rule-03
        foreach ($phpMethod->positionalParams as $param) {
            if ($param->phpClassMapping !== null) {
                $phpMessage = $phpMetadata->findMessage($param->phpClassMapping);
                if ($phpMessage !== null) {
                    $results = $this->rule03->validateMessage($phpMessage, $protoService->name, $contextPath);
                    $report->addResults($results);
                }
            }
        }

        // Check if this method passed all rules
        $newErrors = count($report->getErrors()) - $errorCountBefore;
        $newWarnings = count($report->getWarnings()) - $warningCountBefore;

        if ($newErrors === 0 && $newWarnings === 0) {
            $report->addOk($contextPath . ' - all rules passed');
        }
    }

    /**
     * Case-insensitive method lookup (proto PascalCase → PHP camelCase).
     */
    private function findMethodIgnoreCase(ServiceInfo $service, string $name): ?MethodInfo
    {
        $lower = strtolower($name);
        foreach ($service->methods as $method) {
            if (strtolower($method->name) === $lower) {
                return $method;
            }
        }

        return null;
    }
}
