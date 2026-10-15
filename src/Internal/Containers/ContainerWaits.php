<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitAfterStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitBeforeStarted;

final readonly class ContainerWaits
{
    private function __construct(private Collection $beforeRun, private Collection $afterStarted)
    {
    }

    public static function none(): ContainerWaits
    {
        return new ContainerWaits(
            beforeRun: Collection::createFromEmpty(),
            afterStarted: Collection::createFromEmpty()
        );
    }

    public function waitBeforeRun(): void
    {
        $this->beforeRun->each(actions: static fn(ContainerWaitBeforeStarted $wait) => $wait->waitBefore());
    }

    public function withBeforeRun(ContainerWaitBeforeStarted $wait): ContainerWaits
    {
        return new ContainerWaits(beforeRun: $this->beforeRun->add($wait), afterStarted: $this->afterStarted);
    }

    public function waitAfterStarted(ContainerStarted $containerStarted): void
    {
        $this->afterStarted->each(
            actions: static fn(ContainerWaitAfterStarted $wait) => $wait->waitAfter(containerStarted: $containerStarted)
        );
    }

    public function withAfterStarted(ContainerWaitAfterStarted $wait): ContainerWaits
    {
        return new ContainerWaits(beforeRun: $this->beforeRun, afterStarted: $this->afterStarted->add($wait));
    }
}
