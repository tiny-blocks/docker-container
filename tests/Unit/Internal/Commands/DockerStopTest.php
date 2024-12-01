<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Commands;

use PHPUnit\Framework\TestCase;
use TinyBlocks\DockerContainer\Internal\Containers\Models\ContainerId;

final class DockerStopTest extends TestCase
{
    public function testStopCommand(): void
    {
        /** @Given a DockerStop command */
        $command = DockerStop::from(id: ContainerId::from(value: '1234567890ab'), timeoutInWholeSeconds: 10);

        /** @When the command is converted to a command line */
        $actual = $command->toCommandLine();

        /** @And the timeout should be correct */
        self::assertSame('docker stop 1234567890ab', $actual);
        self::assertSame(10, $command->getTimeoutInWholeSeconds());
    }
}
