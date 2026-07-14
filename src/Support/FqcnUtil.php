<?php

declare(strict_types=1);

namespace ProtoLint\Support;

/**
 * Shared utility for FQCN (fully-qualified class name) operations.
 */
final class FqcnUtil
{
    /**
     * Extract the short name (last segment) from a FQCN.
     *
     * @param string $fqcn Fully-qualified class name
     * @return string Short class name (e.g. "UserMessage" from "App\Dto\UserMessage")
     */
    public static function shortName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);

        return end($parts);
    }
}
