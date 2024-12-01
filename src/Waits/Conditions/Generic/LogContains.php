<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits\Conditions\Generic;

use TinyBlocks\DockerContainer\Contracts\ContainerStarted;
use TinyBlocks\DockerContainer\Waits\Conditions\ExecutionCompleted;

final class LogContains implements ExecutionCompleted
{
    private int $lastProcessedLength = 0;

    private function __construct(private readonly string $text)
    {
    }

    public static function from(string $text): LogContains
    {
        return new LogContains(text: $text);
    }

    public function isCompleteOn(ContainerStarted $containerStarted): bool
    {
        $currentLogs = $containerStarted->getLogs();

        if (empty($currentLogs)) {
            return false;
        }

        $newLogs = substr($currentLogs, $this->lastProcessedLength);
        $this->lastProcessedLength += strlen($newLogs);

        return str_contains($newLogs, $this->text);
    }
}
