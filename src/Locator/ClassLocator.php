<?php

declare(strict_types=1);

namespace ProtoLint\Locator;

use ProtoLint\Support\FqcnUtil;

/**
 * Resolves FQCN (fully-qualified class name) to PHP source file path.
 *
 * Strategy: PSR-4 autoload mapping first, full directory scan fallback.
 * Full scan results are cached for subsequent lookups.
 */
final class ClassLocator
{
    /** @var array<string, string|array<string, string>>|null PSR-4 prefix → base directory (or directories) */
    private ?array $psr4Map = null;

    /** @var array<string, string>|null Cached full-scan index: FQCN → file path */
    private ?array $scanIndex = null;

    /** @var array<string, string>|null Cached short-name index: short name → file path */
    private ?array $shortNameIndex = null;

    private bool $psr4Loaded = false;

    /**
     * @param string $sourceDir Root source directory for scanning
     * @param string|null $composerJsonPath Path to composer.json (default: sourceDir/composer.json)
     */
    public function __construct(
        private string $sourceDir,
        private ?string $composerJsonPath = null,
    ) {
        $this->composerJsonPath ??= $this->sourceDir . '/composer.json';
    }

    /**
     * Resolve FQCN to file path.
     *
     * Tries PSR-4 first, then falls back to full directory scan.
     *
     * @param string $fqcn Fully-qualified class name
     * @return string|null File path or null if not found
     */
    public function resolve(string $fqcn): ?string
    {
        // Try PSR-4 first
        $path = $this->resolveByPsr4($fqcn);
        if ($path !== null) {
            return $path;
        }

        // Fall back to full scan
        return $this->resolveByScan($fqcn);
    }

    /**
     * Resolve FQCN using PSR-4 autoload mapping.
     */
    private function resolveByPsr4(string $fqcn): ?string
    {
        $this->loadPsr4Map();
        if (empty($this->psr4Map)) {
            return null;
        }

        // Normalize FQCN: strip leading backslash
        $fqcn = ltrim($fqcn, '\\');

        // Sort prefixes by length descending (longest prefix match first)
        $prefixes = array_keys($this->psr4Map);
        usort($prefixes, fn($a, $b) => strlen($b) - strlen($a));

        foreach ($prefixes as $prefix) {
            $prefixNorm = rtrim($prefix, '\\');
            if (str_starts_with($fqcn, $prefixNorm . '\\') || $fqcn === $prefixNorm) {
                // Strip the prefix
                $relative = substr($fqcn, strlen($prefixNorm));
                $relative = ltrim($relative, '\\');
                // Convert namespace separators to directory separators
                $relativePath = str_replace('\\', '/', $relative);
                $basePath = $this->psr4Map[$prefix];
                $basePaths = is_array($basePath) ? $basePath : [$basePath];
                foreach ($basePaths as $subBasePath) {
                    $filePath = rtrim($this->sourceDir, '/') . '/' . trim($subBasePath, '/') . '/' . $relativePath . '.php';
                    if (file_exists($filePath)) {
                        return $filePath;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Resolve FQCN using full directory scan (with caching).
     */
    private function resolveByScan(string $fqcn): ?string
    {
        if ($this->scanIndex === null) {
            $this->buildScanIndex();
        }

        $fqcn = ltrim($fqcn, '\\');

        // Exact FQCN match
        if (isset($this->scanIndex[$fqcn])) {
            return $this->scanIndex[$fqcn];
        }

        // Short name fallback: O(1) lookup via pre-built index
        if ($this->shortNameIndex === null) {
            $this->buildShortNameIndex();
        }

        return $this->shortNameIndex[FqcnUtil::shortName($fqcn)] ?? null;
    }

    /**
     * Load PSR-4 mappings from composer.json.
     */
    private function loadPsr4Map(): void
    {
        if ($this->psr4Loaded) {
            return;
        }
        $this->psr4Loaded = true;

        if (!file_exists($this->composerJsonPath)) {
            $this->psr4Map = [];

            return;
        }

        $json = @file_get_contents($this->composerJsonPath);
        if ($json === false) {
            $this->psr4Map = [];

            return;
        }

        $data = json_decode($json, true);
        if (!is_array($data)) {
            $this->psr4Map = [];

            return;
        }

        $this->psr4Map = $data['autoload']['psr-4'] ?? [];
    }

    /**
     * Build a full-scan index of all PHP classes in sourceDir.
     * Extracts FQCN from namespace + class declarations using regex.
     */
    private function buildScanIndex(): void
    {
        $this->scanIndex = [];
        $phpFiles = $this->findPhpFiles($this->sourceDir);

        foreach ($phpFiles as $filePath) {
            $fqcn = $this->extractFqcnFromFile($filePath);
            if ($fqcn !== null) {
                // First occurrence wins (avoid duplicate class overrides)
                if (!isset($this->scanIndex[$fqcn])) {
                    $this->scanIndex[$fqcn] = $filePath;
                }
            }
        }
    }

    /**
     * Build a short-name index from the scan index for O(1) fallback lookup.
     * First occurrence wins when multiple classes share the same short name.
     */
    private function buildShortNameIndex(): void
    {
        if ($this->scanIndex === null) {
            $this->buildScanIndex();
        }
        $this->shortNameIndex = [];
        foreach ($this->scanIndex as $fqcn => $filePath) {
            $short = FqcnUtil::shortName($fqcn);
            if (!isset($this->shortNameIndex[$short])) {
                $this->shortNameIndex[$short] = $filePath;
            }
        }
    }

    /**
     * Recursively find all .php files in a directory.
     *
     * @return string[]
     */
    private function findPhpFiles(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->getExtension() === 'php') {
                $files[] = $fileInfo->getRealPath();
            }
        }

        return $files;
    }

    /**
     * Extract FQCN from a PHP file using regex (fast, no AST parse).
     */
    private function extractFqcnFromFile(string $filePath): ?string
    {
        $code = @file_get_contents($filePath);
        if ($code === false) {
            return null;
        }

        // Extract namespace
        $namespace = null;
        if (preg_match('/^\s*namespace\s+([^\s;]+)\s*;/m', $code, $matches)) {
            $namespace = $matches[1];
        }

        // Extract class/interface/enum name
        if (preg_match('/^\s*(?:abstract\s+|final\s+|readonly\s+)*(?:class|interface|enum)\s+(\w+)/m', $code, $matches)) {
            $className = $matches[1];

            return $namespace !== null ? $namespace . '\\' . $className : $className;
        }

        return null;
    }

    /**
     * Check if a class file exists and is resolvable.
     */
    public function exists(string $fqcn): bool
    {
        return $this->resolve($fqcn) !== null;
    }
}
