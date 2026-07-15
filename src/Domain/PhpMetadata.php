<?php

declare(strict_types=1);

namespace PhpProtoLint\Domain;

use PhpProtoLint\Support\FqcnUtil;

/**
 * Container for PHP-side metadata extracted from PHP source code AST.
 *
 * Mirrors ProtoMetadata structure for intermediate-state alignment matrix comparison.
 */
final readonly class PhpMetadata
{
    /**
     * @param ServiceInfo[] $services Service classes with ProtoMethod-annotated methods
     * @param array<string, MessageInfo> $messagesByName DTO classes keyed by FQCN
     */
    public function __construct(
        public array $services,
        public array $messagesByName,
    ) {}

    public function findService(string $name): ?ServiceInfo
    {
        foreach ($this->services as $service) {
            if ($service->name === $name) {
                return $service;
            }
        }
        // Try short name match (last segment of FQCN)
        $shortName = FqcnUtil::shortName($name);
        foreach ($this->services as $service) {
            if (FqcnUtil::shortName($service->name) === $shortName) {
                return $service;
            }
        }

        return null;
    }

    public function findMessage(string $name): ?MessageInfo
    {
        // Try exact match
        if (isset($this->messagesByName[$name])) {
            return $this->messagesByName[$name];
        }
        // Try short name match
        $shortName = FqcnUtil::shortName($name);
        foreach ($this->messagesByName as $msgName => $msg) {
            if (FqcnUtil::shortName($msgName) === $shortName) {
                return $msg;
            }
            if ($msg->name === $shortName || $msg->name === $name) {
                return $msg;
            }
        }

        return null;
    }
}
