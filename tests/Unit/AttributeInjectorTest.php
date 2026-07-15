<?php

declare(strict_types=1);

namespace PhpProtoLint\Tests\Unit;

use PhpProtoLint\Injector\AttributeInjector;
use PHPUnit\Framework\TestCase;

/**
 * Task 8.7: AttributeInjector unit tests.
 * Tests PHP 8 Attribute overwrite, format preservation, non-Proto attribute preservation.
 */
final class AttributeInjectorTest extends TestCase
{
    public function testInjectProtoFieldOnMethodParam(): void
    {
        $code = <<<'PHP'
<?php
class UserService {
    public function updateUser(int $userId, string $name) {}
}
PHP;
        $injector = new AttributeInjector();
        $result = $injector->injectCode(
            $code,
            ['updateUser' => ['params' => ['userId' => 1, 'name' => 2], 'returnType' => null]],
            [],
        );

        self::assertStringContainsString('#[ProtoField(1)]', $result);
        self::assertStringContainsString('#[ProtoField(2)]', $result);
        self::assertStringContainsString('#[ProtoMethod]', $result);
    }

    public function testPreserveNonProtoAttributes(): void
    {
        $code = <<<'PHP'
<?php
class UserService {
    #[\Route('/users')]
    #[\ProtoField(1)]
    public function getUser(int $userId) {}
}
PHP;
        $injector = new AttributeInjector();
        $result = $injector->injectCode(
            $code,
            ['getUser' => ['params' => ['userId' => 1], 'returnType' => null]],
            [],
        );

        // #[Route] should be preserved
        self::assertStringContainsString('Route', $result);
        // #[ProtoField(1)] should still be present (updated)
        self::assertStringContainsString('#[ProtoField(1)]', $result);
    }

    public function testInjectPropertyAnnotation(): void
    {
        $code = <<<'PHP'
<?php
class UserMessage {
    public int $id;
    public string $name;
}
PHP;
        $injector = new AttributeInjector();
        $result = $injector->injectCode(
            $code,
            [],
            ['id' => 1, 'name' => 2],
        );

        self::assertStringContainsString('#[ProtoField(1)]', $result);
        self::assertStringContainsString('#[ProtoField(2)]', $result);
    }

    public function testFormatPreservation(): void
    {
        $code = <<<'PHP'
<?php

declare(strict_types=1);

class UserService
{
    // This comment should be preserved
    public function getUser(int $userId)
    {
        // Implementation
    }
}
PHP;
        $injector = new AttributeInjector();
        $result = $injector->injectCode(
            $code,
            ['getUser' => ['params' => ['userId' => 1], 'returnType' => null]],
            [],
        );

        // Comment should be preserved
        self::assertStringContainsString('This comment should be preserved', $result);
        // Method body should be preserved
        self::assertStringContainsString('Implementation', $result);
    }
}
