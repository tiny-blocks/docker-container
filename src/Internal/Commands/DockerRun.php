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

    public function toCommandLine(): string
    {
        $name = $this->definition->name->value;

        $parts = Collection::createFrom(elements: [
            'docker run --user root',
            sprintf('--name %s', $name),
            sprintf('--hostname %s', $name),
            sprintf('--label %s', self::MANAGED_LABEL)
        ]);

        $parts = $parts->merge(
            other: $this->definition->portMappings->map(
                transformations: static fn(PortMapping $port): string => $port->toArgument()
            )
        );

        if (!is_null($this->definition->network)) {
            $parts = $parts->add(sprintf('--network=%s', $this->definition->network));
        }

        $parts = $parts->merge(
            other: $this->definition->volumeMappings->map(
                transformations: static fn(VolumeMapping $volume): string => $volume->toArgument()
            )
        );

        $parts = $parts->add('--detach');

        if ($this->definition->autoRemove) {
            $parts = $parts->add('--rm');
        }

        $parts = $parts->merge(
            other: $this->definition->environmentVariables->map(
                transformations: static fn(EnvironmentVariable $environment): string => $environment->toArgument()
            )
        );

        $parts = $parts->add($this->definition->image->name);

        $commandString = $this->commands->joinToString(separator: ' ');

        if (!empty($commandString)) {
            $parts = $parts->add($commandString);
        }

        return trim($parts->joinToString(separator: ' '));
    }
}
