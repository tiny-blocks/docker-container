<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Containers\ContainerId;
use TinyBlocks\DockerContainer\Internal\Exceptions\StopTimeoutOutOfRange;

final readonly class DockerStop implements CommandWithTimeout
{
    private const int PROCESS_TIMEOUT_BUFFER_IN_WHOLE_SECONDS = 10;

    private function __construct(private ContainerId $id, private int $gracefulTimeoutInWholeSeconds)
    {
    }

    public static function from(ContainerId $id, int $timeoutInWholeSeconds): DockerStop
    {
        if ($timeoutInWholeSeconds < 0) {
            throw new StopTimeoutOutOfRange(timeoutInWholeSeconds: $timeoutInWholeSeconds);
        }

        return new DockerStop(id: $id, gracefulTimeoutInWholeSeconds: $timeoutInWholeSeconds);
    }

    public function toArguments(): array
    {
        return ['docker', 'stop', '--time', (string)$this->gracefulTimeoutInWholeSeconds, $this->id->value];
    }

    public function getTimeoutInWholeSeconds(): int
    {
        return $this->gracefulTimeoutInWholeSeconds + self::PROCESS_TIMEOUT_BUFFER_IN_WHOLE_SECONDS;
    }
}
