<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\DockerCommandWithTimeout;
use TinyBlocks\Time\Duration;

final readonly class CommandWithTimeoutMock implements DockerCommandWithTimeout
{
    public function __construct(private string $command, private Duration $timeout)
    {
    }

    public function timeout(): Duration
    {
        return $this->timeout;
    }

    public function toArguments(): array
    {
        return explode(' ', $this->command);
    }
}
