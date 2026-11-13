<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\Name;

final readonly class DockerExecute implements DockerCommand
{
    private function __construct(private Name $name, private array $commands)
    {
    }

    public static function from(Name $name, array $commands): DockerExecute
    {
        return new DockerExecute(name: $name, commands: $commands);
    }

    public function toArguments(): array
    {
        return ['docker', 'exec', $this->name->value, ...$this->commands];
    }
}
