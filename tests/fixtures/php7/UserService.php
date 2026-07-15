<?php

declare(strict_types=1);

namespace PhpProtoLint\Test\Fixture\Php7;

class UserService
{
    /**
     * @proto-method
     * @proto-field $userId 1
     * @proto-return GetUserResponse
     */
    public function getUser(int $userId)
    {
    }

    /**
     * @proto-method
     * @proto-field $userId 1
     * @proto-field $name 2
     * @proto-field $data 3
     * @proto-return bool
     */
    public function updateUser(int $userId, string $name, $data)
    {
    }
}
