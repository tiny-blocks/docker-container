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

    #[RunInSeparateProcess]
    public function testEnsureRunningForStartsReaperWhenReaperIsMissing(): void
    {
        require_once __DIR__ . '/Overrides/file_exists_inside_docker.php';

        /** @Given a ContainerReaper with a mock client */
        $client = new ClientMock();
        $reaper = new ContainerReaper(client: $client);

        /** @And no existing reaper container is listed */
        $client->withDockerListResponse(output: '');

        /** @When ensureRunningFor is called inside a Docker environment */
        $reaper->ensureRunningFor(containerName: 'test-container');

        /** @Then a reaper container should have been started for the target container */
        self::assertStringContainsString(
            'docker run --rm -d --name tiny-blocks-reaper-test-container',
            implode(PHP_EOL, $client->getExecutedCommandLines())
        );
    }

    #[RunInSeparateProcess]
    public function testEnsureRunningForStartsReaperWhenListOutputIsOnlyWhitespace(): void
    {
        require_once __DIR__ . '/Overrides/file_exists_inside_docker.php';

        /** @Given a ContainerReaper with a mock client */
        $client = new ClientMock();
        $reaper = new ContainerReaper(client: $client);

        /** @And the Docker list response contains only whitespace */
        $client->withDockerListResponse(output: "   \n\t ");

        /** @When ensureRunningFor is called inside a Docker environment */
        $reaper->ensureRunningFor(containerName: 'whitespace-container');

        /** @Then a reaper container should have been started for the target container */
        self::assertStringContainsString(
            'docker run --rm -d --name tiny-blocks-reaper-whitespace-container',
            implode(PHP_EOL, $client->getExecutedCommandLines())
        );
    }
}

