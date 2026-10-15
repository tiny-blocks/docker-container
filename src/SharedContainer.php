<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

/**
 * A started container shared by every PHP process that asks for the same name, so no single process removes it.
 *
 * <p>When the code runs inside Docker, a reaper container removes it once the test runner container exits.</p>
 */
interface SharedContainer extends ContainerStarted
{
    /**
     * Tells whether the container was reused from an already-running instance.
     *
     * @return bool True when an existing container was reused, false when a new one was started.
     */
    public function wasReused(): bool;
}
