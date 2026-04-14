<?php

declare(strict_types=1);

namespace Test\Unit\Mocks;

use TinyBlocks\DockerContainer\Internal\Client\Client;
use TinyBlocks\DockerContainer\Internal\Containers\ShutdownHook;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

final class TestableMySQLDockerContainer extends MySQLDockerContainer
{
    public static function createWith(
        string $image,
        ?string $name,
        Client $client,
        ?ShutdownHook $shutdownHook = null
    ): static {
        $container = TestableGenericDockerContainer::createWith(
            image: $image,
            name: $name,
            client: $client,
            shutdownHook: $shutdownHook
        );

        return new static(container: $container);
    }
}
