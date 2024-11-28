<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final readonly class DockerStop implements CommandWithTimeout
{
    use LineBuilder;

    private function __construct(private ContainerId $id, private int $timeoutInWholeSeconds)
    {
    }

    public static function from(ContainerId $id, int $timeoutInWholeSeconds): DockerStop
    {
        return new DockerStop(id: $id, timeoutInWholeSeconds: $timeoutInWholeSeconds);
    }

    public function toCommandLine(): string
    {
        return $this->buildFrom(template: 'docker stop %s', values: [$this->id->value]);
    }

    public function getTimeoutInWholeSeconds(): int
    {
        return $this->timeoutInWholeSeconds;
    }
}
