<?php

declare(strict_types=1);

namespace ProtoLint\Linter;

use ProtoLint\Domain\MessageInfo;
use ProtoLint\Domain\ProtoMetadata;

/**
 * Parses shadow traffic payloads and validates them against .proto contract
 * boundaries.
 *
 * Task 5.1: 解析 proto-mapping.json 中的 request.payload 和 response.payload
 * Task 5.3: 流量合法性断言 — JSON 载荷通过 .proto Message 定义进行静态反序列化模拟
 */
final class PayloadParser
{
    /**
     * Validate that a JSON payload does not exceed the .proto contract boundary.
     *
     * @param array $jsonPayload JSON-decoded payload
     * @param array<string, string> $fieldClassMappings JSON key → proto message type name
     * @param ProtoMetadata $protoMetadata Proto-side metadata
     * @return LintResult[] Boundary violation results
     */
    public function validateBoundary(
        array $jsonPayload,
        array $fieldClassMappings,
        ProtoMetadata $protoMetadata,
    ): array {
        $results = [];

        foreach ($jsonPayload as $jsonKey => $value) {
            if (!isset($fieldClassMappings[$jsonKey])) {
                // No mapping — skip boundary check for this key
                continue;
            }

            $protoTypeName = $fieldClassMappings[$jsonKey];
            $jsonKey = (string) $jsonKey;
            $protoMessage = $protoMetadata->findMessage($protoTypeName);

            if ($protoMessage === null) {
                // Proto message not found — skip (will be caught by other checks)
                continue;
            }

            // Check each JSON field against proto message fields
            if (is_array($value) && !empty($value)) {
                if (array_is_list($value)) {
                    // Index array: check each element against proto fields
                    foreach ($value as $element) {
                        if (is_array($element) && !empty($element)) {
                            $results = array_merge($results, $this->checkFields($element, $protoMessage, $jsonKey));
                        }
                    }
                } else {
                    // Associative array: check directly
                    $results = array_merge($results, $this->checkFields($value, $protoMessage, $jsonKey));
                }
            }
        }

        return $results;
    }

    /**
     * Check that all JSON keys have corresponding proto field definitions.
     *
     * @param array $jsonObject JSON object to check
     * @param MessageInfo $protoMessage Proto message definition
     * @param string $path Current path prefix
     * @return LintResult[]
     */
    private function checkFields(array $jsonObject, MessageInfo $protoMessage, string $path): array
    {
        $results = [];

        foreach (array_keys($jsonObject) as $key) {
            $protoField = $protoMessage->findByName((string) $key);
            if ($protoField === null) {
                $results[] = new LintResult(
                    LintSeverity::ERROR,
                    'Shadow-Lint',
                    sprintf(
                        "Field '%s.%s' in JSON payload exceeds .proto contract boundary",
                        $path,
                        $key,
                    ),
                    'shadow-lint',
                );
            }
        }

        return $results;
    }

    /**
     * Parse a JSON string into an array.
     *
     * @param string $jsonStr JSON string
     * @return array Decoded JSON array
     * @throws \JsonException If JSON is invalid or does not decode to an array
     */
    public function parseJson(string $jsonStr): array
    {
        $data = json_decode($jsonStr, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \JsonException('JSON payload must decode to an array (object or list)');
        }

        return $data;
    }
}
