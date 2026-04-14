<?php

declare(strict_types=1);

namespace Test\Unit\Internal\Containers\Address;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Address;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Hostname;
use TinyBlocks\DockerContainer\Internal\Containers\Address\IP;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Ports;

final class AddressTest extends TestCase
    {
            #[RunInSeparateProcess]
            public function testGetHostForConnectionReturnsLocalhostWhenOutsideDocker(): void
        {
                    require_once __DIR__ . '/../Overrides/file_exists_outside_docker.php';

                /** @Given an Address with a known hostname */
                $address = Address::from(
                                ip: IP::from(value: '172.17.0.2'),
                                ports: Ports::from(
                                                    exposedPorts: \TinyBlocks\Collection\Collection::createFrom(values: [3306]),
                                                    hostMappedPorts: \TinyBlocks\Collection\Collection::createFrom(values: [49153])
                                                ),
                                hostname: Hostname::from(value: 'my-container')
                            );

                /** @When getHostForConnection is called outside Docker */
                $host = $address->getHostForConnection();

                /** @Then it should return 127.0.0.1 */
                self::assertSame('127.0.0.1', $host);
        }

    #[RunInSeparateProcess]
            public function testGetHostForConnectionReturnsHostnameWhenInsideDocker(): void
        {
                    require_once __DIR__ . '/../Overrides/file_exists_inside_docker.php';

                /** @Given an Address with a known hostname */
                $address = Address::from(
                                ip: IP::from(value: '172.17.0.2'),
                                ports: Ports::from(
                                                    exposedPorts: \TinyBlocks\Collection\Collection::createFrom(values: [3306]),
                                                    hostMappedPorts: \TinyBlocks\Collection\Collection::createFrom(values: [49153])
                                                ),
                                hostname: Hostname::from(value: 'my-container')
                            );

                /** @When getHostForConnection is called inside Docker */
                $host = $address->getHostForConnection();

                /** @Then it should return the container hostname */
                self::assertSame('my-container', $host);
        }
    }
