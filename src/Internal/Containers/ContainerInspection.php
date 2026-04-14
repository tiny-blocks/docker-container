<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Address;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Hostname;
use TinyBlocks\DockerContainer\Internal\Containers\Address\IP;
use TinyBlocks\DockerContainer\Internal\Containers\Address\Ports;
use TinyBlocks\DockerContainer\Internal\Containers\Environment\EnvironmentVariables;

final readonly class ContainerInspection
{
    private const int LIMIT = 2;
    private const string SEPARATOR = '=';

    private function __construct(private array $inspectResult)
    {
    }

    public static function from(array $inspectResult): ContainerInspection
    {
        return new ContainerInspection(inspectResult: $inspectResult);
    }

    public function toAddress(): Address
    {
        $networks = $this->inspectResult['NetworkSettings']['Networks'] ?? [];
        $configuration = $this->inspectResult['Config'] ?? [];
        $rawExposedPorts = $configuration['ExposedPorts'] ?? [];
        $rawHostPorts = $this->inspectResult['NetworkSettings']['Ports'] ?? [];

        $ip = IP::from(value: !empty($networks) ? ($networks[key($networks)]['IPAddress'] ?? '') : '');
        $hostname = Hostname::from(value: $configuration['Hostname'] ?? '');

        $exposedPorts = Collection::createFrom(
            elements: array_map(
                static fn(string $port): int => (int)explode('/', $port)[0],
                array_keys($rawExposedPorts)
            )
        );

        $hostMappedPorts = Collection::createFrom(
            elements: array_reduce(
                array_values($rawHostPorts),
                static function (array $ports, ?array $bindings): array {
                    if (is_null($bindings)) {
                        return $ports;
                    }

                    foreach ($bindings as $binding) {
                        $hostPort = (int)($binding['HostPort'] ?? 0);

                        if ($hostPort > 0) {
                            $ports[] = $hostPort;
                        }
                    }

                    return $ports;
                },
                []
            )
        );

        return Address::from(
            ip: $ip,
            ports: Ports::from(exposedPorts: $exposedPorts, hostMappedPorts: $hostMappedPorts),
            hostname: $hostname
        );
    }

    public function toEnvironmentVariables(): EnvironmentVariables
    {
        $rawEnvironment = $this->inspectResult['Config']['Env'] ?? [];
        $variables = [];

        foreach ($rawEnvironment as $variable) {
            [$key, $value] = explode(self::SEPARATOR, $variable, self::LIMIT);
            $variables[$key] = $value;
        }

        return EnvironmentVariables::from(variables: Collection::createFrom(elements: $variables));
    }
}
