<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Container\Models\ContainerId;

final readonly class DockerInspect implements Command
{
    use CommandLineBuilder;

    private function __construct(private ContainerId $id)
    {
    }

    public static function from(ContainerId $id): DockerInspect
    {
        return new DockerInspect(id: $id);
    }

    public function toCommandLine(): string
    {
        return $this->buildCommand(template: 'docker inspect %s', values: [$this->id->value]);
    }
}
