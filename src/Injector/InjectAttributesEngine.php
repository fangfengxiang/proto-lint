<?php

declare(strict_types=1);

namespace ProtoLint\Injector;

use ProtoLint\Domain\MessageInfo;
use ProtoLint\Domain\MethodInfo;
use ProtoLint\Domain\ProtoMetadata;
use ProtoLint\Domain\ServiceInfo;
use ProtoLint\Locator\ClassLocator;
use ProtoLint\Support\NamespacePrefixes;

/**
 * Orchestrator for the inject-attributes pipeline.
 *
 * Tasks 6.7, 6.8, 6.9:
 * - 6.7: Namespace conflict isolation (auto-prefix vs explicit config)
 * - 6.8: CLI output format ([INFO], [GENERATED], [INJECTED], [OK])
 * - 6.9: Missing class error reporting
 *
 * Flow:
 * 1. Parse proto contract → get service/method/field definitions
 * 2. For each method, build injection plans (param tag numbers, return type)
 * 3. Resolve PHP class file paths via ClassLocator
 * 4. Apply namespace conflict isolation for field_class_mappings
 * 5. Delegate to AttributeInjector (PHP 8+) or DelimiterSandboxInjector (PHP 7)
 * 6. Collect and format output messages
 */
final class InjectAttributesEngine
{
    private AttributeInjector $attributeInjector;
    private DelimiterSandboxInjector $sandboxInjector;

    /** @var string[] Output lines for CLI display */
    private array $outputLines = [];

    /** @var array<string> Error messages for missing classes */
    private array $errors = [];

    /** @var int Count of files successfully injected */
    private int $injectedCount = 0;

    /** @var int Count of files with errors */
    private int $errorCount = 0;

    public function __construct()
    {
        $this->attributeInjector = new AttributeInjector();
        $this->sandboxInjector = new DelimiterSandboxInjector();
    }

    /**
     * Run the injection pipeline across all proto services and methods.
     *
     * @param ProtoMetadata $protoMetadata Proto-side metadata
     * @param ClassLocator $classLocator FQCN → file path resolver
     * @param array<string, array<string, array<string, string>>> $fieldClassMappings
     *   Nested map: [service => [method => [jsonKeyPath => fqcn]]]
     *   Empty string fqcn = auto-prefix isolation (task 6.7)
     * @param bool $php7Mode Use delimiter sandbox injector instead of PHP 8 attributes
     * @param bool $dryRun If true, don't write files, just report
     * @return array{output: string[], errors: string[], exitCode: int}
     */
    public function inject(
        ProtoMetadata $protoMetadata,
        ClassLocator $classLocator,
        array $fieldClassMappings = [],
        bool $php7Mode = false,
        bool $dryRun = false,
    ): array {
        $this->outputLines = [];
        $this->errors = [];
        $this->injectedCount = 0;
        $this->errorCount = 0;

        $this->info('Parsing proto contract...');

        foreach ($protoMetadata->services as $service) {
            $this->processService($service, $protoMetadata, $classLocator, $fieldClassMappings, $php7Mode, $dryRun);
        }

        // Final summary
        if ($this->errorCount === 0) {
            $this->ok(sprintf(
                'Attribute alignment complete. %d file(s) injected, 0 files corrupted.',
                $this->injectedCount,
            ));
        } else {
            $this->outputLines[] = sprintf(
                '[FATAL] Attribute alignment failed. %d file(s) injected, %d error(s) detected.',
                $this->injectedCount,
                $this->errorCount,
            );
        }

        return [
            'output' => $this->outputLines,
            'errors' => $this->errors,
            'exitCode' => $this->errorCount > 0 ? 1 : 0,
        ];
    }

    /**
     * Process all methods in a service.
     */
    private function processService(
        ServiceInfo $service,
        ProtoMetadata $protoMetadata,
        ClassLocator $classLocator,
        array $fieldClassMappings,
        bool $php7Mode,
        bool $dryRun,
    ): void {
        foreach ($service->methods as $method) {
            $this->processMethod(
                $service,
                $method,
                $protoMetadata,
                $classLocator,
                $fieldClassMappings,
                $php7Mode,
                $dryRun,
            );
        }
    }

