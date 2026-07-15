<?php

declare(strict_types=1);

namespace PhpProtoLint\Tests\Unit;

use PhpProtoLint\Injector\DelimiterSandboxInjector;
use PHPUnit\Framework\TestCase;

/**
 * Task 8.8: DelimiterSandboxInjector unit tests.
 * Tests delimiter region identification, in-region overwrite, external isolation.
 */
final class DelimiterSandboxInjectorTest extends TestCase
{
    private const START = '// --- proto-auto-generated-start ---';
    private const END = '// --- proto-auto-generated-end ---';

    public function testInjectIntoExistingDelimiters(): void
    {
        $code = <<<'PHP'
<?php
class UserService {
    /**
     * @author Architect.Li
     * // --- proto-auto-generated-start ---
     * @proto-field $oldField 99
     * // --- proto-auto-generated-end ---
     */
    public function updateUser(int $userId) {}
}
PHP;
        $injector = new DelimiterSandboxInjector();
        $result = $injector->injectCode(
            $code,
            ['updateUser' => ['params' => ['userId' => 1], 'returnType' => null]],
            [],
        );

        // New annotation should be present
        self::assertStringContainsString('@proto-field $userId 1', $result);
        // Old annotation should be replaced
        self::assertStringNotContainsString('@proto-field $oldField 99', $result);
    }

    public function testPreserveExternalComments(): void
    {
        $code = <<<'PHP'
<?php
class UserService {
    /**
     * @author Architect.Li
     * Business logic: validate user permissions
     */
    public function updateUser(int $userId) {}
}
PHP;
        $injector = new DelimiterSandboxInjector();
        $result = $injector->injectCode(
            $code,
            ['updateUser' => ['params' => ['userId' => 1], 'returnType' => null]],
            [],
        );

        // External comments should be preserved
        self::assertStringContainsString('@author Architect.Li', $result);
        self::assertStringContainsString('Business logic', $result);
        // Delimiters should be auto-inserted
        self::assertStringContainsString(self::START, $result);
        self::assertStringContainsString(self::END, $result);
    }

    public function testAutoInsertDelimitersWhenMissing(): void
    {
        $code = <<<'PHP'
<?php
class UserService {
    public function getUser(int $userId) {}
}
PHP;
        $injector = new DelimiterSandboxInjector();
        $result = $injector->injectCode(
            $code,
            ['getUser' => ['params' => ['userId' => 1], 'returnType' => null]],
            [],
        );

        self::assertStringContainsString(self::START, $result);
        self::assertStringContainsString(self::END, $result);
        self::assertStringContainsString('@proto-method', $result);
        self::assertStringContainsString('@proto-field $userId 1', $result);
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
        $injector = new DelimiterSandboxInjector();
        $result = $injector->injectCode(
            $code,
            [],
            ['id' => 1, 'name' => 2],
        );

        self::assertStringContainsString('@proto-field $id 1', $result);
        self::assertStringContainsString('@proto-field $name 2', $result);
    }
}
