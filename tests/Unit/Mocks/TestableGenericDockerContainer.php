<?php

declare(strict_types=1);

namespace Test\Unit\Mocks;

use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\CommandHandler\ContainerCommandHandler;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerReaper;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;
use TinyBlocks\DockerContainer\Internal\Containers\ShutdownHook;

final class TestableGenericDockerContainer extends GenericDockerContainer
{
    public static function createWith(
        string $image,
        ?string $name,
        Client $client,
        ?ShutdownHook $shutdownHook = null
    ): static {
        $definition = ContainerDefinition::create(image: $image, name: $name);
        $reaper = new ContainerReaper(client: $client);
        $commandHandler = new ContainerCommandHandler(
            client: $client,
            shutdownHook: $shutdownHook ?? new ShutdownHook()
        );

        return new static(reaper: $reaper, definition: $definition, commandHandler: $commandHandler);
    }
}
