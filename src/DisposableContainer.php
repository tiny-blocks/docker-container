<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;

/**
 * A started container this process owns alone, so it may remove the container once done with it.
 */
interface DisposableContainer extends ContainerStarted
{
    /**
     * Forcefully removes the container and its anonymous volumes, then prunes the unused networks created by the
     * library.
     *
     * @throws DockerCommandExecutionFailed If a removal command cannot run.
     */
    public function remove(): void;

    /**
     * Registers the container to be removed when the PHP process exits.
     */
    public function stopOnShutdown(): void;
}
