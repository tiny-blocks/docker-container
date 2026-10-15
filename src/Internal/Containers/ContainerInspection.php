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
        $configuration = ($this->inspectResult['Config'] ?? []);
        $networkSettings = ($this->inspectResult['NetworkSettings'] ?? []);
        $network = array_first(($networkSettings['Networks'] ?? []));

        return Address::from(
            ip: IP::from(value: ($network['IPAddress'] ?? '')),
            ports: Ports::from(
                exposedPorts: ($configuration['ExposedPorts'] ?? []),
                portBindings: ($networkSettings['Ports'] ?? [])
            ),
            hostname: Hostname::from(value: ($configuration['Hostname'] ?? ''))
        );
    }

    public function toEnvironmentVariables(): EnvironmentVariables
    {
        $rawEnvironment = ($this->inspectResult['Config']['Env'] ?? []);
        $variables = [];

        foreach ($rawEnvironment as $variable) {
            [$key, $value] = explode(self::SEPARATOR, $variable, self::LIMIT);
            $variables[$key] = $value;
        }

        return EnvironmentVariables::from(variables: Collection::createFrom(elements: $variables));
    }
}
