<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Container\Models\Container;

final class DockerListTest extends TestCase
{
    public function testDockerListCommand(): void
    {
        /** @Given a DockerList command */
        $command = DockerList::from(container: Container::create(name: 'container-name', image: 'image-name'));

        /** @When the command is converted to a command line */
        $actual = $command->toCommandLine();

        /** @Then the command line should be as expected */
        self::assertSame('docker ps --all --quiet --filter name=container-name', $actual);
    }
}
