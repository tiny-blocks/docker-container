<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Commands\Options\Item;
use TinyBlocks\DockerContainer\Internal\Commands\Options\Volume;
use TinyBlocks\DockerContainer\Internal\Container\Models\ContainerId;

final class DockerCopyTest extends TestCase
{
    public function testDockerCopyCommand(): void
    {
        /** @Given a DockerCopy command */
        $command = DockerCopy::from(
            Item::from(
                id: ContainerId::from(value: 'abc123abc123'),
                volume: Volume::from(
                    pathOnHost: '/path/to/source',
                    pathOnContainer: '/path/to/destination'
                )
            )
        );

        /** @When the command is converted to a command line */
        $actual = $command->toCommandLine();

        /** @Then the command line should be as expected */
        self::assertSame('docker cp /path/to/source abc123abc123:/path/to/destination', $actual);
    }
}
