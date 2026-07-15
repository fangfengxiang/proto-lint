<?php

declare(strict_types=1);

namespace PhpProtoLint\Domain;

/**
 * Abstract property entity.
 *
 * Contains name, position (physical order), tagNumber (proto field number),
 * dataType (scalar/message/map/list), phpClassMapping (target PHP FQCN),
 * phpType (PHP type hint for Rule-03 strict typing checks),
 * and protoTypeName (proto message type name for Rule-02 recursive descent).
 */
final readonly class FieldInfo
{
    public function __construct(
        public string $name,
        public int $position,
        public int $tagNumber,
        public DataType $dataType,
        public ?string $phpClassMapping = null,
        public ?string $phpType = null,
        public ?string $protoTypeName = null,
    ) {}

    /**
     * Check if this field is a composite type requiring recursive descent.
     */
    public function isComposite(): bool
    {
        return $this->dataType === DataType::MESSAGE
            || $this->dataType === DataType::LIST
            || $this->dataType === DataType::MAP;
    }

    /**
     * Check if position and tagNumber are aligned (1-based, sequential).
     */
    public function isPositionAligned(): bool
    {
        return $this->position === $this->tagNumber;
    }
}
