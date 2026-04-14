<?php

declare(strict_types=1);

namespace Test\Unit\Internal\Containers;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Test\Unit\Mocks\ClientMock;
use TinyBlocks\DockerContainer\Internal\Containers\ContainerReaper;

final class ContainerReaperTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testEnsureRunningForSkipsWhenOutsideDocker(): void
    {
        require_once __DIR__ . '/Overrides/file_exists_outside_docker.php';

        /** @Given a ContainerReaper with a mock client */
        $client = new ClientMock();
        $reaper = new ContainerReaper(client: $client);

        /** @When ensureRunningFor is called outside a Docker environment */
        $reaper->ensureRunningFor(containerName: 'test-container');

        /** @Then no Docker commands should have been executed */
        self::assertEmpty($client->getExecutedCommandLines());
    }
}

