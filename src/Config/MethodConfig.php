<?php

declare(strict_types=1);

namespace PhpProtoLint\Config;

/**
 * Represents a single method's configuration within a service.
 */
final readonly class MethodConfig
{
    /**
     * @param string $name Method name
     * @param string|null $mappingFile Path to proto-mapping.json (null = no mapping, check only)
     * @param string[]|null $targetProtoFiles Override proto files (null = use default_target_proto)
     */
    public function __construct(
        public string $name,
        public ?string $mappingFile,
        public ?array $targetProtoFiles,
    ) {}
}
