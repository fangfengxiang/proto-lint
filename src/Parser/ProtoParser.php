<?php

declare(strict_types=1);

namespace ProtoLint\Parser;

use RuntimeException;

/**
 * Calls protoc to compile .proto files into binary FileDescriptorSet.
 */
final class ProtoParser
{
    /** @var string|null Path to protoc binary (null = use PATH) */
    private ?string $protocPath;

    /**
     * @param string|null $protocPath Override protoc binary path
     */
    public function __construct(?string $protocPath = null)
    {
        $this->protocPath = $protocPath;
    }

    /**
     * Compile .proto files into binary FileDescriptorSet.
     *
     * @param string[] $protoFiles Array of .proto file paths
     * @param string|null $protoPath Proto import path (passed as --proto_path)
     * @return string Binary FileDescriptorSet data
     * @throws RuntimeException If protoc is not available or compilation fails
     */
    public function compile(array $protoFiles, ?string $protoPath = null): string
    {
        $protoc = $this->resolveProtocPath();

        $outputFile = tempnam(sys_get_temp_dir(), 'proto-lint-desc') . '.bin';

        try {
            $command = $this->buildCommand($protoc, $protoFiles, $protoPath, $outputFile);
            $exitCode = 0;
            $output = [];
            exec($command . ' 2>&1', $output, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    'protoc compilation failed (exit code ' . $exitCode . '): ' . implode("\n", $output),
                );
            }

            $binary = @file_get_contents($outputFile);
            if ($binary === false) {
                throw new RuntimeException('Failed to read descriptor output file: ' . $outputFile);
            }

            return $binary;
        } finally {
            @unlink($outputFile);
        }
    }

    /**
     * Check if protoc is available.
     *
     * @throws RuntimeException If protoc is not found
     */
    public function ensureProtocAvailable(): void
    {
        $this->resolveProtocPath();
    }

    private function resolveProtocPath(): string
    {
        $protoc = $this->protocPath ?? 'protoc';

        // Check if protoc exists and is executable
        $escaped = escapeshellarg($protoc);
        $output = shell_exec("$escaped --version 2>&1");
        if ($output === null || !str_contains($output, 'libprotoc')) {
            throw new RuntimeException(
                'protoc not found. Please install protoc compiler first.' . "\n"
                . 'On macOS: brew install protobuf' . "\n"
                . 'On Ubuntu: apt install protobuf-compiler',
            );
        }

        return $protoc;
    }

    /**
     * @param string[] $protoFiles
     */
    private function buildCommand(string $protoc, array $protoFiles, ?string $protoPath, string $outputFile): string
    {
        $parts = [escapeshellarg($protoc)];

        // Auto-derive --proto_path from proto file directories if not provided.
        // protoc requires --proto_path when absolute file paths are used.
        // Collect all unique directories to support multi-directory compilation.
        if ($protoPath === null && !empty($protoFiles)) {
            $dirs = [];
            foreach ($protoFiles as $file) {
                $dirs[dirname($file)] = true;
            }
            foreach (array_keys($dirs) as $dir) {
                $parts[] = '--proto_path=' . escapeshellarg($dir);
            }
        } elseif ($protoPath !== null) {
            $parts[] = '--proto_path=' . escapeshellarg($protoPath);
        }

        $parts[] = '--descriptor_set_out=' . escapeshellarg($outputFile);

        foreach ($protoFiles as $file) {
            $parts[] = escapeshellarg($file);
        }

        return implode(' ', $parts);
    }
}
