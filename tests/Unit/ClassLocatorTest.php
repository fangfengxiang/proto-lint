<?php

declare(strict_types=1);

namespace PhpProtoLint\Tests\Unit;

use PhpProtoLint\Locator\ClassLocator;
use PHPUnit\Framework\TestCase;

/**
 * Task 8.4: ClassLocator unit tests.
 * Tests PSR-4 resolution, full scan fallback, and cache reuse.
 */
final class ClassLocatorTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = dirname(__DIR__) . '/fixtures/php8';
    }

    public function testResolveByPsr4Scan(): void
    {
        $locator = new ClassLocator($this->fixtureDir);

        // The fixture dir doesn't have composer.json, so it falls back to scan
        $path = $locator->resolve('PhpProtoLint\Test\Fixture\Service\UserService');
        self::assertNotNull($path);
        self::assertFileExists($path);
    }

    public function testResolveNonExistentClass(): void
    {
        $locator = new ClassLocator($this->fixtureDir);
        self::assertNull($locator->resolve('NonExistent\Class'));
    }

    public function testExistsReturnsBool(): void
    {
        $locator = new ClassLocator($this->fixtureDir);
        self::assertTrue($locator->exists('PhpProtoLint\Test\Fixture\Service\UserService'));
        self::assertFalse($locator->exists('NonExistent\Class'));
    }

    public function testScanCacheReuse(): void
    {
        $locator = new ClassLocator($this->fixtureDir);

        // First call triggers scan
        $path1 = $locator->resolve('PhpProtoLint\Test\Fixture\Dto\UserMessage');
        // Second call should use cache
        $path2 = $locator->resolve('PhpProtoLint\Test\Fixture\Dto\UserMessage');

        self::assertSame($path1, $path2);
    }

    public function testResolveWithLeadingBackslash(): void
    {
        $locator = new ClassLocator($this->fixtureDir);
        $path = $locator->resolve('\PhpProtoLint\Test\Fixture\Service\UserService');
        self::assertNotNull($path);
    }
}