    /**
     * Process a single method: build plans, resolve class, inject.
     */
    private function processMethod(
        ServiceInfo $service,
        MethodInfo $method,
        ProtoMetadata $protoMetadata,
        ClassLocator $classLocator,
        array $fieldClassMappings,
        bool $php7Mode,
        bool $dryRun,
    ): void {
        $serviceName = $service->name;
        $methodName = $method->name;
        $context = "{$serviceName}::{$methodName}";

        // Find the proto Request message for this method
        $requestMessage = null;
        if ($method->inputDataType !== null) {
            $requestMessage = $protoMetadata->findMessage($method->inputDataType);
        }

        // Build method injection plan: param name → tag number
        $methodPlan = [
            'params' => [],
            'returnType' => $method->returnDataType,
        ];

        if ($requestMessage !== null) {
            foreach ($requestMessage->fields as $field) {
                $methodPlan['params'][$field->name] = $field->tagNumber;
            }
        }

        // Get mappings for this specific service::method
        $methodMappings = $fieldClassMappings[$serviceName][$methodName] ?? [];

        // Resolve DTO class names with namespace conflict isolation (task 6.7)
        $resolvedClasses = $this->resolveFieldClassMappings(
            $methodMappings,
            $serviceName,
            $methodName,
            $requestMessage,
        );

        // Find the PHP service class file
        $serviceFqcn = $this->guessServiceFqcn($serviceName, $classLocator);
        if ($serviceFqcn !== null) {
            $this->injectIntoClass(
                $serviceFqcn,
                $classLocator,
                [$methodName => $methodPlan],
                [],
                $php7Mode,
                $dryRun,
                $context,
            );
        }

        // Inject into DTO classes
        foreach ($resolvedClasses as $jsonKeyPath => $dtoInfo) {
            $fqcn = $dtoInfo['fqcn'];

            // Task 6.9: Missing class error
            if (!$classLocator->exists($fqcn)) {
                $this->error(sprintf(
                    'Class %s not found. Please create the class manually.',
                    $fqcn,
                ));
                $this->errorCount++;

                continue;
            }

            // Build property plans for this DTO
            $dtoProtoMessage = $protoMetadata->findMessage($dtoInfo['protoTypeName']);
            $propPlan = [];
            if ($dtoProtoMessage !== null) {
                foreach ($dtoProtoMessage->fields as $field) {
                    $propPlan[$field->name] = $field->tagNumber;
                }
            }

            $this->injectIntoClass(
                $fqcn,
                $classLocator,
                [],
                $propPlan,
                $php7Mode,
                $dryRun,
                $context . ' → ' . $jsonKeyPath,
            );
        }
    }

    /**
     * Task 6.7: Namespace conflict isolation algorithm.
     *
     * For each field_class_mapping entry:
     * - If FQCN is empty string (default config) → auto-prefix: {ClassName}_{MethodName}_{JSON_KeyPath}
     * - If FQCN is non-empty (explicit config) → use developer-specified global DTO class name
     *
     * @param array<string, string> $mappings jsonKeyPath => fqcn (empty = auto-prefix)
     * @param string $serviceName
     * @param string $methodName
     * @param MessageInfo|null $requestMessage
     * @return array<string, array{fqcn: string, protoTypeName: string}>
     */
    private function resolveFieldClassMappings(
        array $mappings,
        string $serviceName,
        string $methodName,
        ?MessageInfo $requestMessage,
    ): array {
        $result = [];

        foreach ($mappings as $jsonKeyPath => $fqcn) {
            // Determine the proto type name for this field
            $protoTypeName = $this->findProtoTypeNameForKeyPath($jsonKeyPath, $requestMessage);

            if ($fqcn === '' || $fqcn === null) {
                // Default config: auto-prefix isolation
                // {ClassName}_{MethodName}_{JSON_KeyPath} with dots replaced by underscores
                $keyPathPart = str_replace('.', '_', $jsonKeyPath);
                $keyPathPart = $this->pascalCase($keyPathPart);
                $autoFqcn = sprintf('%s_%s_%s', $serviceName, $methodName, $keyPathPart);
                $result[$jsonKeyPath] = [
                    'fqcn' => $autoFqcn,
                    'protoTypeName' => $protoTypeName ?? $jsonKeyPath,
                ];
            } else {
                // Explicit config: use developer-specified global DTO class name
                $result[$jsonKeyPath] = [
                    'fqcn' => $fqcn,
                    'protoTypeName' => $protoTypeName ?? $jsonKeyPath,
                ];
            }
        }

        return $result;
    }

