<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final readonly class DockerInspect implements Command
{
    use LineBuilder;

    private function __construct(private ContainerId $id)
    {
    }

    public static function from(ContainerId $id): DockerInspect
    {
        return new DockerInspect(id: $id);
    }

    public function toCommandLine(): string
    {
        return $this->buildFrom(template: 'docker inspect %s', values: [$this->id->value]);
    }
}
