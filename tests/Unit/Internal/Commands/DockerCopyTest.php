<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Commands\Options\ItemToCopyOption;
use TinyBlocks\DockerContainer\Internal\Commands\Options\VolumeOption;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final class DockerCopyTest extends TestCase
{
    public function testDockerCopyCommand(): void
    {
        /** @Given a DockerCopy command */
        $command = DockerCopy::from(
            ItemToCopyOption::from(
                id: ContainerId::from(value: 'abc123abc123'),
                volume: VolumeOption::from(
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
