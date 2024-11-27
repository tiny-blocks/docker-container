<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOptions;
use TinyBlocks\DockerContainer\Internal\Commands\Options\EnvironmentVariable;
use TinyBlocks\DockerContainer\Internal\Commands\Options\Network;
use TinyBlocks\DockerContainer\Internal\Commands\Options\Port;
use TinyBlocks\DockerContainer\Internal\Commands\Options\SimpleCommandOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\Volume;
use TinyBlocks\DockerContainer\Internal\Container\Models\Container;
use TinyBlocks\DockerContainer\NetworkDrivers;

final class DockerRunTest extends TestCase
{
    public function testDockerRunCommand(): void
    {
        /** @Given a DockerRun command */
        $command = DockerRun::from(
            commands: [],
            container: Container::create(name: 'container-name', image: 'image-name'),
            port: Port::from(portOnHost: 8080, portOnContainer: 80),
            network: Network::from(driver: NetworkDrivers::OVERLAY),
            volumes: CommandOptions::createFromOptions(
                commandOption: Volume::from(
                    pathOnHost: '/path/to/source',
                    pathOnContainer: '/path/to/destination'
                )
            ),
            detached: SimpleCommandOption::DETACH,
            autoRemove: SimpleCommandOption::REMOVE,
            environmentVariables: CommandOptions::createFromOptions(
                commandOption: EnvironmentVariable::from(key: 'key1', value: 'value1')
            )
        );

        /** @When the command is converted to a command line */
        $actual = $command->toCommandLine();

        /** @Then the command line should be as expected */
        self::assertSame(
            "docker run --user root --name container-name --hostname container-name --publish 8080:80 --network overlay --volume /path/to/source:/path/to/destination --detach --rm --env key1='value1' image-name",
            $actual
        );
    }
}
