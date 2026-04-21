<?php

declare(strict_types=1);

namespace Test\Unit\Internal\Containers\Address;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Ports;

final class PortsTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testGetPortForConnectionReturnsHostPortWhenOutsideDocker(): void
    {
        require_once __DIR__ . '/../Overrides/file_exists_outside_docker.php';

        /** @Given Ports with known exposed and host-mapped ports */
        $ports = Ports::from(
            exposedPorts: Collection::createFrom(elements: [3306]),
            hostMappedPorts: Collection::createFrom(elements: [49153])
        );

        /** @When getPortForConnection is called outside Docker */
        $port = $ports->getPortForConnection();

        /** @Then it should return the host-mapped port */
        self::assertSame(49153, $port);
    }

    #[RunInSeparateProcess]
    public function testGetPortForConnectionReturnsExposedPortWhenInsideDocker(): void
    {
        require_once __DIR__ . '/../Overrides/file_exists_inside_docker.php';

        /** @Given Ports with known exposed and host-mapped ports */
        $ports = Ports::from(
            exposedPorts: Collection::createFrom(elements: [3306]),
            hostMappedPorts: Collection::createFrom(elements: [49153])
        );

        /** @When getPortForConnection is called inside Docker */
        $port = $ports->getPortForConnection();

        /** @Then it should return the container-internal exposed port */
        self::assertSame(3306, $port);
    }

    public function testPortsDropsFalsyValuesAndReindexesSequentially(): void
    {
        /** @Given Ports built from collections containing zeros between valid port numbers */
        $ports = Ports::from(
            exposedPorts: Collection::createFrom(elements: [0, 3306, 0, 8080]),
            hostMappedPorts: Collection::createFrom(elements: [0, 49153, 0, 49154])
        );

        /** @Then exposed ports should drop zero values and use sequential numeric keys from zero */
        self::assertSame([3306, 8080], $ports->exposedPorts());
        self::assertSame(3306, $ports->firstExposedPort());

        /** @And host-mapped ports should drop zero values and use sequential numeric keys from zero */
        self::assertSame([49153, 49154], $ports->hostPorts());
        self::assertSame(49153, $ports->firstHostPort());
    }
}
