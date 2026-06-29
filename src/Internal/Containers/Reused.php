<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Address;
use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\DockerContainer\EnvironmentVariables;
use TinyBlocks\DockerContainer\ExecutionCompleted;

final readonly class Reused implements ContainerStarted
{
    public function __construct(
        ContainerReaper $reaper,
        private bool $wasReused,
        private ContainerStarted $containerStarted
    ) {
        $reaper->ensureRunningFor(containerName: $containerStarted->getName());
    }

    public function stop(int $timeoutInWholeSeconds = self::DEFAULT_TIMEOUT_IN_WHOLE_SECONDS): ExecutionCompleted
    {
        return $this->containerStarted->stop(timeoutInWholeSeconds: $timeoutInWholeSeconds);
    }

    public function getId(): string
    {
        return $this->containerStarted->getId();
    }

    public function remove(): void
    {
    }

    public function getName(): string
    {
        return $this->containerStarted->getName();
    }

    public function wasReused(): bool
    {
        return $this->wasReused;
    }

    public function getAddress(): Address
    {
        return $this->containerStarted->getAddress();
    }

    public function stopOnShutdown(): void
    {
    }

    public function executeAfterStarted(array $commands): ExecutionCompleted
    {
        return $this->containerStarted->executeAfterStarted(commands: $commands);
    }

    public function getEnvironmentVariables(): EnvironmentVariables
    {
        return $this->containerStarted->getEnvironmentVariables();
    }
}
