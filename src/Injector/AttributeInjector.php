<?php

declare(strict_types=1);

namespace PhpProtoLint\Injector;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

/**
 * PHP 8+ Attribute injector with format-preserving printing.
 *
 * Tasks 6.1, 6.4: Token 保持模式 + FormatPreservingPrinter 写回
 *
 * Flow:
 * 1. Parse source with Lexer (token retention) → $oldStmts, $oldTokens
 * 2. Clone AST with CloningVisitor → $newStmts
 * 3. Traverse $newStmts with AttributeInjectionVisitor → modified
 * 4. printFormatPreserving($newStmts, $oldStmts, $oldTokens) → output
 */
final class AttributeInjector
{
    private \PhpParser\Parser $parser;
    private Standard $printer;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->printer = new Standard();
    }

    /**
     * Inject/update Proto attributes in a PHP source file.
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
     * Inject/update Proto attributes in PHP source code string.
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
        // Parse source code
        $oldStmts = $this->parser->parse($code);
        if ($oldStmts === null) {
            return $code; // Parse error — return original
        }
        $oldTokens = $this->parser->getTokens();

        // Clone AST for modification (preserves original for format-preserving)
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CloningVisitor());
        $newStmts = $traverser->traverse($oldStmts);

        // Inject attributes
        $injectionVisitor = new AttributeInjectionVisitor($methodPlans, $propertyPlans);
        $traverser2 = new NodeTraverser();
        $traverser2->addVisitor($injectionVisitor);
        $newStmts = $traverser2->traverse($newStmts);

        // Format-preserving print
        return $this->printer->printFormatPreserving($newStmts, $oldStmts, $oldTokens);
    }
}
