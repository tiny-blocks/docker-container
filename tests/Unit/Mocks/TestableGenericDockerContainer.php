<?php

declare(strict_types=1);

namespace Test\Unit\Mocks;

use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\CommandHandler\ContainerCommandHandler;
use TinyBlocks\DockerContainer\Internal\Containers\Definitions\ContainerDefinition;

final class TestableGenericDockerContainer extends GenericDockerContainer
{
    public static function createWith(string $image, ?string $name, Client $client): static
    {
        $definition = ContainerDefinition::create(image: $image, name: $name);
        $commandHandler = new ContainerCommandHandler(client: $client);

        return new static(definition: $definition, commandHandler: $commandHandler);
    }
}
