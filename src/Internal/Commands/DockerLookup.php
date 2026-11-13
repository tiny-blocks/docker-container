<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\Name;

final readonly class DockerLookup implements DockerCommand
{
    private function __construct(private Name $name)
    {
    }

    public static function from(Name $name): DockerLookup
    {
        return new DockerLookup(name: $name);
    }

    public function toArguments(): array
    {
        return ['docker', 'inspect', '--type', 'container', $this->name->value];
    }
}
