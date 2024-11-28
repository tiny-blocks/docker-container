<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Commands\Options\CommandOptions;
use TinyBlocks\DockerContainer\Internal\Commands\Options\EnvironmentVariableOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\NetworkOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\PortOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\SimpleCommandOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\VolumeOption;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Container;

final class DockerRunTest extends TestCase
{
    public function testDockerRunCommand(): void
    {
        /** @Given a DockerRun command */
        $command = DockerRun::from(
            commands: [],
            container: Container::create(name: 'container-name', image: 'image-name'),
            port: PortOption::from(portOnHost: 8080, portOnContainer: 80),
            network: NetworkOption::from(name: 'host'),
            volumes: CommandOptions::createFromOptions(
                commandOption: VolumeOption::from(
                    pathOnHost: '/path/to/source',
                    pathOnContainer: '/path/to/destination'
                )
            ),
            detached: SimpleCommandOption::DETACH,
            autoRemove: SimpleCommandOption::REMOVE,
            environmentVariables: CommandOptions::createFromOptions(
                commandOption: EnvironmentVariableOption::from(key: 'key1', value: 'value1')
            )
        );

        /** @When the command is converted to a command line */
        $actual = $command->toCommandLine();

        /** @Then the command line should be as expected */
        self::assertSame(
            "docker run --user root --name container-name --hostname container-name --publish 8080:80 --network=host --volume /path/to/source:/path/to/destination --detach --rm --env key1='value1' image-name",
            $actual
        );
    }
}
