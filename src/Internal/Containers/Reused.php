<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Contracts\Address;
use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Contracts\EnvironmentVariables;
use TinyBlocks\DockerContainer\Contracts\ExecutionCompleted;

final readonly class Reused implements ContainerStarted
{
    public function __construct(ContainerReaper $reaper, private ContainerStarted $containerStarted)
    {
        $reaper->ensureRunningFor(containerName: $containerStarted->getName());
    }

    public function remove(): void
    {
    }

    public function stopOnShutdown(): void
    {
    }

    public function getId(): string
    {
        return $this->containerStarted->getId();
    }

    public function getName(): string
    {
        return $this->containerStarted->getName();
    }

    public function getAddress(): Address
    {
        return $this->containerStarted->getAddress();
    }

    public function getEnvironmentVariables(): EnvironmentVariables
    {
        return $this->containerStarted->getEnvironmentVariables();
    }

    public function stop(int $timeoutInWholeSeconds = self::DEFAULT_TIMEOUT_IN_WHOLE_SECONDS): ExecutionCompleted
    {
        return $this->containerStarted->stop(timeoutInWholeSeconds: $timeoutInWholeSeconds);
    }


    public function executeAfterStarted(array $commands): ExecutionCompleted
    {
        return $this->containerStarted->executeAfterStarted(commands: $commands);
    }
}
