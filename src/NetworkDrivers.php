<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

/**
 * Network drivers supported by Docker.
 *
 * @see https://docs.docker.com/engine/network/drivers
 */
enum NetworkDrivers: string
{
    case NONE = 'none';
    case HOST = 'host';
    case BRIDGE = 'bridge';
    case IPVLAN = 'ipvlan';
    case OVERLAY = 'overlay';
    case MACVLAN = 'macvlan';
}
