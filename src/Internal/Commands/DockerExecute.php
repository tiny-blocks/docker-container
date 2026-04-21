<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Name;

final readonly class DockerExecute implements Command
{
    private function __construct(private Name $name, private Collection $commands)
    {
    }

    public static function from(Name $name, array $commands): DockerExecute
    {
        return new DockerExecute(name: $name, commands: Collection::createFrom(elements: $commands));
    }

    public function toArguments(): array
    {
        return ['docker', 'exec', $this->name->value, ...$this->commands->toArray()];
    }
}
