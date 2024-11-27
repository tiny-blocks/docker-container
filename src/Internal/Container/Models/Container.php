<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Container\Models;

use TinyBlocks\DockerContainer\Internal\Container\Models\Address\Address;
use TinyBlocks\DockerContainer\Internal\Container\Models\Environment\EnvironmentVariables;

final readonly class Container
{
    private function __construct(
        public Name $name,
        public Image $image,
        public Address $address,
        public EnvironmentVariables $environmentVariables,
        public ?ContainerId $id = null
    ) {
    }

    public static function from(
        ?ContainerId $id,
        Name $name,
        Image $image,
        Address $address,
        EnvironmentVariables $environmentVariables
    ): Container {
        return new Container(
            name: $name,
            image: $image,
            address: $address,
            environmentVariables: $environmentVariables,
            id: $id
        );
    }

    public static function create(?string $name, string $image): Container
    {
        $name = Name::from(value: $name);
        $image = Image::from(image: $image);
        $address = Address::create();
        $environmentVariables = EnvironmentVariables::createFromEmpty();

        return new Container(
            name: $name,
            image: $image,
            address: $address,
            environmentVariables: $environmentVariables
        );
    }

    public function hasId(): bool
    {
        return $this->id !== null;
    }
}
