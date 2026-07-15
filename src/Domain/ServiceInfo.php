<?php

declare(strict_types=1);

namespace PhpProtoLint\Domain;

/**
 * Abstract RPC service entity.
 *
 * Contains a unique service identifier and a collection of MethodInfo.
 */
final readonly class ServiceInfo
{
    /**
     * @param string $name
     * @param MethodInfo[] $methods
     */
    public function __construct(
        public string $name,
        public array $methods,
    ) {}

    /**
     * Find a method by its name.
     */
    public function findMethod(string $name): ?MethodInfo
    {
        foreach ($this->methods as $method) {
            if ($method->name === $name) {
                return $method;
            }
        }

        return null;
    }
}
