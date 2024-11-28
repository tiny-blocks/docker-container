<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Factories;

use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Commands\DockerInspect;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final readonly class ContainerFactory
{
    private AddressFactory $addressFactory;

    private EnvironmentVariablesFactory $variablesFactory;

    public function __construct(private Client $client)
    {
        $this->addressFactory = new AddressFactory();
        $this->variablesFactory = new EnvironmentVariablesFactory();
    }

    public function buildFrom(ContainerId $id, Container $container): Container
    {
        $dockerInspect = DockerInspect::fromId(id: $id);
        $executionCompleted = $this->client->execute(command: $dockerInspect);

        $data = (array)json_decode($executionCompleted->getOutput(), true)[0];

        return Container::from(
            id: $id,
            name: $container->name,
            image: $container->image,
            address: $this->addressFactory->buildFrom(data: $data),
            environmentVariables: $this->variablesFactory->buildFrom(data: $data)
        );
    }
}
