<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal;

use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Container\Models\Address\Address;
use TinyBlocks\DockerContainer\Internal\Container\Models\Container;
use TinyBlocks\DockerContainer\Internal\Container\Models\ContainerId;
use TinyBlocks\DockerContainer\Internal\Container\Models\Environment\EnvironmentVariables;

final readonly class ContainerFactory
{
    public function __construct(
        private ContainerId $id,
        private Container $container,
        private ExecutionCompleted $executionCompleted
    ) {
    }

    public function build(): Container
    {
        $data = (array)json_decode($this->executionCompleted->getOutput(), true)[0];

        return Container::from(
            id: $this->id,
            name: $this->container->name,
            image: $this->container->image,
            address: Address::from(data: $data['NetworkSettings']),
            environmentVariables: EnvironmentVariables::from(data: $data['Config']['Env'])
        );
    }
}
