<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\Internal\Commands\LineBuilder;
use TinyBlocks\DockerContainer\Internal\Commands\CommandWithTimeout as CommandInterface;

final readonly class CommandWithTimeoutMock implements CommandInterface
{
    use LineBuilder;

    public function __construct(public array $command, public int $timeoutInWholeSeconds)
    {
    }

    public function toCommandLine(): string
    {
        return $this->buildFrom(template: 'echo %s', values: $this->command);
    }

    public function getTimeoutInWholeSeconds(): int
    {
        return $this->timeoutInWholeSeconds;
    }
}
