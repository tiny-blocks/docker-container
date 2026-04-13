<?php

declare(strict_types=1);

namespace Test\Unit\Mocks;

use TinyBlocks\DockerContainer\FlywayDockerContainer;
use TinyBlocks\DockerContainer\Internal\Client\Client;

final class TestableFlywayDockerContainer extends FlywayDockerContainer
{
    public static function createWith(string $image, ?string $name, Client $client): static
    {
        $container = TestableGenericDockerContainer::createWith(image: $image, name: $name, client: $client);

        return new static(container: $container);
    }
}
