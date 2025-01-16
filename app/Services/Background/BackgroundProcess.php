<?php

namespace Turso\PHP\Installer\Services\Background;

use Turso\PHP\Installer\Contracts\Background;
use Turso\PHP\Installer\Traits\PlatformProcess;

/**
 * Class BackgroundProcess.
 * This class manages the execution of background processes.
 */
class BackgroundProcess implements Background
{
    use PlatformProcess;

    /**
     * @var string The command to be executed.
     */
    private $command = '';

    /**
     * @var string|null The input data for the command.
     */
    private $input = '';

    /**
     * @var string The file to which the standard output is redirected.
     */
    private $stdoutFile;

    /**
     * @var string The file to which the standard error is redirected.
     */
    private $stderrFile;

    /**
     * Initializes the BackgroundProcess instance.
     *
     * Sets the standard output and error redirection to the default output file.
     *
     * @return void
     */
    public function __construct()
    {
        $this->stdoutFile = $this->getDefaultOutputFile();
        $this->stderrFile = $this->getDefaultOutputFile();
    }

    /**
     * Set the command to be executed.
     *
     * @param string|callable $command The command as a string or a callable returning a string.
     *
     * @return static
     *
     * @throws \InvalidArgumentException if $command is not valid or empty.
     */
    public function withCommand($command): BackgroundProcess
    {
        if (is_callable($command)) {
            $command = $command();
        }

        if (!is_string($command) || empty($command)) {
            throw new \InvalidArgumentException('$command must be a non-empty string or a callable returning a non-empty string');
        }

        $this->command = $command;

        return $this;
    }

    /**
     * Sets the input data for the command.
     *
     * @param string $input The input data for the command.
     *
     * @return static
     */
    public function withInput($input): BackgroundProcess
    {
        $this->input = $input;

        return $this;
    }

    /**
     * Sets the file to which the standard output is redirected.
     *
     * @param string $stdoutFile The file to which the standard output is redirected.
     *
     * @return static
     */
    public function withStdoutFile($stdoutFile): BackgroundProcess
    {
        $this->stdoutFile = (string) $stdoutFile;

        return $this;
    }

    /**
     * Sets the file to which the standard error is redirected.
     *
     * @param string $stderrFile The file to which the standard error is redirected.
     *
     * @return static
     */
    public function withStderrFile($stderrFile): BackgroundProcess
    {
        $this->stderrFile = (string) $stderrFile;

        return $this;
    }

    /**
     * Execute the command in the background.
     *
     * @return int The process ID (PID) of the newly created process.
     *
     * @throws \InvalidArgumentException if ::withCommand() has not been called before calling this method.
     */
    public function run(): int
    {
        if (empty($this->command)) {
            throw new \InvalidArgumentException(__CLASS__ . '::withCommand() must be called before calling ' . __CLASS__ . '::run()');
        }

        $command = $this->buildCommand($this->command, $this->stdoutFile, $this->stderrFile, $this->input);

        return $this->executeCommand($command);
    }
}
