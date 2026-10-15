# Docker container

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    + [Creating a container](#creating-a-container)
    + [Running a container](#running-a-container)
    + [Running if not exists](#running-if-not-exists)
    + [Running to completion](#running-to-completion)
    + [Checking if the container was reused](#checking-if-the-container-was-reused)
    + [Setting network](#setting-network)
    + [Setting port mappings](#setting-port-mappings)
    + [Setting volume mappings](#setting-volume-mappings)
    + [Setting tmpfs mounts](#setting-tmpfs-mounts)
    + [Setting environment variables](#setting-environment-variables)
    + [Disabling auto-remove](#disabling-auto-remove)
    + [Copying files to a container](#copying-files-to-a-container)
    + [Stopping a container](#stopping-a-container)
    + [Stopping on shutdown](#stopping-on-shutdown)
    + [Executing commands after startup](#executing-commands-after-startup)
    + [Wait strategies](#wait-strategies)
        - [Waiting for a fixed time](#waiting-for-a-fixed-time)
        - [Waiting for a dependency](#waiting-for-a-dependency)
    + [MySQL container](#mysql-container)
        - [Configuring MySQL options](#configuring-mysql-options)
        - [Setting readiness timeout](#setting-readiness-timeout)
        - [Using ephemeral storage](#using-ephemeral-storage)
        - [Retrieving connection data](#retrieving-connection-data)
        - [Environment-aware connection](#environment-aware-connection)
    + [Flyway container](#flyway-container)
        - [Setting the database source](#setting-the-database-source)
        - [Configuring migrations](#configuring-migrations)
        - [Configuring Flyway options](#configuring-flyway-options)
        - [Running Flyway commands](#running-flyway-commands)
        - [Running only when enabled](#running-only-when-enabled)
    + [Handling failures](#handling-failures)
    + [Using a custom Docker client](#using-a-custom-docker-client)
    + [Usage examples](#usage-examples)
        - [MySQL with Flyway migrations](#mysql-with-flyway-migrations)
        - [Integration test bootstrap shared by every process of a run](#integration-test-bootstrap-shared-by-every-process-of-a-run)
* [FAQ](#faq)
* [License](#license)
* [Contributing](#contributing)

## Overview

Manages Docker containers programmatically for PHP, enabling integration tests and disposable infrastructure to be
orchestrated directly from code. Supports container definitions, port and volume mappings, environment variables,
readiness probes, one-shot containers run to completion, shutdown hooks, and orphan reaping.

Container definitions are immutable: every `with*` method returns a new definition and leaves the receiver
untouched, so a base definition can be shared and specialized safely. Durations are expressed with `Duration` from
[tiny-blocks/time](https://github.com/tiny-blocks/time).

## Installation

```bash
composer require tiny-blocks/docker-container
```

## How to use

### Creating a container

Creates a container definition from an image. The container gets a unique generated name until `withName()` replaces
it.

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\GenericDockerContainer;

$container = GenericDockerContainer::from(image: 'php:8.5-fpm')->withName(name: 'my-container');
```

Since definitions are immutable, keep the value each `with*` method returns:

```php
$container = $container->withEnvironmentVariable(key: 'APP_ENV', value: 'testing');
```

### Running a container

Creates and starts a container, optionally with the command to run on startup. The returned `DisposableContainer` is
owned by this process, so it can be removed once the work is done (see [Stopping on shutdown](#stopping-on-shutdown)).

```php
$container->run();
```

With commands:

```php
$container->run(commands: ['ls', '-la']);
```

To wait after the container starts, register a wait strategy on the definition (see
[Wait strategies](#wait-strategies)).

### Running if not exists

Starts a container only if a container with the same name does not exist yet, and reuses it otherwise. The returned
`SharedContainer` is shared by every PHP process that asks for the same name (as in mutation testing), so it offers
neither `stopOnShutdown()` nor `remove()`: no single process owns it. When the code runs inside Docker, a reaper
container removes the shared container once the test runner container exits. The wait strategies registered with
`withWaitAfterStarted()` apply only when a new container starts.

```php
$container->runIfNotExists();
```

### Running to completion

Runs a one-shot container until it exits and returns what it wrote to its standard output. The container is created,
receives the files registered with `copyToContainer()`, starts attached, and is removed once it exits, successfully or
not, or once the timeout elapses.

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\Time\Duration;

$execution = GenericDockerContainer::from(image: 'alpine:3.22')
    ->runToCompletion(timeout: Duration::fromSeconds(seconds: 60), commands: ['sh', '-c', 'echo done']);

$execution->getOutput(); # "done\n"
```

A non-zero exit raises `ContainerExitUnsuccessful` carrying what the container wrote to its standard error. A
container still running when the timeout elapses raises `ContainerWaitTimeout`.

### Checking if the container was reused

Tells whether the shared container came from an already-running instance. This distinguishes a fresh start from a
reuse, so first-boot work such as seeding runs only once. Only the `SharedContainer` returned by `runIfNotExists()`
answers it, since a container returned by `run()` is always new.

```php
$started = $container->runIfNotExists();

$alreadyRunning = $started->wasReused();
```

### Setting network

Sets the Docker network the container should join. The network is created automatically when the container runs, if
it does not already exist. Networks created by the library are labeled with `tiny-blocks.docker-container=true` for
safe cleanup.

```php
$container = $container->withNetwork(name: 'my-network');
```

### Setting port mappings

Maps a port from the host to the container. A host port of `0` lets Docker pick a free one, which avoids collisions
between parallel runs.

```php
$container = $container->withPortMapping(portOnHost: 8080, portOnContainer: 80);
```

After the container starts, both ports are available through the `Address`:

```php
$ports = $started->getAddress()->getPorts();

$ports->firstExposedPort();  # 80   (container-internal)
$ports->firstHostPort();     # 8080 (host-accessible)
```

### Setting volume mappings

Mounts a directory from the host into the container.

```php
$container = $container->withVolumeMapping(pathOnHost: '/host/data', pathOnContainer: '/container/data');
```

### Setting tmpfs mounts

Mounts a directory inside the container in memory. It starts empty, and its content is discarded when the container
stops.

```php
$container = $container->withTmpfsMount(pathOnContainer: '/container/cache');
```

### Setting environment variables

Adds an environment variable to the container.

```php
$container = $container->withEnvironmentVariable(key: 'APP_ENV', value: 'testing');
```

### Disabling auto-remove

By default, containers are removed when stopped. This disables that behavior.

```php
$container = $container->withoutAutoRemove();
```

### Copying files to a container

Registers files or directories to be copied from the host into the container. The files are copied after the
container is created and before it starts, so its command already sees them.

```php
$container = $container->copyToContainer(pathOnHost: '/path/to/files', pathOnContainer: '/path/in/container');
```

### Stopping a container

Stops a running container. Docker forces the stop once the grace period elapses.

```php
<?php

declare(strict_types=1);

use TinyBlocks\Time\Duration;

$started = $container->run();
$result = $started->stop(gracePeriod: Duration::fromSeconds(seconds: 10));
```

### Stopping on shutdown

Registers a container started by `run()` to be forcefully removed when the PHP process exits. On shutdown, the
following cleanup is performed automatically:

- The container is killed and removed (`docker rm --force --volumes`).
- Anonymous volumes created by the container (e.g., MySQL's `/var/lib/mysql`) are removed.
- Unused networks created by the library are pruned.

Only resources labeled with `tiny-blocks.docker-container=true` are affected. Containers, volumes, and networks
from other environments are never touched.

```php
$started = $container->run();
$started->stopOnShutdown();
```

### Executing commands after startup

Runs commands inside an already-started container.

```php
$started = $container->run();
$result = $started->executeAfterStarted(commands: ['php', '-v']);
```

The returned `ExecutionCompleted` provides the command output and success status:

```php
$result->getOutput();
$result->isSuccessful();
```

### Wait strategies

Wait strategies are part of the definition. `withWaitBeforeRun()` adds a `ContainerWaitBeforeStarted`, applied before
the container runs, and `withWaitAfterStarted()` adds a `ContainerWaitAfterStarted`, applied after `run()` starts the
container. Each method adds to the strategies already registered, and they apply in the order they were added.

#### Waiting for a fixed time

Pauses for a fixed duration before a container runs or after it starts.

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\Waits\ContainerWaitForTime;
use TinyBlocks\Time\Duration;

$pause = ContainerWaitForTime::forDuration(duration: Duration::fromSeconds(seconds: 3));

$container = $container
    ->withWaitBeforeRun(wait: $pause)
    ->withWaitAfterStarted(wait: $pause);
```

#### Waiting for a dependency

Polls a readiness condition every 250 milliseconds until it holds, and raises `ContainerWaitTimeout` once the timeout
(30 seconds by default) elapses. This is useful when one container depends on another being fully ready.

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\GenericDockerContainer;
use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\DockerContainer\Waits\Conditions\MySQL\MySQLReady;
use TinyBlocks\DockerContainer\Waits\ContainerWaitForDependency;
use TinyBlocks\Time\Duration;

$mySQLStarted = MySQLDockerContainer::from(image: 'mysql:8.4')
    ->withRootPassword(rootPassword: 'root')
    ->run();

$container = GenericDockerContainer::from(image: 'my-app:latest')
    ->withWaitBeforeRun(
        wait: ContainerWaitForDependency::untilReady(condition: MySQLReady::from(container: $mySQLStarted))
            ->withTimeout(timeout: Duration::fromSeconds(seconds: 60))
    )
    ->run();
```

### MySQL container

`MySQLDockerContainer` provides a specialized container for MySQL databases. Both `run()` and `runIfNotExists()`
hand the container over only once MySQL accepts connections, and create the database and the root grants when they
are configured. Both return a `MySQLContainerStarted`: the one from `run()` is also a `DisposableContainer`, and the
one from `runIfNotExists()` is also a `SharedContainer`.

#### Configuring MySQL options

| Method             | Parameter       | Description                                                     |
|--------------------|-----------------|-----------------------------------------------------------------|
| `withTimezone`     | `$timezone`     | Sets the container timezone (e.g., `America/Sao_Paulo`).        |
| `withUsername`     | `$user`         | Sets the MySQL user created on startup.                         |
| `withPassword`     | `$password`     | Sets the password for the MySQL user.                           |
| `withDatabase`     | `$database`     | Sets the default database created on startup.                   |
| `withRootPassword` | `$rootPassword` | Sets the root password for the MySQL instance.                  |
| `withGrantedHosts` | `$hosts`        | Sets hosts granted root privileges (default: `['%', '172.%']`). |

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\MySQLDockerContainer;

$mySQLStarted = MySQLDockerContainer::from(image: 'mysql:8.4')
    ->withName(name: 'my-database')
    ->withTimezone(timezone: 'America/Sao_Paulo')
    ->withUsername(user: 'app_user')
    ->withPassword(password: 'secret')
    ->withDatabase(database: 'my_database')
    ->withPortMapping(portOnHost: 0, portOnContainer: 3306)
    ->withRootPassword(rootPassword: 'root')
    ->withGrantedHosts()
    ->run();
```

#### Setting readiness timeout

Configures how long `run()` and `runIfNotExists()` wait for MySQL to accept connections before throwing a
`ContainerWaitTimeout` exception. The default timeout is 30 seconds.

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\MySQLDockerContainer;
use TinyBlocks\Time\Duration;

$mySQLStarted = MySQLDockerContainer::from(image: 'mysql:8.4')
    ->withName(name: 'my-database')
    ->withRootPassword(rootPassword: 'root')
    ->withReadinessTimeout(timeout: Duration::fromSeconds(seconds: 60))
    ->run();
```

#### Using ephemeral storage

Keeps the MySQL data in memory. The data directory `/var/lib/mysql` becomes a tmpfs mount, and the server starts
without the binary log (`--skip-log-bin`), which would otherwise grow in that memory. MySQL initializes faster, and
statements that write, such as the `TRUNCATE` and `INSERT` a test runs between cases, no longer wait for the disk.

The data is lost when the container stops, so use it only for databases a test run creates and throws away.

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\MySQLDockerContainer;

$mySQLStarted = MySQLDockerContainer::from(image: 'mysql:8.4')
    ->withName(name: 'test-database')
    ->withDatabase(database: 'test_adm')
    ->withRootPassword(rootPassword: 'root')
    ->withEphemeralStorage()
    ->runIfNotExists();
```

The server arguments come after any command given to `run()` or `runIfNotExists()`, so they also apply when the
command names `mysqld` explicitly.

#### Retrieving connection data

After the MySQL container starts, connection details are available through the `MySQLContainerStarted` instance.

```php
<?php

declare(strict_types=1);

$address = $mySQLStarted->getAddress();
$ip = $address->getIp();
$hostname = $address->getHostname();

$ports = $address->getPorts();
$containerPort = $ports->firstExposedPort();  # e.g. 3306 (container-internal)
$hostPort = $ports->firstHostPort();          # e.g. 49153 (host-accessible)

$environmentVariables = $mySQLStarted->getEnvironmentVariables();
$database = $environmentVariables->getValueBy(key: 'MYSQL_DATABASE');
$username = $environmentVariables->getValueBy(key: 'MYSQL_USER');
$password = $environmentVariables->getValueBy(key: 'MYSQL_PASSWORD');

$jdbcUrl = $mySQLStarted->getJdbcUrl();
```

Use `firstExposedPort()` when connecting from another container in the same network.
Use `firstHostPort()` when connecting from the host machine (e.g., tests running outside Docker).

#### Environment-aware connection

The `Address` and `Ports` contracts provide environment-aware methods that automatically resolve the correct host and
port for connecting to a container. These methods detect whether the caller is running inside Docker or on the host
machine:

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\MySQLDockerContainer;

$started = MySQLDockerContainer::from(image: 'mysql:8.4')
    ->withName(name: 'my-database')
    ->withDatabase(database: 'my_database')
    ->withPortMapping(portOnHost: 0, portOnContainer: 3306)
    ->withRootPassword(rootPassword: 'root')
    ->runIfNotExists();

$address = $started->getAddress();

$host = $address->getHostForConnection();             # hostname inside Docker, 127.0.0.1 on host
$port = $address->getPorts()->getPortForConnection(); # container port inside Docker, host-mapped port on host
```

This is useful when the same test suite runs both locally (inside a Docker Compose stack) and in CI (on the host).

### Flyway container

`FlywayDockerContainer` runs Flyway database migrations. Each command runs Flyway once in a fresh container with a
unique generated name, blocks until Flyway exits, and removes the container afterward, so parallel test runs never
collide on it.

#### Setting the database source

Binds Flyway to a MySQL container definition. Defining the source starts nothing. The first Flyway command starts the
MySQL container through `runIfNotExists()`, which reuses a running container of the same name, and waits until MySQL
accepts connections. Flyway then connects with the JDBC URL of that container, the root credentials (from
`MYSQL_ROOT_PASSWORD`), the target schema (from `MYSQL_DATABASE`), and the history table (`schema_history`), so it
needs no connection retries and no readiness wait of its own.

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\FlywayDockerContainer;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

$mySQL = MySQLDockerContainer::from(image: 'mysql:8.4')
    ->withName(name: 'test-database')
    ->withNetwork(name: 'my-network')
    ->withDatabase(database: 'test_adm')
    ->withRootPassword(rootPassword: 'root');

$flyway = FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine')
    ->withSource(container: $mySQL)
    ->withNetwork(name: 'my-network')
    ->withMigrations(pathOnHost: '/path/to/migrations');
```

The credentials, the schema, and the table can be overridden before or after calling `withSource()`:

```php
<?php

declare(strict_types=1);

$flyway = $flyway
    ->withTable(table: 'custom_history')
    ->withSchema(schema: 'custom_schema')
    ->withSource(container: $mySQL)
    ->withCredentials(password: 'secret', username: 'app_user');
```

#### Configuring migrations

Sets the host directory containing Flyway migration SQL files. The files are copied into the container at
`/flyway/migrations` before Flyway starts.

```php
$flyway = $flyway->withMigrations(pathOnHost: '/path/to/migrations');
```

#### Configuring Flyway options

| Method                          | Parameter                | Description                                                      |
|---------------------------------|--------------------------|------------------------------------------------------------------|
| `withTable`                     | `$table`                 | Overrides the history table name (default: `schema_history`).    |
| `withSchema`                    | `$schema`                | Overrides the target schema (default: detected from MySQL).      |
| `withTimeout`                   | `$timeout`               | Sets how long each command waits for Flyway to exit (120 s).     |
| `withCredentials`               | `$password`, `$username` | Overrides the credentials (default: root, from MySQL).           |
| `withConnectRetries`            | `$retries`               | Sets the number of database connection retries.                  |
| `withMigrationNamingValidation` |                          | Rejects migrations whose file names break the naming convention. |
| `withEnvironmentVariable`       | `$key`, `$value`         | Sets any other Flyway setting through its `FLYWAY_*` variable.   |

#### Running Flyway commands

| Method              | Flyway command  | Description                                                     |
|---------------------|-----------------|-----------------------------------------------------------------|
| `migrate()`         | `migrate`       | Applies pending migrations.                                     |
| `validate()`        | `validate`      | Validates applied migrations against local.                     |
| `repair()`          | `repair`        | Repairs the schema history table.                               |
| `cleanAndMigrate()` | `clean migrate` | Enables clean for the run, drops all objects, then re-migrates. |

```php
$flyway->migrate();
$flyway->cleanAndMigrate();
```

Each command blocks until Flyway exits, so its work is done when the method returns, and returns the Flyway output
as an `ExecutionCompleted`. It waits up to 120 seconds by default:

```php
<?php

declare(strict_types=1);

use TinyBlocks\Time\Duration;

$flyway->withTimeout(timeout: Duration::fromSeconds(seconds: 300))->cleanAndMigrate();
```

#### Running only when enabled

`onlyWhenEnabled()` returns a `FlywayContainerGated`, whose commands run only while an environment
variable holds `1`. The variable is read when a command is called. While the variable holds anything else, or is not
set, the command returns `null` and issues no Docker command, so neither Flyway nor its MySQL source is started.

```php
<?php

declare(strict_types=1);

$execution = $flyway
    ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS')
    ->cleanAndMigrate(); # ExecutionCompleted when RUN_MIGRATIONS=1, null otherwise
```

The gated container offers the same four commands, with the same exceptions. A command returns an
`ExecutionCompleted` only when Flyway did run, and `null` tells the caller that nothing ran.

### Handling failures

The exceptions consumers may catch live in `TinyBlocks\DockerContainer\Exceptions`.

| Exception                      | Raised when                                                                          |
|--------------------------------|--------------------------------------------------------------------------------------|
| `DockerCommandExecutionFailed` | A Docker command cannot run, exceeds its timeout, or reports a failure.              |
| `ContainerWaitTimeout`         | A readiness condition does not hold, or a one-shot container does not exit, in time. |
| `ContainerExitUnsuccessful`    | A one-shot container, such as Flyway, exits with a non-zero status.                  |
| `ImageNameEmpty`               | A container is created from an empty image name.                                     |

`ContainerExitUnsuccessful` carries what the container wrote to its standard error, so a failed migration reports the
Flyway error itself:

```text
Container <2xG1...> did not exit successfully. Output: <ERROR: Validate failed: Migrations have failed validation>.
```

### Using a custom Docker client

Every factory accepts a `DockerClient`, the single boundary through which the library executes Docker commands. It
defaults to the local `docker` executable. A custom client can route the commands to another Docker-compatible engine,
or stand in for the engine in the unit tests of code that builds containers. A `DockerCommand` carries only its
arguments and runs through `execute()`, within the default timeout of the client. A `DockerCommandWithTimeout`, such as
a container run to completion or a stop, also carries the timeout it must finish within and runs through
`executeWithTimeout()`:

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\DockerClient;
use TinyBlocks\DockerContainer\DockerCommand;
use TinyBlocks\DockerContainer\DockerCommandWithTimeout;
use TinyBlocks\DockerContainer\ExecutionCompleted;
use TinyBlocks\DockerContainer\GenericDockerContainer;

final readonly class RecordingDockerClient implements DockerClient
{
    public function execute(DockerCommand $command): ExecutionCompleted
    {
        # Inspect $command->toArguments(), then answer with an ExecutionCompleted.
    }

    public function executeWithTimeout(DockerCommandWithTimeout $command): ExecutionCompleted
    {
        # Inspect $command->toArguments() and $command->timeout(), then answer with an ExecutionCompleted.
    }
}

$container = GenericDockerContainer::from(image: 'alpine:3.22', client: new RecordingDockerClient());
```

### Usage examples

- When running the containers from the library on a host (your local machine), map the volume
  `/var/run/docker.sock:/var/run/docker.sock` so the container has access to the Docker daemon on the host machine.
- In some cases, it may be necessary to add the `docker-cli` dependency to your PHP image to interact with Docker
  from within the container.

#### MySQL with Flyway migrations

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\FlywayDockerContainer;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

$mySQL = MySQLDockerContainer::from(image: 'mysql:8.4')
    ->withName(name: 'test-database')
    ->withNetwork(name: 'my-network')
    ->withDatabase(database: 'test_adm')
    ->withPassword(password: 'secret')
    ->withTimezone(timezone: 'America/Sao_Paulo')
    ->withGrantedHosts()
    ->withRootPassword(rootPassword: 'root');

FlywayDockerContainer::from(image: 'flyway/flyway:12-alpine')
    ->withSource(container: $mySQL)
    ->withNetwork(name: 'my-network')
    ->withMigrations(pathOnHost: '/path/to/migrations')
    ->withMigrationNamingValidation()
    ->cleanAndMigrate();

$mySQLStarted = $mySQL->runIfNotExists();
```

MySQL starts when Flyway runs. The last call hands over the same container, reused rather than started again, for
the tests that need its address or its environment variables.

#### Integration test bootstrap shared by every process of a run

A PHPUnit bootstrap runs once per PHP process, and mutation testing starts many processes for one test run. The
pattern below starts MySQL and migrates it only in the process that asks for it (`RUN_MIGRATIONS=1`), while every
process derives the same database host without calling Docker. In every other process, `onlyWhenEnabled()` makes
`cleanAndMigrate()` return `null` before any Docker command, so the whole chain runs unchanged in each process. The
container name is scoped to the test runner (`gethostname()` is the runner container ID inside Docker), so two runs in
parallel never share, clean, or reap each other's database.

```php
<?php

declare(strict_types=1);

use TinyBlocks\DockerContainer\FlywayDockerContainer;
use TinyBlocks\DockerContainer\MySQLDockerContainer;

$network = (string)(getenv('TEST_NETWORK') ?: 'my-service-test_default');
$template = 'my-service-database-%s';
$databaseHost = sprintf($template, gethostname());

putenv(sprintf('DATABASE_HOST=%s', $databaseHost));
putenv('DATABASE_PORT=3306');

FlywayDockerContainer::from(image: 'flyway/flyway:13.9.0-alpine')
    ->withSource(
        container: MySQLDockerContainer::from(image: 'mysql:8.4')
            ->withName(name: $databaseHost)
            ->withNetwork(name: $network)
            ->withDatabase(database: 'my_service_test')
            ->withRootPassword(rootPassword: 'root')
            ->withEphemeralStorage()
    )
    ->withNetwork(name: $network)
    ->withMigrations(pathOnHost: sprintf('%s/../database/migrations', __DIR__))
    ->withMigrationNamingValidation()
    ->onlyWhenEnabled(variable: 'RUN_MIGRATIONS')
    ->cleanAndMigrate();
```

## FAQ

### 01. Why is the shared container name scoped to the test runner?

`runIfNotExists()` shares a container among every process that asks for the same name. With a fixed name, two test
runs in parallel (two terminals, two CI jobs on one host) share one database: one run cleans the schema the other is
using, and the first run to finish reaps the container from under the other. Suffixing the name with `gethostname()`
keeps the sharing inside one run, because inside Docker the host name is the ID of the runner container, unique per
`docker run`, and still identical in every process that run starts.

### 02. Why do run() and runIfNotExists() return different types?

A container from `run()` belongs to the process that started it, so that process may remove it. A container from
`runIfNotExists()` is shared by every process of a run, so no single process may remove it, and only a shared
container can have been reused. Each type offers only what holds for it, instead of one contract whose methods do
nothing or always answer the same on one side.

> Robert C. Martin, *Agile Software Development, Principles, Patterns, and Practices* (Prentice Hall, 2002), Chapter
> 12, "The Interface Segregation Principle".

## License

Docker container is licensed under [MIT](LICENSE).

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
