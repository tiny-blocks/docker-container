<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;

final readonly class DockerCreate implements DockerCommand
{
    public const string MANAGED_LABEL = 'tiny-blocks.docker-container=true';

    private function __construct(private Collection $commands, private ContainerDefinition $definition)
    {
    }

    public static function from(array $commands, ContainerDefinition $definition): DockerCreate
    {
        return new DockerCreate(commands: Collection::createFrom(elements: $commands), definition: $definition);
    }

    public function toArguments(): array
    {
        $name = $this->definition->name->value;

        return [
            'docker',
            'create',
            '--user',
            'root',
            '--name',
            $name,
            '--hostname',
            $name,
            '--label',
            self::MANAGED_LABEL,
            ...$this->definition->toArguments(),
            $this->definition->image->name,
            ...$this->commands->toArray()
        ];
    }
}
