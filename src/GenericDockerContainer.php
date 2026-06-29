<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use Closure;
use TinyBlocks\DockerContainer\Internal\Client\DockerClient;
use TinyBlocks\DockerContainer\Internal\CommandHandler\CommandHandler;
use TinyBlocks\DockerContainer\Internal\CommandHandler\ContainerCommandHandler;
use TinyBlocks\DockerContainer\Internal\Commands\DockerPull;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerReaper;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Containers\RegisteredShutdownHook;
use TinyBlocks\DockerContainer\Internal\Containers\Reused;
use TinyBlocks\DockerContainer\Waits\ContainerWaitAfterStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitBeforeStarted;

class GenericDockerContainer implements DockerContainer
{
    private ?ContainerWaitBeforeStarted $waitBeforeStarted = null;

    protected function __construct(
        private readonly ContainerReaper $reaper,
        protected ContainerDefinition $definition,
        private readonly CommandHandler $commandHandler
    ) {
    }

    public static function from(string $image, ?string $name = null): static
    {
        $client = new DockerClient();
        $definition = ContainerDefinition::create(image: $image, name: $name);
        $reaper = new ContainerReaper(client: $client);
        $commandHandler = new ContainerCommandHandler(client: $client, shutdownHook: new RegisteredShutdownHook());

        return new static(reaper: $reaper, definition: $definition, commandHandler: $commandHandler);
    }

    public function run(array $commands = [], ?ContainerWaitAfterStarted $waitAfterStarted = null): ContainerStarted
    {
        $this->waitBeforeStarted?->waitBefore();

        $dockerRun = DockerRun::from(definition: $this->definition, commands: $commands);
        $containerStarted = $this->commandHandler->run(dockerRun: $dockerRun);

        $waitAfterStarted?->waitAfter(containerStarted: $containerStarted);

        return $containerStarted;
    }

    public function runWhen(
        Closure $gate,
        Closure $then,
        array $commands = [],
        ?ContainerWaitAfterStarted $waitAfterStarted = null
    ): void {
        if (!$gate()) {
            return;
        }

        $then($this->runIfNotExists(commands: $commands, waitAfterStarted: $waitAfterStarted));
    }

    public function pullImage(): static
    {
        $this->commandHandler->execute(command: DockerPull::from(image: $this->definition->image->name));

        return $this;
    }

    public function withNetwork(string $name): static
    {
        $this->definition = $this->definition->withNetwork(name: $name);

        return $this;
    }

    public function runIfNotExists(
        array $commands = [],
        ?ContainerWaitAfterStarted $waitAfterStarted = null
    ): ContainerStarted {
        $existing = $this->commandHandler->findBy(definition: $this->definition);

        if (!is_null($existing)) {
            return new Reused(reaper: $this->reaper, wasReused: true, containerStarted: $existing);
        }

        return new Reused(
            reaper: $this->reaper,
            wasReused: false,
            containerStarted: $this->run(commands: $commands, waitAfterStarted: $waitAfterStarted)
        );
    }

    public function copyToContainer(string $pathOnHost, string $pathOnContainer): static
    {
        $this->definition = $this->definition->withCopyInstruction(
            pathOnHost: $pathOnHost,
            pathOnContainer: $pathOnContainer
        );

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

    public function withVolumeMapping(string $pathOnHost, string $pathOnContainer): static
    {
        $this->definition = $this->definition->withVolumeMapping(
            pathOnHost: $pathOnHost,
            pathOnContainer: $pathOnContainer
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

    public function withEnvironmentVariable(string $key, string $value): static
    {
        $this->definition = $this->definition->withEnvironmentVariable(key: $key, value: $value);

        return $this;
    }
}
