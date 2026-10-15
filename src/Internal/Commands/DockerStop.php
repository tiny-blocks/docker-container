<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommandWithTimeout;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerId;
use TinyBlocks\Time\Duration;

final readonly class DockerStop implements DockerCommandWithTimeout
{
    private const int PROCESS_TIMEOUT_BUFFER_IN_SECONDS = 10;

    private function __construct(private ContainerId $id, private Duration $gracePeriod)
    {
    }

    public static function from(ContainerId $id, Duration $gracePeriod): DockerStop
    {
        return new DockerStop(id: $id, gracePeriod: $gracePeriod);
    }

    public function timeout(): Duration
    {
        return $this->gracePeriod->plus(other: Duration::fromSeconds(seconds: self::PROCESS_TIMEOUT_BUFFER_IN_SECONDS));
    }

    public function toArguments(): array
    {
        return ['docker', 'stop', '--time', (string)$this->gracePeriod->toSeconds(), $this->id->value];
    }
}