    /**
     * Find the proto message type name for a given JSON key path.
     * Traverses the request message fields to find composite types.
     */
    private function findProtoTypeNameForKeyPath(string $keyPath, ?MessageInfo $requestMessage): ?string
    {
        if ($requestMessage === null) {
            return null;
        }

        $parts = explode('.', $keyPath);
        $currentMessage = $requestMessage;

        foreach ($parts as $part) {
            $field = $currentMessage->findByName($part);
            if ($field === null) {
                return null;
            }

            if ($field->protoTypeName !== null) {
                // Look up nested message
                // We can't access ProtoMetadata here, so return the proto type name
                return $field->protoTypeName;
            }

            // If it's a scalar, no nested message
            if (!$field->isComposite()) {
                return null;
            }
        }

        return null;
    }

    /**
     * Inject attributes/annotations into a PHP class file.
     */
    private function injectIntoClass(
        string $fqcn,
        ClassLocator $classLocator,
        array $methodPlans,
        array $propertyPlans,
        bool $php7Mode,
        bool $dryRun,
        string $context,
    ): void {
        $filePath = $classLocator->resolve($fqcn);
        if ($filePath === null) {
            $this->error(sprintf(
                'Class %s not found. Please create the class manually.',
                $fqcn,
            ));
            $this->errorCount++;

            return;
        }

        if (empty($methodPlans) && empty($propertyPlans)) {
            return;
        }

        $this->info(sprintf('Injecting: %s → %s', $context, $fqcn));

        try {
            if ($php7Mode) {
                $newCode = $this->sandboxInjector->inject($filePath, $methodPlans, $propertyPlans);
            } else {
                $newCode = $this->attributeInjector->inject($filePath, $methodPlans, $propertyPlans);
            }

            if (!$dryRun) {
                $written = file_put_contents($filePath, $newCode);
                if ($written === false) {
                    $this->error(sprintf('Failed to write file: %s', $filePath));
                    $this->errorCount++;

                    return;
                }
            }

            $this->injected(sprintf('Updated annotations in %s', $filePath));
            $this->injectedCount++;
        } catch (\Throwable $e) {
            $this->error(sprintf('Failed to inject %s: %s', $fqcn, $e->getMessage()));
            $this->errorCount++;
        }
    }

    /**
     * Try to guess the FQCN of a service class by scanning the source directory.
     */
    private function guessServiceFqcn(string $serviceName, ClassLocator $classLocator): ?string
    {
        foreach (NamespacePrefixes::SERVICE_PREFIXES as $prefix) {
            $fqcn = $prefix . $serviceName;
            if ($classLocator->exists($fqcn)) {
                return $fqcn;
            }
        }

        return null;
    }

    /**
     * Convert a string to PascalCase.
     */
    private function pascalCase(string $str): string
    {
        $str = str_replace(['_', '-', '.'], ' ', $str);
        $str = ucwords($str);

        return str_replace(' ', '', $str);
    }

    // --- CLI output helpers (task 6.8) ---

    private function info(string $message): void
    {
        $this->outputLines[] = '[INFO] ' . $message;
    }

    private function injected(string $message): void
    {
        $this->outputLines[] = '[INJECTED] ' . $message;
    }

    private function ok(string $message): void
    {
        $this->outputLines[] = '[OK] ' . $message;
    }

    private function error(string $message): void
    {
        $this->outputLines[] = '[ERROR] ' . $message;
        $this->errors[] = $message;
    }
}
