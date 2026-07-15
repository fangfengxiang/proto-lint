<?php

declare(strict_types=1);

namespace PhpProtoLint\Console;

use Symfony\Component\Console\Application as SymfonyApplication;

final class Application extends SymfonyApplication
{
    public const VERSION = '0.1.0';

    public function __construct()
    {
        parent::__construct('php-proto-lint', self::VERSION);

        $this->add(new CheckCommand());
        $this->add(new ShadowLintCommand());
        $this->add(new InjectAttributesCommand());
    }
}
