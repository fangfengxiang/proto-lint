<?php

declare(strict_types=1);

namespace PhpProtoLint\Config;

/**
 * Represents a service's configuration with its methods.
 */
final readonly class ServiceConfig
{
    /**
     * @param string $name Service name
     * @param array<string, MethodConfig> $methods
     */
    public function __construct(
        public string $name,
        public array $methods,
    ) {}
}
