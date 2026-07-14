<?php

declare(strict_types=1);

namespace ProtoLint\Config;

use ProtoLint\Support\PathResolver;

/**
 * Parses proto-mapping.json configuration file.
 *
 * Task 7.2: Parse $schema, target_proto_files,
 * request/response → payload + field_class_mappings
 */
final class MappingConfigLoader
{
    /**
     * Load and parse a proto-mapping.json file.
     *
     * @param string $filePath Path to proto-mapping.json
     * @return MappingConfig
     * @throws \RuntimeException If file cannot be read or parsed
     */
    public function load(string $filePath): MappingConfig
    {
        $json = @file_get_contents($filePath);
        if ($json === false) {
            throw new \RuntimeException("Failed to read mapping config file: {$filePath}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in mapping config file: {$filePath}");
        }

        return $this->parseConfig($data, $filePath);
    }

    /**
     * Parse the raw config array into MappingConfig.
     *
     * @param array $data Parsed JSON data
     * @param string $configPath Path to the config file (for resolving relative paths)
     */
    private function parseConfig(array $data, string $configPath): MappingConfig
    {
        $configDir = dirname(realpath($configPath) ?: $configPath);

        $targetProtoFiles = [];
        foreach ($data['target_proto_files'] ?? [] as $file) {
            $targetProtoFiles[] = PathResolver::resolve($configDir, $file);
        }

        // Parse request and response sections
        $request = $this->parsePayloadConfig($data['request'] ?? []);
        $response = $this->parsePayloadConfig($data['response'] ?? []);

        return new MappingConfig(
            $data['$schema'] ?? '',
            $targetProtoFiles,
            $request,
            $response,
        );
    }

    /**
     * Parse a request/response payload config section.
     *
     * @param array $section Raw config data for request or response
     */
    private function parsePayloadConfig(array $section): PayloadConfig
    {
        $payload = $section['payload'] ?? [];
        $fieldClassMappings = $section['field_class_mappings'] ?? [];

        return new PayloadConfig($payload, $fieldClassMappings);
    }
}
