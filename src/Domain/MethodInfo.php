<?php

declare(strict_types=1);

namespace PhpProtoLint\Domain;

/**
 * Abstract RPC method entity.
 *
 * Binds a method name to its PositionalParams (ordered input parameter queue)
 * and ReturnDataType (return data type, typically a message name).
 */
final readonly class MethodInfo
{
    /**
     * @param string $name
     * @param FieldInfo[] $positionalParams Ordered input parameters (1-based position)
     * @param string|null $returnDataType Return message type name (e.g. "UpdateUserResponse")
     * @param string|null $inputDataType Input message type name (proto Request message, e.g. "UpdateUserRequest")
     */
    public function __construct(
        public string $name,
        public array $positionalParams,
        public ?string $returnDataType = null,
        public ?string $inputDataType = null,
    ) {}

    /**
     * Find a parameter by its physical position (1-based).
     */
    public function findParamByPosition(int $position): ?FieldInfo
    {
        foreach ($this->positionalParams as $param) {
            if ($param->position === $position) {
                return $param;
            }
        }

        return null;
    }

    /**
     * Find a parameter by its name.
     */
    public function findParamByName(string $name): ?FieldInfo
    {
        foreach ($this->positionalParams as $param) {
            if ($param->name === $name) {
                return $param;
            }
        }

        return null;
    }
}
