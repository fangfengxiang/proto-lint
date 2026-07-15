<?php

declare(strict_types=1);

namespace PhpProtoLint\Config;

/**
 * Represents a request or response payload configuration.
 */
final readonly class PayloadConfig
{
    /**
     * @param array $payload JSON payload data (shadow traffic)
     * @param array<string, string> $fieldClassMappings JSON key path → PHP FQCN
     *   Empty string FQCN = auto-prefix isolation (task 6.7)
     */
    public function __construct(
        public array $payload,
        public array $fieldClassMappings,
    ) {}
}
