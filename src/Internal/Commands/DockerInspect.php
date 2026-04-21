<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final readonly class DockerInspect implements Command
{
    private function __construct(private ContainerId $id)
    {
    }

    public static function from(ContainerId $id): DockerInspect
    {
        return new DockerInspect(id: $id);
    }

    public function toArguments(): array
    {
        return ['docker', 'inspect', $this->id->value];
    }
}
