<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerId;

final readonly class DockerRemove implements DockerCommand
{
    private function __construct(private ContainerId $id)
    {
    }

    public static function from(ContainerId $id): DockerRemove
    {
        return new DockerRemove(id: $id);
    }

    public function toArguments(): array
    {
        return ['docker', 'rm', '--force', '--volumes', $this->id->value];
    }
}
