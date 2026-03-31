<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Image;
use TinyBlocks\DockerContainer\Internal\Containers\Models\Name;

final readonly class ContainerDefinition
{
    private function __construct(
        public Name $name,
        public Image $image,
        public ?string $network,
        public bool $autoRemove,
        public Collection $portMappings,
        public Collection $volumeMappings,
        public Collection $copyInstructions,
        public Collection $environmentVariables
    ) {
    }

    public static function create(string $image, ?string $name = null): ContainerDefinition
    {
        return new ContainerDefinition(
            name: Name::from(value: $name),
            image: Image::from(image: $image),
            network: null,
            autoRemove: true,
            portMappings: Collection::createFromEmpty(),
            volumeMappings: Collection::createFromEmpty(),
            copyInstructions: Collection::createFromEmpty(),
            environmentVariables: Collection::createFromEmpty()
        );
    }

    public function withNetwork(string $name): ContainerDefinition
    {
        return new ContainerDefinition(
            name: $this->name,
            image: $this->image,
            network: $name,
            autoRemove: $this->autoRemove,
            portMappings: $this->portMappings,
            volumeMappings: $this->volumeMappings,
            copyInstructions: $this->copyInstructions,
            environmentVariables: $this->environmentVariables
        );
    }

    public function withPortMapping(int $portOnHost, int $portOnContainer): ContainerDefinition
    {
        return new ContainerDefinition(
            name: $this->name,
            image: $this->image,
            network: $this->network,
            autoRemove: $this->autoRemove,
            portMappings: $this->portMappings->add(
                PortMapping::from(portOnHost: $portOnHost, portOnContainer: $portOnContainer)
            ),
            volumeMappings: $this->volumeMappings,
            copyInstructions: $this->copyInstructions,
            environmentVariables: $this->environmentVariables
        );
    }

    public function withVolumeMapping(string $pathOnHost, string $pathOnContainer): ContainerDefinition
    {
        return new ContainerDefinition(
            name: $this->name,
            image: $this->image,
            network: $this->network,
            autoRemove: $this->autoRemove,
            portMappings: $this->portMappings,
            volumeMappings: $this->volumeMappings->add(
                VolumeMapping::from(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer)
            ),
            copyInstructions: $this->copyInstructions,
            environmentVariables: $this->environmentVariables
        );
    }

    public function withCopyInstruction(string $pathOnHost, string $pathOnContainer): ContainerDefinition
    {
        return new ContainerDefinition(
            name: $this->name,
            image: $this->image,
            network: $this->network,
            autoRemove: $this->autoRemove,
            portMappings: $this->portMappings,
            volumeMappings: $this->volumeMappings,
            copyInstructions: $this->copyInstructions->add(
                CopyInstruction::from(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer)
            ),
            environmentVariables: $this->environmentVariables
        );
    }

    public function withEnvironmentVariable(string $key, string $value): ContainerDefinition
    {
        return new ContainerDefinition(
            name: $this->name,
            image: $this->image,
            network: $this->network,
            autoRemove: $this->autoRemove,
            portMappings: $this->portMappings,
            volumeMappings: $this->volumeMappings,
            copyInstructions: $this->copyInstructions,
            environmentVariables: $this->environmentVariables->add(
                EnvironmentVariable::from(key: $key, value: $value)
            )
        );
    }

    public function withoutAutoRemove(): ContainerDefinition
    {
        return new ContainerDefinition(
            name: $this->name,
            image: $this->image,
            network: $this->network,
            autoRemove: false,
            portMappings: $this->portMappings,
            volumeMappings: $this->volumeMappings,
            copyInstructions: $this->copyInstructions,
            environmentVariables: $this->environmentVariables
        );
    }
}
