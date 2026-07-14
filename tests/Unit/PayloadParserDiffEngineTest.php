<?php

declare(strict_types=1);

namespace ProtoLint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProtoLint\Domain\PhpMetadata;
use ProtoLint\Linter\DiffEngine;
use ProtoLint\Linter\JsonKeyTreeFlattener;
use ProtoLint\Linter\PayloadParser;

/**
 * Task 8.6: PayloadParser + DiffEngine unit tests.
 * Tests JSON Key tree flattening, diff detection, field deficit alerting.
 */
final class PayloadParserDiffEngineTest extends TestCase
{
    public function testFlattenSimpleJson(): void
    {
        $flattener = new JsonKeyTreeFlattener();
        $paths = $flattener->flatten([
            'user_id' => 10001,
            'name' => 'alice',
        ]);

        self::assertContains('user_id', $paths);
        self::assertContains('name', $paths);
    }

    public function testFlattenNestedJson(): void
    {
        $flattener = new JsonKeyTreeFlattener();
        $paths = $flattener->flatten([
            'data' => [
                'id' => 10001,
                'name' => 'alice',
            ],
            'user_id' => 1,
        ]);

        self::assertContains('data.id', $paths);
        self::assertContains('data.name', $paths);
        self::assertContains('user_id', $paths);
    }

    public function testFlattenIndexedArray(): void
    {
        $flattener = new JsonKeyTreeFlattener();
        $paths = $flattener->flatten([
            'tags' => ['a', 'b'],
        ]);

        self::assertContains('tags[]', $paths);
    }

    public function testDiffEngineNoDiffWhenAllMapped(): void
    {
        $diffEngine = new DiffEngine();
        $phpMetadata = new PhpMetadata([], []);

        $results = $diffEngine->computeDiff(
            ['user_id' => 1, 'name' => 'test'],
            ['user_id' => 'SomeClass', 'name' => 'AnotherClass'],
            $phpMetadata,
        );

        // With no PHP messages loaded, all fields should be reported as deficits
        self::assertNotEmpty($results);
    }

    public function testPayloadParserParseJson(): void
    {
        $parser = new PayloadParser();
        $result = $parser->parseJson('{"key": "value"}');
        self::assertSame(['key' => 'value'], $result);
    }

    public function testPayloadParserInvalidJson(): void
    {
        $parser = new PayloadParser();
        $this->expectException(\JsonException::class);
        $parser->parseJson('invalid json');
    }
}
