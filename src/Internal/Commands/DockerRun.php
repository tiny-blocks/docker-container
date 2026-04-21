<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\EnvironmentVariable;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\PortMapping;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\VolumeMapping;

final readonly class DockerRun implements Command
{
    public const string MANAGED_LABEL = 'tiny-blocks.docker-container=true';

    private function __construct(private Collection $commands, public ContainerDefinition $definition)
    {
    }

    public static function from(ContainerDefinition $definition, array $commands = []): DockerRun
    {
        return new DockerRun(commands: Collection::createFrom(elements: $commands), definition: $definition);
    }

    public function toArguments(): array
    {
        $name = $this->definition->name->value;

        $arguments = [
            'docker',
            'run',
            '--user',
            'root',
            '--name',
            $name,
            '--hostname',
            $name,
            '--label',
            self::MANAGED_LABEL
        ];

        foreach ($this->definition->portMappings as $port) {
            /** @var PortMapping $port */
            $arguments = [...$arguments, ...$port->toArguments()];
        }

        if (!is_null($this->definition->network)) {
            $arguments[] = sprintf('--network=%s', $this->definition->network);
        }

        foreach ($this->definition->volumeMappings as $volume) {
            /** @var VolumeMapping $volume */
            $arguments = [...$arguments, ...$volume->toArguments()];
        }

        $arguments[] = '--detach';

        if ($this->definition->autoRemove) {
            $arguments[] = '--rm';
        }

        foreach ($this->definition->environmentVariables as $environment) {
            /** @var EnvironmentVariable $environment */
            $arguments = [...$arguments, ...$environment->toArguments()];
        }

        $arguments[] = $this->definition->image->name;

        return [...$arguments, ...$this->commands->toArray()];
    }
}
