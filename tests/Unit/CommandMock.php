<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\Internal\Commands\Command as CommandInterface;
use TinyBlocks\DockerContainer\Internal\Commands\LineBuilder;

final readonly class CommandMock implements CommandInterface
{
    use LineBuilder;

    public function __construct(public array $command)
    {
    }

    public function toCommandLine(): string
    {
        return $this->buildFrom(template: 'echo %s', values: $this->command);
    }
}
