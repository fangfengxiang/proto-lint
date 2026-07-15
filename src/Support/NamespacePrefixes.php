<?php

declare(strict_types=1);

namespace PhpProtoLint\Support;

/**
 * Shared namespace prefix constants for service class resolution.
 *
 * Used by InjectAttributesEngine and CheckCommand to guess FQCN
 * from a bare service name by trying common namespace prefixes.
 */
final class NamespacePrefixes
{
    /** @var string[] FQCN namespace prefixes to try when locating service classes */
    public const SERVICE_PREFIXES = [
        '',
        'App\\Service\\',
        'App\\Services\\',
        'PhpProtoLint\\Service\\',
    ];
}
