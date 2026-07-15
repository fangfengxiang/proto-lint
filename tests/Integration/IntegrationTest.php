<?php

declare(strict_types=1);

namespace PhpProtoLint\Tests\Integration;

use PhpProtoLint\Config\BulkConfigLoader;
use PhpProtoLint\Linter\LintEngine;
use PhpProtoLint\Locator\ClassLocator;
use PhpProtoLint\Parser\DescriptorReader;
use PhpProtoLint\Parser\PhpContractParser;
use PhpProtoLint\Parser\ProtoParser;
use PHPUnit\Framework\TestCase;

/**
 * Task 8.9: Integration test.
 * Full pipeline: proto-bulk.json -> proto parsing -> PHP parsing with descent -> LintEngine check.
 */
final class IntegrationTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = dirname(__DIR__) . '/fixtures';
    }

    public function testFullCheckPipeline(): void
    {
        $protoParser = new ProtoParser();

        try {
            $protoParser->ensureProtocAvailable();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('protoc not available');
        }

        $protoFile = $this->fixtureDir . '/proto/user_service.proto';
        $binaryData = $protoParser->compile([$protoFile]);

        $reader = new DescriptorReader();
        $protoMetadata = $reader->read($binaryData);

        $phpSourceDir = $this->fixtureDir . '/php8';
        $classLocator = new ClassLocator($phpSourceDir);
        $phpContractParser = new PhpContractParser();

        $serviceFile = $phpSourceDir . '/Service/UserService.php';
        $phpMetadata = $phpContractParser->parseWithDescent($serviceFile, $classLocator);

        $engine = new LintEngine();
        $report = $engine->check($protoMetadata, $phpMetadata, $phpSourceDir);

        self::assertSame(0, $report->getExitCode(), $report->format());
    }

    public function testConfigLoader(): void
    {
        $loader = new BulkConfigLoader();
        $config = $loader->load($this->fixtureDir . '/proto-bulk.json');

        self::assertSame('UserService', array_key_first($config->services));
        self::assertNotEmpty($config->defaultTargetProto);
    }
}
