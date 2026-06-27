<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\TestCase;
use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\Internal\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Internal\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;

final class MySQLDockerContainerTest extends TestCase
{
    private ClientMock $client;

    protected function setUp(): void
    {
        $this->client = new ClientMock();
    }

    public function testGetJdbcUrlWithoutOptions(): void
    {
        /** @Given a running MySQL container */
        $started = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_adm',
            hostname: 'test-db',
            port: 3306
        );

        /** @When getting the JDBC URL with empty options */
        $jdbcUrl = $started->getJdbcUrl(options: []);

        /** @Then the URL should not include any query string */
        self::assertSame('jdbc:mysql://test-db:3306/test_adm', $jdbcUrl);
    }

    public function testGetJdbcUrlWithCustomOptions(): void
    {
        /** @Given a running MySQL container */
        $started = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_adm',
            hostname: 'test-db',
            port: 3306
        );

        /** @When getting the JDBC URL with custom options */
        $jdbcUrl = $started->getJdbcUrl(options: ['connectTimeout' => '5000', 'useSSL' => 'true']);

        /** @Then the URL should include the custom options */
        self::assertSame(
            'jdbc:mysql://test-db:3306/test_adm?connectTimeout=5000&useSSL=true',
            $jdbcUrl
        );
    }

    public function testCustomReadinessTimeoutIsUsed(): void
    {
        /** @Given a MySQL container with a custom readiness timeout */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'timeout-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeoutInSeconds: 60);

        /** @And the Docker daemon starts the container */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'timeout-db',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check succeeds on first attempt */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then the container should start successfully */
        self::assertSame('timeout-db', $started->getName());
    }

    public function testGetJdbcUrlWithDefaultOptions(): void
    {
        /** @Given a running MySQL container */
        $started = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_adm',
            hostname: 'test-db',
            port: 3306
        );

        /** @When getting the JDBC URL with default options */
        $jdbcUrl = $started->getJdbcUrl();

        /** @Then the URL should include default JDBC options */
        $options = implode('&', [
            'useSSL=false',
            'useUnicode=yes',
            'characterEncoding=UTF-8',
            'allowPublicKeyRetrieval=true'
        ]);
        $template = 'jdbc:mysql://test-db:3306/test_adm?%s';

        self::assertSame(sprintf($template, $options), $jdbcUrl);
    }

    public function testRunMySQLContainerSuccessfully(): void
    {
        /** @Given a MySQL container with full configuration */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'test-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withNetwork(name: 'my-net')
            ->withTimezone(timezone: 'America/Sao_Paulo')
            ->withUsername(user: 'app_user')
            ->withPassword(password: 'secret')
            ->withDatabase(database: 'test_adm')
            ->withPortMapping(portOnHost: 3306, portOnContainer: 3306)
            ->withRootPassword(rootPassword: 'root')
            ->withGrantedHosts()
            ->withoutAutoRemove()
            ->withVolumeMapping(pathOnHost: '/var/lib/mysql', pathOnContainer: '/var/lib/mysql');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'test-db',
                environment: [
                    'TZ=America/Sao_Paulo',
                    'MYSQL_USER=app_user',
                    'MYSQL_PASSWORD=secret',
                    'MYSQL_DATABASE=test_adm',
                    'MYSQL_ROOT_PASSWORD=root'
                ],
                networkName: 'my-net',
                exposedPorts: ['3306/tcp' => (object)[]]
            )
        );

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the database setup command succeeds */
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then it should return a MySQLContainerStarted instance */
        self::assertSame('test-db', $started->getName());
        self::assertSame(InspectResponseFixture::shortContainerId(), $started->getId());

        /** @And the environment variables should be accessible */
        self::assertSame(
            'test_adm',
            $started->getEnvironmentVariables()->getValueBy(
                key: 'MYSQL_DATABASE'
            )
        );
        self::assertSame(
            'app_user',
            $started->getEnvironmentVariables()->getValueBy(
                key: 'MYSQL_USER'
            )
        );
        self::assertSame(
            'secret',
            $started->getEnvironmentVariables()->getValueBy(
                key: 'MYSQL_PASSWORD'
            )
        );
        self::assertSame(
            'root',
            $started->getEnvironmentVariables()->getValueBy(
                key: 'MYSQL_ROOT_PASSWORD'
            )
        );

        /** @And the port should be exposed */
        self::assertSame(3306, $started->getAddress()->getPorts()->firstExposedPort());

        /** @And the docker run command should reflect delegated configuration */
        $commandLines = $this->client->getExecutedCommandLines();
        $runCommand = $commandLines[2];

        self::assertStringNotContainsString('--rm', $runCommand);
        self::assertStringContainsString('--volume /var/lib/mysql:/var/lib/mysql', $runCommand);
        self::assertStringContainsString('--publish 3306:3306', $runCommand);
        self::assertStringContainsString('TZ=America/Sao_Paulo', $runCommand);
        self::assertStringContainsString('MYSQL_USER=app_user', $runCommand);
        self::assertStringContainsString('MYSQL_PASSWORD=secret', $runCommand);
        self::assertStringContainsString('MYSQL_DATABASE=test_adm', $runCommand);
        self::assertStringContainsString('MYSQL_ROOT_PASSWORD=root', $runCommand);

        /** @And the readiness probe should execute env-prefixed mysqladmin ping via docker exec */
        $readinessCommand = $commandLines[4];

        self::assertStringContainsString(
            'docker exec test-db env MYSQL_PWD=root mysqladmin ping -h 127.0.0.1',
            $readinessCommand
        );

        /** @And the database setup should include CREATE DATABASE, GRANT, and FLUSH */
        $setupCommand = $commandLines[5];

        self::assertStringContainsString('CREATE DATABASE IF NOT EXISTS test_adm', $setupCommand);
        self::assertStringContainsString('GRANT ALL PRIVILEGES', $setupCommand);
        self::assertStringContainsString('FLUSH PRIVILEGES', $setupCommand);

        /** @And the GRANT statements should include both default hosts */
        self::assertStringContainsString("'root'@'%'", $setupCommand);
        self::assertStringContainsString("'root'@'172.%'", $setupCommand);
    }

    public function testRunMySQLContainerWithPullImage(): void
    {
        /** @Given a MySQL container with image pulling enabled */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'pull-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->pullImage();

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'pull-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @When the container is started (waiting for the image pull to complete first) */
        $started = $container->run();

        /** @Then the container should be running */
        self::assertSame('pull-db', $started->getName());

        /** @And the docker pull command should have been executed */
        $commandLines = $this->client->getExecutedCommandLines();

        self::assertStringContainsString('docker pull mysql:8.1', implode(PHP_EOL, $commandLines));
    }

    public function testRunMySQLContainerWithoutDatabase(): void
    {
        /** @Given a MySQL container without a database configured */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'no-db',
            image: 'mysql:8.1',
            client: $this->client
        )->withRootPassword(rootPassword: 'root');

        /** @And the Docker daemon returns valid responses with no MYSQL_DATABASE */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'no-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @When the MySQL container is started (no CREATE DATABASE should be called) */
        $started = $container->run();

        /** @Then the container should start without errors */
        self::assertSame('no-db', $started->getName());
    }

    public function testMySQLContainerDelegatesGetAddress(): void
    {
        /** @Given a running MySQL container */
        $started = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_adm',
            hostname: 'address-db',
            port: 3306
        );

        /** @When getting the container address */
        $address = $started->getAddress();

        /** @Then the address should delegate correctly */
        self::assertSame('address-db', $address->getHostname());
        self::assertSame('172.22.0.2', $address->getIp());
        self::assertSame(3306, $address->getPorts()->firstExposedPort());
        self::assertSame([3306], $address->getPorts()->exposedPorts());
    }

    public function testRunMySQLContainerWithWaitBeforeRun(): void
    {
        /** @Given a MySQL container with a wait-before-run condition */
        $condition = $this->createMock(ContainerReady::class);
        $condition->expects(self::once())->method('isReady')->willReturn(true);

        /** @And the container is configured */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'wait-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->withWaitBeforeRun(
                wait: ContainerWaitForDependency::untilReady(condition: $condition)
            );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'wait-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @When the container is started */
        $started = $container->run();

        /** @Then the wait-before-run condition should have been evaluated */
        self::assertSame('wait-db', $started->getName());
    }

    public function testExceptionWhenMySQLNeverBecomesReady(): void
    {
        /** @Given a MySQL container with a very short readiness timeout */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'stuck-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeoutInSeconds: 1);

        /** @And the Docker daemon starts the container */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'stuck-db',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check always fails */
        for ($index = 0; $index < 100; $index++) {
            $this->client->withDockerExecuteResponse(output: 'mysqld is not ready', isSuccessful: false);
        }

        /** @Then a ContainerWaitTimeout exception should be thrown */
        $this->expectException(ContainerWaitTimeout::class);
        $this->expectExceptionMessage('Container readiness check timed out after <1> seconds.');

        /** @When attempting to start the MySQL container */
        $container->run();
    }

    public function testMySQLContainerDelegatesStopCorrectly(): void
    {
        /** @Given a running MySQL container */
        $started = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_adm',
            hostname: 'stop-db',
            port: 3306
        );

        /** @And the Docker stop command succeeds */
        $this->client->withDockerStopResponse(output: '');

        /** @When the container is stopped */
        $stopped = $started->stop();

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testRemoveDelegatesToUnderlyingContainer(): void
    {
        /** @Given a running MySQL container */
        $started = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_adm',
            hostname: 'remove-db',
            port: 3306
        );

        /** @When remove is called */
        $started->remove();

        /** @Then the docker rm command should have been executed */
        $commandLines = $this->client->getExecutedCommandLines();
        $removeCommand = $commandLines[4];

        self::assertStringContainsString('docker rm --force --volumes', $removeCommand);
    }

    public function testRunMySQLContainerWithCopyToContainer(): void
    {
        /** @Given a MySQL container with files to copy */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'copy-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->copyToContainer(pathOnHost: '/host/init', pathOnContainer: '/docker-entrypoint-initdb.d');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'copy-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should be running with copy instructions executed */
        self::assertSame('copy-db', $started->getName());

        /** @And the docker cp command should have been executed */
        $commandLines = $this->client->getExecutedCommandLines();

        self::assertNotEmpty(
            array_filter(
                $commandLines,
                static fn(string $line): bool => str_contains($line, 'docker cp') && str_contains($line, '/host/init')
            )
        );
    }

    public function testRunMySQLContainerWithoutGrantedHosts(): void
    {
        /** @Given a MySQL container without granted hosts */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'no-grants',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'no-grants',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness and CREATE DATABASE calls succeed */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started (no GRANT PRIVILEGES should be called) */
        $started = $container->run();

        /** @Then the container should start without errors */
        self::assertSame('no-grants', $started->getName());

        /** @And the setup should include CREATE DATABASE but no GRANT statements */
        $commandLines = $this->client->getExecutedCommandLines();
        $setupCommand = $commandLines[3];

        self::assertStringContainsString('CREATE DATABASE IF NOT EXISTS test_db', $setupCommand);
        self::assertStringNotContainsString('GRANT ALL PRIVILEGES', $setupCommand);
    }

    public function testRunIfNotExistsCreatesNewMySQLContainer(): void
    {
        /** @Given a MySQL container that does not exist */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'new-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withDatabase(database: 'new_db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the Docker list returns empty (container does not exist) */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'new-db',
                environment: ['MYSQL_DATABASE=new_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check and CREATE DATABASE succeed */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then a new container should be created */
        self::assertSame('new-db', $started->getName());
    }

    public function testRunMySQLContainerWithSingleGrantedHost(): void
    {
        /** @Given a MySQL container with a single granted host */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'single-grant',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withGrantedHosts(hosts: ['%']);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'single-grant',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And readiness and database setup succeed */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the container is started */
        $started = $container->run();

        /** @Then the container should start successfully */
        self::assertSame('single-grant', $started->getName());
    }

    public function testMySQLContainerDelegatesExecuteAfterStarted(): void
    {
        /** @Given a running MySQL container */
        $started = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_adm',
            hostname: 'exec-db',
            port: 3306
        );

        /** @And a command execution returns output */
        $this->client->withDockerExecuteResponse(output: 'SHOW DATABASES output');

        /** @When commands are executed inside the container */
        $execution = $started->executeAfterStarted(commands: ['mysql', '-e', 'SHOW DATABASES']);

        /** @Then the execution should return the output */
        self::assertTrue($execution->isSuccessful());
        self::assertSame('SHOW DATABASES output', $execution->getOutput());
    }

    public function testRunIfNotExistsReturnsMySQLContainerStarted(): void
    {
        /** @Given a MySQL container */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'existing-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withDatabase(database: 'my_db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the container already exists */
        $this->client->withDockerListResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'existing-db',
                environment: ['MYSQL_DATABASE=my_db', 'MYSQL_ROOT_PASSWORD=root'],
                exposedPorts: ['3306/tcp' => (object)[]]
            )
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then it should return a MySQLContainerStarted wrapping the existing container */
        self::assertSame('existing-db', $started->getName());
    }

    public function testMySQLContainerDelegatesStopWithCustomTimeout(): void
    {
        /** @Given a running MySQL container */
        $started = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_adm',
            hostname: 'stop-timeout-db',
            port: 3306
        );

        /** @And the Docker stop command succeeds */
        $this->client->withDockerStopResponse(output: '');

        /** @When the container is stopped with a custom timeout */
        $stopped = $started->stop(timeoutInWholeSeconds: 10);

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    public function testStopOnShutdownDelegatesToUnderlyingContainer(): void
    {
        /** @Given a ShutdownHook that tracks registration */
        $shutdownHook = new ShutdownHookMock();

        /** @And a running MySQL container using the tracked hook */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'shutdown-db',
            image: 'mysql:8.1',
            client: $this->client,
            shutdownHook: $shutdownHook
        )
            ->withDatabase(database: 'test_adm')
            ->withRootPassword(rootPassword: 'root');

        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'shutdown-db',
                environment: ['MYSQL_DATABASE=test_adm', 'MYSQL_ROOT_PASSWORD=root'],
                exposedPorts: ['3306/tcp' => (object)[]]
            )
        );
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        /** @And the container is started */
        $started = $container->run();

        /** @When stopOnShutdown is called */
        $started->stopOnShutdown();

        /** @Then the shutdown hook should have registered the remove callback */
        self::assertSame(1, $shutdownHook->getRegistrationCount());
    }

    public function testGetJdbcUrlDefaultsToPort3306WhenNoPortExposed(): void
    {
        /** @Given a running MySQL container with no exposed ports */
        $started = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_adm',
            hostname: 'test-db'
        );

        /** @When getting the JDBC URL */
        $jdbcUrl = $started->getJdbcUrl(options: []);

        /** @Then the URL should use the default MySQL port 3306 */
        self::assertSame('jdbc:mysql://test-db:3306/test_adm', $jdbcUrl);
    }

    public function testMySQLContainerWithEnvironmentVariableDirectly(): void
    {
        /** @Given a MySQL container with a custom environment variable */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'env-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->withEnvironmentVariable(key: 'CUSTOM_KEY', value: 'custom_value');

        /** @And the Docker daemon returns valid responses including the custom env var */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'env-db',
                environment: ['MYSQL_ROOT_PASSWORD=root', 'CUSTOM_KEY=custom_value']
            )
        );

        /** @And the MySQL readiness check succeeds */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then the custom environment variable should be accessible */
        self::assertSame(
            'custom_value',
            $started->getEnvironmentVariables()->getValueBy(
                key: 'CUSTOM_KEY'
            )
        );

        /** @And the docker run command should include the custom environment variable */
        $runCommand = $this->client->getExecutedCommandLines()[0];

        self::assertStringContainsString('CUSTOM_KEY=custom_value', $runCommand);
    }

    public function testRunMySQLContainerWithGrantedHostsButNoDatabase(): void
    {
        /** @Given a MySQL container with granted hosts but no database */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'grants-only',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->withGrantedHosts(hosts: ['%']);

        /** @And the Docker daemon returns valid responses without MYSQL_DATABASE */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'grants-only',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check and setup succeed */
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then the container should start successfully */
        self::assertSame('grants-only', $started->getName());

        /** @And the setup should include GRANT and FLUSH but no CREATE DATABASE */
        $commandLines = $this->client->getExecutedCommandLines();
        $setupCommand = $commandLines[3];

        self::assertStringNotContainsString('CREATE DATABASE', $setupCommand);
        self::assertStringContainsString('GRANT ALL PRIVILEGES', $setupCommand);
        self::assertStringContainsString('FLUSH PRIVILEGES', $setupCommand);
    }

    public function testGetJdbcUrlUsesExposedPortWhenDifferentFromDefault(): void
    {
        /** @Given a running MySQL container with a non-default port */
        $started = RunningMySQLContainer::startWith(
            client: $this->client,
            database: 'test_adm',
            hostname: 'custom-port-db',
            port: 3307
        );

        /** @When getting the JDBC URL */
        $jdbcUrl = $started->getJdbcUrl(options: []);

        /** @Then the URL should use the exposed port 3307 instead of the default 3306 */
        self::assertSame('jdbc:mysql://custom-port-db:3307/test_adm', $jdbcUrl);
    }

    public function testRunWhenGateHoldsThenMySQLStartedIsPassedToCallback(): void
    {
        /** @Given a MySQL container */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'gate-db',
            image: 'mysql:8.1',
            client: $this->client
        )->withRootPassword(rootPassword: 'root');

        /** @And the Docker list is empty so a fresh container is started */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'gate-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And a callback that captures the started container it receives */
        $received = null;

        /** @When runWhen is invoked with a gate that holds */
        $container->runWhen(
            gate: static fn(): bool => true,
            then: static function (MySQLContainerStarted $started) use (&$received): void {
                $received = $started;
            }
        );

        /** @Then the callback should have received a MySQL started container */
        self::assertInstanceOf(MySQLContainerStarted::class, $received);

        /** @And the received container should carry the expected name */
        self::assertSame('gate-db', $received->getName());
    }

    public function testRunIfNotExistsWhenContainerExistsThenStartedWasReused(): void
    {
        /** @Given a MySQL container that already exists */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'reused-db',
            image: 'mysql:8.1',
            client: $this->client
        )->withRootPassword(rootPassword: 'root');

        /** @And the Docker list returns the existing container ID */
        $this->client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the Docker inspect returns the container details */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'reused-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @When runIfNotExists is called */
        $started = $container->runIfNotExists();

        /** @Then the started container should report that it was reused */
        self::assertTrue($started->wasReused());
    }

    public function testExceptionWhenMySQLReadinessCheckAlwaysThrowsExceptions(): void
    {
        /** @Given a MySQL container with a very short readiness timeout */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'crash-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeoutInSeconds: 1);

        /** @And the Docker daemon starts the container */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'crash-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check always throws exceptions */
        for ($index = 0; $index < 100; $index++) {
            $this->client->withDockerExecuteException(
                exception: new DockerCommandExecutionFailed(reason: 'container crashed', command: 'docker exec')
            );
        }

        /** @Then a ContainerWaitTimeout exception should be thrown (not DockerCommandExecutionFailed) */
        $this->expectException(ContainerWaitTimeout::class);

        /** @When attempting to start the MySQL container */
        $container->run();
    }

    public function testRunMySQLContainerRetriesReadinessCheckBeforeSucceeding(): void
    {
        /** @Given a MySQL container */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'retry-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeoutInSeconds: 10);

        /** @And the Docker daemon starts the container */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'retry-db',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check fails twice before succeeding */
        $this->client->withDockerExecuteResponse(output: 'not ready', isSuccessful: false);
        $this->client->withDockerExecuteResponse(output: 'not ready', isSuccessful: false);
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the CREATE DATABASE command succeeds */
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then the container should start after retries */
        self::assertSame('retry-db', $started->getName());
    }

    public function testRunMySQLContainerRetriesWhenReadinessCheckThrowsException(): void
    {
        /** @Given a MySQL container */
        $container = TestableMySQLDockerContainer::createWith(
            name: 'exception-db',
            image: 'mysql:8.1',
            client: $this->client
        )
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeoutInSeconds: 10);

        /** @And the Docker daemon starts the container */
        $this->client->withDockerRunResponse(output: InspectResponseFixture::containerId());
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'exception-db',
                environment: ['MYSQL_DATABASE=test_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the MySQL readiness check throws an exception first, then succeeds */
        $this->client->withDockerExecuteException(
            exception: new DockerCommandExecutionFailed(reason: 'container not running', command: 'docker exec')
        );
        $this->client->withDockerExecuteResponse(output: 'mysqld is alive');

        /** @And the CREATE DATABASE command succeeds */
        $this->client->withDockerExecuteResponse(output: '');

        /** @When the MySQL container is started */
        $started = $container->run();

        /** @Then the container should start after the exception was caught and retried */
        self::assertSame('exception-db', $started->getName());
    }
}
