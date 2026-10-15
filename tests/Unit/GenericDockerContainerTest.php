<?php

declare(strict_types=1);

namespace Test\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerContainerNotFound;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitAfterStarted;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;
use TinyBlocks\Time\Duration;

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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-test');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-test'));
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped */
        $stopped = $started->stop(gracePeriod: Duration::fromSeconds(seconds: 10));

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testExecuteAfterStarted(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'exec-test');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'invalid:image', client: $this->client)
            ->withName(name: 'fail-test');

        /** @And the Docker daemon returns a failure */
        $this->client->withDockerCreateResponse(output: 'Cannot connect to the Docker daemon.', isSuccessful: false);

        /** @Then a DockerCommandExecutionFailed exception should be thrown */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches('/Cannot connect to the Docker daemon/');

        /** @When the container is started */
        $container->run();
    }

    public function testRunContainerSuccessfully(): void
    {
        /** @Given a container configured with an image and a name */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'test-alpine');

        /** @And the Docker daemon returns a valid container ID and inspect response */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'cmd-test');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'cmd-test'));

        /** @When the container is started with commands */
        $started = $container->run(commands: ['echo', 'hello']);

        /** @Then the container should be running */
        self::assertSame('cmd-test', $started->getName());
    }

    public function testContainerWithHostPortMapping(): void
    {
        /** @Given a container with a host port mapping */
        $container = GenericDockerContainer::from(image: 'mysql:8.4', client: $this->client)
            ->withName(name: 'host-port')
            ->withPortMapping(portOnHost: 33060, portOnContainer: 3306);

        /** @And the Docker daemon returns a response with host port bindings */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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

    public function testRunCommandLineIncludesNetwork(): void
    {
        /** @Given a container with a network */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'net-cmd')
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'wait-test')
            ->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $condition));

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'wait-test'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running (wait was called) */
        self::assertSame('wait-test', $started->getName());
    }

    public function testRunContainerWithoutAutoRemove(): void
    {
        /** @Given a container with auto-remove disabled */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'persistent')
            ->withoutAutoRemove();

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'persistent'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('persistent', $started->getName());
    }

    public function testStopExecutesDockerStopCommand(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-cmd');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-cmd'));
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped */
        $started->stop(gracePeriod: Duration::fromSeconds(seconds: 10));

        /** @Then a docker stop command should have been executed with the container ID */
        $stopCommand = $this->client->getExecutedCommandLines()[3];
        self::assertStringStartsWith('docker stop', $stopCommand);
        self::assertStringContainsString(InspectResponseFixture::shortContainerId(), $stopCommand);
    }

    public function testRemoveCanBeCalledMultipleTimes(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'already-removed');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'args-cmd');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-timeout');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'stop-timeout')
        );
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a custom timeout */
        $stopped = $started->stop(gracePeriod: Duration::fromSeconds(seconds: 10));

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testRunContainerWithCopyToContainer(): void
    {
        /** @Given a container with files to copy */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'copy-test')
            ->copyToContainer(pathOnHost: '/host/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'wait-new')
            ->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $condition));

        /** @And the Docker list returns empty */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'wait-new'));

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the wait-before-run should have been evaluated and the container created */
        self::assertSame('wait-new', $started->getName());
    }

    public function testRunContainerWithWaitAfterStarted(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'wait-after')
            ->withWaitAfterStarted(
                wait: ContainerWaitForTime::forDuration(duration: Duration::fromSeconds(seconds: 1))
            );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'wait-after'));

        /** @When the container is started with a wait-after condition */
        $start = microtime(true);
        $started = $container->run();
        $elapsed = (microtime(true) - $start);

        /** @Then the container should have waited after starting */
        self::assertSame('wait-after', $started->getName());
        self::assertGreaterThanOrEqual(0.9, $elapsed);
    }

    public function testExecuteAfterStartedReturnsFailure(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'exec-fail');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'port-cmd')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'port-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the executed docker run command should contain the port mapping argument */
        $runCommand = $this->client->getExecutedCommandLines()[0];
        self::assertStringContainsString('--publish 8080:80', $runCommand);
    }

    public function testRunCommandLineIncludesTmpfsMounts(): void
    {
        /** @Given a container with two tmpfs mounts */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'tmpfs-cmd')
            ->withTmpfsMount(pathOnContainer: '/app/cache')
            ->withTmpfsMount(pathOnContainer: '/app/sessions');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'tmpfs-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the docker create command should mount both directories in memory */
        self::assertStringContainsString(
            '--tmpfs /app/cache --tmpfs /app/sessions',
            $this->client->getExecutedCommandLines()[0]
        );
    }

    public function testRunContainerWithAutoGeneratedName(): void
    {
        /** @Given a container without a name */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client);

        /** @And the Docker daemon returns valid responses (with any hostname from KSUID) */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'web-server')
            ->withNetwork(name: 'my-network')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withVolumeMapping(pathOnHost: '/var/www', pathOnContainer: '/usr/share/nginx/html')
            ->withEnvironmentVariable(key: 'NGINX_HOST', value: 'localhost')
            ->withEnvironmentVariable(key: 'NGINX_PORT', value: '80');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'new-container')
            ->withEnvironmentVariable(key: 'APP_ENV', value: 'test');

        /** @And the Docker list returns empty (container does not exist) */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'timeout-wait')
            ->withWaitBeforeRun(
                wait: ContainerWaitForDependency::untilReady(condition: $condition)
                    ->withTimeout(timeout: Duration::fromSeconds(seconds: 1))
            );

        /** @Then a ContainerWaitTimeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When the container is started */
        $container->run();
    }

    public function testRunCommandLineIncludesVolumeMapping(): void
    {
        /** @Given a container with two volume mappings */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'vol-cmd')
            ->withVolumeMapping(pathOnHost: '/host/data', pathOnContainer: '/app/data')
            ->withVolumeMapping(pathOnHost: '/host/logs', pathOnContainer: '/app/logs');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'multi-port')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'no-net');

        /** @And the Docker daemon returns a response with empty address data */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'multi-host-port')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon returns a response with multiple host port bindings */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'force-remove');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'force-remove')
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When remove is called */
        $started->remove();

        /** @Then a docker rm command should have been executed with the container ID */
        $commandLines = $this->client->getExecutedCommandLines();
        $removeCommand = $commandLines[3];

        self::assertStringContainsString('docker rm --force --volumes', $removeCommand);
        self::assertStringContainsString(
            InspectResponseFixture::shortContainerId(),
            $removeCommand
        );

        /** @And a docker network prune command should have been executed with the managed label */
        $pruneCommand = $commandLines[4];

        self::assertStringContainsString(
            'docker network prune --force --filter label=tiny-blocks.docker-container=true',
            $pruneCommand
        );
    }

    public function testContainerWithNoExposedPortsReturnsNull(): void
    {
        /** @Given a container with no exposed ports */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'no-ports');

        /** @And the Docker daemon returns valid responses without exposed ports */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'cp-cmd')
            ->copyToContainer(pathOnHost: '/host/sql', pathOnContainer: '/app/sql');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'cp-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then the second executed command should be a docker cp with the correct arguments */
        $copyCommand = $this->client->getExecutedCommandLines()[1];
        self::assertStringStartsWith('docker cp', $copyCommand);
        self::assertStringContainsString('/host/sql', $copyCommand);
        self::assertStringContainsString('/app/sql', $copyCommand);
    }

    public function testRunContainerWithMultipleVolumeMappings(): void
    {
        /** @Given a container with multiple volume mappings */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'multi-vol')
            ->withVolumeMapping(pathOnHost: '/data', pathOnContainer: '/app/data')
            ->withVolumeMapping(pathOnHost: '/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'multi-vol'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('multi-vol', $started->getName());
    }

    public function testRunIfNotExistsReturnsExistingContainer(): void
    {
        /** @Given a container that already exists */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'existing');

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
        $container = GenericDockerContainer::from(image: 'redis:latest', client: $this->client)
            ->withName(name: 'no-host-bind');

        /** @And the Docker daemon returns a response with exposed port but null host bindings */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'exec-cmd');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'exec-cmd'));
        $this->client->withDockerExecuteResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When executing commands inside the container */
        $started->executeAfterStarted(commands: ['ls', '-la', '/tmp']);

        /** @Then a docker exec command should have been executed with the container name and commands */
        $execCommand = $this->client->getExecutedCommandLines()[3];
        self::assertSame('docker exec exec-cmd ls -la /tmp', $execCommand);
    }

    public function testRunContainerWithMultipleCopyInstructions(): void
    {
        /** @Given a container with multiple copy instructions */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'multi-copy')
            ->copyToContainer(pathOnHost: '/host/sql', pathOnContainer: '/app/sql')
            ->copyToContainer(pathOnHost: '/host/config', pathOnContainer: '/app/config');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'multi-copy'));

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running (both docker cp calls were made) */
        self::assertSame('multi-copy', $started->getName());
    }

    public function testExceptionWhenContainerInspectReturnsEmpty(): void
    {
        /** @Given a container whose inspect returns empty data */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'ghost');

        /** @And the Docker daemon returns a valid ID but empty inspect */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'rm-cmd');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'env-cmd')
            ->withEnvironmentVariable(key: 'APP_ENV', value: 'production');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'zero-host-port');

        /** @And the Docker daemon returns an inspect response with HostPort zero alongside a valid binding */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'empty-id');

        /** @And the Docker daemon returns an empty container ID */
        $this->client->withDockerCreateResponse(output: '   ');

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID cannot be empty.');

        /** @When the container is started */
        $container->run();
    }

    public function testRunCommandLineIncludesMultiplePortMappings(): void
    {
        /** @Given a container with multiple port mappings */
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'multi-port-cmd')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'no-host-port-key');

        /** @And the Docker daemon returns an inspect response with a partial binding */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'no-rm-cmd')
            ->withoutAutoRemove();

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'multi-env')
            ->withEnvironmentVariable(key: 'DB_HOST', value: 'localhost')
            ->withEnvironmentVariable(key: 'DB_PORT', value: '5432')
            ->withEnvironmentVariable(key: 'DB_NAME', value: 'mydb');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'short-id');

        /** @And the Docker daemon returns a too-short container ID */
        $this->client->withDockerCreateResponse(output: 'abc123');

        /** @Then an InvalidArgumentException should be thrown */
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Container ID <abc123> is too short. Minimum length is <12> characters.');

        /** @When the container is started */
        $container->run();
    }

    public function testRunContainerThenInspectCommandTargetsContainerId(): void
    {
        /** @Given a container configured with an image and a name */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'inspect-cmd');

        /** @And the Docker daemon returns a valid container ID and inspect response */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'inspect-cmd'));

        /** @When the container is started */
        $container->run();

        /** @Then a docker inspect command should have been executed for the container ID */
        $inspectCommand = $this->client->getExecutedCommandLines()[2];
        self::assertSame(sprintf('docker inspect %s', InspectResponseFixture::shortContainerId()), $inspectCommand);
    }

    public function testRunWhenStartFailsThenDockerCommandExecutionFailed(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'start-fails');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker daemon fails to start it */
        $this->client->withDockerStartResponse(output: 'port is already allocated', isSuccessful: false);

        /** @Then the failure should name the start command and carry the reason reported by Docker */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches("/<'docker' 'start' '6acae5967be0'>.*Reason: port is already allocated/");

        /** @When the container is started */
        $container->run();
    }

    public function testEnvironmentVariableReturnsEmptyStringForMissingKey(): void
    {
        /** @Given a running container with known environment variables */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'env-test');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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

    public function testRunWhenTwoWaitsBeforeRunAreAddedThenBothAreApplied(): void
    {
        /** @Given a first condition that is immediately ready */
        $first = $this->createMock(ContainerReady::class);
        $first->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a second condition that is immediately ready */
        $second = $this->createMock(ContainerReady::class);
        $second->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a container that waits for both conditions before it runs */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'two-waits-before')
            ->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $first))
            ->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $second));

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'two-waits-before')
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running after both conditions held */
        self::assertSame('two-waits-before', $started->getName());
    }

    public function testStopContainerWhenTimeoutIsZeroThenStopIsSuccessful(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-zero');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-zero'));
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a zero graceful timeout */
        $stopped = $started->stop(gracePeriod: Duration::fromSeconds(seconds: 0));

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    #[RunInSeparateProcess]
    public function testStopOnShutdownWhenProcessExitsThenContainerIsRemoved(): void
    {
        $template = '%s/Internal/Containers/Overrides/register_shutdown_function_spy.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'shutdown-run');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'shutdown-run'));

        /** @And the container is started and registered for removal on shutdown */
        $container->run()->stopOnShutdown();

        /** @When the registered shutdown callback runs */
        $GLOBALS['registeredShutdownCallbacks'][0]();

        /** @Then the container should have been removed */
        self::assertContains(
            sprintf('docker rm --force --volumes %s', InspectResponseFixture::shortContainerId()),
            $client->getExecutedCommandLines()
        );
    }

    public function testRunIfNotExistsWhenContainerExistsThenStartedWasReused(): void
    {
        /** @Given a container that already exists */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'reused-flag');

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
        $container = GenericDockerContainer::from(image: 'mysql:8.4', client: $client)
            ->withName(name: 'conn-port')
            ->withPortMapping(portOnHost: 33060, portOnContainer: 3306);

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'reaper-outside');

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

    #[RunInSeparateProcess]
    public function testRunIfNotExistsSkipsReaperCreationWhenReaperAlreadyExists(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_inside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a container that already exists */
        $client = new ClientMock();
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'reaper-skip');

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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'list-cmd');

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
    public function testStopOnShutdownWhenCalledThenRegistersOneShutdownCallback(): void
    {
        $template = '%s/Internal/Containers/Overrides/register_shutdown_function_spy.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'shutdown-test');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the container details */
        $client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'shutdown-test'));

        /** @And the container is started */
        $started = $container->run();

        /** @When stopOnShutdown is called */
        $started->stopOnShutdown();

        /** @Then exactly one shutdown callback should have been registered */
        self::assertCount(1, $GLOBALS['registeredShutdownCallbacks']);
    }

    public function testStopWhenContainerIsSharedThenStopsTheUnderlyingContainer(): void
    {
        /** @Given a container that already exists */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'shared-stop');

        /** @And the Docker list returns the existing container ID */
        $this->client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'shared-stop'));

        /** @And the shared container is obtained */
        $started = $container->runIfNotExists();

        /** @When the shared container is stopped with a grace period of 30 seconds */
        $started->stop(gracePeriod: Duration::fromSeconds(seconds: 30));

        /** @Then the underlying container should be stopped with that grace period */
        self::assertContains(
            sprintf('docker stop --time 30 %s', InspectResponseFixture::shortContainerId()),
            $this->client->getExecutedCommandLines()
        );
    }

    #[RunInSeparateProcess]
    public function testGetPortForConnectionWhenOutsideDockerReturnsHostMappedPort(): void
    {
        $template = '%s/Internal/Containers/Overrides/file_exists_outside_docker.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a container with a host port mapping */
        $container = GenericDockerContainer::from(image: 'mysql:8.4', client: $client)
            ->withName(name: 'conn-port')
            ->withPortMapping(portOnHost: 33060, portOnContainer: 3306);

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'zero-exposed');

        /** @And the Docker daemon returns an inspect response with a zero exposed port alongside a valid one */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'reaper-start');

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
        $container = GenericDockerContainer::from(image: 'nginx:latest', client: $this->client)
            ->withName(name: 'mixed-bindings')
            ->withPortMapping(portOnHost: 8080, portOnContainer: 80)
            ->withPortMapping(portOnHost: 8443, portOnContainer: 443);

        /** @And the Docker daemon returns an inspect response with a trailing null binding */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'conn-host');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'outside-docker')
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'fresh-flag');

        /** @And the Docker list returns empty so the container is created */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'fresh-flag'));

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the started container should report that it was not reused */
        self::assertFalse($started->wasReused());
    }

    public function testExceptionWhenRunFailsRendersCommandWithShellEscapedArguments(): void
    {
        /** @Given a container that will fail to start */
        $container = GenericDockerContainer::from(image: 'invalid:image', client: $this->client)
            ->withName(name: 'fail-render-test');

        /** @And the Docker daemon returns a failure */
        $this->client->withDockerCreateResponse(output: 'boom', isSuccessful: false);

        /** @Then the failure message should render each argument shell-escaped */
        $this->expectException(DockerCommandExecutionFailed::class);
        $this->expectExceptionMessageMatches("/'docker' 'create'/");

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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'conn-host');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'inside-docker')
            ->withNetwork(name: 'my-network');

        /** @And the Docker daemon returns valid responses */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'whitespace-list');

        /** @And the Docker list returns only whitespace */
        $this->client->withDockerListResponse(output: "   \n\t ");

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'whitespace-list')
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then a new container should be created because the whitespace list output is trimmed */
        self::assertSame('whitespace-list', $started->getName());
    }

    public function testRunWhenFilesAreCopiedThenTheyAreCopiedBeforeTheContainerStarts(): void
    {
        /** @Given a container with a copy instruction */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'cp-before-start')
            ->copyToContainer(pathOnHost: '/host/sql', pathOnContainer: '/app/sql');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'cp-before-start')
        );

        /** @When the container is started */
        $container->run();

        /** @Then the container should be created, receive the files, be started, and only then be inspected */
        self::assertSame(
            ['docker create', 'docker cp', 'docker start', 'docker inspect'],
            array_map(
                static fn(array $arguments): string => implode(' ', array_slice($arguments, 0, 2)),
                $this->client->getExecutedArguments()
            )
        );
    }

    public function testRunWhenTwoWaitsAfterStartedAreAddedThenBothReceiveTheContainer(): void
    {
        /** @Given a first wait strategy applied after the container starts */
        $first = $this->createMock(ContainerWaitAfterStarted::class);
        $first->expects(self::once())->method('waitAfter');

        /** @And a second wait strategy applied after the container starts */
        $second = $this->createMock(ContainerWaitAfterStarted::class);
        $second->expects(self::once())->method('waitAfter');

        /** @And a container that applies both wait strategies after it starts */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'two-waits-after')
            ->withWaitAfterStarted(wait: $first)
            ->withWaitAfterStarted(wait: $second);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'two-waits-after')
        );

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running after both wait strategies were applied */
        self::assertSame('two-waits-after', $started->getName());
    }

    public function testRunContainerWhenContainerIdIsExactlyMinimumLengthThenIdIsAccepted(): void
    {
        /** @Given a container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'min-id');

        /** @And the Docker daemon returns a container ID exactly at the minimum length */
        $this->client->withDockerCreateResponse(output: '123456789012');
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
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $client)
            ->withName(name: 'reaper-blank');

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

    public function testRunIfNotExistsWhenContainerIsReusedThenWaitsAfterStartedAreSkipped(): void
    {
        /** @Given a wait strategy applied after a new container starts */
        $wait = $this->createMock(ContainerWaitAfterStarted::class);
        $wait->expects(self::never())->method('waitAfter');

        /** @And a container that already exists */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'reused-waits')
            ->withWaitAfterStarted(wait: $wait);

        /** @And the Docker list returns the existing container ID */
        $this->client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'reused-waits')
        );

        /** @When the container is asked for */
        $started = $container->runIfNotExists();

        /** @Then the existing container should be reused without applying the wait strategy */
        self::assertTrue($started->wasReused());
    }

    public function testStopContainerWhenCustomTimeoutThenProcessTimeoutExceedsGracePeriod(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-buffer');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(hostname: 'stop-buffer')
        );
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a custom graceful timeout */
        $started->stop(gracePeriod: Duration::fromSeconds(seconds: 45));

        /** @Then the process timeout requested exceeds the graceful period by the safety buffer */
        self::assertSame([55], $this->client->getRequestedTimeouts());
    }

    public function testStopContainerWhenCustomTimeoutThenCommandCarriesTimeoutAsStringArgument(): void
    {
        /** @Given a running container */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withName(name: 'stop-args');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(inspectResult: InspectResponseFixture::build(hostname: 'stop-args'));
        $this->client->withDockerStopResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When the container is stopped with a custom timeout */
        $started->stop(gracePeriod: Duration::fromSeconds(seconds: 45));

        /** @Then the stop command arguments should carry the timeout as a string */
        $stopArguments = $this->client->getExecutedArguments()[3];
        self::assertSame(
            ['docker', 'stop', '--time', '45', InspectResponseFixture::shortContainerId()],
            $stopArguments
        );
    }

    public function testRunToCompletionWhenWaitBeforeRunIsRegisteredThenWaitsBeforeCreatingTheContainer(): void
    {
        /** @Given a condition that is immediately ready */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @And a container with a wait-before-run condition */
        $container = GenericDockerContainer::from(image: 'alpine:latest', client: $this->client)
            ->withWaitBeforeRun(wait: ContainerWaitForDependency::untilReady(condition: $condition));

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @When the container runs to completion */
        $container->runToCompletion(timeout: Duration::fromSeconds(seconds: 10));

        /** @Then the container should be created once the wait is satisfied */
        self::assertStringStartsWith('docker create', $this->client->getExecutedCommandLines()[0]);
    }
}
