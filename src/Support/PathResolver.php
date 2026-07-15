<?php

declare(strict_types=1);

namespace PhpProtoLint\Support;

/**
 * Shared utility for resolving paths relative to a base directory.
 */
final class PathResolver
{
    /**
     * Resolve a path relative to a base directory.
     * If the path is already absolute, return it as-is.
     */
    public static function resolve(string $baseDir, string $path): string
    {
        if ($path === '' || $path[0] === '/') {
            return $path;
        }

        return rtrim($baseDir, '/') . '/' . $path;
    }
}
