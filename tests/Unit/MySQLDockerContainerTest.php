<?php

declare(strict_types=1);

namespace Test\Unit;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Test\Models\InspectResponseFixture;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\MySQL\MySQLContainerStarted;
use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\Time\Duration;

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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'timeout-db')
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeout: Duration::fromSeconds(seconds: 60));

        /** @And the Docker daemon starts the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'test-db')
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
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $readinessCommand = $commandLines[5];

        self::assertStringContainsString(
            'docker exec test-db env MYSQL_PWD=root mysqladmin ping -h 127.0.0.1',
            $readinessCommand
        );

        /** @And the database setup should include CREATE DATABASE, GRANT, and FLUSH */
        $setupCommand = $commandLines[6];

        self::assertStringContainsString('CREATE DATABASE IF NOT EXISTS test_adm', $setupCommand);
        self::assertStringContainsString('GRANT ALL PRIVILEGES', $setupCommand);
        self::assertStringContainsString('FLUSH PRIVILEGES', $setupCommand);

        /** @And the GRANT statements should include both default hosts */
        self::assertStringContainsString("'root'@'%'", $setupCommand);
        self::assertStringContainsString("'root'@'172.%'", $setupCommand);
    }

    public function testRunMySQLContainerWithoutDatabase(): void
    {
        /** @Given a MySQL container without a database configured */
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'no-db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the Docker daemon returns valid responses with no MYSQL_DATABASE */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'wait-db')
            ->withRootPassword(rootPassword: 'root')
            ->withWaitBeforeRun(
                wait: ContainerWaitForDependency::untilReady(condition: $condition)
            );

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'stuck-db')
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeout: Duration::fromSeconds(seconds: 1));

        /** @And the Docker daemon starts the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $stopped = $started->stop(gracePeriod: Duration::fromSeconds(seconds: 10));

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
        $removeCommand = $commandLines[5];

        self::assertStringContainsString('docker rm --force --volumes', $removeCommand);
    }

    public function testRunMySQLContainerWithCopyToContainer(): void
    {
        /** @Given a MySQL container with files to copy */
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'copy-db')
            ->withRootPassword(rootPassword: 'root')
            ->copyToContainer(pathOnHost: '/host/init', pathOnContainer: '/docker-entrypoint-initdb.d');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'no-grants')
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $setupCommand = $commandLines[4];

        self::assertStringContainsString('CREATE DATABASE IF NOT EXISTS test_db', $setupCommand);
        self::assertStringNotContainsString('GRANT ALL PRIVILEGES', $setupCommand);
    }

    public function testRunIfNotExistsCreatesNewMySQLContainer(): void
    {
        /** @Given a MySQL container that does not exist */
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'new-db')
            ->withDatabase(database: 'new_db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the Docker list returns empty (container does not exist) */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon returns valid run and inspect responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'single-grant')
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withGrantedHosts(hosts: ['%']);

        /** @And the Docker daemon returns valid responses */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'existing-db')
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
        $stopped = $started->stop(gracePeriod: Duration::fromSeconds(seconds: 10));

        /** @Then the stop should be successful */
        self::assertTrue($stopped->isSuccessful());
    }

    #[RunInSeparateProcess]
    public function testStopOnShutdownDelegatesToUnderlyingContainer(): void
    {
        $template = '%s/Internal/Containers/Overrides/register_shutdown_function_spy.php';
        require_once sprintf($template, __DIR__);

        /** @Given a Docker client */
        $client = new ClientMock();

        /** @And a MySQL container */
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $client)
            ->withName(name: 'shutdown-db')
            ->withDatabase(database: 'test_adm')
            ->withRootPassword(rootPassword: 'root');

        /** @And the Docker daemon creates the container */
        $client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the MySQL settings */
        $client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'shutdown-db',
                environment: ['MYSQL_DATABASE=test_adm', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And the container is started */
        $started = $container->run();

        /** @When stopOnShutdown is called */
        $started->stopOnShutdown();

        /** @Then exactly one shutdown callback should have been registered */
        self::assertCount(1, $GLOBALS['registeredShutdownCallbacks']);
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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'env-db')
            ->withRootPassword(rootPassword: 'root')
            ->withEnvironmentVariable(key: 'CUSTOM_KEY', value: 'custom_value');

        /** @And the Docker daemon returns valid responses including the custom env var */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'grants-only')
            ->withRootPassword(rootPassword: 'root')
            ->withGrantedHosts(hosts: ['%']);

        /** @And the Docker daemon returns valid responses without MYSQL_DATABASE */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $setupCommand = $commandLines[4];

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

    public function testRunIfNotExistsWhenContainerExistsThenStartedWasReused(): void
    {
        /** @Given a MySQL container that already exists */
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'reused-db')
            ->withRootPassword(rootPassword: 'root');

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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'crash-db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeout: Duration::fromSeconds(seconds: 1));

        /** @And the Docker daemon starts the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'retry-db')
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeout: Duration::fromSeconds(seconds: 10));

        /** @And the Docker daemon starts the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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

    public function testWithGrantedHostsThenLeavesTheOriginalDefinitionUntouched(): void
    {
        /** @Given a MySQL container without granted hosts */
        $container = MySQLDockerContainer::from(image: 'mysql:8.4', client: $this->client)
            ->withName(name: 'plain-db')
            ->withRootPassword(rootPassword: 'root');

        /** @And a copy of it granting root access from any host */
        $container->withGrantedHosts();

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the MySQL settings */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'plain-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @When the original definition runs */
        $container->run();

        /** @Then no grant should have been issued */
        self::assertStringNotContainsString('GRANT', implode(PHP_EOL, $this->client->getExecutedCommandLines()));
    }

    public function testRunMySQLContainerRetriesWhenReadinessCheckThrowsException(): void
    {
        /** @Given a MySQL container */
        $container = MySQLDockerContainer::from(image: 'mysql:8.1', client: $this->client)
            ->withName(name: 'exception-db')
            ->withDatabase(database: 'test_db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeout: Duration::fromSeconds(seconds: 10));

        /** @And the Docker daemon starts the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());
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

    public function testRunIfNotExistsWhenContainerIsNewThenWaitsUntilMySQLIsReady(): void
    {
        /** @Given a MySQL container that does not exist yet */
        $container = MySQLDockerContainer::from(image: 'mysql:8.4', client: $this->client)
            ->withName(name: 'booting-db')
            ->withDatabase(database: 'booting_db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the Docker list returns empty */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the MySQL settings */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'booting-db',
                environment: ['MYSQL_DATABASE=booting_db', 'MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And MySQL accepts connections only on the second ping */
        $this->client->withDockerExecuteResponse(output: 'mysqld is not ready', isSuccessful: false);

        /** @When runIfNotExists is called */
        $container->runIfNotExists();

        /** @Then MySQL should have been pinged until ready, and only then the database set up */
        self::assertSame(
            [
                'docker exec booting-db env MYSQL_PWD=root mysqladmin ping -h 127.0.0.1',
                'docker exec booting-db env MYSQL_PWD=root mysqladmin ping -h 127.0.0.1',
                implode(' ', [
                    'docker exec booting-db env MYSQL_PWD=root mysql -uroot -e',
                    'CREATE DATABASE IF NOT EXISTS booting_db; FLUSH PRIVILEGES;'
                ])
            ],
            array_values(
                array_filter(
                    $this->client->getExecutedCommandLines(),
                    static fn(string $commandLine): bool => str_starts_with($commandLine, 'docker exec')
                )
            )
        );
    }

    public function testGetJdbcUrlWhenMySQLContainerIsSharedThenPointsToItsDatabase(): void
    {
        /** @Given a MySQL container reused from another process */
        $started = SharedMySQLContainer::reusedWith(
            client: $this->client,
            database: 'shared_adm',
            hostname: 'shared-db'
        );

        /** @When getting the JDBC URL */
        $jdbcUrl = $started->getJdbcUrl(options: []);

        /** @Then the URL should point to the database of the shared container */
        self::assertSame('jdbc:mysql://shared-db:3306/shared_adm', $jdbcUrl);
    }

    public function testExecuteAfterStartedWhenMySQLContainerIsSharedThenRunsInsideIt(): void
    {
        /** @Given a MySQL container reused from another process */
        $started = SharedMySQLContainer::reusedWith(
            client: $this->client,
            database: 'shared_adm',
            hostname: 'shared-db'
        );

        /** @And the Docker daemon answers the next command */
        $this->client->withDockerExecuteResponse(output: 'shared_adm');

        /** @When a command runs inside the shared container */
        $execution = $started->executeAfterStarted(commands: ['mysql', '-e', 'SHOW DATABASES;']);

        /** @Then the command output should be returned */
        self::assertSame('shared_adm', $execution->getOutput());
    }

    public function testRunIfNotExistsWhenContainerIsReusedThenWaitsUntilMySQLIsReady(): void
    {
        /** @Given a MySQL container another process has just started */
        $container = MySQLDockerContainer::from(image: 'mysql:8.4', client: $this->client)
            ->withName(name: 'shared-db')
            ->withRootPassword(rootPassword: 'root');

        /** @And the Docker list returns the existing container ID */
        $this->client->withDockerListResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the MySQL settings */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'shared-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And MySQL accepts connections only on the second ping */
        $this->client->withDockerExecuteResponse(output: 'mysqld is not ready', isSuccessful: false);

        /** @When runIfNotExists is called */
        $container->runIfNotExists();

        /** @Then MySQL should have been pinged until it accepted connections */
        self::assertSame(
            2,
            count(
                array_filter(
                    $this->client->getExecutedCommandLines(),
                    static fn(string $commandLine): bool => str_contains($commandLine, 'mysqladmin ping')
                )
            )
        );
    }

    public function testStopWhenMySQLContainerIsSharedThenStopsTheUnderlyingContainer(): void
    {
        /** @Given a MySQL container reused from another process */
        $started = SharedMySQLContainer::reusedWith(
            client: $this->client,
            database: 'shared_adm',
            hostname: 'shared-db'
        );

        /** @When the shared container is stopped */
        $started->stop(gracePeriod: Duration::fromSeconds(seconds: 5));

        /** @Then a docker stop command should target the existing container */
        self::assertContains(
            sprintf('docker stop --time 5 %s', InspectResponseFixture::shortContainerId()),
            $this->client->getExecutedCommandLines()
        );
    }

    public function testRunIfNotExistsWhenMySQLNeverBecomesReadyThenContainerWaitTimeout(): void
    {
        /** @Given a MySQL container with a readiness timeout of one second */
        $container = MySQLDockerContainer::from(image: 'mysql:8.4', client: $this->client)
            ->withName(name: 'never-db')
            ->withRootPassword(rootPassword: 'root')
            ->withReadinessTimeout(timeout: Duration::fromSeconds(seconds: 1));

        /** @And the Docker list returns empty */
        $this->client->withDockerListResponse(output: '');

        /** @And the Docker daemon creates the container */
        $this->client->withDockerCreateResponse(output: InspectResponseFixture::containerId());

        /** @And the inspect response carries the MySQL settings */
        $this->client->withDockerInspectResponse(
            inspectResult: InspectResponseFixture::build(
                hostname: 'never-db',
                environment: ['MYSQL_ROOT_PASSWORD=root']
            )
        );

        /** @And MySQL never accepts connections */
        for ($index = 0; $index < 10; $index++) {
            $this->client->withDockerExecuteResponse(output: 'mysqld is not ready', isSuccessful: false);
        }

        /** @Then a timeout exception carrying the readiness timeout should be thrown */
        $this->expectException(ContainerWaitTimeout::class);
        $this->expectExceptionMessage('Container readiness check timed out after <1> seconds.');

        /** @When runIfNotExists is called */
        $container->runIfNotExists();
    }

    public function testGetAddressWhenMySQLContainerIsSharedThenIsTheAddressOfTheExistingContainer(): void
    {
        /** @Given a MySQL container reused from another process */
        $started = SharedMySQLContainer::reusedWith(
            client: $this->client,
            database: 'shared_adm',
            hostname: 'shared-db'
        );

        /** @When reading its address */
        $address = $started->getAddress();

        /** @Then the address, the ID and the environment should be those of the existing container */
        self::assertSame('shared-db', $address->getHostname());
        self::assertSame(InspectResponseFixture::shortContainerId(), $started->getId());
        self::assertSame('shared_adm', $started->getEnvironmentVariables()->getValueBy(key: 'MYSQL_DATABASE'));
    }
}
