<?php

declare(strict_types=1);

namespace ProtoLint\Parser;

use ProtoLint\Domain\PhpMetadata;
use ProtoLint\Locator\ClassLocator;

/**
 * High-level facade for PHP AST extraction with recursive descent.
 *
 * Combines PhpAstParser + AttributeNodeVisitor + ClassLocator to extract
 * PHP-side metadata, recursively descending into composite type DTOs.
 */
final class PhpContractParser
{
    private PhpAstParser $astParser;

    public function __construct(
        ?PhpAstParser $astParser = null,
    ) {
        $this->astParser = $astParser ?? new PhpAstParser();
    }

    /**
     * Parse a single PHP file and extract metadata (no recursive descent).
     *
     * @param string $filePath Path to PHP source file
     * @return PhpMetadata
     */
    public function parseFile(string $filePath): PhpMetadata
    {
        $ast = $this->astParser->parseFile($filePath);

        return $this->extractMetadata($ast);
    }

    /**
     * Parse a PHP file and recursively descend into composite type DTOs.
     *
     * For each FieldInfo with phpClassMapping, locates the DTO class file
     * via ClassLocator, parses its AST, and extracts nested FieldInfo.
     *
     * @param string $filePath Path to the primary PHP source file
     * @param ClassLocator $locator Class-to-file resolver
     * @return PhpMetadata
     */
    public function parseWithDescent(string $filePath, ClassLocator $locator): PhpMetadata
    {
        $metadata = $this->parseFile($filePath);

        // Collect all composite type FQCNs that need descent
        $toDescend = [];
        foreach ($metadata->services as $service) {
            foreach ($service->methods as $method) {
                foreach ($method->positionalParams as $param) {
                    if ($param->phpClassMapping !== null) {
                        $toDescend[$param->phpClassMapping] = true;
                    }
                }
            }
        }

        // Recursively descend into each composite type
        $visited = [];
        $messages = $metadata->messagesByName;
        $queue = array_keys($toDescend);

        while (!empty($queue)) {
            $fqcn = array_shift($queue);
            if (isset($visited[$fqcn])) {
                continue;
            }
            $visited[$fqcn] = true;

            // Skip if already parsed
            if (isset($messages[$fqcn])) {
                // Still need to check its fields for further descent
                foreach ($messages[$fqcn]->fields as $field) {
                    if ($field->phpClassMapping !== null && !isset($visited[$field->phpClassMapping])) {
                        $queue[] = $field->phpClassMapping;
                    }
                }

                continue;
            }

            $classFile = $locator->resolve($fqcn);
            if ($classFile === null) {
                // Class not found — will be reported by LintEngine
                continue;
            }

            $nestedMetadata = $this->parseFile($classFile);

            // Merge nested messages
            foreach ($nestedMetadata->messagesByName as $msgFqcn => $msg) {
                $messages[$msgFqcn] = $msg;
                // Queue nested composite types for further descent
                foreach ($msg->fields as $field) {
                    if ($field->phpClassMapping !== null && !isset($visited[$field->phpClassMapping])) {
                        $queue[] = $field->phpClassMapping;
                    }
                }
            }
        }

        return new PhpMetadata($metadata->services, $messages);
    }

    /**
     * Extract metadata from an AST using AttributeNodeVisitor.
     *
     * @param array $ast AST nodes
     * @return PhpMetadata
     */
    private function extractMetadata(array $ast): PhpMetadata
    {
        $visitor = new AttributeNodeVisitor();
        $this->astParser->traverse($ast, [$visitor]);

        return new PhpMetadata(
            $visitor->getServices(),
            $visitor->getMessages(),
        );
    }
}
