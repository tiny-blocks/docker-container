<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommandWithTimeout;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerId;
use TinyBlocks\Time\Duration;

final readonly class DockerStartAttached implements DockerCommandWithTimeout
{
    private function __construct(private ContainerId $id, private Duration $timeout)
    {
    }

    public static function from(ContainerId $id, Duration $timeout): DockerStartAttached
    {
        return new DockerStartAttached(id: $id, timeout: $timeout);
    }

    public function timeout(): Duration
    {
        return $this->timeout;
    }

    public function toArguments(): array
    {
        return ['docker', 'start', '--attach', $this->id->value];
    }
}
