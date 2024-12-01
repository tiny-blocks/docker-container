<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final readonly class DockerLogs implements Command
{
    use LineBuilder;

    private function __construct(private ContainerId $id)
    {
    }

    public static function from(ContainerId $id): DockerLogs
    {
        return new DockerLogs(id: $id);
    }

    public function toCommandLine(): string
    {
        return $this->buildFrom(template: 'docker logs %s', values: [$this->id->value]);
    }
}
