<?php

declare(strict_types=1);

namespace Test\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\ContainerStarted;
use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\Internal\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerContainerNotFound;
use TinyBlocks\DockerContainer\Internal\Exceptions\StopTimeoutOutOfRange;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;

final class GenericDockerContainerTest extends TestCase
{
    private ClientMock $client;

    protected function setUp(): void
    {
        $this->client = new ClientMock();
    }

    public function testStopContainer(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'stop-test',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-test'));
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped */
        $stopped = $started->stop();

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testExecuteAfterStarted(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'exec-test',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'exec-test'));
        $this->client->withDockerExecuteResponse(output: 'command output');

        /** @And the container is started */
        $started = $container->run();

        /** @When commands are executed inside the running container */
        $execution = $started->executeAfterStarted(commands: ['ls', '-la']);

        /** @Then the execution should be successful */
        self::assertTrue($execution->isSuccessful());
        self::assertSame('command output', $execution->getOutput());
    }

    public function testExceptionWhenRunFails(): void
    {
        /** @Given a container that will fail to start */
        $container = TestableGenericDockerContainer::createWith(
            name: 'fail-test',
            image: 'invalid:image',
            client: $this->client
        );

        /** @And the Docker daemon returns a failure */
        $this->client->withDockerRunResponse(output: 'Cannot connect to the Docker daemon.', isSuccessful: false);

        /** @Then a DockerCommandExecutionFailed exception should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches('/Cannot connect to the Docker daemon/');

        /** @When the container is started */
        $container->run();
    }

    public function testRunContainerSuccessfully(): void
    {
        /** @Given a container configured with an image and a name */
        $container = TestableGenericDockerContainer::createWith(
            name: 'test-alpine',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns a valid container ID and inspect response */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'test-alpine',
                environment: ['PATH=/usr/local/bin']
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running with the expected properties */
        self::assertSame(InspectResponseFixture::shortContainerId(), $started->getId());
        self::assertSame('test-alpine', $started->getName());
        self::assertSame('test-alpine', $started->getAddress()->getHostname());
        self::assertSame('172.22.0.2', $started->getAddress()->getIp());
    }

    public function testRunContainerWithCommands(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'cmd-test',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'cmd-test'));

        /** @When the container is started with commands */
        $started = $container->run(commands: ['echo', 'hello']);

        /** @Then the container should be running */
        self::assertSame('cmd-test', $started->getName());
    }

    public function testRunContainerWithPullImage(): void
    {
        /** @Given a container with image pulling enabled */
        $container = TestableGenericDockerContainer::createWith(
            name: 'pull-test',
            image: 'alpine:latest',
            client: $this->client
        )->pullImage();

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'pull-test'));

        /** @When the container is started (waiting for the image pull to complete first) */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('pull-test', $started->getName());

        /** @And the docker pull command should have been executed */
        $commandLines = $this->client->getExecutedCommandLines();
        self::assertStringContainsString('docker pull alpine:latest', $commandLines[0]);
    }

    public function testContainerWithHostPortMapping(): void
    {
        /** @Given a container with a host port mapping */
        $container = TestableGenericDockerContainer::createWith(
            name: 'host-port',
            image: 'mysql:8.4',
            client: $this->client
        )->withPortMapping(portOnHost: 33060, portOnContainer: 3306);

        /** @And the Docker daemon returns a response with host port bindings */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'host-port',
                exposedPorts: ['3306/tcp' => (object)[]],
                hostPortBindings: [
                    '3306/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '33060']]
                ]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the exposed port should be the container-internal port */
        self::assertSame(3306, $started->getAddress()->getPorts()->firstExposedPort());

        /** @And the host port should be the host-mapped port */
        self::assertSame(33060, $started->getAddress()->getPorts()->firstHostPort());
        self::assertSame([33060], $started->getAddress()->getPorts()->hostPorts());
    }

    public function testExceptionWhenImageNameIsEmpty(): void
    {
        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Image name cannot be empty.');

        /** @When creating a container with an empty image name */
        GenericDockerContainer::from(image: '');
    }

    public function testRemoveOnReusedContainerIsNoOp(): void
    {
        /** @Given a container returned by runIfNotExists (a Reused instance) */
        $container = TestableGenericDockerContainer::createWith(
            name: 'reused-remove',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker list returns an existing container */
        $this->client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'reused-remove')
        );

        /** @When runIfNotExists returns a reused container */
        $started = $container->runIfNotExists();

        /** @And remove is called on the reused container */
        $started->remove();

        /** @Then the container should still be accessible (remove is a no-op for reused containers) */
        self::assertSame('reused-remove', $started->getName());
    }

    public function testRunCommandLineIncludesNetwork(): void
    {
        /** @Given a container with a network */
        $container = TestableGenericDockerContainer::createWith(
            name: 'net-cmd',
            image: 'alpine:latest',
            client: $this->client
        )->withNetwork(name: 'my-network');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'net-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the first command should be the network creation */
        $networkCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString(
            'docker network create --label tiny-blocks.docker-container=true my-network',
            $networkCommand
        );

        /** @And the docker run command should contain the network argument */
        $runCommand = $this->client->getExecutedCommandLines()[2];
        self::assertStringContainsString('--network=my-network', $runCommand);
    }

    public function testRunContainerWithWaitBeforeRun(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a container with a wait-before-run condition */
        $container = TestableGenericDockerContainer::createWith(
            name: 'wait-test',
            image: 'alpine:latest',
            client: $this->client
        )->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $condition));

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'wait-test'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running (wait was called) */
        self::assertSame('wait-test', $started->getName());
    }

    public function testRunContainerWithoutAutoRemove(): void
    {
        /** @Given a container with auto-remove disabled */
        $container = TestableGenericDockerContainer::createWith(
            name: 'persistent',
            image: 'alpine:latest',
            client: $this->client
        )->withoutAutoRemove();

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'persistent'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('persistent', $started->getName());
    }

    public function testStopExecutesDockerStopCommand(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'stop-cmd',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-cmd'));
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped */
        $started->stop();

        /** @Then a docker stop command should have been executed with the container ID */
        $stopCommand = $this->client->getExecutedCommandLines()[2];
        self::assertStringStartsWith('docker stop', $stopCommand);
        self::assertStringContainsString(InspectResponseFixture::shortContainerId(), $stopCommand);
    }

    public function testStopOnShutdownRegistersRemove(): void
    {
        /** @Given a ShutdownHook that tracks registration */
        $shutdownHook = new ShutdownHookMock();

        /** @And a running container using the tracked hook */
        $container = TestableGenericDockerContainer::createWith(
            name: 'shutdown-test',
            image: 'alpine:latest',
            client: $this->client,
            shutdownHook: $shutdownHook
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'shutdown-test')
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When stopOnShutdown is called */
        $started->stopOnShutdown();

        /** @Then the shutdown hook should have registered the remove callback */
        self::assertSame(1, $shutdownHook->getRegistrationCount());
    }

    public function testRemoveCanBeCalledMultipleTimes(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'already-removed',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'already-removed')
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When remove is called twice */
        $started->remove();
        $started->remove();

        /** @Then no exception should be thrown */
        self::assertTrue(true);
    }

    public function testRunCommandLineIncludesCommands(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'args-cmd',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'args-cmd'));

        /** @When the container is started with commands */
        $container->run(commands: ['-connectRetries=15', 'clean', 'migrate']);

        /** @Then the docker run command should end with the commands */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('-connectRetries=15 clean migrate', $runCommand);
    }

    public function testStopContainerWithCustomTimeout(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'stop-timeout',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'stop-timeout')
        );
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a custom timeout */
        $stopped = $started->stop(timeoutInWholeSeconds: 10);

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testRunContainerWithCopyToContainer(): void
    {
        /** @Given a container with files to copy */
        $container = TestableGenericDockerContainer::createWith(
            name: 'copy-test',
            image: 'alpine:latest',
            client: $this->client
        )->copyToContainer(pathOnHost: '/host/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'copy-test'));

        /** @When the container is started (docker cp is automatically called) */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('copy-test', $started->getName());
    }

    public function testRunIfNotExistsWithWaitBeforeRun(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a container with a wait-before-run that does not exist */
        $container = TestableGenericDockerContainer::createWith(
            name: 'wait-new',
            image: 'alpine:latest',
            client: $this->client
        )->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $condition));

        /** @And the Docker list returns empty */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'wait-new'));

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the wait-before-run should have been evaluated and the container created */
        self::assertSame('wait-new', $started->getName());
    }

    public function testRunContainerWithWaitAfterStarted(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'wait-after',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'wait-after'));

        /** @When the container is started with a wait-after condition */
        $start = microtime(true);
        $started = $container->run(waitAfterStarted: ContainerWaitForTime::forSeconds(seconds: 1));
        $elapsed = microtime(true) - $start;

        /** @Then the container should have waited after starting */
        self::assertSame('wait-after', $started->getName());
        self::assertGreaterThanOrEqual(0.9, $elapsed);
    }

    public function testExecuteAfterStartedReturnsFailure(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'exec-fail',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'exec-fail'));
        $this->client->withDockerExecuteResponse(output: 'command not found', isSuccessful: false);

        /** @And the container is started */
        $started = $container->run();

        /** @When an invalid command is executed */
        $execution = $started->executeAfterStarted(commands: ['invalid-command']);

        /** @Then the result should indicate failure */
        self::assertFalse($execution->isSuccessful());
        self::assertSame('command not found', $execution->getOutput());
    }

    public function testRunCommandLineIncludesPortMapping(): void
    {
        /** @Given a container with a port mapping */
        $container = TestableGenericDockerContainer::createWith(
            name: 'port-cmd',
            image: 'nginx:latest',
            client: $this->client
        )->withPortMapping(portOnHost: 8080, portOnContainer: 80);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'port-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the executed docker run command should contain the port mapping argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--publish 8080:80', $runCommand);
    }

    public function testRunContainerWithAutoGeneratedName(): void
    {
        /** @Given a container without a name */
        $container = TestableGenericDockerContainer::createWith(
            name: null,
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses (with any hostname from KSUID) */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'auto-generated'
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should have an auto-generated name (non-empty) */
        self::assertNotEmpty($started->getName());
    }

    public function testRunContainerWithFullConfiguration(): void
    {
        /** @Given a fully configured container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'web-server',
            image: 'nginx:latest',
            client: $this->client
        )
            ->withNetwork(name: 'my-network')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withVolumeMapping(pathOnHost: '/var/www', pathOnContainer: '/usr/share/nginx/html')
            ->withEnvironmentVariable(key: 'NGINX_HOST', value: 'localhost')
            ->withEnvironmentVariable(key: 'NGINX_PORT', value: '80');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'web-server',
                environment: ['NGINX_HOST=localhost', 'NGINX_PORT=80'],
                networkName: 'my-network',
                exposedPorts: ['80/tcp' => (object)[]]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should expose the configured environment variables */
        self::assertSame(
            'localhost',
            $started->getEnvironmentVariables()->getValueBy(
                key: 'NGINX_HOST'
            )
        );
        self::assertSame('80', $started->getEnvironmentVariables()->getValueBy(key: 'NGINX_PORT'));

        /** @And the address should reflect the exposed port */
        self::assertSame(80, $started->getAddress()->getPorts()->firstExposedPort());
        self::assertSame([80], $started->getAddress()->getPorts()->exposedPorts());
    }

    public function testRunIfNotExistsCreatesNewContainer(): void
    {
        /** @Given a container that does not exist */
        $container = TestableGenericDockerContainer::createWith(
            name: 'new-container',
            image: 'alpine:latest',
            client: $this->client
        )->withEnvironmentVariable(key: 'APP_ENV', value: 'test');

        /** @And the Docker list returns empty (container does not exist) */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'new-container',
                environment: ['APP_ENV=test']
            )
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then a new container should be created */
        self::assertSame('new-container', $started->getName());
        self::assertSame('test', $started->getEnvironmentVariables()->getValueBy(key: 'APP_ENV'));
    }

    public function testExceptionWhenWaitBeforeRunTimesOut(): void
    {
        /** @Given a condition that never becomes ready */
        $condition = $this->createStub(ContainerReady::class);
        $condition->method('isReady')->willReturn(false);

        /** @And a container with a wait-before-run that has a short timeout */
        $container = TestableGenericDockerContainer::createWith(
            name: 'timeout-wait',
            image: 'alpine:latest',
            client: $this->client
        )->withWaitBeforeRun(
            wait: ContainerWaitForDependency::untilReady(
                condition: $condition,
                timeoutInSeconds: 1,
                pollIntervalInMicroseconds: 50_000
            )
        );

        /** @Then a ContainerWaitTimeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When the container is started */
        $container->run();
    }

    public function testRunCommandLineIncludesVolumeMapping(): void
    {
        /** @Given a container with two volume mappings */
        $container = TestableGenericDockerContainer::createWith(
            name: 'vol-cmd',
            image: 'alpine:latest',
            client: $this->client
        )
            ->withVolumeMapping(pathOnHost: '/host/data', pathOnContainer: '/app/data')
            ->withVolumeMapping(pathOnHost: '/host/logs', pathOnContainer: '/app/logs');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'vol-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain the first volume mapping argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--volume /host/data:/app/data', $runCommand);

        /** @And the docker run command should contain the second volume mapping argument */
        self::assertStringContainsString('--volume /host/logs:/app/logs', $runCommand);
    }

    public function testRunContainerWithMultiplePortMappings(): void
    {
        /** @Given a container with multiple port mappings */
        $container = TestableGenericDockerContainer::createWith(
            name: 'multi-port',
            image: 'nginx:latest',
            client: $this->client
        )
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'multi-port',
                exposedPorts: ['80/tcp' => (object)[], '443/tcp' => (object)[]]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then both ports should be exposed */
        self::assertSame([80, 443], $started->getAddress()->getPorts()->exposedPorts());
        self::assertSame(80, $started->getAddress()->getPorts()->firstExposedPort());
    }

    public function testAddressDefaultsWhenNetworkInfoIsEmpty(): void
    {
        /** @Given a container with empty network info */
        $container = TestableGenericDockerContainer::createWith(
            name: 'no-net',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns a response with empty address data */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: '',
                ipAddress: ''
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the address should fall back to defaults */
        self::assertSame('127.0.0.1', $started->getAddress()->getIp());
        self::assertSame('localhost', $started->getAddress()->getHostname());
    }

    public function testContainerWithMultipleHostPortMappings(): void
    {
        /** @Given a container with multiple host port mappings */
        $container = TestableGenericDockerContainer::createWith(
            name: 'multi-host-port',
            image: 'nginx:latest',
            client: $this->client
        )
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon returns a response with multiple host port bindings */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'multi-host-port',
                exposedPorts: ['80/tcp' => (object)[], '443/tcp' => (object)[]],
                hostPortBindings: [
                    '80/tcp'  => [['HostIp' => '0.0.0.0', 'HostPort' => '8080']],
                    '443/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '8443']]
                ]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then both exposed and host ports should be available */
        self::assertSame([80, 443], $started->getAddress()->getPorts()->exposedPorts());
        self::assertSame([8080, 8443], $started->getAddress()->getPorts()->hostPorts());
        self::assertSame(8080, $started->getAddress()->getPorts()->firstHostPort());
    }

    public function testRemoveExecutesDockerRmAndNetworkPrune(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'force-remove',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'force-remove')
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When remove is called */
        $started->remove();

        /** @Then a docker rm command should have been executed with the container ID */
        $commandLines = $this->client->getExecutedCommandLines();
        $removeCommand = $commandLines[2];

        self::assertStringContainsString('docker rm --force --volumes', $removeCommand);
        self::assertStringContainsString(
            InspectResponseFixture::shortContainerId(),
            $removeCommand
        );

        /** @And a docker network prune command should have been executed with the managed label */
        $pruneCommand = $commandLines[3];

        self::assertStringContainsString(
            'docker network prune --force --filter label=tiny-blocks.docker-container=true',
            $pruneCommand
        );
    }

    public function testRunWhenGateDoesNotHoldThenNothingRuns(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'gate-closed',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And a callback that records whether it was invoked */
        $wasInvoked = false;

        /** @When runWhen is invoked with a gate that does not hold */
        $container->runWhen(
            gate: static fn(): bool => false,
            then: static function (ContainerStarted $started) use (&$wasInvoked): void {
                $wasInvoked = true;
            }
        );

        /** @Then the callback should not have been invoked */
        self::assertFalse($wasInvoked);

        /** @And no docker command should have been executed */
        self::assertEmpty($this->client->getExecutedCommandLines());
    }

    public function testContainerWithNoExposedPortsReturnsNull(): void
    {
        /** @Given a container with no exposed ports */
        $container = TestableGenericDockerContainer::createWith(
            name: 'no-ports',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses without exposed ports */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'no-ports'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then firstExposedPort should return null */
        self::assertNull($started->getAddress()->getPorts()->firstExposedPort());
        self::assertEmpty($started->getAddress()->getPorts()->exposedPorts());

        /** @And firstHostPort should return null */
        self::assertNull($started->getAddress()->getPorts()->firstHostPort());
        self::assertEmpty($started->getAddress()->getPorts()->hostPorts());
    }

    public function testCopyToContainerExecutesDockerCpCommand(): void
    {
        /** @Given a container with a copy instruction */
        $container = TestableGenericDockerContainer::createWith(
            name: 'cp-cmd',
            image: 'alpine:latest',
            client: $this->client
        )->copyToContainer(pathOnHost: '/host/sql', pathOnContainer: '/app/sql');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'cp-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the second executed command should be a docker cp with the correct arguments */
        $copyCommand = $this->client->getExecutedCommandLines()[2];
        self::assertStringStartsWith('docker cp', $copyCommand);
        self::assertStringContainsString('/host/sql', $copyCommand);
        self::assertStringContainsString('/app/sql', $copyCommand);
    }

    public function testRunContainerWithMultipleVolumeMappings(): void
    {
        /** @Given a container with multiple volume mappings */
        $container = TestableGenericDockerContainer::createWith(
            name: 'multi-vol',
            image: 'alpine:latest',
            client: $this->client
        )
            ->withVolumeMapping(pathOnHost: '/data', pathOnContainer: '/app/data')
            ->withVolumeMapping(pathOnHost: '/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'multi-vol'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('multi-vol', $started->getName());
    }

    public function testRunIfNotExistsReturnsExistingContainer(): void
    {
        /** @Given a container that already exists */
        $container = TestableGenericDockerContainer::createWith(
            name: 'existing',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker list returns the existing container ID */
        $this->client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'existing',
                environment: ['EXISTING=true']
            )
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the existing container should be returned */
        self::assertSame('existing', $started->getName());
        self::assertSame(InspectResponseFixture::shortContainerId(), $started->getId());
        self::assertSame('true', $started->getEnvironmentVariables()->getValueBy(key: 'EXISTING'));
    }

    public function testContainerWithExposedPortButNoHostBinding(): void
    {
        /** @Given a container with an exposed port but no host binding */
        $container = TestableGenericDockerContainer::createWith(
            name: 'no-host-bind',
            image: 'redis:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns a response with exposed port but null host bindings */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'no-host-bind',
                exposedPorts: ['6379/tcp' => (object)[]],
                hostPortBindings: ['6379/tcp' => null]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the exposed port should be available */
        self::assertSame(6379, $started->getAddress()->getPorts()->firstExposedPort());

        /** @And the host port should be null since there is no binding */
        self::assertNull($started->getAddress()->getPorts()->firstHostPort());
        self::assertEmpty($started->getAddress()->getPorts()->hostPorts());
    }

    public function testExecuteAfterStartedRunsDockerExecCommand(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'exec-cmd',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'exec-cmd'));
        $this->client->withDockerExecuteResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When executing commands inside the container */
        $started->executeAfterStarted(commands: ['ls', '-la', '/tmp']);

        /** @Then a docker exec command should have been executed with the container name and commands */
        $execCommand = $this->client->getExecutedCommandLines()[2];
        self::assertSame('docker exec exec-cmd ls -la /tmp', $execCommand);
    }

    public function testRunContainerWithMultipleCopyInstructions(): void
    {
        /** @Given a container with multiple copy instructions */
        $container = TestableGenericDockerContainer::createWith(
            name: 'multi-copy',
            image: 'alpine:latest',
            client: $this->client
        )
            ->copyToContainer(pathOnHost: '/host/sql', pathOnContainer: '/app/sql')
            ->copyToContainer(pathOnHost: '/host/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'multi-copy'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running (both docker cp calls were made) */
        self::assertSame('multi-copy', $started->getName());
    }

    public function testExceptionWhenContainerInspectReturnsEmpty(): void
    {
        /** @Given a container whose inspect returns empty data */
        $container = TestableGenericDockerContainer::createWith(
            name: 'ghost',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns a valid ID but empty inspect */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: []);

        /** @Then a DockerContainerNotFound exception should be thrown */
        $this->expectException(DockerContainerNotFound::class);
        $this->expectExceptionMessage('Docker container with name <ghost> was not found.');

        /** @When the container is started */
        $container->run();
    }

    public function testRunCommandLineIncludesAutoRemoveByDefault(): void
    {
        /** @Given a container with default configuration */
        $container = TestableGenericDockerContainer::createWith(
            name: 'rm-cmd',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'rm-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain --rm */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--rm', $runCommand);
    }

    public function testRunCommandLineIncludesEnvironmentVariable(): void
    {
        /** @Given a container with an environment variable */
        $container = TestableGenericDockerContainer::createWith(
            name: 'env-cmd',
            image: 'alpine:latest',
            client: $this->client
        )->withEnvironmentVariable(key: 'APP_ENV', value: 'production');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'env-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain the environment variable argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--env APP_ENV=production', $runCommand);
    }

    public function testContainerWithZeroHostPortDropsItFromResult(): void
    {
        /** @Given a container whose inspect payload reports a binding with HostPort equal to zero */
        $container = TestableGenericDockerContainer::createWith(
            name: 'zero-host-port',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns an inspect response with HostPort zero alongside a valid binding */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'zero-host-port',
                exposedPorts: ['80/tcp' => (object)[], '443/tcp' => (object)[]],
                hostPortBindings: [
                    '80/tcp'  => [['HostIp' => '0.0.0.0', 'HostPort' => '0']],
                    '443/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '8443']]
                ]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then only strictly positive host ports should be retained */
        self::assertSame([8443], $started->getAddress()->getPorts()->hostPorts());
    }

    public function testExceptionWhenDockerReturnsEmptyContainerId(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'empty-id',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns an empty container ID */
        $this->client->withDockerRunResponse(output: '   ');

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID cannot be empty.');

        /** @When the container is started */
        $container->run();
    }

    public function testRunCommandLineIncludesMultiplePortMappings(): void
    {
        /** @Given a container with multiple port mappings */
        $container = TestableGenericDockerContainer::createWith(
            name: 'multi-port-cmd',
            image: 'nginx:latest',
            client: $this->client
        )
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'multi-port-cmd'
            )
        );

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should contain both port mapping arguments */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--publish 8080:80', $runCommand);
        self::assertStringContainsString('--publish 8443:443', $runCommand);
    }

    public function testContainerWithBindingMissingHostPortIsSkipped(): void
    {
        /** @Given a container whose inspect payload includes a binding with no HostPort key */
        $container = TestableGenericDockerContainer::createWith(
            name: 'no-host-port-key',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns an inspect response with a partial binding */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'no-host-port-key',
                exposedPorts: ['80/tcp' => (object)[]],
                hostPortBindings: [
                    '80/tcp' => [['HostIp' => '0.0.0.0']]
                ]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then bindings lacking HostPort should be skipped without errors */
        self::assertEmpty($started->getAddress()->getPorts()->hostPorts());
    }

    public function testRunCommandLineExcludesAutoRemoveWhenDisabled(): void
    {
        /** @Given a container with auto-remove disabled */
        $container = TestableGenericDockerContainer::createWith(
            name: 'no-rm-cmd',
            image: 'alpine:latest',
            client: $this->client
        )->withoutAutoRemove();

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'no-rm-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker run command should NOT contain --rm */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringNotContainsString('--rm', $runCommand);
    }

    public function testRunContainerWithMultipleEnvironmentVariables(): void
    {
        /** @Given a container with multiple environment variables */
        $container = TestableGenericDockerContainer::createWith(
            name: 'multi-env',
            image: 'alpine:latest',
            client: $this->client
        )
            ->withEnvironmentVariable(key: 'DB_HOST', value: 'localhost')
            ->withEnvironmentVariable(key: 'DB_PORT', value: '5432')
            ->withEnvironmentVariable(key: 'DB_NAME', value: 'mydb');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'multi-env',
                environment: ['DB_HOST=localhost', 'DB_PORT=5432', 'DB_NAME=mydb']
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then all environment variables should be accessible */
        self::assertSame(
            'localhost',
            $started->getEnvironmentVariables()->getValueBy(key: 'DB_HOST')
        );
        self::assertSame('5432', $started->getEnvironmentVariables()->getValueBy(key: 'DB_PORT'));
        self::assertSame('mydb', $started->getEnvironmentVariables()->getValueBy(key: 'DB_NAME'));
    }

    public function testExceptionWhenDockerReturnsTooShortContainerId(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'short-id',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns a too-short container ID */
        $this->client->withDockerRunResponse(output: 'abc123');

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID <abc123> is too short. Minimum length is <12> characters.');

        /** @When the container is started */
        $container->run();
    }

    public function testRunContainerThenInspectCommandTargetsContainerId(): void
    {
        /** @Given a container configured with an image and a name */
        $container = TestableGenericDockerContainer::createWith(
            name: 'inspect-cmd',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns a valid container ID and inspect response */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'inspect-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then a docker inspect command should have been executed for the container ID */
        $inspectCommand = $this->client->getExecutedCommandLines()[1];
        self::assertSame(sprintf('docker inspect %s', InspectResponseFixture::shortContainerId()), $inspectCommand);
    }

    public function testRunContainerWhenStartedFreshThenWasReusedIsFalse(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'fresh-run',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'fresh-run'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the started container should report that it was not reused */
        self::assertFalse($started->wasReused());
    }

    public function testEnvironmentVariableReturnsEmptyStringForMissingKey(): void
    {
        /** @Given a running container with known environment variables */
        $container = TestableGenericDockerContainer::createWith(
            name: 'env-test',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'env-test',
                environment: ['KNOWN=value']
            )
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When querying for a missing environment variable */
        $missingValue = $started->getEnvironmentVariables()->getValueBy(key: 'MISSING');

        /** @Then it should return an empty string */
        self::assertSame('', $missingValue);
    }

    public function testStopContainerWhenTimeoutIsZeroThenStopIsSuccessful(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'stop-zero',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-zero'));
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a zero graceful timeout */
        $stopped = $started->stop(timeoutInWholeSeconds: 0);

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testRunIfNotExistsWhenContainerExistsThenStartedWasReused(): void
    {
        /** @Given a container that already exists */
        $container = TestableGenericDockerContainer::createWith(
            name: 'reused-flag',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker list returns the existing container ID */
        $this->client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'reused-flag'));

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the started container should report that it was reused */
        self::assertTrue($started->wasReused());
    }

    #[RunInSeparateProcess]
    public function testGetPortForConnectionWhenInsideDockerReturnsExposedPort(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_inside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container with a host port mapping */
        $container = TestableGenericDockerContainer::createWith(
            name: 'conn-port',
            image: 'mysql:8.4',
            client: $client
        )->withPortMapping(portOnHost: 33060, portOnContainer: 3306);

        /** @And the Docker daemon returns valid responses */
        $client->withDockerRunResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries exposed and host-mapped ports */
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'conn-port',
                exposedPorts: ['3306/tcp' => (object)[]],
                hostPortBindings: ['3306/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '33060']]]
            )
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When the connection port is resolved inside Docker */
        $port = $started->getAddress()->getPorts()->getPortForConnection();

        /** @Then it should be the container-internal exposed port */
        self::assertSame(3306, $port);
    }

    #[RunInSeparateProcess]
    public function testRunIfNotExistsWhenOutsideDockerThenSkipsReaperCreation(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_outside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container that already exists, running outside a Docker environment */
        $container = TestableGenericDockerContainer::createWith(
            name: 'reaper-outside',
            image: 'alpine:latest',
            client: $client
        );

        /** @And the Docker list returns an existing container */
        $client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'reaper-outside'));

        /** @When runIfNotExists is called */
        $container->runIfNotExists();

        /** @Then no reaper container should have been started */
        self::assertStringNotContainsString(
            'tiny-blocks-reaper',
            implode(PHP_EOL, $client->getExecutedCommandLines())
        );
    }

    public function testRunWhenGateHoldsThenStartedContainerIsPassedToCallback(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'gate-open',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker list is empty so a fresh container is started */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'gate-open'));

        /** @And a callback that captures the container it receives */
        $received = null;

        /** @When runWhen is invoked with a gate that holds */
        $container->runWhen(
            gate: static fn(): bool => true,
            then: static function (ContainerStarted $started) use (&$received): void {
                $received = $started;
            }
        );

        /** @Then the callback should have received the started container */
        self::assertSame('gate-open', $received?->getName());

        /** @And a docker run command should have been executed */
        self::assertStringContainsString('docker run', implode(PHP_EOL, $this->client->getExecutedCommandLines()));
    }

    #[RunInSeparateProcess]
    public function testRunIfNotExistsSkipsReaperCreationWhenReaperAlreadyExists(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_inside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a container that already exists */
        $client = new ClientMock();
        $container = TestableGenericDockerContainer::createWith(
            name: 'reaper-skip',
            image: 'alpine:latest',
            client: $client
        );

        /** @And the Docker list returns an existing container */
        $client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'reaper-skip')
        );

        /** @And the reaper container already exists */
        $client->withDockerListResponse(output: 'existing-reaper-id');

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the container should be returned */
        self::assertSame('reaper-skip', $started->getName());

        /** @And the reused container should have probed for the reaper list to exist */
        $commandLines = $client->getExecutedCommandLines();
        $reaperListed = false;

        foreach ($commandLines as $commandLine) {
            $reaperListed = $reaperListed || str_contains($commandLine, 'tiny-blocks-reaper-reaper-skip');
            self::assertStringNotContainsString(
                'docker run --rm -d --name tiny-blocks-reaper',
                $commandLine
            );
        }
        self::assertTrue($reaperListed);
    }

    public function testRunIfNotExistsThenListCommandFiltersByExactContainerName(): void
    {
        /** @Given a container that already exists */
        $container = TestableGenericDockerContainer::createWith(
            name: 'list-cmd',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker list returns the existing container ID */
        $this->client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'list-cmd'));

        /** @When runIfNotExists is called */
        $container->runIfNotExists();

        /** @Then the first command should list containers filtered by the exact name */
        $listCommand = $this->client->getExecutedCommandLines()[0];
        self::assertSame('docker ps --all --quiet --filter name=^list-cmd$', $listCommand);
    }

    #[RunInSeparateProcess]
    public function testGetPortForConnectionWhenOutsideDockerReturnsHostMappedPort(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_outside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container with a host port mapping */
        $container = TestableGenericDockerContainer::createWith(
            name: 'conn-port',
            image: 'mysql:8.4',
            client: $client
        )->withPortMapping(portOnHost: 33060, portOnContainer: 3306);

        /** @And the Docker daemon returns valid responses */
        $client->withDockerRunResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries exposed and host-mapped ports */
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'conn-port',
                exposedPorts: ['3306/tcp' => (object)[]],
                hostPortBindings: ['3306/tcp' => [['HostIp' => '0.0.0.0', 'HostPort' => '33060']]]
            )
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When the connection port is resolved outside Docker */
        $port = $started->getAddress()->getPorts()->getPortForConnection();

        /** @Then it should be the host-mapped port */
        self::assertSame(33060, $port);
    }

    public function testRunContainerWhenExposedPortIsZeroThenItIsDroppedFromResult(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'zero-exposed',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns an inspect response with a zero exposed port alongside a valid one */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'zero-exposed',
                exposedPorts: ['0/tcp' => (object)[], '3306/tcp' => (object)[]]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then only the strictly positive exposed port should be retained */
        self::assertSame([3306], $started->getAddress()->getPorts()->exposedPorts());
    }

    #[RunInSeparateProcess]
    public function testRunIfNotExistsWhenReaperIsMissingThenStartsReaperContainer(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_inside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container that already exists */
        $container = TestableGenericDockerContainer::createWith(
            name: 'reaper-start',
            image: 'alpine:latest',
            client: $client
        );

        /** @And the Docker list returns an existing container */
        $client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'reaper-start'));

        /** @And no reaper container is currently listed */
        $client->withDockerListResponse(output: '');

        /** @When runIfNotExists is called */
        $container->runIfNotExists();

        /** @Then a reaper container should have been started with the docker run command */
        $commandLines = implode(PHP_EOL, $client->getExecutedCommandLines());
        self::assertStringContainsString('docker run --rm -d --name tiny-blocks-reaper-reaper-start', $commandLines);

        /** @And the reaper script should watch the test-runner hostname before pruning */
        self::assertStringContainsString('while docker inspect', $commandLines);
    }

    public function testContainerWithMixedValidAndNullHostBindingsRetainsValidPorts(): void
    {
        /** @Given a container whose inspect payload mixes valid and null host bindings */
        $container = TestableGenericDockerContainer::createWith(
            name: 'mixed-bindings',
            image: 'nginx:latest',
            client: $this->client
        )
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon returns an inspect response with a trailing null binding */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'mixed-bindings',
                exposedPorts: ['80/tcp' => (object)[], '443/tcp' => (object)[], '5432/tcp' => (object)[]],
                hostPortBindings: [
                    '80/tcp'   => [['HostIp' => '0.0.0.0', 'HostPort' => '8080']],
                    '443/tcp'  => [['HostIp' => '0.0.0.0', 'HostPort' => '8443']],
                    '5432/tcp' => null
                ]
            )
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the host-mapped ports should retain all previously collected ports when a null binding follows */
        self::assertSame([8080, 8443], $started->getAddress()->getPorts()->hostPorts());
    }

    #[RunInSeparateProcess]
    public function testGetHostForConnectionWhenOutsideDockerReturnsLoopbackAddress(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_outside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container with a known hostname */
        $container = TestableGenericDockerContainer::createWith(
            name: 'conn-host',
            image: 'alpine:latest',
            client: $client
        );

        /** @And the Docker daemon returns valid responses */
        $client->withDockerRunResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the hostname */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'conn-host'));

        /** @And the container is started */
        $started = $container->run();

        /** @When the connection host is resolved outside Docker */
        $host = $started->getAddress()->getHostForConnection();

        /** @Then it should be the loopback address */
        self::assertSame('127.0.0.1', $host);
    }

    #[RunInSeparateProcess]
    public function testRunContainerWithNetworkWhenOutsideDockerSkipsHostConnection(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_outside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a container configured with a network, running outside a Docker environment */
        $client = new ClientMock();
        $container = TestableGenericDockerContainer::createWith(
            name: 'outside-docker',
            image: 'alpine:latest',
            client: $client
        )->withNetwork(name: 'my-network');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'outside-docker')
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('outside-docker', $started->getName());

        /** @And no network connect command should have been executed for the host */
        $commandLines = $client->getExecutedCommandLines();

        foreach ($commandLines as $commandLine) {
            self::assertStringNotContainsString(
                'docker network connect',
                $commandLine
            );
        }
    }

    public function testRunIfNotExistsWhenContainerIsMissingThenStartedWasNotReused(): void
    {
        /** @Given a container that does not exist */
        $container = TestableGenericDockerContainer::createWith(
            name: 'fresh-flag',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker list returns empty so the container is created */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'fresh-flag'));

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the started container should report that it was not reused */
        self::assertFalse($started->wasReused());
    }

    public function testStopContainerWhenTimeoutIsNegativeThenStopTimeoutOutOfRange(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'stop-negative',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'stop-negative')
        );

        /** @And the container is started */
        $started = $container->run();

        /** @Then an exception indicating the graceful timeout is out of range should be thrown */
        $this->expectException(StopTimeoutOutOfRange::class);
        $this->expectExceptionMessage('Graceful stop timeout must be zero or greater, got <-1> seconds.');

        /** @When the container is stopped with a negative graceful timeout */
        $started->stop(timeoutInWholeSeconds: -1);
    }

    public function testExceptionWhenRunFailsRendersCommandWithShellEscapedArguments(): void
    {
        /** @Given a container that will fail to start */
        $container = TestableGenericDockerContainer::createWith(
            name: 'fail-render-test',
            image: 'invalid:image',
            client: $this->client
        );

        /** @And the Docker daemon returns a failure */
        $this->client->withDockerRunResponse(output: 'boom', isSuccessful: false);

        /** @Then the failure message should render each argument shell-escaped */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches("/'docker' 'run'/");

        /** @When the container is started */
        $container->run();
    }

    #[RunInSeparateProcess]
    public function testGetHostForConnectionWhenInsideDockerReturnsContainerHostname(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_inside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container with a known hostname */
        $container = TestableGenericDockerContainer::createWith(
            name: 'conn-host',
            image: 'alpine:latest',
            client: $client
        );

        /** @And the Docker daemon returns valid responses */
        $client->withDockerRunResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the hostname */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'conn-host'));

        /** @And the container is started */
        $started = $container->run();

        /** @When the connection host is resolved inside Docker */
        $host = $started->getAddress()->getHostForConnection();

        /** @Then it should be the container hostname */
        self::assertSame('conn-host', $host);
    }

    #[RunInSeparateProcess]
    public function testRunContainerWithNetworkWhenInsideDockerConnectsHostToNetwork(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_inside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container configured with a network, running inside a Docker environment */
        $container = TestableGenericDockerContainer::createWith(
            name: 'inside-docker',
            image: 'alpine:latest',
            client: $client
        )->withNetwork(name: 'my-network');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerRunResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the hostname */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'inside-docker'));

        /** @When the container is started */
        $container->run();

        /** @Then a docker network connect command should connect the host to the network */
        $connectCommand = $client->getExecutedCommandLines()[1];
        self::assertStringStartsWith('docker network connect my-network', $connectCommand);
    }

    public function testRunIfNotExistsTreatsWhitespaceOnlyListOutputAsMissingContainer(): void
    {
        /** @Given a container that does not exist according to a whitespace-only docker list response */
        $container = TestableGenericDockerContainer::createWith(
            name: 'whitespace-list',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker list returns only whitespace */
        $this->client->withDockerListResponse(output: "   \n\t ");

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'whitespace-list')
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then a new container should be created because the whitespace list output is trimmed */
        self::assertSame('whitespace-list', $started->getName());
    }

    public function testRunContainerWhenContainerIdIsExactlyMinimumLengthThenIdIsAccepted(): void
    {
        /** @Given a container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'min-id',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns a container ID exactly at the minimum length */
        $this->client->withDockerRunResponse(output: '123456789012');
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'min-id'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the full minimum-length ID should be accepted and preserved */
        self::assertSame('123456789012', $started->getId());
    }

    #[RunInSeparateProcess]
    public function testRunIfNotExistsWhenReaperListIsWhitespaceThenStartsReaperContainer(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_inside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container that already exists */
        $container = TestableGenericDockerContainer::createWith(
            name: 'reaper-blank',
            image: 'alpine:latest',
            client: $client
        );

        /** @And the Docker list returns an existing container */
        $client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'reaper-blank'));

        /** @And the reaper list response contains only whitespace */
        $client->withDockerListResponse(output: "   \n\t ");

        /** @When runIfNotExists is called */
        $container->runIfNotExists();

        /** @Then a reaper container should have been started because the whitespace list is treated as empty */
        self::assertStringContainsString(
            'docker run --rm -d --name tiny-blocks-reaper-reaper-blank',
            implode(PHP_EOL, $client->getExecutedCommandLines())
        );
    }

    public function testStopContainerWhenCustomTimeoutThenProcessTimeoutExceedsGracePeriod(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'stop-buffer',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'stop-buffer')
        );
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a custom graceful timeout */
        $started->stop(timeoutInWholeSeconds: 45);

        /** @Then the process timeout requested exceeds the graceful period by the safety buffer */
        self::assertSame([55], $this->client->getRequestedTimeouts());
    }

    public function testStopContainerWhenCustomTimeoutThenCommandCarriesTimeoutAsStringArgument(): void
    {
        /** @Given a running container */
        $container = TestableGenericDockerContainer::createWith(
            name: 'stop-args',
            image: 'alpine:latest',
            client: $this->client
        );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-args'));
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a custom timeout */
        $started->stop(timeoutInWholeSeconds: 45);

        /** @Then the stop command arguments should carry the timeout as a string */
        $stopArguments = $this->client->getExecutedArguments()[2];
        self::assertSame(
            ['docker', 'stop', '--time', '45', InspectResponseFixture::shortContainerId()],
            $stopArguments
        );
    }
}
