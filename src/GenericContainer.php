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
use TinyBlocks\DockerContainer\Internal\ContainerHandler;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;
use TinyBlocks\DockerContainer\Internal\Containers\Started;
use TinyBlocks\DockerContainer\Waits\ContainerWait;

class GenericContainer implements DockerContainer
{
    private ?ContainerWait $wait = null;

    private ?PortOption $port = null;

    private ?NetworkOption $network = null;

    private bool $autoRemove = true;

    private CommandOptions $items;

    private CommandOptions $volumes;

    private ContainerHandler $containerHandler;

    private CommandOptions $environmentVariables;

    private function __construct(private readonly Container $container)
    {
        $this->items = CommandOptions::createFromEmpty();
        $this->volumes = CommandOptions::createFromEmpty();
        $this->environmentVariables = CommandOptions::createFromEmpty();

        $this->containerHandler = new ContainerHandler(client: new DockerClient());
    }

    public static function from(string $image, ?string $name = null): static
    {
        $container = Container::create(name: $name, image: $image);

        return new static(container: $container);
    }

    public function run(array $commandsOnRun = []): ContainerStarted
    {
        $this->wait?->wait();

        $dockerRun = DockerRun::from(
            commands: $commandsOnRun,
            container: $this->container,
            port: $this->port,
            network: $this->network,
            volumes: $this->volumes,
            detached: SimpleCommandOption::DETACH,
            autoRemove: $this->autoRemove ? SimpleCommandOption::REMOVE : null,
            environmentVariables: $this->environmentVariables
        );

        $container = $this->containerHandler->run(command: $dockerRun);

        $this->items->each(
            actions: function (VolumeOption $volume) use ($container) {
                $item = ItemToCopyOption::from(id: $container->id, volume: $volume);
                $dockerCopy = DockerCopy::from(item: $item);
                $this->containerHandler->execute(command: $dockerCopy);
            }
        );

        return new Started(container: $container, containerHandler: $this->containerHandler);
    }

    public function runIfNotExists(array $commandsOnRun = []): ContainerStarted
    {
        $dockerList = DockerList::from(container: $this->container);
        $container = $this->containerHandler->findBy(command: $dockerList);

        if ($container->hasId()) {
            return new Started(container: $container, containerHandler: $this->containerHandler);
        }

        return $this->run(commandsOnRun: $commandsOnRun);
    }

    public function copyToContainer(string $pathOnHost, string $pathOnContainer): static
    {
        $volume = VolumeOption::from(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer);
        $this->items->add(elements: $volume);

        return $this;
    }

    public function withWait(ContainerWait $wait): static
    {
        $this->wait = $wait;

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
