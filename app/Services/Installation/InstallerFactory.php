<?php

namespace Turso\PHP\Installer\Services\Installation;

use Turso\PHP\Installer\Services\Installation\Platforms\LinuxInstaller;
use Turso\PHP\Installer\Services\Installation\Platforms\MacInstaller;
use Turso\PHP\Installer\Services\Installation\Platforms\WindowsInstaller;

class InstallerFactory
{
    public static function create(): BaseInstaller
    {
        $os = strtolower(php_uname('s'));

        return match (true) {
            str_contains($os, 'darwin') => new MacInstaller,
            str_contains($os, 'linux') => new LinuxInstaller,
            str_contains($os, 'windows') => new WindowsInstaller,
            default => throw new \Exception('Unsupported OS'),
        };
    }
}
