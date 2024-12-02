<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Contracts\Address;
use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Contracts\EnvironmentVariables;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;
use TinyBlocks\DockerContainer\Internal\CommandHandler;
use TinyBlocks\DockerContainer\Internal\Commands\DockerExecute;
use TinyBlocks\DockerContainer\Internal\Commands\DockerStop;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;

readonly class Started implements ContainerStarted
{
    public function __construct(public Container $container, public CommandHandler $commandHandler)
    {
    }

    public function getId(): string
    {
        return $this->container->id->value;
    }

    public function getName(): string
    {
        return $this->container->name->value;
    }

    public function getAddress(): Address
    {
        return $this->container->address;
    }

    public function getEnvironmentVariables(): EnvironmentVariables
    {
        return $this->container->environmentVariables;
    }

    public function stop(int $timeoutInWholeSeconds = self::DEFAULT_TIMEOUT_IN_WHOLE_SECONDS): ExecutionCompleted
    {
        $command = DockerStop::from(id: $this->container->id, timeoutInWholeSeconds: $timeoutInWholeSeconds);

        return $this->commandHandler->execute(command: $command);
    }

    public function executeAfterStarted(array $commands): ExecutionCompleted
    {
        $command = DockerExecute::from(name: $this->container->name, commandOptions: $commands);

        return $this->commandHandler->execute(command: $command);
    }
}
