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

        $portArguments = $this->definition->portMappings->reduce(
            accumulator: static fn(array $carry, PortMapping $port): array => [...$carry, ...$port->toArguments()],
            initial: []
        );
        $arguments = [...$arguments, ...$portArguments];

        if (!is_null($this->definition->network)) {
            $template = '--network=%s';
            $arguments[] = sprintf($template, $this->definition->network);
        }

        $volumeArguments = $this->definition->volumeMappings->reduce(
            accumulator: static fn(array $carry, VolumeMapping $volume): array => [
                ...$carry,
                ...$volume->toArguments()
            ],
            initial: []
        );
        $arguments = [...$arguments, ...$volumeArguments];

        $arguments[] = '--detach';

        if ($this->definition->autoRemove) {
            $arguments[] = '--rm';
        }

        $environmentArguments = $this->definition->environmentVariables->reduce(
            accumulator: static fn(array $carry, EnvironmentVariable $environment): array => [
                ...$carry,
                ...$environment->toArguments()
            ],
            initial: []
        );
        $arguments = [...$arguments, ...$environmentArguments];

        $arguments[] = $this->definition->image->name;

        return [...$arguments, ...$this->commands->toArray()];
    }
}
