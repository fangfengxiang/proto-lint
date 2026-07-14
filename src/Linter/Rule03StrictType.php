<?php

declare(strict_types=1);

namespace ProtoLint\Linter;

use ProtoLint\Config\BulkConfig;
use ProtoLint\Domain\MessageInfo;
use ProtoLint\Domain\MethodInfo;

/**
 * Rule-03: Strict type determinism constraint (configurable).
 *
 * Validates that protocol-participating code (function params, return types,
 * DTO properties) does not use mixed, union types (int|string), or bare
 * generic array (without inner type specification). Default: FAIL.
 * Configurable to Warning per service/method via rule_overrides.
 *
 * Task 4.4
 */
final class Rule03StrictType
{
    private const RULE_NAME = 'Rule-03';

    /** @var array<string, array<string, array<string, string>>> rule_overrides: "ServiceName" => ["MethodName" => ["rule_03" => "warning"|"error"]] */
    private array $ruleOverrides;

    /**
     * @param array<string, array<string, array<string, string>>> $ruleOverrides e.g. ["UserService" => ["updateUser" => ["rule_03" => "warning"]]]
     */
    public function __construct(array $ruleOverrides = [])
    {
        $this->ruleOverrides = $ruleOverrides;
    }

    /**
     * @param MethodInfo $phpMethod PHP method with positional params
     * @param string $serviceName Service name for rule_overrides lookup
     * @param string $contextPath e.g. "UserService::updateUser"
     * @return LintResult[]
     */
    public function validate(MethodInfo $phpMethod, string $serviceName, string $contextPath): array
    {
        $severity = $this->getSeverity($serviceName, $phpMethod->name);
        $results = [];

        // Check each parameter type
        foreach ($phpMethod->positionalParams as $param) {
            $results = array_merge($results, $this->checkType(
                $param->phpType,
                $param->name,
                'parameter',
                $contextPath,
                $severity,
            ));
        }

        // Check return type
        if ($phpMethod->returnDataType !== null) {
            $results = array_merge($results, $this->checkType(
                $phpMethod->returnDataType,
                '(return)',
                'return type',
                $contextPath,
                $severity,
            ));
        }

        return $results;
    }

    /**
     * Validate DTO properties in a MessageInfo.
     *
     * @return LintResult[]
     */
    public function validateMessage(MessageInfo $message, string $serviceName, string $contextPath): array
    {
        $severity = $this->getSeverity($serviceName, '');
        $results = [];

        foreach ($message->fields as $field) {
            $results = array_merge($results, $this->checkType(
                $field->phpType,
                $field->name,
                'property',
                $contextPath . ' → ' . $message->name,
                $severity,
            ));
        }

        return $results;
    }

    /**
     * Check a single type hint for violations.
     *
     * @param string|null $typeStr PHP type hint string
     * @param string $name Parameter/property name
     * @param string $kind "parameter" | "return type" | "property"
     * @param string $contextPath Full context path
     * @param LintSeverity $severity ERROR or WARNING
     * @return LintResult[]
     */
    private function checkType(
        ?string $typeStr,
        string $name,
        string $kind,
        string $contextPath,
        LintSeverity $severity,
    ): array {
        if ($typeStr === null) {
            // No type hint — flag as missing strict type
            return [new LintResult(
                $severity,
                self::RULE_NAME,
                sprintf("%s '\$%s' has no type hint in protocol context", ucfirst($kind), $name),
                $contextPath,
            )];
        }

        $results = [];

        // Strip nullable prefix for checking
        $checkType = ltrim($typeStr, '?');

        // Check for mixed type
        if (strtolower($checkType) === 'mixed') {
            $results[] = new LintResult(
                $severity,
                self::RULE_NAME,
                sprintf("%s type 'mixed' is not allowed in protocol context (\$%s)", ucfirst($kind), $name),
                $contextPath,
            );
        }

        // Check for union types (contains |)
        if (str_contains($checkType, '|')) {
            $results[] = new LintResult(
                $severity,
                self::RULE_NAME,
                sprintf("Union type '%s' is not allowed in protocol context (\$%s)", $checkType, $name),
                $contextPath,
            );
        }

        // Check for bare array (without generic type specification)
        if (strtolower($checkType) === 'array') {
            $results[] = new LintResult(
                $severity,
                self::RULE_NAME,
                sprintf("Empty generic 'array' is not allowed, specify inner type (\$%s)", $name),
                $contextPath,
            );
        }

        return $results;
    }

    /**
     * Determine the severity for this service/method.
     * Default is ERROR. Can be downgraded to WARNING via rule_overrides.
     */
    private function getSeverity(string $serviceName, string $methodName): LintSeverity
    {
        // Check method-level override
        if ($methodName !== '' && isset($this->ruleOverrides[$serviceName][$methodName]['rule_03'])) {
            $override = $this->ruleOverrides[$serviceName][$methodName]['rule_03'];
            if (strtolower($override) === 'warning') {
                return LintSeverity::WARNING;
            }
        }

        // Check service-level override
        if (isset($this->ruleOverrides[$serviceName][BulkConfig::SERVICE_LEVEL_KEY]['rule_03'])) {
            $override = $this->ruleOverrides[$serviceName]['*']['rule_03'];
            if (strtolower($override) === 'warning') {
                return LintSeverity::WARNING;
            }
        }

        // Default: ERROR
        return LintSeverity::ERROR;
    }
}
