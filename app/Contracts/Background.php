<?php

namespace Turso\PHP\Installer\Contracts;

/**
 * Interface Background
 *
 * This interface defines methods for managing background processes.
 */
interface Background
{
    /**
     * Set the command to be executed.
     *
     * @param string|callable $command The command to execute.
     * @return static
     */
    public function withCommand($command);

    /**
     * Sets the input data for the command.
     *
     * @param string $input The input data for the command.
     * @return static
     */
    public function withInput($input);

    /**
     * Sets the file to which the standard output is redirected.
     *
     * @param string $stdoutFile The file to which the standard output is redirected.
     * @return static
     */
    public function withStdoutFile($stdoutFile);

    /**
     * Sets the file to which the standard error is redirected.
     *
     * @param string $stderrFile The file to which the standard error is redirected.
     * @return static
     */
    public function withStderrFile($stderrFile);

    /**
     * Execute the command in the background.
     *
     * @return int The process ID (PID) of the newly created process.
     */
    public function run();
}
