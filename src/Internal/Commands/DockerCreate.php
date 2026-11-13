<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\DockerContainer\DockerCommandWithTimeout;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\Time\Duration;

final readonly class DockerCreate implements DockerCommandWithTimeout
{
    public const string MANAGED_LABEL = 'tiny-blocks.docker-container=true';

    private function __construct(private array $commands, private ContainerDefinition $definition)
    {
    }

    public static function from(array $commands, ContainerDefinition $definition): DockerCreate
    {
        return new DockerCreate(commands: $commands, definition: $definition);
    }

    public function timeout(): Duration
    {
        return $this->definition->pullTimeout;
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
            ...$this->commands
        ];
    }
}
