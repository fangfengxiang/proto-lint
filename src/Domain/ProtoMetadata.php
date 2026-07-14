<?php

declare(strict_types=1);

namespace ProtoLint\Domain;

/**
 * Container for proto-side metadata extracted from .proto files.
 *
 * Contains service definitions and a lookup map of message definitions by name.
 */
final readonly class ProtoMetadata
{
    /**
     * @param ServiceInfo[] $services
     * @param array<string, MessageInfo> $messagesByName
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

        return null;
    }

    public function findMessage(string $name): ?MessageInfo
    {
        // Try exact match first
        if (isset($this->messagesByName[$name])) {
            return $this->messagesByName[$name];
        }
        // Try with leading dot stripped (proto descriptors use ".PackageName.MessageName")
        $stripped = ltrim($name, '.');
        if (isset($this->messagesByName[$stripped])) {
            return $this->messagesByName[$stripped];
        }
        // Try matching by short name (last segment after ".")
        $parts = explode('.', $stripped);
        $shortName = end($parts);
        foreach ($this->messagesByName as $msgName => $msg) {
            if (str_ends_with($msgName, '.' . $shortName) || $msgName === $shortName) {
                return $msg;
            }
        }

        return null;
    }
}
