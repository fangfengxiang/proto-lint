<?php

declare(strict_types=1);

namespace PhpProtoLint\Linter;

/**
 * Recursively flattens a JSON payload into dot-notation field paths.
 *
 * Task 5.2: JSON Key 树递归探测与扁平化
 *
 * Examples:
 *   {"data": {"id": 1, "name": "alice"}} → ["data.id", "data.name"]
 *   {"items": [{"name": "a"}, {"name": "b"}]} → ["items[].name"]
 *   {"data": {}} → ["data"]
 */
final class JsonKeyTreeFlattener
{
    /**
     * Flatten a JSON-decoded array into dot-notation paths.
     *
     * @param mixed $data JSON-decoded data
     * @param string $prefix Current path prefix
     * @return string[] Flattened field paths
     */
    public function flatten(mixed $data, string $prefix = ''): array
    {
        if (!is_array($data)) {
            return $prefix !== '' ? [$prefix] : [];
        }

        // Empty array or object → leaf node
        if (empty($data)) {
            return $prefix !== '' ? [$prefix] : [];
        }

        $paths = [];

        if ($this->isIndexedArray($data)) {
            // Indexed array (list): collect unique sub-paths from all elements
            $subPaths = [];
            foreach ($data as $item) {
                if (is_array($item) && !empty($item)) {
                    $subPaths = array_merge($subPaths, $this->flatten($item, ''));
                } elseif (!is_array($item)) {
                    $subPaths[] = '';
                }
            }
            $subPaths = array_unique($subPaths);

            if (empty($subPaths)) {
                $paths[] = $prefix . '[]';
            } else {
                foreach ($subPaths as $sp) {
                    $paths[] = $sp === '' ? $prefix . '[]' : $prefix . '[].' . $sp;
                }
            }
        } else {
            // Associative array (object): recurse into each key
            foreach ($data as $key => $value) {
                $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
                if (is_array($value)) {
                    if (empty($value)) {
                        // Empty object/array as leaf node
                        $paths[] = $path;
                    } else {
                        $paths = array_merge($paths, $this->flatten($value, $path));
                    }
                } else {
                    // Scalar value
                    $paths[] = $path;
                }
            }
        }

        return $paths;
    }

    /**
     * Check if an array is an indexed list (sequential numeric keys from 0).
     */
    private function isIndexedArray(array $array): bool
    {
        if (empty($array)) {
            return false;
        }

        return array_is_list($array);
    }
}
