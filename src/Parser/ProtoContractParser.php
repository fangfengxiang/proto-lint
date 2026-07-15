<?php

declare(strict_types=1);

namespace PhpProtoLint\Parser;

use PhpProtoLint\Domain\ProtoMetadata;

/**
 * High-level facade combining ProtoParser (protoc call) and DescriptorReader
 * (descriptor tree navigation) into a single entry point.
 */
final class ProtoContractParser
{
    private ProtoParser $protoParser;
    private DescriptorReader $descriptorReader;

    public function __construct(?ProtoParser $protoParser = null, ?DescriptorReader $descriptorReader = null)
    {
        $this->protoParser = $protoParser ?? new ProtoParser();
        $this->descriptorReader = $descriptorReader ?? new DescriptorReader();
    }

    /**
     * Parse .proto files and return proto-side metadata.
     *
     * @param string[] $protoFiles Array of .proto file paths
     * @param string|null $protoPath Proto import path (--proto_path)
     * @return ProtoMetadata
     */
    public function parse(array $protoFiles, ?string $protoPath = null): ProtoMetadata
    {
        $binary = $this->protoParser->compile($protoFiles, $protoPath);

        return $this->descriptorReader->read($binary);
    }

    /**
     * Ensure protoc is available before parsing.
     */
    public function ensureProtocAvailable(): void
    {
        $this->protoParser->ensureProtocAvailable();
    }
}
