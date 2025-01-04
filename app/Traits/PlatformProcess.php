<?php

namespace App\Traits;

trait PlatformProcess
{
    /**
     * Determines if the current platform is Windows.
     *
     * @return bool
     */
    private function isWindows(): bool
    {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }

    /**
     * Gets the default output file for the platform.
     *
     * @return string
     */
    private function getDefaultOutputFile(): string
    {
        return $this->isWindows() ? 'NUL' : '/dev/null';
    }

    /**
     * Builds the platform-specific command to run the process.
     *
     * @param string $command
     * @param string $stdoutFile
     * @param string $stderrFile
     * @param string|null $input
     * @return string
     */
    private function buildCommand(string $command, string $stdoutFile, string $stderrFile, ?string $input = null): string
    {
        if ($this->isWindows()) {
            // Windows command
            $builtCommand = sprintf('start /B %s > %s 2>%s', $command, $stdoutFile, $stderrFile);

            if ($input) {
                throw new \RuntimeException('Input redirection is not supported on Windows in this implementation.');
            }
        } else {
            // Unix-like command
            $builtCommand = sprintf('%s > %s 2>%s', $command, $stdoutFile, $stderrFile);

            if ($input) {
                $builtCommand .= ' <<< ' . escapeshellarg($input);
            }

            $builtCommand .= ' & echo $!';
        }

        return $builtCommand;
    }

    /**
     * Executes the platform-specific command and returns the process ID (PID).
     *
     * @param string $command
     * @return int
     */
    private function executeCommand(string $command): int
    {
        if ($this->isWindows()) {
            // Windows execution
            $pid = shell_exec($command);
            return $pid ?: 0;
        } else {
            // Unix-like execution
            $tmpFile = tmpfile();
            $meta = stream_get_meta_data($tmpFile);
            $tmpFilename = $meta['uri'];

            fwrite($tmpFile, "#!/bin/bash\n\n$command");

            $pid = (int) trim(shell_exec('bash ' . $tmpFilename));
            fclose($tmpFile);

            return $pid;
        }
    }
}
