<?php

declare(strict_types=1);

namespace PhpProtoLint\Test\Fixture\Service;

use PhpProtoLint\Test\Fixture\Dto\UserMessage;

class UserService
{
    #[\ProtoMethod]
    public function getUser(
        #[\ProtoField(1)]
        int $userId,
    ): GetUserResponse
    {
        // Implementation
    }

    #[\ProtoMethod]
    public function updateUser(
        #[\ProtoField(1)]
        int $userId,
        #[\ProtoField(2)]
        string $name,
        #[\ProtoField(3)]
        UserMessage $data,
    ): bool {
        // Implementation
    }
}
