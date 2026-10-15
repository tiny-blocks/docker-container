<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

use TinyBlocks\Collection\Collection;
use TinyBlocks\DockerContainer\Internal\Containers\Image;
use TinyBlocks\DockerContainer\Internal\Containers\Name;

final readonly class ContainerDefinition
{
    private function __construct(
        public Name $name,
        public Image $image,
        public Removal $removal,
        public Collection $networks,
        public Collection $tmpfsMounts,
        public Collection $portMappings,
        public Collection $volumeMappings,
        public Collection $copyInstructions,
        public Collection $environmentVariables
    ) {
    }

    public static function create(string $image): ContainerDefinition
    {
        return new ContainerDefinition(
            name: Name::generated(),
            image: Image::from(image: $image),
            removal: Removal::AUTOMATIC,
            networks: Collection::createFromEmpty(),
            tmpfsMounts: Collection::createFromEmpty(),
            portMappings: Collection::createFromEmpty(),
            volumeMappings: Collection::createFromEmpty(),
            copyInstructions: Collection::createFromEmpty(),
            environmentVariables: Collection::createFromEmpty()
        );
    }

    public function withName(string $name): ContainerDefinition
    {
        return new ContainerDefinition(
            name: Name::from(value: $name),
            image: $this->image,
            removal: $this->removal,
            networks: $this->networks,
            tmpfsMounts: $this->tmpfsMounts,
            portMappings: $this->portMappings,
            volumeMappings: $this->volumeMappings,
            copyInstructions: $this->copyInstructions,
            environmentVariables: $this->environmentVariables
        );
    }

    public function toArguments(): array
    {
        return $this->portMappings
            ->merge(other: $this->networks)
            ->merge(other: $this->volumeMappings)
            ->merge(other: $this->tmpfsMounts)
            ->add($this->removal)
            ->merge(other: $this->environmentVariables)
            ->reduce(
                accumulator: static fn(array $arguments, ContainerOption $option): array => [
                    ...$arguments,
                    ...$option->toArguments()
                ],
                initial: []
            );
    }

    public function withNetwork(string $name): ContainerDefinition
    {
        return new ContainerDefinition(
            name: $this->name,
            image: $this->image,
            removal: $this->removal,
            networks: Collection::createFrom(elements: [Network::from(name: $name)]),
            tmpfsMounts: $this->tmpfsMounts,
            portMappings: $this->portMappings,
            volumeMappings: $this->volumeMappings,
            copyInstructions: $this->copyInstructions,
            environmentVariables: $this->environmentVariables
        );
    }

    public function withTmpfsMount(string $pathOnContainer): ContainerDefinition
    {
        return new ContainerDefinition(
            name: $this->name,
            image: $this->image,
            removal: $this->removal,
            networks: $this->networks,
            tmpfsMounts: $this->tmpfsMounts->add(TmpfsMount::from(pathOnContainer: $pathOnContainer)),
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
            removal: $this->removal,
            networks: $this->networks,
            tmpfsMounts: $this->tmpfsMounts,
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
            removal: $this->removal,
            networks: $this->networks,
            tmpfsMounts: $this->tmpfsMounts,
            portMappings: $this->portMappings,
            volumeMappings: $this->volumeMappings->add(
                VolumeMapping::from(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer)
            ),
            copyInstructions: $this->copyInstructions,
            environmentVariables: $this->environmentVariables
        );
    }

    public function withoutAutoRemove(): ContainerDefinition
    {
        return new ContainerDefinition(
            name: $this->name,
            image: $this->image,
            removal: Removal::MANUAL,
            networks: $this->networks,
            tmpfsMounts: $this->tmpfsMounts,
            portMappings: $this->portMappings,
            volumeMappings: $this->volumeMappings,
            copyInstructions: $this->copyInstructions,
            environmentVariables: $this->environmentVariables
        );
    }

    public function withCopyInstruction(string $pathOnHost, string $pathOnContainer): ContainerDefinition
    {
        return new ContainerDefinition(
            name: $this->name,
            image: $this->image,
            removal: $this->removal,
            networks: $this->networks,
            tmpfsMounts: $this->tmpfsMounts,
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
            removal: $this->removal,
            networks: $this->networks,
            tmpfsMounts: $this->tmpfsMounts,
            portMappings: $this->portMappings,
            volumeMappings: $this->volumeMappings,
            copyInstructions: $this->copyInstructions,
            environmentVariables: $this->environmentVariables->add(
                EnvironmentVariable::from(key: $key, value: $value)
            )
        );
    }
}
