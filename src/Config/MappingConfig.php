<?php

declare(strict_types=1);

namespace ProtoLint\Config;

/**
 * Top-level proto-mapping.json configuration.
 */
final readonly class MappingConfig
{
    /**
     * @param string $schema JSON Schema URL
     * @param string[] $targetProtoFiles Proto files for this mapping
     * @param PayloadConfig $request Request payload + mappings
     * @param PayloadConfig $response Response payload + mappings
     */
    public function __construct(
        public string $schema,
        public array $targetProtoFiles,
        public PayloadConfig $request,
        public PayloadConfig $response,
    ) {}
}
