<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Factories;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Address;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Hostname;
use TinyBlocks\DockerContainer\Internal\Containers\Address\IP;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Ports;
use TinyBlocks\DockerContainer\Internal\Containers\Environment\EnvironmentVariables;

final readonly class InspectResultParser
{
    private const int LIMIT = 2;
    private const string SEPARATOR = '=';

    public function parseAddress(array $data): Address
    {
        $networks = $data['NetworkSettings']['Networks'] ?? [];
        $configuration = $data['Config'] ?? [];
        $rawPorts = $configuration['ExposedPorts'] ?? [];

        $ip = IP::from(value: !empty($networks) ? ($networks[key($networks)]['IPAddress'] ?? '') : '');
        $hostname = Hostname::from(value: $configuration['Hostname'] ?? '');

        $exposedPorts = Collection::createFrom(
            elements: array_map(
                static fn(string $port): int => (int)explode('/', $port)[0],
                array_keys($rawPorts)
            )
        );

        return Address::from(ip: $ip, ports: Ports::from(ports: $exposedPorts), hostname: $hostname);
    }

    public function parseEnvironmentVariables(array $data): EnvironmentVariables
    {
        $envData = $data['Config']['Env'] ?? [];
        $variables = [];

        foreach ($envData as $variable) {
            [$key, $value] = explode(self::SEPARATOR, $variable, self::LIMIT);
            $variables[$key] = $value;
        }

        return EnvironmentVariables::from(variables: Collection::createFrom(elements: $variables));
    }
}
