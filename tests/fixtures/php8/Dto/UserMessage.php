<?php

declare(strict_types=1);

namespace ProtoLint\Test\Fixture\Dto;

class UserMessage
{
    #[\ProtoField(1)]
    public int $id;

    #[\ProtoField(2)]
    public string $name;

    #[\ProtoField(3)]
    public string $email;
}
