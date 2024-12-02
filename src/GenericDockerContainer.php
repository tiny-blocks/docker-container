<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Internal\Client\DockerClient;
use TinyBlocks\DockerContainer\Internal\Commands\DockerCopy;
use TinyBlocks\DockerContainer\Internal\Commands\DockerList;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRun;
use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOptions;
use TinyBlocks\DockerContainer\Internal\Commands\Options\EnvironmentVariableOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\ItemToCopyOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\NetworkOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\PortOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\SimpleCommandOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\VolumeOption;
use TinyBlocks\DockerContainer\Internal\ContainerCommandHandler;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;
use TinyBlocks\DockerContainer\Internal\Containers\Started;
use TinyBlocks\DockerContainer\Waits\ContainerWaitAfterStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitBeforeStarted;

class GenericDockerContainer implements DockerContainer
{
    private ?PortOption $port = null;

    private CommandOptions $items;

    private ?NetworkOption $network = null;

    private CommandOptions $volumes;

    private bool $autoRemove = true;

    private ContainerCommandHandler $commandHandler;

    private ?ContainerWaitBeforeStarted $waitBeforeStarted = null;

    private CommandOptions $environmentVariables;

    private function __construct(private readonly Container $container)
    {
        $this->items = CommandOptions::createFromEmpty();
        $this->volumes = CommandOptions::createFromEmpty();
        $this->environmentVariables = CommandOptions::createFromEmpty();

        $this->commandHandler = new ContainerCommandHandler(client: new DockerClient());
    }

    public static function from(string $image, ?string $name = null): static
    {
        $container = Container::create(name: $name, image: $image);

        return new static(container: $container);
    }

    public function run(array $commands = [], ?ContainerWaitAfterStarted $waitAfterStarted = null): ContainerStarted
    {
        $this->waitBeforeStarted?->waitBefore();

        $dockerRun = DockerRun::from(
            commands: $commands,
            container: $this->container,
            port: $this->port,
            network: $this->network,
            volumes: $this->volumes,
            detached: SimpleCommandOption::DETACH,
            autoRemove: $this->autoRemove ? SimpleCommandOption::REMOVE : null,
            environmentVariables: $this->environmentVariables
        );

        $container = $this->commandHandler->run(dockerRun: $dockerRun);

        $this->items->each(
            actions: function (VolumeOption $volume) use ($container) {
                $item = ItemToCopyOption::from(id: $container->id, volume: $volume);
                $dockerCopy = DockerCopy::from(item: $item);
                $this->commandHandler->execute(command: $dockerCopy);
            }
        );

        $containerStarted = new Started(container: $container, commandHandler: $this->commandHandler);
        $waitAfterStarted?->waitAfter(containerStarted: $containerStarted);

        return $containerStarted;
    }

    public function runIfNotExists(
        array $commands = [],
        ?ContainerWaitAfterStarted $waitAfterStarted = null
    ): ContainerStarted {
        $dockerList = DockerList::from(container: $this->container);
        $container = $this->commandHandler->findBy(dockerList: $dockerList);

        if ($container->hasId()) {
            return new Started(container: $container, commandHandler: $this->commandHandler);
        }

        return $this->run(commands: $commands, waitAfterStarted: $waitAfterStarted);
    }

    public function copyToContainer(string $pathOnHost, string $pathOnContainer): static
    {
        $volume = VolumeOption::from(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer);
        $this->items->add(elements: $volume);

        return $this;
    }

    public function withNetwork(string $name): static
    {
        $this->network = NetworkOption::from(name: $name);

        return $this;
    }

    public function withPortMapping(int $portOnHost, int $portOnContainer): static
    {
        $this->port = PortOption::from(portOnHost: $portOnHost, portOnContainer: $portOnContainer);

        return $this;
    }

    public function withWaitBeforeRun(ContainerWaitBeforeStarted $wait): static
    {
        $this->waitBeforeStarted = $wait;

        return $this;
    }

    public function withoutAutoRemove(): static
    {
        $this->autoRemove = false;

        return $this;
    }

    public function withVolumeMapping(string $pathOnHost, string $pathOnContainer): static
    {
        $volume = VolumeOption::from(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer);
        $this->volumes->add(elements: $volume);

        return $this;
    }

    public function withEnvironmentVariable(string $key, string $value): static
    {
        $environmentVariable = EnvironmentVariableOption::from(key: $key, value: $value);
        $this->environmentVariables->add(elements: $environmentVariable);

        return $this;
    }
}
