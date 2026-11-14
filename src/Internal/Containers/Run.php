<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Internal\Containers;

final readonly class Run
{
    private const string LABEL_KEY = 'tiny-blocks.docker-container.run';

    private function __construct(public string $identifier)
    {
    }

    public static function current(): Run
    {
        $template = '%s-%d';

        return new Run(
            identifier: sprintf($template, HostEnvironment::containerHostname(), HostEnvironment::processId())
        );
    }

    public function label(): string
    {
        $template = '%s=%s';

        return sprintf($template, self::LABEL_KEY, $this->identifier);
    }

    public function filter(): string
    {
        $template = 'label=%s';

        return sprintf($template, $this->label());
    }
}
