<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Contracts\Address;
use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Contracts\EnvironmentVariables;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\CommandHandler\CommandHandler;
use TinyBlocks\DockerContainer\Internal\Commands\DockerExecute;
use TinyBlocks\DockerContainer\Internal\Commands\DockerNetworkPrune;
use TinyBlocks\DockerContainer\Internal\Commands\DockerRemove;
use TinyBlocks\DockerContainer\Internal\Commands\DockerStop;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Address as ContainerAddress;
use TinyBlocks\DockerContainer\Internal\Containers\Environment\EnvironmentVariables as ContainerEnvironmentVariables;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Name;

final readonly class Started implements ContainerStarted
{
    public function __construct(
        private ContainerId $id,
        private Name $name,
        private ContainerAddress $address,
        private CommandHandler $commandHandler,
        private ContainerEnvironmentVariables $environmentVariables
    ) {
    }

    public function getId(): string
    {
        return $this->id->value;
    }

    public function getName(): string
    {
        return $this->name->value;
    }

    public function getAddress(): Address
    {
        return $this->address;
    }

    public function getEnvironmentVariables(): EnvironmentVariables
    {
        return $this->environmentVariables;
    }

    public function stop(int $timeoutInWholeSeconds = self::DEFAULT_TIMEOUT_IN_WHOLE_SECONDS): ExecutionCompleted
    {
        $command = DockerStop::from(id: $this->id, timeoutInWholeSeconds: $timeoutInWholeSeconds);

        return $this->commandHandler->execute(command: $command);
    }

    public function remove(): void
    {
        $this->commandHandler->execute(command: DockerRemove::from(id: $this->id));
        $this->commandHandler->execute(command: DockerNetworkPrune::create());
    }

    public function stopOnShutdown(): void
    {
        register_shutdown_function([$this, 'remove']);
    }

    public function executeAfterStarted(array $commands): ExecutionCompleted
    {
        $command = DockerExecute::from(name: $this->name, commands: $commands);

        return $this->commandHandler->execute(command: $command);
    }
}
