<?php

declare(strict_types=1);

namespace Test\Unit;

use TinyBlocks\DockerContainer\FlywayDockerContainer;
use TinyBlocks\DockerContainer\Internal\Client\Client;

final class TestableFlywayDockerContainer extends FlywayDockerContainer
{
    public static function createWith(?string $name, string $image, Client $client): self
    {
        $container = TestableGenericDockerContainer::createWith(name: $name, image: $image, client: $client);

        return new self(container: $container);
    }
}
