<?php

declare(strict_types=1);

namespace Test\Unit\Mocks;

use TinyBlocks\DockerContainer\Internal\Commands\CommandWithTimeout;

final readonly class CommandWithTimeoutMock implements CommandWithTimeout
{
    public function __construct(private string $command, private int $timeoutInWholeSeconds)
    {
    }

    public function toArguments(): array
    {
        return explode(' ', $this->command);
    }

    public function getTimeoutInWholeSeconds(): int
    {
        return $this->timeoutInWholeSeconds;
    }
}
