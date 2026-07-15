<?php

declare(strict_types=1);

namespace PhpProtoLint\Parser;

use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

/**
 * Parses PHP source code into AST using nikic/php-parser v5
 * with Emulative Lexer for cross-version PHP 7/8 parsing.
 */
final class PhpAstParser
{
    private \PhpParser\Parser $parser;

    public function __construct()
    {
        // ParserFactory uses Emulative lexer for cross-version PHP 7/8 parsing
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * Parse PHP source code into AST.
     *
     * @param string $code PHP source code
     * @return Node\Stmt[] Array of top-level statements
     */
    public function parse(string $code): array
    {
        $ast = $this->parser->parse($code);

        return $ast ?? [];
    }

    /**
     * Parse a PHP file into AST.
     *
     * @param string $filePath Path to PHP source file
     * @return Node\Stmt[] Array of top-level statements
     * @throws \RuntimeException If file cannot be read
     */
    public function parseFile(string $filePath): array
    {
        $code = @file_get_contents($filePath);
        if ($code === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        return $this->parse($code);
    }

    /**
     * Create a NodeTraverser with the given visitors.
     *
     * @param \PhpParser\NodeVisitor[] $visitors
     */
    public function traverse(array $ast, array $visitors): array
    {
        $traverser = new NodeTraverser();
        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        return $traverser->traverse($ast);
    }
}
