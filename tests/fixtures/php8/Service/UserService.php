<?php

declare(strict_types=1);

namespace ProtoLint\Test\Fixture\Service;

use ProtoLint\Test\Fixture\Dto\UserMessage;

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
