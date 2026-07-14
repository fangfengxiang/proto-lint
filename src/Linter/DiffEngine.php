<?php

declare(strict_types=1);

namespace ProtoLint\Linter;

use ProtoLint\Domain\PhpMetadata;

/**
 * Computes the set difference between JSON payload paths and PHP DTO
 * #[ProtoField] ownership paths to detect field deficit.
 *
 * Task 5.4: 字段缺损差集检测
 * Task 5.5: 字段缺损报警
 */
final class DiffEngine
{
    private JsonKeyTreeFlattener $flattener;

    public function __construct()
    {
        $this->flattener = new JsonKeyTreeFlattener();
    }

    /**
     * Compute the diff: JSON paths that have no corresponding #[ProtoField]
     * ownership in PHP DTO classes.
     *
     * @param array $jsonPayload JSON-decoded payload
     * @param array<string, string> $fieldClassMappings JSON top-level key → PHP FQCN
     * @param PhpMetadata $phpMetadata PHP-side metadata with DTO classes
     * @return LintResult[] Field deficit results
     */
    public function computeDiff(
        array $jsonPayload,
        array $fieldClassMappings,
        PhpMetadata $phpMetadata,
    ): array {
        $jsonPaths = $this->flattener->flatten($jsonPayload);
        $phpPaths = $this->collectPhpPaths($jsonPayload, $fieldClassMappings, $phpMetadata);

        $results = [];

        // Diff: JSON paths - PHP paths (fields in traffic but no PHP ownership)
        $phpPathsSet = array_flip($phpPaths);
        foreach ($jsonPaths as $jsonPath) {
            // Also check with [] notation stripped for matching
            $normalizedPath = $this->normalizeArrayPath($jsonPath);
            if (!isset($phpPathsSet[$jsonPath]) && !isset($phpPathsSet[$normalizedPath])) {
                $results[] = new LintResult(
                    LintSeverity::ERROR,
                    'Shadow-Lint',
                    sprintf(
                        "Field '%s' exists in traffic payload but has no #[ProtoField] ownership in PHP class. Traffic may be silently dropped.",
                        $jsonPath,
                    ),
                    'shadow-lint',
                );
            }
        }

        return $results;
    }

    /**
     * Collect all PHP DTO #[ProtoField] paths, prefixed with the JSON top-level key.
     *
     * @return string[]
     */
    private function collectPhpPaths(
        array $jsonPayload,
        array $fieldClassMappings,
        PhpMetadata $phpMetadata,
    ): array {
        $paths = [];

        foreach ($jsonPayload as $jsonKey => $value) {
            if (!isset($fieldClassMappings[$jsonKey])) {
                // No class mapping for this key — it's a scalar field
                if (!is_array($value)) {
                    $paths[] = (string) $jsonKey;
                }

                continue;
            }

            $className = $fieldClassMappings[$jsonKey];
            $message = $phpMetadata->findMessage($className);

            if ($message === null) {
                continue;
            }

            // Generate paths: jsonKey.fieldName for each field in the DTO
            foreach ($message->fields as $field) {
                $paths[] = $jsonKey . '.' . $field->name;
            }
        }

        return $paths;
    }

    /**
     * Normalize array notation paths for matching.
     * "items[].name" → "items.name"
     */
    private function normalizeArrayPath(string $path): string
    {
        return str_replace('[].', '.', $path);
    }
}
