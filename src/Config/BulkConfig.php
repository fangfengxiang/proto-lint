<?php

declare(strict_types=1);

namespace PhpProtoLint\Config;

/**
 * Top-level proto-bulk.json configuration.
 */
final readonly class BulkConfig
{
    /** Special key indicating service-level (not method-level) rule override. */
    public const SERVICE_LEVEL_KEY = '__service__';

    /**
     * @param string $schema JSON Schema URL
     * @param string $sourceDir Source directory for PHP files
     * @param string $defaultTargetProto Default .proto file path
     * @param array<string, ServiceConfig> $services
     * @param array<string, array<string, array<string, string>>> $ruleOverrides
     *   [service => [method => [rule => severity]]]
     *   Method key BulkConfig::SERVICE_LEVEL_KEY indicates service-level override.
     */
    public function __construct(
        public string $schema,
        public string $sourceDir,
        public string $defaultTargetProto,
        public array $services,
        public array $ruleOverrides,
    ) {}

    /**
     * Get rule_overrides formatted for Rule03StrictType.
     *
     * @return array<string, array<string, array<string, string>>>
     */
    public function getRuleOverridesForEngine(): array
    {
        return $this->ruleOverrides;
    }
}
