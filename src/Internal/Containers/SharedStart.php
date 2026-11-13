<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;

final readonly class SharedStart
{
    public function __construct(private Name $name, private ContainerLookup $lookup, private ContainerStartup $startup)
    {
    }

    public function startOrJoin(array $commands): Shared
    {
        try {
            $id = $this->startup->launch(commands: $commands);
        } catch (DockerCommandExecutionFailed $failure) {
            $winner = $this->lookup->byName(name: $this->name);

            if (is_null($winner)) {
                throw $failure;
            }

            return Shared::reused(containerStarted: $winner);
        }

        return Shared::created(containerStarted: $this->startup->handOver(id: $id));
    }
}
