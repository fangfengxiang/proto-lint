<?php

declare(strict_types=1);

namespace ProtoLint\Linter;

use ProtoLint\Domain\MessageInfo;
use ProtoLint\Domain\MethodInfo;

/**
 * Rule-01: Positional parameter absolute alignment.
 *
 * Validates that PHP method parameters' physical position (1, 2, 3...)
 * strictly matches their #[ProtoField(X)] tag number X, and that the
 * order matches the .proto Request message field definition order.
 *
 * Task 4.2
 */
final class Rule01PositionalAlignment
{
    private const RULE_NAME = 'Rule-01';

    /**
     * @param MethodInfo $phpMethod PHP method with positional params
     * @param MessageInfo|null $protoRequestMessage Proto Request message with field definitions
     * @param string $contextPath e.g. "UserService::updateUser"
     * @return LintResult[]
     */
    public function validate(MethodInfo $phpMethod, ?MessageInfo $protoRequestMessage, string $contextPath): array
    {
        $results = [];
        $phpParams = $phpMethod->positionalParams;

        // Check 1: Each PHP param's position must match its tagNumber
        foreach ($phpParams as $param) {
            if ($param->tagNumber === 0) {
                $results[] = new LintResult(
                    LintSeverity::ERROR,
                    self::RULE_NAME,
                    sprintf("Parameter '%s' (position %d) is missing #[ProtoField] annotation", $param->name, $param->position),
                    $contextPath,
                );

                continue;
            }

            if ($param->position !== $param->tagNumber) {
                $results[] = new LintResult(
                    LintSeverity::ERROR,
                    self::RULE_NAME,
                    sprintf(
                        'Positional parameter index %d ($%s) has #[ProtoField(%d)] annotation. Expected #[ProtoField(%d)]',
                        $param->position,
                        $param->name,
                        $param->tagNumber,
                        $param->position,
                    ),
                    $contextPath,
                );
            }
        }

        // Check 2: PHP param order must match proto field order
        if ($protoRequestMessage !== null) {
            $protoFields = $protoRequestMessage->fields;

            // Check param count matches field count
            if (count($phpParams) !== count($protoFields)) {
                $results[] = new LintResult(
                    LintSeverity::ERROR,
                    self::RULE_NAME,
                    sprintf(
                        "PHP method has %d parameters but proto Request message '%s' has %d fields",
                        count($phpParams),
                        $protoRequestMessage->name,
                        count($protoFields),
                    ),
                    $contextPath,
                );
            }

            // Check each position: PHP param tagNumber must match proto field tagNumber
            $maxLen = min(count($phpParams), count($protoFields));
            for ($i = 0; $i < $maxLen; $i++) {
                $phpParam = $phpParams[$i];
                $protoField = $protoFields[$i];

                if ($phpParam->tagNumber !== $protoField->tagNumber) {
                    $results[] = new LintResult(
                        LintSeverity::ERROR,
                        self::RULE_NAME,
                        sprintf(
                            "PHP parameter position %d (\$%s, #[ProtoField(%d)]) does not match proto field '%s' (number %d) at position %d",
                            $phpParam->position,
                            $phpParam->name,
                            $phpParam->tagNumber,
                            $protoField->name,
                            $protoField->tagNumber,
                            $i + 1,
                        ),
                        $contextPath,
                    );
                }
            }
        }

        return $results;
    }
}
