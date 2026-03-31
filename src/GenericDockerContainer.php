<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Internal\Client\DockerClient;
use TinyBlocks\DockerContainer\Internal\CommandHandler\CommandHandler;
use TinyBlocks\DockerContainer\Internal\CommandHandler\ContainerCommandHandler;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Waits\ContainerWaitAfterStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitBeforeStarted;

class GenericDockerContainer implements DockerContainer
{
    protected ContainerDefinition $definition;

    private ?ContainerWaitBeforeStarted $waitBeforeStarted = null;

    private CommandHandler $commandHandler;

    protected function __construct(ContainerDefinition $definition, CommandHandler $commandHandler)
    {
        $this->definition = $definition;
        $this->commandHandler = $commandHandler;
    }

    public static function from(string $image, ?string $name = null): static
    {
        $definition = ContainerDefinition::create(image: $image, name: $name);
        $commandHandler = new ContainerCommandHandler(client: new DockerClient());

        return new static(definition: $definition, commandHandler: $commandHandler);
    }

    public function run(array $commands = [], ?ContainerWaitAfterStarted $waitAfterStarted = null): ContainerStarted
    {
        $this->waitBeforeStarted?->waitBefore();

        $dockerRun = DockerRun::from(definition: $this->definition, commands: $commands);
        $containerStarted = $this->commandHandler->run(dockerRun: $dockerRun);

        $waitAfterStarted?->waitAfter(containerStarted: $containerStarted);

        return $containerStarted;
    }

    public function runIfNotExists(
        array $commands = [],
        ?ContainerWaitAfterStarted $waitAfterStarted = null
    ): ContainerStarted {
        $existing = $this->commandHandler->findBy(definition: $this->definition);

        if ($existing !== null) {
            return $existing;
        }

        return $this->run(commands: $commands, waitAfterStarted: $waitAfterStarted);
    }

    public function copyToContainer(string $pathOnHost, string $pathOnContainer): static
    {
        $this->definition = $this->definition->withCopyInstruction(
            pathOnHost: $pathOnHost,
            pathOnContainer: $pathOnContainer
        );

        return $this;
    }

    public function withNetwork(string $name): static
    {
        $this->definition = $this->definition->withNetwork(name: $name);

        return $this;
    }

    public function withPortMapping(int $portOnHost, int $portOnContainer): static
    {
        $this->definition = $this->definition->withPortMapping(
            portOnHost: $portOnHost,
            portOnContainer: $portOnContainer
        );

        return $this;
    }

    public function withWaitBeforeRun(ContainerWaitBeforeStarted $wait): static
    {
        $this->waitBeforeStarted = $wait;

        return $this;
    }

    public function withoutAutoRemove(): static
    {
        $this->definition = $this->definition->withoutAutoRemove();

        return $this;
    }

    public function withVolumeMapping(string $pathOnHost, string $pathOnContainer): static
    {
        $this->definition = $this->definition->withVolumeMapping(
            pathOnHost: $pathOnHost,
            pathOnContainer: $pathOnContainer
        );

        return $this;
    }

    public function withEnvironmentVariable(string $key, string $value): static
    {
        $this->definition = $this->definition->withEnvironmentVariable(key: $key, value: $value);

        return $this;
    }
}
