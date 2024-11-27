# Docker container

[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)

* [Overview](#overview)
* [Installation](#installation)
* [How to use](#how-to-use)
    * [Creating a container](#creating-a-container)
    * [Running a container](#running-a-container)
    * [Running a container if it doesn't exist](#running-a-container-if-it-doesnt-exist)
    * [Setting network](#setting-network)
    * [Setting port mappings](#setting-port-mappings)
    * [Setting volumes mappings](#setting-volumes-mappings)
    * [Setting environment variables](#setting-environment-variables)
    * [Disabling auto-remove](#disabling-auto-remove)
    * [Copying files to a container](#copying-files-to-a-container)
    * [Waiting for a condition](#waiting-for-a-condition)
* [Usage examples](#usage-examples)
* [License](#license)
* [Contributing](#contributing)

<div id='overview'></div> 

## Overview

The `DockerContainer` library provides an interface and implementations to manage Docker containers programmatically.
It simplifies the creation, execution, and interaction with containers, such as adding network configurations, mapping
ports, setting environment variables, and executing commands inside containers.

<div id='installation'></div>

## Installation

```bash
composer require tiny-blocks/docker-container
```

<div id='how-to-use'></div>

## How to use

### Creating a container

Creates a container from a specified image and optionally a name.
The `from` method can be used to initialize a new container instance with an image and an optional name for
identification.

```php
$container = GenericContainer::from(image: 'php:8.3-fpm', name: 'my-container');
```

### Running a container

Starts a container and executes commands once it is running.
The `run` method allows you to start the container with specific commands, enabling you to run processes inside the
container right after it is initialized.

```php
$container->run(commandsOnRun: ['ls', '-la']);
```

### Running a container if it doesn't exist

Starts the container only if it doesn't already exist, otherwise does nothing.
The `runIfNotExists` method checks if the container is already running.
If it exists, it does nothing.
If it doesn't, it creates and starts the container, running any provided commands.

```php
$container->runIfNotExists(commandsOnRun: ['echo', 'Hello World!']);
```

### Setting network

Configure the network driver for the container.  
The `withNetwork` method allows you to define the type of network the container should connect to.

Supported network drivers include:

- `NONE`: No network.
- `HOST`: Use the host network stack.
- `BRIDGE`: The default network driver, used when containers are connected to a bridge network.
- `IPVLAN`: A driver that uses the underlying host's IP address.
- `OVERLAY`: Allows communication between containers across different Docker daemons.
- `MACVLAN`: Assigns a MAC address to the container, allowing it to appear as a physical device on the network.

```php
$container->withNetwork(driver: NetworkDrivers::HOST);
```

### Setting port mappings

Maps ports between the host and the container.
The `withPortMapping` method maps a port from the host to a port inside the container.
This is essential when you need to expose services like a web server running in the container to the host
machine.

```php
$container->withPortMapping(portOnHost: 9000, portOnContainer: 9000);
```

### Setting volumes mappings

Maps a volume from the host to the container.
The `withVolumeMapping` method allows you to link a directory from the host to the container, which is useful for
persistent data storage or sharing data between containers.

```php
$container->withVolumeMapping(pathOnHost: '/path/on/host', pathOnContainer: '/path/in/container');
```

### Setting environment variables

Sets environment variables inside the container.
The `withEnvironmentVariable` method allows you to configure environment variables within the container, useful for
configuring services like databases, application settings, etc.

```php
$container->withEnvironmentVariable(key: 'XPTO', value: '123');
```

### Disabling auto-remove

Prevents the container from being automatically removed when stopped.
By default, Docker removes containers after they stop.
The `withoutAutoRemove` method disables this feature, keeping the container around even after it finishes its
execution.

```php
$container->withoutAutoRemove();
```

### Copying files to a container

Copies files or directories from the host machine to the container.
The `copyToContainer` method allows you to transfer files from the host system into the container’s file system, useful
for adding resources like configurations or code.

```php
$container->copyToContainer(pathOnHost: '/path/to/files', pathOnContainer: '/path/in/container');
```

### Waiting for a condition

Makes the container wait for a specific condition before proceeding.
The `withWait` method allows the container to pause its execution until a specified condition is met, which is useful
for ensuring that a service inside the container is ready before continuing with other operations.

```php
$container->withWait(wait: ContainerWaitForDependency::untilReady(condition: MySQLReady::from(container: $container)));
```

<div id='usage-examples'></div>

## Usage examples

### MySQL and Generic Containers

The MySQL container is configured and started with the necessary credentials and volumes:

```php
$mySQLContainer = MySQLContainer::from(image: 'mysql:8.1', name: 'test-database')
    ->withUsername(user: 'root')
    ->withPassword(password: 'root')
    ->withDatabase(database: 'test_adm')
    ->withPortMapping(portOnHost: 3306, portOnContainer: 3306)
    ->withRootPassword(rootPassword: 'root')
    ->withVolumeMapping(pathOnHost: '/var/lib/mysql', pathOnContainer: '/var/lib/mysql')
    ->runIfNotExists();
```

With the MySQL container started, it is possible to retrieve data, such as the address and JDBC connection URL:

```php
$address = $mySQLContainer->getAddress();
$template = 'jdbc:mysql://%s:%s/%s?useUnicode=yes&characterEncoding=UTF-8&allowPublicKeyRetrieval=true&useSSL=false';
$jdbcUrl = sprintf($template, $address->getIp(), $address->getPorts()->firstExposedPort(), 'test_adm');
```

The Flyway container is configured and only starts and executes migrations after the MySQL container is **ready**:

```php
$flywayContainer = GenericContainer::from(image: 'flyway/flyway:11.0.0')
    ->withWait(wait: ContainerWaitForDependency::untilReady(condition: MySQLReady::from(container: $mySQLContainer)))
    ->copyToContainer(pathOnHost: '/migrations', pathOnContainer: '/flyway/sql')
    ->withVolumeMapping(pathOnHost: '/migrations', pathOnContainer: '/flyway/sql')
    ->withEnvironmentVariable(key: 'FLYWAY_URL', value: $jdbcUrl)
    ->withEnvironmentVariable(key: 'FLYWAY_USER', value: 'root')
    ->withEnvironmentVariable(key: 'FLYWAY_TABLE', value: 'schema_history')
    ->withEnvironmentVariable(key: 'FLYWAY_SCHEMAS', value: 'test_adm')
    ->withEnvironmentVariable(key: 'FLYWAY_EDITION', value: 'community')
    ->withEnvironmentVariable(key: 'FLYWAY_PASSWORD', value: 'root')
    ->withEnvironmentVariable(key: 'FLYWAY_LOCATIONS', value: 'filesystem:/flyway/sql')
    ->withEnvironmentVariable(key: 'FLYWAY_CLEAN_DISABLED', value: 'false')
    ->withEnvironmentVariable(key: 'FLYWAY_VALIDATE_MIGRATION_NAMING', value: 'true')
    ->run(commandsOnRun: ['-connectRetries=15', 'clean', 'migrate']);
```

<div id='license'></div>

## License

Docker container is licensed under [MIT](LICENSE).

<div id='contributing'></div>

## Contributing

Please follow the [contributing guidelines](https://github.com/tiny-blocks/tiny-blocks/blob/main/CONTRIBUTING.md) to
contribute to the project.
