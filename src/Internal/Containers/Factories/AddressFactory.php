<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Factories;

use TinyBlocks\DockerContainer\Internal\Containers\Models\Address\Address;

final readonly class AddressFactory
{
    public function buildFrom(array $data): Address
    {
        $networks = $data['NetworkSettings']['Networks'];
        $configuration = $data['Config'];
        $ports = $configuration['ExposedPorts'] ?? [];

        $address = [
            'ip'       => $networks[key($networks)]['IPAddress'],
            'ports'    => [
                'exposedPorts' => array_map(fn($port) => (int)explode('/', $port)[0], array_keys($ports))
            ],
            'hostname' => $configuration['Hostname']
        ];

        return Address::from(data: $address);
    }
}
