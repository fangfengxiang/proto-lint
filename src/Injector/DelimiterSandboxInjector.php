<?php

declare(strict_types=1);

namespace PhpProtoLint\Injector;

/**
 * PHP 7 Delimiter Sandbox injector.
 *
 * Tasks 6.5, 6.6: 定界符区域识别与区域内擦写
 *
 * Uses regex to locate `// --- proto-auto-generated-start ---` and
 * `// --- proto-auto-generated-end ---` regions, replacing only the
 * content within delimiters. External human-written comments are preserved.
 * If delimiters are missing, they are auto-inserted.
 */
final class DelimiterSandboxInjector
{
    private const START_MARKER = '// --- proto-auto-generated-start ---';
    private const END_MARKER = '// --- proto-auto-generated-end ---';

    /**
     * Inject/update @proto-* docblock annotations within delimiter sandbox.
     *
     * @param string $filePath PHP source file path
     * @param array<string, array{params: array<string, int>, returnType: ?string}> $methodPlans
     * @param array<string, int> $propertyPlans
     * @return string Modified source code
     */
    public function inject(
        string $filePath,
        array $methodPlans,
        array $propertyPlans,
    ): string {
        $code = @file_get_contents($filePath);
        if ($code === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        return $this->injectCode($code, $methodPlans, $propertyPlans);
    }

    /**
     * Inject/update @proto-* annotations in source code string.
     *
     * @param string $code PHP source code
     * @param array<string, array{params: array<string, int>, returnType: ?string}> $methodPlans
     * @param array<string, int> $propertyPlans
     * @return string Modified source code
     */
    public function injectCode(
        string $code,
        array $methodPlans,
        array $propertyPlans,
    ): string {
        // For each method in the plan, find the method and inject/update
        // the delimiter sandbox in its DocBlock
        foreach ($methodPlans as $methodName => $plan) {
            $annotations = $this->buildMethodAnnotations($methodName, $plan);
            $code = $this->injectMethodAnnotations($code, $methodName, $annotations);
        }

        // For each property in the plan, inject/update @proto-field
        foreach ($propertyPlans as $propName => $tagNumber) {
            $annotation = " @proto-field \${$propName} {$tagNumber}";
            $code = $this->injectPropertyAnnotation($code, $propName, $annotation);
        }

        return $code;
    }

    /**
     * Build the @proto-* annotation block for a method.
     *
     * @param string $methodName
     * @param array{params: array<string, int>, returnType: ?string} $plan
     * @return string Annotation content (without delimiters)
     */
    private function buildMethodAnnotations(string $methodName, array $plan): string
    {
        $lines = [' * @proto-method'];

        foreach ($plan['params'] as $paramName => $tagNumber) {
            $lines[] = " * @proto-field \${$paramName} {$tagNumber}";
        }

        if ($plan['returnType'] !== null) {
            $lines[] = " * @proto-return {$plan['returnType']}";
        }

        return implode("\n", $lines);
    }

    /**
     * Inject method annotations into the delimiter sandbox.
     * If delimiters are missing, insert them in the method's DocBlock.
     */
    private function injectMethodAnnotations(string $code, string $methodName, string $annotations): string
    {
        // Pattern to find the method declaration
        // Look for: function methodName(
        $methodPattern = '/(function\s+' . preg_quote($methodName, '/') . '\s*\()/';

        if (!preg_match($methodPattern, $code, $methodMatch, PREG_OFFSET_CAPTURE)) {
            return $code; // Method not found
        }

        $methodPos = $methodMatch[0][1];

        // Check if there's a DocBlock before the method
        $beforeMethod = substr($code, 0, $methodPos);
        $docBlockEnd = strrpos($beforeMethod, '*/');
        $docBlockStart = $docBlockEnd !== false ? strrpos(substr($beforeMethod, 0, $docBlockEnd), '/**') : false;

        if ($docBlockStart !== false && $docBlockEnd !== false) {
            // Existing DocBlock — check for delimiter sandbox
            $docBlock = substr($code, $methodPos - strlen($beforeMethod) + $docBlockStart, $docBlockEnd - $docBlockStart + 2);

            if (str_contains($docBlock, self::START_MARKER)) {
                // Replace content within existing delimiters
                $code = $this->replaceSandboxContent($code, $annotations);
            } else {
                // Insert delimiters into existing DocBlock
                $sandboxContent = "\n     " . self::START_MARKER . "\n" . $annotations . "\n     " . self::END_MARKER . "\n     ";
                // Insert before the closing */ of the DocBlock
                $insertPos = $methodPos - strlen($beforeMethod) + $docBlockEnd;
                $code = substr($code, 0, $insertPos) . $sandboxContent . substr($code, $insertPos);
            }
        } else {
            // No DocBlock — create one with delimiters
            $newDocBlock = "/**\n     " . self::START_MARKER . "\n" . $annotations . "\n     " . self::END_MARKER . "\n     */\n    ";
            $code = substr($code, 0, $methodPos) . $newDocBlock . substr($code, $methodPos);
        }

        return $code;
    }

    /**
     * Replace content within existing delimiter sandbox.
     */
    private function replaceSandboxContent(string $code, string $newContent): string
    {
        $pattern = '/(' . preg_quote(self::START_MARKER, '/') . ')(.*?)(' . preg_quote(self::END_MARKER, '/') . ')/s';

        return preg_replace($pattern, '$1' . "\n" . $newContent . "\n     $3", $code) ?? $code;
    }

    /**
     * Inject @proto-field annotation into a property's DocBlock.
     */
    private function injectPropertyAnnotation(string $code, string $propName, string $annotation): string
    {
        // Pattern to find: public $propName or public int $propName etc.
        $pattern = '/(public\s+[^$]*\$' . preg_quote($propName, '/') . ')/';

        if (!preg_match($pattern, $code, $match, PREG_OFFSET_CAPTURE)) {
            return $code; // Property not found
        }

        $propPos = $match[0][1];
        $beforeProp = substr($code, 0, $propPos);

        // Check for existing DocBlock
        $docBlockEnd = strrpos($beforeProp, '*/');
        $docBlockStart = $docBlockEnd !== false ? strrpos(substr($beforeProp, 0, $docBlockEnd), '/**') : false;

        if ($docBlockStart !== false && $docBlockEnd !== false) {
            // Existing DocBlock — check if @proto-field already exists
            $docBlock = substr($beforeProp, $docBlockStart, $docBlockEnd - $docBlockStart + 2);
            if (str_contains($docBlock, "@proto-field \${$propName}")) {
                // Update existing annotation
                $pattern = '/@proto-field \$' . preg_quote($propName, '/') . '\s+\d+/';
                $code = preg_replace($pattern, trim($annotation), $code) ?? $code;
            } else {
                // Add annotation before closing */
                $insertPos = $propPos - strlen($beforeProp) + $docBlockEnd;
                $code = substr($code, 0, $insertPos) . $annotation . "\n     " . substr($code, $insertPos);
            }
        } else {
            // No DocBlock — create one
            $newDocBlock = "/**\n     " . trim($annotation) . "\n     */\n    ";
            $code = substr($code, 0, $propPos) . $newDocBlock . substr($code, $propPos);
        }

        return $code;
    }
}
