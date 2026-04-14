<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer\Waits;

use TinyBlocks\DockerContainer\Internal\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Waits\Conditions\ContainerReady;

final readonly class ContainerWaitForDependency implements ContainerWaitBeforeStarted
{
    private function __construct(
        private ContainerReady $condition,
        private int $timeoutInSeconds,
        private int $pollIntervalInMicroseconds
    ) {
    }

    public static function untilReady(
        ContainerReady $condition,
        int $timeoutInSeconds = self::DEFAULT_TIMEOUT_IN_SECONDS,
        int $pollIntervalInMicroseconds = self::DEFAULT_POLL_INTERVAL_IN_MICROSECONDS
    ): ContainerWaitForDependency {
        return new ContainerWaitForDependency(
            condition: $condition,
            timeoutInSeconds: $timeoutInSeconds,
            pollIntervalInMicroseconds: $pollIntervalInMicroseconds
        );
    }

    public function waitBefore(): void
    {
        $deadline = microtime(true) + $this->timeoutInSeconds;

        $ready = $this->condition->isReady();

        while (!$ready && microtime(true) < $deadline) {
            usleep($this->pollIntervalInMicroseconds);
            $ready = $this->condition->isReady();
        }

        if (!$ready) {
            throw new ContainerWaitTimeout(timeoutInSeconds: $this->timeoutInSeconds);
        }
    }
}
