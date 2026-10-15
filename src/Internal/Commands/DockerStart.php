<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerId;

final readonly class DockerStart implements DockerCommand
{
    private function __construct(private ContainerId $id)
    {
    }

    public static function from(ContainerId $id): DockerStart
    {
        return new DockerStart(id: $id);
    }

    public function toArguments(): array
    {
        return ['docker', 'start', $this->id->value];
    }
}
