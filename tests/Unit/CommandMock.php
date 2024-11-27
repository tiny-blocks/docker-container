<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\Internal\Commands\Command as CommandInterface;
use TinyBlocks\DockerContainer\Internal\Commands\CommandLineBuilder;

final readonly class CommandMock implements CommandInterface
{
    use CommandLineBuilder;

    public function __construct(public array $command)
    {
    }

    public function toCommandLine(): string
    {
        return $this->buildCommand(template: 'echo %s', values: $this->command);
    }
}
