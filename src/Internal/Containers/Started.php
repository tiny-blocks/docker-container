<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Address;
use TinyBlocks\DockerContainer\DisposableContainer;
use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\EnvironmentVariables;
use TinyBlocks\DockerContainer\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\Commands\DockerExecute;
use TinyBlocks\DockerContainer\Internal\Commands\DockerNetworkPrune;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRemove;
use TinyBlocks\DockerContainer\Internal\Commands\DockerStop;
use TinyBlocks\Time\Duration;

final readonly class Started implements DisposableContainer
{
    public function __construct(
        private ContainerId $id,
        private Name $name,
        private DockerClient $client,
        private Address $address,
        private EnvironmentVariables $environmentVariables
    ) {
    }

    public function stop(Duration $gracePeriod): ExecutionCompleted
    {
        return $this->client->executeWithTimeout(command: DockerStop::from(id: $this->id, gracePeriod: $gracePeriod));
    }

    public function getId(): string
    {
        return $this->id->value;
    }

    public function remove(): void
    {
        $this->client->execute(command: DockerRemove::from(id: $this->id));
        $this->client->execute(command: DockerNetworkPrune::create());
    }

    public function getName(): string
    {
        return $this->name->value;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function stopOnShutdown(): void
    {
        register_shutdown_function($this->remove(...));
    }

    public function executeAfterStarted(array $commands): ExecutionCompleted
    {
        return $this->client->execute(command: DockerExecute::from(name: $this->name, commands: $commands));
    }

    public function getEnvironmentVariables(): EnvironmentVariables
    {
        return $this->environmentVariables;
    }
}
