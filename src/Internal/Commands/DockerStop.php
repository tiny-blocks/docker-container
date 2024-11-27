<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Container\Models\ContainerId;

final readonly class DockerStop implements CommandWithTimeout
{
    use CommandLineBuilder;

    private function __construct(private ContainerId $id, private int $timeoutInWholeSeconds)
    {
    }

    public static function from(ContainerId $id, int $timeoutInWholeSeconds): DockerStop
    {
        return new DockerStop(id: $id, timeoutInWholeSeconds: $timeoutInWholeSeconds);
    }

    public function toCommandLine(): string
    {
        return $this->buildCommand(template: 'docker stop %s', values: [$this->id->value]);
    }

    public function getTimeoutInWholeSeconds(): int
    {
        return $this->timeoutInWholeSeconds;
    }
}
