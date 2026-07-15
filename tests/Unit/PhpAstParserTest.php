<?php

declare(strict_types=1);

namespace PhpProtoLint\Tests\Unit;

use PhpProtoLint\Locator\ClassLocator;
use PhpProtoLint\Parser\PhpAstParser;
use PhpProtoLint\Parser\PhpContractParser;
use PHPUnit\Framework\TestCase;

/**
 * Task 8.3: PhpAstParser unit tests.
 * Tests PHP 8 Attribute extraction, PHP 7 docblock extraction, recursive descent.
 */
final class PhpAstParserTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = dirname(__DIR__) . '/fixtures';
    }

    public function testParsePhp8Attributes(): void
    {
        $filePath = $this->fixtureDir . '/php8/Service/UserService.php';
        $parser = new PhpContractParser();
        $metadata = $parser->parseFile($filePath);

        self::assertCount(1, $metadata->services);
        $service = $metadata->services[0];
        self::assertSame('UserService', $service->name);

        // Should have 2 methods
        self::assertCount(2, $service->methods);
    }

    public function testParsePhp7Docblocks(): void
    {
        $filePath = $this->fixtureDir . '/php7/UserService.php';
        $parser = new PhpContractParser();
        $metadata = $parser->parseFile($filePath);

        self::assertCount(1, $metadata->services);
        $service = $metadata->services[0];
        self::assertSame('UserService', $service->name);

        $updateMethod = $service->findMethod('updateUser');
        self::assertNotNull($updateMethod);
        self::assertCount(3, $updateMethod->positionalParams);
    }

    public function testRecursiveDescent(): void
    {
        $filePath = $this->fixtureDir . '/php8/Service/UserService.php';
        $locator = new ClassLocator($this->fixtureDir . '/php8');
        $parser = new PhpContractParser();

        $metadata = $parser->parseWithDescent($filePath, $locator);

        // Should have descended into UserMessage DTO
        $userMessageFound = false;
        foreach ($metadata->messagesByName as $fqcn => $msg) {
            if (str_contains($fqcn, 'UserMessage')) {
                $userMessageFound = true;

                break;
            }
        }
        self::assertTrue($userMessageFound, 'Recursive descent should find UserMessage DTO');
    }

    public function testParseNonExistentFile(): void
    {
        $parser = new PhpAstParser();
        $this->expectException(\RuntimeException::class);
        $parser->parseFile('/nonexistent/file.php');
    }
}
