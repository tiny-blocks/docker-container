<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\DockerCommand;

final readonly class CommandMock implements DockerCommand
{
    public function __construct(private string $command)
    {
    }

    public function toArguments(): array
    {
        return explode(' ', $this->command);
    }
}
