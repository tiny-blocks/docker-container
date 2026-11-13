<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers\Definitions;

use TinyBlocks\Collection\Collection;
use TinyBlocks\Collection\KeyPreservation;
use TinyBlocks\DockerContainer\Internal\Containers\Image;
use TinyBlocks\DockerContainer\Internal\Containers\Name;
use TinyBlocks\Time\Duration;

final readonly class ContainerDefinition
{
    private const int SINGLE_START_ATTEMPT = 1;
    private const int START_ATTEMPTS_ON_A_NETWORK = 2;
    private const int DEFAULT_PULL_TIMEOUT_IN_SECONDS = 300;

    private function __construct(
        public Name $name,
        public Image $image,
        public Collection $networks,
        public Duration $pullTimeout,
        public Collection $tmpfsMounts,
        public Collection $portMappings,
        public Collection $volumeMappings,
        public Collection $copyInstructions,
        public Collection $environmentVariables,
        public Removal $removal = Removal::AUTOMATIC
    ) {
    }

    public static function create(string $image): ContainerDefinition
    {
        return new ContainerDefinition(
            name: Name::generated(),
            image: Image::from(image: $image),
            networks: Collection::createFromEmpty(),
            pullTimeout: Duration::fromSeconds(seconds: self::DEFAULT_PULL_TIMEOUT_IN_SECONDS),
            tmpfsMounts: Collection::createFromEmpty(),
            portMappings: Collection::createFromEmpty(),
            volumeMappings: Collection::createFromEmpty(),
            copyInstructions: Collection::createFromEmpty(),
            environmentVariables: Collection::createFromEmpty()
        );
    }

    public function withName(string $name): ContainerDefinition
    {
        return clone($this, ['name' => Name::from(value: $name)]);
    }

    public function toArguments(): array
    {
        return $this->portMappings
            ->merge(other: $this->networks)
            ->merge(other: $this->volumeMappings)
            ->merge(other: $this->tmpfsMounts)
            ->add($this->removal)
            ->merge(other: $this->environmentVariables)
            ->map(transformations: static fn(ContainerOption $option): array => $option->toArguments())
            ->flatten()
            ->toArray(keyPreservation: KeyPreservation::DISCARD);
    }

    public function withNetwork(string $name): ContainerDefinition
    {
        return clone($this, ['networks' => Collection::createFrom(elements: [Network::from(name: $name)])]);
    }

    public function startAttempts(): int
    {
        return $this->networks->isEmpty() ? self::SINGLE_START_ATTEMPT : self::START_ATTEMPTS_ON_A_NETWORK;
    }

    public function withTmpfsMount(string $pathOnContainer): ContainerDefinition
    {
        return clone($this, [
            'tmpfsMounts' => $this->tmpfsMounts->add(TmpfsMount::from(pathOnContainer: $pathOnContainer))
        ]);
    }

    public function withPortMapping(int $portOnHost, int $portOnContainer): ContainerDefinition
    {
        return clone($this, [
            'portMappings' => $this->portMappings->add(
                PortMapping::from(portOnHost: $portOnHost, portOnContainer: $portOnContainer)
            )
        ]);
    }

    public function withPullTimeout(Duration $timeout): ContainerDefinition
    {
        return clone($this, ['pullTimeout' => $timeout]);
    }

    public function withVolumeMapping(string $pathOnHost, string $pathOnContainer): ContainerDefinition
    {
        return clone($this, [
            'volumeMappings' => $this->volumeMappings->add(
                VolumeMapping::from(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer)
            )
        ]);
    }

    public function withoutAutoRemove(): ContainerDefinition
    {
        return clone($this, ['removal' => Removal::MANUAL]);
    }

    public function withCopyInstruction(string $pathOnHost, string $pathOnContainer): ContainerDefinition
    {
        return clone($this, [
            'copyInstructions' => $this->copyInstructions->add(
                CopyInstruction::from(pathOnHost: $pathOnHost, pathOnContainer: $pathOnContainer)
            )
        ]);
    }

    public function withEnvironmentVariable(string $key, string $value): ContainerDefinition
    {
        return clone($this, [
            'environmentVariables' => $this->environmentVariables->add(
                EnvironmentVariable::from(key: $key, value: $value)
            )
        ]);
    }
}
