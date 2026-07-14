<?php

declare(strict_types=1);

namespace ProtoLint\Domain;

/**
 * Abstract message (struct) entity.
 *
 * Contains a message name and a set of FieldInfo with explicit tag numbers.
 */
final readonly class MessageInfo
{
    /**
     * @param string $name
     * @param FieldInfo[] $fields
     */
    public function __construct(
        public string $name,
        public array $fields,
    ) {}

    /**
     * Find a field by its tag number.
     */
    public function findByTagNumber(int $tagNumber): ?FieldInfo
    {
        foreach ($this->fields as $field) {
            if ($field->tagNumber === $tagNumber) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Find a field by its name.
     */
    public function findByName(string $name): ?FieldInfo
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }

        return null;
    }
}
