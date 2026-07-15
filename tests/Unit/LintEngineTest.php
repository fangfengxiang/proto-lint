<?php

declare(strict_types=1);

namespace PhpProtoLint\Tests\Unit;

use PhpProtoLint\Domain\DataType;
use PhpProtoLint\Domain\FieldInfo;
use PhpProtoLint\Domain\MessageInfo;
use PhpProtoLint\Domain\MethodInfo;
use PhpProtoLint\Domain\PhpMetadata;
use PhpProtoLint\Domain\ProtoMetadata;
use PhpProtoLint\Domain\ServiceInfo;
use PhpProtoLint\Linter\LintEngine;
use PHPUnit\Framework\TestCase;

/**
 * Task 8.5: LintEngine unit tests.
 * Tests Rule-01 positional alignment, Rule-02 cascade recursion, Rule-03 strict type.
 */
final class LintEngineTest extends TestCase
{
    public function testAllRulesPassWhenAligned(): void
    {
        $protoField1 = new FieldInfo('user_id', 1, 1, DataType::SCALAR);
        $protoField2 = new FieldInfo('name', 2, 2, DataType::SCALAR);

        $protoMethod = new MethodInfo('updateUser', [$protoField1, $protoField2], 'bool', 'UpdateUserRequest');
        $protoService = new ServiceInfo('UserService', [$protoMethod]);

        $protoMessage = new MessageInfo('UpdateUserRequest', [$protoField1, $protoField2]);
        $protoMetadata = new ProtoMetadata([$protoService], ['UpdateUserRequest' => $protoMessage]);

        $phpField1 = new FieldInfo('user_id', 1, 1, DataType::SCALAR, null, 'int');
        $phpField2 = new FieldInfo('name', 2, 2, DataType::SCALAR, null, 'string');
        $phpMethod = new MethodInfo('updateUser', [$phpField1, $phpField2], 'bool');
        $phpService = new ServiceInfo('UserService', [$phpMethod]);
        $phpMetadata = new PhpMetadata([$phpService], []);

        $engine = new LintEngine();
        $report = $engine->check($protoMetadata, $phpMetadata);

        self::assertFalse($report->hasErrors(), 'Should have no errors: ' . $report->format());
    }

    public function testRule01FailsOnPositionMismatch(): void
    {
        $protoField1 = new FieldInfo('user_id', 1, 1, DataType::SCALAR);
        $protoField2 = new FieldInfo('name', 2, 2, DataType::SCALAR);

        $protoMethod = new MethodInfo('updateUser', [$protoField1, $protoField2], 'bool', 'UpdateUserRequest');
        $protoService = new ServiceInfo('UserService', [$protoMethod]);

        $protoMessage = new MessageInfo('UpdateUserRequest', [$protoField1, $protoField2]);
        $protoMetadata = new ProtoMetadata([$protoService], ['UpdateUserRequest' => $protoMessage]);

        $phpField1 = new FieldInfo('user_id', 1, 2, DataType::SCALAR, null, 'int');
        $phpField2 = new FieldInfo('name', 2, 1, DataType::SCALAR, null, 'string');
        $phpMethod = new MethodInfo('updateUser', [$phpField1, $phpField2], 'bool');
        $phpService = new ServiceInfo('UserService', [$phpMethod]);
        $phpMetadata = new PhpMetadata([$phpService], []);

        $engine = new LintEngine();
        $report = $engine->check($protoMetadata, $phpMetadata);

        self::assertTrue($report->hasErrors());
    }

    public function testRule03FailsOnMixedType(): void
    {
        $protoField = new FieldInfo('data', 1, 1, DataType::SCALAR);
        $protoMethod = new MethodInfo('test', [$protoField], 'void', 'TestRequest');
        $protoService = new ServiceInfo('TestService', [$protoMethod]);
        $protoMessage = new MessageInfo('TestRequest', [$protoField]);
        $protoMetadata = new ProtoMetadata([$protoService], ['TestRequest' => $protoMessage]);

        $phpField = new FieldInfo('data', 1, 1, DataType::SCALAR, null, 'mixed');
        $phpMethod = new MethodInfo('test', [$phpField], 'void');
        $phpService = new ServiceInfo('TestService', [$phpMethod]);
        $phpMetadata = new PhpMetadata([$phpService], []);

        $engine = new LintEngine();
        $report = $engine->check($protoMetadata, $phpMetadata);

        self::assertTrue($report->hasErrors());
    }

    public function testRule03OverrideToWarning(): void
    {
        $protoField = new FieldInfo('data', 1, 1, DataType::SCALAR);
        $protoMethod = new MethodInfo('test', [$protoField], 'void', 'TestRequest');
        $protoService = new ServiceInfo('TestService', [$protoMethod]);
        $protoMessage = new MessageInfo('TestRequest', [$protoField]);
        $protoMetadata = new ProtoMetadata([$protoService], ['TestRequest' => $protoMessage]);

        $phpField = new FieldInfo('data', 1, 1, DataType::SCALAR, null, 'mixed');
        $phpMethod = new MethodInfo('test', [$phpField], 'void');
        $phpService = new ServiceInfo('TestService', [$phpMethod]);
        $phpMetadata = new PhpMetadata([$phpService], []);

        $engine = new LintEngine([
            'TestService' => ['test' => ['rule_03' => 'warning']],
        ]);
        $report = $engine->check($protoMetadata, $phpMetadata);

        self::assertFalse($report->hasErrors(), 'Should not have errors with override');
        self::assertTrue($report->hasWarnings(), 'Should have warnings with override');
    }

    public function testCaseInsensitiveMethodMatching(): void
    {
        $protoMethod = new MethodInfo('GetUser', [], 'GetUserResponse', 'GetUserRequest');
        $protoService = new ServiceInfo('UserService', [$protoMethod]);
        $protoMetadata = new ProtoMetadata([$protoService], []);

        $phpMethod = new MethodInfo('getUser', [], 'GetUserResponse');
        $phpService = new ServiceInfo('UserService', [$phpMethod]);
        $phpMetadata = new PhpMetadata([$phpService], []);

        $engine = new LintEngine();
        $report = $engine->check($protoMetadata, $phpMetadata);

        $notFoundErrors = array_filter(
            $report->getErrors(),
            fn($r) => str_contains($r->message, 'not found'),
        );
        self::assertEmpty($notFoundErrors, 'Should match GetUser to getUser case-insensitively');
    }
}
