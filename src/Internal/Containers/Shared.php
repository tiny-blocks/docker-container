<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Address;
use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\DockerContainer\EnvironmentVariables;
use TinyBlocks\DockerContainer\ExecutionCompleted;
use TinyBlocks\DockerContainer\SharedContainer;
use TinyBlocks\Time\Duration;

final readonly class Shared implements SharedContainer
{
    private function __construct(private bool $wasReused, private ContainerStarted $containerStarted)
    {
    }

    public static function reused(ContainerStarted $containerStarted): Shared
    {
        return new Shared(wasReused: true, containerStarted: $containerStarted);
    }

    public static function created(ContainerStarted $containerStarted): Shared
    {
        return new Shared(wasReused: false, containerStarted: $containerStarted);
    }

    public function stop(Duration $gracePeriod): ExecutionCompleted
    {
        return $this->containerStarted->stop(gracePeriod: $gracePeriod);
    }

    public function getId(): string
    {
        return $this->containerStarted->getId();
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

    public function executeAfterStarted(array $commands): ExecutionCompleted
    {
        return $this->containerStarted->executeAfterStarted(commands: $commands);
    }

    public function getEnvironmentVariables(): EnvironmentVariables
    {
        return $this->containerStarted->getEnvironmentVariables();
    }
}
