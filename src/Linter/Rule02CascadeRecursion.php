<?php

declare(strict_types=1);

namespace PhpProtoLint\Linter;

use PhpProtoLint\Domain\MessageInfo;
use PhpProtoLint\Domain\MethodInfo;
use PhpProtoLint\Domain\PhpMetadata;
use PhpProtoLint\Domain\ProtoMetadata;

/**
 * Rule-02: Deep cascade recursive validation.
 *
 * For composite type params (Message/List/Map), recursively descends into
 * the corresponding DTO/DAO class AST to validate that all properties have
 * #[ProtoField] annotations and that tag numbers match the .proto message
 * field definitions. Error stack traces the full Method → Param → Field path.
 *
 * Task 4.3
 */
final class Rule02CascadeRecursion
{
    private const RULE_NAME = 'Rule-02';

    /**
     * @param MethodInfo $phpMethod PHP method with positional params
     * @param MessageInfo|null $protoRequestMessage Proto Request message
     * @param PhpMetadata $phpMetadata PHP-side metadata (includes recursively descended DTOs)
     * @param ProtoMetadata $protoMetadata Proto-side metadata
     * @param string $contextPath e.g. "UserService::updateUser"
     * @return LintResult[]
     */
    public function validate(
        MethodInfo $phpMethod,
        ?MessageInfo $protoRequestMessage,
        PhpMetadata $phpMetadata,
        ProtoMetadata $protoMetadata,
        string $contextPath,
    ): array {
        $results = [];

        foreach ($phpMethod->positionalParams as $param) {
            // Only check composite types
            if (!$param->isComposite() || $param->phpClassMapping === null) {
                continue;
            }

            // Find the PHP DTO MessageInfo
            $phpMessage = $phpMetadata->findMessage($param->phpClassMapping);
            if ($phpMessage === null) {
                $results[] = new LintResult(
                    LintSeverity::ERROR,
                    self::RULE_NAME,
                    sprintf("Class '%s' not found. Please create the class manually.", $param->phpClassMapping),
                    $contextPath . ' → $' . $param->name,
                );

                continue;
            }

            // Find the corresponding proto message
            $protoMessage = $this->findProtoMessageForParam($param, $protoRequestMessage, $protoMetadata);

            if ($protoMessage !== null) {
                $results = array_merge($results, $this->checkMessage(
                    $phpMessage,
                    $protoMessage,
                    $contextPath . ' → $' . $param->name,
                    $phpMetadata,
                    $protoMetadata,
                ));
            }
        }

        return $results;
    }

    /**
     * Find the proto MessageInfo corresponding to a PHP param.
     * Uses the proto Request message's field at the same tag number.
     */
    private function findProtoMessageForParam(
        \PhpProtoLint\Domain\FieldInfo $param,
        ?MessageInfo $protoRequestMessage,
        ProtoMetadata $protoMetadata,
    ): ?MessageInfo {
        if ($protoRequestMessage === null) {
            return null;
        }

        // Find the proto field matching this param's tag number
        $protoField = $protoRequestMessage->findByTagNumber($param->tagNumber);
        if ($protoField === null) {
            // Try by name
            $protoField = $protoRequestMessage->findByName($param->name);
        }

        if ($protoField === null || $protoField->protoTypeName === null) {
            return null;
        }

        return $protoMetadata->findMessage($protoField->protoTypeName);
    }

    /**
     * Recursively compare a PHP MessageInfo with a proto MessageInfo.
     *
     * @param array<string> $visited Names of proto messages already visited (circular reference protection)
     * @return LintResult[]
     */
    private function checkMessage(
        MessageInfo $phpMessage,
        MessageInfo $protoMessage,
        string $path,
        PhpMetadata $phpMetadata,
        ProtoMetadata $protoMetadata,
        array $visited = [],
    ): array {
        // Circular reference protection — stop if we've already visited this proto message
        if (in_array($protoMessage->name, $visited, true)) {
            return [];
        }
        $visited[] = $protoMessage->name;

        $results = [];

        // Check each proto field has a matching PHP field
        foreach ($protoMessage->fields as $protoField) {
            $phpField = $phpMessage->findByTagNumber($protoField->tagNumber);
            if ($phpField === null) {
                $phpField = $phpMessage->findByName($protoField->name);
            }

            if ($phpField === null) {
                $results[] = new LintResult(
                    LintSeverity::ERROR,
                    self::RULE_NAME,
                    sprintf(
                        "Field '%s' (tag %d) not found in PHP class '%s'",
                        $protoField->name,
                        $protoField->tagNumber,
                        $phpMessage->name,
                    ),
                    $path,
                );

                continue;
            }

            // Check tag number alignment
            if ($phpField->tagNumber !== $protoField->tagNumber && $phpField->tagNumber !== 0) {
                $results[] = new LintResult(
                    LintSeverity::ERROR,
                    self::RULE_NAME,
                    sprintf(
                        "Field '\$%s' has #[ProtoField(%d)] but proto field number is %d in message '%s'",
                        $phpField->name,
                        $phpField->tagNumber,
                        $protoField->tagNumber,
                        $protoMessage->name,
                    ),
                    $path,
                );
            }

            // Recurse for nested composite types
            if ($protoField->isComposite()
                && $phpField->phpClassMapping !== null
                && $protoField->protoTypeName !== null
            ) {
                $nestedPhpMessage = $phpMetadata->findMessage($phpField->phpClassMapping);
                $nestedProtoMessage = $protoMetadata->findMessage($protoField->protoTypeName);

                if ($nestedPhpMessage !== null && $nestedProtoMessage !== null) {
                    $results = array_merge($results, $this->checkMessage(
                        $nestedPhpMessage,
                        $nestedProtoMessage,
                        $path . ' → ' . $protoField->name,
                        $phpMetadata,
                        $protoMetadata,
                        $visited,
                    ));
                } elseif ($nestedPhpMessage === null) {
                    $results[] = new LintResult(
                        LintSeverity::ERROR,
                        self::RULE_NAME,
                        sprintf("Class '%s' not found. Please create the class manually.", $phpField->phpClassMapping),
                        $path . ' → ' . $protoField->name,
                    );
                }
            }
        }

        // Check for PHP fields missing #[ProtoField] annotation
        foreach ($phpMessage->fields as $phpField) {
            if ($phpField->tagNumber === 0) {
                $results[] = new LintResult(
                    LintSeverity::ERROR,
                    self::RULE_NAME,
                    sprintf(
                        "Field '\$%s' in class '%s' is missing #[ProtoField] annotation",
                        $phpField->name,
                        $phpMessage->name,
                    ),
                    $path,
                );
            }
        }

        return $results;
    }
}
