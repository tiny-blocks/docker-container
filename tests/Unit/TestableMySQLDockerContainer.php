<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Containers\ShutdownHook;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

final class TestableMySQLDockerContainer extends MySQLDockerContainer
{
    public static function createWith(
        ?string $name,
        string $image,
        Client $client,
        ?ShutdownHook $shutdownHook = null
    ): self {
        $container = TestableGenericDockerContainer::createWith(
            name: $name,
            image: $image,
            client: $client,
            shutdownHook: $shutdownHook
        );

        return new self(container: $container);
    }
}
