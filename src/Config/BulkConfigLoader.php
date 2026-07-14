<?php

declare(strict_types=1);

namespace ProtoLint\Config;

use ProtoLint\Support\PathResolver;

/**
 * Parses proto-bulk.json configuration file.
 *
 * Task 7.1: Parse $schema, source_dir, default_target_proto,
 * services → methods → mapping_file / target_proto_files / rule_overrides
 * Task 7.3: rule_overrides config parsing (service/method level Rule-03 severity)
 */
final class BulkConfigLoader
{
    /**
     * Load and parse a proto-bulk.json file.
     *
     * @param string $filePath Path to proto-bulk.json
     * @return BulkConfig
     * @throws \RuntimeException If file cannot be read or parsed
     */
    public function load(string $filePath): BulkConfig
    {
        $json = @file_get_contents($filePath);
        if ($json === false) {
            throw new \RuntimeException("Failed to read config file: {$filePath}");
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \RuntimeException("Invalid JSON in config file: {$filePath}");
        }

        return $this->parseConfig($data, $filePath);
    }

    /**
     * Parse the raw config array into BulkConfig.
     *
     * @param array $data Parsed JSON data
     * @param string $configPath Path to the config file (for resolving relative paths)
     */
    private function parseConfig(array $data, string $configPath): BulkConfig
    {
        $configDir = dirname(realpath($configPath) ?: $configPath);

        $sourceDir = PathResolver::resolve($configDir, $data['source_dir'] ?? 'src/');
        $defaultTargetProto = PathResolver::resolve($configDir, $data['default_target_proto'] ?? '');

        // Parse services → methods
        $services = [];
        $ruleOverrides = [];

        foreach ($data['services'] ?? [] as $serviceName => $serviceConfig) {
            $methods = [];

            foreach ($serviceConfig['methods'] ?? [] as $methodName => $methodConfig) {
                $mappingFile = isset($methodConfig['mapping_file'])
                    ? PathResolver::resolve($configDir, $methodConfig['mapping_file'])
                    : null;

                $targetProtoFiles = isset($methodConfig['target_proto_files'])
                    ? array_map(fn($f) => PathResolver::resolve($configDir, $f), $methodConfig['target_proto_files'])
                    : null;

                $methods[$methodName] = new MethodConfig(
                    $methodName,
                    $mappingFile,
                    $targetProtoFiles,
                );

                // Parse method-level rule_overrides (task 7.3)
                if (isset($methodConfig['rule_overrides'])) {
                    foreach ($methodConfig['rule_overrides'] as $rule => $severity) {
                        $ruleOverrides[$serviceName][$methodName][$rule] = $severity;
                    }
                }
            }

            // Parse service-level rule_overrides (task 7.3)
            if (isset($serviceConfig['rule_overrides'])) {
                foreach ($serviceConfig['rule_overrides'] as $rule => $severity) {
                    $ruleOverrides[$serviceName][BulkConfig::SERVICE_LEVEL_KEY][$rule] = $severity;
                }
            }

            $services[$serviceName] = new ServiceConfig($serviceName, $methods);
        }

        return new BulkConfig(
            $data['$schema'] ?? '',
            $sourceDir,
            $defaultTargetProto,
            $services,
            $ruleOverrides,
        );
    }
}
