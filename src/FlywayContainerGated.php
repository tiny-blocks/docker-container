<?php

declare(strict_types=1);

namespace TinyBlocks\DockerContainer;

use TinyBlocks\DockerContainer\Exceptions\ContainerExitUnsuccessful;
use TinyBlocks\DockerContainer\Exceptions\ContainerWaitTimeout;
use TinyBlocks\DockerContainer\Exceptions\DockerCommandExecutionFailed;
use TinyBlocks\DockerContainer\Internal\Containers\Environment\EnvironmentFlag;

/**
 * Flyway container gated by an environment variable, whose commands run only while the variable is enabled.
 *
 * <p>The variable is enabled when its value is <code>1</code>, and it is read each time a command is called. While
 * it is not enabled, a command skips the run entirely and returns <code>null</code>, so no Docker command is issued,
 * neither for Flyway nor for its MySQL source.</p>
 */
final readonly class FlywayContainerGated
{
    private function __construct(private EnvironmentFlag $flag, private FlywayDockerContainer $flyway)
    {
    }

    /**
     * Creates a FlywayContainerGated from a Flyway definition and the variable that enables it.
     *
     * @param FlywayDockerContainer $flyway The Flyway definition each command runs.
     * @param string $variable The name of the environment variable that enables the commands.
     * @return FlywayContainerGated The created gated container.
     */
    public static function from(FlywayDockerContainer $flyway, string $variable): FlywayContainerGated
    {
        return new FlywayContainerGated(flag: EnvironmentFlag::from(variable: $variable), flyway: $flyway);
    }

    /**
     * Runs the Flyway repair command when the variable is enabled, and blocks until Flyway exits.
     *
     * @return ExecutionCompleted|null The successful outcome, or null when the variable is not enabled and nothing
     *                                 ran.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If the MySQL source is not ready or Flyway does not exit within the timeout.
     * @throws ContainerExitUnsuccessful If Flyway exits with a non-zero status, carrying the Flyway error output.
     */
    public function repair(): ?ExecutionCompleted
    {
        return $this->flag->isEnabled() ? $this->flyway->repair() : null;
    }

    /**
     * Runs the Flyway migrate command when the variable is enabled, and blocks until Flyway exits.
     *
     * @return ExecutionCompleted|null The successful outcome, or null when the variable is not enabled and nothing
     *                                 ran.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If the MySQL source is not ready or Flyway does not exit within the timeout.
     * @throws ContainerExitUnsuccessful If Flyway exits with a non-zero status, carrying the Flyway error output.
     */
    public function migrate(): ?ExecutionCompleted
    {
        return $this->flag->isEnabled() ? $this->flyway->migrate() : null;
    }

    /**
     * Runs the Flyway validate command when the variable is enabled, and blocks until Flyway exits.
     *
     * @return ExecutionCompleted|null The successful outcome, or null when the variable is not enabled and nothing
     *                                 ran.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If the MySQL source is not ready or Flyway does not exit within the timeout.
     * @throws ContainerExitUnsuccessful If Flyway exits with a non-zero status, including a failed validation.
     */
    public function validate(): ?ExecutionCompleted
    {
        return $this->flag->isEnabled() ? $this->flyway->validate() : null;
    }

    /**
     * Runs the Flyway clean command followed by migrate when the variable is enabled, and blocks until Flyway exits.
     *
     * @return ExecutionCompleted|null The successful outcome, or null when the variable is not enabled and nothing
     *                                 ran.
     * @throws DockerCommandExecutionFailed If a Docker command fails.
     * @throws ContainerWaitTimeout If the MySQL source is not ready or Flyway does not exit within the timeout.
     * @throws ContainerExitUnsuccessful If Flyway exits with a non-zero status, carrying the Flyway error output.
     */
    public function cleanAndMigrate(): ?ExecutionCompleted
    {
        return $this->flag->isEnabled() ? $this->flyway->cleanAndMigrate() : null;
    }
}
