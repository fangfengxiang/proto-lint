<?php

declare(strict_types=1);

namespace ProtoLint\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ProtoLint\Parser\DescriptorReader;
use ProtoLint\Parser\ProtoParser;

/**
 * Task 8.2: ProtoParser unit tests.
 * Tests protoc invocation, descriptor deserialization, metadata extraction.
 *
 * Note: These tests require protoc to be installed.
 */
final class ProtoParserTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = dirname(__DIR__) . '/fixtures/proto';
    }

    public function testProtocAvailable(): void
    {
        $parser = new ProtoParser();

        try {
            $parser->ensureProtocAvailable();
            self::assertTrue(true, 'protoc is available');
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('protoc not available: ' . $e->getMessage());
        }
    }

    public function testCompileProtoFile(): void
    {
        $parser = new ProtoParser();

        try {
            $parser->ensureProtocAvailable();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('protoc not available');
        }

        $protoFile = $this->fixtureDir . '/user_service.proto';
        $binaryData = $parser->compile([$protoFile]);

        self::assertNotEmpty($binaryData);
    }

    public function testDescriptorReader(): void
    {
        $parser = new ProtoParser();

        try {
            $parser->ensureProtocAvailable();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('protoc not available');
        }

        $protoFile = $this->fixtureDir . '/user_service.proto';
        $binaryData = $parser->compile([$protoFile]);

        $reader = new DescriptorReader();
        $metadata = $reader->read($binaryData);

        // Should have UserService
        self::assertCount(1, $metadata->services);
        self::assertSame('UserService', $metadata->services[0]->name);

        // Should have methods
        $methods = $metadata->services[0]->methods;
        self::assertCount(2, $methods);
        self::assertSame('GetUser', $methods[0]->name);
        self::assertSame('UpdateUser', $methods[1]->name);

        // Should have messages
        $getUserReq = $metadata->findMessage('GetUserRequest');
        self::assertNotNull($getUserReq);
        self::assertCount(1, $getUserReq->fields);

        $updateUserReq = $metadata->findMessage('UpdateUserRequest');
        self::assertNotNull($updateUserReq);
        self::assertCount(3, $updateUserReq->fields);
        self::assertSame(1, $updateUserReq->fields[0]->tagNumber);
        self::assertSame(2, $updateUserReq->fields[1]->tagNumber);
        self::assertSame(3, $updateUserReq->fields[2]->tagNumber);
    }
}
