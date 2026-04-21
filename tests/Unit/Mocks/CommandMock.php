<?php

declare(strict_types=1);

namespace Test\Unit\Mocks;

use TinyBlocks\DockerContainer\Internal\Commands\Command;

final readonly class CommandMock implements Command
{
    public function __construct(private string $command)
    {
    }

    public function toArguments(): array
    {
        return explode(' ', $this->command);
    }
}
